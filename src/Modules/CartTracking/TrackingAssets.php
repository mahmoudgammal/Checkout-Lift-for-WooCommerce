<?php
/**
 * Checkout tracker assets.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Assets\CheckoutBridgeAssets;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\ConsentPolicy;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings;

/**
 * Loads the tracker only where it can do something.
 */
final class TrackingAssets {

	/**
	 * Seconds of quiet before a change is sent.
	 *
	 * Long enough that a customer typing an email address produces one
	 * request rather than one per character, and comfortably above the
	 * server's two-second throttle.
	 */
	private const DEBOUNCE_SECONDS = 3;

	/**
	 * Create the asset loader.
	 *
	 * @param ConsentPolicy   $consent    Consent gate.
	 * @param PrivacySettings $settings   Privacy settings.
	 * @param CheckoutBlocks  $blocks     Which checkout is configured.
	 * @param string          $plugin_url Plugin URL.
	 * @param string          $version    Asset version.
	 */
	public function __construct(
		private ConsentPolicy $consent,
		private PrivacySettings $settings,
		private CheckoutBlocks $blocks,
		private string $plugin_url,
		private string $version
	) {
	}

	/**
	 * Enqueue the tracker on the checkout when tracking is switched on.
	 */
	public function enqueue(): void {
		if ( ! is_checkout() || is_order_received_page() || ! $this->settings->tracking_enabled() ) {
			return;
		}

		wp_enqueue_script(
			'scfe-cart-tracker',
			$this->plugin_url . 'assets/js/cart-tracker.js',
			$this->dependencies(),
			$this->version,
			true
		);

		wp_localize_script(
			'scfe-cart-tracker',
			'scfe_tracking',
			array(
				'endpoint'          => rest_url( CaptureController::NAMESPACE . CaptureController::ROUTE ),
				'nonce'             => wp_create_nonce( 'wp_rest' ),
				'debounce'          => self::DEBOUNCE_SECONDS * 1000,
				'consentRequired'   => $this->consent->needs_control(),
				'consentField'      => ConsentField::FIELD_ID,
				'blockConsentField' => ConsentField::BLOCK_FIELD_ID,
			)
		);
	}

	/**
	 * Return the scripts the tracker needs on this checkout.
	 *
	 * The bridge exists to translate WooCommerce's jQuery events, and it
	 * declares jQuery as a dependency. On a block checkout there are no such
	 * events and the tracker never asks the bridge for anything, so requiring
	 * it there would pull jQuery onto a page that had done without it.
	 *
	 * Getting this wrong in either direction is not fatal. The tracker decides
	 * which reader to use from the rendered markup, so the worst outcome is a
	 * script loaded that nothing calls.
	 *
	 * @return array<int, string>
	 */
	private function dependencies(): array {
		return $this->blocks->in_use() ? array() : array( CheckoutBridgeAssets::HANDLE );
	}
}
