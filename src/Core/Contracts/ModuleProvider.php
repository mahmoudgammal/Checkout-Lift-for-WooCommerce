<?php
/**
 * Contract for plugin module providers.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts;

/**
 * Supplies independently bootable modules to the composition root.
 */
interface ModuleProvider {

	/**
	 * Return the modules supplied by this provider.
	 *
	 * @return iterable<Module>
	 */
	public function modules(): iterable;
}
