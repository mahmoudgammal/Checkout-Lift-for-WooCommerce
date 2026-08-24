<?php
/**
 * Resolved names of the plugin's custom tables.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Database;

/**
 * Single source of truth for custom table names.
 *
 * Names are resolved through `$wpdb->prefix` so multisite installs and
 * prefixed databases keep working. Nothing else in the plugin may build a
 * table name by hand.
 */
final class TableNames {

	/**
	 * Abandoned and in-progress checkouts.
	 */
	public const CARTS = 'scfe_carts';

	/**
	 * Queued and delivered recovery messages.
	 */
	public const MESSAGES = 'scfe_messages';

	/**
	 * Privacy-minimized checkout attempt lifecycle.
	 */
	public const ATTEMPTS = 'scfe_checkout_attempts';

	/**
	 * Return every table this plugin owns.
	 *
	 * @return list<string> Unprefixed table names.
	 */
	public static function all(): array {
		return array( self::CARTS, self::MESSAGES, self::ATTEMPTS );
	}

	/**
	 * Return the prefixed name of one table.
	 *
	 * @param string $table Unprefixed table name.
	 */
	public function name( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . $table;
	}

	/**
	 * Return prefixed names for every table this plugin owns.
	 *
	 * @return list<string>
	 */
	public function all_names(): array {
		return array_map( array( $this, 'name' ), self::all() );
	}
}
