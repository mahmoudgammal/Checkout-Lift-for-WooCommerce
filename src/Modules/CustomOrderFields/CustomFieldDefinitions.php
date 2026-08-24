<?php
/**
 * Base custom order-field definitions.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;

/**
 * Selects custom fields owned by the base order-fields module.
 */
final class CustomFieldDefinitions {

	/**
	 * Create the definitions reader.
	 *
	 * @param CheckoutFieldsRepository $checkout_fields Stored checkout fields.
	 */
	public function __construct( private CheckoutFieldsRepository $checkout_fields ) {
	}

	/**
	 * Return base custom fields keyed by checkout field identifier.
	 *
	 * @return array<string, array{label: string, type: string}>
	 */
	public function all(): array {
		$definitions = array();

		foreach ( $this->checkout_fields->fields() as $section_fields ) {
			$definitions = array_merge( $definitions, $this->from_section( $section_fields ) );
		}

		return $definitions;
	}

	/**
	 * Select definitions from one checkout section.
	 *
	 * @param array<string, array<string, mixed>> $section_fields Stored section.
	 * @return array<string, array{label: string, type: string}>
	 */
	private function from_section( array $section_fields ): array {
		$definitions = array();

		foreach ( $section_fields as $field_key => $settings ) {
			$definition = $this->from_settings( $field_key, $settings );

			if ( null !== $definition ) {
				$definitions[ $field_key ] = $definition;
			}
		}

		return $definitions;
	}

	/**
	 * Build one owned definition or reject a field owned elsewhere.
	 *
	 * @param string               $field_key Field identifier.
	 * @param array<string, mixed> $settings Stored field settings.
	 * @return array{label: string, type: string}|null
	 */
	private function from_settings( string $field_key, array $settings ): ?array {
		$type              = is_string( $settings['type'] ?? null ) ? $settings['type'] : 'text';
		$specialized_types = apply_filters( 'scfe_specialized_custom_field_types', array() );
		$specialized_types = is_array( $specialized_types ) ? $specialized_types : array();

		if ( empty( $settings['custom'] ) || in_array( $type, $specialized_types, true ) ) {
			return null;
		}

		return array(
			'label' => is_string( $settings['label'] ?? null ) ? $settings['label'] : $field_key,
			'type'  => $type,
		);
	}
}
