<?php
/**
 * WordPress personal-data export for stored checkout activity.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\CartStore;

/**
 * Answers a data-export request for one email address.
 *
 * A customer whose abandoned checkout was stored has the right to see it, and
 * WordPress already provides the request, verification, and packaging flow.
 * Hooking into that is both correct and far less error-prone than a bespoke
 * export screen.
 */
final class PersonalDataExporter {

	/**
	 * Rows returned per page, matching the core exporter's paging contract.
	 */
	private const PER_PAGE = 50;

	/**
	 * Create the exporter.
	 *
	 * @param CartStore $carts Cart storage.
	 */
	public function __construct( private CartStore $carts ) {
	}

	/**
	 * Register this exporter with WordPress.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register( array $exporters ): array {
		$exporters['checkout-lift-for-woocommerce'] = array(
			'exporter_friendly_name' => __( 'Checkout activity', 'checkout-lift-for-woocommerce' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Export stored checkout activity for one address.
	 *
	 * @param string $email_address Address being exported.
	 * @param int    $page          One-based page number.
	 * @return array{data: list<array<string, mixed>>, done: bool}
	 */
	public function export( string $email_address, int $page = 1 ): array {
		$page   = max( 1, $page );
		$offset = ( $page - 1 ) * self::PER_PAGE;
		$rows   = $this->carts->find_by_email( $email_address, self::PER_PAGE, $offset );
		$export = array();

		foreach ( $rows as $row ) {
			$export[] = array(
				'group_id'    => 'scfe-checkout-activity',
				'group_label' => __( 'Checkout activity', 'checkout-lift-for-woocommerce' ),
				'item_id'     => 'scfe-cart-' . (int) ( $row['id'] ?? 0 ),
				'data'        => $this->fields( $row ),
			);
		}

		return array(
			'data' => $export,
			'done' => count( $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * Describe one stored checkout in export form.
	 *
	 * The resume token hash is deliberately omitted. It is not information
	 * about the person, and disclosing it would weaken the token.
	 *
	 * @param array<string, mixed> $row Stored cart.
	 * @return list<array{name: string, value: string}>
	 */
	private function fields( array $row ): array {
		$labels = array(
			'created_at'      => __( 'Started at', 'checkout-lift-for-woocommerce' ),
			'updated_at'      => __( 'Last activity', 'checkout-lift-for-woocommerce' ),
			'status'          => __( 'Status', 'checkout-lift-for-woocommerce' ),
			'first_name'      => __( 'First name', 'checkout-lift-for-woocommerce' ),
			'last_name'       => __( 'Last name', 'checkout-lift-for-woocommerce' ),
			'email'           => __( 'Email address', 'checkout-lift-for-woocommerce' ),
			'phone'           => __( 'Phone', 'checkout-lift-for-woocommerce' ),
			'country'         => __( 'Country', 'checkout-lift-for-woocommerce' ),
			'total'           => __( 'Cart total', 'checkout-lift-for-woocommerce' ),
			'currency'        => __( 'Currency', 'checkout-lift-for-woocommerce' ),
			'item_count'      => __( 'Items in cart', 'checkout-lift-for-woocommerce' ),
			'checkout_step'   => __( 'Furthest checkout step', 'checkout-lift-for-woocommerce' ),
			'payment_method'  => __( 'Payment method', 'checkout-lift-for-woocommerce' ),
			'shipping_method' => __( 'Shipping method', 'checkout-lift-for-woocommerce' ),
			'consent'         => __( 'Consented to follow-up', 'checkout-lift-for-woocommerce' ),
			'consent_at'      => __( 'Consent recorded at', 'checkout-lift-for-woocommerce' ),
			'expires_at'      => __( 'Scheduled deletion', 'checkout-lift-for-woocommerce' ),
		);

		$fields = array();

		foreach ( $labels as $column => $label ) {
			$value = $row[ $column ] ?? '';

			if ( '' === $value ) {
				continue;
			}

			$fields[] = array(
				'name'  => $label,
				'value' => 'consent' === $column
					? ( (int) $value ? __( 'Yes', 'checkout-lift-for-woocommerce' ) : __( 'No', 'checkout-lift-for-woocommerce' ) )
					: (string) $value,
			);
		}

		return $fields;
	}
}
