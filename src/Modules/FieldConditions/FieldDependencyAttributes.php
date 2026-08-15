<?php
/**
 * Field dependency browser attributes.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions;

/**
 * Adds wrapper attributes used by the dependency JavaScript.
 */
final class FieldDependencyAttributes {

	/**
	 * Create the attribute adapter.
	 *
	 * @param BrowserDependencyConfigurations $configurations Browser dependency configurations.
	 */
	public function __construct( private BrowserDependencyConfigurations $configurations ) {
	}

	/**
	 * Add dependency targeting classes and attributes.
	 *
	 * @param array<string, mixed> $arguments WooCommerce field arguments.
	 * @param string               $field_key Field identifier.
	 * @param mixed                $value Current value.
	 * @return array<string, mixed>
	 */
	public function add( array $arguments, string $field_key, $value ): array {
		unset( $value );

		if ( ! $this->configurations->has_configuration( $field_key ) ) {
			return $arguments;
		}

		$arguments['class']                                     = array_merge( (array) ( $arguments['class'] ?? array() ), array( 'scfe-has-dependency', 'scfe-dependency-fade' ) );
		$arguments['custom_attributes']                         = is_array( $arguments['custom_attributes'] ?? null ) ? $arguments['custom_attributes'] : array();
		$arguments['custom_attributes']['data-scfe-dependency'] = $field_key;

		return $arguments;
	}
}
