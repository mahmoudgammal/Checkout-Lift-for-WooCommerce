<?php
/**
 * Consent control rendered at checkout.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\ConsentPolicy;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings;
use Throwable;

/**
 * Asks the customer whether their details may be stored.
 *
 * Each checkout gets the control the only way that checkout accepts one.
 *
 * The classic checkout takes printed markup, rendered outside the order
 * review, because WooCommerce replaces that markup on every AJAX refresh and a
 * control living there would silently reset itself while the customer filled
 * the form.
 *
 * The Blocks checkout does not render arbitrary markup at all. Its only
 * supported extension point is an additional checkout field, which also puts
 * the answer in the Store API schema and on the resulting order.
 *
 * The box starts unticked on both. A pre-ticked consent box is not consent.
 */
final class ConsentField {

	/**
	 * Identifier the tracker reads the classic answer from.
	 */
	public const FIELD_ID = 'scfe-recovery-consent';

	/**
	 * Identifier of the additional checkout field on the Blocks checkout.
	 *
	 * WooCommerce requires a namespaced id and reads the namespace as the
	 * owning extension, so this cannot share the classic id's shape.
	 */
	public const BLOCK_FIELD_ID = 'scfe/recovery-consent';

	/**
	 * Create the field renderer.
	 *
	 * @param ConsentPolicy   $consent  Consent gate.
	 * @param PrivacySettings $settings Privacy settings.
	 * @param CheckoutBlocks  $blocks   Which checkout is configured.
	 */
	public function __construct(
		private ConsentPolicy $consent,
		private PrivacySettings $settings,
		private CheckoutBlocks $blocks
	) {
	}

	/**
	 * Render the classic control when one is needed.
	 */
	public function render(): void {
		if ( ! $this->consent->needs_control() ) {
			return;
		}

		printf(
			'<p class="form-row scfe-recovery-consent-row"><label for="%1$s"><input type="checkbox" id="%1$s" class="scfe-recovery-consent"> <span>%2$s</span></label></p>',
			esc_attr( self::FIELD_ID ),
			esc_html( $this->settings->consent_text() )
		);
	}

	/**
	 * Declare the control the Blocks checkout renders.
	 *
	 * Registration puts the field in the Store API schema for every request,
	 * so a store that is not running the block checkout is left alone rather
	 * than given an unused field in a public schema.
	 */
	public function register_block_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		if ( ! $this->consent->needs_control() || ! $this->blocks->in_use() ) {
			return;
		}

		$label = $this->settings->consent_text();

		if ( '' === $label ) {
			return;
		}

		try {
			woocommerce_register_additional_checkout_field(
				array(
					'id'       => self::BLOCK_FIELD_ID,
					'label'    => $label,
					'location' => 'contact',
					'type'     => 'checkbox',
					'required' => false,
				)
			);
		} catch ( Throwable $e ) {
			// A rejected consent field safely disables capture without taking down checkout.
			return;
		}
	}
}
