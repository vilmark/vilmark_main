<?php

if ( !class_exists( 'M_Ping' ) ) {

	class M_Ping {

		var $build = 1;

		var $db;
		var $tables = array('membership_levels', 'membership_rules', 'subscriptions', 'subscriptions_levels', 'membership_relationships', 'membermeta', 'communications', 'urlgroups', 'ping_history', 'pings');

		var $membership_levels;
		var $membership_rules;
		var $membership_relationships;
		var $subscriptions;
		var $subscriptions_levels;
		var $membermeta;
		var $communications;
		var $urlgroups;
		var $ping_history;
		var $pings;

		// if the data needs reloaded, or hasn't been loaded yet
		var $dirty = true;

		var $ping;
		var $id;

		var $pingconstants = array(
			'%blogname%'         => '',
			'%blogurl%'          => '',
			'%username%'         => '',
			'%usernicename%'     => '',
			'%userdisplayname%'  => '',
			'%userfirstname%'    => '',
			'%userlastname%'     => '',
			'%useremail%'        => '',
			'%userid%'           => '',
			'%networkname%'      => '',
			'%networkurl%'       => '',
			'%subscriptionname%' => '',
			'%levelname%'        => '',
			'%timestamp%'        => '',
		);

		function __construct( $id = false) {

			global $wpdb;

			$this->db = $wpdb;

			foreach($this->tables as $table) {
				$this->$table = membership_db_prefix($this->db, $table);
			}

			$this->id = $id;

		}

		function M_Ping( $id = false ) {
			$this->__construct( $id );
		}

		function ping_name() {
			$this->ping = $this->get_ping();

			return $this->ping->pingname;
		}

		function ping_url() {
			$this->ping = $this->get_ping();

			return $this->ping->pingurl;
		}

		function get_ping( $force = false ) {

			if(!empty($this->ping) && !$force) {
				return $this->ping;
			} else {
				$sql = $this->db->prepare( "SELECT * FROM {$this->pings} WHERE id = %d ", $this->id );

				return $this->db->get_row( $sql );
			}

		}

		function get_specifc_ping( $id ) {

			$sql = $this->db->prepare( "SELECT * FROM {$this->pings} WHERE id = %d ", $id );

			return $this->db->get_row( $sql );

		}

		function editform() {

			$this->ping = $this->get_ping();

			echo '<table class="form-table">';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Ping name','membership') . '</th>';
			echo '<td valign="top"><input name="pingname" type="text" size="50" title="' . __('Ping name', 'membership') . '" style="width: 50%;" value="' . esc_attr(stripslashes($this->ping->pingname)) . '" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Ping URL','membership') . '</th>';
			echo '<td valign="top"><input name="pingurl" type="text" size="50" title="' . __('Ping URL', 'membership') . '" style="width: 50%;" value="' . esc_attr(stripslashes($this->ping->pingurl)) . '" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Ping data', 'membership') . '</th>';
			echo '<td valign="top"><textarea name="pinginfo" rows="15" cols="40" style="float: left; width: 40%;">' . esc_html(stripslashes($this->ping->pinginfo)) . '</textarea>';
			// Display some instructions for the message.
			echo '<div class="instructions" style="float: left; width: 40%; margin-left: 10px;">';
			echo __('You can use the following constants within the message body to embed database information.','membership');
			echo '<br /><br /><em style="font-family:monospace">';

			echo implode('<br/>', array_keys(apply_filters('membership_ping_constants_list', $this->pingconstants)) );

			echo '</em><br/><br />';
			echo __('You can also make a variable an array. (e.g. merge_vars[FNAME]=%userfirstname%)', 'membership') . '<br /><br />';
			echo __('One entry per line. e.g. key=value','membership');
			echo '</div>';
			echo '</td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Ping method','membership') . '</th>';
			echo '<td valign="top" align="left">';
			echo '<select name="pingtype">';
				echo '<option value="GET"';
				if($this->ping->pingtype == 'GET') echo ' selected="selected"';
				echo '>' . __('GET', 'membership') . '</option>';
				echo '<option value="POST"';
				if($this->ping->pingtype == 'POST') echo ' selected="selected"';
				echo '>' . __('POST', 'membership') . '</option>';
			echo '</select>';
			echo '</td></tr>';

			echo '</table>';

		}

		function addform() {

			echo '<table class="form-table">';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Ping name','membership') . '</th>';
			echo '<td valign="top"><input name="pingname" type="text" size="50" title="' . __('Ping name', 'membership') . '" style="width: 50%;" value="" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Ping URL','membership') . '</th>';
			echo '<td valign="top"><input name="pingurl" type="text" size="50" title="' . __('Ping URL', 'membership') . '" style="width: 50%;" value="" /></td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Ping data', 'membership') . '</th>';
			echo '<td valign="top"><textarea name="pinginfo" rows="15" cols="40" style="float: left; width: 40%;"></textarea>';
			// Display some instructions for the message.
			echo '<div class="instructions" style="float: left; width: 40%; margin-left: 10px;">';
			echo __('You can use the following constants within the message body to embed database information.','membership');
			echo '<br /><br /><em style="font-family:monospace">';

			echo implode('<br/>', array_keys(apply_filters('membership_ping_constants_list', $this->pingconstants)) );

			echo '</em><br/><br />';
			echo __('You can also make a variable an array. (e.g. merge_vars[FNAME]=%userfirstname%)', 'membership') . '<br /><br />';
			echo __('One entry per line. e.g. key=value','membership');
			echo '</div>';
			echo '</td>';
			echo '</tr>';

			echo '<tr class="form-field form-required">';
			echo '<th style="" scope="row" valign="top">' . __('Ping method','membership') . '</th>';
			echo '<td valign="top" align="left">';
			echo '<select name="pingtype">';
				echo '<option value="GET"';
				echo '>' . __('GET', 'membership') . '</option>';
				echo '<option value="POST"';
				echo '>' . __('POST', 'membership') . '</option>';
			echo '</select>';
			echo '</td></tr>';

			echo '</table>';

		}

		function add() {

			$insert = array(
								"pingname"	=> 	$_POST['pingname'],
								"pingurl"	=>	$_POST['pingurl'],
								"pinginfo"	=>	$_POST['pinginfo'],
								"pingtype"	=>	$_POST['pingtype']
							);

			return $this->db->insert( $this->pings, $insert );

		}

		function update() {

			$updates = array(
								"pingname"	=> 	$_POST['pingname'],
								"pingurl"	=>	$_POST['pingurl'],
								"pinginfo"	=>	$_POST['pinginfo'],
								"pingtype"	=>	$_POST['pingtype']
							);

			return $this->db->update( $this->pings, $updates, array( "id" => $this->id) );

		}

		function delete() {

			$sql = $this->db->prepare( "DELETE FROM {$this->pings} WHERE id = %d", $this->id );

			return $this->db->query( $sql );

		}

		// History
		function get_history() {
			$sql = $this->db->prepare( "SELECT * FROM {$this->ping_history} WHERE ping_id = %d ORDER BY ping_sent DESC LIMIT 0, 50 ", $this->id );

			return $this->db->get_results( $sql );
		}

		function get_history_item( $history_id ) {
			$sql = $this->db->prepare( "SELECT * FROM {$this->ping_history} WHERE id = %d ", $history_id );

			return $this->db->get_row( $sql );
		}

		function add_history( $sent, $return ) {

			$insert = array(
							"ping_id"		=> 	$this->id,
							"ping_sent"		=>	gmdate( 'Y-m-d H:i:s' ),
							"ping_info"		=>	serialize($sent),
							"ping_return"	=>	serialize($return)
						);

			return $this->db->insert( $this->ping_history, $insert );
		}

		function update_history( $history_id, $sent, $return ) {
			$update = array(
							"ping_id"		=> 	$this->id,
							"ping_sent"		=>	gmdate( 'Y-m-d H:i:s' ),
							"ping_info"		=>	serialize($sent),
							"ping_return"	=>	serialize($return)
						);

			return $this->db->update( $this->ping_history, $update, array( "id" => $history_id ) );
		}

		// processing
		function send_ping( $sub_id = false, $level_id = false, $user_id = false ) {
			if ( !class_exists( 'WP_Http' ) ) {
				include_once( ABSPATH . WPINC . '/class-http.php' );
			}

			$this->ping = $this->get_ping();
			if ( !$this->ping ) {
				return;
			}

			$pingdata = apply_filters( 'membership_ping_constants_list', $this->pingconstants );

			$member = Membership_Plugin::factory()->get_member( empty( $user_id ) ? get_current_user_id() : $user_id );

			if ( !$sub_id ) {
				$ids = $member->get_subscription_ids();
				if ( !empty( $ids ) ) {
					$sub_id = $ids[0];
				}
			}

			if ( !$level_id ) {
				$ids = $member->get_level_ids();
				if ( !empty( $ids ) ) {
					$level_id = $ids[0]->level_id;
				}
			}

			foreach ( $pingdata as $key => $value ) {
				switch ( $key ) {
					case '%blogname%':
						$pingdata[$key] = get_option( 'blogname' );
						break;

					case '%blogurl%':
						$pingdata[$key] = get_option( 'home' );
						break;

					case '%username%':
						$pingdata[$key] = $member->user_login;
						break;

					case '%usernicename%':
						$pingdata[$key] = $member->user_nicename;
						break;

					case '%userdisplayname%':
						$pingdata[$key] = $member->display_name;
						break;

					case '%userfirstname%':
						$pingdata[$key] = $member->user_firstname;
						break;

					case '%userlastname%':
						$pingdata[$key] = $member->user_lastname;
						break;

					case '%useremail%':
						$pingdata[$key] = $member->user_email;
						break;

					case '%userid%':
						$pingdata[$key] = $member->ID;
						break;

					case '%networkname%':
						$pingdata[$key] = get_site_option( 'site_name' );
						break;

					case '%networkurl%':
						$pingdata[$key] = get_site_option( 'siteurl' );
						break;

					case '%subscriptionname%':
						if ( !empty( $sub_id ) ) {
							$sub = Membership_Plugin::factory()->get_subscription( $sub_id );
							$pingdata[$key] = $sub->sub_name();
						} else {
							$pingdata[$key] = '';
						}

						break;

					case '%levelname%':
						if ( !empty( $level_id ) ) {
							$level = Membership_Plugin::factory()->get_level( $level_id );
							$pingdata[$key] = $level->level_title();
						} else {
							$pingdata[$key] = '';
						}
						break;

					case '%timestamp%':
						$pingdata[$key] = time();
						break;

					default:
						$pingdata[$key] = apply_filters( 'membership_pingfield_' . $key, '' );
						break;
				}
			}

			// Globally replace the values in the ping and then make it into an array to send
			$pingmessage = str_replace( array_keys( $pingdata ), array_values( $pingdata ), $this->ping->pinginfo );
			$pingmessage = array_map( 'trim', explode( PHP_EOL, $pingmessage ) );

			// make the ping message into a sendable bit of text
			$pingtosend = array();
			foreach ( $pingmessage as $key => $value ) {
				$temp = explode( "=", $value );

				if ( strpos($temp[0], '[') !== false && strpos($temp[0], ']') !== false ) {
					//this key is an array - let's add to the $pingtosend array as an array
					$varname = $temp[0];
					$value = $temp[1];

					$start = strpos($varname, '[');
					$end = strpos($varname, ']');
					$key = substr($varname, 0, $start);
					$subkey = substr($varname, $start+1, $end-$start-1);

					if ( !isset($pingtosend[$key]) ) {
						$pingtosend[$key] = array($subkey => $value);
					} else {
						$pingtosend[$key][$subkey] = $value;
					}
				} elseif ( count( $temp ) == 2 ) {
					$pingtosend[$temp[0]] = $temp[1];
				}
			}

			// Send the request
			$request = new WP_Http();
			$url = $this->ping->pingurl;
			switch ( $this->ping->pingtype ) {
				case 'GET':
					if ( strpos( $url, '?') === false ) {
						$url .= '?';
					}
					$url .= http_build_query($pingtosend);
					// old method kept for consideration as using WP method.
					// $url = esc_url( add_query_arg( array_map( 'urlencode', $pingtosend ), $url ) );
					$result = $request->request( $url, array( 'method' => 'GET', 'body' => '' ) );
					break;

				case 'POST':
					$result = $request->request( $url, array( 'method' => 'POST', 'body' => $pingtosend ) );
					break;
			}

			/*
			  'headers': an array of response headers, such as "x-powered-by" => "PHP/5.2.1"
			  'body': the response string sent by the server, as you would see it with you web browser
			  'response': an array of HTTP response codes. Typically, you'll want to have array('code'=>200, 'message'=>'OK')
			  'cookies': an array of cookie information
			 */

			$this->add_history( $pingtosend, $result );
		}

		function resend_historic_ping( $history_id, $rewrite ) {
			$history = $this->get_history_item( $history_id );

			if(!empty($history)) {
				$this->id = $history->ping_id;
				$ping = $this->get_specifc_ping( $history->ping_id );

				if( !class_exists( 'WP_Http' ) ) {
				    include_once( ABSPATH . WPINC. '/class-http.php' );
				}

				$url = $ping->pingurl;
				$pingtosend = unserialize($history->ping_info);

				// Send the request
				if( class_exists( 'WP_Http' ) ) {
					$request = new WP_Http;

					switch( $ping->pingtype ) {
						case 'GET':		$url = untrailingslashit($url) . "?";
						 				foreach($pingtosend as $key => $val) {
											if(substr($url, -1) != '?') $url .= "&";
											$url .= $key . "=" . urlencode($val);
										}
										$result = $request->request( $url, array( 'method' => 'GET', 'body' => '' ) );
										break;

						case 'POST':	$result = $request->request( $url, array( 'method' => 'POST', 'body' => $pingtosend ) );
										break;
					}

					/*
					'headers': an array of response headers, such as "x-powered-by" => "PHP/5.2.1"
					'body': the response string sent by the server, as you would see it with you web browser
					'response': an array of HTTP response codes. Typically, you'll want to have array('code'=>200, 'message'=>'OK')
					'cookies': an array of cookie information
					*/

					if($rewrite) {
						$this->add_history( $pingtosend, $result );
					} else {
						$this->update_history( $history_id, $pingtosend, $result );
					}
				}
			}
		}

	}

}

