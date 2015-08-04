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
 * Base rule class.
 *
 * @category Membership
 * @package Model
 * @subpackage Rule
 */
class Membership_Model_Rule {

	/**
	 * The data associated with this rule.
	 *
	 * @access public
	 * @var mixed
	 */
	var $data;

	/**
	 * Level data associated with this rule.
	 *
	 * @access public
	 * @var mixed
	 */
	var $level_data;

	/**
	 * The name of the rule.
	 *
	 * @access public
	 * @var string
	 */
	var $name = 'none';

	/**
	 * The label of the rule.
	 *
	 * @access public
	 * @var string
	 */
	var $label = 'None Set';

	/**
	 * The description of the rule.
	 *
	 * @access public
	 * @var string
	 */
	var $description = '';

	/**
	 * The area of the rule.
	 *
	 * @access public
	 * @var string
	 */
	var $rulearea = 'public';

	/**
	 * The level id which uses this rule.
	 *
	 * @access public
	 * @var int
	 */
	var $level_id = false;

	/**
	 * The data associated with previous rules.
	 *
	 * @access public
	 * @var mixed
	 */
	var $previous_data;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @param int $level_id The level id which uses this rule.
	 */
	public function __construct( $level_id = false ) {
		global $M_previous_positive, $M_previous_negative;
		$this->level_id = $level_id;
		$this->on_creation();
	}

	/**
	 * Renders rule widget in an access level sidebar.
	 *
	 * @access public
	 * @param boolean $dragged Determines whether this rule has been dragged into the level.
	 */
	public function admin_sidebar( $dragged ) {
		?><li class="draggable-level" id="<?php echo $this->name ?>"<?php if ( $dragged === true ) echo ' style="display:none"' ?>>
			<div class="action action-draggable">
				<div class="action-top closed">
					<a href="#available-actions" class="action-button hide-if-no-js"></a>
					<?php echo esc_html( $this->label ) ?>
				</div>
				<div class="action-body closed">
					<?php if ( !empty( $this->description ) ) : ?>
						<p><?php echo esc_html( $this->description ) ?></p>
					<?php endif; ?>
					<p>
						<a href="#addtopositive" class="action-to-positive" title="<?php _e( 'Add this rule to the positive area of the membership level', 'membership' ) ?>">
							<?php esc_html_e( 'Add to Positive rules', 'membership' ) ?>
						</a>
						<a href="#addtonegative" class="action-to-negative" title="<?php _e( 'Add this rule to the negative area of the membership level', 'membership' ) ?>">
							<?php esc_html_e( 'Add to Negative rules', 'membership' ) ?>
						</a>
					</p>
				</div>
			</div>
		</li><?php
	}

	/**
	 * Renders rule settings at access level edit form.
	 *
	 * @access public
	 * @param mixed $data The data associated with this rule.
	 */
	public function admin_main( $data ) {}

	/**
	 * Handles rule's stuff initialization.
	 *
	 * @access public
	 */
	public function on_creation() {}


	/**
	 * Set level_data.
	 *
	 * @access public
	 */
	public function set_level_data( $level_data ) {
		$this->level_data = $level_data;
	}

	/**
	 * Associates positive data with this rule.
	 *
	 * @access public
	 * @param mixed $data The positive data to associate with the rule.
	 */
	public function on_positive( $data ) {
		$this->data = $data;
	}

	/**
	 * Associates negative data with this rule.
	 *
	 * @access public
	 * @param mixed $data The negative data to associate with the rule.
	 */
	public function on_negative( $data ) {
		$this->data = $data;
	}

	/**
	 * Validates the rule on positive assertion.
	 *
	 * @access public
	 * @return boolean TRUE if assertion is successfull, otherwise FALSE.
	 */
	public function validate_positive( $args = null ) {
		return true;
	}

	/**
	 * Validates the rule on negative assertion.
	 *
	 * @access public
	 * @return boolean TRUE if assertion is successfull, otherwise FALSE.
	 */
	public function validate_negative( $args = null ) {
		return true;
	}

	/**
	 * Determines whether the rule area belongs to admin side.
	 *
	 * @access public
	 * @return boolean TRUE if rule area belongs to admin side, otherwise FALSE.
	 */
	public function is_adminside() {
		return in_array( $this->rulearea, array( 'admin', 'core' ) );
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
		return false;
	}

	/**
	 * Filters out unescessary bloat from Access Rules.
	 *
	 * @since 3.5.2.4
	 *
	 * @access public
	 */
	public static function filter_rule_array( $rule_array ) {

		return array_filter( $rule_array, array( 'self', 'filter_empty_ids' ) );
	}

	public static function filter_empty_ids( $item ) {
		return isset( $item['id'] );
	}

}