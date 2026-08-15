<?php
/**
 * Data-handling settings for stored checkout activity.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy;

/**
 * Owns how long recovery data is kept and what happens to it on uninstall.
 *
 * These rows describe people who never completed a purchase, so the defaults
 * lean towards keeping less: a bounded retention window, and removal when the
 * plugin is deleted. Both are the merchant's to change.
 */
final class PrivacySettings {

	private const OPTION_NAME = 'scfe_privacy';

	/**
	 * Days a cart is kept before the retention sweep removes it.
	 */
	private const DEFAULT_RETENTION_DAYS = 60;

	/**
	 * Shortest and longest retention a merchant may configure.
	 */
	private const MIN_RETENTION_DAYS = 1;
	private const MAX_RETENTION_DAYS = 730;

	/**
	 * Settings cached for this request.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cached = null;

	/**
	 * Return whether checkout activity may be recorded at all.
	 *
	 * Defaults to off. Installing or updating a plugin must never silently
	 * begin collecting personal data; the merchant has to decide to start.
	 */
	public function tracking_enabled(): bool {
		return ! empty( $this->settings()['tracking_enabled'] );
	}

	/**
	 * Return whether a customer must actively agree before anything is stored.
	 *
	 * Defaults to on, which is the position that holds in the strictest
	 * jurisdictions the plugin will be used in. A merchant whose local law is
	 * more permissive can turn it off deliberately.
	 */
	public function require_consent(): bool {
		$stored = $this->settings()['require_consent'] ?? null;

		return null === $stored ? true : (bool) $stored;
	}

	/**
	 * Return the wording shown beside the consent control.
	 */
	public function consent_text(): string {
		$stored = $this->settings()['consent_text'] ?? '';

		if ( is_string( $stored ) && '' !== trim( $stored ) ) {
			return $stored;
		}

		return __( 'Save my details so I can finish this order later.', 'checkout-lift-for-woocommerce' );
	}

	/**
	 * Return how many days a cart may be kept.
	 */
	public function retention_days(): int {
		$stored = $this->settings()['retention_days'] ?? null;

		if ( ! is_numeric( $stored ) ) {
			return self::DEFAULT_RETENTION_DAYS;
		}

		return max( self::MIN_RETENTION_DAYS, min( self::MAX_RETENTION_DAYS, (int) $stored ) );
	}

	/**
	 * Return whether stored data is removed when the plugin is deleted.
	 */
	public function delete_on_uninstall(): bool {
		$stored = $this->settings()['delete_on_uninstall'] ?? null;

		// Unconfigured sites default to removing personal data.
		return null === $stored ? true : (bool) $stored;
	}

	/**
	 * Replace the stored settings after clamping them to the allowed range.
	 *
	 * @param array<string, mixed> $settings Submitted settings.
	 */
	public function replace( array $settings ): bool {
		$sanitized = array(
			'retention_days'      => $this->clamp_days( $settings['retention_days'] ?? null ),
			'delete_on_uninstall' => ! empty( $settings['delete_on_uninstall'] ),
			'tracking_enabled'    => ! empty( $settings['tracking_enabled'] ),
			'require_consent'     => ! array_key_exists( 'require_consent', $settings ) || ! empty( $settings['require_consent'] ),
			'consent_text'        => sanitize_text_field( (string) ( $settings['consent_text'] ?? '' ) ),
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
	 * Clamp a submitted retention period into the allowed range.
	 *
	 * @param mixed $days Submitted retention period.
	 */
	private function clamp_days( $days ): int {
		if ( ! is_numeric( $days ) ) {
			return self::DEFAULT_RETENTION_DAYS;
		}

		return max( self::MIN_RETENTION_DAYS, min( self::MAX_RETENTION_DAYS, (int) $days ) );
	}
}
