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
 * Rule class responsible for URL groups protection.
 *
 * @category Membership
 * @package Model
 * @subpackage Rule
 */
class Membership_Model_Rule_URLGroups extends Membership_Model_Rule {

	/**
	 * Retrives array of URL groups created in the system.
	 *
	 * @access public
	 * @global wpdb $wpdb Current database connection.
	 * @return array Array of URL groups.
	 */
	public function get_groups() {
		global $wpdb;

		return $wpdb->get_results( sprintf(
			"SELECT * FROM %s WHERE SUBSTR(groupname, 1, 1) <> '_' ORDER BY id ASC",
			MEMBERSHIP_TABLE_URLGROUPS
		) );
	}

	/**
	 * Handles rule's stuff initialization.
	 *
	 * @access public
	 */
	public function on_creation() {
		$this->name = 'urlgroups';
		$this->rulearea = 'core';

		$this->label = esc_html__( 'URL Groups', 'membership' );
		$this->description = esc_html__( "Allows specific URL's to be protected (includes ability to protect using regular expressions).", 'membership' );
	}

	/**
	 * Renders rule settings at access level edit form.
	 *
	 * @access public
	 * @param mixed $data The data associated with this rule.
	 */
	public function admin_main($data) {
		if(!$data) $data = array();
		?>
		<div class='level-operation' id='main-urlgroups'>
			<h2 class='sidebar-name'><?php _e('URL Groups', 'membership');?><span><a href='#remove' id='remove-urlgroups' class='removelink' title='<?php _e("Remove URL Groups from this rules area.",'membership'); ?>'><?php _e('Remove','membership'); ?></a></span></h2>
			<div class='inner-operation'>
				<p><?php _e('Select the URL Groups to be covered by this rule by checking the box next to the relevant URL Group name.','membership'); ?></p>
				<?php
					$urlgroups = $this->get_groups();

					if(!empty($urlgroups)) {
						?>
						<table cellspacing="0" class="widefat fixed">
							<thead>
							<tr>
								<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
								<th style="" class="manage-column column-name" id="name" scope="col"><?php _e('URL Group', 'membership'); ?></th>
								</tr>
							</thead>

							<tfoot>
							<tr>
								<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
								<th style="" class="manage-column column-name" id="name" scope="col"><?php _e('URL Group', 'membership'); ?></th>
								</tr>
							</tfoot>

							<tbody>
						<?php
						foreach($urlgroups as $key => $urlgroup) {
							?>
							<tr valign="middle" class="alternate" id="urlgroup-<?php echo $urlgroup->id; ?>">
								<th class="check-column" scope="row">
									<input type="checkbox" value="<?php echo $urlgroup->id; ?>" name="urlgroups[]" <?php if(in_array($urlgroup->id, $data)) echo 'checked="checked"'; ?>>
								</th>
								<td class="column-name">
									<strong><?php echo esc_html($urlgroup->groupname); ?></strong>
								</td>
						    </tr>
							<?php
						}
						?>
							</tbody>
						</table>
						<?php
					}

				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Associates positive data with this rule.
	 *
	 * @access public
	 * @param mixed $data The positive data to associate with the rule.
	 */
	public function on_positive( $data ) {
		$this->data = $data;
		if ( !empty( $this->data ) && is_array( $this->data ) ) {
			foreach ( $this->data as $group_id ) {
				$group = new M_Urlgroup( $group_id );
				M_add_to_global_urlgroup( $group->group_urls_array(), 'positive' );
			}
		}
	}

	/**
	 * Associates negative data with this rule.
	 *
	 * @access public
	 * @param mixed $data The negative data to associate with the rule.
	 */
	public function on_negative( $data ) {
		$this->data = $data;
		if ( !empty( $this->data ) && is_array( $this->data ) ) {
			foreach ( $this->data as $group_id ) {
				$group = new M_Urlgroup( $group_id );
				M_add_to_global_urlgroup( $group->group_urls_array(), 'negative' );
			}
		}
	}

	/**
	 * Validates the rule on negative assertion.
	 *
	 * @access public
	 * @return boolean TRUE if assertion is successfull, otherwise FALSE.
	 */
	public function validate_negative( $args = null ) {
		global $M_global_groups;

		$host = is_ssl() ? "https://" : "http://";
		$host .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$exclude = apply_filters( 'membership_excluded_urls', array() );
		if( membership_check_expression_match( $host, $exclude ) ) {
			return true;
		}

		$found = false;

		$negative = !empty( $M_global_groups['negative'] )
			? array_unique( $M_global_groups['negative'] )
			: array();

		if ( !empty( $negative ) ) {
			$found |= membership_check_expression_match( $host, $negative );
		}

		return !$found;
	}

	/**
	 * Validates the rule on positive assertion.
	 *
	 * @access public
	 * @return boolean TRUE if assertion is successfull, otherwise FALSE.
	 */
	public function validate_positive( $args = null ) {
		global $M_global_groups;

		$host = is_ssl() ? "https://" : "http://";
		$host .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$exclude = apply_filters( 'membership_excluded_urls', array() );
		if( membership_check_expression_match( $host, $exclude ) ) {
			return true;
		}

		$found = false;

		$negative = !empty( $M_global_groups['positive'] )
			? array_unique( $M_global_groups['positive'] )
			: array();

		if ( !empty( $negative ) ) {
			$found |= membership_check_expression_match( $host, $negative );
		}

		return $found;
	}

	/**
	 * Determines whether current rule should handle other blogs protection in
	 * the network. Used only for global table installation case.
	 *
	 * @since 3.5
	 *
	 * @access public
	 * @return boolean TRUE if the rule should be handled, otherwise FALSE.
	 */
	public function is_network_wide() {
		return true;
	}

	public function get_data() {
		return '';
	}

}