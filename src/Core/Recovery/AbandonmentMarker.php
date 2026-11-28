<?php
/**
 * Mark quiet checkouts abandoned.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartRepository;

/**
 * Moves checkouts with no activity past the idle time from open to abandoned.
 */
final class AbandonmentMarker {

	/**
	 * Rows handled per run, so one run never holds the cron request for long.
	 */
	private const BATCH = 100;

	/**
	 * Create the marker.
	 *
	 * @param CartRepository   $carts    Captured checkouts.
	 * @param RecoverySettings $settings Merchant's idle time.
	 */
	public function __construct(
		private CartRepository $carts,
		private RecoverySettings $settings
	) {
	}

	/**
	 * Mark every checkout that has been quiet for longer than the idle time.
	 *
	 * Each marked row is announced on the scfe_checkout_abandoned action, which
	 * is where an add-on schedules its follow-up messages.
	 */
	public function run(): void {
		// The revenue classifier reads the same filter, so both agree on the cutoff.
		$minutes = max( 1, (int) apply_filters( 'scfe_recovery_idle_minutes', $this->settings->idle_minutes() ) );

		foreach ( $this->carts->find_newly_abandoned( $minutes, self::BATCH ) as $cart ) {
			$cart_id = (int) ( $cart['id'] ?? 0 );

			if ( $cart_id < 1 || ! $this->carts->update( $cart_id, array( 'status' => 'abandoned' ) ) ) {
				continue;
			}

			$cart['status'] = 'abandoned';

			/**
			 * Fires after a checkout is marked abandoned.
			 *
			 * @param array<string, mixed> $cart The checkout row.
			 */
			do_action( 'scfe_checkout_abandoned', $cart );
		}
	}
}
