<?php
/**
 * Classic checkout browser-condition attributes.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

/**
 * Keeps browser condition UX attached to server-transformed fields.
 */
final class ConditionAttributes {

	private const CONFIGURATIONS = array(
		'conditions' => array( 'rules', 'data-scfe-conditions', 'scfe-conditional-field' ),
	);

	/**
	 * Add valid condition data attributes and targeting classes.
	 *
	 * @param array<string, mixed> $field Current field arguments.
	 * @param array<string, mixed> $settings Stored field settings.
	 * @return array<string, mixed>
	 */
	public function add( array $field, array $settings ): array {
		foreach ( self::CONFIGURATIONS as $setting_key => $configuration ) {
			$field = $this->add_configuration( $field, $settings[ $setting_key ] ?? null, $configuration );
		}

		$extended = apply_filters( 'scfe_checkout_condition_attributes', $field, $settings );

		return is_array( $extended ) ? $extended : $field;
	}

	/**
	 * Add one valid stored browser configuration.
	 *
	 * @param array<string, mixed>        $field Current field arguments.
	 * @param mixed                       $stored_configuration Stored condition configuration.
	 * @param array{string,string,string} $configuration Required key, attribute, and class.
	 * @return array<string, mixed>
	 */
	private function add_configuration( array $field, $stored_configuration, array $configuration ): array {
		if ( ! $this->has_entries( $stored_configuration, $configuration[0] ) ) {
			return $field;
		}

		$encoded_configuration = wp_json_encode( $stored_configuration );

		if ( ! is_string( $encoded_configuration ) ) {
			return $field;
		}

		$field['custom_attributes'][ $configuration[1] ] = $encoded_configuration;
		$field['class'][]                                = $configuration[2];

		return $field;
	}

	/**
	 * Check the minimal existing browser configuration shape.
	 *
	 * @param mixed  $stored_configuration Stored configuration.
	 * @param string $entries_key Required entries key.
	 */
	private function has_entries( $stored_configuration, string $entries_key ): bool {
		return is_array( $stored_configuration )
			&& isset( $stored_configuration[ $entries_key ] )
			&& is_array( $stored_configuration[ $entries_key ] )
			&& array() !== $stored_configuration[ $entries_key ];
	}
}
