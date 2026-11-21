<?php
/**
 * Parse stored field-to-field conditions.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions;

/**
 * Validates the shared top-level condition shape.
 */
final class FieldConditionParser {

	/**
	 * Operator of a rule saved without one.
	 *
	 * The field editor displays "is" for a rule whose operator was never
	 * changed and, before 1.0.1, saved such rules with no operator at all.
	 * Every reader must therefore treat a missing operator as "is", or the
	 * server and the browser disagree about whether the field is shown.
	 */
	public const DEFAULT_OPERATOR = 'is';

	/**
	 * Parse an array or historical JSON string.
	 *
	 * @param mixed $stored_conditions Stored condition configuration.
	 * @return array{action: string, logic: string, rules: list<array{field: string, operator: string, value: string}>}|null
	 */
	public function parse( $stored_conditions ): ?array {
		if ( is_string( $stored_conditions ) ) {
			$stored_conditions = json_decode( $stored_conditions, true );
		}

		if ( ! is_array( $stored_conditions ) || empty( $stored_conditions['rules'] ) || ! is_array( $stored_conditions['rules'] ) ) {
			return null;
		}

		$action = $stored_conditions['action'] ?? 'show';
		$logic  = $stored_conditions['logic'] ?? 'all';
		$rules  = $this->rules( $stored_conditions['rules'] );

		if ( null === $rules || ! in_array( $action, array( 'show', 'hide' ), true ) || ! in_array( $logic, array( 'all', 'any' ), true ) ) {
			return null;
		}

		return array(
			'action' => $action,
			'logic'  => $logic,
			'rules'  => $rules,
		);
	}

	/**
	 * Validate every stored rule before any rule is trusted.
	 *
	 * @param array $stored_rules Stored rule list.
	 * @return list<array{field: string, operator: string, value: string}>|null
	 */
	private function rules( array $stored_rules ): ?array {
		$rules = array();

		foreach ( $stored_rules as $stored_rule ) {
			$parsed_rule = $this->parsed_rule( $stored_rule );

			if ( null === $parsed_rule ) {
				return null;
			}

			$rules[] = $parsed_rule;
		}

		return $rules;
	}

	/**
	 * Parse one rule without accepting unsafe field identifiers.
	 *
	 * @param mixed $stored_rule Stored rule.
	 * @return array{field: string, operator: string, value: string}|null
	 */
	private function parsed_rule( $stored_rule ): ?array {
		if ( ! is_array( $stored_rule ) ) {
			return null;
		}

		$field_key = $stored_rule['field'] ?? '';
		$operator  = $stored_rule['operator'] ?? '';
		$expected  = $stored_rule['value'] ?? '';

		if ( '' === $operator ) {
			$operator = self::DEFAULT_OPERATOR;
		}

		if ( ! is_string( $field_key ) || 1 !== preg_match( '/^[a-z0-9_]+$/D', $field_key ) ) {
			return null;
		}

		if ( ! is_string( $operator ) || ! is_scalar( $expected ) ) {
			return null;
		}

		return array(
			'field'    => $field_key,
			'operator' => $operator,
			'value'    => (string) $expected,
		);
	}
}
