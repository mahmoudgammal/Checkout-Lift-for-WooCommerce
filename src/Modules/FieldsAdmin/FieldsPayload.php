<?php
/**
 * Translation between stored fields and the editor's shape.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin;

/**
 * Converts checkout fields between how they are stored and how they are edited.
 *
 * Storage keys fields by name because that is what every consumer looks up.
 * The editor needs ordered lists, because order is the thing a merchant drags.
 * Keeping the translation here rather than in the controller makes it testable
 * without a REST request, and it is the piece every later stage builds on.
 */
final class FieldsPayload {

	/**
	 * Properties the editor owns. Everything else is carried untouched.
	 *
	 * @var list<string>
	 */
	private const MANAGED = array( 'enabled', 'required', 'label', 'placeholder', 'width', 'custom' );

	/**
	 * Condition groups the editor now owns.
	 *
	 * @var list<string>
	 */
	private const CONDITIONS = array(
		'conditions',
		'field_dependencies',
	);

	/**
	 * Type and its per-type settings, edited through the field modal.
	 *
	 * Separate from MANAGED because these only apply to some types, and a
	 * field must not accumulate settings belonging to a type it is not.
	 *
	 * @var array<string, list<string>>
	 */
	private const TYPE_SETTINGS = array(
		'select' => array( 'options' ),
	);

	/**
	 * Return condition keys supplied by Lite and installed add-ons.
	 *
	 * @return list<string>
	 */
	private function condition_keys(): array {
		$keys = apply_filters( 'scfe_field_condition_keys', self::CONDITIONS );

		return is_array( $keys ) ? array_values( array_filter( $keys, 'is_string' ) ) : self::CONDITIONS;
	}

	/**
	 * Return type settings supplied by Lite and installed add-ons.
	 *
	 * @return array<string, list<string>>
	 */
	private function type_settings(): array {
		$settings = apply_filters( 'scfe_field_type_settings', self::TYPE_SETTINGS );

		return is_array( $settings ) ? $settings : self::TYPE_SETTINGS;
	}

	/**
	 * Every per-type setting, whatever the type.
	 *
	 * @return list<string>
	 */
	private function all_type_settings(): array {
		return array_merge( array( 'type', 'default' ), ...array_values( $this->type_settings() ) );
	}

	/**
	 * Every property the editor now owns.
	 *
	 * @return list<string>
	 */
	private function all_managed(): array {
		return array_merge( self::MANAGED, $this->all_type_settings(), $this->condition_keys() );
	}

	/**
	 * Describe one stored field for the editor.
	 *
	 * @param string               $key      Field key.
	 * @param array<string, mixed> $settings Stored settings.
	 * @return array<string, mixed>
	 */
	public function to_editor_field( string $key, array $settings ): array {
		return array(
			'key'         => $key,
			'label'       => (string) ( $settings['label'] ?? '' ),
			'placeholder' => (string) ( $settings['placeholder'] ?? '' ),
			'width'       => (int) ( $settings['width'] ?? 100 ),
			// Missing `enabled` means untouched, not disabled, preserving WooCommerce defaults.
			'enabled'     => ! isset( $settings['enabled'] ) || (bool) $settings['enabled'],
			'required'    => ! empty( $settings['required'] ),
			'custom'      => ! empty( $settings['custom'] ),
			'type'        => (string) ( $settings['type'] ?? 'text' ),
			'default'     => (string) ( $settings['default'] ?? '' ),
			'options'     => (string) ( $settings['options'] ?? '' ),
			'settings'    => $this->type_settings_of( $settings ),
			'conditions'  => $this->conditions_of( $settings ),
			// Preserve settings this editor does not own, such as `class`.
			'carried'     => array_diff_key( $settings, array_flip( $this->all_managed() ) ),
		);
	}

	/**
	 * Return the condition groups a field currently has.
	 *
	 * Stored values may be JSON strings, because the old editor wrote them
	 * that way. They are decoded here so the interface always receives
	 * structured data whichever screen last saved the field.
	 *
	 * @param array<string, mixed> $settings Stored settings.
	 * @return array<string, mixed>
	 */
	private function conditions_of( array $settings ): array {
		$groups = array();

		foreach ( $this->condition_keys() as $group ) {
			$value = $settings[ $group ] ?? null;

			if ( is_string( $value ) ) {
				$value = json_decode( $value, true );
			}

			if ( is_array( $value ) && array() !== $value ) {
				$groups[ $group ] = $value;
			}
		}

		return $groups;
	}

	/**
	 * Return the per-type settings a field currently carries.
	 *
	 * @param array<string, mixed> $settings Stored settings.
	 * @return array<string, mixed>
	 */
	private function type_settings_of( array $settings ): array {
		$type  = (string) ( $settings['type'] ?? 'text' );
		$keys  = $this->type_settings()[ $type ] ?? array();
		$found = array();

		foreach ( $keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$found[ $key ] = $settings[ $key ];
			}
		}

