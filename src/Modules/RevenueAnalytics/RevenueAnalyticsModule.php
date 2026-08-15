<?php
/**
 * Revenue analytics module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleScheduleHooks;

/**
 * Registers collection, reconciliation, reporting, and the admin screen.
 */
final class RevenueAnalyticsModule implements Module {

	private const CRON_SCHEDULE = 'scfe_revenue_fifteen_minutes';

	/**
	 * Create the module.
	 *
	 * @param AttemptTracker        $tracker     Attempt lifecycle.
	 * @param AttributionRecorder   $attribution Attribution writer.
	 * @param AbandonmentClassifier $classifier  Abandonment scanner.
	 * @param RevenueController     $controller  Reporting endpoint.
	 * @param RevenuePage           $page        Admin page.
	 * @param RevenueAssets         $assets      Admin assets.
	 * @param RevenueRetention      $retention   Analytics cleanup.
	 */
	public function __construct(
		private AttemptTracker $tracker,
		private AttributionRecorder $attribution,
		private AbandonmentClassifier $classifier,
		private RevenueController $controller,
		private RevenuePage $page,
		private RevenueAssets $assets,
		private RevenueRetention $retention
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'revenue-analytics';
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
		add_action( 'scfe_checkout_activity_captured', array( $this->tracker, 'touch' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this->tracker, 'from_classic_checkout' ), 20 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this->tracker, 'from_store_api' ), 20 );
		add_action( 'woocommerce_order_status_changed', array( $this->tracker, 'reconcile' ), 10, 1 );
		add_action( 'woocommerce_order_refunded', array( $this->tracker, 'reconcile_refund' ), 20, 2 );
		add_action( 'scfe_recovery_order_attributed', array( $this->attribution, 'record_recovery' ), 10, 2 );
		add_action( 'scfe_recovery_order_refunded', array( $this->attribution, 'record_recovery_refund' ), 10, 2 );
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( ModuleScheduleHooks::REVENUE_CLASSIFY, array( $this->classifier, 'run' ) );
		add_action( ModuleScheduleHooks::REVENUE_CLASSIFY, array( $this->retention, 'run' ), 20 );
		add_action( 'admin_menu', array( $this->page, 'register_menu' ), 21 );
		add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue' ) );
		add_action( 'rest_api_init', array( $this->controller, 'register_routes' ) );
	}

	/**
	 * Register the bounded classifier interval.
	 *
	 * @param array<string, array<string, mixed>> $schedules Cron schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_schedule( array $schedules ): array {
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every fifteen minutes', 'checkout-lift-for-woocommerce' ),
		);

		return $schedules;
	}

	/**
	 * Ensure the classifier event exists.
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( ModuleScheduleHooks::REVENUE_CLASSIFY ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, ModuleScheduleHooks::REVENUE_CLASSIFY );
		}
	}
}
