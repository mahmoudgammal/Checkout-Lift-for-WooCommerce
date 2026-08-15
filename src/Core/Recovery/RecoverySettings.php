<?php
/**
 * Merchant-configurable recovery behaviour.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery;

/**
 * Timing and wording a merchant controls.
 *
 * These were previously constants reachable only through filters, which meant
 * changing when a reminder is sent required editing PHP. Anything a merchant
 * would reasonably want to change belongs in a setting, not a filter.
 */
final class RecoverySettings {

	private const OPTION_NAME = 'scfe_recovery';

	private const DEFAULT_IDLE_MINUTES     = 30;
	private const DEFAULT_REMINDER_MINUTES = 45;

	private const MIN_MINUTES = 1;
	private const MAX_MINUTES = 10080;

	/**
	 * Settings cached for this request.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cached = null;

	/**
	 * Return how long a checkout must be quiet before it counts as abandoned.
	 */
	public function idle_minutes(): int {
		return $this->minutes( 'idle_minutes', self::DEFAULT_IDLE_MINUTES );
	}

	/**
	 * Return how long after abandonment the first reminder is due.
	 */
	public function reminder_minutes(): int {
		return $this->minutes( 'reminder_minutes', self::DEFAULT_REMINDER_MINUTES );
	}

	/**
	 * Return a stored first-email subject override, if one exists.
	 */
	public function custom_email_subject(): string {
		$value = $this->settings()['email_subject'] ?? '';

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Return a stored first-email body override, if one exists.
	 */
	public function custom_email_body(): string {
		$value = $this->settings()['email_body'] ?? '';

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Return the reminder subject line.
	 */
	public function email_subject(): string {
		$stored = $this->settings()['email_subject'] ?? '';

		if ( is_string( $stored ) && '' !== trim( $stored ) ) {
			return $stored;
		}

		/* translators: %s: site name. */
		return sprintf( __( 'You left something behind at %s', 'checkout-lift-for-woocommerce' ), $this->site_name() );
	}

	/**
	 * Return the reminder body.
	 *
	 * Placeholders are deliberately few and obvious. A template language would
	 * be more powerful and much easier to get wrong.
	 */
	public function email_body(): string {
		$stored = $this->settings()['email_body'] ?? '';

		if ( is_string( $stored ) && '' !== trim( $stored ) ) {
			return $stored;
		}

		return __(
			"Hi {name},\n\nYour order is still waiting. Pick up exactly where you left off, with your details already filled in.\n\n{link}\n\nIf you have already ordered, or did not mean to start one, you can ignore this message.",
			'checkout-lift-for-woocommerce'
		);
	}

	/**
	 * Replace the stored settings after bounding them.
	 *
	 * @param array<string, mixed> $settings Submitted settings.
	 */
	public function replace( array $settings ): bool {
		$current   = $this->settings();
		$sanitized = array_merge(
			$current,
			array(
				'idle_minutes'     => $this->clamp( $settings['idle_minutes'] ?? null, self::DEFAULT_IDLE_MINUTES ),
				'reminder_minutes' => $this->clamp( $settings['reminder_minutes'] ?? null, self::DEFAULT_REMINDER_MINUTES ),
				'email_subject'    => sanitize_text_field( (string) ( $settings['email_subject'] ?? '' ) ),
				'email_body'       => sanitize_textarea_field( (string) ( $settings['email_body'] ?? '' ) ),
			)
		);

		if ( $sanitized === $this->settings() ) {
			return true;
		}

		if ( ! update_option( self::OPTION_NAME, $sanitized ) ) {
			return false;
		}

		$this->cached = $sanitized;

		return true;
	}

	/**
	 * Read the stored settings once per request.
	 *
	 * @return array<string, mixed>
	 */
	private function settings(): array {
		if ( null !== $this->cached ) {
			return $this->cached;
		}

		$stored       = get_option( self::OPTION_NAME, array() );
		$this->cached = is_array( $stored ) ? $stored : array();

		return $this->cached;
	}

	/**
	 * Read one bounded minute value.
	 *
	 * @param string $key      Setting key.
	 * @param int    $fallback Default when unset or unreadable.
	 */
	private function minutes( string $key, int $fallback ): int {
		return $this->clamp( $this->settings()[ $key ] ?? null, $fallback );
	}

	/**
	 * Bound a submitted minute value.
	 *
	 * @param mixed $value    Submitted value.
	 * @param int   $fallback Default when unreadable.
	 */
	private function clamp( $value, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( self::MIN_MINUTES, min( self::MAX_MINUTES, (int) $value ) );
	}

	/**
	 * Return the site name, decoded for use in a subject line.
	 */
	private function site_name(): string {
		return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}
}
