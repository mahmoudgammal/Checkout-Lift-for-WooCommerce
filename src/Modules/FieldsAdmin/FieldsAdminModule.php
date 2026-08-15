<?php
/**
 * React checkout fields editor module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;
use DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard\DashboardAssets;

/**
 * Registers the replacement field editor and the endpoint behind it.
 */
final class FieldsAdminModule implements Module {

	/**
	 * Create the module.
	 *
	 * @param FieldsPage         $page       Admin screen.
	 * @param FieldsController   $controller Fields endpoint.
	 * @param DashboardAssets    $assets     Built bundle loader.
	 * @param AdvancedController $advanced   Advanced settings and tools.
	 */
	public function __construct(
		private FieldsPage $page,
		private FieldsController $controller,
		private DashboardAssets $assets,
		private AdvancedController $advanced
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'fields-admin';
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
		// Ahead of the classic screen's default priority, so the first
		// entry in the menu is the one merchants should be using.
		add_action( 'admin_menu', array( $this->page, 'register_menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue' ) );
		add_action( 'rest_api_init', array( $this->controller, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->advanced, 'register_routes' ) );
	}
}
