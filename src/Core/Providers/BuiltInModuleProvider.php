<?php
/**
 * Provider for modules that define the plugin's base runtime.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Providers;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\ModuleProvider;

/**
 * Supplies modules that cannot be switched off by a merchant.
 */
final class BuiltInModuleProvider implements ModuleProvider {

	/**
	 * Base modules in their established registration order.
	 *
	 * @var list<Module>
	 */
	private array $modules;

	/**
	 * Create a provider for the supplied base modules.
	 *
	 * @param array $modules Base modules.
	 * @phpstan-param list<Module> $modules
	 */
	public function __construct( array $modules ) {
		$this->modules = $modules;
	}

	/**
	 * {@inheritDoc}
	 */
	public function modules(): iterable {
		return $this->modules;
	}
}
