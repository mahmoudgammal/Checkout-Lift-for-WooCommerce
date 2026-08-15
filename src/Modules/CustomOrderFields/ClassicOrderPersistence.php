<?php
/**
 * Classic checkout custom-field persistence.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields;

use WC_Order;

/**
 * Persists base custom fields through the current WooCommerce order object.
 */
final class ClassicOrderPersistence {

	/**
	 * Create the classic persistence adapter.
	 *
	 * @param CustomFieldDefinitions $definitions Base custom field definitions.
	 * @param SubmittedFieldValues   $submitted_fields Submitted value mapper.
	 */
	public function __construct(
		private CustomFieldDefinitions $definitions,
		private SubmittedFieldValues $submitted_fields
	) {
	}

	/**
	 * Register the classic checkout order hook.
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_checkout_create_order', array( $this, 'persist' ), 10, 2 );
	}

	/**
	 * Add accepted custom fields before WooCommerce saves the order.
	 *
	 * @param WC_Order             $order Current order object.
	 * @param array<string, mixed> $checkout_data Processed checkout data.
	 */
	public function persist( WC_Order $order, array $checkout_data ): void {
		$hidden_fields = apply_filters( 'scfe_hidden_checkout_fields', array() );
		$hidden_fields = is_array( $hidden_fields ) ? $hidden_fields : array();
		$meta_values   = $this->submitted_fields->collect(
			$this->definitions->all(),
			$checkout_data,
			$hidden_fields
		);

		foreach ( $meta_values as $meta_key => $value ) {
			$order->update_meta_data( $meta_key, $value );
		}
	}
}
