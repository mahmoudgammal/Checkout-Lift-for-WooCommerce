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
	public function for_field( array $settings, int $decimals = 2 ): array {
		$groups = array();

		// Both groups compare a customer-entered value despite separate editor sections.
		foreach ( array( 'field_dependencies', 'conditions' ) as $key ) {
			$rule = $this->field_group( $settings[ $key ] ?? null );

			if ( array() !== $rule ) {
				$groups[] = $rule;
			}
		}

		$hidden = $this->combine( $groups );

		return apply_filters( 'scfe_checkout_block_hidden_rules', $hidden, $settings, $decimals );
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
