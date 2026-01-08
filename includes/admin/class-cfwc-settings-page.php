<?php
/**
 * Admin Settings Page.
 *
 * Integrates with WooCommerce Settings API to provide a native
 * settings experience within the WooCommerce admin area.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Admin;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Settings_Page class.
 *
 * Handles registration and rendering of the plugin settings tab
 * within WooCommerce settings.
 *
 * @since 1.0.0
 */
class Settings_Page {

	/**
	 * Settings tab ID.
	 *
	 * @var string
	 */
	const TAB_ID = 'cfwc_captcha';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Add settings tab to WooCommerce.
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );

		// Render settings fields.
		add_action( 'woocommerce_settings_' . self::TAB_ID, array( $this, 'output_settings' ) );

		// Save settings.
		add_action( 'woocommerce_update_options_' . self::TAB_ID, array( $this, 'save_settings' ) );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'display_notices' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_cfwc_test_connection', array( $this, 'ajax_test_connection' ) );
	}

	/**
	 * Add settings tab to WooCommerce settings.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing settings tabs.
	 * @return array Modified tabs array.
	 */
	public function add_settings_tab( $tabs ) {
		$tabs[ self::TAB_ID ] = __( 'CAPTCHA', 'captcha-for-woocommerce' );
		return $tabs;
	}

	/**
	 * Output settings fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function output_settings() {
		woocommerce_admin_fields( $this->get_settings() );

		// Output custom sections.
		$this->output_provider_status();
		$this->output_test_connection_button();
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings() {
		woocommerce_update_options( $this->get_settings() );

		// Rebuild our settings cache from WC options.
		$this->sync_settings_from_wc();
	}

	/**
	 * Get settings fields array.
	 *
	 * Returns the settings fields configuration for WooCommerce
	 * Settings API.
	 *
	 * @since 1.0.0
	 * @return array Settings fields.
	 */
	private function get_settings() {
		$provider_manager = Plugin::instance()->providers();
		$settings_manager = Plugin::instance()->settings();

		$settings = array(
			// Section: Provider Settings.
			array(
				'title' => __( 'CAPTCHA Provider', 'captcha-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Choose your preferred CAPTCHA provider and enter your API keys.', 'captcha-for-woocommerce' ),
				'id'    => 'cfwc_provider_section',
			),

			array(
				'title'    => __( 'Provider', 'captcha-for-woocommerce' ),
				'desc'     => __( 'Select the CAPTCHA service you want to use.', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_provider',
				'type'     => 'select',
				'options'  => $provider_manager->get_options(),
				'default'  => '',
				'class'    => 'wc-enhanced-select',
				'desc_tip' => true,
			),

			array(
				'title'             => __( 'Site Key', 'captcha-for-woocommerce' ),
				'desc'              => __( 'Enter the site key from your CAPTCHA provider.', 'captcha-for-woocommerce' ),
				'id'                => 'cfwc_site_key',
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),

			array(
				'title'             => __( 'Secret Key', 'captcha-for-woocommerce' ),
				'desc'              => __( 'Enter the secret key from your CAPTCHA provider. Keep this private.', 'captcha-for-woocommerce' ),
				'id'                => 'cfwc_secret_key',
				'type'              => 'password',
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),

			array(
				'type' => 'sectionend',
				'id'   => 'cfwc_provider_section',
			),

			// Section: Appearance.
			array(
				'title' => __( 'Appearance', 'captcha-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Customize how the CAPTCHA widget appears on your site.', 'captcha-for-woocommerce' ),
				'id'    => 'cfwc_appearance_section',
			),

			array(
				'title'    => __( 'Theme', 'captcha-for-woocommerce' ),
				'desc'     => __( 'Select the widget color theme.', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_theme',
				'type'     => 'select',
				'options'  => array(
					'auto'  => __( 'Auto (match site)', 'captcha-for-woocommerce' ),
					'light' => __( 'Light', 'captcha-for-woocommerce' ),
					'dark'  => __( 'Dark', 'captcha-for-woocommerce' ),
				),
				'default'  => 'auto',
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Size', 'captcha-for-woocommerce' ),
				'desc'     => __( 'Select the widget size.', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_size',
				'type'     => 'select',
				'options'  => array(
					'normal'  => __( 'Normal', 'captcha-for-woocommerce' ),
					'compact' => __( 'Compact', 'captcha-for-woocommerce' ),
				),
				'default'  => 'normal',
				'desc_tip' => true,
			),

			array(
				'title'             => __( 'Score Threshold', 'captcha-for-woocommerce' ),
				'desc'              => __( 'For reCAPTCHA v3: minimum score required to pass (0.0 to 1.0). Lower values are more lenient.', 'captcha-for-woocommerce' ),
				'id'                => 'cfwc_score_threshold',
				'type'              => 'number',
				'default'           => '0.5',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '1',
					'step' => '0.1',
				),
			),

			array(
				'type' => 'sectionend',
				'id'   => 'cfwc_appearance_section',
			),

			// Section: Protected Forms.
			array(
				'title' => __( 'Protected Forms', 'captcha-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Select which forms should require CAPTCHA verification.', 'captcha-for-woocommerce' ),
				'id'    => 'cfwc_forms_section',
			),

			array(
				'title'    => __( 'WordPress Forms', 'captcha-for-woocommerce' ),
				'desc'     => __( 'WordPress Login', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_forms[wp_login]',
				'type'     => 'checkbox',
				'default'  => 'no',
				'checkboxgroup' => 'start',
			),

			array(
				'desc'     => __( 'WordPress Registration', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_forms[wp_register]',
				'type'     => 'checkbox',
				'default'  => 'no',
				'checkboxgroup' => '',
			),

			array(
				'desc'     => __( 'WordPress Lost Password', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_forms[wp_lost_password]',
				'type'     => 'checkbox',
				'default'  => 'no',
				'checkboxgroup' => '',
			),

			array(
				'desc'     => __( 'WordPress Comments', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_forms[wp_comment]',
				'type'     => 'checkbox',
				'default'  => 'no',
				'checkboxgroup' => 'end',
			),

			array(
				'title'    => __( 'WooCommerce Forms', 'captcha-for-woocommerce' ),
				'desc'     => __( 'My Account Login', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_forms[wc_login]',
				'type'     => 'checkbox',
				'default'  => 'no',
				'checkboxgroup' => 'start',
			),

			array(
				'desc'     => __( 'My Account Registration', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_forms[wc_register]',
				'type'     => 'checkbox',
				'default'  => 'no',
				'checkboxgroup' => '',
			),

			array(
				'desc'     => __( 'My Account Lost Password', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_forms[wc_lost_password]',
				'type'     => 'checkbox',
				'default'  => 'no',
				'checkboxgroup' => '',
			),

			array(
				'desc'     => __( 'Checkout (Classic)', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_forms[wc_checkout_classic]',
				'type'     => 'checkbox',
				'default'  => 'no',
				'checkboxgroup' => '',
			),

			array(
				'desc'     => __( 'Checkout (Block)', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_forms[wc_checkout_block]',
				'type'     => 'checkbox',
				'default'  => 'no',
				'checkboxgroup' => '',
			),

			array(
				'desc'     => __( 'Pay for Order', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_forms[wc_pay_order]',
				'type'     => 'checkbox',
				'default'  => 'no',
				'checkboxgroup' => 'end',
			),

			// WooCommerce Extensions (conditionally shown).
			$this->get_extension_form_settings(),

			array(
				'type' => 'sectionend',
				'id'   => 'cfwc_forms_section',
			),

			// Section: Access Control.
			array(
				'title' => __( 'Access Control', 'captcha-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Configure which users should bypass CAPTCHA verification.', 'captcha-for-woocommerce' ),
				'id'    => 'cfwc_access_section',
			),

			array(
				'title'   => __( 'Skip for Logged-in Users', 'captcha-for-woocommerce' ),
				'desc'    => __( 'Skip CAPTCHA for all logged-in users', 'captcha-for-woocommerce' ),
				'id'      => 'cfwc_whitelist_logged_in',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'title'    => __( 'Skip for Roles', 'captcha-for-woocommerce' ),
				'desc'     => __( 'Skip CAPTCHA for users with these roles.', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_whitelist_roles',
				'type'     => 'multiselect',
				'options'  => $this->get_role_options(),
				'default'  => array(),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => true,
			),

			array(
				'title'    => __( 'Whitelisted IPs', 'captcha-for-woocommerce' ),
				'desc'     => __( 'Enter IP addresses to skip CAPTCHA (one per line).', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_whitelist_ips',
				'type'     => 'textarea',
				'default'  => '',
				'desc_tip' => true,
				'css'      => 'height: 100px;',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'cfwc_access_section',
			),

			// Section: Advanced.
			array(
				'title' => __( 'Advanced Settings', 'captcha-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Additional protection and debugging options.', 'captcha-for-woocommerce' ),
				'id'    => 'cfwc_advanced_section',
			),

			array(
				'title'   => __( 'Enable Honeypot', 'captcha-for-woocommerce' ),
				'desc'    => __( 'Add invisible honeypot fields for extra bot protection', 'captcha-for-woocommerce' ),
				'id'      => 'cfwc_enable_honeypot',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'title'             => __( 'Minimum Form Time', 'captcha-for-woocommerce' ),
				'desc'              => __( 'Minimum seconds before form can be submitted (honeypot feature).', 'captcha-for-woocommerce' ),
				'id'                => 'cfwc_honeypot_min_time',
				'type'              => 'number',
				'default'           => '3',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min' => '1',
					'max' => '30',
				),
			),

			array(
				'title'    => __( 'Failsafe Mode', 'captcha-for-woocommerce' ),
				'desc'     => __( 'Action to take when CAPTCHA service is unreachable.', 'captcha-for-woocommerce' ),
				'id'       => 'cfwc_failsafe_mode',
				'type'     => 'select',
				'options'  => array(
					'block'    => __( 'Block all submissions (strictest)', 'captcha-for-woocommerce' ),
					'honeypot' => __( 'Use honeypot fallback (recommended)', 'captcha-for-woocommerce' ),
					'allow'    => __( 'Allow all submissions (not recommended)', 'captcha-for-woocommerce' ),
				),
				'default'  => 'honeypot',
				'desc_tip' => true,
			),

			array(
				'title'   => __( 'Debug Logging', 'captcha-for-woocommerce' ),
				'desc'    => __( 'Enable debug logging to WooCommerce logs', 'captcha-for-woocommerce' ),
				'id'      => 'cfwc_enable_debug_logging',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'cfwc_advanced_section',
			),
		);

		/**
		 * Filter the settings fields.
		 *
		 * @since 1.0.0
		 * @param array $settings Settings fields array.
		 */
		return apply_filters( 'cfwc_settings_fields', $settings );
	}

	/**
	 * Get extension form settings.
	 *
	 * Returns settings fields for WooCommerce extensions that are installed.
	 *
	 * @since 1.0.0
	 * @return array Settings fields or empty array.
	 */
	private function get_extension_form_settings() {
		$fields = array();

		// Product Vendors.
		if ( class_exists( 'WC_Product_Vendors' ) ) {
			$fields[] = array(
				'title'         => __( 'WooCommerce Extensions', 'captcha-for-woocommerce' ),
				'desc'          => __( 'Product Vendors Registration', 'captcha-for-woocommerce' ),
				'id'            => 'cfwc_forms[wcpv_registration]',
				'type'          => 'checkbox',
				'default'       => 'no',
				'checkboxgroup' => 'start',
			);
		}

		// Subscriptions.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$fields[] = array(
				'desc'          => __( 'Subscriptions Checkout', 'captcha-for-woocommerce' ),
				'id'            => 'cfwc_forms[wc_subscriptions]',
				'type'          => 'checkbox',
				'default'       => 'no',
				'checkboxgroup' => '',
			);
		}

		// Memberships.
		if ( function_exists( 'wc_memberships' ) ) {
			$fields[] = array(
				'desc'          => __( 'Memberships Registration', 'captcha-for-woocommerce' ),
				'id'            => 'cfwc_forms[wc_memberships]',
				'type'          => 'checkbox',
				'default'       => 'no',
				'checkboxgroup' => 'end',
			);
		}

		// Close checkbox group if started.
		if ( ! empty( $fields ) ) {
			$last_key = array_key_last( $fields );
			$fields[ $last_key ]['checkboxgroup'] = 'end';
		}

		return $fields;
	}

	/**
	 * Get role options for multiselect.
	 *
	 * @since 1.0.0
	 * @return array Role options.
	 */
	private function get_role_options() {
		$roles   = wp_roles()->roles;
		$options = array();

		foreach ( $roles as $role_slug => $role_data ) {
			$options[ $role_slug ] = translate_user_role( $role_data['name'] );
		}

		return $options;
	}

	/**
	 * Output provider status information.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function output_provider_status() {
		$settings = Plugin::instance()->settings();
		$provider = $settings->get( 'provider' );

		if ( empty( $provider ) ) {
			return;
		}

		$provider_instance = Plugin::instance()->providers()->get_provider( $provider );
		if ( ! $provider_instance ) {
			return;
		}

		$api_url = $provider_instance->get_api_key_url();
		if ( ! empty( $api_url ) && $provider_instance->requires_api_keys() ) {
			?>
			<p class="cfwc-api-link">
				<a href="<?php echo esc_url( $api_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Get your API keys', 'captcha-for-woocommerce' ); ?> &rarr;
				</a>
			</p>
			<?php
		}
	}

	/**
	 * Output test connection button.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function output_test_connection_button() {
		?>
		<p class="cfwc-test-connection">
			<button type="button" class="button button-secondary" id="cfwc-test-connection">
				<?php esc_html_e( 'Test Connection', 'captcha-for-woocommerce' ); ?>
			</button>
			<span class="cfwc-test-result"></span>
		</p>
		<?php
	}

	/**
	 * Sync settings from WooCommerce options to our settings array.
	 *
	 * WooCommerce saves each field separately, but we want them
	 * consolidated in our settings array for easier access.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function sync_settings_from_wc() {
		$settings = array(
			'provider'             => get_option( 'cfwc_provider', '' ),
			'site_key'             => get_option( 'cfwc_site_key', '' ),
			'secret_key'           => get_option( 'cfwc_secret_key', '' ),
			'theme'                => get_option( 'cfwc_theme', 'auto' ),
			'size'                 => get_option( 'cfwc_size', 'normal' ),
			'score_threshold'      => get_option( 'cfwc_score_threshold', 0.5 ),
			'forms'                => $this->get_enabled_forms(),
			'whitelist_logged_in'  => get_option( 'cfwc_whitelist_logged_in', 'no' ),
			'whitelist_roles'      => get_option( 'cfwc_whitelist_roles', array() ),
			'whitelist_ips'        => get_option( 'cfwc_whitelist_ips', '' ),
			'enable_honeypot'      => get_option( 'cfwc_enable_honeypot', 'no' ),
			'honeypot_min_time'    => get_option( 'cfwc_honeypot_min_time', 3 ),
			'failsafe_mode'        => get_option( 'cfwc_failsafe_mode', 'honeypot' ),
			'enable_debug_logging' => get_option( 'cfwc_enable_debug_logging', 'no' ),
		);

		update_option( 'cfwc_settings', $settings );
	}

	/**
	 * Get enabled forms from checkbox options.
	 *
	 * @since 1.0.0
	 * @return array Array of enabled form IDs.
	 */
	private function get_enabled_forms() {
		$forms_option = get_option( 'cfwc_forms', array() );
		$enabled      = array();

		if ( is_array( $forms_option ) ) {
			foreach ( $forms_option as $form_id => $value ) {
				if ( 'yes' === $value ) {
					$enabled[] = $form_id;
				}
			}
		}

		return $enabled;
	}

	/**
	 * Display admin notices.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_notices() {
		// Only show on our settings page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tab'] ) || self::TAB_ID !== $_GET['tab'] ) {
			return;
		}

		$settings = Plugin::instance()->settings();

		// Warning if no provider configured.
		if ( ! $settings->is_provider_configured() ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e( 'Captcha for WooCommerce:', 'captcha-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Please select a CAPTCHA provider and enter your API keys to enable protection.', 'captcha-for-woocommerce' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * AJAX handler for connection test.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'cfwc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'captcha-for-woocommerce' ) ) );
		}

		$provider_id = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$site_key    = isset( $_POST['site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['site_key'] ) ) : '';
		$secret_key  = isset( $_POST['secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['secret_key'] ) ) : '';

		if ( empty( $provider_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a provider.', 'captcha-for-woocommerce' ) ) );
		}

		$provider = Plugin::instance()->providers()->get_provider( $provider_id );

		if ( ! $provider ) {
			wp_send_json_error( array( 'message' => __( 'Invalid provider.', 'captcha-for-woocommerce' ) ) );
		}

		$result = $provider->test_connection( $site_key, $secret_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'captcha-for-woocommerce' ) ) );
	}
}
