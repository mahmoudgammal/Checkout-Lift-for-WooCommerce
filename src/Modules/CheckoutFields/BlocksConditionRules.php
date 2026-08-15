<?php
/**
 * Stored conditions expressed as block checkout rules.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields;

/**
 * Turns a field's stored conditions into the rules WooCommerce evaluates.
 *
 * WooCommerce accepts `hidden` as JSON Schema and checks it against a document
 * object describing the cart, the checkout and the customer, on the server and
 * in the browser both. So a condition needs no evaluator of ours on the block
 * checkout; it needs a translation.
 *
 * Everything here answers the question "when is this field hidden", which is
 * the inverse of how the editor asks it. A group set to `show` is hidden when
 * its rules do *not* match; a group set to `hide` is hidden when they do.
 *
 * **A group is carried whole or not at all.** A group can mix rules that have
 * a path in the document object with rules that have none, and dropping the
 * ones that have none would change what the group means. Silently widening a
 * merchant's condition is worse than not carrying it.
 */
final class BlocksConditionRules {

	/**
	 * Address fields the customer groups expose.
	 */
	private const ADDRESS_FIELDS = array(
		'first_name',
		'last_name',
		'company',
		'country',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
		'phone',
	);

	/**
	 * Comparisons a field rule may use.
	 *
	 * Only equality, deliberately. The rest of the editor's operators are
	 * about substrings, emptiness and numeric order on values the document
	 * object holds as free text, and each needs its own decision about what it
	 * should mean. Equality covers the common case, which is a field that
	 * appears for one country.
	 */
	private const FIELD_OPERATORS = array( 'is', 'is_not' );

	/**
	 * Cart rule types that have a path, mapped to how they are read.
	 */
	private const CART_TYPES = array( 'cart_total', 'product_in_cart' );

	/**
	 * Create the rule translator.
	 *
	 * @param BlocksCustomFields $custom_fields Block checkout field ids.
	 */
	public function __construct( private BlocksCustomFields $custom_fields ) {
	}

	/**
	 * Return the `hidden` rules for one field, or nothing.
	 *
	 * @param array<string, mixed> $settings Stored field settings.
	 * @param int                  $decimals Currency decimal places.
	 * @return array<string, mixed>
	 */
	public function for_field( array $settings, int $decimals ): array {
		$groups = array();

		foreach ( array( 'shipping_conditions', 'payment_conditions' ) as $key ) {
			$rule = $this->method_group( $settings[ $key ] ?? null, 'shipping_conditions' === $key );

			if ( array() !== $rule ) {
				$groups[] = $rule;
			}
		}

		// Both groups compare a customer-entered value despite separate editor sections.
		foreach ( array( 'field_dependencies', 'conditions' ) as $key ) {
			$rule = $this->field_group( $settings[ $key ] ?? null );

			if ( array() !== $rule ) {
				$groups[] = $rule;
			}
		}

		$cart = $this->cart_group( $settings['cart_conditions'] ?? null, $decimals );

		if ( array() !== $cart ) {
			$groups[] = $cart;
		}

		return $this->combine( $groups );
	}

	/**
	 * Join every group so that any one of them can hide the field.
	 *
	 * Each group is already a schema against the document object's root, so
	 * they combine without further wrapping.
	 *
	 * @param array<int, array<string, mixed>> $groups Rule groups.
	 * @return array<string, mixed>
	 */
	private function combine( array $groups ): array {
		if ( array() === $groups ) {
			return array();
		}

		return 1 === count( $groups ) ? $groups[0] : array( 'anyOf' => $groups );
	}

	/**
	 * Express a chosen-method condition.
	 *
	 * @param mixed $condition   Stored condition.
	 * @param bool  $is_shipping Whether this is the shipping condition.
	 * @return array<string, mixed>
	 */
	private function method_group( $condition, bool $is_shipping ): array {
		if ( ! is_array( $condition ) || ! is_array( $condition['methods'] ?? null ) ) {
			return array();
		}

		$methods = array_values( array_filter( array_map( 'strval', $condition['methods'] ) ) );

		if ( array() === $methods ) {
			return array();
		}

		// Shipping rates are a list; the payment method is a scalar.
		$matches = $is_shipping
			? array( 'contains' => array( 'enum' => $methods ) )
			: array( 'enum' => $methods );

		$placed = $this->at(
			$is_shipping ? 'cart' : 'checkout',
			$is_shipping ? 'shipping_rates' : 'payment_method',
			$matches
		);

		return $this->grouped( array( $placed ), $condition );
	}

