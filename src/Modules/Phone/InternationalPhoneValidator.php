<?php
/**
 * Server-side international phone format validation.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone;

/**
 * Enforces the E.164 container format produced by the checkout widget.
 */
final class InternationalPhoneValidator {

	/**
	 * Check the leading plus, non-zero country code, digits, and 15-digit ceiling.
	 *
	 * @param string $phone_number Submitted phone number.
	 */
	public function is_valid( string $phone_number ): bool {
		return 1 === preg_match( '/^\+[1-9][0-9]{1,14}$/D', $phone_number );
	}
}
