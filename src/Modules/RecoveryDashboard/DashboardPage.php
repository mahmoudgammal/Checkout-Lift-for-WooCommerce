<?php
/**
 * Recovery dashboard admin screen.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Admin\AdminMenu;

/**
 * Registers the screen and renders the root the React app mounts into.
 *
 * A new screen rather than an extension of the field editor, so it can be
 * built in React from the start without touching the older template. That
 * template is scheduled for replacement, and adding to it would be work
 * thrown away.
 */
final class DashboardPage {

	/**
	 * Menu slug and the id the app mounts on.
	 */
	public const SLUG = 'scfe-checkout-recovery';

	/**
	 * Register the submenu entry.
	 */
	public function register_menu(): void {
		add_submenu_page(
			AdminMenu::PARENT,
			__( 'Recovery', 'checkout-lift-for-woocommerce' ),
			__( 'Recovery', 'checkout-lift-for-woocommerce' ),
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
