<?php
/**
 * Transform WooCommerce default address fields.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

/**
 * Applies billing settings to WooCommerce's shared address definitions.
 */
final class DefaultAddressFieldsTransformer {

	/**
	 * Billing keys whose shared definition WooCommerce's address script
	 * re-applies on the page, through `woocommerce_country_locale_field_selectors`.
	 *
	 * Phone joined those selectors in WooCommerce 11, so its locale priority
	 * and requirement now override the rendered row unless set here.
	 */
	private const ADDRESS_MAP = array(
		'billing_phone'     => 'phone',
		'billing_country'   => 'country',
		'billing_address_1' => 'address_1',
		'billing_address_2' => 'address_2',
		'billing_city'      => 'city',
		'billing_state'     => 'state',
		'billing_postcode'  => 'postcode',
	);

	private const NAME_MAP = array(
		'billing_first_name' => 'first_name',
		'billing_last_name'  => 'last_name',
	);

	/**
	 * Apply stored billing settings to default address fields.
	 *
	 * @param array<string, array<string, mixed>> $address_fields WooCommerce address fields.
	 * @param array<string, array<string, mixed>> $billing_settings Stored billing settings.
	 * @return array<string, array<string, mixed>>
	 */
	public function transform( array $address_fields, array $billing_settings ): array {
		$priority = 0;

		foreach ( $billing_settings as $billing_key => $settings ) {
			$priority += 10;

			if ( isset( self::ADDRESS_MAP[ $billing_key ] ) ) {
				$address_fields = $this->apply_address_field(
					$address_fields,
					self::ADDRESS_MAP[ $billing_key ],
					$settings,
					$priority
				);
			}
		}

		return $this->apply_name_fields( $address_fields, $billing_settings );
	}

	/**
	 * Apply one mapped address setting.
	 *
	 * @param array<string, array<string, mixed>> $address_fields Current fields.
	 * @param string                              $address_key Mapped WooCommerce key.
	 * @param array<string, mixed>                $settings Stored settings.
	 * @param int                                 $priority Calculated priority.
	 * @return array<string, array<string, mixed>>
	 */
	private function apply_address_field(
		array $address_fields,
		string $address_key,
		array $settings,
		int $priority
	): array {
		if ( ! isset( $address_fields[ $address_key ] ) ) {
			return $address_fields;
		}

		if ( isset( $settings['enabled'] ) && ! $settings['enabled'] ) {
			unset( $address_fields[ $address_key ] );
			return $address_fields;
		}

		$current_field                  = $address_fields[ $address_key ];
		$address_fields[ $address_key ] = $this->decorate_address_field( $current_field, $settings, $priority );

		return $address_fields;
	}

	/**
	 * Apply the existing address presentation subset.
	 *
	 * @param array<string, mixed> $field Current address field.
	 * @param array<string, mixed> $settings Stored settings.
	 * @param int                  $priority Calculated priority.
	 * @return array<string, mixed>
	 */
	private function decorate_address_field( array $field, array $settings, int $priority ): array {
		$field['priority'] = $priority;
		$field['class']    = FieldWidthClasses::replace_layout( array_merge( array( 'form-row' ), (array) ( $field['class'] ?? array() ) ) );

		if ( array_key_exists( 'required', $settings ) ) {
			$field['required'] = (bool) $settings['required'];
		}

		if ( isset( $settings['label'] ) && is_string( $settings['label'] ) && '' !== $settings['label'] ) {
			$field['label'] = sanitize_text_field( $settings['label'] );
		}

		if ( isset( $settings['placeholder'] ) && is_string( $settings['placeholder'] ) ) {
			$field['placeholder'] = sanitize_text_field( $settings['placeholder'] );
		}

		return $field;
	}

	/**
	 * Apply the historical enabled/required behavior for name fields.
	 *
	 * @param array<string, array<string, mixed>> $address_fields Current fields.
	 * @param array<string, array<string, mixed>> $billing_settings Stored billing settings.
	 * @return array<string, array<string, mixed>>
	 */
	private function apply_name_fields( array $address_fields, array $billing_settings ): array {
		foreach ( self::NAME_MAP as $billing_key => $address_key ) {
			if ( isset( $billing_settings[ $billing_key ] ) ) {
				$address_fields = $this->apply_name_field(
					$address_fields,
					$address_key,
					$billing_settings[ $billing_key ]
				);
			}
		}

		return $address_fields;
	}

	/**
	 * Apply one name-field enabled/required setting.
	 *
	 * @param array<string, array<string, mixed>> $address_fields Current fields.
	 * @param string                              $address_key Mapped WooCommerce key.
	 * @param array<string, mixed>                $settings Stored settings.
	 * @return array<string, array<string, mixed>>
	 */
	private function apply_name_field( array $address_fields, string $address_key, array $settings ): array {
		if ( isset( $settings['enabled'] ) && ! $settings['enabled'] ) {
			unset( $address_fields[ $address_key ] );
		} elseif ( isset( $address_fields[ $address_key ] ) && array_key_exists( 'required', $settings ) ) {
			$address_fields[ $address_key ]['required'] = (bool) $settings['required'];
		}

		return $address_fields;
	}
}
