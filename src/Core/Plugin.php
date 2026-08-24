<?php
/**
 * Main plugin orchestrator.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core;

/**
 * Composes and boots the plugin modules.
 */
final class Plugin {

	/**
	 * Instance of this class.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Return an instance of this class.
	 *
	 * @return self A single instance of this class.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->boot_modules();
		$this->init_hooks();
	}

	/**
	 * Register the modules that have completed direct cutover.
	 *
	 * @throws \UnexpectedValueException When an extension supplies an invalid provider list.
	 */
	private function boot_modules() {
		$registry        = new \DesignTich\SimpleCheckoutFieldsEditor\Core\ModuleRegistry();
		$checkout_fields = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository();
		$module_settings = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleSettings();

		$this->register_module_providers(
			$registry,
			array(
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Providers\BuiltInModuleProvider(
					$this->built_in_modules( $checkout_fields )
				),
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Providers\OptionalModuleProvider(
					$module_settings,
					$this->optional_module_groups( $checkout_fields )
				),
			)
		);
		$registry->boot();
	}

	/**
	 * Register built-in and extension module providers.
	 *
	 * @param ModuleRegistry $registry  Module registry.
	 * @param array          $providers Initial providers.
	 * @phpstan-param list<\DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\ModuleProvider> $providers
	 * @throws \UnexpectedValueException When an extension supplies an invalid provider list.
	 */
	private function register_module_providers( ModuleRegistry $registry, array $providers ): void {

		/**
		 * Filter the providers that supply plugin modules.
		 *
		 * Add-on plugins register their provider here after their classes are loaded.
		 *
		 * @since 1.0.3
		 *
		 * @param list<\DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\ModuleProvider> $providers Module providers.
		 */
		$providers = apply_filters( 'scfe_module_providers', $providers );

		if ( ! is_array( $providers ) ) {
			throw new \UnexpectedValueException( 'The scfe_module_providers filter must return an array.' );
		}

		foreach ( $providers as $provider ) {
			if ( ! $provider instanceof \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\ModuleProvider ) {
				throw new \UnexpectedValueException( 'The scfe_module_providers filter must contain ModuleProvider instances.' );
			}

			$registry->register_provider( $provider );
		}
	}

	/**
	 * Compose modules that define the base plugin runtime.
	 *
	 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 * @return list<\DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module>
	 */
	private function built_in_modules( $checkout_fields ): array {
		return array(
			$this->database_module(),
			$this->privacy_module(),
			$this->fields_admin_module( $checkout_fields ),
			$this->core_assets_module(),
			$this->checkout_fields_module( $checkout_fields ),
			$this->custom_order_fields_module( $checkout_fields ),
			$this->checkout_fields_admin_module( $checkout_fields ),
			$this->settings_module(),
		);
	}

	/**
	 * Compose independently packageable module groups without constructing them.
	 *
	 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 * @return list<\DesignTich\SimpleCheckoutFieldsEditor\Core\Providers\OptionalModuleGroup>
	 * @throws \UnexpectedValueException When an extension supplies invalid groups.
	 */
	private function optional_module_groups( $checkout_fields ): array {
		$groups = array(
			$this->optional_capability_group( 'recovery.tracking', 'recovery', fn() => $this->cart_tracking_module() ),
			$this->optional_capability_group( 'recovery.dashboard', 'recovery', fn() => $this->recovery_dashboard_module() ),
			$this->optional_capability_group( 'recovery.settings', 'recovery', fn() => $this->recovery_settings_module() ),
			$this->optional_capability_group( 'revenue.analytics', 'recovery', fn() => $this->revenue_analytics_module() ),
			$this->optional_capability_group( 'conditions.fields', 'conditions', fn() => $this->field_conditions_module( $checkout_fields ) ),
			$this->optional_module_group( 'phone', fn() => $this->phone_module( $checkout_fields ) ),
			$this->optional_module_group( 'multilingual', fn() => $this->multilingual_module() ),
		);

		/**
		 * Filter optional module groups before their lazy factories are evaluated.
		 *
		 * Add-ons use this to retain deterministic registration order while owning
		 * their module source and composition.
		 *
		 * @since 1.0.0
		 *
		 * @param list<\DesignTich\SimpleCheckoutFieldsEditor\Core\Providers\OptionalModuleGroup> $groups          Optional groups.
		 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository $checkout_fields Checkout field settings.
		 */
		$groups = apply_filters( 'scfe_optional_module_groups', $groups, $checkout_fields );

		if ( ! is_array( $groups ) ) {
			throw new \UnexpectedValueException( 'The scfe_optional_module_groups filter must return an array.' );
		}

		foreach ( $groups as $group ) {
			if ( ! $group instanceof \DesignTich\SimpleCheckoutFieldsEditor\Core\Providers\OptionalModuleGroup ) {
				throw new \UnexpectedValueException( 'The scfe_optional_module_groups filter must contain OptionalModuleGroup instances.' );
			}
		}

		return array_values( $groups );
	}

