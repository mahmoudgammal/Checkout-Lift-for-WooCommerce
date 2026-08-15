<?php
/**
 * Bounded checkout-attempt abandonment scan.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoverySettings;

/**
 * Applies the configured recovery inactivity threshold to attempts.
 */
final class AbandonmentClassifier {

	/**
	 * Create the classifier.
	 *
	 * @param AttemptRepository $attempts Attempt storage.
	 * @param RecoverySettings  $settings Recovery timing.
	 */
	public function __construct(
		private AttemptRepository $attempts,
		private RecoverySettings $settings
	) {
	}

	/**
	 * Classify one bounded batch.
	 */
	public function run(): void {
		$minutes = max( 1, (int) apply_filters( 'scfe_recovery_idle_minutes', $this->settings->idle_minutes() ) );
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - ( $minutes * MINUTE_IN_SECONDS ) );
		$this->attempts->abandon_before( $cutoff, 100 );
	}
}
