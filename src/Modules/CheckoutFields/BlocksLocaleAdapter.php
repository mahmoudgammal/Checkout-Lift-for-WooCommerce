<?php
/**
 * Block checkout field hooks.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;

/**
 * Applies stored field settings to the WooCommerce Checkout block.
 *
 * The block checkout never calls `woocommerce_checkout_fields`. What it does
 * read, for core address fields, is the country locale, and so does the Store
 * API when it validates the finished order. Hiding, requiring, renaming and
 * reordering all travel that way.
 *
 * Two things about the locale shape drive everything below, and both were
 * measured rather than assumed:
 *
 * The locale is keyed by country and by nothing else. It has no notion of a
 * billing address or a shipping address, and WooCommerce validates both
 * against the same array. **A field cannot be configured differently per
 * section here.** Where the two sections disagree this class chooses the
 * cautious answer rather than picking a winner: a field is hidden only when
 * every section that has it hides it, and required when any section requires
 * it. Neither choice can remove a field a merchant still wanted or drop
 * validation they still had.
 *
 * `OrderController::validate_address_fields()` iterates the `default` entry
 * and merges each country entry over it, so a setting written only into a
 * country is never validated. Everything therefore goes into `default`, and
 * also into any country that already mentions the field, because otherwise
 * that country's stock entry would win over ours for that country alone.
 *
 * The two halves need two different filters. The `default` entry cannot be
 * written through `woocommerce_get_country_locale`, because WooCommerce
 * assigns it *after* that filter has run:
 *
 *     $this->locale['default'] = apply_filters(
 *         'woocommerce_get_country_locale_default',
 *         $this->get_default_address_fields()
 *     );
 *
 * Anything written into `default` from the first filter is thrown away a few
 * lines later, silently. That is why the default is written through
 * `woocommerce_get_country_locale_default` and the countries through
 * `woocommerce_get_country_locale`.
 */
final class BlocksLocaleAdapter {

	/**
	 * Section prefixes that map onto the shared address locale.
	 *
	 * Stored keys carry their section, `billing_city`. Locale keys do not.
	 */
	private const SECTION_PREFIXES = array( 'billing_', 'shipping_' );

	/**
	 * Fields the address locale governs.
	 *
	 * Written out rather than read from WooCommerce, for two reasons. This
	 * runs inside `woocommerce_get_country_locale`, and asking WooCommerce for
	 * its address fields from in there invites a loop. And the list is not a
	 * setting: it is the fixed shape of a WooCommerce address.
	 *
	 * The contact email is absent on purpose. It is not an address field, and
	 * the locale has no say over it.
	 */
	private const ADDRESS_FIELDS = array(
		'first_name',
		'last_name',
		'company',
		'country',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
		'phone',
	);

	/**
	 * Create the block checkout adapter.
	 *
	 * @param CheckoutFieldsRepository $checkout_fields Stored field settings.
	 * @param CheckoutBlocks           $blocks          Which checkout is configured.
	 */
	public function __construct(
		private CheckoutFieldsRepository $checkout_fields,
		private CheckoutBlocks $blocks
	) {
	}

	/**
	 * Register block checkout hooks.
	 *
	 * The filter is always added and asks which checkout it is on when it
	 * runs. Deciding at registration would mean asking WooCommerce which
	 * checkout page is configured while modules are still booting, which is
	 * both earlier than the answer is reliable and a dependency on load order
	 * that nothing here needs.
	 */
	public function register_hooks(): void {
		// Run after WooCommerce and merchant filters so this adjusts their final locale data.
		add_filter( 'woocommerce_get_country_locale', array( $this, 'filter_locale' ), 1000 );
		add_filter( 'woocommerce_get_country_locale_default', array( $this, 'filter_default_locale' ), 1000 );
	}

	/**
	 * Apply stored settings to the locale every country falls back to.
	 *
	 * @param array<string, array<string, mixed>> $fields Default address fields.
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_default_locale( array $fields ): array {
		return $this->blocks->in_use() ? $this->apply_to_default( $fields ) : $fields;
	}

	/**
	 * Write the decisions into the entry every country falls back to.
	 *
	 * @param array<string, array<string, mixed>> $fields Default address fields.
	 * @return array<string, array<string, mixed>>
	 */
	public function apply_to_default( array $fields ): array {
		foreach ( $this->address_decisions() as $field_key => $decision ) {
			$fields[ $field_key ] = array_merge( $fields[ $field_key ] ?? array(), $decision );
		}

		return $fields;
	}

