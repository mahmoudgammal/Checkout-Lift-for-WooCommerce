<?php
/**
 * Which optional modules the merchant has switched on.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Settings;

/**
 * Reads and writes the merchant's module choices.
 *
 * Not to be confused with `Features`, which answers what this *build* ships.
 * The two are different questions and both have to say yes: a build may
 * exclude a module a merchant would happily switch on, and a merchant may
 * switch off a module their build includes. A module runs when the package
 * carries it and the shop asked for it.
 *
 * Everything defaults to on. A merchant who upgrades should find the plugin
 * behaving as it did, not silently missing the half they never visited a
 * settings screen to enable.
 */
final class ModuleSettings {

	/**
	 * Option holding the merchant's choices.
	 */
	private const OPTION_NAME = 'scfe_modules';

	/**
	 * Modules a merchant may switch off, and what to call them.
	 *
	 * The plumbing is deliberately absent: the field editor, its storage, its
	 * admin screens and the order display are what the plugin *is*, and
	 * offering to switch them off would offer to break it.
	 *
	 * @var array<string, string>
	 */
	private const OPTIONAL = array(
		'recovery'             => 'Checkout Recovery',
		'conditions'           => 'Conditional fields',
		'multistep'            => 'Multi-step checkout',
		'address-autocomplete' => 'Address autocomplete',
		'phone'                => 'International phone field',
		'file-upload'          => 'File upload fields',
		'datetime'             => 'Date and time fields',
		'fee'                  => 'Fee fields',
		'repeater'             => 'Repeater fields',
		'multilingual'         => 'WPML and Polylang support',
	);

	/**
	 * Settings cached for this request.
	 *
	 * @var array<string, bool>|null
	 */
	private ?array $cached = null;

	/**
	 * Return every module a merchant may switch off.
	 *
	 * @return array<string, string>
	 */
	public static function optional(): array {
		return self::OPTIONAL;
	}

	/**
	 * Report whether one module should run.
	 *
	 * A name this class does not know is always allowed, so a module that is
	 * not offered as a choice cannot be switched off by a stray option value.
	 *
	 * @param string $module Module name.
	 */
	public function enabled( string $module ): bool {
		if ( ! isset( self::OPTIONAL[ $module ] ) ) {
			return true;
		}

		return $this->all()[ $module ] ?? true;
	}

	/**
	 * Return every module's state, including the ones never saved.
	 *
	 * @return array<string, bool>
	 */
	public function all(): array {
		if ( null !== $this->cached ) {
			return $this->cached;
		}

		$stored = get_option( self::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$values = array();

		foreach ( array_keys( self::OPTIONAL ) as $module ) {
			// Missing settings default on so newly added modules work on existing sites.
			$values[ $module ] = ! isset( $stored[ $module ] ) || (bool) $stored[ $module ];
		}

		$this->cached = $values;

		return $values;
	}

	/**
	 * Replace the stored choices.
	 *
	 * @param array<string, mixed> $settings Submitted choices.
	 */
	public function replace( array $settings ): bool {
		$sanitized = array();

		foreach ( array_keys( self::OPTIONAL ) as $module ) {
			$sanitized[ $module ] = ! empty( $settings[ $module ] );
		}

		if ( $sanitized === $this->all() ) {
			return true;
		}

		if ( ! update_option( self::OPTION_NAME, $sanitized ) ) {
			return false;
		}

		$this->cached = $sanitized;

		return true;
	}
}
