<?php
/**
 * Analytics retention worker.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings;

/**
 * Removes analytics facts using the merchant's recovery retention period.
 */
final class RevenueRetention {

	private const BATCH_LIMIT = 500;

	/**
	 * Create the worker.
	 *
	 * @param AttemptRepository     $attempts     Attempt storage.
	 * @param AttributionRepository $attributions Attribution storage.
	 * @param PrivacySettings       $privacy      Retention setting.
	 */
	public function __construct(
		private AttemptRepository $attempts,
		private AttributionRepository $attributions,
		private PrivacySettings $privacy
	) {
	}

	/**
	 * Delete one bounded batch in dependency order.
	 */
	public function run(): void {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $this->privacy->retention_days() * DAY_IN_SECONDS ) );
		$ids    = $this->attempts->expired_ids( $cutoff, self::BATCH_LIMIT );

		$this->attributions->delete_attempts( $ids );
		$this->attempts->delete_ids( $ids );
	}
}
