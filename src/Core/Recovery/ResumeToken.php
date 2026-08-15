<?php
/**
 * Resume tokens for stored checkouts.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery;

/**
 * Mints and hashes the token that lets a customer resume their checkout.
 *
 * A recovery link is a bearer credential: whoever holds it sees the name,
 * address, and contents of that checkout. It therefore has to be unguessable,
 * and it must not be readable from a database copy, so only its hash is
 * stored. Lookups are performed on the hash, which is also what keeps the
 * comparison out of PHP and free of timing concerns.
 */
final class ResumeToken {

	/**
	 * Bytes of entropy per token.
	 *
	 * 32 bytes is 256 bits, rendered as 64 hexadecimal characters. Guessing
	 * one is not a practical attack at any request rate.
	 */
	private const ENTROPY_BYTES = 32;

	/**
	 * Query argument carrying the token on a resume link.
	 */
	public const QUERY_ARG = 'scfe_resume';

	/**
	 * Mint a new token.
	 *
	 * `random_bytes()` is the only acceptable source here. `wp_rand()` and
	 * `uniqid()` are predictable enough to matter for a bearer credential.
	 *
	 * @return string Raw token, to be sent to the customer and never stored.
	 */
	public function create(): string {
		return bin2hex( random_bytes( self::ENTROPY_BYTES ) );
	}

	/**
	 * Return the stored form of a token.
	 *
	 * @param string $token Raw token.
	 * @return string Hash matching the `token_hash` column, or an empty string
	 *                when the token is not well formed.
	 */
	public function hash( string $token ): string {
		if ( ! $this->is_well_formed( $token ) ) {
			return '';
		}

		return hash( 'sha256', $token );
	}

	/**
	 * Check that a candidate looks like a token this class minted.
	 *
	 * Rejecting malformed input before it reaches a query keeps obviously
	 * hostile values out of the lookup entirely.
	 *
	 * @param string $token Candidate token.
	 */
	public function is_well_formed( string $token ): bool {
		return 1 === preg_match( '/^[a-f0-9]{' . ( self::ENTROPY_BYTES * 2 ) . '}$/', $token );
	}

	/**
	 * Build the link that resumes a stored checkout.
	 *
	 * @param string $token       Raw token.
	 * @param string $checkout_url Checkout page URL.
	 */
	public function url( string $token, string $checkout_url ): string {
		return add_query_arg( self::QUERY_ARG, $token, $checkout_url );
	}
}
