<?php
/**
 * The plugin's own admin menu.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Admin;

/**
 * Owns the top-level menu every plugin screen lives under.
 *
 * Screens were previously scattered as separate WooCommerce submenu entries,
 * which gave the plugin no identity in the sidebar and no obvious home as more
 * screens arrive. One parent keeps them together and makes room for what comes
 * next without adding another WooCommerce entry each time.
 */
final class AdminMenu {

	/**
	 * Parent menu slug every screen attaches to.
	 */
	public const PARENT = 'simple-checkout';

	/**
	 * Capability required for every screen.
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Register the parent menu.
	 *
	 * Registered at priority 9 so screens hooking at the default priority
	 * find the parent already present.
	 */
	public function register(): void {
		add_menu_page(
			__( 'Checkout Lift', 'checkout-lift-for-woocommerce' ),
			__( 'Checkout Lift', 'checkout-lift-for-woocommerce' ),
			self::CAPABILITY,
			self::PARENT,
			'',
			'dashicons-cart',
			57
		);
	}

	/**
	 * Remove the duplicate first submenu entry WordPress adds.
	 *
	 * A parent with no callback still repeats itself as its own first child.
	 * The first real screen is promoted instead, so the menu reads as intended.
	 */
	public function tidy_submenu(): void {
		global $submenu;

		if ( ! isset( $submenu[ self::PARENT ] ) || ! is_array( $submenu[ self::PARENT ] ) ) {
			return;
		}

		foreach ( $submenu[ self::PARENT ] as $index => $entry ) {
			if ( isset( $entry[2] ) && self::PARENT === $entry[2] ) {
				unset( $submenu[ self::PARENT ][ $index ] );
			}
		}
	}
}
