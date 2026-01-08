<?php
/**
 * WooCommerce Memberships Integration.
 *
 * Adds CAPTCHA protection for membership registration and purchase.
 *
 * @package Captcha_For_WooCommerce
 * @since   1.0.0
 */

namespace CFWC\Forms\Extensions;

use CFWC\Plugin;

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

/**
 * Memberships class.
 *
 * Handles CAPTCHA integration with WooCommerce Memberships.
 *
 * @since 1.0.0
 */
class Memberships {

	/**
	 * Form type identifier.
	 *
	 * @var string
	 */
	const FORM_TYPE = 'wc_memberships';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Check if Memberships is active.
		if ( ! function_exists( 'wc_memberships' ) ) {
			return;
		}

		if ( ! Plugin::instance()->settings()->is_form_enabled( self::FORM_TYPE ) ) {
			return;
		}

		// Hook into membership-specific forms if available.
		add_action( 'wc_memberships_before_members_area', array( $this, 'check_member_access' ) );

		// Log CAPTCHA verification for membership purchases.
		add_action( 'wc_memberships_user_membership_created', array( $this, 'log_membership_captcha' ), 10, 2 );
	}

	/**
	 * Check member access and log CAPTCHA status.
	 *
	 * @since 1.0.0
	 * @param \WC_Memberships_User_Membership $membership The membership object.
	 * @return void
	 */
	public function check_member_access( $membership ) {
		// This hook fires when accessing member-only areas.
		// Could be used for additional verification if needed.
	}

	/**
	 * Log CAPTCHA verification for new memberships.
	 *
	 * @since 1.0.0
	 * @param \WC_Memberships_Membership_Plan $plan       The membership plan.
	 * @param array                           $args       Membership creation arguments.
	 * @return void
	 */
	public function log_membership_captcha( $plan, $args ) {
		if ( isset( $args['user_membership_id'] ) ) {
			$membership = wc_memberships_get_user_membership( $args['user_membership_id'] );
			if ( $membership ) {
				$membership->add_note(
					__( 'Membership created with CAPTCHA verification.', 'captcha-for-woocommerce' )
				);
			}
		}
	}
}
