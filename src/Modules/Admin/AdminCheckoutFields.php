<?php
/**
 * Admin checkout field data.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Admin;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;

/**
 * Merges stored settings into admin default fields without changing order.
 */
final class AdminCheckoutFields {

	/**
	 * Create the field provider.
	 *
	 * @param CheckoutFieldsRepository $settings Stored checkout fields.
	 * @param DefaultCheckoutFields    $defaults Default field provider.
	 */
	public function __construct(
		private CheckoutFieldsRepository $settings,
		private DefaultCheckoutFields $defaults
	) {
	}

	/**
	 * Return fields for the admin template.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function fields(): array {
		return $this->merge_saved_fields( $this->defaults->fields(), $this->settings->fields() );
	}

	/**
	 * Merge saved fields into defaults.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $defaults Default fields.
	 * @param array<string, array<string, array<string, mixed>>> $saved Saved settings.
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function merge_saved_fields( array $defaults, array $saved ): array {
		foreach ( $saved as $section => $fields ) {
			$defaults[ $section ] = $this->merge_section( $defaults[ $section ] ?? array(), $fields );
		}

		return $defaults;
	}

	/**
	 * Merge one section in saved order.
	 *
	 * @param array<string, array<string, mixed>> $defaults Section defaults.
	 * @param array<string, array<string, mixed>> $saved Section settings.
	 * @return array<string, array<string, mixed>>
	 */
	private function merge_section( array $defaults, array $saved ): array {
		$merged = array();

		foreach ( $saved as $field_key => $field_data ) {
			$merged[ $field_key ] = array_merge( $defaults[ $field_key ] ?? array(), $field_data );
		}

		foreach ( $defaults as $field_key => $field_data ) {
			if ( ! isset( $merged[ $field_key ] ) ) {
				$merged[ $field_key ] = $field_data;
			}
		}

		return $merged;
	}
}
