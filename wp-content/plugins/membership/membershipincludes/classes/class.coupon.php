<?php

if ( !class_exists( 'M_Coupon' ) ) {

	class M_Coupon {

		var $build = 2;

		var $tables = array('coupons', 'subscriptions');

		var $coupons;
		var $subscriptions;

		var $coupon_label = false;

		var $id;
		var $_coupon;
		var $_tips;

		var $errors = array();

		var $thecoupon;

		function __construct( $id = false, &$tips = false ) {

			global $wpdb, $site_id;

			$this->db = $wpdb;

			foreach($this->tables as $table) {
				$this->$table = membership_db_prefix($this->db, $table);
			}

			// If we're constructing before the tables exist, just exit.
			$table = membership_db_prefix($this->db, 'coupons');
			if($this->db->get_var("SHOW TABLES LIKE '$table'") != $table) {
				return;
			}


			// If we are passing a non numeric ID we should try to find the ID by searching for the coupon name instead.
			if(!is_numeric($id)) {
				$search = $this->db->get_var( $this->db->prepare( "SELECT id FROM $this->coupons WHERE couponcode = %s", strtoupper($id) ) );

				if(!empty($search)) {
					$this->id = $search;
				}
			} else {
				$this->id = $id;
			}

			if($tips !== false) {
				$this->_tips = $tips;
			}

			// Get the coupon for further usage
			$this->_coupon = $this->get_coupon();

		}

		function M_Coupon( $id = false, &$tips = false ) {
			$this->__construct( $id, $tips );
		}

		function remove_coupon_application( $sub_id = false ) {

			global $blog_id;

			// Grab the user account as we should be logged in by now
			$user = wp_get_current_user();

			if(defined('MEMBERSHIP_GLOBAL_TABLES') && MEMBERSHIP_GLOBAL_TABLES === true) {
				if(function_exists('get_site_transient')) {
					$trying = get_site_transient( 'm_coupon_' . $blog_id . '_' . $user->ID . '_' . $sub_id );
					if($trying != false) {
						// We have found an existing coupon try so remove it as we are using a new one
						delete_site_transient( 'm_coupon_' . $blog_id . '_' . $user->ID . '_' . $sub_id );
					}
				} else {
					$trying = get_transient( 'm_coupon_' . $blog_id . '_' . $user->ID . '_' . $sub_id );
					if($trying != false) {
						// We have found an existing coupon try so remove it as we are using a new one
						delete_transient( 'm_coupon_' . $blog_id . '_' . $user->ID . '_' . $sub_id );
					}
				}
			} else {
				$trying = get_transient( 'm_coupon_' . $blog_id . '_' . $user->ID . '_' . $sub_id );
				if($trying != false) {
					// We have found an existing coupon try so remove it as we are using a new one
					delete_transient( 'm_coupon_' . $blog_id . '_' . $user->ID . '_' . $sub_id );
				}
			}

		}

		function record_coupon_application( $sub_id = false, $pricing = false ) {
			global $blog_id;

			$global = defined( 'MEMBERSHIP_GLOBAL_TABLES' ) && filter_var( MEMBERSHIP_GLOBAL_TABLES, FILTER_VALIDATE_BOOLEAN );

			// Create transient for 1 hour.  This means the user has 1 hour to redeem the coupon after its been applied before it goes back into the pool.
			// If you want to use a different time limit use the filter below
			$time = apply_filters( 'membership_apply_coupon_redemption_time', HOUR_IN_SECONDS );

			// Grab the user account as we should be logged in by now
			$user = wp_get_current_user();

			$transient_name = 'm_coupon_' . $blog_id . '_' . $user->ID . '_' . $sub_id;
			$transient_value = array(
				'coupon_id'       => $this->_coupon->id,
				'user_id'         => $user->ID,
				'sub_id'          => $sub_id,
				'prices_w_coupon' => $pricing,
			);

			// Check if a transient already exists and delete it if it does
			if ( $global && function_exists( 'get_site_transient' ) ) {
				$trying = get_site_transient( $transient_name );
				if ( $trying != false ) {
					// We have found an existing coupon try so remove it as we are using a new one
					delete_site_transient( $transient_name );
				}

				set_site_transient( $transient_name, $transient_value, $time );
			} else {
				$trying = get_transient( $transient_name );
				if ( $trying != false ) {
					// We have found an existing coupon try so remove it as we are using a new one
					delete_transient( $transient_name );
				}

				set_transient( $transient_name, $transient_value, $time );
			}
		}

		function confirm_coupon_application() {

		}

		function valid_coupon() {
			if ( empty( $this->_coupon ) ) {
				// We don't have a coupon so there wasn't a valid one
				return false;
			}

			$timestamp = current_time( 'timestamp', true );
			$used_out = !empty( $this->_coupon->coupon_uses ) && absint( $this->_coupon->coupon_used ) >= absint( $this->_coupon->coupon_uses );
			$too_early = !empty( $this->_coupon->coupon_startdate ) && strtotime( $this->_coupon->coupon_startdate ) > $timestamp;
			$too_late = !empty( $this->_coupon->coupon_enddate ) && strtotime( $this->_coupon->coupon_enddate ) < $timestamp;

			return !$used_out && !$too_early && !$too_late;
		}

		function valid_for_subscription( $sub_id ) {
			if ( !$this->valid_coupon() ) {
				// We don't have a coupon so there wasn't a valid one
				return false;
			}

			return $this->_coupon->coupon_sub_id == 0 || $this->_coupon->coupon_sub_id == $sub_id;
		}

		function get_coupon_code() {

			if(empty($this->_coupon)) {
				// We don't have a coupon so there wasn't a valid one
				return false;
			} else {
				return strtoupper($this->_coupon->couponcode);
			}

		}

		function apply_price( $price ) {

			if($this->_coupon->discount_type == 'pct') {
				$discount = ($price / 100) * $this->_coupon->discount;
				$new_price = $price - $discount;
			} else if($this->_coupon->discount_type == 'amt') {
				$new_price = $price - $this->_coupon->discount;
			} else {
				//Unknown type
				$new_price = $price;
			}

			if($new_price < 0) {
				$new_price = 0;
			}

			return apply_filters('membership_coupon_price', $new_price, $price, $this->_coupon);

		}

		function apply_coupon_pricing( $pricing = false ) {
			if ( $pricing === false || empty( $this->_coupon ) ) {
				return $pricing;
			}

			// Cycle through the pricing array
			$types = array( 'serial', 'finite', 'indefinite' );
			$apply_to = $this->_coupon->coupon_apply_to;
			foreach ( $pricing as $key => $price ) {
				if ( $price['amount'] != 0 && ( $apply_to == $price['type'] || !in_array( $apply_to, $types ) ) ) {
					$pricing[$key]['origin'] = $price['amount'];
					$pricing[$key]['amount'] = $this->apply_price( $price['amount'] );
				}
			}

			return $pricing;
		}

		function get_not_valid_message( $sub_id ) {

			if( empty($this->_coupon) ) {
				// We don't have a coupon so there wasn't a valid one
				return __('The Coupon code is invalid.','membership');
			}

			if( !empty($this->_coupon->coupon_uses) && (int) $this->_coupon->coupon_used >= (int) $this->_coupon->coupon_uses ) {
				return __('No Coupons remaining for this code.','membership');
			}

			if( strtotime( $this->_coupon->coupon_enddate ) < time() ) {
				return __('This Coupon has expired.','membership');
			}

			if( $this->_coupon->coupon_sub_id != 0 && $this->_coupon->coupon_sub_id != $sub_id ) {
				return __('The Coupon is not valid for this subscription.','membership');
			}

			return '';

		}

		function add( $data ) {

			global $blog_id;

			if($this->id > 0 ) {
				return $this->update( $data );
			} else {
				if(!empty($data)) {

					$newdata = array();

					$newdata['couponcode'] = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($data['couponcode']));

					$search = $this->db->get_var( $this->db->prepare( "SELECT id FROM $this->coupons WHERE couponcode = %s", $newdata['couponcode'] ) );
					if(!empty($search)) {
						$coupon_exists = true;
						$this->errors[] = __( 'Coupon Code already exists.', 'membership' );
					}

					if (!$newdata['couponcode'])
					   $this->errors[] = __('Please enter a valid Coupon Code', 'membership');

					$newdata['discount'] = round($data['discount'], 2);
					if ($newdata['discount'] <= 0)
						$this->errors[] = __('Please enter a valid Discount Amount', 'membership');

					$newdata['discount_type'] = $data['discount_type'];
					if ($newdata['discount_type'] != 'amt' && $newdata['discount_type'] != 'pct')
						$this->errors[] = __('Please choose a valid Discount Type', 'membership');

					$newdata['discount_currency'] = $data['discount_currency'];

					$newdata['coupon_sub_id'] = $data['coupon_sub_id'];

					$newdata['coupon_startdate'] = date('Y-m-d H:i:s',strtotime($data['coupon_startdate']));
					if ($newdata['coupon_startdate'] === false)
						$this->errors[] = __('Please enter a valid Start Date', 'membership');

					if(empty($data['coupon_enddate'])) {
						if(isset($newdata['coupon_enddate'])) {
							unset($newdata['coupon_enddate']);
						}
					} else {
						$newdata['coupon_enddate'] = ( !empty($data['coupon_enddate']) ? date('Y-m-d H:i:s',strtotime($data['coupon_enddate'])) : '' );
						if (!empty($newdata['coupon_enddate']) && $data['coupon_enddate'] < $data['coupon_startdate']) {
							$this->errors[] = __('Please enter a valid End Date not earlier than the Start Date', 'membership');
						}
					}

					$newdata['coupon_uses'] = (is_numeric($data['coupon_uses'])) ? (int) $data['coupon_uses'] : '';

					$newdata['coupon_apply_to'] = $data['coupon_apply_to'];

					//We need to insert a site_id
					$newdata['site_id'] = $blog_id;

					if( empty( $this->errors ) ) {
						$this->db->insert( $this->coupons, $newdata );
					}

				} else {
					$this->errors[] = __('Please ensure you complete the form.','membership');
				}
			}

			return $this->errors;
		}

		function increment_coupon_used() {
			$uses = (int)$this->_coupon->coupon_uses;
			$used = (int)$this->_coupon->coupon_used;
			if ( $uses == 0 || $used < $uses ) {
				$sql = $this->db->prepare( "UPDATE {$this->coupons} SET coupon_used = coupon_used + 1 WHERE id = %d", $this->id );
				return $this->db->query( $sql ) ? true : false;
			}

			return false;
		}

		function update( $data ) {

			global $blog_id;

			$coupon_id = $data['ID'];

			if(!empty($data) && isset($coupon_id)) {

				$newdata = array();

					$newdata['couponcode'] = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($data['couponcode']));
					if (!$newdata['couponcode'])
					   $this->errors[] = __('Please enter a valid Coupon Code', 'membership');

					$newdata['discount'] = round($data['discount'], 2);
					if ($newdata['discount'] <= 0)
						$this->errors[] = __('Please enter a valid Discount Amount', 'membership');

					$newdata['discount_type'] = $data['discount_type'];
					if ($newdata['discount_type'] != 'amt' && $newdata['discount_type'] != 'pct')
						$this->errors[] = __('Please choose a valid Discount Type', 'membership');

					$newdata['discount_currency'] = $data['discount_currency'];

					$newdata['coupon_sub_id'] = $data['coupon_sub_id'];

					$newdata['coupon_startdate'] = date('Y-m-d H:i:s',strtotime($data['coupon_startdate']));
					if (empty($newdata['coupon_startdate']))
						$this->errors[] = __('Please enter a valid Start Date', 'membership');

					if(empty($data['coupon_enddate'])) {
						if(isset($newdata['coupon_enddate'])) {
							unset($newdata['coupon_enddate']);
						}
					} else {
						$newdata['coupon_enddate'] = ( !empty($data['coupon_enddate']) ? date('Y-m-d H:i:s',strtotime($data['coupon_enddate'])) : '' );
						if (!empty($newdata['coupon_enddate']) && $data['coupon_enddate'] < $data['coupon_startdate']) {
							$this->errors[] = __('Please enter a valid End Date not earlier than the Start Date', 'membership');
						}
					}

					if(isset($data['coupon_uses']))
						$newdata['coupon_uses'] = $data['coupon_uses'];

					$newdata['coupon_apply_to'] = $data['coupon_apply_to'];

					$this->db->update( $this->coupons, $newdata, array('id' => $coupon_id ), '%s', '%s' );
					//$this->db->update( $this->coupons, $newdata );

			} else {
				$this->errors[] = __('Please ensure you complete the form.','membership');
			}

		}

		function delete() {
			if ( apply_filters( 'pre_membership_delete_coupon', true, $this->id ) ) {
				$deleted = $this->db->delete( $this->coupons, array( 'id' => $this->id ), array( '%d' ) );
				if ( $deleted ) {
					do_action( 'membership_delete_coupon', $this->id );
					return true;
				}
			}

			return false;
		}

		private function get_subscriptions() {

			// Bring up a list of active subscriptions
			$sql = $this->db->prepare( "SELECT * FROM {$this->subscriptions} WHERE sub_active = %d", 1 );

			return $this->db->get_results( $sql );

		}

		function get_coupon( $return_array = false ) {
			$sql = $this->db->prepare( "SELECT * FROM {$this->coupons} WHERE id = %d", $this->id );

			if($return_array) {
				return $this->db->get_row( $sql, ARRAY_A );
			} else {
				return $this->db->get_row( $sql );
			}
		}

		function addform() {

			global $M_options;

			echo '<table class="form-table">';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Coupon Code','membership') . $this->_tips->add_tip( __('The Coupon code should contain letters and numbers only.','membership') ) . '</th>';
			echo '<td valign="top"><input name="couponcode" type="text" size="50" title="' . __('Coupon Code', 'membership') . '" style="width: 50%;" value="" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Discount','membership') . $this->_tips->add_tip( __('The amount or percantage of a discount the coupon is valid for.','membership') ) . '</th>';
			echo '<td valign="top"><input name="discount" type="text" size="6" title="' . __('discount', 'membership') . '" style="width: 6em;" value="" />';
			echo "&nbsp;";
			echo "<select name='discount_type'>";
				echo "<option value='amt'>" . (isset($M_options['paymentcurrency']) ? $M_options['paymentcurrency'] : '$') . "</option>";
				echo "<option value='pct'>%</option>";
			echo "</select>";
			echo "<input type='hidden' name='discount_currency' value='", isset( $M_options['paymentcurrency'] ) ? $M_options['paymentcurrency'] : '' , "'/>";
			echo "</td>";
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Start Date','membership') . $this->_tips->add_tip( __('The date that the Coupon code should be valid from.','membership') ) . '</th>';
			echo '<td valign="top"><input name="coupon_startdate" type="text" size="20" title="' . __('Start Date', 'membership') . '" style="width: 10em;" value="' . date("Y-m-d") . '" class="pickdate" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Expire Date','membership') . $this->_tips->add_tip( __('The date that the Coupon code should be valid until. Leave this blank if there is no end date.','membership') ) . '</th>';
			echo '<td valign="top"><input name="coupon_enddate" type="text" size="20" title="' . __('Expire Date', 'membership') . '" style="width: 10em;" value="" class="pickdate" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Subscription','membership') . $this->_tips->add_tip( __('The subscription that this coupon can be used on.','membership') ) . '</th>';
			echo '<td valign="top">';
			echo "<select name='coupon_sub_id'>";
				echo "<option value='0'>" . __('Any Subscription','membership') . "</option>";

				$subs = $this->get_subscriptions();
				if(!empty($subs)) {
					foreach($subs as $sub) {
						echo "<option value='" . $sub->id . "'>" . $sub->sub_name . "</option>";
					}
				}
			echo "</select>";
			echo "</td>";
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Allowed Uses','membership') . $this->_tips->add_tip( __('The number of times the coupon can be used. Leave this blank if there is no limit.','membership') ) . '</th>';
			echo '<td valign="top"><input name="coupon_uses" type="text" size="20" title="' . __('Allowed Uses', 'membership') . '" style="width: 6em;" value="" class="" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Apply Coupon to','membership') . $this->_tips->add_tip( __('The parts of a subscription that the coupon should be applied to.','membership') ) . '</th>';
			echo '<td valign="top">';
			echo "<select name='coupon_apply_to'>";
				echo "<option value='all'>" . __('All Levels','membership') . "</option>";
				echo "<option value='finite'>" . __('Only Finite Levels','membership') . "</option>";
				echo "<option value='serial'>" . __('Only Serial Levels','membership') . "</option>";
				echo "<option value='indefinite'>" . __('Only Indefinite Levels','membership') . "</option>";
			echo "</select>";
			echo "</td>";
			echo '</tr>';

			echo '</div>';
			echo '</td>';
			echo '</tr>';

			echo '</table>';

		}

		function editform() {

			global $M_options;

			if(empty($this->_coupon)) {
				$this->_coupon = $this->get_coupon();
			}

			echo '<table class="form-table">';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Coupon Code','membership') . $this->_tips->add_tip( __('The Coupon code should contain letters and numbers only.','membership') ) . '</th>';
			echo '<td valign="top"><input name="couponcode" type="text" size="50" title="' . __('Coupon Code', 'membership') . '" style="width: 50%;" value="' . esc_attr($this->_coupon->couponcode) . '" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Discount','membership') . $this->_tips->add_tip( __('The amount or percantage of a discount the coupon is valid for.','membership') ) . '</th>';
			echo '<td valign="top"><input name="discount" type="text" size="6" title="' . __('discount', 'membership') . '" style="width: 6em;" value="';
			if($this->_coupon->discount_type == 'amt') {
				echo esc_attr($this->_coupon->discount);
			} else {
				echo esc_attr(number_format_i18n($this->_coupon->discount, 2));
			}
			echo '" />';
			echo "&nbsp;";
			echo "<select name='discount_type'>";
				echo "<option value='amt' " . selected('amt', esc_attr($this->_coupon->discount_type), false) . ">" . (isset($M_options['paymentcurrency']) ? $M_options['paymentcurrency'] : '$') . "</option>";
				echo "<option value='pct'" . selected('pct', esc_attr($this->_coupon->discount_type), false) . ">%</option>";
			echo "</select>";
			echo "<input type='hidden' name='discount_currency' value='" . esc_attr($this->_coupon->discount_currency) . "'/>";
			echo "</td>";
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Start Date','membership') . $this->_tips->add_tip( __('The date that the Coupon code should be valid from.','membership') ) . '</th>';
			echo '<td valign="top"><input name="coupon_startdate" type="text" size="20" title="' . __('Start Date', 'membership') . '" style="width: 10em;" value="' . mysql2date("Y-m-d", $this->_coupon->coupon_startdate) . '" class="pickdate" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Expire Date','membership') . $this->_tips->add_tip( __('The date that the Coupon code should be valid until. Leave this blank if there is no end date.','membership') ) . '</th>';
			echo '<td valign="top"><input name="coupon_enddate" type="text" size="20" title="' . __('Expire Date', 'membership') . '" style="width: 10em;" value="';
			if(!empty($this->_coupon->coupon_enddate)) echo mysql2date("Y-m-d", $this->_coupon->coupon_enddate);
			echo '" class="pickdate" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Subscription','membership') . $this->_tips->add_tip( __('The subscription that this coupon can be used on.','membership') ) . '</th>';
			echo '<td valign="top">';
			echo "<select name='coupon_sub_id'>";
				echo "<option value='0' " . selected(0, $this->_coupon->coupon_sub_id) . ">" . __('Any Subscription','membership') . "</option>";

				$subs = $this->get_subscriptions();
				if(!empty($subs)) {
					foreach($subs as $sub) {
						echo "<option value='" . $sub->id . "' " . selected($sub->id, $this->_coupon->coupon_sub_id) . ">" . $sub->sub_name . "</option>";
					}
				}
			echo "</select>";
			echo "</td>";
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Allowed Uses','membership') . $this->_tips->add_tip( __('The number of times the coupon can be used. Leave this blank if there is no limit.','membership') ) . '</th>';
			echo '<td valign="top"><input name="coupon_uses" type="text" size="20" title="' . __('Allowed Uses', 'membership') . '" style="width: 6em;" value="';
			if($this->_coupon->coupon_uses != 0) echo esc_attr($this->_coupon->coupon_uses);
			echo '" class="" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Apply Coupon to','membership') . $this->_tips->add_tip( __('The parts of a subscription that the coupon should be applied to.','membership') ) . '</th>';
			echo '<td valign="top">';
			echo "<select name='coupon_apply_to'>";
				echo "<option value='all'" . (($this->_coupon->coupon_apply_to == 'all') ? " selected='selected'" : "") . ">" . __('All Levels','membership') . "</option>";
				echo "<option value='finite'" . (($this->_coupon->coupon_apply_to == 'finite') ? " selected='selected'" : "") . ">" . __('Only Finite Levels','membership') . "</option>";
				echo "<option value='serial'" . (($this->_coupon->coupon_apply_to == 'serial') ? " selected='selected'" : "") . ">" . __('Only Serial Levels','membership') . "</option>";
				echo "<option value='indefinite'" . (($this->_coupon->coupon_apply_to == 'indefinite') ? " selected='selected'" : "") . ">" . __('Only Indefinite Levels','membership') . "</option>";
			echo "</select>";
			echo "</td>";
			echo '</tr>';

			echo '</div>';
			echo '</td>';
			echo '</tr>';

			echo '</table>';

		}

	}

}