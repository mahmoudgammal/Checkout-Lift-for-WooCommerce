<?php
/**
 * Admin module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Admin;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Keeps the extension points other modules hang off.
 *
 * This module used to own the classic admin screen, its assets, and its AJAX
 * endpoints. All of that was replaced by the React editor. What remains are
 * the `scfe_save_settings`, `scfe_import_settings`, `scfe_reset_settings`,
 * and `scfe_export_data` hooks, which multi-step and address autocomplete
 * still extend and which are part of the plugin's public surface.
 */
final class AdminModule implements Module {

	/**
	 * Create the module.
	 *
	 * @param AdvancedSettingsSaveAction $advanced_save Advanced settings save hook.
	 * @param AdvancedSettingsHooks      $advanced_hooks Advanced import/export/reset hooks.
	 */
	public function __construct(
		private AdvancedSettingsSaveAction $advanced_save,
		private AdvancedSettingsHooks $advanced_hooks
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'admin';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies(): array {
		return array( 'checkout-fields-admin' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {
		// The screen these served was replaced by the React editor. The hooks
		// below stay because other modules extend them.
		add_action( 'scfe_save_settings', array( $this->advanced_save, 'handle' ) );
		add_action( 'scfe_reset_settings', array( $this->advanced_hooks, 'reset' ) );
		add_filter( 'scfe_export_data', array( $this->advanced_hooks, 'export' ) );
		add_action( 'scfe_import_settings', array( $this->advanced_hooks, 'import' ) );
	}
}
