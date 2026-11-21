<?php
/**
 * Removal of a checkout the customer stopped agreeing to save.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\MessageStore;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartColumns;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartRepository;

/**
 * Deletes the current session's unfinished checkout after consent is withdrawn.
 *
 * The consent control promises to save details only while it is ticked, so
 * unticking it removes what was saved for this checkout rather than leaving
 * it until the retention sweep. Queued messages go first, as in the eraser,
 * so nothing is sent afterwards. A checkout that already ended in an order is
 * left alone; it is no longer this checkout's data.
 */
final class ConsentWithdrawal {

	/**
	 * Create the withdrawal handler.
	 *
	 * @param CartRepository $carts    Cart storage.
	 * @param MessageStore   $messages Message storage.
	 */
	public function __construct(
		private CartRepository $carts,
		private MessageStore $messages
	) {
	}

	/**
	 * Delete the session's unfinished checkout, if there is one.
	 *
	 * @param string $session_key Checkout session identifier.
	 * @return bool Whether a checkout was deleted.
	 */
	public function withdraw( string $session_key ): bool {
		$cart = $this->carts->find_by_session( $session_key );

		if ( null === $cart || ! CartColumns::is_unfinished( $cart ) ) {
			return false;
		}

		$cart_id = (int) ( $cart['id'] ?? 0 );

		$this->messages->delete_by_cart_ids( array( $cart_id ) );

		return $this->carts->delete( $cart_id );
	}
}
