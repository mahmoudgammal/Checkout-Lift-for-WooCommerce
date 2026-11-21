<?php
/**
 * Apply stored presentation settings to classic checkout fields.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

/**
 * Applies the common presentation contract to default and custom fields.
 */
final class FieldDecorator {

	/**
	 * Create the field decorator.
	 *
	 * @param ConditionAttributes $condition_attributes Browser condition attributes.
	 */
	public function __construct( private ConditionAttributes $condition_attributes ) {
	}

	/**
	 * Apply field settings and calculated priority.
	 *
	 * @param array<string, mixed> $field Existing field arguments.
	 * @param array<string, mixed> $settings Stored field settings.
	 * @param int                  $priority Calculated priority.
	 * @return array<string, mixed>
	 */
	public function decorate( array $field, array $settings, int $priority ): array {
		$field             = $this->apply_text( $field, $settings );
		$field['priority'] = $priority;

		if ( array_key_exists( 'required', $settings ) ) {
			$field['required'] = (bool) $settings['required'];
		}

		// Width travels on the wrapper class because WooCommerce puts custom attributes on the input.
		$field['class'] = FieldWidthClasses::replace_layout( $field['class'] ?? array() );

		return $this->condition_attributes->add( $field, $settings );
	}

	/**
	 * Apply sanitized label and placeholder settings.
	 *
	 * @param array<string, mixed> $field Current field.
	 * @param array<string, mixed> $settings Stored settings.
	 * @return array<string, mixed>
	 */
	private function apply_text( array $field, array $settings ): array {
		if ( isset( $settings['label'] ) && is_string( $settings['label'] ) && '' !== $settings['label'] ) {
			$field['label'] = sanitize_text_field( $settings['label'] );
		}

		if ( isset( $settings['placeholder'] ) && is_string( $settings['placeholder'] ) ) {
			$field['placeholder'] = sanitize_text_field( $settings['placeholder'] );
		}

		return $field;
	}
}