	/**
	 * Compose one capability behind an existing merchant setting.
	 *
	 * @param string   $capability Build capability identifier.
	 * @param string   $setting    Existing merchant setting identifier.
	 * @param callable $factory    Lazy module factory.
	 * @phpstan-param callable(): \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module $factory
	 */
	private function optional_capability_group( string $capability, string $setting, callable $factory ): \DesignTich\SimpleCheckoutFieldsEditor\Core\Providers\OptionalModuleGroup {
		return new \DesignTich\SimpleCheckoutFieldsEditor\Core\Providers\OptionalModuleGroup(
			$capability,
			$setting,
			array( $factory )
		);
	}

	/**
	 * Compose a module group whose build and merchant identifiers are identical.
	 *
	 * @param string   $identifier Module identifier.
	 * @param callable $factory    Lazy module factory.
	 * @phpstan-param callable(): \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module $factory
	 */
	private function optional_module_group( string $identifier, callable $factory ): \DesignTich\SimpleCheckoutFieldsEditor\Core\Providers\OptionalModuleGroup {
		return new \DesignTich\SimpleCheckoutFieldsEditor\Core\Providers\OptionalModuleGroup(
			$identifier,
			$identifier,
			array( $factory )
		);
	}

	/**
	 * Compose the settings screen.
	 *
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function settings_module() {
		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Settings\SettingsModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Settings\SettingsPage(),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Settings\ModulesController(
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleSettings(),
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleShutdown()
			)
		);
	}

	/**
	 * Compose custom table installation and upgrades.
	 *
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function database_module() {
		return new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\DatabaseModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\Migrator(
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\Schema(
					new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\TableNames()
				),
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\TableNames()
			)
		);
	}

	/**
	 * Compose retention, export, and erasure of stored checkout activity.
	 *
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function privacy_module() {
		$tables   = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\TableNames();
		$carts    = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartRepository(
			$tables,
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartColumns(),
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings()
		);
		$messages = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\MessageRepository( $tables );

		return new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacyModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\RetentionSweep( $carts, $messages ),
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PersonalDataExporter( $carts ),
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PersonalDataEraser( $carts, $messages )
		);
	}

	/**
	 * Compose the public checkout-activity capture endpoint.
	 *
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function cart_tracking_module() {
		$tables  = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\TableNames();
		$privacy = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings();
		$consent = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\ConsentPolicy( $privacy );
		$blocks  = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks();

		$carts  = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartRepository(
			$tables,
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\CartColumns(),
			$privacy
		);
		$tokens = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\ResumeToken();

		$capture = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\CartCapture(
			$carts,
			$consent,
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\CapturedCheckout(),
			$tokens
		);

		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking\CartTrackingModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking\CaptureController(
				$capture,
				$consent,
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking\CaptureRateLimiter()
			),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking\ConsentField( $consent, $privacy, $blocks ),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking\TrackingAssets(
				$consent,
				$privacy,
				$blocks,
				$this->plugin_url(),
				$this->version()
			),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CartTracking\CheckoutCompletion( $carts )
		);
	}

	/**
	 * Compose the recovery dashboard screen and its endpoint.
	 *
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function recovery_dashboard_module() {
		$tables = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\TableNames();

		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard\RecoveryDashboardModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard\DashboardPage(),
			$this->dashboard_assets(),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard\StatsController(
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\RecoveryStats( $tables ),
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings()
			),
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Admin\AdminMenu()
		);
	}

	/**
	 * Compose the recovery settings endpoint and validate its add-on fields.
	 *
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 * @throws \UnexpectedValueException When an add-on supplies an invalid extension.
	 */
	private function recovery_settings_module() {
		/**
		 * Filter the optional fields attached to the recovery settings endpoint.
		 *
		 * @since 1.0.3
		 *
		 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoverySettingsExtension|null $extension Settings extension.
		 */
		$extension = apply_filters( 'scfe_recovery_settings_extension', null );

		if ( null !== $extension && ! $extension instanceof \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoverySettingsExtension ) {
			throw new \UnexpectedValueException( 'The scfe_recovery_settings_extension filter must return a RecoverySettingsExtension or null.' );
		}

		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard\RecoverySettingsModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard\SettingsController(
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings(),
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoverySettings(),
				$extension
			)
		);
	}

	/**
	 * Compose privacy-minimized checkout revenue analytics.
	 *
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function revenue_analytics_module() {
		$tables   = new \DesignTich\SimpleCheckoutFieldsEditor\Core\Database\TableNames();
		$attempts = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\AttemptRepository( $tables );
		$session  = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\AttemptSession();
		$tracker  = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\AttemptTracker(
			$attempts,
			$session,
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\PlacedOrderFacts(),
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings(),
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks()
		);
		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\RevenueAnalyticsModule(
			$tracker,
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\AbandonmentClassifier(
				$attempts,
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery\RecoverySettings()
			),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\RevenueController(
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\RevenueQuery( $tables ),
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings()
			),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\RevenuePage(),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\RevenueAssets(
				$this->plugin_dir(),
				$this->plugin_url(),
				$this->version()
			),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RevenueAnalytics\RevenueRetention(
				$attempts,
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy\PrivacySettings()
			)
		);
	}

	/**
	 * Compose the loader for the built React bundle.
	 *
	 * One bundle serves every React screen, so one loader lists them all.
	 *
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard\DashboardAssets
	 */
	private function dashboard_assets() {
		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard\DashboardAssets(
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks(),
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\ModuleSettings(),
			$this->plugin_dir(),
			$this->plugin_url(),
			$this->version(),
			array(
				\DesignTich\SimpleCheckoutFieldsEditor\Modules\RecoveryDashboard\DashboardPage::SLUG,
				\DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin\FieldsPage::SLUG,
				\DesignTich\SimpleCheckoutFieldsEditor\Modules\Settings\SettingsPage::SLUG,
			)
		);
	}

	/**
	 * Compose the replacement field editor.
	 *
	 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function fields_admin_module( $checkout_fields ) {
		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin\FieldsAdminModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin\FieldsPage(),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin\FieldsController(
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Admin\AdminCheckoutFields(
					$checkout_fields,
					new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Admin\DefaultCheckoutFields()
				),
				$checkout_fields,
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin\FieldSettingsSanitizer(),
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin\FieldsPayload()
			),
			$this->dashboard_assets(),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldsAdmin\AdvancedController(
				$checkout_fields,
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin\FieldSettingsSanitizer()
			)
		);
	}

	/**
	 * Compose assets shared by more than one feature module.
	 *
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function core_assets_module() {
		return new \DesignTich\SimpleCheckoutFieldsEditor\Core\Assets\CoreAssetsModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Assets\CheckoutBridgeAssets(
				$this->plugin_url(),
				$this->version()
			)
		);
	}

	/**
	 * Compose base custom order-field persistence and display.
	 *
	 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function custom_order_fields_module( $checkout_fields ) {
		$definitions = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields\CustomFieldDefinitions( $checkout_fields );

		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields\CustomOrderFieldsModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields\ClassicOrderPersistence(
				$definitions,
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields\SubmittedFieldValues(
					new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields\FieldValueSanitizer()
				)
			),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields\OrderFieldsRenderer(
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CustomOrderFields\OrderFieldRows(
					$definitions,
					$this->blocks_custom_fields( $checkout_fields )
				)
			)
		);
	}

	/**
	 * Compose base classic checkout fields.
	 *
	 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function checkout_fields_module( $checkout_fields ) {
		$shipping_settings = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\ShippingAddressSettings();

		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\CheckoutFieldsModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\ClassicCheckoutAdapter(
				$checkout_fields,
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\CheckoutFieldsTransformer(
					new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\CustomFieldBuilder(
						new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\FieldOptionsParser()
					),
					new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\FieldDecorator(
						new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\ConditionAttributes()
					),
					$shipping_settings
				),
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\DefaultAddressFieldsTransformer(),
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\DisabledFields( $checkout_fields )
			),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\BlocksLocaleAdapter(
				$checkout_fields,
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks()
			),
			$this->blocks_custom_fields( $checkout_fields ),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\CheckoutFieldsAssets(
				$checkout_fields,
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\FieldWidthStyles( $checkout_fields ),
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\BlocksFieldWidthStyles( $checkout_fields ),
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks(),
				$this->plugin_url(),
				$this->version()
			)
		);
	}

	/**
	 * Compose the block checkout custom field registrar.
	 *
	 * Shared, because the order screen needs the same id mapping the registrar
	 * uses. Two copies would be two chances for them to disagree about what a
	 * field is called.
	 *
	 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\BlocksCustomFields
	 */
	private function blocks_custom_fields( $checkout_fields ) {
		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\BlocksCustomFields(
			$checkout_fields,
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFields\FieldOptionsParser(),
			new \DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\CheckoutBlocks()
		);
	}

	/**
	 * Compose the checkout-fields administration module.
	 *
	 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function checkout_fields_admin_module( $checkout_fields ) {
		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin\CheckoutFieldsAdminModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin\SaveSettingsAction(
				$checkout_fields,
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin\SettingsPayloadDecoder(),
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin\FieldSettingsSanitizer()
			)
		);
	}

	/**
	 * Compose the Phone module and its private collaborators.
	 *
	 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function phone_module( $checkout_fields ) {
		$settings = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone\PhoneFieldSettings( $checkout_fields );

		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone\PhoneModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone\PhoneAssets(
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone\CountryResolver(),
				$settings,
				$this->plugin_url(),
				$this->version()
			),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone\ClassicCheckoutValidator(
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone\InternationalPhoneValidator(),
				$settings
			)
		);
	}

	/**
	 * Compose server-authoritative field conditions.
	 *
	 * @param \DesignTich\SimpleCheckoutFieldsEditor\Core\Settings\CheckoutFieldsRepository $checkout_fields Checkout field settings.
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function field_conditions_module( $checkout_fields ) {
		$parser                 = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions\FieldConditionParser();
		$browser_configurations = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions\BrowserDependencyConfigurations(
			$checkout_fields,
			$parser
		);

		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions\FieldConditionsModule(
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions\ClassicValidationAdapter(
				$checkout_fields,
				new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions\FieldVisibilityResolver(
					new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions\FieldConditionConfigurations(
						$parser
					),
					new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions\FieldRuleEvaluator()
				),
				new \DesignTich\SimpleCheckoutFieldsEditor\Core\Checkout\SerializedCheckoutDataParser()
			),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions\FieldDependencyAssets( $browser_configurations, $this->plugin_url(), $this->version() ),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\FieldConditions\FieldDependencyAttributes( $browser_configurations )
		);
	}

	/**
	 * Compose WPML and Polylang integration.
	 *
	 * @return \DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module
	 */
	private function multilingual_module() {
		$providers        = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual\MultilingualProviders();
		$base_strings     = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual\TranslatableStringService( $providers, 'Checkout Lift' );
		$checkout_strings = new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual\CheckoutFieldStringRegistry(
			$base_strings,
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual\OptionsStringParser()
		);

		return new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual\MultilingualModule(
			$providers,
			$checkout_strings,
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual\CheckoutFieldsTranslator( $providers, $base_strings ),
			new \DesignTich\SimpleCheckoutFieldsEditor\Modules\Multilingual\MultilingualAdminNotice( $providers )
		);
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		/**
		 * Hook to include additional feature files (for external extensions).
		 *
		 * @since 1.0.0
		 */
		do_action( 'scfe_include_features' );
	}

	/**
	 * Check if the separately installed Pro add-on is active.
	 *
	 * @return bool
	 */
	public function is_pro_active() {
		return (bool) apply_filters( 'scfe_is_pro', false );
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		/**
		 * Hook to initialize additional features (for external extensions).
		 *
		 * @since 1.0.0
		 */
		do_action( 'scfe_init_features' );
	}

	/**
	 * Return plugin directory path.
	 */
	private function plugin_dir(): string {
		return (string) \constant( 'SCFE_PLUGIN_DIR' );
	}

	/**
	 * Return plugin URL.
	 */
	private function plugin_url(): string {
		return (string) \constant( 'SCFE_PLUGIN_URL' );
	}

	/**
	 * Return plugin version.
	 */
	private function version(): string {
		return (string) \constant( 'SCFE_VERSION' );
	}
}
