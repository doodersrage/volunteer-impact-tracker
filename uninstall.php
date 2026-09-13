<?php
/**
 * Fires when the plugin is deleted from wp-admin (not on simple deactivation).
 * Removes the custom table and options. Opportunity posts are left in place
 * since they're ordinary WordPress content the site owner may still want.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'vit_hours';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- table name from trusted constant, uninstall routine.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

delete_option( 'vit_settings' );
delete_option( 'vit_db_version' );

if ( function_exists( 'wp_roles' ) ) {
	foreach ( array_keys( wp_roles()->roles ) as $slug ) {
		$role = get_role( $slug );
		if ( $role ) {
			$role->remove_cap( 'manage_vit_volunteers' );
		}
	}
}
