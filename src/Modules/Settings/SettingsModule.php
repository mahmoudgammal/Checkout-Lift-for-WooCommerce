<?php
/**
 * The settings screen module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Settings;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers the settings screen and the endpoint behind its first tab.
 *
 * Never optional. A merchant who switched off the module that carries the
 * switches would have no way back.
 */
final class SettingsModule implements Module {

	/**
	 * Create the settings module.
	 *
	 * @param SettingsPage      $page       The screen.
	 * @param ModulesController $controller The module switches.
	 */
	public function __construct(
		private SettingsPage $page,
		private ModulesController $controller
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'settings';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this->page, 'register_menu' ) );
		add_action( 'rest_api_init', array( $this->controller, 'register_routes' ) );
	}
}