add_action( 'membership_add_level', 'M_ping_joinedlevel', 10, 2 );
function M_ping_joinedlevel( $tolevel_id, $user_id ) {
	// Set up the level and find out if it has a joining ping
	$level = Membership_Plugin::factory()->get_level( $tolevel_id );
	$joiningping_id = $level->get_meta( 'joining_ping' );
	if ( !empty( $joiningping_id ) ) {
		$ping = new M_Ping( $joiningping_id );
		$ping->send_ping( false, $tolevel_id, $user_id );
	}
}

add_action( 'membership_drop_level', 'M_ping_leftlevel', 10, 2 );
function M_ping_leftlevel( $fromlevel_id, $user_id ) {
	// Set up the level and find out if it has a leaving ping
	$level = Membership_Plugin::factory()->get_level( $fromlevel_id );
	$leavingping_id = $level->get_meta( 'leaving_ping' );
	if ( !empty( $leavingping_id ) ) {
		$ping = new M_Ping( $leavingping_id );
		$ping->send_ping( false, $fromlevel_id, $user_id );
	}
}

add_action( 'membership_move_level', 'M_ping_movedlevel', 10, 3 );
function M_ping_movedlevel( $fromlevel_id, $tolevel_id, $user_id ) {
	M_ping_leftlevel( $fromlevel_id, $user_id );
	M_ping_joinedlevel( $tolevel_id, $user_id );
}

