<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings: org name, hourly dollar value, certificate text, approval workflow toggle.
 */
class VIT_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_vit_save_settings', array( __CLASS__, 'save' ) );
	}

	public static function get( $key = null, $default = null ) {
		$settings = wp_parse_args(
			get_option( 'vit_settings', array() ),
			array(
				'org_name'         => get_bloginfo( 'name' ),
				'hourly_value'     => 33.49,
				'certificate_text' => __( 'In recognition of your generous service and dedication.', 'volunteer-impact-tracker' ),
				'require_approval' => 1,
			)
		);

		if ( null === $key ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Volunteer Tracker Settings', 'volunteer-impact-tracker' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'volunteer-impact-tracker' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="vit_save_settings">
				<?php wp_nonce_field( 'vit_save_settings', 'vit_settings_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="org_name"><?php esc_html_e( 'Organization Name', 'volunteer-impact-tracker' ); ?></label></th>
						<td><input type="text" class="regular-text" id="org_name" name="org_name" value="<?php echo esc_attr( $settings['org_name'] ); ?>">
							<p class="description"><?php esc_html_e( 'Shown on printed certificates.', 'volunteer-impact-tracker' ); ?></p>
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
						<th scope="row"><label for="require_approval"><?php esc_html_e( 'Require Approval', 'volunteer-impact-tracker' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" id="require_approval" name="require_approval" value="1" <?php checked( 1, $settings['require_approval'] ); ?>>
								<?php esc_html_e( 'Self-reported hours must be approved by an admin before they count toward reports and certificates.', 'volunteer-impact-tracker' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="certificate_text"><?php esc_html_e( 'Certificate Message', 'volunteer-impact-tracker' ); ?></label></th>
						<td>
							<textarea id="certificate_text" name="certificate_text" rows="3" class="large-text"><?php echo esc_textarea( $settings['certificate_text'] ); ?></textarea>
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

		$settings = array(
			'org_name'         => isset( $_POST['org_name'] ) ? sanitize_text_field( wp_unslash( $_POST['org_name'] ) ) : '',
			'hourly_value'     => isset( $_POST['hourly_value'] ) ? (float) $_POST['hourly_value'] : 0,
			'require_approval' => isset( $_POST['require_approval'] ) ? 1 : 0,
			'certificate_text' => isset( $_POST['certificate_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['certificate_text'] ) ) : '',
		);

		update_option( 'vit_settings', $settings );

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=vit-settings' ) ) );
		exit;
	}
}
