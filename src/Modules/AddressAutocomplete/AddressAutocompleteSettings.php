<?php
/**
 * Address autocomplete settings.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\AddressAutocomplete;

/**
 * Reads and sanitizes the existing address-autocomplete option shape.
 */
final class AddressAutocompleteSettings {

	private const OPTION_NAME = 'scfe_address_autocomplete';

	private const TYPES = array( 'address', 'geocode', 'establishment', '(regions)' );

	/**
	 * Return normalized settings.
	 *
	 * @return array{enabled: bool, api_key: string, countries: string, billing_enabled: bool, shipping_enabled: bool, locate_me: bool, types: string}
	 */
	public function settings(): array {
		$stored = get_option( self::OPTION_NAME, array() );

		return $this->sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Register the setting for Settings API consumers.
	 */
	public function register(): void {
		register_setting(
			'scfe_address_autocomplete_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Return whether the frontend autocomplete integration can run.
	 */
	public function is_enabled(): bool {
		$settings = $this->settings();

		return $settings['enabled'] && '' !== $settings['api_key'];
	}

	/**
	 * Replace the stored settings and report persistence failure honestly.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 */
	public function replace( array $settings ): bool {
		$sanitized = $this->sanitize( $settings );

		if ( $sanitized === $this->settings() ) {
			return true;
		}

		return update_option( self::OPTION_NAME, $sanitized );
	}

	/**
	 * Normalize and sanitize raw settings.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array{enabled: bool, api_key: string, countries: string, billing_enabled: bool, shipping_enabled: bool, locate_me: bool, types: string}
	 */
	public function sanitize( array $settings ): array {
		return array(
			'enabled'          => ! empty( $settings['enabled'] ),
			'api_key'          => $this->text( $settings, 'api_key' ),
			'countries'        => implode( ',', $this->countries( $settings['countries'] ?? '' ) ),
			'billing_enabled'  => ! array_key_exists( 'billing_enabled', $settings ) || ! empty( $settings['billing_enabled'] ),
			'shipping_enabled' => ! array_key_exists( 'shipping_enabled', $settings ) || ! empty( $settings['shipping_enabled'] ),
			'locate_me'        => ! empty( $settings['locate_me'] ),
			'types'            => $this->type( $settings['types'] ?? 'address' ),
		);
	}

	/**
	 * Return country restrictions as lowercase ISO-like two-letter codes.
	 *
	 * @param mixed $countries Raw countries setting.
	 * @return list<string>
	 */
	public function countries( $countries ): array {
		if ( ! is_scalar( $countries ) ) {
			return array();
		}

		$normalized = array();
		foreach ( explode( ',', (string) $countries ) as $country ) {
			$country = sanitize_key( trim( $country ) );
			if ( 1 === preg_match( '/^[a-z]{2}$/', $country ) ) {
				$normalized[] = $country;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Return a sanitized scalar text setting.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @param string               $key Setting key.
	 */
	private function text( array $settings, string $key ): string {
		return is_scalar( $settings[ $key ] ?? null ) ? sanitize_text_field( (string) $settings[ $key ] ) : '';
	}

	/**
	 * Return an allowed Google Places autocomplete type.
	 *
	 * @param mixed $type Raw type.
	 */
	private function type( $type ): string {
		$type = is_scalar( $type ) ? sanitize_text_field( (string) $type ) : 'address';

		return in_array( $type, self::TYPES, true ) ? $type : 'address';
	}
}
