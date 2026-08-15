<?php
/**
 * Custom order-field display rows.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields;

use DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\BlocksCustomFields;
use WC_Order;

/**
 * Reads displayable base custom-field values through WooCommerce CRUD.
 *
 * An order can carry the same answer under either of two keys, depending on
 * which checkout took it. The classic checkout writes `_order_gift_note`, the
 * key this plugin knows the field by. The block checkout goes through
 * WooCommerce's additional checkout fields, which build their own key from the
 * field's group and id: `_wc_other/scfe/gift-note`.
 *
 * Both are read here. Without the second, a merchant who configured a field
 * and then took an order on the block checkout would find their own panel
 * empty and have to know to go looking elsewhere for the answer.
 */
final class OrderFieldRows {

	/**
	 * Prefix WooCommerce stores an order-location field under.
	 *
	 * Taken from `CheckoutFields::get_group_key()`, which returns the leading
	 * underscore and the trailing slash as part of the prefix. `_wc_additional`
	 * is the same idea under its old name and is deprecated.
	 */
	private const OTHER_PREFIX = '_wc_other/';

	/**
	 * Prefixes for a field WooCommerce keeps with an address.
	 *
	 * An address field is offered under both addresses, and there is no telling
	 * from here which one the customer answered on.
	 */
	private const ADDRESS_PREFIXES = array( '_wc_billing/', '_wc_shipping/' );

	/**
	 * Create the order row reader.
	 *
	 * @param CustomFieldDefinitions $definitions Base custom field definitions.
	 * @param BlocksCustomFields     $block_fields Block checkout field ids.
	 */
	public function __construct(
		private CustomFieldDefinitions $definitions,
		private BlocksCustomFields $block_fields
	) {
	}

	/**
	 * Return populated rows for an order.
	 *
	 * @param WC_Order $order Current order.
	 * @return list<array{label: string, value: string}>
	 */
	public function for_order( WC_Order $order ): array {
		$rows = array();

		foreach ( $this->definitions->all() as $field_key => $definition ) {
			$value = $this->value( $order, (string) $field_key );

			if ( '' !== $value ) {
				$rows[] = array(
					'label' => $definition['label'],
					'value' => $value,
				);
			}
		}

		return $rows;
	}

	/**
	 * Read one field's answer from whichever key holds it.
	 *
	 * @param WC_Order $order     Current order.
	 * @param string   $field_key Stored field key.
	 */
	private function value( WC_Order $order, string $field_key ): string {
		foreach ( $this->meta_keys( $field_key ) as $meta_key ) {
			$value = $order->get_meta( $meta_key, true );

			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return (string) $value;
			}
		}

		return '';
	}

	/**
	 * Return the keys an answer to this field could be stored under.
	 *
	 * @param string $field_key Stored field key.
	 * @return list<string>
	 */
	private function meta_keys( string $field_key ): array {
		// The classic key first: it is the one this plugin writes, and on a
		// classic store it is the only one that will ever hold anything.
		$keys = array( '_' . $field_key );

		$section  = $this->section( $field_key );
		$block_id = $this->block_fields->field_id( $section, $field_key );

		if ( '' === $block_id ) {
			return $keys;
		}

		$prefixes = 'order' === $section ? array( self::OTHER_PREFIX ) : self::ADDRESS_PREFIXES;

		foreach ( $prefixes as $prefix ) {
			// The prefix already carries its own leading underscore.
			$keys[] = $prefix . $block_id;
		}

		return $keys;
	}

	/**
	 * Work out which section a stored key belongs to.
	 *
	 * @param string $field_key Stored field key.
	 */
	private function section( string $field_key ): string {
		foreach ( array( 'billing', 'shipping' ) as $section ) {
			if ( str_starts_with( $field_key, $section . '_' ) ) {
				return $section;
			}
		}

		return 'order';
	}
}
