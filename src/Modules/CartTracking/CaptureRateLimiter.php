<?php
/**
 * Throttling for the public capture endpoint.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking;

/**
 * Enforces per-session, per-address, and site-wide public write limits.
 */
final class CaptureRateLimiter {

	/**
	 * Shortest gap between two writes for one session, in seconds.
	 */
	private const SESSION_INTERVAL = 2;

	/**
	 * Requests allowed from one address per hour.
	 */
	private const ADDRESS_LIMIT = 300;

	/**
	 * Site-wide requests accepted per hour.
	 */
	private const SITE_LIMIT = 1800;

	/**
	 * Atomically apply the session, address, and site limits.
	 *
	 * @param string $session_key Checkout session identifier.
	 */
	public function allow( string $session_key ): bool {
		if ( '' === $session_key ) {
			return false;
		}

		if ( ! $this->lock() ) {
			return false;
		}

		try {
			$limits  = array(
				array( 'scfe_cap_site', self::SITE_LIMIT, HOUR_IN_SECONDS ),
				array( 'scfe_cap_session_' . md5( $session_key ), 1, self::SESSION_INTERVAL ),
			);
			$address = $this->address();

			if ( '' !== $address ) {
				$limits[] = array( 'scfe_cap_address_' . md5( $address ), self::ADDRESS_LIMIT, HOUR_IN_SECONDS );
			}

			foreach ( $limits as $limit ) {
				if ( $this->count( $limit[0] ) >= $limit[1] ) {
					return false;
				}
			}

			foreach ( $limits as $limit ) {
				set_transient( $limit[0], $this->count( $limit[0] ) + 1, $limit[2] );
			}

			return true;
		} finally {
			$this->unlock();
		}
	}

	/**
	 * Acquire the database-scoped capture lock.
	 */
	private function lock(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- A connection-scoped advisory lock serializes the public write budget; the name is bound.
		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $this->lock_name(), 1 ) );

		return 1 === (int) $locked;
	}

	/**
	 * Release the database-scoped capture lock.
	 */
	private function unlock(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Releases only the connection-scoped advisory lock acquired above.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $this->lock_name() ) );
	}

	/**
	 * Return a lock name isolated to the current site's tables.
	 */
	private function lock_name(): string {
		global $wpdb;

		return 'scfe_capture_' . md5( (string) $wpdb->prefix );
	}

	/**
	 * Read one transient counter.
	 *
	 * @param string $key Transient key.
	 */
	private function count( string $key ): int {
		$count = get_transient( $key );

		return is_numeric( $count ) ? (int) $count : 0;
	}

	/**
	 * Return the client address.
	 *
	 * WooCommerce resolves supported proxy headers before the value is hashed.
	 */
	private function address(): string {
		if ( class_exists( '\WC_Geolocation' ) ) {
			return (string) \WC_Geolocation::get_ip_address();
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Value is only hashed into a transient key, never stored or output.
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