add_action( 'membership_add_subscription', 'M_ping_joinedsub', 10, 4 );
function M_ping_joinedsub( $tosub_id, $tolevel_id, $to_order, $user_id ) {
	$sub = Membership_Plugin::factory()->get_subscription( $tosub_id );
	$subjoiningping_id = $sub->get_meta( 'joining_ping' );
	if ( !empty( $subjoiningping_id ) ) {
		$ping = new M_Ping( $subjoiningping_id );
		$ping->send_ping( $tosub_id, $tolevel_id, $user_id );
	}

	$level = Membership_Plugin::factory()->get_level( $tolevel_id );
	$joiningping_id = $level->get_meta( 'joining_ping' );
	if ( !empty( $joiningping_id ) ) {
		$ping = new M_Ping( $joiningping_id );
		$ping->send_ping( $tosub_id, $tolevel_id, $user_id );
	}
}

add_action( 'membership_drop_subscription', 'M_ping_leftsub', 10, 3 );
function M_ping_leftsub( $fromsub_id, $fromlevel_id, $user_id ) {
	$x = '';
	// Leaving the level
	M_ping_leftlevel( $fromlevel_id, $user_id );
	// Leaving the sub
	M_ping_expiresub( $fromsub_id, $fromlevel_id, $user_id );
}

add_action( 'membership_expire_subscription', 'M_ping_expiresub', 10, 3 );
function M_ping_expiresub( $sub_id, $from_level, $user_id ) {
	if( ! empty( $from_level ) ) {
		M_ping_leftlevel( $from_level, $user_id );
	}

	$sub = Membership_Plugin::factory()->get_subscription( $sub_id );
	$subleavingping_id = $sub->get_meta( 'leaving_ping' );
	if ( !empty( $subleavingping_id ) ) {
		$ping = new M_Ping( $subleavingping_id );
		membership_debug_log( "M_ping_expiresub: LINE 548" . print_r( $ping, true ) );
		$ping->send_ping( $sub_id, false, $user_id );
	}
}

add_action( 'membership_move_subscription', 'M_ping_movedsub', 10, 6 );
function M_ping_movedsub( $fromsub_id, $fromlevel_id, $tosub_id, $tolevel_id, $to_order, $user_id ) {
	M_ping_leftsub( $fromsub_id, $fromlevel_id, $user_id );
	M_ping_joinedsub( $tosub_id, $tolevel_id, $to_order, $user_id );
}