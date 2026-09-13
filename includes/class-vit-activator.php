<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation: creates the custom hours-log table
 * and registers the CPT rewrite rules.
 */
class VIT_Activator {

	public static function activate() {
		global $wpdb;

		$table_name      = $wpdb->prefix . VIT_TABLE_HOURS;
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

		update_option( 'vit_db_version', VIT_VERSION );

		// Register CPT before flushing rewrite rules.
		require_once VIT_PLUGIN_DIR . 'includes/class-vit-cpt.php';
		VIT_CPT::register_post_type();
		flush_rewrite_rules();

		// Sensible defaults for settings, only set once.
		if ( false === get_option( 'vit_settings' ) ) {
			add_option(
				'vit_settings',
				array(
					'org_name'          => get_bloginfo( 'name' ),
					'hourly_value'      => 33.49, // Editable in Settings; update to your current benchmark rate.
					'certificate_text'  => __( 'In recognition of your generous service and dedication.', 'volunteer-impact-tracker' ),
					'require_approval'  => 1,
				)
			);
		}
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
