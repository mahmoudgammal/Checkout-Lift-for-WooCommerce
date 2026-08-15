<?php
/**
 * Multi-step settings repository.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\MultiStep;

/**
 * Reads and writes the existing multi-step option shape.
 */
final class MultiStepSettingsRepository {

	private const OPTION_NAME = 'scfe_multistep_settings';

	/**
	 * Return normalized multi-step settings.
	 *
	 * @return array{enabled: bool, step1_title: string, step2_title: string, step3_title: string, step4_title: string, next_label: string, prev_label: string, validate_steps: bool}
	 */
	public function settings(): array {
		$stored = get_option( self::OPTION_NAME, array() );

		return $this->normalize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Replace multi-step settings.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 */
	public function replace( array $settings ): bool {
		$normalized = $this->normalize( $settings );

		if ( $normalized === $this->settings() ) {
			return true;
		}

		return update_option( self::OPTION_NAME, $normalized );
	}

	/**
	 * Normalize the stored option shape.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array{enabled: bool, step1_title: string, step2_title: string, step3_title: string, step4_title: string, next_label: string, prev_label: string, validate_steps: bool}
	 */
	public function normalize( array $settings ): array {
		return array(
			'enabled'        => ! empty( $settings['enabled'] ),
			'step1_title'    => $this->text( $settings, 'step1_title' ),
			'step2_title'    => $this->text( $settings, 'step2_title' ),
			'step3_title'    => $this->text( $settings, 'step3_title' ),
			'step4_title'    => $this->text( $settings, 'step4_title' ),
			'next_label'     => $this->text( $settings, 'next_label' ),
			'prev_label'     => $this->text( $settings, 'prev_label' ),
			'validate_steps' => ! array_key_exists( 'validate_steps', $settings ) || ! empty( $settings['validate_steps'] ),
		);
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
}
