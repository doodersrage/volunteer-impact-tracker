<?php
/**
 * Plugin Name:       Commonscribe Volunteer Log
 * Plugin URI:         https://github.com/doodersrage/volunteer-impact-tracker
 * Description:        Log volunteer hours, approve self-reports, and generate grant-ready reports and printable certificates.
 * Version:            1.2.0
 * Requires at least:  6.2
 * Requires PHP:       7.4
 * Author:             doodersrage
 * Author URI:         https://github.com/doodersrage
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        commonscribe-volunteer-log
 * Domain Path:        /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'COMMONSCRIBE_VERSION', '1.2.0' );
define( 'COMMONSCRIBE_PLUGIN_FILE', __FILE__ );
define( 'COMMONSCRIBE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COMMONSCRIBE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'COMMONSCRIBE_TABLE_HOURS', 'commonscribe_hours' );
define( 'COMMONSCRIBE_CPT_OPPORTUNITY', 'commonscribe_opportunity' );
define( 'COMMONSCRIBE_CAPABILITY', 'manage_commonscribe_volunteers' );
define( 'COMMONSCRIBE_MAX_HOURS_PER_ENTRY', 24 );
define( 'COMMONSCRIBE_ENTRIES_PER_PAGE', 20 );

require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-activator.php';
require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-cpt.php';
require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-settings.php';
require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-emails.php';
require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-admin.php';
require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-frontend.php';
require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-reports.php';
require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-certificate.php';
require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-dashboard.php';

register_activation_hook( __FILE__, array( 'COMMONSCRIBE_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'COMMONSCRIBE_Activator', 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 */
function commonscribe_init() {
	COMMONSCRIBE_CPT::init();
	COMMONSCRIBE_Settings::init();
	COMMONSCRIBE_Emails::init();
	COMMONSCRIBE_Admin::init();
	COMMONSCRIBE_Frontend::init();
	COMMONSCRIBE_Reports::init();
	COMMONSCRIBE_Certificate::init();
	COMMONSCRIBE_Dashboard::init();
}
add_action( 'plugins_loaded', 'commonscribe_init' );

/**
 * Suggest privacy-policy text for sites that store volunteer personal data.
 */
function commonscribe_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content = sprintf(
		'<p>%1$s</p><p>%2$s</p><p>%3$s</p>',
		esc_html__( 'Commonscribe Volunteer Log stores volunteer names, email addresses, hours served, dates, optional notes, and related opportunity references in a custom database table so your organization can approve time, run reports, and issue certificates.', 'commonscribe-volunteer-log' ),
		esc_html__( 'Certificate links include the volunteer email address and a cryptographic signature. Anyone with the exact link can view the certificate; links should be shared privately with the intended volunteer.', 'commonscribe-volunteer-log' ),
		esc_html__( 'Optional email notifications may send hour-submission or approval messages to site administrators and/or volunteers. Deleting the plugin removes the hours table and plugin settings; opportunity posts are left in place.', 'commonscribe-volunteer-log' )
	);

	wp_add_privacy_policy_content(
		__( 'Commonscribe Volunteer Log', 'commonscribe-volunteer-log' ),
		wp_kses_post( $content )
	);
}
add_action( 'admin_init', 'commonscribe_privacy_policy_content' );

/**
 * Keep the capability on roles configured in Settings (administrator always).
 */
function commonscribe_sync_capabilities() {
	COMMONSCRIBE_Settings::sync_capabilities();
}
add_action( 'admin_init', 'commonscribe_sync_capabilities' );

/**
 * Today's date in the site timezone (Y-m-d).
 *
 * @return string
 */
function commonscribe_today() {
	return current_time( 'Y-m-d' );
}

/**
 * Sanitize a Y-m-d date string; return empty string if invalid.
 *
 * @param string $date Raw date.
 * @return string
 */
function commonscribe_sanitize_date( $date ) {
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
function commonscribe_sanitize_hours( $hours ) {
	$hours = round( (float) $hours, 2 );
	if ( $hours <= 0 || $hours > COMMONSCRIBE_MAX_HOURS_PER_ENTRY ) {
		return 0;
	}
	return $hours;
}

/**
 * Count pending hour entries (for menu badges / notices).
 *
 * @return int
 */
function commonscribe_pending_count() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE status = %s',
			$wpdb->prefix . COMMONSCRIBE_TABLE_HOURS,
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
function commonscribe_get_entry( $id ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM %i WHERE id = %d',
			$wpdb->prefix . COMMONSCRIBE_TABLE_HOURS,
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
function commonscribe_opportunity_label( $post ) {
	$date = get_post_meta( $post->ID, '_commonscribe_date', true );
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
function commonscribe_status_label( $status ) {
	$labels = array(
		'approved' => __( 'Approved', 'commonscribe-volunteer-log' ),
		'pending'  => __( 'Pending', 'commonscribe-volunteer-log' ),
		'rejected' => __( 'Rejected', 'commonscribe-volunteer-log' ),
	);
	return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

/**
 * Hours table name with prefix.
 *
 * @return string
 */
function commonscribe_table() {
	global $wpdb;
	return $wpdb->prefix . COMMONSCRIBE_TABLE_HOURS;
}
