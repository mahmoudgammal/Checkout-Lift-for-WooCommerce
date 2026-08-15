<?php
/**
 * Decides whether checkout activity may be stored.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy;

/**
 * The single gate every write must pass.
 *
 * Putting this decision in one place means capture code cannot accidentally
 * store something it should not, and there is exactly one method to audit when
 * someone asks on what basis data was collected.
 */
final class ConsentPolicy {

	/**
	 * Recorded when the customer ticked the consent control.
	 */
	public const SOURCE_EXPLICIT = 'explicit';

	/**
	 * Recorded when the merchant operates without an explicit control.
	 */
	public const SOURCE_SETTING = 'setting';

	/**
	 * Create the policy.
	 *
	 * @param PrivacySettings $settings Privacy settings.
	 */
	public function __construct( private PrivacySettings $settings ) {
	}

	/**
	 * Return whether a cart may be stored given the customer's answer.
	 *
	 * @param bool $consent_given Whether the customer actively agreed.
	 */
	public function may_store( bool $consent_given ): bool {
		if ( ! $this->settings->tracking_enabled() ) {
			return false;
		}

		if ( ! $this->settings->require_consent() ) {
			return true;
		}

		return $consent_given;
	}

	/**
	 * Return whether the consent control should be rendered at checkout.
	 */
	public function needs_control(): bool {
		return $this->settings->tracking_enabled() && $this->settings->require_consent();
	}

	/**
	 * Return the consent columns to store alongside a cart.
	 *
	 * The basis is recorded with the data it justifies, so a row always
	 * carries the reason it exists rather than depending on a setting that may
	 * since have changed.
	 *
	 * @param bool $consent_given Whether the customer actively agreed.
	 * @return array{consent: int, consent_source: string, consent_at: string|null}
	 */
	public function record( bool $consent_given ): array {
		$explicit = $this->settings->require_consent();

		return array(
			'consent'        => $consent_given || ! $explicit ? 1 : 0,
			'consent_source' => $explicit ? self::SOURCE_EXPLICIT : self::SOURCE_SETTING,
			'consent_at'     => $consent_given || ! $explicit ? gmdate( 'Y-m-d H:i:s' ) : null,
		);
	}
}
