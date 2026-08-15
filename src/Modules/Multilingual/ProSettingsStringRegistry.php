<?php
/**
 * Pro settings string registration.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Registers advanced settings strings.
 */
final class ProSettingsStringRegistry {

	/**
	 * Create the registry.
	 *
	 * @param MultilingualProviders     $providers Provider detector.
	 * @param TranslatableStringService $strings Translation string service.
	 */
	public function __construct(
		private MultilingualProviders $providers,
		private TranslatableStringService $strings
	) {
	}

	/**
	 * Register saved pro settings when providers are active.
	 */
	public function register_saved_settings(): void {
		if ( ! $this->providers->active() ) {
			return;
		}

		$this->register_multistep( null, get_option( 'scfe_multistep_settings', array() ) );
	}

	/**
	 * Register multi-step labels.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 */
	public function register_multistep( $old_value, $new_value ): void {
		if ( ! is_array( $new_value ) ) {
			return;
		}

		foreach ( $this->multistep_keys() as $key ) {
			$text = $new_value[ $key ] ?? null;

			if ( is_string( $text ) && '' !== $text ) {
				$this->strings->register( 'multistep_' . $key, $text );
			}
		}
	}

	/**
	 * Preserve the historical autocomplete hook without speculative strings.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 */
	public function register_autocomplete( $old_value, $new_value ): void {
	}

	/**
	 * Return translatable multistep keys.
	 *
	 * @return list<string>
	 */
	private function multistep_keys(): array {
		return array( 'step1_title', 'step2_title', 'step3_title', 'step4_title', 'next_label', 'prev_label' );
	}
}
