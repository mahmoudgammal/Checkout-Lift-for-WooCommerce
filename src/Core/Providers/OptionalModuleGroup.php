<?php
/**
 * One independently packageable optional module group.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Providers;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleSettings;

/**
 * Groups modules behind one merchant-controlled setting.
 */
final class OptionalModuleGroup {

	/**
	 * Create an independently gated module group.
	 *
	 * @param string $identifier Stable group identifier.
	 * @param string $setting    Merchant setting identifier.
	 * @param array  $factories  Lazy module factories.
	 * @phpstan-param list<callable(): Module> $factories
	 */
	public function __construct(
		private string $identifier,
		private string $setting,
		private array $factories
	) {
	}

	/**
	 * Check the merchant's module choice.
	 *
	 * @param ModuleSettings $settings Merchant module choices.
	 */
	public function enabled( ModuleSettings $settings ): bool {
		return $settings->enabled( $this->setting );
	}

	/**
	 * Return the stable identifier used to position add-on groups.
	 */
	public function capability(): string {
		return $this->identifier;
	}

	/**
	 * Construct the modules only after every gate has passed.
	 *
	 * @return iterable<Module>
	 */
	public function modules(): iterable {
		foreach ( $this->factories as $factory ) {
			yield $factory();
		}
	}
}
