<?php
/**
 * Plugin Name:       Volunteer Impact Tracker
 * Plugin URI:         https://github.com/doodersrage/volunteer-impact-tracker
 * Description:        Log volunteer hours against opportunities, approve self-reported time, and generate reports and printable certificates for grant applications and board reporting.
 * Version:            1.0.1
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Author:             Your Organization
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        volunteer-impact-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'VIT_VERSION', '1.0.1' );
define( 'VIT_PLUGIN_FILE', __FILE__ );
define( 'VIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VIT_TABLE_HOURS', 'vit_hours' );
define( 'VIT_CPT_OPPORTUNITY', 'vit_opportunity' );
define( 'VIT_CAPABILITY', 'manage_vit_volunteers' );
define( 'VIT_MAX_HOURS_PER_ENTRY', 24 );

require_once VIT_PLUGIN_DIR . 'includes/class-vit-activator.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-cpt.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-settings.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-admin.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-frontend.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-reports.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-certificate.php';

register_activation_hook( __FILE__, array( 'VIT_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VIT_Activator', 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 */
function vit_init() {
	load_plugin_textdomain( 'volunteer-impact-tracker', false, dirname( plugin_basename( VIT_PLUGIN_FILE ) ) . '/languages' );

	VIT_CPT::init();
	VIT_Settings::init();
	VIT_Admin::init();
	VIT_Frontend::init();
	VIT_Reports::init();
	VIT_Certificate::init();
}
add_action( 'plugins_loaded', 'vit_init' );

/**
 * Give administrators the plugin capability by default on activation,
 * and make sure it stays attached to the administrator role going forward.
 */
function vit_grant_admin_capability() {
	$role = get_role( 'administrator' );
	if ( $role && ! $role->has_cap( VIT_CAPABILITY ) ) {
		$role->add_cap( VIT_CAPABILITY );
	}
}
add_action( 'admin_init', 'vit_grant_admin_capability' );

/**
 * Today's date in the site timezone (Y-m-d).
 *
 * @return string
 */
function vit_today() {
	return current_time( 'Y-m-d' );
}

/**
 * Sanitize a Y-m-d date string; return empty string if invalid.
 *
 * @param string $date Raw date.
 * @return string
 */
function vit_sanitize_date( $date ) {
	$date = sanitize_text_field( $date );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return '';
	}
	$parts = array_map( 'intval', explode( '-', $date ) );
	if ( count( $parts ) !== 3 || ! checkdate( $parts[1], $parts[2], $parts[0] ) ) {
		return '';
	}
	return $date;
}

/**
 * Sanitize hours for a single entry. Returns 0 if out of range.
 *
 * @param mixed $hours Raw hours value.
 * @return float
 */
function vit_sanitize_hours( $hours ) {
	$hours = round( (float) $hours, 2 );
	if ( $hours <= 0 || $hours > VIT_MAX_HOURS_PER_ENTRY ) {
		return 0;
	}
	return $hours;
}

/**
 * Count pending hour entries (for menu badges / notices).
 *
 * @return int
 */
function vit_pending_count() {
	global $wpdb;
	$table = $wpdb->prefix . VIT_TABLE_HOURS;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
}

/**
 * Format an opportunity option label (title + optional date).
 *
 * @param WP_Post $post Opportunity post.
 * @return string
 */
function vit_opportunity_label( $post ) {
	$date = get_post_meta( $post->ID, '_vit_date', true );
	if ( $date ) {
		return sprintf( '%s (%s)', $post->post_title, $date );
	}
	return $post->post_title;
}
