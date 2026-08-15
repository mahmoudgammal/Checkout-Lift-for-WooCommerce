<?php
/**
 * Advanced settings import/export/reset hooks.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Admin;

use DesignTich\SimpleCheckoutFieldsEditor\Modules\AddressAutocomplete\AddressAutocompleteSettings;
use DesignTich\SimpleCheckoutFieldsEditor\Modules\MultiStep\MultiStepSettingsRepository;

/**
 * Owns advanced settings attached to base import/export/reset hooks.
 */
final class AdvancedSettingsHooks {

	/**
	 * Create the hook handler.
	 *
	 * @param MultiStepSettingsRepository $multistep Multi-step settings.
	 * @param AddressAutocompleteSettings $autocomplete Autocomplete settings sanitizer.
	 */
	public function __construct(
		private MultiStepSettingsRepository $multistep,
		private AddressAutocompleteSettings $autocomplete
	) {
	}

	/**
	 * Reset advanced settings.
	 */
	public function reset(): void {
		delete_option( 'scfe_multistep_settings' );
		delete_option( 'scfe_address_autocomplete' );
	}

	/**
	 * Add advanced settings to export data.
	 *
	 * @param array<string, mixed> $data Export data.
	 * @return array<string, mixed>
	 */
	public function export( array $data ): array {
		$data['multistep_settings']    = get_option( 'scfe_multistep_settings', array() );
		$data['autocomplete_settings'] = get_option( 'scfe_address_autocomplete', array() );

		return $data;
	}

	/**
	 * Import advanced settings.
	 *
	 * @param array<string, mixed> $settings Imported settings.
	 */
	public function import( array $settings ): void {
		if ( isset( $settings['multistep_settings'] ) && is_array( $settings['multistep_settings'] ) ) {
			$this->multistep->replace( $settings['multistep_settings'] );
		}

		if ( isset( $settings['autocomplete_settings'] ) && is_array( $settings['autocomplete_settings'] ) ) {
			update_option( 'scfe_address_autocomplete', $this->autocomplete->sanitize( $settings['autocomplete_settings'] ) );
		}
	}
}
