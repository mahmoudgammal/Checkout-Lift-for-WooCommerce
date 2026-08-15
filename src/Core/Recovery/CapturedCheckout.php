<?php
/**
 * Sanitized checkout data submitted for capture.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery;

/**
 * Reduces a capture request to the fields a recovery message actually needs.
 *
 * This is the trust boundary for tracking. The payload arrives from the
 * browser, so nothing here is believed: fields are allowlisted, lengths are
 * bounded to the column widths, and the cart total is read from WooCommerce
 * rather than from the request, because a browser must never be able to state
 * what an order is worth.
 */
final class CapturedCheckout {

	/**
	 * Longest accepted value for a free-text field.
	 *
	 * Matches the 191-character columns, so an oversized value is refused
	 * here rather than silently truncated by MySQL.
	 */
	private const MAX_TEXT = 191;

	/**
	 * Checkout steps a client may report.
	 *
	 * @var list<string>
	 */
	private const STEPS = array( '', 'contact', 'billing', 'shipping', 'payment', 'review' );

	/**
	 * Build sanitized customer fields from a capture payload.
	 *
	 * @param array<string, mixed> $payload Raw request payload.
	 * @return array<string, mixed>
	 */
	public function customer_fields( array $payload ): array {
		$email = $this->text( $payload['email'] ?? '' );

		return array(
			'email'           => is_email( $email ) ? sanitize_email( $email ) : '',
			'phone'           => $this->bounded( $this->text( $payload['phone'] ?? '' ), 32 ),
			'first_name'      => $this->text( $payload['first_name'] ?? '' ),
			'last_name'       => $this->text( $payload['last_name'] ?? '' ),
			'country'         => $this->country( $payload['country'] ?? '' ),
			'checkout_step'   => $this->step( $payload['checkout_step'] ?? '' ),
			'payment_method'  => $this->bounded( $this->text( $payload['payment_method'] ?? '' ), 64 ),
			'shipping_method' => $this->bounded( $this->text( $payload['shipping_method'] ?? '' ), 64 ),
		);
	}

	/**
	 * Return whether the payload carries anything worth storing.
	 *
	 * A row with no way to reach the customer cannot be recovered, so it is
	 * personal data collected for no purpose. Those are refused.
	 *
	 * @param array<string, mixed> $fields Sanitized customer fields.
	 */
	public function is_contactable( array $fields ): bool {
		return '' !== ( $fields['email'] ?? '' ) || '' !== ( $fields['phone'] ?? '' );
	}

	/**
	 * Sanitize one free-text value and bound it to the column width.
	 *
	 * @param mixed $value Raw value.
	 */
	private function text( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return $this->bounded( sanitize_text_field( (string) $value ), self::MAX_TEXT );
	}

	/**
	 * Trim a value to a maximum length.
	 *
	 * @param string $value  Sanitized value.
	 * @param int    $length Maximum length.
	 */
	private function bounded( string $value, int $length ): string {
		return mb_substr( $value, 0, $length );
	}

	/**
	 * Normalize a country to an uppercase two-letter code.
	 *
	 * @param mixed $value Raw value.
	 */
	private function country( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) ?? '' );

		return 2 === strlen( $code ) ? $code : '';
	}

	/**
	 * Normalize a reported checkout step to a known value.
	 *
	 * @param mixed $value Raw value.
	 */
	private function step( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$step = strtolower( (string) $value );

		return in_array( $step, self::STEPS, true ) ? $step : '';
	}
}
