<?php
/**
 * Checkout-attempt token stored in the WooCommerce session.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics;

/**
 * Manages the anonymous token for the current checkout session.
 */
final class AttemptSession {

	private const SESSION_KEY = 'scfe_revenue_attempt';

	/**
	 * Return or create the current token.
	 */
	public function token(): string {
		$session = $this->session();

		if ( null === $session ) {
			return '';
		}

		$token = $session->get( self::SESSION_KEY, '' );

		if ( ! is_string( $token ) || 64 !== strlen( $token ) ) {
			$token = bin2hex( random_bytes( 32 ) );
			$session->set( self::SESSION_KEY, $token );
		}

		return $token;
	}

	/**
	 * Return the irreversible token hash used in storage.
	 */
	public function hash(): string {
		$token = $this->token();

		return '' === $token ? '' : hash( 'sha256', $token );
	}

	/**
	 * End the current attempt.
	 */
	public function rotate(): void {
		$session = $this->session();

		if ( null !== $session ) {
			$session->set( self::SESSION_KEY, '' );
		}
	}

	/**
	 * Return the WooCommerce session when available.
	 */
	private function session(): ?object {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		if ( null === WC()->session && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		return is_object( WC()->session ) ? WC()->session : null;
	}
}
