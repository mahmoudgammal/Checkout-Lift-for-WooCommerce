<?php
/**
 * Collect parsed field condition configurations.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions;

/**
 * Adapts the unchanged checkout settings shape for graph evaluation.
 */
final class FieldConditionConfigurations {

	/**
	 * Create the configuration collector.
	 *
	 * @param FieldConditionParser $condition_parser Stored condition parser.
	 */
	public function __construct( private FieldConditionParser $condition_parser ) {
	}

	/**
	 * Collect condition sets by target field.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $stored_fields Checkout field settings.
	 * @return array<string, list<array{kind: string, action: string, logic: string, rules: list<array{field: string, operator: string, value: string}>}>>
	 */
	public function from_fields( array $stored_fields ): array {
		$conditions_by_field = array();

		foreach ( $stored_fields as $section_fields ) {
			foreach ( $section_fields as $field_key => $field_settings ) {
				$condition_sets = $this->field_condition_sets( $field_settings );

				if ( ! empty( $condition_sets ) ) {
					$conditions_by_field[ $field_key ] = $condition_sets;
				}
			}
		}

		return $conditions_by_field;
	}

	/**
	 * Return valid basic and advanced sets for one field.
	 *
	 * @param array<string, mixed> $field_settings Stored field settings.
	 * @return list<array{kind: string, action: string, logic: string, rules: list<array{field: string, operator: string, value: string}>}>
	 */
	private function field_condition_sets( array $field_settings ): array {
		$condition_sets   = array();
		$basic_conditions = $this->parsed_condition_set( 'basic', $field_settings['conditions'] ?? null );
		$dependencies     = $this->parsed_condition_set( 'dependency', $field_settings['field_dependencies'] ?? null );

		if ( null !== $basic_conditions ) {
			$condition_sets[] = $basic_conditions;
		}

		if ( null !== $dependencies ) {
			$condition_sets[] = $dependencies;
		}

		return $condition_sets;
	}

	/**
	 * Return one valid condition set.
	 *
	 * @param string $kind Condition family.
	 * @param mixed  $stored_conditions Stored condition configuration.
	 * @return array{kind: string, action: string, logic: string, rules: list<array{field: string, operator: string, value: string}>}|null
	 */
	private function parsed_condition_set( string $kind, $stored_conditions ): ?array {
		$parsed_conditions = $this->condition_parser->parse( $stored_conditions );

		return null === $parsed_conditions
			? null
			: array_merge( array( 'kind' => $kind ), $parsed_conditions );
	}
}
