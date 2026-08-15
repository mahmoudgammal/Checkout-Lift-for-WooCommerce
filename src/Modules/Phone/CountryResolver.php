<?php
/**
 * Resolve the initial phone country without browser-side tracking.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone;

/**
 * Uses WooCommerce geolocation with the store country as its fallback.
 */
final class CountryResolver {

	/**
	 * Return a lowercase ISO 3166-1 alpha-2 country code when available.
	 */
	public function country_code(): string {
		$base_location = wc_get_base_location();
		$geolocation   = wc_get_customer_geolocation( $base_location );
		$country_code  = $this->normalize_country( $geolocation['country'] ?? '' );

		if ( '' !== $country_code ) {
			return $country_code;
		}

		return $this->normalize_country( $base_location['country'] ?? '' );
	}

	/**
	 * Normalize only valid two-letter country codes.
	 *
	 * @param mixed $country_code Country value returned by WooCommerce.
	 */
	private function normalize_country( $country_code ): string {
		if ( ! is_string( $country_code ) || 1 !== preg_match( '/^[a-z]{2}$/i', $country_code ) ) {
			return '';
		}

		return strtolower( $country_code );
	}
}
