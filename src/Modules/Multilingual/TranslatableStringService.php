<?php
/**
 * Translation string service.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Registers and translates strings through WPML or Polylang.
 */
final class TranslatableStringService {

	/**
	 * Create the service.
	 *
	 * @param MultilingualProviders $providers Provider detector.
	 * @param string                $context Translation context.
	 */
	public function __construct(
		private MultilingualProviders $providers,
		private string $context
	) {
	}

	/**
	 * Register one string with supported providers.
	 *
	 * @param string $name Translation name.
	 * @param string $text Text to register.
	 */
	public function register( string $name, string $text ): void {
		if ( '' === $text ) {
			return;
		}

		if ( $this->providers->wpml_strings_active() ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public action.
			do_action( 'wpml_register_single_string', $this->context, $name, $text );
		}

		if ( $this->providers->polylang_active() && function_exists( 'pll_register_string' ) ) {
			pll_register_string( $name, $text, $this->context, false );
		}
	}

	/**
	 * Translate one string through the active provider.
	 *
	 * @param string $name Translation name.
	 * @param string $text Text to translate.
	 */
	public function translate( string $name, string $text ): string {
		if ( '' === $text ) {
			return $text;
		}

		if ( $this->providers->wpml_strings_active() ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public filter.
			return (string) apply_filters( 'wpml_translate_single_string', $text, $this->context, $name );
		}

		if ( $this->providers->polylang_active() && function_exists( 'pll__' ) ) {
			return (string) pll__( $text );
		}

		return $text;
	}

	/**
	 * Return the provider context.
	 */
	public function context(): string {
		return $this->context;
	}
}