	/**
	 * Apply stored settings to the country locale.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $locale Country locale.
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function filter_locale( array $locale ): array {
		// Classic checkout uses separate filters that preserve per-section settings.
		return $this->blocks->in_use() ? $this->apply_to_countries( $locale ) : $locale;
	}

	/**
	 * Overwrite the country entries that would otherwise win.
	 *
	 * The `default` entry is deliberately not touched here. WooCommerce
	 * assigns it after this filter has run, so anything written into it is
	 * discarded without a word.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $locale Country locale.
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function apply_to_countries( array $locale ): array {
		foreach ( $this->address_decisions() as $field_key => $decision ) {
			foreach ( $locale as $country => $entry ) {
				if ( 'default' === $country || ! isset( $entry[ $field_key ] ) ) {
					continue;
				}

				// Apply the override per country so locale merges cannot restore the default.
				$locale[ $country ][ $field_key ] = array_merge( $entry[ $field_key ], $decision );
			}
		}

		return $locale;
	}

	/**
	 * Return the decisions that belong to an address field.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function address_decisions(): array {
		$decisions = array();

		foreach ( $this->decisions( $this->checkout_fields->fields() ) as $field_key => $decision ) {
			if ( in_array( $field_key, self::ADDRESS_FIELDS, true ) ) {
				$decisions[ $field_key ] = $decision;
			}
		}

		return $decisions;
	}

	/**
	 * Reduce the stored sections to one decision per locale field.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $stored Stored settings.
	 * @return array<string, array<string, mixed>>
	 */
	public function decisions( array $stored ): array {
		/**
		 * Gathered per locale key before being reduced.
		 *
		 * @var array<string, array<int, array<string, mixed>>> $collected
		 */
		$collected = array();
		$order     = array();

		foreach ( $stored as $section => $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}

			$position = 0;

			foreach ( $fields as $stored_key => $settings ) {
				$field_key = $this->locale_key( (string) $stored_key );

				if ( '' === $field_key || ! is_array( $settings ) || ! empty( $settings['custom'] ) ) {
					// Custom fields cannot be expressed through address locale data.
					continue;
				}

				$position += 10;

				$collected[ $field_key ][] = $settings;

				// Billing wins shared-field order because the editor presents it first.
				if ( ! isset( $order[ $field_key ] ) || 'billing' === $section ) {
					$order[ $field_key ] = $position;
				}
			}
		}

		$decisions = array();

		foreach ( $collected as $field_key => $entries ) {
			$decisions[ $field_key ] = $this->reduce( $entries, $order[ $field_key ] ?? 10 );
		}

		return $decisions;
	}

	/**
	 * Reduce one field's settings across every section that has it.
	 *
	 * @param array<int, array<string, mixed>> $entries  Settings per section.
	 * @param int                              $priority Position to render at.
	 * @return array<string, mixed>
	 */
	private function reduce( array $entries, int $priority ): array {
		$hidden   = true;
		$required = false;
		$label    = '';

		foreach ( $entries as $settings ) {
			$enabled = ! isset( $settings['enabled'] ) || (bool) $settings['enabled'];

			if ( $enabled ) {
				$hidden = false;
			}

			// Disabled required fields must not block checkout.
			if ( $enabled && ! empty( $settings['required'] ) ) {
				$required = true;
			}

			if ( '' === $label && isset( $settings['label'] ) && is_string( $settings['label'] ) && '' !== $settings['label'] ) {
				$label = $settings['label'];
			}
		}

		$decision = array(
			'hidden'   => $hidden,
			'required' => $required && ! $hidden,
			'priority' => $priority,
		);

		if ( '' !== $label ) {
			$decision['label'] = $label;
		}

		return $decision;
	}

	/**
	 * Strip the section from a stored field key.
	 *
	 * @param string $stored_key Stored field key.
	 * @return string Locale key, or an empty string when there is no section.
	 */
	private function locale_key( string $stored_key ): string {
		foreach ( self::SECTION_PREFIXES as $prefix ) {
			if ( str_starts_with( $stored_key, $prefix ) ) {
				return substr( $stored_key, strlen( $prefix ) );
			}
		}

		return '';
	}
}
