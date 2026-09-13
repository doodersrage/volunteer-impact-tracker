<?php
/**
 * Plugin Name:       Volunteer Impact Tracker
 * Plugin URI:         https://example.com/volunteer-impact-tracker
 * Description:        Log volunteer hours against opportunities, approve self-reported time, and generate reports and printable certificates for grant applications and board reporting.
 * Version:            1.0.0
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

define( 'VIT_VERSION', '1.0.0' );
define( 'VIT_PLUGIN_FILE', __FILE__ );
define( 'VIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VIT_TABLE_HOURS', 'vit_hours' );
define( 'VIT_CPT_OPPORTUNITY', 'vit_opportunity' );
define( 'VIT_CAPABILITY', 'manage_vit_volunteers' );

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
