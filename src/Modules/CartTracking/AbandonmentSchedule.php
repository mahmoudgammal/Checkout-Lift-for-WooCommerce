<?php
/**
 * Recurring check for abandoned checkouts.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoveryHooks;

/**
 * Keeps the abandonment check scheduled every fifteen minutes.
 */
final class AbandonmentSchedule {

	/**
	 * Cron schedule name.
	 */
	private const SCHEDULE = 'scfe_recovery_fifteen_minutes';

	/**
	 * Register the fifteen-minute interval.
	 *
	 * @param array<string, array<string, mixed>> $schedules Registered schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_schedule( array $schedules ): array {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every fifteen minutes', 'checkout-lift-for-woocommerce' ),
		);

		return $schedules;
	}

	/**
	 * Schedule the check if it is not already scheduled.
	 *
	 * An existing event keeps its interval, so a site where an earlier add-on
	 * scheduled it more often keeps that pace.
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( RecoveryHooks::SCAN ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, RecoveryHooks::SCAN );
		}
	}
}
