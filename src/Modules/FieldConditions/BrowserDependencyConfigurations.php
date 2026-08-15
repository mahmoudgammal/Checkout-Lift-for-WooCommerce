<?php
/**
 * Browser field-dependency configurations.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;

/**
 * Builds the legacy client-side dependency configuration shape.
 */
final class BrowserDependencyConfigurations {

	/**
	 * Create the browser configuration reader.
	 *
	 * @param CheckoutFieldsRepository $checkout_fields Stored checkout fields.
	 * @param FieldConditionParser     $parser Field-condition parser.
	 */
	public function __construct(
		private CheckoutFieldsRepository $checkout_fields,
		private FieldConditionParser $parser
	) {
	}

	/**
	 * Return enabled field dependencies for browser UX.
	 *
	 * @return array<string, array{rules: list<array{field: string, operator: string, value: string}>, action: string, logic: string, effect: string}>
	 */
	public function configurations(): array {
		$configurations = array();

		foreach ( $this->checkout_fields->fields() as $section_fields ) {
			foreach ( $section_fields as $field_key => $field_settings ) {
				$configuration = $this->configuration_from_field( $field_settings );

				if ( null !== $configuration ) {
					$configurations[ $field_key ] = $configuration;
				}
			}
		}

		return $configurations;
	}

	/**
	 * Return whether a field has a valid browser dependency configuration.
	 *
	 * @param string $field_key Field identifier.
	 */
	public function has_configuration( string $field_key ): bool {
		return isset( $this->configurations()[ $field_key ] );
	}

	/**
	 * Parse one field's dependency settings.
	 *
	 * @param array<string, mixed> $field_settings Stored field settings.
	 * @return array{rules: list<array{field: string, operator: string, value: string}>, action: string, logic: string, effect: string}|null
	 */
	private function configuration_from_field( array $field_settings ): ?array {
		if ( empty( $field_settings['enabled'] ) ) {
			return null;
		}

		$parsed = $this->parser->parse( $field_settings['field_dependencies'] ?? null );

		if ( null === $parsed ) {
			return null;
		}

		$parsed['effect'] = $this->effect( $field_settings['field_dependencies'] ?? null );

		return $parsed;
	}

	/**
	 * Return a trusted browser animation effect.
	 *
	 * @param mixed $stored_dependencies Stored dependencies.
	 */
	private function effect( $stored_dependencies ): string {
		if ( is_string( $stored_dependencies ) ) {
			$stored_dependencies = json_decode( $stored_dependencies, true );
		}

		$effect = is_array( $stored_dependencies ) && is_string( $stored_dependencies['effect'] ?? null )
			? $stored_dependencies['effect']
			: 'fade';

		return in_array( $effect, array( 'fade', 'slide' ), true ) ? $effect : 'fade';
	}
}
