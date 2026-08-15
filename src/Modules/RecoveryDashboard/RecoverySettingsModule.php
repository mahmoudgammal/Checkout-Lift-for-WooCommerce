<?php
/**
 * Recovery settings module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers the protected endpoint behind the shared settings screen.
 */
final class RecoverySettingsModule implements Module {

	/**
	 * Create the settings module.
	 *
	 * @param SettingsController $controller Protected settings endpoint.
	 */
	public function __construct( private SettingsController $controller ) {
	}

	/** {@inheritDoc} */
	public function id(): string {
		return 'recovery-settings';
	}

	/** {@inheritDoc} */
	public function dependencies(): array {
		return array( 'recovery-dashboard' );
	}

	/** {@inheritDoc} */
	public function boot(): void {
		add_action( 'rest_api_init', array( $this->controller, 'register_routes' ) );
	}
}
