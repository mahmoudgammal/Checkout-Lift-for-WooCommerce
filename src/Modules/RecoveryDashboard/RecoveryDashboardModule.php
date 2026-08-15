<?php
/**
 * Recovery dashboard module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Admin\AdminMenu;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers the recovery dashboard screen and the endpoint behind it.
 */
final class RecoveryDashboardModule implements Module {

	/**
	 * Create the dashboard module.
	 *
	 * @param DashboardPage   $page       Admin screen.
	 * @param DashboardAssets $assets     Built bundle.
	 * @param StatsController $controller Figures endpoint.
	 * @param AdminMenu       $menu       Plugin menu.
	 */
	public function __construct(
		private DashboardPage $page,
		private DashboardAssets $assets,
		private StatsController $controller,
		private AdminMenu $menu
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'recovery-dashboard';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies(): array {
		return array( 'database' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {
		// Register the parent before any screen attaches its submenu.
		add_action( 'admin_menu', array( $this->menu, 'register' ), 1 );
		add_action( 'admin_menu', array( $this->page, 'register_menu' ), 20 );
		add_action( 'admin_menu', array( $this->menu, 'tidy_submenu' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue' ) );
		add_action( 'rest_api_init', array( $this->controller, 'register_routes' ) );
	}
}
