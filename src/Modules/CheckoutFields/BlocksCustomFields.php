<?php
/**
 * Custom fields on the block checkout.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;
use Throwable;

/**
 * Registers stored custom fields as WooCommerce additional checkout fields.
 *
 * This is the only way to put a field of our own on the block checkout, and it
 * is a narrow one. WooCommerce accepts exactly three types, read from
 * `CheckoutFields::$supported_field_types`:
 *
 *     text, select, checkbox
 *
 * The editor offers twelve. Four more map onto `text` by giving up their input
 * type, and the rest cannot be expressed at all: a date picker, a file upload,
 * a fee that changes the cart total, and a repeater are each their own block
 * to build. Those are skipped here rather than registered as something they
 * are not, because a date field that silently became a free-text box would be
 * worse than one that is honestly absent.
 *
 * Location follows the section. An `order` field becomes an order field. A
 * billing or shipping field becomes an `address` field, which is the faithful
 * mapping and carries a consequence worth knowing: WooCommerce renders address
 * fields under *both* addresses. As with the locale, the block checkout has no
 * way to say "billing only".
 */
final class BlocksCustomFields {

	/**
	 * Editor types WooCommerce accepts directly.
	 */
	private const NATIVE_TYPES = array(
		'text'   => 'text',
		'select' => 'select',
	);

	/**
	 * Editor types that survive only as free text.
	 *
	 * Registering these loses the browser's own validation and keypad. It is
	 * still better than dropping the field, because the merchant asked for the
	 * question and the answer is what matters.
	 */
	private const DEGRADED_TYPES = array( 'number', 'email', 'url', 'textarea' );

	/**
	 * Sections that become address fields.
	 */
	private const ADDRESS_SECTIONS = array( 'billing', 'shipping' );

	/**
	 * Namespace WooCommerce requires on every field id.
	 */
	private const NAMESPACE_PREFIX = 'scfe/';

	/**
	 * Create the custom field registrar.
	 *
	 * @param CheckoutFieldsRepository $checkout_fields Stored field settings.
	 * @param FieldOptionsParser       $options         Select option parser.
	 * @param CheckoutBlocks           $blocks          Which checkout is configured.
	 */
	public function __construct(
		private CheckoutFieldsRepository $checkout_fields,
		private FieldOptionsParser $options,
		private CheckoutBlocks $blocks
	) {
		// The translator depends on this object's field-id mapping.
		$this->conditions = new BlocksConditionRules( $this );
	}

	/**
	 * Condition translator.
	 *
	 * @var BlocksConditionRules
	 */
	private BlocksConditionRules $conditions;

	/**
	 * Return the currency's decimal places.
	 *
	 * The editor stores a cart total as a decimal and the document object
	 * holds one as an integer in the smallest unit, so the two only meet
	 * through the store's own precision.
	 */
	private function decimals(): int {
		return function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
	}

	/**
	 * Declare every custom field the block checkout can render.
	 *
	 * Runs on `woocommerce_init`, which is the only point WooCommerce accepts
	 * these. Registering later misses the Store API schema and the field
	 * silently does nothing.
	 */
	public function register(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) || ! $this->blocks->in_use() ) {
			return;
		}

		foreach ( $this->definitions( $this->checkout_fields->fields() ) as $definition ) {
			try {
				woocommerce_register_additional_checkout_field( $definition );
			} catch ( Throwable $e ) {
				// Skip a rejected field without taking down the remaining checkout fields.
				continue;
			}
		}
	}

	/**
	 * Translate the stored configuration into field definitions.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $stored Stored settings.
	 * @return array<int, array<string, mixed>>
	 */
	public function definitions( array $stored ): array {
		$definitions = array();

		foreach ( $stored as $section => $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}

			foreach ( $fields as $stored_key => $settings ) {
				$definition = $this->definition( (string) $section, (string) $stored_key, $settings );

				if ( null === $definition ) {
					continue;
				}

				// Billing and shipping can share a key, but WooCommerce allows one registration.
				if ( isset( $definitions[ $definition['id'] ] ) ) {
					continue;
				}

				$definitions[ $definition['id'] ] = $definition;
			}
		}

		return array_values( $definitions );
	}

	/**
	 * Build one field definition, or refuse the field.
	 *
	 * @param string $section    Stored section.
	 * @param string $stored_key Stored field key.
	 * @param mixed  $settings   Stored settings.
	 * @return array<string, mixed>|null
	 */
	private function definition( string $section, string $stored_key, $settings ): ?array {
		if ( ! is_array( $settings ) || empty( $settings['custom'] ) ) {
			return null;
		}

		if ( isset( $settings['enabled'] ) && ! $settings['enabled'] ) {
			return null;
		}

		$type = $this->block_type( (string) ( $settings['type'] ?? 'text' ) );

		if ( null === $type ) {
			return null;
		}

		$id = $this->field_id( $section, $stored_key );

		if ( '' === $id ) {
			return null;
		}

		$label = isset( $settings['label'] ) && is_string( $settings['label'] ) && '' !== $settings['label']
			? $settings['label']
			: $stored_key;

		$definition = array(
			'id'       => $id,
			'label'    => $label,
			'location' => in_array( $section, self::ADDRESS_SECTIONS, true ) ? 'address' : 'order',
			'type'     => $type,
			'required' => ! empty( $settings['required'] ),
		);

		if ( 'select' === $type ) {
			$options = $this->select_options( $settings['options'] ?? '' );

			if ( array() === $options ) {
				// WooCommerce rejects select fields without options.
				return null;
			}

			$definition['options'] = $options;
		}

		$hidden = $this->conditions->for_field( $settings, $this->decimals() );

		if ( array() !== $hidden ) {
			$definition['hidden'] = $hidden;
		}

		return $definition;
	}

	/**
	 * Map an editor field type onto one WooCommerce accepts.
	 *
	 * @param string $type Editor type.
	 * @return string|null WooCommerce type, or null when it cannot be carried.
	 */
	private function block_type( string $type ): ?string {
		if ( isset( self::NATIVE_TYPES[ $type ] ) ) {
			return self::NATIVE_TYPES[ $type ];
		}

		return in_array( $type, self::DEGRADED_TYPES, true ) ? 'text' : null;
	}

	/**
	 * Build the namespaced id WooCommerce requires.
	 *
	 * Public because the order screen has to look a value up by it. WooCommerce
	 * stores an additional field under its own id, not under the key this
	 * plugin knows the field by, so the two have to be able to meet.
	 *
	 * @param string $section    Stored section.
	 * @param string $stored_key Stored field key.
	 */
	public function field_id( string $section, string $stored_key ): string {
		$name = $stored_key;

		foreach ( array( $section . '_', 'billing_', 'shipping_', 'order_' ) as $prefix ) {
			if ( str_starts_with( $name, $prefix ) ) {
				$name = substr( $name, strlen( $prefix ) );
				break;
			}
		}

		// WooCommerce accepts lowercase letters, digits and hyphens only.
		$name = strtolower( (string) preg_replace( '/[^a-zA-Z0-9]+/', '-', $name ) );
		$name = trim( $name, '-' );

		return '' === $name ? '' : self::NAMESPACE_PREFIX . $name;
	}

	/**
	 * Convert stored option text into the shape WooCommerce expects.
	 *
	 * @param mixed $stored Stored option text.
	 * @return array<int, array{value: string, label: string}>
	 */
	private function select_options( $stored ): array {
		$options = array();

		foreach ( $this->options->parse( $stored ) as $value => $label ) {
			$options[] = array(
				'value' => (string) $value,
				'label' => (string) $label,
			);
		}

		return $options;
	}
}
