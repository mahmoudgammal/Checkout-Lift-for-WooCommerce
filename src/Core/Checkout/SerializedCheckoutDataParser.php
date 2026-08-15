<?php
/**
 * Serialized classic checkout data parser.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout;

/**
 * Parses WooCommerce's serialized order-review form payload.
 */
final class SerializedCheckoutDataParser {

	/**
	 * Parse serialized checkout fields.
	 *
	 * @param string $serialized_fields WooCommerce checkout form payload.
	 * @return array<string, mixed>
	 */
	public function parse( string $serialized_fields ): array {
		$checkout_fields = array();
		wp_parse_str( $serialized_fields, $checkout_fields );

		return $checkout_fields;
	}
}
