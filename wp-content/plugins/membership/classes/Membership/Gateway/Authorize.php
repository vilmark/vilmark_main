<?php

// +----------------------------------------------------------------------+
// | Copyright Incsub (http://incsub.com/)                                |
// +----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License, version 2, as  |
// | published by the Free Software Foundation.                           |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to the Free Software          |
// | Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,               |
// | MA 02110-1301 USA                                                    |
// +----------------------------------------------------------------------+

/**
 * Authorize.Net gateway class.
 *
 * @since    3.5
 *
 * @category Membership
 * @package  Gateway
 */
class Membership_Gateway_Authorize extends Membership_Gateway {

	const MODE_SANDBOX = 'sandbox';
	const MODE_LIVE    = 'live';

	const TRANSACTION_TYPE_AUTHORIZED        = 1;
	const TRANSACTION_TYPE_CAPTURED          = 2;
	const TRANSACTION_TYPE_RECURRING         = 3;
	const TRANSACTION_TYPE_VOIDED            = 4;
	const TRANSACTION_TYPE_CANCELED_RECURING = 5;
	const TRANSACTION_TYPE_CIM_AUTHORIZED    = 6;

	/**
	 * Gateway id.
	 *
	 * @since  3.5
	 *
	 * @access public
	 * @var string
	 */
	public $gateway = 'authorize';

	/**
	 * Gateway title.
	 *
	 * @since  3.5
	 *
	 * @access public
	 * @var string
	 */
	public $title = 'Authorize.Net';

	/**
	 * Determines whether gateway has payment form or not.
	 *
	 * @since  3.5
	 *
	 * @access public
	 * @var boolean
	 */
	public $haspaymentform = true;

	/**
	 * Array of payment result.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @var array
	 */
	protected $_payment_result;

	/**
	 * Current member.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @var Membership_Model_Member
	 */
	protected $_member;

	/**
	 * Current subscription.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @var Membership_Model_Subscription
	 */
	protected $_subscription;

	/**
	 * The array of transaction processed during payment.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @var array
	 */
	protected $_transactions;

	/**
	 * User's Authorize.net CIM profile ID.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @var int
	 */
	protected $_cim_profile_id;

	/**
	 * User's Authorize.net CIM payment profile ID.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @var int
	 */
	protected $_cim_payment_profile_id;

	/**
	 * Constructor.
	 *
	 * @since  3.5
	 *
	 * @access public
	 */
	public function __construct() {
		parent::__construct();

		$this->_add_action( 'M_gateways_settings_' . $this->gateway, 'render_settings' );
		$this->_add_action( 'membership_purchase_button', 'render_subscribe_button', 10, 3 );
		$this->_add_action( 'membership_payment_form_' . $this->gateway, 'render_payment_form', 10, 3 );
		$this->_add_action( 'membership_expire_subscription', 'cancel_subscription_transactions', 10, 3 );
		$this->_add_action( 'membership_drop_subscription', 'drop_subscription_transactions', 10, 3 );
		$this->_add_action( 'membership_move_subscription', 'capture_next_transaction', 10, 6 );
		$this->_add_filter( 'membership_unsubscribe_subscription', 'process_unsubscribe_subscription', 10, 3 );

		$this->_add_action( 'wp_enqueue_scripts', 'enqueue_scripts' );
		$this->_add_action( 'wp_login', 'propagate_ssl_cookie', 10, 2 );
		$this->_add_action( 'wpmu_delete_user', 'save_cim_profile_id' );
		$this->_add_action( 'delete_user', 'save_cim_profile_id' );
		$this->_add_action( 'deleted_user', 'delete_cim_profile' );

		$this->_add_ajax_action( 'processpurchase_' . $this->gateway, 'process_purchase', true, true );
		$this->_add_ajax_action( 'purchaseform', 'render_popover_payment_form' );
	}

	/**
	 * Saves Authorize.net CIM profile ID before delete an user.
	 *
	 * @since  3.5
	 * @action delete_user
	 *
	 * @access public
	 *
	 * @param int $user_id User's ID which will be deleted.
	 */
	public function save_cim_profile_id( $user_id ) {
		$this->_cim_profile_id = get_user_meta( $user_id, 'authorize_cim_id', true );
	}

	/**
	 * Voids all authorized payements, delete subscriptions and removes
	 * Authorize.net CIM profile when an user is deleted. And finally deletes
	 * transaction log.
	 *
	 * @since  3.5
	 * @action deleted_user
	 *
	 * @access public
	 *
	 * @param int $user_id The ID of an user which was deleted.
	 */
	public function delete_cim_profile( $user_id ) {
		$this->cancel_subscription_transactions( false, $user_id );
		$this->db->delete( MEMBERSHIP_TABLE_SUBSCRIPTION_TRANSACTION, array( 'transaction_user_ID' => $user_id ), array( '%d' ) );

		if ( $this->_cim_profile_id ) {
			$this->_get_cim()->deleteCustomerProfile( $this->_cim_profile_id );
		}
	}

