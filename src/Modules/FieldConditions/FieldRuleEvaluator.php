<?php
/**
 * Evaluate allowlisted field dependency rules.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions;

/**
 * Preserves the two historical string-comparison modes.
 */
final class FieldRuleEvaluator {

	private const BASIC_OPERATORS = array(
		'is',
		'is_not',
		'contains',
		'not_contains',
		'starts_with',
		'ends_with',
		'is_empty',
		'is_not_empty',
		'greater_than',
		'less_than',
	);

	private const DEPENDENCY_OPERATORS = array(
		'is',
		'is_not',
		'contains',
		'not_contains',
		'starts_with',
		'ends_with',
		'is_empty',
		'is_not_empty',
		'greater_than',
		'less_than',
		'checked',
		'unchecked',
		'in',
		'not_in',
	);

	/**
	 * Evaluate the case-insensitive basic condition behavior.
	 *
	 * @param array{operator: string, value: string} $rule Stored rule.
	 * @param string                                 $field_value Processed source value.
	 */
	public function basic_matches( array $rule, string $field_value ): ?bool {
		if ( ! in_array( $rule['operator'], self::BASIC_OPERATORS, true ) ) {
			return null;
		}

		return $this->matches(
			$rule['operator'],
			strtolower( $field_value ),
			strtolower( $rule['value'] )
		);
	}

	/**
	 * Evaluate the case-sensitive advanced dependency behavior.
	 *
	 * @param array{operator: string, value: string} $rule Stored rule.
	 * @param string                                 $field_value Processed source value.
	 */
	public function dependency_matches( array $rule, string $field_value ): ?bool {
		if ( ! in_array( $rule['operator'], self::DEPENDENCY_OPERATORS, true ) ) {
			return null;
		}

		return $this->matches( $rule['operator'], $field_value, $rule['value'] );
	}

	/**
	 * Apply one validated operator.
	 *
	 * @param string $operator Validated operator.
	 * @param string $field_value Processed source value.
	 * @param string $expected Stored expected value.
	 */
	private function matches( string $operator, string $field_value, string $expected ): bool {
		if ( in_array( $operator, array( 'is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with' ), true ) ) {
			return $this->string_matches( $operator, $field_value, $expected );
		}

		if ( in_array( $operator, array( 'greater_than', 'less_than' ), true ) ) {
			return $this->number_matches( $operator, $field_value, $expected );
		}

		return $this->state_matches( $operator, $field_value, $expected );
	}

	/**
	 * Apply a string operator.
	 *
	 * @param string $operator Validated string operator.
	 * @param string $field_value Processed source value.
	 * @param string $expected Stored expected value.
	 */
	private function string_matches( string $operator, string $field_value, string $expected ): bool {
		return match ( $operator ) {
			'is' => $expected === $field_value,
			'is_not' => $expected !== $field_value,
			'contains' => str_contains( $field_value, $expected ),
			'not_contains' => ! str_contains( $field_value, $expected ),
			'starts_with' => str_starts_with( $field_value, $expected ),
			default => '' === $expected ? '' === $field_value : str_ends_with( $field_value, $expected ),
		};
	}

	/**
	 * Apply a numeric operator without treating malformed numbers as zero.
	 *
	 * @param string $operator Validated numeric operator.
	 * @param string $field_value Processed source value.
	 * @param string $expected Stored expected value.
	 */
	private function number_matches( string $operator, string $field_value, string $expected ): bool {
		if ( ! is_numeric( $field_value ) || ! is_numeric( $expected ) ) {
			return false;
		}

		return 'greater_than' === $operator
			? (float) $field_value > (float) $expected
			: (float) $field_value < (float) $expected;
	}

	/**
	 * Apply empty, checked, and comma-list operators.
	 *
	 * @param string $operator Validated state operator.
	 * @param string $field_value Processed source value.
	 * @param string $expected Stored expected value.
	 */
	private function state_matches( string $operator, string $field_value, string $expected ): bool {
		return match ( $operator ) {
			'is_empty' => '' === trim( $field_value ),
			'is_not_empty' => '' !== trim( $field_value ),
			'checked' => '' !== $field_value && '0' !== $field_value,
			'unchecked' => '' === $field_value || '0' === $field_value,
			'in' => in_array( $field_value, $this->listed_values( $expected ), true ),
			default => ! in_array( $field_value, $this->listed_values( $expected ), true ),
		};
	}

	/**
	 * Split the historical comma-separated operator value.
	 *
	 * @param string $expected Stored comma-separated values.
	 * @return list<string>
	 */
	private function listed_values( string $expected ): array {
		return array_map( 'trim', explode( ',', $expected ) );
	}
}
