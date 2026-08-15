<?php
/**
 * Schema installation and versioned upgrades.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Database;

/**
 * Brings the database up to the schema version this build expects.
 *
 * The stored version is compared before doing any work, because dbDelta reads
 * the full table description and must never run on an ordinary request. The
 * check runs on load rather than only on activation, so a plugin updated over
 * FTP or by WP-CLI still migrates.
 */
final class Migrator {

	/**
	 * Schema version this build expects.
	 *
	 * Raise this when a statement in Schema changes.
	 */
	public const VERSION = 3;

	/**
	 * Option holding the installed schema version.
	 */
	public const VERSION_OPTION = 'scfe_db_version';

	/**
	 * Create the migrator.
	 *
	 * @param Schema     $schema Table definitions.
	 * @param TableNames $tables Table name resolver.
	 */
	public function __construct( private Schema $schema, private TableNames $tables ) {
	}

	/**
	 * Return whether the database is behind this build.
	 */
	public function needs_migration(): bool {
		return $this->installed_version() < self::VERSION;
	}

	/**
	 * Install or upgrade the schema, then record the version.
	 *
	 * @return bool Whether any work was performed.
	 */
	public function migrate(): bool {
		if ( ! $this->needs_migration() ) {
			return false;
		}

		if ( 1 === $this->installed_version() ) {
			$this->deduplicate_messages();
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $this->schema->statements() as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::VERSION_OPTION, self::VERSION, false );

		return true;
	}

	/**
	 * Return the schema version currently installed.
	 */
	public function installed_version(): int {
		$stored = get_option( self::VERSION_OPTION, 0 );

		return is_numeric( $stored ) ? (int) $stored : 0;
	}

	/**
	 * Keep one terminal-preferred row before adding the logical unique key.
	 *
	 * Sent wins first so an already delivered reminder can never become
	 * pending again. Other terminal outcomes win over pending, then the oldest
	 * row wins ties. The query is only run while upgrading schema version one.
	 */
	private function deduplicate_messages(): void {
		global $wpdb;

		$table       = $this->tables->name( TableNames::MESSAGES );
		$winner_rank = "CASE winner.status WHEN 'sent' THEN 4 WHEN 'cancelled' THEN 3 WHEN 'failed' THEN 2 WHEN 'pending' THEN 1 ELSE 0 END";
		$loser_rank  = "CASE loser.status WHEN 'sent' THEN 4 WHEN 'cancelled' THEN 3 WHEN 'failed' THEN 2 WHEN 'pending' THEN 1 ELSE 0 END";
		$sql         = "DELETE loser FROM %i AS loser INNER JOIN %i AS winner ON winner.cart_id = loser.cart_id AND winner.channel = loser.channel WHERE {$winner_rank} > {$loser_rank} OR ({$winner_rank} = {$loser_rank} AND winner.id < loser.id)";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The statement is fixed migration SQL and both identifiers are bound.
		$wpdb->query( $wpdb->prepare( $sql, $table, $table ) );
	}
}
