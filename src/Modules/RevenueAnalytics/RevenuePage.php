<?php
/**
 * Revenue analytics admin page.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Admin\AdminMenu;

/**
 * Registers the analytics screen and React mount point.
 */
final class RevenuePage {

	public const SLUG = 'scfe-revenue-analytics';

	/**
	 * Register the submenu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			AdminMenu::PARENT,
			__( 'Revenue Analytics', 'checkout-lift-for-woocommerce' ),
			__( 'Revenue Analytics', 'checkout-lift-for-woocommerce' ),
			AdminMenu::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the accessible loading fallback.
	 */
	public function render(): void {
		printf(
			'<div class="wrap"><div id="%s">%s</div></div>',
			esc_attr( self::SLUG ),
			esc_html__( 'Loading…', 'checkout-lift-for-woocommerce' )
		);
	}
}
