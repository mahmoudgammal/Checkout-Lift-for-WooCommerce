<?php
/**
 * Multilingual provider detection.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Detects supported multilingual plugins at the WordPress boundary.
 */
final class MultilingualProviders {

	/**
	 * Check if WPML is active.
	 */
	public function wpml_active(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' ) && class_exists( 'SitePress' );
	}

	/**
	 * Check if WPML String Translation is active.
	 */
	public function wpml_strings_active(): bool {
		return defined( 'WPML_ST_VERSION' );
	}

	/**
	 * Check if Polylang is active.
	 */
	public function polylang_active(): bool {
		return defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_register_string' );
	}

	/**
	 * Check whether any supported multilingual provider is active.
	 */
	public function active(): bool {
		return $this->wpml_active() || $this->polylang_active();
	}

	/**
	 * Return the active provider label for admin notices.
	 */
	public function label(): string {
		return $this->wpml_active() ? 'WPML' : 'Polylang';
	}

	/**
	 * Return the current language code.
	 */
	public function current_language(): string {
		if ( $this->wpml_active() ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML public filter.
			return (string) apply_filters( 'wpml_current_language', null );
		}

		if ( $this->polylang_active() && function_exists( 'pll_current_language' ) ) {
			return (string) pll_current_language();
		}

		return get_locale();
	}
}
