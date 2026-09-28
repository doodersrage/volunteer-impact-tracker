<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings: org name, hourly value, workflow, emails, and manager roles.
 */
class COMMONSCRIBE_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_commonscribe_save_settings', array( __CLASS__, 'save' ) );
	}

	public static function defaults() {
		return array(
			'org_name'                  => get_bloginfo( 'name' ),
			'hourly_value'              => 33.49,
			'certificate_text'          => __( 'In recognition of your generous service and dedication.', 'commonscribe-volunteer-log' ),
			'require_approval'          => 1,
			'require_login'             => 0,
			'notify_admin_pending'      => 1,
			'notify_volunteer_approved' => 0,
			'manager_roles'             => array( 'administrator' ),
		);
	}

	public static function get( $key = null, $default = null ) {
		$settings = wp_parse_args( get_option( 'commonscribe_settings', array() ), self::defaults() );

		if ( ! is_array( $settings['manager_roles'] ) ) {
			$settings['manager_roles'] = array( 'administrator' );
		}
		if ( ! in_array( 'administrator', $settings['manager_roles'], true ) ) {
			$settings['manager_roles'][] = 'administrator';
		}

		if ( null === $key ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Sync COMMONSCRIBE_CAPABILITY onto configured roles; remove from others (except keep cleaning).
	 */
	public static function sync_capabilities() {
		$wanted = self::get( 'manager_roles', array( 'administrator' ) );
		$roles  = wp_roles();
		if ( ! $roles ) {
			return;
		}

		foreach ( array_keys( $roles->roles ) as $slug ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			if ( in_array( $slug, $wanted, true ) ) {
				if ( ! $role->has_cap( COMMONSCRIBE_CAPABILITY ) ) {
					$role->add_cap( COMMONSCRIBE_CAPABILITY );
				}
			} elseif ( $role->has_cap( COMMONSCRIBE_CAPABILITY ) && 'administrator' !== $slug ) {
				$role->remove_cap( COMMONSCRIBE_CAPABILITY );
			}
		}

		// Administrator always retains the capability.
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( COMMONSCRIBE_CAPABILITY ) ) {
			$admin->add_cap( COMMONSCRIBE_CAPABILITY );
		}
	}

	public static function add_menu() {
		add_submenu_page(
			'commonscribe-volunteers',
			__( 'Volunteer Tracker Settings', 'commonscribe-volunteer-log' ),
			__( 'Settings', 'commonscribe-volunteer-log' ),
			COMMONSCRIBE_CAPABILITY,
			'commonscribe-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			return;
		}
		$settings = self::get();
		$roles    = wp_roles()->roles;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Volunteer Tracker Settings', 'commonscribe-volunteer-log' ); ?></h1>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above. ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'commonscribe-volunteer-log' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="commonscribe_save_settings">
				<?php wp_nonce_field( 'commonscribe_save_settings', 'commonscribe_settings_nonce' ); ?>

				<h2><?php esc_html_e( 'Organization', 'commonscribe-volunteer-log' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="org_name"><?php esc_html_e( 'Organization Name', 'commonscribe-volunteer-log' ); ?></label></th>
						<td><input type="text" class="regular-text" id="org_name" name="org_name" value="<?php echo esc_attr( $settings['org_name'] ); ?>">
							<p class="description"><?php esc_html_e( 'Shown on printed certificates and in notification emails.', 'commonscribe-volunteer-log' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hourly_value"><?php esc_html_e( 'Dollar Value per Volunteer Hour', 'commonscribe-volunteer-log' ); ?></label></th>
						<td>
							$<input type="number" step="0.01" min="0" id="hourly_value" name="hourly_value" value="<?php echo esc_attr( $settings['hourly_value'] ); ?>" style="width:100px;">
							<p class="description">
								<?php esc_html_e( 'Used to estimate the in-kind dollar value of volunteer time in reports. Independent Sector publishes an updated national estimate each year — check their current figure and update this field annually rather than relying on the plugin default.', 'commonscribe-volunteer-log' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="certificate_text"><?php esc_html_e( 'Certificate Message', 'commonscribe-volunteer-log' ); ?></label></th>
						<td>
							<textarea id="certificate_text" name="certificate_text" rows="3" class="large-text"><?php echo esc_textarea( $settings['certificate_text'] ); ?></textarea>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Workflow', 'commonscribe-volunteer-log' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Require Approval', 'commonscribe-volunteer-log' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="require_approval" name="require_approval" value="1" <?php checked( 1, (int) $settings['require_approval'] ); ?>>
								<?php esc_html_e( 'Self-reported hours must be approved before they count toward reports and certificates.', 'commonscribe-volunteer-log' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require Login', 'commonscribe-volunteer-log' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="require_login" name="require_login" value="1" <?php checked( 1, (int) $settings['require_login'] ); ?>>
								<?php esc_html_e( 'Only logged-in users can submit hours via the front-end form.', 'commonscribe-volunteer-log' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Email notifications', 'commonscribe-volunteer-log' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin notice', 'commonscribe-volunteer-log' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="notify_admin_pending" value="1" <?php checked( 1, (int) $settings['notify_admin_pending'] ); ?>>
								<?php esc_html_e( 'Email site admins when a volunteer submits hours that need approval.', 'commonscribe-volunteer-log' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Volunteer notice', 'commonscribe-volunteer-log' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="notify_volunteer_approved" value="1" <?php checked( 1, (int) $settings['notify_volunteer_approved'] ); ?>>
								<?php esc_html_e( 'Email the volunteer when their hours are approved.', 'commonscribe-volunteer-log' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Who can manage volunteers', 'commonscribe-volunteer-log' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Roles', 'commonscribe-volunteer-log' ); ?></th>
						<td>
							<?php foreach ( $roles as $slug => $role ) : ?>
								<?php
								$locked = ( 'administrator' === $slug );
								$checked = in_array( $slug, $settings['manager_roles'], true );
								?>
								<label style="display:block;margin-bottom:4px;">
									<input type="checkbox" name="manager_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $checked ); ?> <?php disabled( $locked ); ?>>
									<?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
									<?php if ( $locked ) : ?>
										<span class="description"><?php esc_html_e( '(always included)', 'commonscribe-volunteer-log' ); ?></span>
										<input type="hidden" name="manager_roles[]" value="administrator">
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Selected roles can access the Volunteers admin screens.', 'commonscribe-volunteer-log' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'commonscribe-volunteer-log' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function save() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'commonscribe-volunteer-log' ) );
		}
		if ( ! isset( $_POST['commonscribe_settings_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['commonscribe_settings_nonce'] ) ), 'commonscribe_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'commonscribe-volunteer-log' ) );
		}

		$hourly = isset( $_POST['hourly_value'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['hourly_value'] ) ) : 0;
		if ( $hourly < 0 ) {
			$hourly = 0;
		}

		$manager_roles = array( 'administrator' );
		$raw_roles     = array();
		if ( isset( $_POST['manager_roles'] ) ) {
			$raw_roles = map_deep( wp_unslash( $_POST['manager_roles'] ), 'sanitize_text_field' );
		}
		if ( is_array( $raw_roles ) ) {
			$valid = array_keys( wp_roles()->roles );
			foreach ( $raw_roles as $slug ) {
				$slug = sanitize_key( $slug );
				if ( in_array( $slug, $valid, true ) && ! in_array( $slug, $manager_roles, true ) ) {
					$manager_roles[] = $slug;
				}
			}
		}

		$settings = array(
			'org_name'                  => isset( $_POST['org_name'] ) ? sanitize_text_field( wp_unslash( $_POST['org_name'] ) ) : '',
			'hourly_value'              => round( $hourly, 2 ),
			'require_approval'          => isset( $_POST['require_approval'] ) ? 1 : 0,
			'require_login'             => isset( $_POST['require_login'] ) ? 1 : 0,
			'notify_admin_pending'      => isset( $_POST['notify_admin_pending'] ) ? 1 : 0,
			'notify_volunteer_approved' => isset( $_POST['notify_volunteer_approved'] ) ? 1 : 0,
			'certificate_text'          => isset( $_POST['certificate_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['certificate_text'] ) ) : '',
			'manager_roles'             => $manager_roles,
		);

		update_option( 'commonscribe_settings', $settings );
		self::sync_capabilities();

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=commonscribe-settings' ) ) );
		exit;
	}
}
