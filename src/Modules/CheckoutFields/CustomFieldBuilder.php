<?php
/**
 * Build classic custom checkout fields.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

/**
 * Maps the current stored custom-field shape to WooCommerce field arguments.
 */
final class CustomFieldBuilder {

	/**
	 * Create the custom-field builder.
	 *
	 * @param FieldOptionsParser $options_parser Stored option parser.
	 */
	public function __construct(
		private FieldOptionsParser $options_parser
	) {
	}

	/**
	 * Build one custom checkout field.
	 *
	 * @param string               $field_key Field identifier.
	 * @param array<string, mixed> $settings Stored settings.
	 * @return array<string, mixed>|null
	 */
	public function build( string $field_key, array $settings ): ?array {
		$type = (string) ( $settings['type'] ?? 'text' );

		if ( ! in_array( $type, array( 'text', 'number', 'email', 'url', 'select', 'textarea' ), true ) ) {
			$extended = apply_filters( 'scfe_custom_checkout_field', null, $field_key, $settings );

			return is_array( $extended ) ? $extended : null;
		}

		$field = array(
			'type'              => $type,
			'label'             => $settings['label'] ?? $field_key,
			'placeholder'       => $settings['placeholder'] ?? '',
			'required'          => (bool) ( $settings['required'] ?? false ),
			'default'           => $settings['default'] ?? '',
			'custom_attributes' => array( 'data-width' => $settings['width'] ?? '100' ),
		);

		return $this->with_options( $field, $settings['options'] ?? null );
	}

	/**
	 * Add parsed select-style options when configured.
	 *
	 * @param array<string, mixed> $field Built field.
	 * @param mixed                $stored_options Stored options.
	 * @return array<string, mixed>
	 */
	private function with_options( array $field, $stored_options ): array {
		$options = $this->options_parser->parse( $stored_options );

		if ( array() !== $options ) {
			$field['options'] = $options;
		}

		return $field;
	}
}
