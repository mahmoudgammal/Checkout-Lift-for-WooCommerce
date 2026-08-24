<?php
/**
 * Provider for merchant-switchable modules.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Providers;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\ModuleProvider;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleSettings;

/**
 * Supplies optional modules enabled by the shop.
 */
final class OptionalModuleProvider implements ModuleProvider {

	/**
	 * Create a provider for the supplied optional module factories.
	 *
	 * @param ModuleSettings $settings Merchant module choices.
	 * @param array          $groups   Optional module groups in registration order.
	 * @phpstan-param list<OptionalModuleGroup> $groups
	 */
	public function __construct(
		private ModuleSettings $settings,
		private array $groups
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function modules(): iterable {
		foreach ( $this->groups as $group ) {
			if ( ! $group->enabled( $this->settings ) ) {
				continue;
			}

			foreach ( $group->modules() as $module ) {
				yield $module;
			}
		}
	}
}
