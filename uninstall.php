<?php
/**
 * Uninstall script for Checkout Lift.
 *
 * This file runs when the plugin is deleted from WordPress.
 * It removes plugin settings and the custom tables it owns.
 *
 * @package SimpleCheckoutFieldsEditor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Stored checkout activity describes people who never completed a purchase.
 * Whether deleting the plugin also destroys it is the merchant's decision, so
 * the setting is read before anything is dropped. Absent means never
 * configured, and the safer default for personal data is removal.
 *
 * Values are read directly rather than through the plugin's classes, because
 * uninstall.php runs without the plugin bootstrapped.
 */
$scfe_privacy     = get_option( 'scfe_privacy', array() );
$scfe_delete_data = ! is_array( $scfe_privacy ) || ! array_key_exists( 'delete_on_uninstall', $scfe_privacy )
	? true
	: (bool) $scfe_privacy['delete_on_uninstall'];

if ( ! $scfe_delete_data ) {
	// The merchant asked to keep their data. Leave the tables and the schema
	// version in place so a reinstall picks up exactly where it left off.
	return;
}

delete_option( 'scfe_checkout_fields' );
delete_option( 'scfe_disable_shipping' );
delete_option( 'scfe_multistep_settings' );
delete_option( 'scfe_address_autocomplete' );
delete_option( 'scfe_multilingual_notice_dismissed' );
delete_option( 'scfe_db_version' );
delete_option( 'scfe_privacy' );
delete_option( 'scfe_recovery' );
delete_option( 'scfe_modules' );

global $wpdb;

foreach ( array( 'scfe_carts', 'scfe_messages', 'scfe_checkout_attempts', 'scfe_attributions' ) as $scfe_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . $scfe_table . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers cannot be bound, and the name is built from a hardcoded suffix plus the site prefix.
}
