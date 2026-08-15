<?php
/**
 * Scheduled removal of expired checkout activity.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartRepository;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\MessageRepository;

/**
 * Enforces the configured retention window.
 *
 * A retention setting nobody acts on is worse than none, because it implies a
 * promise the plugin is not keeping. This is what makes it true.
 */
final class RetentionSweep {

	/**
	 * Cron hook that runs the sweep.
	 */
	public const HOOK = 'scfe_retention_sweep';

	/**
	 * Carts removed per run.
	 *
	 * Bounded so a large backlog clears over several days rather than in one
	 * long transaction that could time out or lock the table.
	 */
	private const CART_BATCH = 500;

	/**
	 * Orphaned messages removed per run.
	 */
	private const MESSAGE_BATCH = 1000;

	/**
	 * Create the sweep.
	 *
	 * @param CartRepository    $carts    Cart storage.
	 * @param MessageRepository $messages Message storage.
	 */
	public function __construct(
		private CartRepository $carts,
		private MessageRepository $messages
	) {
	}

	/**
	 * Ensure the sweep is scheduled.
	 */
	public function schedule(): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
	}

	/**
	 * Remove expired carts and any messages left without a cart.
	 */
	public function run(): void {
		$this->carts->delete_expired( self::CART_BATCH );
		$this->messages->delete_orphans( self::MESSAGE_BATCH );
	}
}
