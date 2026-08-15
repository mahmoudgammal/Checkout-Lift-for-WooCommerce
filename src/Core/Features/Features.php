<?php
/**
 * Runtime feature availability.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Features;

/**
 * Answers whether one feature may run in this installation.
 *
 * This is a packaging and lifecycle boundary, not a security boundary. Anyone
 * with file access can edit a PHP check out of the plugin, so nothing here is
 * treated as protection. What it buys is a single place to turn a capability
 * off when an entitlement lapses, and one edit instead of forty when the
 * feature set is split across builds.
 *
 * Every answer is resolved from memory. No option read, no network call, and
 * no database query, because callers sit on the checkout request path.
 */
final class Features {

	/**
	 * Features disabled for this installation, keyed for O(1) lookup.
	 *
	 * @var array<string, true>
	 */
	private array $disabled;

	/**
	 * Create the feature gate.
	 *
	 * @param array<int, string> $disabled Feature identifiers that must not run.
	 */
	public function __construct( array $disabled = array() ) {
		$this->disabled = array();

		foreach ( $disabled as $feature ) {
			if ( $this->is_valid_id( $feature ) ) {
				$this->disabled[ $feature ] = true;
			}
		}
	}

	/**
	 * Build the gate from the current build's constant.
	 *
	 * A build ships its own exclusions by defining `SCFE_DISABLED_FEATURES` as
	 * a comma separated list. When it is absent every feature is available,
	 * which matches the current single-build behaviour.
	 */
	public static function from_build(): self {
		if ( ! \defined( 'SCFE_DISABLED_FEATURES' ) ) {
			return new self();
		}

		$configured = (string) \constant( 'SCFE_DISABLED_FEATURES' );

		return new self( array_filter( array_map( 'trim', explode( ',', $configured ) ) ) );
	}

	/**
	 * Check whether a feature may run.
	 *
	 * @param string $feature Feature identifier, for example `recovery.whatsapp`.
	 */
	public function enabled( string $feature ): bool {
		if ( ! $this->is_valid_id( $feature ) ) {
			return false;
		}

		$enabled = ! isset( $this->disabled[ $feature ] );

		/**
		 * Filter whether one feature may run.
		 *
		 * Entitlement checks belong here. An implementation must answer from
		 * cached state and must fail open on error: a store cannot stop
		 * working because a licence server is unreachable.
		 *
		 * @since 1.0.3
		 *
		 * @param bool   $enabled Whether the feature may run.
		 * @param string $feature Feature identifier.
		 */
		return (bool) apply_filters( 'scfe_feature_enabled', $enabled, $feature );
	}

	/**
	 * Check that an identifier is a safe, stable feature name.
	 *
	 * @param mixed $feature Candidate identifier.
	 */
	private function is_valid_id( $feature ): bool {
		return is_string( $feature ) && 1 === preg_match( '/^[a-z][a-z0-9]*(?:[.-][a-z][a-z0-9]*)*$/', $feature );
	}
}
