<?php
/**
 * Tidying up after a module the merchant switched off.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Settings;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoveryHooks;

/**
 * Clears the scheduled work a switched-off module leaves behind.
 *
 * Switching a module off stops it registering, which stops it *doing* the
 * work, but a hook already in WordPress's cron table stays there. It would
 * then fire for the rest of the shop's life against a listener that no longer
 * exists: harmless, and quietly wrong every hour.
 *
 * Stored data is deliberately untouched. Nothing a merchant configured or
 * captured is deleted for the sake of a switch they may flip back tomorrow.
 * Only the timers stop.
 */
final class ModuleShutdown {

	/**
	 * Hooks each module schedules, by module name.
	 *
	 * Read from the modules themselves rather than written out here. A hook
	 * name copied into a second place drifts from the first without anything
	 * failing, and this list is only ever read when a module is switched off.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function scheduled(): array {
		return array(
			'recovery'    => array( RecoveryHooks::SCAN, RecoveryHooks::SEND, ModuleScheduleHooks::REVENUE_CLASSIFY ),
			'file-upload' => array( ModuleScheduleHooks::FILE_UPLOAD_CLEANUP ),
		);
	}

	/**
	 * Clear the timers belonging to modules that have just been switched off.
	 *
	 * @param array<string, bool> $before State before the change.
	 * @param array<string, bool> $after  State after it.
	 * @return array<int, string> The hooks that were cleared.
	 */
	public function apply( array $before, array $after ): array {
		$cleared = array();

		foreach ( $this->scheduled() as $module => $hooks ) {
			$was_on = $before[ $module ] ?? true;
			$is_on  = $after[ $module ] ?? true;

			// Clear only on disable; doing this on every save can race a module rescheduling itself.
			if ( ! $was_on || $is_on ) {
				continue;
			}

			foreach ( $hooks as $hook ) {
				wp_clear_scheduled_hook( $hook );
				$cleared[] = $hook;
			}
		}

		return $cleared;
	}
}
