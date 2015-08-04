<?php

if ( ! class_exists( 'ProSites_Helper_ProSite' ) ) {

	class ProSites_Helper_ProSite {

		public static $last_site = false;

		public static function get_site( $blog_id ) {
			global $wpdb;
			self::$last_site = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
			return self::$last_site;
		}

		public static function last_gateway( $blog_id ) {

			// Try to avoid another load
			if( ! empty( self::$last_site ) && self::$last_site->blog_ID = $blog_id ) {
				$site = self::$last_site;
			} else {
				$site = self::get_site( $blog_id );
			}

			if( ! empty( $site ) ) {
				return ProSites_Helper_Gateway::convert_legacy( $site->gateway );
			} else {
				return false;
			}

		}

		public static function get_activation_key( $blog_id ) {
			global $wpdb;
			$bloginfo = get_blog_details( $blog_id );
			return $wpdb->get_var( $wpdb->prepare( "SELECT activation_key FROM $wpdb->signups WHERE domain = %s AND path = %s", $bloginfo->domain, $bloginfo->path ) );
		}

		public static function get_blog_id( $activation_key ) {
			global $wpdb;
			$blog_id = 0;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE activation_key = %d", $activation_key ) );
			if( $row && $row->activation_key == $activation_key ) {
				$blog_id = domain_exists( $row->domain, $row->path, $wpdb->siteid );
				// As a fallback, try the site domain
				if ( empty( $blog_id ) ) {
					$domain  = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM $wpdb->site WHERE id = %d", $wpdb->siteid ) );
					$blog_id = domain_exists( $domain, $row->path, $wpdb->siteid );
				}
			}
			return $blog_id;
		}

		public static function redirect_signup_page() {
			global $pagenow, $psts;
			$show_signup = $psts->get_setting( 'show_signup' );

			if( 'wp-signup.php' == $pagenow && $show_signup ) {
				wp_redirect( $psts->checkout_url() );
				exit();
			}
		}

		public static function get_blog_info( $blog_id ) {
			global $wpdb, $psts;

			$is_recurring      = $psts->is_blog_recurring( $blog_id );
			$trialing = ProSites_Helper_Registration::is_trial( $blog_id );
			$trial_message = '';
			if ( $trialing ) {
				// assuming its recurring
				$trial_message = '<div id="psts-general-error" class="psts-warning">' . __( 'You are still within your trial period. Once your trial finishes your account will be automatically charged.', 'psts' ) . '</div>';
			}
			$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
			$level_id = $psts->get_level( $blog_id );
			$level    = $psts->get_level_setting( $level_id, 'name' );

			$cancel_info_message = '<p class="prosites-cancel-description">' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
			$cancel_label        = __( 'Cancel Your Subscription', 'psts' );
			// CSS class of <a> is important to handle confirmations
			$cancel_info_link = '<p class="prosites-cancel-link"><a class="cancel-prosites-plan button" href="' . wp_nonce_url( $psts->checkout_url( $blog_id ) . '&action=cancel', 'psts-cancel' ) . '" title="' . esc_attr( $cancel_label ) . '">' . esc_html( $cancel_label ) . '</a></p>';

			// Get other information from database
			$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
			$period = false;
			$last_amount = false;
			$last_gateway = false;
			if( $result ) {
				$period = (int) $result->term;
				$last_amount = floatval( $result->amount );
				$last_gateway = $result->gateway;
			}

			$args = apply_filters( 'psts_blog_info_args',
				array(
					'level_id' => apply_filters( 'psts_blog_info_level_id', $level_id, $blog_id ),
					'level' => apply_filters( 'psts_blog_info_level', $level, $blog_id ),
					'expires' => apply_filters( 'psts_blog_info_expires', $end_date, $blog_id ),
					'trial' => apply_filters( 'psts_blog_info_trial', $trial_message, $blog_id ),
					'recurring' => apply_filters( 'psts_blog_info_recurring', $is_recurring, $blog_id ),
					'pending' => apply_filters( 'psts_blog_info_pending', '', $blog_id ),
					'complete_message' => apply_filters( 'psts_blog_info_complete_message', '', $blog_id ),
					'thanks_message' => apply_filters( 'psts_blog_info_thanks_message', '', $blog_id ),
					'visit_site_message' => apply_filters( 'psts_blog_info_thanks_message', '', $blog_id ),
					'cancel' => apply_filters( 'psts_blog_info_cancelled', false, $blog_id ),
					'cancellation_message' => apply_filters( 'psts_blog_info_cancellation_message', '', $blog_id ),
					'period' => apply_filters( 'psts_blog_info_period', $period, $blog_id ),
					// E.g. Visa, Mastercard, PayPal, etc.
					'payment_type' => apply_filters( 'psts_blog_info_payment_type', false, $blog_id ),
					// E.g. last 4-digits (ok to leave empty)
					'payment_reminder' => apply_filters( 'psts_blog_info_payment_reminder', false, $blog_id ),
					// Acceptable: end | start | block
					'payment_reminder_location' => apply_filters( 'psts_blog_info_payment_remind_location', 'end', $blog_id ),
					// If its a credit card, the following can be used for expiry information
					'payment_expire_month' => apply_filters( 'psts_blog_info_payment_expire_month', false, $blog_id ),
					'payment_expire_year' => apply_filters( 'psts_blog_info_payment_expire_year', false, $blog_id ),
					'last_payment_date' => apply_filters( 'psts_blog_info_last_payment_date', false, $blog_id ),
					'last_payment_amount' => apply_filters( 'psts_blog_info_last_payment_amount', $last_amount, $blog_id),
					'last_payment_gateway' => apply_filters( 'psts_blog_info_last_payment_gateway', $last_gateway, $blog_id),
					'next_payment_date' => apply_filters( 'psts_blog_info_next_payment_date', false, $blog_id ),
					// Information about cancelling
					'cancel_info' => apply_filters( 'psts_blog_info_cancel_message', $cancel_info_message, $blog_id ),
					// Best not to change this one...
					'cancel_info_link' => $cancel_info_link,
					'receipt_form' => $psts->receipt_form( $blog_id ),
					'all_fields' => apply_filters( 'psts_blog_info_all_fields', true, $blog_id ),
				),
				$blog_id
			);

			return $args;
		}

		/**
		 * Sets meta for a ProSite
		 *
		 * @param array $meta
		 * @param int $blog_id
		 *
		 * @return bool
		 */
		public static function update_prosite_meta( $blog_id = 0, $meta = array() ) {

			if ( false === $meta || empty( $blog_id ) ) {
				return false;
			}
			global $wpdb;

			$updated = $wpdb->update(
				$wpdb->base_prefix . 'pro_sites',
				array(
					'meta' => maybe_serialize( $meta ),
				),
				array(
					'blog_ID' => $blog_id
				)
			);

			return $updated;
		}

		/**
		 * Fetches meta for a ProSite
		 *
		 * @param int $blog_id
		 *
		 * @return bool|mixed|string
		 */
		public static function get_prosite_meta( $blog_id = 0 ) {
			if ( empty( $blog_id ) ) {
				return false;
			}

			global $wpdb;
			$meta = false;
			$result = $wpdb->get_row( $wpdb->prepare( "SELECT meta FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %s", $blog_id ) );
			if ( ! empty( $result ) ) {
				$meta = maybe_unserialize( $result->meta );
			}

			return $meta;
		}

		public static function start_session() {
			//Start Session if not there already, required for sign up.
			if ( ! session_id() ) {
				session_start();
			}
		}

		/**
		 *  Get the AJAX url.
		 *
		 *  Fixes potential issue with Domain Mapping plugin.
		 */
		public static function admin_ajax_url() {
			$path = "admin-ajax.php";
			$scheme = ( is_ssl() || force_ssl_admin() ? 'https' : 'http' );

			if( class_exists( 'domain_map') ) {
				global $dm_map;
				return $dm_map->domain_mapping_admin_url( admin_url( $path, $scheme ), '/', false );
			} else{
				return admin_url( $path, $scheme );
			}

		}

		/**
		 * Update pricing level order, Updates Pro site settings
		 *
		 * @param $levels
		 *
		 */
		public static function update_level_order( $levels ) {
			$data         = array();
			$data['psts'] = array();

			$pricing_levels_order = array();

			foreach ( $levels as $level_code => $level ) {
				$pricing_levels_order[] = $level_code;
			}

			//Get and update psts settings
			// get settings
			$old_settings                         = get_site_option( 'psts_settings' );
			$data['psts']['pricing_levels_order'] = implode(',', $pricing_levels_order );
			$settings                             = array_merge( $old_settings, apply_filters( 'psts_settings_filter', $data['psts'], 'pricing_table' ) );
			update_site_option( 'psts_settings', $settings );
		}


	}
}