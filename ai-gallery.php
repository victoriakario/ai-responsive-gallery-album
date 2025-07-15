<?php

 /*
Plugin Name: Responsive Gallery Album (Gallery shortcode) by AugustInfotech
Plugin URI: 
Description: AugustInfotech Responsive Gallery Album for WordPress
Version: 1.50
Text Domain: aigallery
Author: August Infotech
Adjusted by: Victoria Kariolic
Author URI:
*/

// Define plugin paths using trailing slashes for consistency and compatibility
define('AI_DIR_PATH', trailingslashit(plugin_dir_path(__FILE__)));
define('AI_URL_PATH', trailingslashit(plugin_dir_url(__FILE__)));

$upload = wp_upload_dir();

define('AI_GALLERY_DIR_PATH', trailingslashit($upload['basedir']) . 'al_gallery_files');
define('AI_GALLERY_URL_PATH', trailingslashit($upload['baseurl']) . 'al_gallery_files');
define('AI_GALLERY_THUMB_DIR_PATH', AI_GALLERY_DIR_PATH . 'al_gallery_thumb_files');
define('AI_GALLERY_THUMB_URL_PATH', AI_GALLERY_URL_PATH . 'al_gallery_thumb_files');
define('AI_PHOTO_DIR_PATH', AI_GALLERY_DIR_PATH . 'ai_photo_files');
define('AI_PHOTO_URL_PATH', AI_GALLERY_URL_PATH . 'ai_photo_files');
define('AI_PHOTO_THUMB_DIR_PATH', AI_GALLERY_DIR_PATH . 'ai_photo_files/al_photo_thumb_files');
define('AI_PHOTO_THUMB_URL_PATH', AI_GALLERY_URL_PATH . 'ai_photo_files/al_photo_thumb_files');

add_action('plugins_loaded', 'ai_gallery_init');


/**
 * Initialize plugin textdomain
 */
function ai_gallery_init() {
	load_plugin_textdomain('aigallery', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

/**
 * Register activation and uninstall hooks
 */
register_activation_hook(__FILE__, 'ai_add_gallery_table');

if (function_exists('register_uninstall_hook')) {
	register_uninstall_hook(__FILE__, 'ai_drop_gallery_table');
}

// Include shortcode logic
require_once AI_DIR_PATH . 'album-shortcode.php';

// Setup Admin Menu
add_action('admin_menu', 'ai_gallery_setting');

function ai_gallery_setting() {
	add_menu_page('AI Gallery', 'AI Gallery', 'manage_options', 'ai_gallery', 'ai_listing_album', '', 28.5);
	add_submenu_page('ai_gallery', 'Add Album', 'Add New Album', 'manage_options', 'ai_album', 'ai_add_new_album');
	add_submenu_page('', 'Photos', 'Photos', 'manage_options', 'ai_listing_photos', 'ai_listing_photos');
	add_submenu_page('', 'Add Photos', 'Add New Photos', 'manage_options', 'ai_new_photos', 'ai_add_new_photos');
	add_action('admin_enqueue_scripts', 'ai_admin_enqueue_scripts');

	$paths = [
		AI_GALLERY_DIR_PATH,
		AI_GALLERY_THUMB_DIR_PATH,
		AI_PHOTO_DIR_PATH,
		AI_PHOTO_THUMB_DIR_PATH
	];

	foreach ($paths as $path) {
		if (!file_exists($path)) {
			wp_mkdir_p($path); // more robust alternative to mkdir
		}
	}
}

// Add menu icon styling
add_action('admin_head', 'ai_rg_add_menu_icons_styles');
function ai_rg_add_menu_icons_styles() {
	echo "<style>#adminmenu .toplevel_page_ai_gallery div.wp-menu-image:before { content: '\f161'; }</style>";
}

// Enqueue admin scripts
function ai_admin_enqueue_scripts() {
	$screen = get_current_screen();
	if (strpos($screen->id, 'ai_gallery') !== false) {
		wp_enqueue_script('jquery.validate', AI_URL_PATH . 'js/jquery.validate.js', ['jquery'], null, true);
		wp_enqueue_script('jquery.microgallery', AI_URL_PATH . 'js/jquery.microgallery.js', ['jquery'], null, true);
	}
}

// Database Table Creation
function ai_add_gallery_table() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$album_table = $wpdb->prefix . 'ai_album';
	$photo_table = $wpdb->prefix . 'ai_photos';

	$sql_album = "CREATE TABLE $album_table (
		album_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		album_title VARCHAR(100) NULL,
		album_date DATE NULL,
		album_cover_image VARCHAR(255) NULL,
		album_slug VARCHAR(100) NULL,
		album_visible TINYINT(1) NOT NULL DEFAULT 1,
		album_order INT NOT NULL,
		PRIMARY KEY (album_id)
	) $charset_collate;";

	$sql_photo = "CREATE TABLE $photo_table (
		photo_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		photo_album_id BIGINT UNSIGNED NOT NULL,
		photo_title VARCHAR(100) NULL,
		photo_date DATE NULL,
		photo_filename VARCHAR(255) NULL,
		photo_slug VARCHAR(100) NULL,
		photo_visible TINYINT(1) NOT NULL DEFAULT 1,
		photo_order INT NOT NULL,
		PRIMARY KEY (photo_id)
	) $charset_collate;";

	dbDelta($sql_album);
	dbDelta($sql_photo);
}

// Database Table Removal
function ai_drop_gallery_table() {
	global $wpdb;
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_album");
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_photos");
}

// Include plugin admin pages
function ai_listing_album() {
	include AI_DIR_PATH . 'albumlist.php';
}
function ai_add_new_album() {
	include AI_DIR_PATH . 'newalbum.php';
}
function ai_listing_photos() {
	include AI_DIR_PATH . 'photolist.php';
}
function ai_add_new_photos() {
	include AI_DIR_PATH . 'newphoto.php';
}

// AJAX album reordering with permission and nonce checks
add_action('wp_ajax_ai_album_ajax_updateOrder', 'ai_ajax_albumupdateOrder_callback');
function ai_ajax_albumupdateOrder_callback() {
	check_ajax_referer('ai_gallery_nonce', 'nonce');
	if (!current_user_can('edit_posts')) {
		wp_send_json_error('Unauthorized');
	}
	global $wpdb;
	$order = array_map('intval', $_POST['recordsArray'] ?? []);
	foreach ($order as $i => $id) {
		$wpdb->update($wpdb->prefix . 'ai_album', ['album_order' => $i + 1], ['album_id' => $id]);
	}
	wp_send_json_success();
}

// AJAX photo reordering with permission and nonce checks
add_action('wp_ajax_ai_photo_ajax_updateOrder', 'ai_ajax_photoupdateOrder_callback');
function ai_ajax_photoupdateOrder_callback() {
	check_ajax_referer('ai_gallery_nonce', 'nonce');
	if (!current_user_can('edit_posts')) {
		wp_send_json_error('Unauthorized');
	}
	global $wpdb;
	$order = array_map('intval', $_POST['recordsArray'] ?? []);
	foreach ($order as $j => $id) {
		$wpdb->update($wpdb->prefix . 'ai_photos', ['photo_order' => $j + 1], ['photo_id' => $id]);
	}
	wp_send_json_success();
}
