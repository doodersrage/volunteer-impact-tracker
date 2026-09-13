<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end self-report form: [vit_log_hours]
 * Submissions land as "pending" (or "approved" automatically if the
 * "Require Approval" setting is turned off).
 */
class VIT_Frontend {

	public static function init() {
		add_shortcode( 'vit_log_hours', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'admin_post_vit_frontend_log_hours', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_vit_frontend_log_hours', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue() {
		if ( is_singular() && has_shortcode( get_post()->post_content, 'vit_log_hours' ) ) {
			wp_enqueue_style( 'vit-frontend', VIT_PLUGIN_URL . 'assets/css/frontend.css', array(), VIT_VERSION );
		}
	}

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'opportunity_id' => '', // Optional: pin the form to one opportunity.
			),
			$atts,
			'vit_log_hours'
		);

		$opportunities = get_posts(
			array(
				'post_type'      => VIT_CPT_OPPORTUNITY,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		$current_user   = wp_get_current_user();
		$prefill_name    = $current_user->exists() ? $current_user->display_name : '';
		$prefill_email   = $current_user->exists() ? $current_user->user_email : '';

		ob_start();

		if ( isset( $_GET['vit_submitted'] ) ) {
			echo '<p class="vit-success">' . esc_html__( 'Thanks! Your hours have been submitted.', 'volunteer-impact-tracker' ) . '</p>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vit-frontend-form">
			<input type="hidden" name="action" value="vit_frontend_log_hours">
			<input type="hidden" name="vit_redirect" value="<?php echo esc_url( get_permalink() ); ?>">
			<?php wp_nonce_field( 'vit_frontend_log_hours', 'vit_frontend_nonce' ); ?>

			<p>
				<label for="vit_volunteer_name"><?php esc_html_e( 'Your Name', 'volunteer-impact-tracker' ); ?> *</label><br>
				<input type="text" required id="vit_volunteer_name" name="volunteer_name" value="<?php echo esc_attr( $prefill_name ); ?>">
			</p>
			<p>
				<label for="vit_volunteer_email"><?php esc_html_e( 'Your Email', 'volunteer-impact-tracker' ); ?> *</label><br>
				<input type="email" required id="vit_volunteer_email" name="volunteer_email" value="<?php echo esc_attr( $prefill_email ); ?>">
			</p>

			<?php if ( ! empty( $atts['opportunity_id'] ) ) : ?>
				<input type="hidden" name="opportunity_id" value="<?php echo esc_attr( absint( $atts['opportunity_id'] ) ); ?>">
			<?php else : ?>
				<p>
					<label for="vit_opportunity"><?php esc_html_e( 'Opportunity', 'volunteer-impact-tracker' ); ?></label><br>
					<select id="vit_opportunity" name="opportunity_id">
						<option value=""><?php esc_html_e( '— General / Unlisted —', 'volunteer-impact-tracker' ); ?></option>
						<?php foreach ( $opportunities as $opp ) : ?>
							<option value="<?php echo esc_attr( $opp->ID ); ?>"><?php echo esc_html( $opp->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>

			<p>
				<label for="vit_hours"><?php esc_html_e( 'Hours', 'volunteer-impact-tracker' ); ?> *</label><br>
				<input type="number" step="0.25" min="0.25" required id="vit_hours" name="hours">
			</p>
			<p>
				<label for="vit_date_served"><?php esc_html_e( 'Date Served', 'volunteer-impact-tracker' ); ?> *</label><br>
				<input type="date" required id="vit_date_served" name="date_served" max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
			</p>
			<p>
				<label for="vit_notes"><?php esc_html_e( 'Notes (optional)', 'volunteer-impact-tracker' ); ?></label><br>
				<textarea id="vit_notes" name="notes" rows="2"></textarea>
			</p>

			<?php
			// Honeypot field against basic spam bots.
			?>
			<p style="position:absolute;left:-9999px;" aria-hidden="true">
				<label for="vit_website">Website</label>
				<input type="text" id="vit_website" name="vit_website" tabindex="-1" autocomplete="off">
			</p>

			<p><button type="submit"><?php esc_html_e( 'Submit Hours', 'volunteer-impact-tracker' ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function handle_submit() {
		if ( ! isset( $_POST['vit_frontend_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vit_frontend_nonce'] ) ), 'vit_frontend_log_hours' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'volunteer-impact-tracker' ) );
		}

		// Honeypot: if filled in, silently pretend success and bail.
		if ( ! empty( $_POST['vit_website'] ) ) {
			self::redirect_back();
		}

		$name  = isset( $_POST['volunteer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['volunteer_name'] ) ) : '';
		$email = isset( $_POST['volunteer_email'] ) ? sanitize_email( wp_unslash( $_POST['volunteer_email'] ) ) : '';
		$hours = isset( $_POST['hours'] ) ? (float) $_POST['hours'] : 0;
		$date  = isset( $_POST['date_served'] ) ? sanitize_text_field( wp_unslash( $_POST['date_served'] ) ) : '';

		if ( empty( $name ) || empty( $email ) || $hours <= 0 || empty( $date ) ) {
			wp_die( esc_html__( 'Please fill in all required fields and go back.', 'volunteer-impact-tracker' ) );
		}

		global $wpdb;
		$table            = $wpdb->prefix . VIT_TABLE_HOURS;
		$require_approval = (int) VIT_Settings::get( 'require_approval', 1 );

		$wpdb->insert(
			$table,
			array(
				'opportunity_id'  => ! empty( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : null,
				'user_id'         => get_current_user_id() ?: null,
				'volunteer_name'  => $name,
				'volunteer_email' => $email,
				'hours'           => $hours,
				'date_served'     => $date,
				'notes'           => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
				'status'          => $require_approval ? 'pending' : 'approved',
				'created_at'      => current_time( 'mysql' ),
				'approved_by'     => $require_approval ? null : 0,
				'approved_at'     => $require_approval ? null : current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		self::redirect_back();
	}

	private static function redirect_back() {
		$redirect = isset( $_POST['vit_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['vit_redirect'] ) ) : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'vit_submitted', '1', $redirect ) );
		exit;
	}
}
