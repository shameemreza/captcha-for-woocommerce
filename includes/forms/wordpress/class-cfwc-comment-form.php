<?php
/**
 * WordPress Comment Form Integration.
 *
 * Adds CAPTCHA protection to the WordPress comment form.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\WordPress;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Comment_Form class.
 *
 * Handles CAPTCHA integration with the WordPress comment form.
 *
 * @since 1.0.0
 */
class Comment_Form {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wp_comment';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Render CAPTCHA on comment form.
		add_action( 'comment_form_after_fields', array( $this, 'render_captcha' ) );
		add_action( 'comment_form_logged_in_after', array( $this, 'render_captcha_logged_in' ) );

		// Validate CAPTCHA on comment submission.
		add_filter( 'preprocess_comment', array( $this, 'validate_captcha' ) );
	}

	/**
	 * Render CAPTCHA on the comment form for guests.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_captcha() {
		Plugin::instance()->render( self::FORM_TYPE );
	}

	/**
	 * Render CAPTCHA on the comment form for logged-in users.
	 *
	 * Only renders if logged-in users are not whitelisted.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_captcha_logged_in() {
		$settings = Plugin::instance()->settings();

		// Skip if logged-in users are whitelisted.
		if ( 'yes' === $settings->get( 'whitelist_logged_in' ) ) {
			return;
		}

		Plugin::instance()->render( self::FORM_TYPE );
	}

	/**
	 * Validate CAPTCHA during comment submission.
	 *
	 * @since 1.0.0
	 * @param array $comment_data Comment data array.
	 * @return array Comment data array.
	 */
	public function validate_captcha( $comment_data ) {
		// Skip for pingbacks and trackbacks.
		if ( ! empty( $comment_data['comment_type'] ) &&
			 in_array( $comment_data['comment_type'], array( 'pingback', 'trackback' ), true ) ) {
			return $comment_data;
		}

		$result = Plugin::instance()->verify( self::FORM_TYPE );

		if ( is_wp_error( $result ) ) {
			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Comment Submission Failed', 'captcha-for-woocommerce' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		return $comment_data;
	}
}
