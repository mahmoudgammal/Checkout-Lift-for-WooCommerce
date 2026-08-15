<?php
/**
 * Classic checkout validation adapter for field conditions.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\SerializedCheckoutDataParser;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository;
use WP_Error;

/**
 * Removes validation and persistence authority from browser-hidden field claims.
 */
final class ClassicValidationAdapter {

	/**
	 * Server-resolved hidden fields for the current checkout.
	 *
	 * @var list<string>
	 */
	private array $hidden_fields = array();

	/**
	 * Create the classic validation adapter.
	 *
	 * @param CheckoutFieldsRepository     $checkout_fields Checkout field settings.
	 * @param FieldVisibilityResolver      $visibility_resolver Dependency graph evaluator.
	 * @param SerializedCheckoutDataParser $checkout_parser Serialized form parser.
	 */
	public function __construct(
		private CheckoutFieldsRepository $checkout_fields,
		private FieldVisibilityResolver $visibility_resolver,
		private SerializedCheckoutDataParser $checkout_parser
	) {
	}

	/**
	 * Register classic checkout validation and persistence hooks.
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'resolve_order_review' ), 20 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'cache_visibility' ), 20 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'resolve_validation' ), 10, 2 );
		add_filter( 'scfe_hidden_checkout_fields', array( $this, 'add_hidden_fields' ) );
	}

	/**
	 * Resolve hidden fields and remove only their validation errors.
	 *
	 * @param array<string, mixed> $posted_fields Processed checkout values.
	 * @param WP_Error             $validation_errors Checkout validation errors.
	 */
	public function resolve_validation( array $posted_fields, WP_Error $validation_errors ): void {
		$this->resolve( $posted_fields );

		foreach ( $validation_errors->get_error_codes() as $error_code ) {
			if ( $this->error_belongs_to_hidden_field( $error_code ) ) {
				$validation_errors->remove( $error_code );
			}
		}
	}

	/**
	 * Resolve visibility before order-review totals are calculated.
	 *
	 * @param string $serialized_fields Serialized checkout form.
	 */
	public function resolve_order_review( string $serialized_fields ): void {
		$this->resolve( $this->checkout_parser->parse( $serialized_fields ) );
	}

	/**
	 * Resolve visibility before final checkout totals are calculated.
	 *
	 * @param array<string, mixed> $posted_fields Processed checkout fields.
	 * @return array<string, mixed>
	 */
	public function cache_visibility( array $posted_fields ): array {
		$this->resolve( $posted_fields );

		return $posted_fields;
	}

	/**
	 * Share trusted hidden fields with order persistence.
	 *
	 * @param array $hidden_fields Hidden fields from other server modules.
	 * @return list<string>
	 */
	public function add_hidden_fields( array $hidden_fields ): array {
		return array_values( array_unique( array_merge( $hidden_fields, $this->hidden_fields ) ) );
	}

	/**
	 * Check whether an error code identifies a hidden field.
	 *
	 * @param string $error_code WooCommerce validation error code.
	 */
	private function error_belongs_to_hidden_field( string $error_code ): bool {
		foreach ( $this->hidden_fields as $field_key ) {
			if ( false !== strpos( $error_code, $field_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve hidden fields from trusted settings and submitted values.
	 *
	 * @param array<string, mixed> $posted_fields Checkout fields.
	 */
	private function resolve( array $posted_fields ): void {
		$this->hidden_fields = $this->visibility_resolver->hidden_fields(
			$this->checkout_fields->fields(),
			$posted_fields
		);
	}
}
