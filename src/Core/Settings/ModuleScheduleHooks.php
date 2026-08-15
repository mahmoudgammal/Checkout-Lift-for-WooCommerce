<?php
/**
 * Stable schedule hooks shared across Lite settings and Pro modules.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Settings;

/**
 * Names scheduled by optional modules but cleared from both plugin lifecycles.
 */
final class ModuleScheduleHooks {

	public const FILE_UPLOAD_CLEANUP = 'scfe_cleanup_temp_files';

	public const REVENUE_CLASSIFY = 'scfe_revenue_classify_attempts';
}
