<?php
/**
 * Build classic custom checkout fields.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Features\Features;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleSettings;

/**
 * Maps the current stored custom-field shape to WooCommerce field arguments.
 */
final class CustomFieldBuilder {

	/**
	 * Create the custom-field builder.
	 *
	 * @param FieldOptionsParser $options_parser Stored option parser.
	 * @param ModuleSettings     $modules        Merchant module choices.
	 * @param Features           $features       Features shipped by this build.
	 */
	public function __construct(
		private FieldOptionsParser $options_parser,
		private ModuleSettings $modules,
		private Features $features
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
		$type   = (string) ( $settings['type'] ?? 'text' );
		$module = $this->module_for_type( $type );

		if ( null !== $module && ( ! $this->features->enabled( $module ) || ! $this->modules->enabled( $module ) ) ) {
			return null;
		}

		$field = array(
			'type'              => $type,
			'label'             => $settings['label'] ?? $field_key,
			'placeholder'       => $settings['placeholder'] ?? '',
			'required'          => (bool) ( $settings['required'] ?? false ),
			'default'           => $settings['default'] ?? '',
			'file_types'        => $settings['file_types'] ?? 'jpg,jpeg,png,pdf',
			'max_file_size'     => $settings['max_file_size'] ?? '5',
			'fee_amount'        => $settings['fee_amount'] ?? '0',
			'fee_type'          => $settings['fee_type'] ?? 'fixed',
			'fee_taxable'       => $settings['fee_taxable'] ?? '',
			'custom_attributes' => array( 'data-width' => $settings['width'] ?? '100' ),
		);

		return $this->with_options( $field, $settings['options'] ?? null );
	}

	/**
	 * Return the optional runtime module that owns a specialized field type.
	 *
	 * @param string $type Stored field type.
	 */
	private function module_for_type( string $type ): ?string {
		$modules = array(
			'date'     => 'datetime',
			'time'     => 'datetime',
			'datetime' => 'datetime',
			'file'     => 'file-upload',
			'fee'      => 'fee',
			'repeater' => 'repeater',
		);

		return $modules[ $type ] ?? null;
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
