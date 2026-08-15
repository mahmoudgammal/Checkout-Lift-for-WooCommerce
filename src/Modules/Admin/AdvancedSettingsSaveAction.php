<?php
/**
 * Advanced settings save hook.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Admin;

use DesignTich\SimpleCheckoutFieldsEditor\Modules\AddressAutocomplete\AddressAutocompleteSettings;
use DesignTich\SimpleCheckoutFieldsEditor\Modules\MultiStep\MultiStepSettingsRepository;

/**
 * Persists advanced settings during the existing save-settings action.
 */
final class AdvancedSettingsSaveAction {

	/**
	 * Create the save hook.
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
	 * Save advanced settings.
	 */
	public function handle(): void {
		$this->multistep->replace( $this->multistep_settings() );
		update_option( 'scfe_address_autocomplete', $this->autocomplete->sanitize( $this->autocomplete_settings( 'autocomplete_' ) ) );
	}

	/**
	 * Read multi-step settings from the verified parent request.
	 *
	 * @return array<string, mixed>
	 */
	private function multistep_settings(): array {
		return array(
			'enabled'        => $this->posted_bool( 'multistep_enabled' ),
			'step1_title'    => $this->posted_text( 'multistep_step1_title' ),
			'step2_title'    => $this->posted_text( 'multistep_step2_title' ),
			'step3_title'    => $this->posted_text( 'multistep_step3_title' ),
			'step4_title'    => $this->posted_text( 'multistep_step4_title' ),
			'next_label'     => $this->posted_text( 'multistep_next_label' ),
			'prev_label'     => $this->posted_text( 'multistep_prev_label' ),
			'validate_steps' => $this->posted_bool( 'multistep_validate_steps' ),
		);
	}

	/**
	 * Read autocomplete settings from the verified parent request.
	 *
	 * @param string $prefix Posted key prefix.
	 * @return array<string, mixed>
	 */
	private function autocomplete_settings( string $prefix ): array {
		return array(
			'enabled'          => $this->posted_bool( $prefix . 'enabled' ),
			'api_key'          => $this->posted_text( $prefix . 'api_key' ),
			'countries'        => $this->posted_text( $prefix . 'countries' ),
			'billing_enabled'  => $this->posted_bool( $prefix . 'billing_enabled' ),
			'shipping_enabled' => $this->posted_bool( $prefix . 'shipping_enabled' ),
			'types'            => $this->posted_text( $prefix . 'types', 'address' ),
			'locate_me'        => $this->posted_bool( $prefix . 'locate_me' ),
		);
	}

	/**
	 * Read a posted boolean.
	 *
	 * @param string $key Posted key.
	 */
	private function posted_bool( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Parent save action verifies nonce before firing this hook.
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

		return '' !== $value && '0' !== $value && 'false' !== strtolower( $value );
	}

	/**
	 * Read posted text.
	 *
	 * @param string $key Posted key.
	 * @param string $fallback_text Default text.
	 */
	private function posted_text( string $key, string $fallback_text = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Parent save action verifies nonce before firing this hook.
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $fallback_text;
	}
}
