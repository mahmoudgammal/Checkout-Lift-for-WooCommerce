<?php
/**
 * Which checkout the store is running.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;

/**
 * Answers whether the checkout page is built from the checkout block.
 *
 * Needed only for decisions the server has to make before a request reaches
 * the checkout page, such as whether to add a field to the Store API schema.
 * Anything decided while rendering the checkout should read the page it is
 * rendering instead, which is always correct and never cached.
 */
final class CheckoutBlocks {

	/**
	 * Cached answer for this request.
	 *
	 * @var bool|null
	 */
	private ?bool $in_use = null;

	/**
	 * Report whether the checkout page uses the checkout block.
	 */
	public function in_use(): bool {
		if ( null !== $this->in_use ) {
			return $this->in_use;
		}

		$this->in_use = $this->detect();

		return $this->in_use;
	}

	/**
	 * Work out which checkout is configured.
	 */
	private function detect(): bool {
		// WooCommerce's own answer, which it also caches. Preferred, because a
		// store that later gains another way to use the block gains it here.
		if ( is_callable( array( CartCheckoutUtils::class, 'is_checkout_block_default' ) ) ) {
			return (bool) CartCheckoutUtils::is_checkout_block_default();
		}

		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return false;
		}

		$page = get_post( wc_get_page_id( 'checkout' ) );

		if ( null === $page ) {
			return false;
		}

		return has_block( 'woocommerce/checkout', $page );
	}
}
