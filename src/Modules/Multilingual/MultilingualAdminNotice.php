<?php
/**
 * Multilingual admin notice.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual;

/**
 * Renders and dismisses the provider-detected admin notice.
 */
final class MultilingualAdminNotice {

	private const NOTICE_OPTION = 'scfe_multilingual_notice_dismissed';
	private const NONCE_ACTION  = 'scfe_dismiss_notice';

	/**
	 * Create the notice.
	 *
	 * @param MultilingualProviders $providers Provider detector.
	 */
	public function __construct( private MultilingualProviders $providers ) {
	}

	/**
	 * Render the contextual notice.
	 */
	public function render(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', $this->dismiss_script() );
		?>
		<div class="notice notice-info is-dismissible scfe-multilingual-notice">
			<p><strong><?php esc_html_e( 'Multilingual Support Enabled', 'checkout-lift-for-woocommerce' ); ?></strong></p>
			<p><?php echo wp_kses_post( $this->message() ); ?></p>
			<p><a href="<?php echo esc_url( $this->translation_url() ); ?>" class="button button-secondary"><?php esc_html_e( 'Translate Strings', 'checkout-lift-for-woocommerce' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Dismiss the notice.
	 */
	public function dismiss(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'checkout-lift-for-woocommerce' ), 403 );
		}

		update_option( self::NOTICE_OPTION, true );
		wp_send_json_success();
	}

	/**
	 * Decide whether the notice should render.
	 */
	private function should_render(): bool {
		$screen = get_current_screen();

		return $screen
			&& 'woocommerce_page_checkout-lift-for-woocommerce' === $screen->id
			&& $this->providers->active()
			&& ! get_option( self::NOTICE_OPTION, false );
	}

	/**
	 * Build notice copy.
	 */
	private function message(): string {
		return sprintf(
			/* translators: %s: Plugin name (WPML or Polylang). */
			esc_html__( '%s detected! Field labels, placeholders, and options will be automatically registered for translation. Visit your translation settings to translate checkout field strings.', 'checkout-lift-for-woocommerce' ),
			'<strong>' . esc_html( $this->providers->label() ) . '</strong>'
		);
	}

	/**
	 * Build provider settings URL.
	 */
	private function translation_url(): string {
		$context = 'Checkout Lift';

		if ( $this->providers->wpml_active() && $this->providers->wpml_strings_active() ) {
			return admin_url( 'admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php&context=' . rawurlencode( $context ) );
		}

		return admin_url( 'admin.php?page=mlang_strings&group=' . rawurlencode( $context ) );
	}

	/**
	 * Return dismiss JavaScript attached to WordPress' bundled jQuery.
	 */
	private function dismiss_script(): string {
		$payload = array(
			'action' => 'scfe_dismiss_multilingual_notice',
			'nonce'  => wp_create_nonce( self::NONCE_ACTION ),
		);

		return 'jQuery(document).on("click", ".scfe-multilingual-notice .notice-dismiss", function(){jQuery.post(ajaxurl, ' . wp_json_encode( $payload ) . ');});';
	}
}
