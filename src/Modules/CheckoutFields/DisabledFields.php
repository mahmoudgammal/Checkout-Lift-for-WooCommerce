<?php
/**
 * Disabled checkout-field decisions.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;
use WP_Error;

/**
 * Centralizes the server-side meaning of an explicitly disabled field.
 */
final class DisabledFields {

	/**
	 * Cached disabled keys for this request.
	 *
	 * @var list<string>|null
	 */
	private ?array $disabled_keys = null;

	/**
	 * Create the disabled-fields service.
	 *
	 * @param CheckoutFieldsRepository $checkout_fields Stored field settings.
	 */
	public function __construct( private CheckoutFieldsRepository $checkout_fields ) {
	}

	/**
	 * Remove disabled fields from one WooCommerce section.
	 *
	 * @param array<string, array<string, mixed>> $fields Current fields.
	 * @param string                              $section_key Stored section key.
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_section( array $fields, string $section_key ): array {
		$section_settings = $this->checkout_fields->fields()[ $section_key ] ?? array();

		foreach ( $section_settings as $field_key => $settings ) {
			if ( isset( $settings['enabled'] ) && ! $settings['enabled'] ) {
				unset( $fields[ $field_key ] );
			}
		}

		return $fields;
	}

	/**
	 * Remove validation errors whose code identifies a disabled field.
	 *
	 * @param WP_Error $validation_errors WooCommerce validation errors.
	 */
	public function remove_validation_errors( WP_Error $validation_errors ): void {
		foreach ( $validation_errors->get_error_codes() as $error_code ) {
			if ( $this->error_belongs_to_disabled_field( $error_code ) ) {
				$validation_errors->remove( $error_code );
			}
		}
	}

	/**
	 * Add disabled fields to the shared server-hidden persistence state.
	 *
	 * @param array $hidden_fields Hidden fields from other modules.
	 * @return list<string>
	 */
	public function add_to_hidden_fields( array $hidden_fields ): array {
		return array_values( array_unique( array_merge( $hidden_fields, $this->keys() ) ) );
	}

	/**
	 * Return every explicitly disabled field key.
	 *
	 * @return list<string>
	 */
	private function keys(): array {
		if ( null !== $this->disabled_keys ) {
			return $this->disabled_keys;
		}

		$this->disabled_keys = array();

		foreach ( $this->checkout_fields->fields() as $section_fields ) {
			$this->disabled_keys = array_merge( $this->disabled_keys, $this->section_keys( $section_fields ) );
		}

		return $this->disabled_keys;
	}

	/**
	 * Return disabled keys from one stored section.
	 *
	 * @param array<string, array<string, mixed>> $section_fields Stored section.
	 * @return list<string>
	 */
	private function section_keys( array $section_fields ): array {
		$disabled_keys = array();

		foreach ( $section_fields as $field_key => $settings ) {
			if ( isset( $settings['enabled'] ) && ! $settings['enabled'] ) {
				$disabled_keys[] = $field_key;
			}
		}

		return $disabled_keys;
	}

	/**
	 * Check whether an error code identifies a disabled field.
	 *
	 * @param string $error_code WooCommerce error code.
	 */
	private function error_belongs_to_disabled_field( string $error_code ): bool {
		foreach ( $this->keys() as $field_key ) {
			if ( false !== strpos( $error_code, $field_key ) ) {
				return true;
			}
		}

		return false;
	}
}
