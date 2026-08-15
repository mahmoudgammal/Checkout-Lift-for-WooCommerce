<?php
/**
 * One independently packageable optional module group.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Providers;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Features\Features;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleSettings;

/**
 * Keeps build availability separate from the merchant's existing setting key.
 */
final class OptionalModuleGroup {

	/**
	 * Create an independently gated module group.
	 *
	 * @param string $capability Build capability identifier.
	 * @param string $setting    Existing merchant setting identifier.
	 * @param array  $factories  Lazy module factories.
	 * @phpstan-param list<callable(): Module> $factories
	 */
	public function __construct(
		private string $capability,
		private string $setting,
		private array $factories
	) {
	}

	/**
	 * Check both the legacy group gate and the granular build capability.
	 *
	 * @param Features       $features Build feature availability.
	 * @param ModuleSettings $settings Merchant module choices.
	 */
	public function enabled( Features $features, ModuleSettings $settings ): bool {
		if ( ! $features->enabled( $this->setting ) || ! $settings->enabled( $this->setting ) ) {
			return false;
		}

		return $this->setting === $this->capability || $features->enabled( $this->capability );
	}

	/**
	 * Return the stable build capability used to position add-on groups.
	 */
	public function capability(): string {
		return $this->capability;
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
