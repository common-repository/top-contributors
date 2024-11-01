<?php
/**
Plugin Name: Top Contributors
Version: 1.1
Plugin URI: http://justmyecho.com/2010/07/top-contributors-plugin-wordpress/
Description: Display your top commenters in a widget. Make sure to backup any customizations to your "top-contributors/css/tooltip.css" before upgrading.
Author: Robin Dalton
Author URI: http://justmyecho.com
Changes:
	1.1 - Added Time limit options, fixed some formatting/style issues.
	1.0 - Initial release.
**/

define('JMETC_PLUGINPATH', WP_CONTENT_URL . '/plugins/'. plugin_basename(dirname(__FILE__)) . '/');
load_plugin_textdomain( 'jmetc', null, JMETC_PLUGINPATH . 'languages' );


$jmetc_options = get_option('jmetc');

function top_contributors_load_widget() {
	register_widget( 'Top_Contributors_Widget' );
}

class Top_Contributors_Widget extends WP_Widget {

	function Top_Contributors_Widget() {
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'jmetc', 'description' => __('Display Top Contributors.', 'jmetc') );

		/* Widget control settings. */
		$control_ops = array( 'width' => 250, 'height' => 350, 'id_base' => 'jmetc-widget' );

		/* Create the widget. */
		$this->WP_Widget( 'jmetc-widget', __('Top Contributors', 'jmetc'), $widget_ops, $control_ops );
	}

	function widget( $args, $instance ) {
		extract( $args );
		
		echo $before_widget;
		if($instance['title'] != '') {
			echo $before_title . $instance['title'] . $after_title;
		}
		jme_top_contributors();
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags for title and name to remove HTML (important for text inputs). */
		foreach($new_instance as $key => $val) {
			$instance[$key] = strip_tags( $new_instance[$key] );
		}
		return $instance;
	}

	function form( $instance ) {
		
		/* Set up some default widget settings. */
		$defaults = array( 	'title' => __( '', 'jmetc' ) );
							
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title:', 'jmetc'); ?></label>
			<input type="text" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:225px;" />
		</p>
		<p><?php _e('Widget options can be found under Settings > Top Contributors.', 'jmetc'); ?></p>

	<?php
	}
}

function jme_tc_activate() {
	global $wpdb;
	$tcOptions = array(	'limit' => 10,
						'show_count' => 1,
						'show_avatar' => 1,
						'show_icon' => 1,
						'avatar_size' => 40,
						'exclude_author' => '',
						'format' => 1,
						'avatar_rating' => 'g',
						'cache' => array( 1 => '', 2 => ''),
						'toplist' => array(),
						'icon' => 'star.png',
						'time_limit_type' => 1,
						'time_limit_int' => 1,
						'time_limit_interval' => 3,
						'time_limit_this' => 2
					);
	if (!get_option('jmetc')) {
		add_option('jmetc', $tcOptions);
	}	
	@$wpdb->query("ALTER TABLE $wpdb->comments ADD INDEX `comment_author_email` ( `comment_author_email` )");
}
	
function jme_tc_deactivate() {
	global $wpdb;
	//delete_option('jmetc');
	@$wpdb->query("ALTER TABLE $wpdb->comments DROP INDEX `comment_author_email`");
}

