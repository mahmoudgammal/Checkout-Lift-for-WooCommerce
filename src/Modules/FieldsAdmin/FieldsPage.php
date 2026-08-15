<?php
/**
 * React checkout fields screen.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Admin\AdminMenu;

/**
 * Registers the checkout fields editor.
 *
 * This is now the primary screen. The classic editor is still registered
 * below it rather than deleted, because it is what customers have been using
 * and a fallback costs little while this one earns trust. Removing it is a
 * separate decision, not a side effect of promoting this one.
 */
final class FieldsPage {

	/**
	 * Menu slug and the id the app mounts on.
	 */
	public const SLUG = 'scfe-checkout-fields';

	/**
	 * Register the submenu entry.
	 */
	public function register_menu(): void {
		// Priority puts this above the classic screen, so the first entry is
		// the one merchants should be using.
		add_submenu_page(
			AdminMenu::PARENT,
			__( 'Checkout Fields', 'checkout-lift-for-woocommerce' ),
			__( 'Checkout Fields', 'checkout-lift-for-woocommerce' ),
			AdminMenu::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the mount point.
	 */
	public function render(): void {
		printf(
			'<div class="wrap"><div id="%s">%s</div></div>',
			esc_attr( self::SLUG ),
			esc_html__( 'Loading…', 'checkout-lift-for-woocommerce' )
		);
	}
}