	/**
	 * Voids authorized only payments and cancels active recuring subscriptions
	 * for specific or all subscriptions.
	 *
	 * @since  3.5
	 * @action membership_expire_subscription 10 2
	 *
	 * @access public
	 *
	 * @param int $sub_id  The subscription ID.
	 * @param mixed $from_level
	 * @param int $user_id The user ID.
	 */
	public function cancel_subscription_transactions( $sub_id, $from_level, $user_id ) {
		$transactions = $this->db->get_results( sprintf(
			'SELECT transaction_ID AS record_id, transaction_paypal_ID AS id, transaction_status AS status FROM %s WHERE transaction_user_ID = %d AND transaction_status IN (%d, %d, %d)%s',
			MEMBERSHIP_TABLE_SUBSCRIPTION_TRANSACTION,
			$user_id,
			self::TRANSACTION_TYPE_AUTHORIZED,
			self::TRANSACTION_TYPE_RECURRING,
			self::TRANSACTION_TYPE_CIM_AUTHORIZED,
			! empty( $sub_id ) ? ' AND transaction_subscription_ID = ' . $sub_id : ''
		) );

		foreach ( $transactions as $transaction ) {
			$status = false;
			if ( $transaction->status == self::TRANSACTION_TYPE_AUTHORIZED ) {
				$this->_get_aim( false, false )->void( $transaction->id );
				$status = self::TRANSACTION_TYPE_VOIDED;
			} elseif ( $transaction->status == self::TRANSACTION_TYPE_RECURRING ) {
				$this->_get_arb()->cancelSubscription( $transaction->id );
				$status = self::TRANSACTION_TYPE_CANCELED_RECURING;
			} elseif ( $transaction->status == self::TRANSACTION_TYPE_CIM_AUTHORIZED ) {
				if ( ! $this->_cim_profile_id ) {
					$this->_cim_profile_id = get_user_meta( $user_id, 'authorize_cim_id', true );
				}

				$cim_transaction          = $this->_get_cim_transaction();
				$cim_transaction->transId = $transaction->id;
				$this->_get_cim()->createCustomerProfileTransaction( 'Void', $cim_transaction );
				$status = self::TRANSACTION_TYPE_VOIDED;
			}

			if ( $status && $sub_id ) {
				$this->db->update(
					MEMBERSHIP_TABLE_SUBSCRIPTION_TRANSACTION,
					array( 'transaction_status' => $status ),
					array( 'transaction_ID' => $transaction->record_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Cancels transactions when subscription is dropped.
	 *
	 * @since  3.5
	 * @action membership_drop_subscription 10 3
	 *
	 * @access public
	 *
	 * @param int $fromsub_id   The subscription ID to drop.
	 * @param int $fromlevel_id The subscription's level ID.
	 * @param int $user_id      The member ID.
	 */
	public function drop_subscription_transactions( $fromsub_id, $fromlevel_id, $user_id ) {
		$this->cancel_subscription_transactions( $fromsub_id, $user_id );
	}

	/**
	 * Captures next transaction accordingly to subscription settings.
	 *
	 * @since  3.5
	 * @action membership_move_subscription 10 6
	 *
	 * @access public
	 */
	public function capture_next_transaction( $fromsub_id, $fromlevel_id, $tosub_id, $tolevel_id, $to_order, $user_id ) {
		// don't do anything if subscription has been changed
		if ( $fromsub_id != $tosub_id ) {
			return;
		}

		// fetch next authorized transaction
		$transactions = $this->db->get_results( sprintf( '
			SELECT transaction_ID AS record_id, transaction_paypal_ID AS id, transaction_status AS status, transaction_total_amount/100 AS amount, transaction_stamp AS stamp
			  FROM %s
			 WHERE transaction_user_ID = %d
			   AND transaction_subscription_ID = %d
			   AND transaction_status IN (%d, %d)
			 ORDER BY transaction_ID ASC
			 LIMIT 1',
			MEMBERSHIP_TABLE_SUBSCRIPTION_TRANSACTION,
			$user_id,
			$tosub_id,
			self::TRANSACTION_TYPE_AUTHORIZED,
			self::TRANSACTION_TYPE_CIM_AUTHORIZED
		) );

		foreach ( $transactions as $transaction ) {
			// don't capture future transactions
			if ( $transaction->stamp > time() ) {
				continue;
			}

			// capture transaction
			$status = false;
			if ( $transaction->status == self::TRANSACTION_TYPE_AUTHORIZED ) {
				$this->_get_aim( false, false )->priorAuthCapture( $transaction->id, $transaction->amount );
				$status = self::TRANSACTION_TYPE_CAPTURED;
			} elseif ( $transaction->status == self::TRANSACTION_TYPE_CIM_AUTHORIZED ) {
				if ( ! $this->_cim_profile_id ) {
					$this->_cim_profile_id = get_user_meta( $user_id, 'authorize_cim_id', true );
				}

				$cim_transaction          = $this->_get_cim_transaction();
				$cim_transaction->transId = $transaction->id;
				$cim_transaction->amount  = $transaction->amount;
				$this->_get_cim()->createCustomerProfileTransaction( 'PriorAuthCapture', $cim_transaction );
				$status = self::TRANSACTION_TYPE_CAPTURED;
			}

			// update transaction status
			if ( $status && $tosub_id ) {
				$this->db->update(
					MEMBERSHIP_TABLE_SUBSCRIPTION_TRANSACTION,
					array( 'transaction_status' => $status ),
					array( 'transaction_ID' => $transaction->record_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Cancels subscription transactions if the subscription has to be expired.
	 *
	 * @since  3.5
	 * @filter membership_unsubscribe_subscription 10 3
	 *
	 * @access public
	 *
	 * @param boolean $expire  Determines whether to mark a subscription to expire or not.
	 * @param int     $sub_id  Current subscription to unsubscribe from.
	 * @param int     $user_id The user ID.
	 *
	 * @return boolean Incoming value for $expire variable.
	 */
	public function process_unsubscribe_subscription( $expire, $sub_id, $user_id ) {
		if ( $expire ) {
			if ( get_current_user_id() == $user_id ) {
				$this->_member = Membership_Plugin::factory()->get_member( $user_id );
				if ( $this->_member->has_subscription() && $this->_member->on_sub( $sub_id ) ) {
					$this->cancel_subscription_transactions( $sub_id, $user_id );
					if ( defined( 'MEMBERSHIP_DEACTIVATE_USER_ON_CANCELATION' ) && filter_var( MEMBERSHIP_DEACTIVATE_USER_ON_CANCELATION, FILTER_VALIDATE_BOOLEAN ) ) {
						$this->_member->deactivate();
					}
				}
			}
		}

		return $expire;
	}

	/**
	 * Propagates SSL cookies when user logs in.
	 *
	 * @since  3.5
	 * @action wp_login 10 2
	 *
	 * @access public
	 *
	 * @param type    $login
	 * @param WP_User $user
	 */
	public function propagate_ssl_cookie( $login, WP_User $user ) {
		if ( ! is_ssl() ) {
			wp_set_auth_cookie( $user->ID, true, true );
		}
	}

	/**
	 * Renders gateway settings page.
	 *
	 * @since  3.5
	 * @action M_gateways_settings_authorize
	 *
	 * @access public
	 */
	public function render_settings() {
		$template = new Membership_Render_Gateway_Authorize_Settings();

		$template->api_user = $this->_get_option( 'api_user' );
		$template->api_key  = $this->_get_option( 'api_key' );

		$template->pay_button_label = $this->_get_option( 'pay_button_label' );

		$template->mode  = $this->_get_option( 'mode', self::MODE_SANDBOX );
		$template->modes = array(
			self::MODE_SANDBOX => __( 'Sandbox', 'membership' ),
			self::MODE_LIVE    => __( 'Live', 'membership' ),
		);

		$template->render();
	}

	/**
	 * Updates gateway options.
	 *
	 * @since  3.5
	 *
	 * @access public
	 * @return boolean TRUE on success, otherwise FALSE.
	 */
	public function update() {
		$method = defined( 'MEMBERSHIP_GLOBAL_TABLES' ) && filter_var( MEMBERSHIP_GLOBAL_TABLES, FILTER_VALIDATE_BOOLEAN )
			? 'update_site_option'
			: 'update_option';

		$mode = filter_input( INPUT_POST, 'mode' );
		if ( in_array( $mode, array( self::MODE_LIVE, self::MODE_SANDBOX ) ) ) {
			$method( $this->gateway . "_mode", $mode );
		}

		foreach ( array( 'api_user', 'api_key', 'pay_button_label' ) as $option ) {
			$key = "{$this->gateway}_{$option}";
			if ( isset( $_POST[$option] ) ) {
				$method( $key, filter_input( INPUT_POST, $option ) );
			}
		}

		return true;
	}

	/**
	 * Renders payment button.
	 *
	 * @since  3.5
	 * @action membership_purchase_button 10 3
	 *
	 * @access public
	 * @global array                        $M_options    The array of membership options.
	 *
	 * @param Membership_Model_Subscription $subscription New subscription.
	 * @param array                         $pricing      The pricing information.
	 * @param int                           $user_id      The current user id.
	 */
	public function render_subscribe_button( $subscription, $pricing, $user_id ) {
		$this->_render_button( esc_attr__( 'Pay Now', 'membership' ), $subscription, $user_id, filter_input( INPUT_GET, 'from_subscription', FILTER_VALIDATE_INT ) );
	}

	/**
	 * Displays upgrade subscription button.
	 *
	 * @since  3.5
	 *
	 * @access public
	 *
	 * @param Membership_Model_Subscription $subscription New subscription.
	 * @param array                         $pricing      The pricing information.
	 * @param int                           $user_id      The current user id.
	 * @param type                          $fromsub_id   From subscription ID.
	 */
	public function display_upgrade_button( $subscription, $pricing, $user_id, $fromsub_id = false ) {
		$this->_render_button( esc_attr__( 'Upgrade', 'membership' ), $subscription, $user_id, $fromsub_id );
	}

	/**
	 * Displays upgrade subscription button.
	 *
	 * @since  3.5
	 *
	 * @access public
	 *
	 * @param Membership_Model_Subscription $subscription New subscription.
	 * @param array                         $pricing      The pricing information.
	 * @param int                           $user_id      The current user id.
	 * @param type                          $fromsub_id   From subscription ID.
	 */
	public function display_upgrade_from_free_button( $subscription, $pricing, $user_id, $fromsub_id = false ) {
		$this->display_upgrade_button( $subscription, $pricing, $user_id, $fromsub_id );
	}

	/**
	 * Displays unsubscribe button.
	 *
	 * @access public
	 *
	 * @param type $subscription
	 * @param type $pricing
	 * @param type $user_id
	 */
	public function display_cancel_button( $subscription, $pricing, $user_id ) {
		?>
		<form class="unsubbutton" method="post">
		<?php wp_nonce_field( 'cancel-sub_' . $subscription->sub_id() ) ?>
		<input type="hidden" name="action" value="unsubscribe">
		<input type="hidden" name="gateway" value="<?php echo esc_attr( $this->gateway ) ?>">
		<input type="hidden" name="subscription" value="<?php echo esc_attr( $subscription->sub_id() ) ?>">
		<input type="hidden" name="user" value="<?php echo esc_attr( $user_id ) ?>">
		<input type="submit" value="<?php esc_attr_e( 'Unsubscribe', 'membership' ) ?>" class="button <?php echo apply_filters( 'membership_subscription_button_color', '' ) ?>">
		</form><?php
	}

	/**
	 * Renders gateway button.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @global array                        $M_options    The array of membership options.
	 *
	 * @param string                        $label        The button label.
	 * @param Membership_Model_Subscription $subscription New subscription.
	 * @param int                           $user_id      The current user id.
	 * @param type                          $fromsub_id   From subscription ID.
	 */
	protected function _render_button( $label, $subscription, $user_id, $fromsub_id = false ) {
		global $M_options;

		$actionurl = isset( $M_options['registration_page'] ) ? str_replace( 'http:', 'https:', get_permalink( $M_options['registration_page'] ) ) : '';
		if ( empty( $actionurl ) ) {
			$actionurl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}

		$template = new Membership_Render_Gateway_Authorize_Button();

		$template->gateway              = $this->gateway;
		$template->subscription_id      = $subscription->id;
		$template->from_subscription_id = (int) $fromsub_id;
		$template->user_id              = $user_id;

		$pay_button_label       = trim( $this->_get_option( 'pay_button_label' ) );
		$template->button_label = ! empty( $pay_button_label ) ? $pay_button_label : $label;

		$actionurl           = esc_url( add_query_arg( array( 'action' => 'registeruser', 'subscription' => $subscription->id ), $actionurl ) );
		$template->actionurl = $actionurl;

		$coupon                = membership_get_current_coupon();
		$template->coupon_code = ! empty( $coupon ) ? $coupon->get_coupon_code() : '';

		$template->render();
	}

	/**
	 * Renders payment form.
	 *
	 * @since  3.5
	 * @action membership_payment_form_authorize
	 *
	 * @access public
	 *
	 * @param Membership_Model_Subscription $subscription The current subscription to subscribe to.
	 * @param array                         $pricing      The pricing information.
	 * @param int                           $user_id      The current user id.
	 */
	public function render_payment_form( $subscription, $pricing, $user_id ) {
		// check errors
		$error = false;

		// check API user login and transaction key
		$api_u = trim( $this->_get_option( 'api_user' ) );
		$api_k = trim( $this->_get_option( 'api_key' ) );
		if ( empty( $api_u ) || empty( $api_k ) ) {
			$error = __( 'This payment gateway has not been configured. Your transaction will not be processed.', 'membership' );
		}

		// fetch CIM profile
		$cim_profiles = array();
		// CIM can't handle recurring billing
		if ( ! in_array( 'serial', wp_list_pluck( $pricing, 'type' ) ) ) {
			$cim_profile_id = get_user_meta( $user_id, 'authorize_cim_id', true );
			if ( $cim_profile_id ) {
				$response = $this->_get_cim()->getCustomerProfile( $cim_profile_id );
				if ( $response->isOk() ) {
					$cim_profiles = json_decode( json_encode( $response->xml->profile ), true );
					$cim_profiles = is_array( $cim_profiles ) && ! empty( $cim_profiles['paymentProfiles'] ) && is_array( $cim_profiles['paymentProfiles'] )
						? $cim_profiles['paymentProfiles']
						: array();
				}
			}
		}

		// fetch coupon information
		$coupon = membership_get_current_coupon();
		$coupon = ! empty( $coupon ) ? $coupon->get_coupon_code() : '';

		// initialize and render form template
		$template = new Membership_Render_Gateway_Authorize_Form();

		$template->is_popup          = self::is_popup();
		$template->error             = $error;
		$template->coupon            = $coupon;
		$template->subscription_id   = $subscription->id;
		$template->subscription_name = $subscription->sub_name();
		$template->gateway           = $this->gateway;
		$template->user_id           = $user_id;
		$template->cim_profiles      = $cim_profiles;
		$template->from_subscription = filter_input( INPUT_POST, 'from_subscription', FILTER_VALIDATE_INT );

		$template->render();
	}

	/**
	 * Renders popover payment form.
	 *
	 * @since  3.5
	 * @action wp_ajax_purchaseform
	 *
	 * @access public
	 * @global WP_Scripts $wp_scripts
	 */
	public function render_popover_payment_form() {
		if ( filter_input( INPUT_POST, 'gateway' ) != $this->gateway ) {
			return;
		}

		$subscription = Membership_Plugin::factory()->get_subscription( filter_input( INPUT_POST, 'subscription', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) );
		$user_id      = filter_input( INPUT_POST, 'user', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1, 'default' => get_current_user_id() ) ) );
		do_action( 'membership_payment_form_' . $this->gateway, $subscription, $subscription->get_pricingarray(), $user_id );

		exit;
	}

	/**
	 * Processes purchase action.
	 *
	 * @since  3.5
	 * @action wp_ajax_nopriv_processpurchase_authorize
	 * @action wp_ajax_processpurchase_authorize
	 *
	 * @access public
	 */
	public function process_purchase() {
		global $M_options;
		if ( empty( $M_options['paymentcurrency'] ) ) {
			$M_options['paymentcurrency'] = 'USD';
		}

		if ( ! is_ssl() ) {
			wp_die( __( 'You must use HTTPS in order to do this', 'membership' ) );
			exit;
		}

		// fetch subscription and pricing
		$sub_id              = filter_input( INPUT_POST, 'subscription_id', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		$this->_subscription = Membership_Plugin::factory()->get_subscription( $sub_id );
		$pricing             = $this->_subscription->get_pricingarray();
		if ( ! $pricing ) {
			status_header( 404 );
			exit;
		}

		// apply a coupon
		$coupon = membership_get_current_coupon();
		if ( $coupon && $coupon->valid_for_subscription( $this->_subscription->id ) ) {
			$pricing = $coupon->apply_coupon_pricing( $pricing );
		}

		// fetch member
		$user_id       = is_user_logged_in() ? get_current_user_id() : $_POST['user_id'];
		$this->_member = Membership_Plugin::factory()->get_member( $user_id );

		// fetch CIM user and payment profiles info
		// pay attention that CIM can't handle recurring transaction, so we need
		// to use standard ARB aproach and full cards details
		$has_serial = in_array( 'serial', wp_list_pluck( $pricing, 'type' ) );
		if ( ! $has_serial ) {
			$this->_cim_payment_profile_id = trim( filter_input( INPUT_POST, 'profile' ) );
			if ( ! empty( $this->_cim_payment_profile_id ) ) {
				$this->_cim_profile_id = get_user_meta( $this->_member->ID, 'authorize_cim_id', true );
				if ( $this->_cim_profile_id ) {
					$response = $this->_get_cim()->getCustomerPaymentProfile( $this->_cim_profile_id, $this->_cim_payment_profile_id );
					if ( $response->isError() ) {
						$this->_cim_payment_profile_id = false;
					}
				}
			}
		}

		// process payments
		$first_payment         = false;
		$started               = new DateTime();
		$this->_payment_result = array( 'status' => '', 'errors' => array() );
		$this->_transactions   = array();

		for ( $i = 0, $count = count( $pricing ); $i < $count; $i ++ ) {
			if ( $first_payment === false && $pricing[$i]['amount'] > 0 ) {
				$first_payment = $pricing[$i]['amount'];
			}

			switch ( $pricing[$i]['type'] ) {
				case 'finite':
					//Using AIM for onetime payment
					$this->_transactions[] = $this->_process_nonserial_purchase( $pricing[$i], $started );
					/*//Call ARB with only one recurrency for each subscription level.
					$this->_transactions[] = $this->_process_serial_purchase( $pricing[$i], $started, 1, $unit = 'months', 12 );
					$interval              = self::_get_period_interval_in_date_format( $pricing[$i]['unit'] );
					$started->modify( sprintf( '+%d %s', $pricing[$i]['period'], $interval ) );*/
					break;
				case 'indefinite':
					$this->_transactions[] = $this->_process_nonserial_purchase( $pricing[$i], $started );
					break 2;
				case 'serial':
					//Call ARB with no end date (an ongoing subscription).
					$this->_transactions[] = $this->_process_serial_purchase( $pricing[$i], $started, 9999 );
					break 2;
			}

			if ( $this->_payment_result['status'] == 'error' ) {
				$this->_rollback_transactions();
				break;
			}
		}

		if ( $this->_payment_result['status'] == 'success' ) {
			// create member subscription
			if ( $this->_member->has_subscription() ) {
				$from_sub_id = filter_input( INPUT_POST, 'from_subscription', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
				if ( $this->_member->on_sub( $from_sub_id ) ) {
					$this->_member->drop_subscription( $from_sub_id );
				}

				if ( $this->_member->on_sub( $sub_id ) ) {
					$this->_member->drop_subscription( $sub_id );
				}
			}
			$this->_member->create_subscription( $sub_id, $this->gateway );

			// create CIM profile it is not exists, otherwise update it if new card was added
			$this->_cim_profile_id = get_user_meta( $this->_member->ID, 'authorize_cim_id', true );
			if ( ! $this->_cim_profile_id ) {
				$this->_create_cim_profile();
			} elseif ( ! $has_serial && empty( $this->_cim_payment_profile_id ) ) {
				$this->_update_cim_profile();
			}

			// process transactions
			$this->_commit_transactions();

			if ( $first_payment ) {
				do_action( 'membership_authorizenet_payment_processed', $this->_member->ID, $sub_id );
				do_action( 'membership_payment_processed', $this->_member->ID, $sub_id, $first_payment, $M_options['paymentcurrency'], $this->_transactions[0]['transaction'] );
			}

			// process response message and redirect
			if ( self::is_popup() && ! empty( $M_options['registrationcompleted_message'] ) ) {
				$html = '<div class="header" style="width: 750px"><h1>';
				$html .= sprintf( __( 'Sign up for %s completed', 'membership' ), $this->_subscription->sub_name() );
				$html .= '</h1></div><div class="fullwidth">';
				$html .= stripslashes( wpautop( $M_options['registrationcompleted_message'] ) );
				$html .= '</div>';

				$this->_payment_result['redirect'] = 'no';
				$this->_payment_result['message']  = $html;
			} else {
				$this->_payment_result['message']  = '';
				$this->_payment_result['redirect'] = strpos( home_url(), 'https://' ) === 0
					? str_replace( 'https:', 'http:', M_get_registrationcompleted_permalink() )
					: M_get_registrationcompleted_permalink();
			}
		}

		echo json_encode( $this->_payment_result );
		exit;
	}

	/**
	 * Processes non serial level purchase.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 *
	 * @param array    $price The array with current price information.
	 * @param DateTime $date  The date when to process this transaction.
	 *
	 * @return array Returns transaction information on success, otherwise NULL.
	 */
	protected function _process_nonserial_purchase( $price, $date, $authOnly = false ) {
		if ( $price['amount'] == 0 ) {
			$this->_payment_result['status'] = 'success';

			return null;
		}

		$success = $transaction_id = $method = $error = false;
		$amount  = number_format( $price['amount'], 2, '.', '' );
		if ( ! empty( $this->_cim_profile_id ) && ! empty( $this->_cim_payment_profile_id ) ) {
			$transaction         = $this->_get_cim_transaction();
			$transaction->amount = $amount;

			$lineItem = new AuthorizeNetLineItem();
		    $lineItem->itemId = $this->_subscription->sub_id();
		    $lineItem->name = $this->_subscription->sub_name();
		    $lineItem->description = $this->_subscription->sub_description();
		    $lineItem->quantity = 1;
		    $lineItem->unitPrice = number_format( $price['amount'], 2, '.', '' );

			$transaction->lineItems = array( $lineItem );


			$response = $this->_get_cim()->createCustomerProfileTransaction( 'AuthOnly', $transaction );
			if ( $response->isOk() ) {
				$success        = true;
				$method         = 'cim';
				$transaction_id = $response->getTransactionResponse()->transaction_id;
			} else {
				$error = $response->getMessageText();
			}
		} else {

			// Uncomment this to include line items in user's receipt and adjust taxable flag accordingly
			//$taxable = false;
			//$this->_get_aim()->addLineItem(
			//	$this->_subscription->sub_id(),
			//	$this->_subscription->sub_name(),
			//	$this->_subscription->sub_description(),
			//	1,
			//	number_format( $price['amount'], 2, '.', '' ),
			//	$taxable
			//);

			$response = $this->_get_aim()->authorizeOnly( $amount );

			if ( $response->approved ) {
				$success        = true;
				$transaction_id = $response->transaction_id;
				$method         = 'aim';
			} elseif ( $response->error ) {
				$error = $response->response_reason_text;
			}
		}

		if ( $success ) {
			$this->_payment_result['status'] = 'success';

			return array(
				'method'      => $method,
				'transaction' => $transaction_id,
				'date'        => $date->format( 'U' ),
				'amount'      => $amount,
			);
		}

		$this->_payment_result['status']   = 'error';
		$this->_payment_result['errors'][] = $error;

		return null;
	}

	function process_finite_payment(){
		$aim = new AuthorizeNetAIM();
	}

	/**
	 * Processes serial level purchase.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @global array   $M_options         The array of plugin options.
	 *
	 * @param array    $price             The array with current price information.
	 * @param DateTime $date              The date when to process this transaction.
	 * @param int      $total_occurencies The number of billing occurrences or payments for the subscription.
	 *                                    To submit a subscription with no end date, this field must be submitted with a value of 9999
	 *
	 * @return array Returns transaction information on success, otherwise NULL.
	 */
	protected function _process_serial_purchase( $price, $date, $total_occurencies, $unit = null, $interval_length = null ) {
		if ( $price['amount'] == 0 ) {
			$this->_payment_result['status'] = 'success';

			return null;
		}

		// initialize AIM transaction to check CC
		if ( count( array_filter( $this->_transactions ) ) == 0 ) {
			$authOnly = true;
			$transaction = $this->_process_nonserial_purchase( $price, $date, $authOnly );
			if ( is_null( $transaction ) ) {
				return null;
			}

			//$transaction['void'] = true;  // best not to uncomment this line unless you know what you are doing.

			$this->_transactions[] = $transaction;

			// WARNING: By removing the lines below you will end up charging users twice on the same day.
			// Please DO NOT remove these unless you are absolutely sure you know what you are doing, see line 881 and lines 986 - 1006
			$interval              = self::_get_period_interval_in_date_format( $price['unit'] );
			$date->modify( sprintf( '+%d %s', $price['period'], $interval ) );
		}

		$amount = number_format( $price['amount'], 2, '.', '' );

		$level = Membership_Plugin::factory()->get_level( $price['level_id'] );
		$name  = substr( sprintf(
			'%s / %s',
			$level->level_title(),
			$this->_subscription->sub_name()
		), 0, 50 );

		$subscription                   = $this->_get_arb_subscription( $price );
		$subscription->name             = $name;
		$subscription->amount           = $amount;
		$subscription->startDate        = $date->format( 'Y-m-d' );
		$subscription->totalOccurrences = $total_occurencies;

		if ( ! is_null( $unit ) ) {
			$subscription->intervalUnit = $unit;
		}

		if ( ! is_null( $interval_length ) ) {
			$subscription->intervalLength = $interval_length;
		}

		if ( isset( $price['origin'] ) ) {
			// coupon is applied, so we need to add trial period
			$subscription->amount           = $amount = number_format( $price['origin'], 2, '.', '' );
			$subscription->trialAmount      = number_format( $price['amount'], 2, '.', '' );
			$subscription->trialOccurrences = 1;
			$subscription->totalOccurrences = $subscription->totalOccurrences + $subscription->trialOccurrences;
		}

		$arb      = $this->_get_arb();
		$response = $arb->createSubscription( $subscription );
		if ( $response->isOk() ) {
			$this->_payment_result['status'] = 'success';

			return array(
				'method'      => 'arb',
				'transaction' => $response->getSubscriptionId(),
				'date'        => $date->format( 'U' ),
				'amount'      => $amount,
			);
		}

		$this->_payment_result['status']   = 'error';
		$this->_payment_result['errors'][] = $response->getMessageText();

		return null;
	}

	/**
	 * Converts period interval into date modification format.
	 *
	 * @since  3.5
	 *
	 * @static
	 * @access private
	 *
	 * @param string $code Period interval abbriviation.
	 *
	 * @return string Date modification interval.
	 */
	private static function _get_period_interval_in_date_format( $code ) {
		switch ( $code ) {
			case 'w':
				return 'week';
			case 'm':
				return 'month';
			case 'y':
				return 'year';
		}

		return 'day';
	}

	/**
	 * Processes transactions.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @global array $M_options The array of plugin options.
	 */
	protected function _commit_transactions() {
		global $M_options;

		$sub_id = $this->_subscription->sub_id();
		$notes  = $this->_get_option( 'mode', self::MODE_SANDBOX ) != self::MODE_LIVE ? 'Sandbox' : '';

		// process each transaction information and save it to CIM
		foreach ( $this->_transactions as $index => $info ) {
			if ( is_null( $info ) ) {
				continue;
			}

			// :: NOTE :: This code added only for developers who know what they are doing. You probably should not mess with this!
			// Remove (VOID) auth only transactions for serial purchases
			//if( isset( $info['void'] ) && true == $info['void'] ) {
			//
			//	if ( 'aim' == $info['method'] ) {
			//		$this->_get_aim( true, false )->void( $info['transaction'] );
			//	} elseif ( 'cim' == $info['method'] ) {
			//		$this->_get_cim()->createCustomerProfileTransaction( 'Void', $info['transaction'] );
			//	}
			//
			//	$this->_record_transaction(
			//		$this->_member->ID,
			//		$sub_id,
			//		$info['amount'],
			//		$M_options['paymentcurrency'],
			//		$info['date'],
			//		$info['transaction'],
			//		self::TRANSACTION_TYPE_VOIDED,
			//		__('Authorize only transaction removed.', 'membership' )
			//	);
			//}

			$status = 0;
			if ( $info['method'] == 'aim' ) {
				$status = self::TRANSACTION_TYPE_AUTHORIZED;

				// capture first transaction
				if ( $index == 0 ) {
					$this->_get_aim( true, false )->priorAuthCapture( $info['transaction'] );
					$status = self::TRANSACTION_TYPE_CAPTURED;
				}
			} elseif ( $info['method'] == 'cim' ) {
				$status = self::TRANSACTION_TYPE_CIM_AUTHORIZED;

				// capture first transaction
				if ( $index == 0 ) {
					$transaction          = $this->_get_cim_transaction();
					$transaction->transId = $info['transaction'];
					$transaction->amount  = $info['amount'];

					$lineItem = new AuthorizeNetLineItem();
				    $lineItem->itemId = $this->_subscription->sub_id();
				    $lineItem->name = $this->_subscription->sub_name();
				    $lineItem->description = $this->_subscription->sub_description();
				    $lineItem->quantity = 1;
				    $lineItem->unitPrice = $info['amount'];

					$transaction->lineItems = array( $lineItem );

					$this->_get_cim()->createCustomerProfileTransaction( 'PriorAuthCapture', $transaction );
					$status = self::TRANSACTION_TYPE_CAPTURED;
				}
			} elseif ( $info['method'] == 'arb' ) {
				$status = self::TRANSACTION_TYPE_RECURRING;
			}

			if ( $status ) {
				// save transaction information in the database
				$this->_record_transaction(
					$this->_member->ID,
					$sub_id,
					$info['amount'],
					$M_options['paymentcurrency'],
					$info['date'],
					$info['transaction'],
					$status,
					$notes
				);
			}
		}
	}

	/**
	 * Rollbacks transactions all transactions and subscriptions.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 */
	protected function _rollback_transactions() {
		foreach ( $this->_transactions as $info ) {
			if ( $info['method'] == 'aim' ) {
				$this->_get_aim()->void( $info['transaction'] );
			} elseif ( $info['method'] == 'arb' ) {
				$this->_get_arb()->cancelSubscription( $info['transaction'] );
			}
		}
	}

	/**
	 * Creates Authorize.net CIM profile for current user.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @return int Customer profile ID on success, otherwise FALSE.
	 */
	protected function _create_cim_profile() {
		require_once MEMBERSHIP_ABSPATH . '/classes/Authorize.net/AuthorizeNet.php';

		$customer                     = new AuthorizeNetCustomer();
		$customer->merchantCustomerId = $this->_member->ID;
		$customer->email              = $this->_member->user_email;
		$customer->paymentProfiles[]  = $this->_create_cim_payment_profile();

		$response = $this->_get_cim()->createCustomerProfile( $customer );
		if ( $response->isError() ) {
			return false;
		}

		$profile_id = $response->getCustomerProfileId();
		update_user_meta( $this->_member->ID, 'authorize_cim_id', $profile_id );

		return $profile_id;
	}

	/**
	 * Updates CIM profile by adding a new credit card.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @return boolean TRUE on success, otherwise FALSE.
	 */
	protected function _update_cim_profile() {
		$payment  = $this->_create_cim_payment_profile();
		$response = $this->_get_cim()->createCustomerPaymentProfile( $this->_cim_profile_id, $payment );
		if ( $response->isError() ) {
			return false;
		}

		return true;
	}

	/**
	 * Creates CIM payment profile and fills it with posted credit card data.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @return AuthorizeNetPaymentProfile The instance of AuthorizeNetPaymentProfile class.
	 */
	protected function _create_cim_payment_profile() {
		require_once MEMBERSHIP_ABSPATH . '/classes/Authorize.net/AuthorizeNet.php';

		$payment = new AuthorizeNetPaymentProfile();

		// billing information
		$payment->billTo->firstName   = substr( trim( filter_input( INPUT_POST, 'first_name' ) ), 0, 50 );
		$payment->billTo->lastName    = substr( trim( filter_input( INPUT_POST, 'last_name' ) ), 0, 50 );
		$payment->billTo->company     = substr( trim( filter_input( INPUT_POST, 'company' ) ), 0, 50 );
		$payment->billTo->address     = substr( trim( filter_input( INPUT_POST, 'address' ) ), 0, 60 );
		$payment->billTo->city        = substr( trim( filter_input( INPUT_POST, 'city' ) ), 0, 40 );
		$payment->billTo->state       = substr( trim( filter_input( INPUT_POST, 'state' ) ), 0, 40 );
		$payment->billTo->zip         = substr( trim( filter_input( INPUT_POST, 'zip' ) ), 0, 20 );
		$payment->billTo->country     = substr( trim( filter_input( INPUT_POST, 'country' ) ), 0, 60 );
		$payment->billTo->phoneNumber = substr( trim( filter_input( INPUT_POST, 'phone' ) ), 0, 25 );
		$payment->billTo->faxNumber   = substr( trim( filter_input( INPUT_POST, 'fax' ) ), 0, 25 );

		// card information
		$payment->payment->creditCard->cardNumber     = preg_replace( '/\D/', '', filter_input( INPUT_POST, 'card_num' ) );
		$payment->payment->creditCard->cardCode       = trim( filter_input( INPUT_POST, 'card_code' ) );
		$payment->payment->creditCard->expirationDate = sprintf( '%04d-%02d', filter_input( INPUT_POST, 'exp_year', FILTER_VALIDATE_INT ), substr( filter_input( INPUT_POST, 'exp_month', FILTER_VALIDATE_INT ), - 2 ) );

		return $payment;
	}

	/**
	 * Initializes and returns AuthorizeNetAIM object.
	 *
	 * @since     3.5
	 *
	 * @access    protected
	 * @staticvar AuthorizeNetAIM $aim The instance of AuthorizeNetAIM class.
	 *
	 * @param boolean $refresh  Determines whether we need to refresh $aim object or not.
	 * @param boolean $pre_fill Determines whether we need to pre fill AIM object with posted data or not.
	 *
	 * @return AuthorizeNetAIM The instance of AuthorizeNetAIM class.
	 */
	protected function _get_aim( $refresh = false, $pre_fill = true ) {
		static $aim = null;

		if ( ! $refresh && ! is_null( $aim ) ) {
			return $aim;
		}

		require_once MEMBERSHIP_ABSPATH . '/classes/Authorize.net/AuthorizeNet.php';

		// merchant information
		$login_id        = $this->_get_option( 'api_user' );
		$transaction_key = $this->_get_option( 'api_key' );
		$mode            = $this->_get_option( 'mode', self::MODE_SANDBOX );

		// create new AIM
		$aim = new AuthorizeNetAIM( $login_id, $transaction_key );
		$aim->setSandbox( $mode != self::MODE_LIVE );
		if ( defined( 'MEMBERSHIP_AUTHORIZE_LOGFILE' ) ) {
			$aim->setLogFile( MEMBERSHIP_AUTHORIZE_LOGFILE );
		}

		if ( $pre_fill ) {
			// card information
			$aim->card_num         = preg_replace( '/\D/', '', filter_input( INPUT_POST, 'card_num' ) );
			$aim->card_code        = trim( filter_input( INPUT_POST, 'card_code' ) );
			$aim->exp_date         = sprintf( '%02d/%02d', filter_input( INPUT_POST, 'exp_month', FILTER_VALIDATE_INT ), substr( filter_input( INPUT_POST, 'exp_year', FILTER_VALIDATE_INT ), - 2 ) );
			$aim->duplicate_window = MINUTE_IN_SECONDS;

			// customer information
			$aim->cust_id     = $this->_member->ID;
			$aim->customer_ip = self::_get_remote_ip();
			$aim->email       = $this->_member->user_email;

			// billing information
			$aim->first_name = substr( trim( filter_input( INPUT_POST, 'first_name' ) ), 0, 50 );
			$aim->last_name  = substr( trim( filter_input( INPUT_POST, 'last_name' ) ), 0, 50 );
			$aim->company    = substr( trim( filter_input( INPUT_POST, 'company' ) ), 0, 50 );
			$aim->address    = substr( trim( filter_input( INPUT_POST, 'address' ) ), 0, 60 );
			$aim->city       = substr( trim( filter_input( INPUT_POST, 'city' ) ), 0, 40 );
			$aim->state      = substr( trim( filter_input( INPUT_POST, 'state' ) ), 0, 40 );
			$aim->zip        = substr( trim( filter_input( INPUT_POST, 'zip' ) ), 0, 20 );
			$aim->country    = substr( trim( filter_input( INPUT_POST, 'country' ) ), 0, 60 );
			$aim->phone      = substr( trim( filter_input( INPUT_POST, 'phone' ) ), 0, 25 );
			$aim->fax        = substr( trim( filter_input( INPUT_POST, 'fax' ) ), 0, 25 );
		}

		return $aim;
	}

	/**
	 * Initializes and returns AuthorizeNetARB object.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @return AuthorizeNetARB The instance of AuthorizeNetARB class.
	 */
	protected function _get_arb() {

		require_once MEMBERSHIP_ABSPATH . '/classes/Authorize.net/AuthorizeNet.php';

		// merchant information
		$login_id        = $this->_get_option( 'api_user' );
		$transaction_key = $this->_get_option( 'api_key' );
		$mode            = $this->_get_option( 'mode', self::MODE_SANDBOX );

		$arb = new AuthorizeNetARB( $login_id, $transaction_key );
		$arb->setSandbox( $mode != self::MODE_LIVE );
		if ( defined( 'MEMBERSHIP_AUTHORIZE_LOGFILE' ) ) {
			$arb->setLogFile( MEMBERSHIP_AUTHORIZE_LOGFILE );
		}

		return $arb;
	}

	/**
	 * Initializes and returns AuthorizeNet_Subscription object.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @return AuthorizeNet_Subscription The instance of AuthorizeNet_Subscription class.
	 */
	protected function _get_arb_subscription( $pricing ) {
		require_once MEMBERSHIP_ABSPATH . '/classes/Authorize.net/AuthorizeNet.php';

		// create new subscription
		$subscription                      = new AuthorizeNet_Subscription();
		$subscription->customerId          = $this->_member->ID;
		$subscription->customerEmail       = $this->_member->user_email;
		$subscription->customerPhoneNumber = substr( trim( filter_input( INPUT_POST, 'phone' ) ), 0, 25 );
		$subscription->customerFaxNumber   = substr( trim( filter_input( INPUT_POST, 'fax' ) ), 0, 25 );

		switch ( $pricing['unit'] ) {
			case 'd':
				$subscription->intervalLength = $pricing['period'];
				$subscription->intervalUnit   = 'days';
				break;
			case 'w':
				$subscription->intervalLength = $pricing['period'] * 7;
				$subscription->intervalUnit   = 'days';
				break;
			case 'm':
				$subscription->intervalLength = $pricing['period'];
				$subscription->intervalUnit   = 'months';
				break;
			case 'y':
				$subscription->intervalLength = $pricing['period'] * 12;
				$subscription->intervalUnit   = 'months';
				break;
		}

		// card information
		$subscription->creditCardCardNumber     = preg_replace( '/\D/', '', filter_input( INPUT_POST, 'card_num' ) );
		$subscription->creditCardCardCode       = trim( filter_input( INPUT_POST, 'card_code' ) );
		$subscription->creditCardExpirationDate = sprintf( '%04d-%02d', filter_input( INPUT_POST, 'exp_year', FILTER_VALIDATE_INT ), filter_input( INPUT_POST, 'exp_month', FILTER_VALIDATE_INT ) );

		// billing information
		$subscription->billToFirstName = substr( trim( filter_input( INPUT_POST, 'first_name' ) ), 0, 50 );
		$subscription->billToLastName  = substr( trim( filter_input( INPUT_POST, 'last_name' ) ), 0, 50 );
		$subscription->billToCompany   = substr( trim( filter_input( INPUT_POST, 'company' ) ), 0, 50 );
		$subscription->billToAddress   = substr( trim( filter_input( INPUT_POST, 'address' ) ), 0, 60 );
		$subscription->billToCity      = substr( trim( filter_input( INPUT_POST, 'city' ) ), 0, 40 );
		$subscription->billToState     = substr( trim( filter_input( INPUT_POST, 'state' ) ), 0, 40 );
		$subscription->billToZip       = substr( trim( filter_input( INPUT_POST, 'zip' ) ), 0, 20 );
		$subscription->billToCountry   = substr( trim( filter_input( INPUT_POST, 'country' ) ), 0, 60 );

		return $subscription;
	}

	/**
	 * Returns the instance of AuthorizeNetCIM class.
	 *
	 * @since     3.5
	 *
	 * @access    protected
	 * @staticvar AuthorizeNetCIM $cim The instance of AuthorizeNetCIM class.
	 * @return AuthorizeNetCIM The instance of AuthorizeNetCIM class.
	 */
	protected function _get_cim() {
		static $cim = null;

		if ( ! is_null( $cim ) ) {
			return $cim;
		}

		require_once MEMBERSHIP_ABSPATH . '/classes/Authorize.net/AuthorizeNet.php';

		// merchant information
		$login_id        = $this->_get_option( 'api_user' );
		$transaction_key = $this->_get_option( 'api_key' );
		$mode            = $this->_get_option( 'mode', self::MODE_SANDBOX );

		$cim = new AuthorizeNetCIM( $login_id, $transaction_key );
		$cim->setSandbox( $mode != self::MODE_LIVE );
		if ( defined( 'MEMBERSHIP_AUTHORIZE_LOGFILE' ) ) {
			$cim->setLogFile( MEMBERSHIP_AUTHORIZE_LOGFILE );
		}

		return $cim;
	}

	/**
	 * Initializes and returns Authorize.net CIM transaction object.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 * @return AuthorizeNetTransaction The instance of AuthorizeNetTransaction class.
	 */
	protected function _get_cim_transaction() {
		require_once MEMBERSHIP_ABSPATH . '/classes/Authorize.net/AuthorizeNet.php';

		$transaction                           = new AuthorizeNetTransaction();
		$transaction->customerProfileId        = $this->_cim_profile_id;
		$transaction->customerPaymentProfileId = $this->_cim_payment_profile_id;

		return $transaction;
	}

	/**
	 * Returns gateway option.
	 *
	 * @since  3.5
	 *
	 * @access protected
	 *
	 * @param string $name    The option name.
	 * @param mixed  $default The default value.
	 *
	 * @return mixed The option value if it exists, otherwise default value.
	 */
	protected function _get_option( $name, $default = false ) {
		$key = "{$this->gateway}_{$name}";

		return defined( 'MEMBERSHIP_GLOBAL_TABLES' ) && filter_var( MEMBERSHIP_GLOBAL_TABLES, FILTER_VALIDATE_BOOLEAN )
			? get_site_option( $key, $default )
			: get_option( $key, $default );
	}

	/**
	 * Enqueues scripts.
	 *
	 * @since  3.5
	 * @action wp_enqueue_scripts
	 *
	 * @access public
	 */
	public function enqueue_scripts() {
		if ( membership_is_registration_page() || membership_is_subscription_page() ) {
			wp_enqueue_script( 'membership-authorize', MEMBERSHIP_ABSURL . 'js/authorizenet.js', array( 'jquery' ), Membership_Plugin::VERSION, true );
			wp_localize_script( 'membership-authorize', 'membership_authorize', array(
				'return_url'        => esc_url( add_query_arg( 'action', 'processpurchase_' . $this->gateway, admin_url( 'admin-ajax.php', 'https' ) ) ),
				'payment_error_msg' => __( 'There was an unknown error encountered with your payment. Please contact the site administrator.', 'membership' ),
				'stylesheet_url'    => MEMBERSHIP_ABSURL . 'css/authorizenet.css',
			) );
		}
	}

	/**
	 * Renders gateway transactions.
	 *
	 * @since  3.5
	 *
	 * @access public
	 */
	public function transactions() {
		// prepare table
		$table = new Membership_Table_Gateway_Transaction_Authorize( array(
			'gateway'       => $this->gateway,
			'subscriptions' => $this->db->get_results( 'SELECT * FROM ' . MEMBERSHIP_TABLE_SUBSCRIPTIONS, ARRAY_A ),
			'statuses'      => array(
				self::TRANSACTION_TYPE_AUTHORIZED        => esc_html__( 'Authorized (ARB)', 'membership' ),
				self::TRANSACTION_TYPE_CIM_AUTHORIZED    => esc_html__( 'Authorized (CIM)', 'membership' ),
				self::TRANSACTION_TYPE_CAPTURED          => esc_html__( 'Captured', 'membership' ),
				self::TRANSACTION_TYPE_VOIDED            => esc_html__( 'Voided', 'membership' ),
				self::TRANSACTION_TYPE_RECURRING         => esc_html__( 'Recurring', 'membership' ),
				self::TRANSACTION_TYPE_CANCELED_RECURING => esc_html__( 'Cancelled Recurring', 'membership' ),
			),
		) );
		$table->prepare_items();

		// render template
		$template        = new Membership_Render_Gateway_Authorize_Transactions();
		$template->table = $table;
		$template->render();
	}

	/**
	 * Determines whether popup form is used or not.
	 *
	 * @since  3.5
	 *
	 * @static
	 * @access private
	 * @global array $M_options The plugin options.
	 * @return boolean TRUE if popup form is used, otherwise false.
	 */
	private static function is_popup() {
		global $M_options;

		return isset( $M_options['formtype'] ) && $M_options['formtype'] == 'new';
	}

}