	/**
	 * Express a condition on what the customer has entered.
	 *
	 * @param mixed $group Stored group.
	 * @return array<string, mixed>
	 */
	private function field_group( $group ): array {
		$rules = $this->rules_of( $group );

		if ( array() === $rules ) {
			return array();
		}

		$schemas = array();

		foreach ( $rules as $rule ) {
			$schema = $this->field_rule( is_array( $rule ) ? $rule : array() );

			if ( array() === $schema ) {
				// A rule without a document path makes its whole group unsupported.
				return array();
			}

			$schemas[] = $schema;
		}

		return $this->grouped( $schemas, $group );
	}

	/**
	 * Express one rule about an entered value.
	 *
	 * @param array<string, mixed> $rule Stored rule.
	 * @return array<string, mixed>
	 */
	private function field_rule( array $rule ): array {
		$operator = (string) ( $rule['operator'] ?? '' );
		$subject  = (string) ( $rule['field'] ?? '' );

		if ( ! in_array( $operator, self::FIELD_OPERATORS, true ) || '' === $subject ) {
			return array();
		}

		$path = $this->field_path( $subject );

		if ( array() === $path ) {
			return array();
		}

		$value   = (string) ( $rule['value'] ?? '' );
		$matches = array( 'const' => $value );

		if ( 'is_not' === $operator ) {
			$matches = array( 'not' => $matches );
		}

		return $this->at( $path[0], $path[1], $matches, $path[2] ?? null );
	}

	/**
	 * Locate a stored field key in the document object.
	 *
	 * @param string $stored_key Stored field key.
	 * @return array{0: string, 1: string, 2?: string} Group, property, and an optional nesting.
	 */
	private function field_path( string $stored_key ): array {
		foreach ( array( 'billing', 'shipping' ) as $section ) {
			$prefix = $section . '_';

			if ( ! str_starts_with( $stored_key, $prefix ) ) {
				continue;
			}

			$name = substr( $stored_key, strlen( $prefix ) );

			// Only core address field paths are established in the checkout document.
			return in_array( $name, self::ADDRESS_FIELDS, true )
				? array( 'customer', $section . '_address', $name )
				: array();
		}

		// Order-section custom fields use WooCommerce's registered checkout id.
		$id = $this->custom_fields->field_id( 'order', $stored_key );

		return '' === $id ? array() : array( 'checkout', 'additional_fields', $id );
	}

	/**
	 * Express a condition on the cart.
	 *
	 * @param mixed $group    Stored group.
	 * @param int   $decimals Currency decimal places.
	 * @return array<string, mixed>
	 */
	private function cart_group( $group, int $decimals ): array {
		$rules = $this->rules_of( $group );

		if ( array() === $rules ) {
			return array();
		}

		$schemas = array();

		foreach ( $rules as $rule ) {
			$schema = $this->cart_rule( is_array( $rule ) ? $rule : array(), $decimals );

			if ( array() === $schema ) {
				// Category conditions have no supported document path.
				return array();
			}

			$schemas[] = $schema;
		}

		return $this->grouped( $schemas, $group );
	}

