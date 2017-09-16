<?php
/*
Plugin Name: bbP Post First
Description: A member should first post a bbPress topic before (s)he can see the other posts in that forum
Plugin URI: http://casier.eu/wp-dev/bbp-post-first
Author: Pascal Casier
Author URI: http://casier.eu/wp-dev/
Text Domain: bbp-post-first
Version: 1.0.3
License: GPL2
*/

// No direct access
if ( !defined( 'ABSPATH' ) ) exit;

function bbppostfirst_user_has_posted( $user_id, $forum_id ) {
	$user_id = get_current_user_id();
	$forum_id = bbp_get_forum_id();
	$key = 'post_first_forum_ids';
	$arr_value = get_user_meta($user_id, $key, true);
	if (!$arr_value) $arr_value = array();
	foreach ($arr_value as $value) {
		if ($value == $forum_id ) {
			return true;
		}
	}

	return false; 
}; 

function bbppostfirst_filter_bbp_after_filter_key_parse_args( $r ) {
	$forum_id = bbp_get_forum_id();
	// Check if forum has the 'Post First' flag
	if (!get_post_meta( $forum_id, 'bbppostfirst_active', true ) ) {
		return $r;
	}
	
	// Check if user is in a role that is allowed to post anyway
	$bbppostfirst_roles_ok = get_option('bbppostfirst_roles_ok', false);
	global $current_user;
	$userallowed=false;
	foreach($current_user->roles as $role) {
		if (strpos($bbppostfirst_roles_ok, '*'.$role.'*') !== FALSE) {
			// User is allowed thanks to his role
			return $r;
		}
	}
	
	// Let see if user posted already before
	if ( bbppostfirst_user_has_posted( get_current_user_id(), $forum_id ) ) {
		// ok, user posted before
		return $r;
	} else {
		// no post done yet in this forum
		echo'<style type="text/css">
			.bbp-template-notice {
				display:none;
			}
		</style>';
		return false;
	}
}; 
add_filter( "bbp_after_has_topics_parse_args", 'bbppostfirst_filter_bbp_after_filter_key_parse_args', 10, 1 );

function bbppostfirst_get_template_part_slug( $slug, $name ) { 
    if ($name == 'single-forum') {
		$forum_id = bbp_get_forum_id();
		if ( get_post_meta( $forum_id, 'bbppostfirst_active', true ) ) {
			// Check if user is in a role that is allowed to post anyway
			$bbppostfirst_roles_ok = get_option('bbppostfirst_roles_ok', false);
			global $current_user;
			$userallowed=false;
			foreach($current_user->roles as $role) {
				if (strpos($bbppostfirst_roles_ok, '*'.$role.'*') !== FALSE) {
					// User is allowed thanks to his role
					return $esc_attr;
				}
			}
			if ( ! bbppostfirst_user_has_posted( get_current_user_id(), $forum_id ) ) {
				echo '<div style="
					text-align: center;
					font-size: 14px;
					line-height: 300%;
					border-width: 1px;
					border-style: solid;
					padding: 0 .6em;
					margin: 5px 0 15px;
					-webkit-border-radius: 3px;
					border-radius: 3px;
					background-color: #ffffe0;
					border-color: #e6db55;
					color: #000;
					clear: both;
					">';
				echo get_option( 'bbppostfirst_text', 'Please post your own topic first before you can see other responses.' );
				echo '</div>';
			}
		}
	}
}; 
add_action( "get_template_part_content", 'bbppostfirst_get_template_part_slug', 10, 2 ); 

function bbppostfirst_forum_has_post() {
	$a = get_user_meta($user_id, $key, true);
	$forum_id = bbp_get_forum_id();
	if ($a) {
		foreach ($a as $item) {
			if ($item == $forum_id ) {
				return;
			}
		}
	} else {
		$a = array();
	}
	$a[] = $forum_id;
	update_user_meta( get_current_user_id(), 'post_first_forum_ids', $a );
}
add_action( 'bbp_new_topic_post_extras', 'bbppostfirst_forum_has_post' );

/**
* Add column to forum list
*/
function bbppostfirst_edit_forum_column( $columns ) {
	$columns['bbppostfirst'] = 'Post First';
	return $columns;
}
add_filter( 'manage_edit-forum_columns', 'bbppostfirst_edit_forum_column' );

