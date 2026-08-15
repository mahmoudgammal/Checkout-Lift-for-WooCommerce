<?php
/**
 * Resolve server-authoritative field visibility.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions;

/**
 * Evaluates dependency graphs and fails visible on malformed or cyclic paths.
 */
final class FieldVisibilityResolver {

	/**
	 * Parsed condition sets keyed by target field.
	 *
	 * @var array<string, list<array{kind: string, action: string, logic: string, rules: list<array{field: string, operator: string, value: string}>}>>
	 */
	private array $conditions_by_field = array();

	/**
	 * Normalized checkout values.
	 *
	 * @var array<string, string>
	 */
	private array $field_values = array();

	/**
	 * Resolved visibility keyed by field.
	 *
	 * @var array<string, bool>
	 */
	private array $resolved_visibility = array();

	/**
	 * Fields in the current dependency path.
	 *
	 * @var array<string, true>
	 */
	private array $resolving_fields = array();

	/**
	 * Create the graph evaluator.
	 *
	 * @param FieldConditionConfigurations $configurations Parsed condition collector.
	 * @param FieldRuleEvaluator           $rule_evaluator Rule evaluator.
	 */
	public function __construct(
		private FieldConditionConfigurations $configurations,
		private FieldRuleEvaluator $rule_evaluator
	) {
	}

	/**
	 * Return fields hidden by trusted checkout values.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $stored_fields Checkout field settings.
	 * @param array<string, mixed>                               $posted_fields Processed checkout values.
	 * @return list<string>
	 */
	public function hidden_fields( array $stored_fields, array $posted_fields ): array {
		$this->conditions_by_field = $this->configurations->from_fields( $stored_fields );
		$this->field_values        = $this->normalized_values( $posted_fields );
		$this->resolved_visibility = array();
		$this->resolving_fields    = array();
		$hidden_fields             = array();

		foreach ( array_keys( $this->conditions_by_field ) as $field_key ) {
			if ( false === $this->field_is_visible( $field_key ) ) {
				$hidden_fields[] = $field_key;
			}
		}

		return $hidden_fields;
	}

	/**
	 * Resolve one field, returning null for a circular dependency path.
	 *
	 * @param string $field_key Target field key.
	 */
	private function field_is_visible( string $field_key ): ?bool {
		if ( isset( $this->resolved_visibility[ $field_key ] ) ) {
			return $this->resolved_visibility[ $field_key ];
		}

		if ( isset( $this->resolving_fields[ $field_key ] ) ) {
			return null;
		}

		$this->resolving_fields[ $field_key ] = true;
		$visibility                           = $this->condition_sets_visibility( $field_key );
		unset( $this->resolving_fields[ $field_key ] );

		if ( null !== $visibility ) {
			$this->resolved_visibility[ $field_key ] = $visibility;
		}

		return $visibility;
	}

	/**
	 * Resolve every configured condition set for one target field.
	 *
	 * @param string $field_key Target field key.
	 */
	private function condition_sets_visibility( string $field_key ): ?bool {
		foreach ( $this->conditions_by_field[ $field_key ] ?? array() as $condition_set ) {
			$condition_visibility = $this->condition_set_visibility( $field_key, $condition_set );

			if ( null === $condition_visibility ) {
				return null;
			}

			if ( ! $condition_visibility ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolve one parsed condition set.
	 *
	 * @param string $target_field Target field key.
	 * @param array  $condition_set Parsed conditions.
	 */
	private function condition_set_visibility( string $target_field, array $condition_set ): ?bool {
		$rule_matches = array();

		foreach ( $condition_set['rules'] as $rule ) {
			$rule_match = $this->rule_matches( $target_field, $condition_set['kind'], $rule );

			if ( null === $rule_match ) {
				return null;
			}

			$rule_matches[] = $rule_match;
		}

		$conditions_match = 'all' === $condition_set['logic']
			? ! in_array( false, $rule_matches, true )
			: in_array( true, $rule_matches, true );

		return 'show' === $condition_set['action'] ? $conditions_match : ! $conditions_match;
	}

	/**
	 * Resolve one rule and its source field.
	 *
	 * @param string $target_field Target field key.
	 * @param string $kind Condition family.
	 * @param array  $rule Parsed rule.
	 */
	private function rule_matches( string $target_field, string $kind, array $rule ): ?bool {
		if ( $target_field === $rule['field'] ) {
			return null;
		}

		$source_visibility = $this->field_is_visible( $rule['field'] );

		if ( null === $source_visibility ) {
			return null;
		}

		$field_value = $source_visibility ? ( $this->field_values[ $rule['field'] ] ?? '' ) : '';

		return 'basic' === $kind
			? $this->rule_evaluator->basic_matches( $rule, $field_value )
			: $this->rule_evaluator->dependency_matches( $rule, $field_value );
	}

	/**
	 * Normalize processed checkout values to scalar strings.
	 *
	 * @param array<string, mixed> $posted_fields Processed checkout values.
	 * @return array<string, string>
	 */
	private function normalized_values( array $posted_fields ): array {
		$field_values = array();

		foreach ( $posted_fields as $field_key => $field_value ) {
			if ( is_string( $field_key ) && is_scalar( $field_value ) ) {
				$field_values[ $field_key ] = (string) $field_value;
			}
		}

		return $field_values;
	}
}
