<?php
/**
 * Base custom order fields module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers base custom order-field persistence and display.
 */
final class CustomOrderFieldsModule implements Module {

	/**
	 * Create the custom order fields module.
	 *
	 * @param ClassicOrderPersistence $persistence Classic checkout persistence.
	 * @param OrderFieldsRenderer     $renderer Order display hooks.
	 */
	public function __construct(
		private ClassicOrderPersistence $persistence,
		private OrderFieldsRenderer $renderer
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'custom-order-fields';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies(): array {
		return array( 'checkout-fields' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {
		$this->persistence->register_hooks();
		$this->renderer->register_hooks();
	}
}