		return $found;
	}

	/**
	 * Convert submitted sections into the stored shape.
	 *
	 * Sections and fields keep the order they arrive in, which is how a
	 * reordered list is persisted: the array order is the display order.
	 *
	 * @param array<mixed> $submitted Submitted sections.
	 * @return array<string, array<string, mixed>>
	 */
	public function to_storage( array $submitted ): array {
		$settings = array();

		foreach ( $submitted as $section ) {
			if ( ! is_array( $section ) || ! isset( $section['key'] ) || ! is_string( $section['key'] ) ) {
				continue;
			}

			$settings[ $section['key'] ] = $this->to_storage_section( $section['fields'] ?? null );
		}

		return $settings;
	}

	/**
	 * Bound a submitted width to a usable percentage.
	 *
	 * A value that is not a number falls back to full width rather than being
	 * clamped to the minimum. Clamping would render the field at one percent,
	 * which hides it from the checkout entirely, and a typo should not be able
	 * to make a required field invisible.
	 *
	 * @param mixed $width Submitted width.
	 */
	private function width( $width ): int {
		if ( ! is_numeric( $width ) ) {
			return 100;
		}

		return max( 1, min( 100, (int) $width ) );
	}

	/**
	 * Convert one submitted section's fields.
	 *
	 * @param mixed $fields Submitted fields.
	 * @return array<string, mixed>
	 */
	private function to_storage_section( $fields ): array {
		$rows = array();

		foreach ( is_array( $fields ) ? $fields : array() as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['key'] ) || ! is_string( $field['key'] ) ) {
				continue;
			}

			// Keep the first duplicate key to avoid silently overwriting it.
			if ( isset( $rows[ $field['key'] ] ) ) {
				continue;
			}

			$rows[ $field['key'] ] = $this->to_storage_field( $field );
		}

		return $rows;
	}

	/**
	 * Reduce one submitted field to its stored properties.
	 *
	 * Carried properties are applied first so a managed value always wins.
	 * The reverse would let a stale carried copy overwrite the edit that was
	 * just made.
	 *
	 * @param array<string, mixed> $field Submitted field.
	 * @return array<string, mixed>
	 */
	private function to_storage_field( array $field ): array {
		$carried = is_array( $field['carried'] ?? null ) ? $field['carried'] : array();

		$row = array_merge(
			array_diff_key( $carried, array_flip( $this->all_managed() ) ),
			array(
				'enabled'     => ! empty( $field['enabled'] ),
				'required'    => ! empty( $field['required'] ),
				'label'       => (string) ( $field['label'] ?? '' ),
				'placeholder' => (string) ( $field['placeholder'] ?? '' ),
				'width'       => $this->width( $field['width'] ?? null ),
				'custom'      => ! empty( $field['custom'] ),
			)
		);

		$row = array_merge( $row, $this->conditions_to_storage( $field['conditions'] ?? null ) );

		if ( empty( $field['custom'] ) ) {
			return $row;
		}

		// Only custom fields carry a type; WooCommerce owns default field types.
		$type           = $this->type( $field['type'] ?? null );
		$row['type']    = $type;
		$row['default'] = (string) ( $field['default'] ?? '' );

		if ( 'select' === $type ) {
			$row['options'] = (string) ( $field['options'] ?? '' );
		}

		// Drop settings from prior types so hidden behavior cannot survive a type change.
		foreach ( $this->type_settings()[ $type ] ?? array() as $key ) {
			$submitted = is_array( $field['settings'] ?? null ) ? $field['settings'] : array();

			if ( isset( $submitted[ $key ] ) ) {
				$row[ $key ] = $submitted[ $key ];
			}
		}

		return $row;
	}

	/**
	 * Reduce submitted condition groups to those with content.
	 *
	 * An empty group is omitted rather than stored as an empty structure,
	 * which every parser would have to treat as "no conditions" anyway.
	 *
	 * @param mixed $conditions Submitted condition groups.
	 * @return array<string, mixed>
	 */
	private function conditions_to_storage( $conditions ): array {
		if ( ! is_array( $conditions ) ) {
			return array();
		}

		$groups = array();

		foreach ( $this->condition_keys() as $group ) {
			$value = $conditions[ $group ] ?? null;

			if ( ! is_array( $value ) ) {
				continue;
			}

			// Payment and shipping groups store methods; other groups store rules.
			$content = $value['methods'] ?? $value['rules'] ?? null;

			if ( is_array( $content ) && array() !== $content ) {
				$groups[ $group ] = $value;
			}
		}

		return $groups;
	}

	/**
	 * Return a supported field type.
	 *
	 * @param mixed $type Submitted type.
	 */
	private function type( $type ): string {
		$supported = apply_filters( 'scfe_custom_field_types', array( 'text', 'number', 'email', 'url', 'select', 'textarea' ) );
		$supported = is_array( $supported ) ? $supported : array();

		return in_array( $type, $supported, true ) ? (string) $type : 'text';
	}
}
