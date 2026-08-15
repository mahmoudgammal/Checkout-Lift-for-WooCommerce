<?php
/**
 * The plugin's settings screen.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Settings;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Admin\AdminMenu;

/**
 * Registers one place for everything that is a setting.
 *
 * Settings had grown into whichever screen happened to need them: recovery
 * timings behind the Recovery tab, multi-step and autocomplete behind the
 * field editor's Advanced tab. Each was findable only by someone who already
 * knew where to look.
 */
final class SettingsPage {

	/**
	 * Page slug.
	 */
	public const SLUG = 'scfe-settings';

	/**
	 * Register the submenu entry.
	 */
	public function register_menu(): void {
		add_submenu_page(
			AdminMenu::PARENT,
			__( 'Settings', 'checkout-lift-for-woocommerce' ),
			__( 'Settings', 'checkout-lift-for-woocommerce' ),
			AdminMenu::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the mount point.
	 *
	 * The message is what a visitor sees if the built asset is missing, which
	 * is the one failure mode that would otherwise show a blank screen with
	 * no explanation.
	 */
	public function render(): void {
		printf(
			'<div class="wrap"><div id="%s">%s</div></div>',
			esc_attr( self::SLUG ),
			esc_html__( 'Loading…', 'checkout-lift-for-woocommerce' )
		);
	}
}
