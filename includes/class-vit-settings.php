<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings: org name, hourly value, workflow, emails, and manager roles.
 */
class VIT_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_vit_save_settings', array( __CLASS__, 'save' ) );
	}

	public static function defaults() {
		return array(
			'org_name'                  => get_bloginfo( 'name' ),
			'hourly_value'              => 33.49,
			'certificate_text'          => __( 'In recognition of your generous service and dedication.', 'volunteer-impact-tracker' ),
			'require_approval'          => 1,
			'require_login'             => 0,
			'notify_admin_pending'      => 1,
			'notify_volunteer_approved' => 0,
			'manager_roles'             => array( 'administrator' ),
		);
	}

	public static function get( $key = null, $default = null ) {
		$settings = wp_parse_args( get_option( 'vit_settings', array() ), self::defaults() );

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
	 * Sync VIT_CAPABILITY onto configured roles; remove from others (except keep cleaning).
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
				if ( ! $role->has_cap( VIT_CAPABILITY ) ) {
					$role->add_cap( VIT_CAPABILITY );
				}
			} elseif ( $role->has_cap( VIT_CAPABILITY ) && 'administrator' !== $slug ) {
				$role->remove_cap( VIT_CAPABILITY );
			}
		}

		// Administrator always retains the capability.
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( VIT_CAPABILITY ) ) {
			$admin->add_cap( VIT_CAPABILITY );
		}
	}

	public static function add_menu() {
		add_submenu_page(
			'vit-volunteers',
			__( 'Volunteer Tracker Settings', 'volunteer-impact-tracker' ),
			__( 'Settings', 'volunteer-impact-tracker' ),
			VIT_CAPABILITY,
			'vit-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			return;
		}
		$settings = self::get();
		$roles    = wp_roles()->roles;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Volunteer Tracker Settings', 'volunteer-impact-tracker' ); ?></h1>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above. ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'volunteer-impact-tracker' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="vit_save_settings">
				<?php wp_nonce_field( 'vit_save_settings', 'vit_settings_nonce' ); ?>

				<h2><?php esc_html_e( 'Organization', 'volunteer-impact-tracker' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="org_name"><?php esc_html_e( 'Organization Name', 'volunteer-impact-tracker' ); ?></label></th>
						<td><input type="text" class="regular-text" id="org_name" name="org_name" value="<?php echo esc_attr( $settings['org_name'] ); ?>">
							<p class="description"><?php esc_html_e( 'Shown on printed certificates and in notification emails.', 'volunteer-impact-tracker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hourly_value"><?php esc_html_e( 'Dollar Value per Volunteer Hour', 'volunteer-impact-tracker' ); ?></label></th>
						<td>
							$<input type="number" step="0.01" min="0" id="hourly_value" name="hourly_value" value="<?php echo esc_attr( $settings['hourly_value'] ); ?>" style="width:100px;">
							<p class="description">
								<?php esc_html_e( 'Used to estimate the in-kind dollar value of volunteer time in reports. Independent Sector publishes an updated national estimate each year — check their current figure and update this field annually rather than relying on the plugin default.', 'volunteer-impact-tracker' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="certificate_text"><?php esc_html_e( 'Certificate Message', 'volunteer-impact-tracker' ); ?></label></th>
						<td>
							<textarea id="certificate_text" name="certificate_text" rows="3" class="large-text"><?php echo esc_textarea( $settings['certificate_text'] ); ?></textarea>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Workflow', 'volunteer-impact-tracker' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Require Approval', 'volunteer-impact-tracker' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="require_approval" name="require_approval" value="1" <?php checked( 1, (int) $settings['require_approval'] ); ?>>
								<?php esc_html_e( 'Self-reported hours must be approved before they count toward reports and certificates.', 'volunteer-impact-tracker' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require Login', 'volunteer-impact-tracker' ); ?></th>
						<td>
							<label>
								<input type="checkbox" id="require_login" name="require_login" value="1" <?php checked( 1, (int) $settings['require_login'] ); ?>>
								<?php esc_html_e( 'Only logged-in users can submit hours via the front-end form.', 'volunteer-impact-tracker' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Email notifications', 'volunteer-impact-tracker' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin notice', 'volunteer-impact-tracker' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="notify_admin_pending" value="1" <?php checked( 1, (int) $settings['notify_admin_pending'] ); ?>>
								<?php esc_html_e( 'Email site admins when a volunteer submits hours that need approval.', 'volunteer-impact-tracker' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Volunteer notice', 'volunteer-impact-tracker' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="notify_volunteer_approved" value="1" <?php checked( 1, (int) $settings['notify_volunteer_approved'] ); ?>>
								<?php esc_html_e( 'Email the volunteer when their hours are approved.', 'volunteer-impact-tracker' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Who can manage volunteers', 'volunteer-impact-tracker' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Roles', 'volunteer-impact-tracker' ); ?></th>
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
										<span class="description"><?php esc_html_e( '(always included)', 'volunteer-impact-tracker' ); ?></span>
										<input type="hidden" name="manager_roles[]" value="administrator">
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Selected roles can access the Volunteers admin screens.', 'volunteer-impact-tracker' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'volunteer-impact-tracker' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function save() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'volunteer-impact-tracker' ) );
		}
		if ( ! isset( $_POST['vit_settings_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vit_settings_nonce'] ) ), 'vit_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'volunteer-impact-tracker' ) );
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

		update_option( 'vit_settings', $settings );
		self::sync_capabilities();

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=vit-settings' ) ) );
		exit;
	}
}