function bbppostfirst_manage_forum_column($column_name, $id) {
	switch ($column_name) {
	case 'bbppostfirst':
		if (bbp_is_forum_category($id)) {
			echo '';
		} else {
			$status = get_post_meta( $id, 'bbppostfirst_active', true );
			if ($status) {
				echo 'Active';
			} else {
				echo '';
			}
		}
		break;
	default:
		break;
	} // end switch
}  
add_action('manage_forum_posts_custom_column', 'bbppostfirst_manage_forum_column', 10, 2);

/**
* Add metabox to forum
*/
function bbppostfirst_forum_metabox() {
	if (bbp_is_forum_category(get_the_ID())) {
		echo __('Not possible for categories', 'bbp-post-first');
	} else {
		$forum_id = get_the_ID();
		// Check if button has just been pressed
		if ( isset($_GET['bbppostfirst'] ) ) {
			if ($_GET['bbppostfirst'] == 'activate' ) {
				update_post_meta( $forum_id, 'bbppostfirst_active', 'yes' );
			}
			if ($_GET['bbppostfirst'] == 'deactivate' ) {
				delete_post_meta( $forum_id, 'bbppostfirst_active' );
			}
		}
		$status = get_post_meta( $forum_id, 'bbppostfirst_active', true );
		$actual_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		if ($status) {
			echo '<a href="' . $actual_link . '&bbppostfirst=deactivate">ACTIVE, click to deactivate</a>';
		} else {
			echo '<a href="' . $actual_link . '&bbppostfirst=activate">Click to activate</a>';
		}
	}
}

function bbppostfirst_attributes_metabox() {
	// Meta data
	add_meta_box(
		'bbppostfirst_forum_metabox',
		__( 'Post First', 'bbp-post-first' ),
		'bbppostfirst_forum_metabox',
		'forum',
		'side'
	);
}
add_action('add_meta_boxes', 'bbppostfirst_attributes_metabox');

/**
* Add Subscriptions menu item under forums
*/
add_action('admin_menu', 'bbptoolkit_subscr_submenu');
function bbptoolkit_subscr_submenu(){
	$confHook = add_submenu_page('edit.php?post_type=forum', 'Post First', 'Post First', 'edit_forums', 'forum_postfirst', 'forum_postfirst_page');
}
/**
* MAIN PAGE
*/
function forum_postfirst_page() {
	// Check for updates
	if ( isset($_POST['optssave']) ) {
		if( !empty($_POST["bbppostfirst_text"]) ) {
			update_option('bbppostfirst_text', $_POST["bbppostfirst_text"]);
		} else {
			delete_option('bbppostfirst_text');
		}
		if (!empty($_POST['bbppostfirst_roles'])) {
			$optionArray = $_POST['bbppostfirst_roles'];
			$fullstr = '*';
			foreach ($optionArray as $optionitem) {
				$fullstr = $fullstr . $optionitem . '*';
			}
			update_option('bbppostfirst_roles_ok', $fullstr);
		} else {
			delete_option('bbppostfirst_roles_ok');
		}	
	}
	// Main page
	echo '<h2>Post First - Configuration</h2>';
	echo '<p>View the forums that have Post First active on the standard "<a href="' . site_url() . '/wp-admin/edit.php?post_type=forum">All Forums</a>" list where a new column has been added.<br>';
	echo 'Edit any forum there and find the box called "Post First" on the right with below the possibility to (de)active the features for this forum.</p>';

	$actual_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	echo '<form action="' . $actual_link . '" method="post">';
	$text = get_option( 'bbppostfirst_text', 'Please post your own topic first before you can see other responses.' );
	echo '<p>Text if not posted yet:<br>';
	echo '&nbsp;&nbsp;<input type="text" name="bbppostfirst_text" value="' . $text . '" size="100" />';
	echo '</p>';

	echo '<p>The following roles can always post:<br>';
	$bbppostfirst_roles_ok = get_option('bbppostfirst_roles_ok', false);
	global $wp_roles;
	$all_roles = array_keys($wp_roles->roles);
	foreach ($all_roles as $myrole) {
		echo '<p>&nbsp;&nbsp;&nbsp;<input type="checkbox" name="bbppostfirst_roles[]" value="'.$myrole.'" ';
		if (strpos($bbppostfirst_roles_ok, '*'.strval($myrole).'*') !== false) { echo 'checked'; }
		echo '>' . $myrole.'</p>';
	}

	echo '</p>';

	echo '<input type="submit" name="optssave" value="'; _e('Save settings', 'bbp-post-first'); echo'" />';	
	echo '</form>';

	echo '<p>"Recalculate" the following forums:<br>';
	echo '&nbsp;&nbsp; Still to be done';
	echo '</p>';

}

?>
