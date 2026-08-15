<?php
/**
 * Contract for the cart storage the privacy layer needs.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts;

/**
 * The narrow slice of cart storage used to answer a data-subject request.
 *
 * Export and erasure need to look carts up by email and remove them. They have
 * no business inserting, updating, or sweeping, so they depend on this rather
 * than on the whole repository.
 */
interface CartStore {

	/**
	 * Return carts recorded against one email address.
	 *
	 * @param string $email  Customer email.
	 * @param int    $limit  Maximum rows.
	 * @param int    $offset Rows to skip.
	 * @return list<array<string, mixed>>
	 */
	public function find_by_email( string $email, int $limit = 50, int $offset = 0 ): array;

	/**
	 * Delete every cart recorded against one email address.
	 *
	 * @param string $email Customer email.
	 * @return int Rows removed.
	 */
	public function delete_by_email( string $email ): int;
}
