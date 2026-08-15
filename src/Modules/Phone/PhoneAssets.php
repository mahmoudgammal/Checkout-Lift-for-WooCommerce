<?php
/**
 * Classic checkout assets owned by the Phone module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone;

/**
 * Loads the phone widget only on checkout requests.
 */
final class PhoneAssets {

	/**
	 * Create the classic checkout asset adapter.
	 *
	 * @param CountryResolver    $country_resolver Initial-country resolver.
	 * @param PhoneFieldSettings $field_settings Current phone field settings.
	 * @param string             $plugin_url       Absolute plugin asset URL.
	 * @param string             $plugin_version   First-party asset version.
	 */
	public function __construct(
		private CountryResolver $country_resolver,
		private PhoneFieldSettings $field_settings,
		private string $plugin_url,
		private string $plugin_version
	) {
	}

	/**
	 * Register the asset boundary.
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue Phone module assets on classic checkout.
	 */
	public function enqueue(): void {
		if ( ! is_checkout() || ! $this->field_settings->is_enabled() ) {
			return;
		}

		$this->enqueue_library();
		$this->enqueue_module_assets();
		$this->localize_script();
	}

	/**
	 * Enqueue the bundled phone input library.
	 */
	private function enqueue_library(): void {
		wp_enqueue_style(
			'intl-tel-input',
			$this->plugin_url . 'assets/lib/intl-tel-input/intlTelInput.min.css',
			array(),
			'17.0.8'
		);
		wp_enqueue_script(
			'intl-tel-input',
			$this->plugin_url . 'assets/lib/intl-tel-input/intlTelInput.min.js',
			array( 'jquery' ),
			'17.0.8',
			true
		);
	}

	/**
	 * Enqueue first-party Phone module assets.
	 */
	private function enqueue_module_assets(): void {
		wp_enqueue_style(
			'scfe-phone',
			$this->plugin_url . 'assets/css/phone-field.css',
			array( 'intl-tel-input' ),
			$this->plugin_version
		);
		wp_enqueue_script(
			'scfe-phone',
			$this->plugin_url . 'assets/js/modules/phone.js',
			array( 'jquery', 'intl-tel-input' ),
			$this->plugin_version,
			true
		);
	}

	/**
	 * Pass paths, country, and translated errors to the Phone module.
	 */
	private function localize_script(): void {
		wp_localize_script(
			'scfe-phone',
			'scfePhone',
			array(
				'utilsScript'    => $this->plugin_url . 'assets/lib/intl-tel-input/utils.js',
				'initialCountry' => $this->country_resolver->country_code(),
				'errors'         => array(
					'invalid'     => __( 'Invalid phone number', 'checkout-lift-for-woocommerce' ),
					'invalidCode' => __( 'Invalid country code', 'checkout-lift-for-woocommerce' ),
					'tooShort'    => __( 'Phone number is too short', 'checkout-lift-for-woocommerce' ),
					'tooLong'     => __( 'Phone number is too long', 'checkout-lift-for-woocommerce' ),
					'notNumber'   => __( 'Invalid phone number', 'checkout-lift-for-woocommerce' ),
				),
			)
		);
	}
}
