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
 * Rule class responsible for posts protection.
 *
 * @category Membership
 * @package Model
 * @subpackage Rule
 */
class Membership_Model_Rule_Posts extends Membership_Model_Rule {

	var $name = 'posts';
	var $label = 'Posts';
	var $description = 'Allows specific posts to be protected.';
	var $post_ids = array();

	var $rulearea = 'public';

	function admin_main($data) {
		global $M_options;

		if ( !$data ) {
			$data = array();
		}

		$posts_to_show = !empty( $M_options['membership_post_count'] ) ? $M_options['membership_post_count'] : MEMBERSHIP_POST_COUNT;
		$posts = get_posts( array(
			'numberposts' => $posts_to_show,
			'offset'      => 0,
			'orderby'     => 'post_date',
			'order'       => 'DESC',
			'post_type'   => 'post',
			'post_status' => 'publish'
		) );

		?><div class='level-operation' id='main-posts'>
			<h2 class='sidebar-name'><?php _e('Posts', 'membership');?><span><a href='#remove' id='remove-posts' class='removelink' title='<?php _e("Remove Posts from this rules area.",'membership'); ?>'><?php _e('Remove','membership'); ?></a></span></h2>
			<div class='inner-operation'>
				<p><?php _e('Select the posts to be covered by this rule by checking the box next to the relevant posts title.','membership'); ?></p>
				<?php if ( $posts ) : ?>
					<table cellspacing="0" class="widefat fixed">
						<thead>
						<tr>
							<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
							<th style="" class="manage-column column-name" id="name" scope="col"><?php _e('Post title', 'membership'); ?></th>
							<th style="" class="manage-column column-name" id="drip" scope="col"><?php _e('Available/blocked after ', 'membership'); ?><small><?php _e('(0 days for immediate)', 'membership'); ?></small></th>
							<th style="" class="manage-column column-date" id="date" scope="col"><?php _e('Post date', 'membership'); ?></th>
						</tr>
						</thead>

						<tfoot>
						<tr>
							<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
							<th style="" class="manage-column column-name" id="name" scope="col"><?php _e('Post title', 'membership'); ?></th>
							<th style="" class="manage-column column-name" id="drip" scope="col"><?php _e('Available/blocked after ', 'membership'); ?><small><?php _e('(0 days for immediate)', 'membership'); ?></small></th>
							<th style="" class="manage-column column-date" id="date" scope="col"><?php _e('Post date', 'membership'); ?></th>
						</tr>
						</tfoot>

						<tbody>
						<?php $key = 0; ?>
						<?php
							$post_ids = $this->get_post_ids( $data );
							$drip_days = $this->get_post_days( $data );
							$drip_units = $this->get_post_days_units( $data );
						?>

						<?php foreach( $posts as $post ) : ?>

							<tr valign="middle" class="alternate" id="post-<?php echo $post->ID; ?>">
								<th class="check-column" scope="row">
									<input type="checkbox" value="<?php echo $post->ID; ?>" name="posts[<?php echo $key; ?>][id]"<?php checked( in_array( $post->ID, $post_ids ) ) ?>>
								</th>
								<td class="column-name">
									<strong><?php echo esc_html($post->post_title); ?></strong>
								</td>
								<td class="column-drip">
									<?php
									    $value = 0;
										$use_default = true;
										if( $key < count( $drip_days ) && in_array( $post->ID, $post_ids ) ) {
											$value = ! empty( $drip_days ) && (! empty( $drip_days[$key] ) || 0 == $drip_days[$key]) ? $drip_days[$key] : 0;
											$use_default = false;
										}
									?>
									<input type="text" value="<?php echo esc_attr( $value ); ?>" name="posts[<?php echo $key; ?>][drip]" size="4">
									<select name="posts[<?php echo $key; ?>][drip_unit]">
										<?php $default = isset( $drip_units[$key] ) && ! empty( $drip_units ) && (selected( $drip_units[$key], 'd' ) || selected( $drip_units[$key], 'm' ) || selected( $drip_units[$key], 'y' )) ? '' : $use_default ? 'selected' : '';  ?>
									    <option value="d" <?php ! $use_default && ! empty( $drip_units ) ? selected( $drip_units[$key], 'd' ) : false; ?><?php echo $default; ?>><?php _e( 'Day(s)', 'membership' ) ?></option>
									    <option value="w" <?php ! $use_default && ! empty( $drip_units ) ? selected( $drip_units[$key], 'w' ) : false; ?>><?php _e( 'Week(s)', 'membership' ) ?></option>
									    <option value="m" <?php ! $use_default && ! empty( $drip_units ) ? selected( $drip_units[$key], 'm' ) : false; ?>><?php _e( 'Month(s)', 'membership' ) ?></option>
									</select>
								</td>

								<td class="column-date">
									<?php echo date( 'd M y', strtotime( $post->post_date ) ); ?>
								</td>
						    </tr>
						<?php $key += 1; ?>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<p class='description'><?php printf( __( "Only the most recent %d posts are shown above, if you have more than that then you should consider using categories instead.", 'membership' ), $posts_to_show ) ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns valid post ID's.
	 *
	 * @access public
	 */
	public function get_post_ids( $data ) {
		// this is a legacy rule
		if( $this->is_legacy_rule( $data ) ) {
			return $data;
		}

		$post_ids = array();
		foreach( $data as $key => $item ) {
			if( !empty( $item['id'] ) ) {
				$post_ids[$key] = $item['id'];
			}
		}

		return $post_ids;
	}

	public function get_dripped_post_ids( $data ) {
		// Legacy rule, dripped doesn't apply
		if( $this->is_legacy_rule( $data ) ) {
			return $data;
		}

		// Get the key variables
		$post_ids = $this->get_post_ids( $data );
		$post_drip = $this->get_post_days( $data );
		$post_unit = $this->get_post_days_units( $data );

		// New array of ids
		$drip_post_ids = array();

		$dates = Membership_Model_Level::get_dates( $this->level_data );

		if( !empty( $dates ) ) {
			// If the user has multiple start dates because of different subscriptions, pick the first start date
			$start = strtotime( $dates[0]->startdate );
			foreach( $dates as $date ) {
				$new_start = strtotime( $date->startdate );
				$start = $start > $new_start ? $new_start : $start;
			}

			$now = time();
			$datediff = $now - $start;
			$days = floor($datediff/(60*60*24));  // could be negative, but that doesn't matter

			// Check each page against dripped requirements
			foreach( $post_ids as $key => $post ) {
				$days_required = $post_drip[$key];
				switch( $post_unit[$key] ) {
					case 'd':
						$days_required = $days_required;
						break;
					case 'w':
						$days_required = $days_required * 7;
						break;
					case 'm':
						$days_required = $days_required * 30;
						break;
				}

				if( $days >= $days_required || 0 == $days_required ) {
					$drip_post_ids[$key] = $post;
				}
			}
		} else {
			$drip_post_ids = $post_ids;
		}
		return $drip_post_ids;
	}

	/**
	 * Get availability.
	 *
	 * @access public
	 */
	public function get_post_days( $data ) {
		// this is a legacy rule
		if( $this->is_legacy_rule( $data ) ) {
			return false;
		}

		$days = array();
		foreach( $data as $key => $item ) {
			if( !empty( $item['drip'] ) || 0 == $item['drip'] ) {
				$val = (int) $item['drip'];
				$days[$key] = ! empty( $val ) || 0 == $val ? $val : 0;
			}
		}

		return $days;
	}

	/**
	 * Get availability units.
	 *
	 * @access public
	 */
	public function get_post_days_units( $data ) {
		// this is a legacy rule
		if( $this->is_legacy_rule( $data ) ) {
			return false;
		}

		$units = array();
		foreach( $data as $key => $item ) {
			$units[$key] = ! empty( $item['drip_unit'] ) ? $item['drip_unit'] : 'd';
		}
		return $units;
	}

	/**
	 * Check for legacy rule.
	 *
	 * @access public
	 */
	public function is_legacy_rule( $data ) {
		if( is_array( array_pop( $data ) ) ) {
			return false;
		} else {
			return true;
		}
	}


	function on_positive( $data ) {
		$this->data = (array) $data;
		add_action( 'pre_get_posts', array( $this, 'add_viewable_posts' ), 99 );
		add_filter('widget_posts_args', array($this, 'filter_wp_recent_posts_positive' ), 99 );
		add_filter( 'previous_post_link', array( $this, 'post_link' ), 10, 4 );
		add_filter( 'next_post_link', array( $this, 'post_link' ), 10, 4 );
	}

	function post_link( $output, $format, $link, $post ) {
		global $M_rule_filters, $wp_query;

		$hide_link = false;
		$b_post_in = false;
		$b_post_not_in = false;
		$b_cat_in = false;
		$b_cat_not_in = false;

		/* == POSITIVE RULES == */
		if ( ! empty( $M_rule_filters['post_viewable']['post__in'] ) ) {
			if ( ! in_array( $post->ID, $M_rule_filters['post_viewable']['post__in'] ) ) {
				$b_post_in = true;
			}
		}

		if ( ! empty( $M_rule_filters['category_viewable']['category__in'] ) ) {
			foreach ( $M_rule_filters['category_viewable']['category__in'] as $cat_id ) {
				if ( ! has_category( $cat_id, $post ) ) {
					$b_cat_in = true;
				}
			}
		}

		$hide_link = $b_post_in ? $b_cat_in ? true : $hide_link : $b_cat_in ? true : $hide_link;

		/* == NEGATIVE RULES == */
		if ( ! empty( $M_rule_filters['post_not_viewable']['post__not_in'] ) ) {
			if ( in_array( $post->ID, $M_rule_filters['post_not_viewable']['post__not_in'] ) ) {
				$b_post_not_in = true;
			}
		}

		if( ! empty( $M_rule_filters['category_not_viewable']['category__not_in'] ) ) {

			foreach ( $M_rule_filters['category_not_viewable']['category__not_in'] as $cat_id ) {
				if ( has_category( $cat_id, $post ) ) {
					$b_cat_not_in = true;
				}
			}
		}

		$hide_link = $b_post_not_in ? $b_cat_not_in ? $hide_link : false : $b_cat_not_in ? true : $hide_link;

		$output = $hide_link ? '' : $output;

		return $output;
	}

	function on_negative( $data ) {
		$this->data = (array) $data;
		add_action( 'pre_get_posts', array( $this, 'add_unviewable_posts' ), 99 );
		add_filter('widget_posts_args', array($this, 'filter_wp_recent_posts_negative' ), 99 );
		add_filter( 'previous_post_link', array( $this, 'post_link' ), 10, 4 );
		add_filter( 'next_post_link', array( $this, 'post_link' ), 10, 4 );
	}

	function add_viewable_posts( $wp_query ) {
		global $post, $M_rule_filters;

		// For Post and Category rules to work together, we convert categories to post ids
		if ( ! empty( $wp_query->query_vars['category__in'] ) && empty( $wp_query->query_vars['post__in'] ) ) {
			$wp_query->query_vars['post_in'] = M_get_category_post_ids( $wp_query->query_vars['category__in'] );
			unset( $wp_query->query_vars['category__in'] );
		}

		if( ! $wp_query->is_main_query() ) { return false; }

		if ( !$wp_query->is_singular && empty( $wp_query->query_vars['pagename'] ) && ( !isset( $wp_query->query_vars['post_type'] ) || in_array( $wp_query->query_vars['post_type'], array( 'post', '' ) )) ) {

			$post_ids = $this->get_dripped_post_ids( $this->data );

			$M_rule_filters['post_viewable']['post__in'] = is_array( $wp_query->query_vars['post__in'] ) ? $wp_query->query_vars['post__in'] : array();

			foreach ( (array) $post_ids as $key => $value ) {
				$M_rule_filters['post_viewable']['post__in'][] = $value;
			}

			// Merge posts from viewable categories
			if ( isset( $M_rule_filters['category_viewable']['category__in'] ) && ! empty( $M_rule_filters['category_viewable']['category__in'] ) ) {
				$M_rule_filters['post_viewable']['post__in'] = array_merge( $M_rule_filters['post_viewable']['post__in'], M_get_category_post_ids( $M_rule_filters['category_viewable']['category__in'] ) );
			}

			$wp_query->query_vars['post__in'] = array_unique( $M_rule_filters['post_viewable']['post__in'] );
		} else if( $wp_query->is_singular ) {
			$post_ids = $this->get_dripped_post_ids( $this->data );
			$M_rule_filters['post_viewable']['post__in'] = isset( $M_rule_filters['post_viewable']['post__in'] ) ? array_unique( array_merge( $M_rule_filters['post_viewable']['post__in'], $post_ids ) ) : $post_ids;
		}
	}

	function add_unviewable_posts( $wp_query ) {
		global $M_rule_filters;

		if( ! $wp_query->is_main_query() ) { return false; }

		if ( !$wp_query->is_singular && empty( $wp_query->query_vars['pagename'] ) && ( !isset( $wp_query->query_vars['post_type'] ) || in_array( $wp_query->query_vars['post_type'], array( 'post', '' ) ) ) ) {

			$post_ids = $this->get_dripped_post_ids( $this->data );

			$M_rule_filters['post_not_viewable']['post__not_in'] = is_array( $wp_query->query_vars['post__not_in'] ) ? $wp_query->query_vars['post__not_in'] : array();

			foreach ( (array) $post_ids as $key => $value ) {
				$M_rule_filters['post_not_viewable']['post__not_in'][] = $value;
			}

			// Merge posts from non viewable categories
			if ( isset( $M_rule_filters['category_not_viewable']['category__not_in'] ) && ! empty( $M_rule_filters['category_not_viewable']['category__not_in'] ) ) {
				$M_rule_filters['post_not_viewable']['post__not_in'] = array_merge( $M_rule_filters['post_not_viewable']['post__not_in'], M_get_category_post_ids( $M_rule_filters['category_not_viewable']['category__not_in'] ) );
			}

			$wp_query->query_vars['post__not_in'] = array_unique( $M_rule_filters['post_not_viewable']['post__not_in'] );
		} else if( $wp_query->is_singular ) {
			$post_ids = $this->get_dripped_post_ids( $this->data );
			$M_rule_filters['post_not_viewable']['post__not_in'] = isset( $M_rule_filters['post_not_viewable']['post__not_in'] ) ? array_unique( array_merge( $M_rule_filters['post_not_viewable']['post__not_in'], $post_ids ) ) : $post_ids;
		}
	}

	function filter_wp_recent_posts_positive( $args ) {
		global $M_rule_filters;

		$M_rule_filters['post_viewable']['post__in'] = isset( $M_rule_filters['post_viewable']['post__in'] ) ? $M_rule_filters['post_viewable']['post__in'] : array();

		$categories = isset( $args['category__in'] ) ? $args['category__in'] : array();
		unset($args['category__in']);

		$posts = array_unique( array_merge( $M_rule_filters['post_viewable']['post__in'], M_get_category_post_ids( $categories ) ) );
		$args['post__in'] = $posts;
		$args['ignore_sticky_posts'] = 1;

		return $args;
	}

	function filter_wp_recent_posts_negative( $args ) {
		global $M_rule_filters;

		$M_rule_filters['post_not_viewable']['post__not_in'] = isset( $M_rule_filters['post_not_viewable']['post__not_in'] ) ? $M_rule_filters['post_not_viewable']['post__not_in'] : array();

		$categories = isset( $args['category__not_in'] ) ? $args['category__not_in'] : array();
		unset($args['category__not_in']);

		$posts = array_unique( array_merge( $M_rule_filters['post_not_viewable']['post__not_in'], M_get_category_post_ids( $categories ) ) );
		$args['post__not_in'] = $posts;
		$args['ignore_sticky_posts'] = 1;

		return $args;
	}

	public function validate_negative( $args = null ) {
		if( $this->is_category_invalid( $args ) ) {
			return false;
		}
		$page = get_queried_object();
		$this->post_ids = $this->get_dripped_post_ids( $this->data );
		return is_a( $page, 'WP_Post' ) && $page->post_type == 'post'
			? !in_array( $page->ID, $this->post_ids )
			: parent::validate_negative();
	}

	public function validate_positive( $args = null ) {
		if( $this->is_category_valid( $args ) ) {
			return true;
		}
		$page = get_queried_object();
		$this->post_ids = $this->get_dripped_post_ids( $this->data );
		return is_a( $page, 'WP_Post' ) && $page->post_type == 'post'
			? in_array( $page->ID, $this->post_ids )
			: parent::validate_positive();

	}

	private function is_category_invalid( $arr ) {
		$invalid = false;
		if( ! is_array( $arr ) ) {
			return false;
		}

		foreach( $arr as $key => $item ) {

			if( 'categories' == $item['name'] ) {
				$invalid |= ! $item['result'];
			}

		}
		return $invalid;
	}

	private function is_category_valid( $arr ) {
		$valid = false;
		if( ! is_array( $arr ) ) {
			return false;
		}

		foreach( $arr as $key => $item ) {

			if( 'categories' == $item['name'] ) {
				$valid |= $item['result'];
			}

		}
		return $valid;
	}

	public function get_data() {
		return $this->post_ids;
	}

}