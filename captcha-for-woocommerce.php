<?php
/**
 * Plugin Name:       Captcha for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/captcha-for-woocommerce
 * Description:       Multi-provider bot protection with reCAPTCHA, Turnstile & hCaptcha. Protect checkout, login, registration and more.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Shameem Reza
 * Author URI:        https://shameem.blog
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       captcha-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 8.0
 * WC tested up to:      10.4.3
 *
 * @package Captcha_For_WooCommerce
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin version.
 *
 * @var string
 */
define( 'CFWC_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 *
 * @var string
 */
define( 'CFWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 *
 * @var string
 */
define( 'CFWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 *
 * @var string
 */
define( 'CFWC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version required.
 *
 * @var string
 */
define( 'CFWC_MIN_PHP_VERSION', '7.4' );

/**
 * Minimum WordPress version required.
 *
 * @var string
 */
define( 'CFWC_MIN_WP_VERSION', '6.0' );

/**
 * Minimum WooCommerce version required.
 *
 * @var string
 */
define( 'CFWC_MIN_WC_VERSION', '8.0' );

/**
 * Check if WooCommerce is active.
 *
 * This function checks both single site and multisite installations
 * to determine if WooCommerce plugin is active.
 *
 * @since 1.0.0
 * @return bool True if WooCommerce is active, false otherwise.
 */
function cfwc_is_woocommerce_active() {
	// Check for single site.
	$active_plugins = get_option( 'active_plugins', array() );
	if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
		return true;
	}

	// Check for multisite.
	if ( is_multisite() ) {
		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );
		if ( isset( $network_plugins['woocommerce/woocommerce.php'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Display admin notice when WooCommerce is not active.
 *
 * Shows a dismissible error notice in the admin area when WooCommerce
 * is not installed or activated, as this plugin requires WooCommerce.
 *
 * @since 1.0.0
 * @return void
 */
function cfwc_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error is-dismissible">
		<p>
			<strong><?php esc_html_e( 'Captcha for WooCommerce', 'captcha-for-woocommerce' ); ?></strong>
			<?php esc_html_e( 'requires WooCommerce to be installed and activated.', 'captcha-for-woocommerce' ); ?>
			<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">
				<?php esc_html_e( 'Install WooCommerce', 'captcha-for-woocommerce' ); ?>
			</a>
		</p>
	</div>
	<?php
}

/**
 * Check PHP version requirement.
 *
 * @since 1.0.0
 * @return bool True if PHP version meets requirement.
 */
function cfwc_check_php_version() {
	return version_compare( PHP_VERSION, CFWC_MIN_PHP_VERSION, '>=' );
}

/**
 * Display admin notice for PHP version requirement.
 *
 * @since 1.0.0
 * @return void
 */
function cfwc_php_version_notice() {
	?>
	<div class="notice notice-error is-dismissible">
		<p>
			<strong><?php esc_html_e( 'Captcha for WooCommerce', 'captcha-for-woocommerce' ); ?></strong>
			<?php
			printf(
				/* translators: 1: Required PHP version, 2: Current PHP version */
				esc_html__( 'requires PHP version %1$s or higher. Your current version is %2$s.', 'captcha-for-woocommerce' ),
				esc_html( CFWC_MIN_PHP_VERSION ),
				esc_html( PHP_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Load the plugin autoloader.
 *
 * This function includes the autoloader file which handles automatic
 * loading of plugin classes following PSR-4 naming conventions.
 *
 * @since 1.0.0
 * @return void
 */
function cfwc_load_autoloader() {
	require_once CFWC_PLUGIN_DIR . 'includes/class-cfwc-autoloader.php';
}

/**
 * Initialize the plugin.
 *
 * This is the main initialization function that sets up the plugin
 * after all plugins are loaded. It checks requirements and loads
 * the main plugin class.
 *
 * @since 1.0.0
 * @return void
 */
function cfwc_init() {
	// Check PHP version.
	if ( ! cfwc_check_php_version() ) {
		add_action( 'admin_notices', 'cfwc_php_version_notice' );
		return;
	}

	// Check if WooCommerce is active.
	if ( ! cfwc_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'cfwc_woocommerce_missing_notice' );
		return;
	}

	// Load autoloader.
	cfwc_load_autoloader();

	// Initialize main plugin class.
	CFWC\Plugin::instance();
}
add_action( 'plugins_loaded', 'cfwc_init' );

/**
 * Note: Since WordPress 4.6 and for plugins hosted on WordPress.org,
 * translations are automatically loaded from translate.wordpress.org.
 * Manual load_plugin_textdomain() calls are no longer needed.
 *
 * @see https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#loading-text-domain
 */

/**
 * Activation hook callback.
 *
 * Runs when the plugin is activated. Sets up default options
 * and performs any necessary database updates.
 *
 * @since 1.0.0
 * @return void
 */
function cfwc_activate() {
	// Set default options if they don't exist.
	if ( false === get_option( 'cfwc_settings' ) ) {
		$defaults = array(
			'provider'              => '',
			'site_key'              => '',
			'secret_key'            => '',
			'theme'                 => 'auto',
			'size'                  => 'normal',
			'forms'                 => array(),
			'whitelist_logged_in'   => 'no',
			'whitelist_roles'       => array(),
			'whitelist_ips'         => '',
			'enable_honeypot'       => 'no',
			'enable_rate_limiting'  => 'no',
			'rate_limit_requests'   => 30,
			'enable_debug_logging'  => 'no',
			'failsafe_mode'         => 'honeypot',
		);
		add_option( 'cfwc_settings', $defaults );
	}

	// Set plugin version for future upgrade checks.
	update_option( 'cfwc_version', CFWC_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'cfwc_activate' );

/**
 * Deactivation hook callback.
 *
 * Runs when the plugin is deactivated. Cleans up any temporary data
 * but preserves settings for potential reactivation.
 *
 * @since 1.0.0
 * @return void
 */
function cfwc_deactivate() {
	// Clean up transients.
	delete_transient( 'cfwc_connection_test' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cfwc_deactivate' );

/**
 * Declare compatibility with WooCommerce features.
 *
 * This function declares compatibility with WooCommerce High-Performance
 * Order Storage (HPOS) and Cart/Checkout Blocks.
 *
 * @since 1.0.0
 * @return void
 */
function cfwc_declare_wc_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		// Declare HPOS compatibility.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);

		// Declare Cart/Checkout Blocks compatibility.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			__FILE__,
			true
		);
	}
}
add_action( 'before_woocommerce_init', 'cfwc_declare_wc_compatibility' );

/**
 * Add settings link to plugin action links.
 *
 * Adds a convenient "Settings" link to the plugin's entry on the
 * Plugins page, allowing quick access to the configuration page.
 *
 * @since 1.0.0
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function cfwc_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=wc-settings&tab=cfwc_captcha' ) ),
		esc_html__( 'Settings', 'captcha-for-woocommerce' )
	);

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . CFWC_PLUGIN_BASENAME, 'cfwc_plugin_action_links' );