	/**
	 * Express one rule about the cart.
	 *
	 * @param array<string, mixed> $rule     Stored rule.
	 * @param int                  $decimals Currency decimal places.
	 * @return array<string, mixed>
	 */
	private function cart_rule( array $rule, int $decimals ): array {
		$type     = (string) ( $rule['type'] ?? '' );
		$operator = (string) ( $rule['operator'] ?? '' );

		if ( ! in_array( $type, self::CART_TYPES, true ) ) {
			return array();
		}

		if ( 'product_in_cart' === $type ) {
			$product = (int) ( $rule['value'] ?? 0 );

			if ( 1 > $product || ! in_array( $operator, self::FIELD_OPERATORS, true ) ) {
				return array();
			}

			// `cart.items` repeats product ids by quantity.
			$matches = array( 'contains' => array( 'const' => $product ) );

			if ( 'is_not' === $operator ) {
				$matches = array( 'not' => $matches );
			}

			return $this->at( 'cart', 'items', $matches );
		}

		$amount = $this->minor_units( $rule['value'] ?? null, $decimals );

		if ( null === $amount ) {
			return array();
		}

		// Block totals use minor units while editor rules store decimal amounts.
		$matches = $this->amount_comparison( $operator, $amount );

		return array() === $matches
			? array()
			: $this->at( 'cart', 'totals', $matches, 'total_price' );
	}

	/**
	 * Express a comparison against a cart total.
	 *
	 * @param string $operator Stored operator.
	 * @param int    $amount   Amount in minor units.
	 * @return array<string, mixed>
	 */
	private function amount_comparison( string $operator, int $amount ): array {
		switch ( $operator ) {
			case 'is':
				return array( 'const' => $amount );
			case 'is_not':
				return array( 'not' => array( 'const' => $amount ) );
			case 'greater_than':
				return array( 'exclusiveMinimum' => $amount );
			case 'less_than':
				return array( 'exclusiveMaximum' => $amount );
		}

		return array();
	}

	/**
	 * Convert a stored decimal amount into minor units.
	 *
	 * @param mixed $value    Stored amount.
	 * @param int   $decimals Currency decimal places.
	 */
	private function minor_units( $value, int $decimals ): ?int {
		if ( ! is_scalar( $value ) || '' === (string) $value || ! is_numeric( (string) $value ) ) {
			return null;
		}

		return (int) round( ( (float) $value ) * ( 10 ** $decimals ) );
	}

	/**
	 * Combine a group's rules and turn its action into a hiding rule.
	 *
	 * Returns a schema against the document object's root, so that groups of
	 * every kind combine the same way afterwards.
	 *
	 * @param array<int, array<string, mixed>> $placed Rules, each placed at its path.
	 * @param mixed                            $group  Stored group.
	 * @return array<string, mixed>
	 */
	private function grouped( array $placed, $group ): array {
		$logic = is_array( $group ) ? (string) ( $group['logic'] ?? 'all' ) : 'all';

		$wrapped = array_map(
			static function ( array $rule ): array {
				return array( 'properties' => $rule );
			},
			$placed
		);

		$matches = 1 === count( $wrapped )
			? $wrapped[0]
			: array( ( 'all' === $logic ? 'allOf' : 'anyOf' ) => $wrapped );

		return $this->inverted( $matches, $group );
	}

	/**
	 * Turn a matching rule into a hiding rule, following the stored action.
	 *
	 * @param array<string, mixed> $matches Rule that is true when the condition matches.
	 * @param mixed                $group   Stored group.
	 * @return array<string, mixed>
	 */
	private function inverted( array $matches, $group ): array {
		$action = is_array( $group ) ? (string) ( $group['action'] ?? 'show' ) : 'show';

		// Visibility rules map to the inverse hidden expression.
		return 'hide' === $action ? $matches : array( 'not' => $matches );
	}

	/**
	 * Place a rule at a path in the document object.
	 *
	 * @param string               $group    Top-level group.
	 * @param string               $property Property within it.
	 * @param array<string, mixed> $rule     Rule to place.
	 * @param string|null          $nested   Optional property one level deeper.
	 * @return array<string, mixed>
	 */
	private function at( string $group, string $property, array $rule, ?string $nested = null ): array {
		if ( null !== $nested ) {
			$rule = array( 'properties' => array( $nested => $rule ) );
		}

		return array(
			$group => array(
				'properties' => array(
					$property => $rule,
				),
			),
		);
	}

	/**
	 * Return a group's rules, or nothing if it has none.
	 *
	 * @param mixed $group Stored group.
	 * @return array<int, mixed>
	 */
	private function rules_of( $group ): array {
		if ( ! is_array( $group ) || ! is_array( $group['rules'] ?? null ) ) {
			return array();
		}

		return array_values( $group['rules'] );
	}
}
