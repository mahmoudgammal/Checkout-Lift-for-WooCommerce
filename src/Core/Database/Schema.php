<?php
/**
 * Custom table definitions.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Database;

/**
 * Builds the CREATE TABLE statements handed to dbDelta.
 *
 * Statement formatting follows dbDelta's parser, which is stricter than SQL:
 * one field per line, two spaces after PRIMARY KEY, and KEY definitions on
 * their own lines. Reformatting these carelessly makes dbDelta re-run ALTER
 * statements on every request.
 */
final class Schema {

	/**
	 * Create the schema builder.
	 *
	 * @param TableNames $tables Table name resolver.
	 */
	public function __construct( private TableNames $tables ) {
	}

	/**
	 * Return every CREATE TABLE statement for the current version.
	 *
	 * @return list<string>
	 */
	public function statements(): array {
		return array( $this->carts(), $this->messages(), $this->attempts() );
	}

	/**
	 * Checkouts that were started, whether or not they completed.
	 *
	 * Personal data is deliberately minimal: only what a recovery message
	 * needs. `expires_at` is written on every row so retention is enforceable
	 * without reconstructing it later, and consent is recorded alongside the
	 * contact details it applies to rather than in a separate store.
	 */
	private function carts(): string {
		$table = $this->tables->name( TableNames::CARTS );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash char(64) NOT NULL,
			session_key varchar(64) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(32) NOT NULL DEFAULT '',
			first_name varchar(191) NOT NULL DEFAULT '',
			last_name varchar(191) NOT NULL DEFAULT '',
			country char(2) NOT NULL DEFAULT '',
			currency char(3) NOT NULL DEFAULT '',
			total decimal(19,4) NOT NULL DEFAULT 0.0000,
			item_count smallint(5) unsigned NOT NULL DEFAULT 0,
			items longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			checkout_step varchar(32) NOT NULL DEFAULT '',
			payment_method varchar(64) NOT NULL DEFAULT '',
			shipping_method varchar(64) NOT NULL DEFAULT '',
			recovered_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			recovered_total decimal(19,4) NOT NULL DEFAULT 0.0000,
			consent tinyint(1) NOT NULL DEFAULT 0,
			consent_source varchar(32) NOT NULL DEFAULT '',
			consent_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY status_updated (status,updated_at),
			KEY expires_at (expires_at),
			KEY session_key (session_key),
			KEY email (email)
		) {$this->charset_collate()};";
	}

	/**
	 * Recovery messages, queued and sent.
	 *
	 * Kept separate from carts so a cart can carry a whole workflow, and so a
	 * delivery retry never rewrites the cart row.
	 */
	private function messages(): string {
		$table = $this->tables->name( TableNames::MESSAGES );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cart_id bigint(20) unsigned NOT NULL,
			channel varchar(20) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			scheduled_at datetime NOT NULL,
			sent_at datetime NULL,
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			last_error varchar(191) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY cart_channel (cart_id,channel),
			KEY status_scheduled (status,scheduled_at)
		) {$this->charset_collate()};";
	}

	/**
	 * Privacy-minimized checkout attempt lifecycle.
	 */
	private function attempts(): string {
		$table = $this->tables->name( TableNames::ATTEMPTS );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash char(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			runtime varchar(16) NOT NULL DEFAULT 'classic',
			currency char(3) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_total decimal(19,4) NOT NULL DEFAULT 0.0000,
			first_seen_at datetime NOT NULL,
			last_seen_at datetime NOT NULL,
			abandoned_at datetime NULL,
			placed_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY order_id (order_id),
			KEY status_last_seen (status,last_seen_at),
			KEY currency_first_seen (currency,first_seen_at)
		) {$this->charset_collate()};";
	}

	/**
	 * Return the site's charset and collation clause.
	 */
	private function charset_collate(): string {
		global $wpdb;

		return $wpdb->get_charset_collate();
	}
}
