<?php
/**
 * Field conditions feature module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers classic server authority for field-to-field conditions.
 */
final class FieldConditionsModule implements Module {

	/**
	 * Create the field conditions module.
	 *
	 * @param ClassicValidationAdapter  $classic_validation Classic validation adapter.
	 * @param FieldDependencyAssets     $assets Browser dependency assets.
	 * @param FieldDependencyAttributes $attributes Browser dependency attributes.
	 */
	public function __construct(
		private ClassicValidationAdapter $classic_validation,
		private FieldDependencyAssets $assets,
		private FieldDependencyAttributes $attributes
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'field-conditions';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies(): array {
		return array( 'core-assets' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {
		$this->classic_validation->register_hooks();
		add_action( 'wp_enqueue_scripts', array( $this->assets, 'enqueue' ) );
		add_filter( 'woocommerce_form_field_args', array( $this->attributes, 'add' ), 20, 3 );
	}
}
