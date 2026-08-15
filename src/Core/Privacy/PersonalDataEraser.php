<?php
/**
 * WordPress personal-data erasure for stored checkout activity.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\CartStore;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\MessageStore;

/**
 * Answers an erasure request for one email address.
 *
 * These rows describe people who never completed a purchase, so there is no
 * accounting or tax basis for keeping them against the person's wishes. The
 * eraser therefore removes rather than anonymises, and removes the queued
 * messages with them so nothing is sent afterwards.
 */
final class PersonalDataEraser {

	/**
	 * Rows handled per page.
	 */
	private const PER_PAGE = 50;

	/**
	 * Create the eraser.
	 *
	 * @param CartStore    $carts    Cart storage.
	 * @param MessageStore $messages Message storage.
	 */
	public function __construct(
		private CartStore $carts,
		private MessageStore $messages
	) {
	}

	/**
	 * Register this eraser with WordPress.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register( array $erasers ): array {
		$erasers['checkout-lift-for-woocommerce'] = array(
			'eraser_friendly_name' => __( 'Checkout activity', 'checkout-lift-for-woocommerce' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Remove stored checkout activity for one address.
	 *
	 * @param string $email_address Address being erased.
	 * @param int    $page          One-based page number.
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public function erase( string $email_address, int $page = 1 ): array {
		unset( $page ); // Deliberately unused; see below.

		// Always read page one because deletions shift remaining rows forward.
		$rows = $this->carts->find_by_email( $email_address, self::PER_PAGE, 0 );

		if ( array() === $rows ) {
			return $this->result( false, true );
		}

		$cart_ids = array();

		foreach ( $rows as $row ) {
			$cart_ids[] = (int) ( $row['id'] ?? 0 );
		}

		// Delete messages first to avoid orphaning queued references.
		$this->messages->delete_by_cart_ids( $cart_ids );
		$removed = $this->carts->delete_by_email( $email_address );

		return $this->result( $removed > 0, count( $rows ) < self::PER_PAGE );
	}

	/**
	 * Build the response WordPress expects.
	 *
	 * @param bool $removed Whether anything was removed.
	 * @param bool $done    Whether erasure is complete.
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	private function result( bool $removed, bool $done ): array {
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $done,
		);
	}
}