function jme_tc_refresh_cache() {
	global $wpdb, $jmetc_options;
	
	$jmetc_options['toplist'] = array();
	
	$author_sql = "";
	
	if($jmetc_options['exclude_author'] != '') {
		$authorlist = array();
		$authors = explode(",",$jmetc_options['exclude_author']);
		for($i=0;$i<count($authors);$i++) {
			if(trim($authors[$i]) != '') {
				$authorlist[] = strtolower(trim($authors[$i]));
			}
		}
		$al = implode("','",$authorlist);		
		if($al != '') {
			$author_sql = "AND LOWER(comment_author_email) NOT IN('" . $al . "')";
		}
	}
	
	$timeInterval = "";
	$currenttime = time();
	
	if($jmetc_options['time_limit_type'] == 2) {
		$basetime = 60 * 60 * 24; // 1 day of time
		
		if ($jmetc_options['time_limit_interval'] == 1) {
			$time = $basetime; // last day
		} else if ($jmetc_options['time_limit_interval'] == 2) {
			$time = $basetime * 7; // last week
		} else if ($jmetc_options['time_limit_interval'] == 3) {
			$time = $basetime * 30; // last month
		} else if ($jmetc_options['time_limit_interval'] == 4) {
			$time = $basetime * 365; // last year
		}
		
		$int = (is_numeric(trim($jmetc_options['time_limit_int']))) ? trim($jmetc_options['time_limit_int']) : 1;
		$time = $time * $int; // multiply by number of intervals
		
		$subTime = $currenttime - $time;
		$dateLimit = gmdate('Y-m-d H:i:s', $subTime);
		$timeInterval = "AND comment_date_gmt > '" . $dateLimit . "'";
	}
	if($jmetc_options['time_limit_type'] == 3) {
		
		// define current day, month, year
		$currentDay = gmdate('j'); 
		$currentMonth = gmdate('n');
		$currentYear = gmdate('Y');
		
		// set current to the time
		$theDay = $currentDay;
		$theMonth = $currentMonth;
		$theYear = $currentYear;
			
		/* if this week, get the start of week */
		if($jmetc_options['time_limit_this'] == 1) {
			// set array of how many days into the week, Sunday = 0
			$weekArray = array(	'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6 );
	
			$currentDayOfWeek = gmdate('l'); // get day of week
			
			// get starting day of week. Used this method to support PHP4, only PHP5 can get current day directly.
			$theDay = $theDay - $weekArray[$currentDayOfWeek];
		}
		/* if this month, set day = 1 */
		if($jmetc_options['time_limit_this'] == 2) {
			$theDay = 1;
		}
		/* if this year, set day, month = 1 */
		if($jmetc_options['time_limit_this'] == 3) {
			$theDay = 1;
			$theMonth = 1;
		}
			
		$subTime = mktime(0, 0, 0, $theMonth, $theDay, $theYear);
		$dateLimit = gmdate('Y-m-d H:i:s', $subTime);
		$timeInterval = "AND comment_date_gmt > '" . $dateLimit . "'";
	}
	
	$query = "	SELECT 	COUNT(comment_ID) AS `comment_count`,
						comment_author,
						comment_author_email,
						comment_author_url
					FROM $wpdb->comments
					WHERE comment_approved = 1
					AND comment_type = ''
					$author_sql
					$timeInterval
					GROUP BY comment_author_email
					ORDER BY comment_count DESC
					LIMIT $jmetc_options[limit]
				";	
					
	$gettc = $wpdb->get_results( $query );
		
	
	if($jmetc_options['format'] == 1) {
			
		$cache[1] = '<div class="top-contributors">';
		if($gettc) {
			foreach($gettc as $tc) {
				$gavatar = md5(strtolower(trim($tc->comment_author_email))) . '?s=' . $jmetc_options['avatar_size'] . '&d=mm&r=' . $jmetc_options['avatar_rating'];
				$cache[1] .= '<div class="list">';
				if($tc->comment_author_url != '') {
					$username = '<a href="' . $tc->comment_author_url . '">' . $tc->comment_author . '</a>';
				} else {
					$username = $tc->comment_author;
				}					
				if($jmetc_options['show_avatar'] == 1) {
					$cache[1] .= '<img src="http://www.gravatar.com/avatar/' . $gavatar . '" width="' . $jmetc_options['avatar_size'] . '" height="' . $jmetc_options['avatar_size'] . '" />';
				}
				$cache[1] .= '<div class="tc-user">' . $username . '</div>';
				if($jmetc_options['show_count'] == 1) {
					$cache[1] .= '<div class="tc-count">' . number_format($tc->comment_count);
					$cache[1] .= sprintf( _n("%s comment", "%s comments", $tc->comment_count), number_format($tc->comment_count), 'jmetc');
					$cache[1] .= '</div>';
				}
				$cache[1] .= '<div style="clear:both;"></div></div>';
				$jmetc_options['toplist'][] = $tc->comment_author;
			}
		} else {
			$cache[1] .= sprintf( _e('No contributors found.', 'jmetc'));
		}
		$cache[1] .= '</div>';
	
	
		$jmetc_options['cache'][1] = $cache[1];	
	}
	
	if($jmetc_options['format'] == 2) {
	
		$cache[2] = '<div class="top-contributors">';
		if($gettc) {
			foreach($gettc as $tc) {
				$gavatar = md5(strtolower(trim($tc->comment_author_email))) . '?s=' . $jmetc_options['avatar_size'] . '&d=mm&r=' . $jmetc_options['avatar_rating'];
				$cache[2] .= '<div class="gallery">';
			
				$cache[2] .= '<img title="' . $tc->comment_author;
				if($jmetc_options['show_count'] == 1) {
					$cache[2] .= '<br />';
					$cache[2] .= sprintf(_n("%s comment", "%s comments", $tc->comment_count), number_format($tc->comment_count), 'jmetc');
				}
				$cache[2] .= '" src="http://www.gravatar.com/avatar/' . $gavatar . '" width="' . $jmetc_options['avatar_size'] . '" height="' . $jmetc_options['avatar_size'] . '" />';
				$cache[2] .= '</div>';
				$jmetc_options['toplist'][] = $tc->comment_author;
			}
		} else {
			$cache[2] .= sprintf( _e('No contributors found.', 'jmetc'));
		}
		$cache[2] .= '<div style="clear:both;"></div></div>';
		
		$jmetc_options['cache'][2] = $cache[2];
	}
	
	update_option('jmetc', $jmetc_options);
}

function jme_top_contributors() {
	global $jmetc_options;
	//$jmetc_options = get_option('jmetc');
	
	if( ($jmetc_options['format'] == 1 && $jmetc_options['cache'][1] == '') ||
		($jmetc_options['format'] == 2 && $jmetc_options['cache'][2] == '') ) {
			
		jme_tc_refresh_cache();
	}	
	
	if($jmetc_options['format'] == 1) echo $jmetc_options['cache'][1];
	if($jmetc_options['format'] == 2) echo $jmetc_options['cache'][2];
		
}

function jme_add_options_page() {
	add_options_page( __('Top Contributors', 'jmetc'), __('Top Contributors', 'jmetc'), 'edit_themes', basename(__FILE__), 'jme_the_options_page');
}
	
function jme_the_options_page() {
	global $jmetc_options;

	if($_POST['save_settings']) {
		//$jmetc_options = get_option('jmetc');
		$jmetc_options['limit'] = $_POST['limit'];
		$jmetc_options['show_count'] = ($_POST['show_count'] == 1) ? 1 : 0;
		$jmetc_options['exclude_author'] = $_POST['exclude_author'];
		$jmetc_options['show_avatar'] = ($_POST['show_avatar'] == 1) ? 1 : 0;
		$jmetc_options['avatar_size'] = $_POST['avatar_size'];
		$jmetc_options['format'] = $_POST['format'];
		$jmetc_options['show_icon'] = ($_POST['show_icon'] == 1) ? 1 : 0;
		$jmetc_options['avatar_rating'] = $_POST['avatar_rating'];
		$jmetc_options['icon'] = $_POST['icon'];
		//$jmetc_options['cache'][1] = '';
		//$jmetc_options['cache'][2] = '';
		$jmetc_options['time_limit_type'] = $_POST['time_limit_type'];
		$jmetc_options['time_limit_int'] = $_POST['time_limit_int'];
		$jmetc_options['time_limit_interval'] = $_POST['time_limit_interval'];
		$jmetc_options['time_limit_this'] = $_POST['time_limit_this'];
		update_option('jmetc', $jmetc_options);
		jme_tc_refresh_cache();
		echo '<div id="message" class="updated fade"><p>Your options have been saved.</p></div>';
	}
	
	//$jmetc_options = get_option('jmetc');
	?>
	<div class="wrap">
	<h2><?php _e('Top Contributors', 'jmetc'); ?></h2>
	<p></p>
	<h3><?php _e('Widget Options', 'jmetc'); ?></h3>
	<form method="post" name="jme_options">
	<p><label for="limit"><?php _e('Number of Contributors:', 'jmetc'); ?> 
		<input style="width:50px;" type="text" id="limit" name="limit" value="<?php echo htmlentities($jmetc_options['limit']); ?>" /></label></p>
		
	<p><label for="show_count"><input type="checkbox" id="show_count" name="show_count" value="1"<?php if($jmetc_options['show_count'] == 1) echo ' checked="checked"'; ?> />
			<?php _e('Display Comment Count', 'jmetc'); ?></label></p>

	<p><label for="show_avatar"><input type="checkbox" id="show_avatar" name="show_avatar" value="1"<?php if($jmetc_options['show_avatar'] == 1) echo ' checked="checked"'; ?> />
			<?php _e('Display User Avatar', 'jmetc'); ?></label></p>	

	<p><label for="avatar_size"><?php _e('Avatar Size in pixels:', 'jmetc'); ?>	<input style="width:50px;" type="text" id="avatar_size" name="avatar_size" value="<?php echo htmlentities($jmetc_options['avatar_size']); ?>" /></label></p>

	<p><label for="avatar_rating"><?php _e('Avatar Rating:', 'jmetc'); ?> <br />
		<input type="radio" name="avatar_rating" value="g"<?php if($jmetc_options['avatar_rating'] == 'g') echo ' checked="checked"'; ?>> G &nbsp;
		<input type="radio" name="avatar_rating" value="pg"<?php if($jmetc_options['avatar_rating'] == 'pg') echo ' checked="checked"'; ?>> PG &nbsp;
		<input type="radio" name="avatar_rating" value="r"<?php if($jmetc_options['avatar_rating'] == 'r') echo ' checked="checked"'; ?>> R &nbsp;
		<input type="radio" name="avatar_rating" value="x"<?php if($jmetc_options['avatar_rating'] == 'x') echo ' checked="checked"'; ?>> X &nbsp;</label></p>

	<p><b><?php _e('Limit comments from...', 'jmetc'); ?></b><br />
		<label for="time_limit_type1"><input type="radio" id="time_limit_type1" name="time_limit_type" value="1"<?php if($jmetc_options['time_limit_type'] == 1) echo ' checked="checked"'; ?>> <?php _e('All Time', 'jmetc'); ?></label>
	<br />
		<label for="time_limit_type2"><input type="radio" id="time_limit_type2" name="time_limit_type" value="2"<?php if($jmetc_options['time_limit_type'] == 2) echo ' checked="checked"'; ?>> <?php _e('The Last', 'jmetc'); ?> </label><input type="text" style="width:40px;" id="time_limit_int" name="time_limit_int" value="<?php echo $jmetc_options['time_limit_int']; ?>" /> <select id="time_limit_interval" name="time_limit_interval">
			<option value="1"<?php if($jmetc_options['time_limit_interval'] == 1) echo ' selected="selected"'; ?>><?php _e('day(s)', 'jmetc'); ?> </option>
			<option value="2"<?php if($jmetc_options['time_limit_interval'] == 2) echo ' selected="selected"'; ?>><?php _e('week(s)', 'jmetc'); ?> </option>
			<option value="3"<?php if($jmetc_options['time_limit_interval'] == 3) echo ' selected="selected"'; ?>><?php _e('month(s)', 'jmetc'); ?> </option>
			<option value="4"<?php if($jmetc_options['time_limit_interval'] == 4) echo ' selected="selected"'; ?>><?php _e('year(s)', 'jmetc'); ?> </option>
		</select>
	<br />
		<label for="time_limit_type3"><input type="radio" id="time_limit_type3" name="time_limit_type" value="3"<?php if($jmetc_options['time_limit_type'] == 3) echo ' checked="checked"'; ?>> <?php _e('Only This', 'jmetc'); ?> </label><select id="time_limit_this" name="time_limit_this">
			<option value="1"<?php if($jmetc_options['time_limit_this'] == 1) echo ' selected="selected"'; ?>><?php _e('week', 'jmetc'); ?> </option>
			<option value="2"<?php if($jmetc_options['time_limit_this'] == 2) echo ' selected="selected"'; ?>><?php _e('month', 'jmetc'); ?> </option>
			<option value="3"<?php if($jmetc_options['time_limit_this'] == 3) echo ' selected="selected"'; ?>><?php _e('year', 'jmetc'); ?> </option>
		</select>
	</p>


	<p><label for="exclude_author"><?php _e('Exclude Users by their <code>Email Address</code> (separate by comma):', 'jmetc'); ?><br />
		<textarea style="width:400px;height:50px;" id="exclude_author" name="exclude_author"><?php echo htmlspecialchars(stripslashes($jmetc_options['exclude_author'])); ?></textarea></label></p>

	<h3><?php _e('Widget Format:', 'jmetc'); ?></h3>
	<p>
		<div style="float:left;margin:0 20px 0;">
		<label for="format1"><input type="radio" id="format1" name="format" value="1"<?php if($jmetc_options['format'] == 1) echo ' checked="checked"'; ?>> <?php _e('List Style', 'jmetc'); ?></label><br /><img src="<?php echo JMETC_PLUGINPATH; ?>images/list.png" />
		</div>
		<div style="float:left;">
		<label for="format2"><input type="radio" id="format2" name="format" value="2"<?php if($jmetc_options['format'] == 2) echo ' checked="checked"'; ?>> <?php _e('Avatar Gallery Style with tooltips', 'jmetc'); ?></label><br /><img src="<?php echo JMETC_PLUGINPATH; ?>images/gallery.png" />
		</div>
		<div style="clear:both;"></div>		
	</p>
		
	<h3><?php _e('Other Options', 'jmetc'); ?></h3>
	
	<p><label for="show_icon"><input type="checkbox" id="show_icon" name="show_icon" value="1"<?php if($jmetc_options['show_icon'] == 1) echo ' checked="checked"'; ?> />
		<?php _e('Show "Top Contributor Icon" next to Username in comments.', 'jmetc'); ?></label><br />
		<?php _e('This option gives your loyal blog followers and contributors some recognition by adding a little icon next to their name in all of their comments.', 'jmetc'); ?><br />
		<?php _e('By default this is a Star, however it can be changed to any Icon you want by uploading the new image to the plugin image directory <code>../plugins/top-contributors/images</code>.', 'jmetc'); ?></p>

	<p><label for="icon">Icon Image: <input style="width:150px;" type="text" id="icon" name="icon" value="<?php echo htmlentities($jmetc_options['icon']); ?>" /></label> <img src="<?php echo JMETC_PLUGINPATH; ?>images/<?php echo $jmetc_options['icon']; ?>" alt="" title="Top Contributor" /></p>


	<p class="submit"><input type="submit" name="save_settings" value="<?php _e('Save Options', 'jmetc'); ?>" /></p>
	
	</form>
	<p><?php _e('Use the <i>Top Contributors Widget</i> to add the widget to sidebar, or paste this code into your template where you want the widget to display:', 'jmetc'); ?> <br />
	<code>&lt;?php if(function_exists('jme_top_contributors')) { jme_top_contributors(); } ?&gt;</code></p>

	</div>

<?php
}

function jme_top_contributors_header() {
	global $jmetc_options;
	//$jmetc_options = get_option('jmetc');
	if($jmetc_options['format'] == 2) {
		wp_enqueue_script( 'jqdim', JMETC_PLUGINPATH.'js/jquery.dimensions.js', array('jquery'), '' );
		wp_enqueue_script( 'jqtt', JMETC_PLUGINPATH.'js/jquery.tooltip.js', array('jquery'), '' );
	}
}
function jme_top_contributors_tooltip() {
	global $jmetc_options;
	//$jmetc_options = get_option('jmetc');
	if($jmetc_options['format'] == 2) {
		echo "<script type=\"text/javascript\">jQuery(document).ready(function($) { $('.top-contributors img').tooltip({delay:0,showURL:false,}); });</script>\n";
	}
	echo "<link rel=\"stylesheet\" href=\"" . JMETC_PLUGINPATH . "css/tooltip.css\" type=\"text/css\" />\n";
}

function jme_tc_icon($user) {
	global $jmetc_options;
	//$jmetc_options = get_option('jmetc');	
	$string = $user;
	if($jmetc_options['show_icon'] == 1) {
		if(in_array(strip_tags($user), $jmetc_options['toplist'])) {
			$string = $user . ' <img src="' . JMETC_PLUGINPATH . 'images/' . $jmetc_options['icon'] . '" alt="" title="Top Contributor" />';
		}
	}
	return $string;
}

function jmetc_settings_link($links, $file) {
	static $this_plugin;
 
	if( !$this_plugin ) $this_plugin = plugin_basename(__FILE__);
 
	if( $file == $this_plugin ){
		$settings_link = '<a href="options-general.php?page='.dirname(plugin_basename(__FILE__)).'.php">' . __('Settings') . '</a>';
		$links = array_merge( array($settings_link), $links); // before other links
	}
	return $links;
}


add_action('admin_menu', 'jme_add_options_page');
add_action('widgets_init', 'top_contributors_load_widget');
add_action('init', 'jme_top_contributors_header');
add_action('wp_head', 'jme_top_contributors_tooltip');

add_filter('plugin_action_links', 'jmetc_settings_link', 10, 2);
add_filter('get_comment_author_link','jme_tc_icon');

add_action('delete_comment','jme_tc_refresh_cache');
add_action('wp_set_comment_status','jme_tc_refresh_cache');
add_action('comment_post','jme_tc_refresh_cache');

register_activation_hook( __FILE__, jme_tc_activate);
register_deactivation_hook( __FILE__, jme_tc_deactivate);
?>