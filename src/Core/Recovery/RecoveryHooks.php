<?php
/**
 * Stable recovery hook names shared by Lite lifecycle code and Pro automation.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery;

/**
 * Defines the persisted cron event names without coupling Lite to Pro classes.
 */
final class RecoveryHooks {

	/**
	 * Cron hook that marks quiet checkouts abandoned.
	 */
	public const SCAN = 'scfe_recovery_scan';

	/**
	 * Cron hook that delivers due messages.
	 */
	public const SEND = 'scfe_recovery_send';

	/**
	 * This class only carries stable identifiers.
	 */
	private function __construct() {
	}
}
