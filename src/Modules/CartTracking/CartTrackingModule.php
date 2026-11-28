<?php
/**
 * Checkout activity tracking module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\AbandonmentMarker;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoveryHooks;

/**
 * Registers the capture endpoint.
 *
 * The route is registered unconditionally so it answers predictably, and
 * refuses inside the handler when tracking is off. Registering conditionally
 * would make a disabled feature return 404, which is indistinguishable from a
 * broken install when a merchant asks why recovery is not working.
 */
final class CartTrackingModule implements Module {

	/**
	 * Create the tracking module.
	 *
	 * @param CaptureController   $controller Capture endpoint.
	 * @param ConsentField        $field      Checkout consent control.
	 * @param TrackingAssets      $assets     Tracker assets.
	 * @param CheckoutCompletion  $completion Records completed tracked checkouts.
	 * @param AbandonmentSchedule $schedule   Keeps the abandonment check scheduled.
	 * @param AbandonmentMarker   $marker     Marks quiet checkouts abandoned.
	 */
	public function __construct(
		private CaptureController $controller,
		private ConsentField $field,
		private TrackingAssets $assets,
		private CheckoutCompletion $completion,
		private AbandonmentSchedule $schedule,
		private AbandonmentMarker $marker
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'cart-tracking';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies(): array {
		return array( 'database', 'privacy' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {
		add_action( 'rest_api_init', array( $this->controller, 'register_routes' ) );

		// Classic consent sits outside the AJAX-refreshed order review; Blocks uses its own hook.
		add_action( 'woocommerce_after_order_notes', array( $this->field, 'render' ) );

		// Block fields must register here to enter the Store API schema.
		add_action( 'woocommerce_init', array( $this->field, 'register_block_field' ) );

		add_action( 'wp_enqueue_scripts', array( $this->assets, 'enqueue' ) );

		// Close tracked carts from both classic and Store API checkout paths.
		add_action( 'woocommerce_checkout_order_processed', array( $this->completion, 'from_classic_checkout' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this->completion, 'from_store_api' ) );

		// Runs after an older add-on's own scan on the same hook, so that scan
		// still claims the rows it schedules messages for.
		add_filter( 'cron_schedules', array( $this->schedule, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- AbandonmentSchedule registers 15 minutes, the minimum the sniff allows.
		add_action( RecoveryHooks::SCAN, array( $this->marker, 'run' ), 20 );

		// Scheduling writes options, so it is kept off customer requests.
		add_action( 'admin_init', array( $this->schedule, 'schedule' ) );
	}
}
