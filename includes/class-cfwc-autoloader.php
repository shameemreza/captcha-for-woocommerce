<?php
/**
 * Autoloader for Captcha for WooCommerce.
 *
 * Handles automatic loading of plugin classes following WordPress
 * naming conventions and PSR-4 style directory structure.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Autoloader class.
 *
 * This class handles the automatic loading of all plugin classes
 * based on their namespace and class name. It follows WordPress
 * coding standards while maintaining a clean directory structure.
 *
 * @since 1.0.0
 */
class CFWC_Autoloader {

	/**
	 * Path to the includes directory.
	 *
	 * @var string
	 */
	private $include_path;

	/**
	 * Namespace prefix for autoloaded classes.
	 *
	 * @var string
	 */
	private $namespace_prefix = 'CFWC\\';

	/**
	 * Constructor.
	 *
	 * Sets up the include path and registers the autoloader.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->include_path = CFWC_PLUGIN_DIR . 'includes/';

		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Autoload callback.
	 *
	 * This method is called by PHP's autoload mechanism when a class
	 * is referenced but not yet loaded. It converts the class name to
	 * a file path and includes the file if it exists.
	 *
	 * @since 1.0.0
	 * @param string $class_name The fully-qualified class name to load.
	 * @return void
	 */
	public function autoload( $class_name ) {
		// Only handle our namespace.
		if ( 0 !== strpos( $class_name, $this->namespace_prefix ) ) {
			return;
		}

		// Remove namespace prefix.
		$relative_class = substr( $class_name, strlen( $this->namespace_prefix ) );

		// Convert to file path following WordPress conventions.
		$file = $this->get_file_path( $relative_class );

		// Include the file if it exists.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Convert class name to file path.
	 *
	 * Converts a class name to a WordPress-style file path.
	 * For example: CFW\Admin\Settings becomes includes/admin/class-cfwc-settings.php
	 *
	 * @since 1.0.0
	 * @param string $relative_class The class name without namespace prefix.
	 * @return string The full file path for the class.
	 */
	private function get_file_path( $relative_class ) {
		// Split by namespace separator.
		$parts = explode( '\\', $relative_class );

		// Get the class name (last part).
		$class_name = array_pop( $parts );

		// Convert class name to filename (WordPress style).
		$filename = 'class-cfwc-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

		// Build directory path.
		$directory = '';
		if ( ! empty( $parts ) ) {
			$directory = strtolower( implode( '/', $parts ) ) . '/';
		}

		return $this->include_path . $directory . $filename;
	}
}

// Initialize autoloader.
new CFWC_Autoloader();
