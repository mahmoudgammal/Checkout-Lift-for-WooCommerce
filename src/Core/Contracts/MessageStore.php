<?php
/**
 * Contract for the message storage the privacy layer needs.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts;

/**
 * The narrow slice of message storage used when carts are removed.
 *
 * Erasing a person's data must also drop anything queued to be sent to them,
 * which is the only reason the privacy layer touches messages at all.
 */
interface MessageStore {

	/**
	 * Delete every message belonging to the given carts.
	 *
	 * @param array<int, int> $cart_ids Cart identifiers.
	 * @return int Rows removed.
	 */
	public function delete_by_cart_ids( array $cart_ids ): int;

	/**
	 * Delete messages whose cart no longer exists.
	 *
	 * @param int $limit Maximum rows to remove in this pass.
	 * @return int Rows removed.
	 */
	public function delete_orphans( int $limit = 500 ): int;
}
