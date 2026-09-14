<?php
/**
 * Plugin Name:       Volunteer Impact Tracker
 * Plugin URI:         https://github.com/doodersrage/volunteer-impact-tracker
 * Description:        Log volunteer hours, approve self-reports, and generate grant-ready reports and printable certificates.
 * Version:            1.1.3
 * Requires at least:  6.2
 * Requires PHP:       7.4
 * Author:             doodersrage
 * Author URI:         https://github.com/doodersrage
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        volunteer-impact-tracker
 * Domain Path:        /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'VIT_VERSION', '1.1.3' );
define( 'VIT_PLUGIN_FILE', __FILE__ );
define( 'VIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VIT_TABLE_HOURS', 'vit_hours' );
define( 'VIT_CPT_OPPORTUNITY', 'vit_opportunity' );
define( 'VIT_CAPABILITY', 'manage_vit_volunteers' );
define( 'VIT_MAX_HOURS_PER_ENTRY', 24 );
define( 'VIT_ENTRIES_PER_PAGE', 20 );

require_once VIT_PLUGIN_DIR . 'includes/class-vit-activator.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-cpt.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-settings.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-emails.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-admin.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-frontend.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-reports.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-certificate.php';
require_once VIT_PLUGIN_DIR . 'includes/class-vit-dashboard.php';

register_activation_hook( __FILE__, array( 'VIT_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VIT_Activator', 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 */
function vit_init() {
	VIT_CPT::init();
	VIT_Settings::init();
	VIT_Emails::init();
	VIT_Admin::init();
	VIT_Frontend::init();
	VIT_Reports::init();
	VIT_Certificate::init();
	VIT_Dashboard::init();
}
add_action( 'plugins_loaded', 'vit_init' );

/**
 * Suggest privacy-policy text for sites that store volunteer personal data.
 */
function vit_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content = sprintf(
		'<p>%1$s</p><p>%2$s</p><p>%3$s</p>',
		esc_html__( 'Volunteer Impact Tracker stores volunteer names, email addresses, hours served, dates, optional notes, and related opportunity references in a custom database table so your organization can approve time, run reports, and issue certificates.', 'volunteer-impact-tracker' ),
		esc_html__( 'Certificate links include the volunteer email address and a cryptographic signature. Anyone with the exact link can view the certificate; links should be shared privately with the intended volunteer.', 'volunteer-impact-tracker' ),
		esc_html__( 'Optional email notifications may send hour-submission or approval messages to site administrators and/or volunteers. Deleting the plugin removes the hours table and plugin settings; opportunity posts are left in place.', 'volunteer-impact-tracker' )
	);

	wp_add_privacy_policy_content(
		__( 'Volunteer Impact Tracker', 'volunteer-impact-tracker' ),
		wp_kses_post( $content )
	);
}
add_action( 'admin_init', 'vit_privacy_policy_content' );

/**
 * Keep the capability on roles configured in Settings (administrator always).
 */
function vit_sync_capabilities() {
	VIT_Settings::sync_capabilities();
}
add_action( 'admin_init', 'vit_sync_capabilities' );

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
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE status = %s',
			$wpdb->prefix . VIT_TABLE_HOURS,
			'pending'
		)
	);
}

/**
 * Fetch a single hours entry by ID.
 *
 * @param int $id Entry ID.
 * @return object|null
 */
function vit_get_entry( $id ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM %i WHERE id = %d',
			$wpdb->prefix . VIT_TABLE_HOURS,
			absint( $id )
		)
	);
	return $row ? $row : null;
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

/**
 * Translated label for an entry status slug.
 *
 * @param string $status Status slug.
 * @return string
 */
function vit_status_label( $status ) {
	$labels = array(
		'approved' => __( 'Approved', 'volunteer-impact-tracker' ),
		'pending'  => __( 'Pending', 'volunteer-impact-tracker' ),
		'rejected' => __( 'Rejected', 'volunteer-impact-tracker' ),
	);
	return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

/**
 * Hours table name with prefix.
 *
 * @return string
 */
function vit_table() {
	global $wpdb;
	return $wpdb->prefix . VIT_TABLE_HOURS;
}
