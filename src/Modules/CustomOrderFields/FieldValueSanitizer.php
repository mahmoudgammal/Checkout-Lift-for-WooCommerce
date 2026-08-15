<?php
/**
 * Base custom order-field value sanitization.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields;

/**
 * Preserves the existing single-line scalar storage contract.
 */
final class FieldValueSanitizer {

	/**
	 * Sanitize a submitted scalar or reject unsupported containers.
	 *
	 * @param mixed $value Submitted checkout value.
	 */
	public function sanitize( $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		return sanitize_text_field( (string) $value );
	}
}
