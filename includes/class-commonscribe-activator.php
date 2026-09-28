<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation: creates the custom hours-log table
 * and registers the CPT rewrite rules.
 */
class COMMONSCRIBE_Activator {

	public static function activate() {
		global $wpdb;

		self::migrate_legacy_storage();

		$table_name      = $wpdb->prefix . COMMONSCRIBE_TABLE_HOURS;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			opportunity_id BIGINT(20) UNSIGNED NULL,
			user_id BIGINT(20) UNSIGNED NULL,
			volunteer_name VARCHAR(191) NOT NULL,
			volunteer_email VARCHAR(191) NULL,
			hours DECIMAL(6,2) NOT NULL DEFAULT 0,
			date_served DATE NOT NULL,
			notes TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			approved_by BIGINT(20) UNSIGNED NULL,
			approved_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY opportunity_id (opportunity_id),
			KEY status (status),
			KEY date_served (date_served),
			KEY volunteer_email (volunteer_email)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'commonscribe_db_version', COMMONSCRIBE_VERSION );

		// Register CPT before flushing rewrite rules.
		require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-cpt.php';
		require_once COMMONSCRIBE_PLUGIN_DIR . 'includes/class-commonscribe-settings.php';
		COMMONSCRIBE_CPT::register_post_type();
		flush_rewrite_rules();

		if ( false === get_option( 'commonscribe_settings' ) ) {
			add_option( 'commonscribe_settings', COMMONSCRIBE_Settings::defaults() );
		}

		COMMONSCRIBE_Settings::sync_capabilities();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Move data stored under the pre-1.2.0 vit_ prefix.
	 */
	private static function migrate_legacy_storage() {
		global $wpdb;

		$old_table = $wpdb->prefix . 'vit_hours';
		$new_table = $wpdb->prefix . COMMONSCRIBE_TABLE_HOURS;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time lookup of this plugin's legacy table.
		$old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old_table ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time lookup of this plugin's hours table.
		$new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $new_table ) ) );

		if ( $old_exists === $old_table && $new_exists !== $new_table ) {
			$old_sql = esc_sql( $old_table );
			$new_sql = esc_sql( $new_table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-time rename of this plugin's legacy hours table. Identifiers are escaped.
			$wpdb->query( "RENAME TABLE `{$old_sql}` TO `{$new_sql}`" );
		}

		$legacy_settings = get_option( 'vit_settings', null );
		if ( null !== $legacy_settings && false === get_option( 'commonscribe_settings', false ) ) {
			add_option( 'commonscribe_settings', $legacy_settings );
		}
		delete_option( 'vit_settings' );
		delete_option( 'vit_db_version' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time post type rename for this plugin's opportunities.
		$wpdb->update(
			$wpdb->posts,
			array( 'post_type' => COMMONSCRIBE_CPT_OPPORTUNITY ),
			array( 'post_type' => 'vit_opportunity' ),
			array( '%s' ),
			array( '%s' )
		);

		$meta_map = array(
			'_vit_date'     => '_commonscribe_date',
			'_vit_location' => '_commonscribe_location',
			'_vit_capacity' => '_commonscribe_capacity',
		);
		foreach ( $meta_map as $old_key => $new_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time meta key rename for this plugin.
			$wpdb->update(
				$wpdb->postmeta,
				array( 'meta_key' => $new_key ),
				array( 'meta_key' => $old_key ),
				array( '%s' ),
				array( '%s' )
			);
		}

		if ( function_exists( 'wp_roles' ) && wp_roles() ) {
			foreach ( array_keys( wp_roles()->roles ) as $slug ) {
				$role = get_role( $slug );
				if ( $role && $role->has_cap( 'manage_vit_volunteers' ) ) {
					$role->remove_cap( 'manage_vit_volunteers' );
				}
			}
		}
	}
}
