<?php
/**
 * Multi-step settings translator.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Translates stored multi-step labels at option-read time.
 */
final class MultiStepSettingsTranslator {

	/**
	 * Create the translator.
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
	 * Translate multi-step settings.
	 *
	 * @param mixed $settings Stored settings.
	 * @return mixed
	 */
	public function translate( $settings ) {
		if ( ! $this->providers->active() || ! is_array( $settings ) ) {
			return $settings;
		}

		foreach ( $this->keys() as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
				$settings[ $key ] = $this->strings->translate( 'multistep_' . $key, $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Return translatable setting keys.
	 *
	 * @return list<string>
	 */
	private function keys(): array {
		return array( 'step1_title', 'step2_title', 'step3_title', 'step4_title', 'next_label', 'prev_label' );
	}
}
