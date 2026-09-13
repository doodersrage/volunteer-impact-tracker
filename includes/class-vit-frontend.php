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
	}

	/**
	 * Enqueue front-end styles when the shortcode actually renders
	 * (works for classic content, widgets, and block themes).
	 */
	private static function enqueue_styles() {
		wp_enqueue_style( 'vit-frontend', VIT_PLUGIN_URL . 'assets/css/frontend.css', array(), VIT_VERSION );
	}

	public static function render_shortcode( $atts ) {
		self::enqueue_styles();

		$atts = shortcode_atts(
			array(
				'opportunity_id' => '', // Optional: pin the form to one opportunity.
			),
			$atts,
			'vit_log_hours'
		);

		$pinned_id = absint( $atts['opportunity_id'] );
		if ( $pinned_id && VIT_CPT_OPPORTUNITY !== get_post_type( $pinned_id ) ) {
			$pinned_id = 0;
		}

		$opportunities = get_posts(
			array(
				'post_type'      => VIT_CPT_OPPORTUNITY,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		$current_user  = wp_get_current_user();
		$prefill_name  = $current_user->exists() ? $current_user->display_name : '';
		$prefill_email = $current_user->exists() ? $current_user->user_email : '';

		ob_start();

		if ( isset( $_GET['vit_submitted'] ) ) {
			$mode = isset( $_GET['vit_mode'] ) ? sanitize_key( wp_unslash( $_GET['vit_mode'] ) ) : '';
			if ( 'approved' === $mode ) {
				echo '<p class="vit-success">' . esc_html__( 'Thanks! Your hours have been recorded.', 'volunteer-impact-tracker' ) . '</p>';
			} else {
				echo '<p class="vit-success">' . esc_html__( 'Thanks! Your hours have been submitted and are waiting for approval.', 'volunteer-impact-tracker' ) . '</p>';
			}
		}
		if ( isset( $_GET['vit_error'] ) ) {
			echo '<p class="vit-error">' . esc_html__( 'Please check your entries and try again. Hours must be between 0.25 and 24, and the date cannot be in the future.', 'volunteer-impact-tracker' ) . '</p>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vit-frontend-form">
			<input type="hidden" name="action" value="vit_frontend_log_hours">
			<input type="hidden" name="vit_redirect" value="<?php echo esc_url( get_permalink() ); ?>">
			<?php wp_nonce_field( 'vit_frontend_log_hours', 'vit_frontend_nonce' ); ?>

			<p>
				<label for="vit_volunteer_name"><?php esc_html_e( 'Your Name', 'volunteer-impact-tracker' ); ?> *</label><br>
				<input type="text" required id="vit_volunteer_name" name="volunteer_name" value="<?php echo esc_attr( $prefill_name ); ?>" maxlength="191" autocomplete="name">
			</p>
			<p>
				<label for="vit_volunteer_email"><?php esc_html_e( 'Your Email', 'volunteer-impact-tracker' ); ?> *</label><br>
				<input type="email" required id="vit_volunteer_email" name="volunteer_email" value="<?php echo esc_attr( $prefill_email ); ?>" autocomplete="email">
			</p>

			<?php if ( $pinned_id ) : ?>
				<input type="hidden" name="opportunity_id" value="<?php echo esc_attr( $pinned_id ); ?>">
				<p class="vit-pinned-opportunity">
					<strong><?php esc_html_e( 'Opportunity', 'volunteer-impact-tracker' ); ?>:</strong>
					<?php echo esc_html( get_the_title( $pinned_id ) ); ?>
				</p>
			<?php else : ?>
				<p>
					<label for="vit_opportunity"><?php esc_html_e( 'Opportunity', 'volunteer-impact-tracker' ); ?></label><br>
					<select id="vit_opportunity" name="opportunity_id">
						<option value=""><?php esc_html_e( '— General / Unlisted —', 'volunteer-impact-tracker' ); ?></option>
						<?php foreach ( $opportunities as $opp ) : ?>
							<option value="<?php echo esc_attr( $opp->ID ); ?>"><?php echo esc_html( vit_opportunity_label( $opp ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>

			<p>
				<label for="vit_hours"><?php esc_html_e( 'Hours', 'volunteer-impact-tracker' ); ?> *</label><br>
				<input type="number" step="0.25" min="0.25" max="<?php echo esc_attr( VIT_MAX_HOURS_PER_ENTRY ); ?>" required id="vit_hours" name="hours">
			</p>
			<p>
				<label for="vit_date_served"><?php esc_html_e( 'Date Served', 'volunteer-impact-tracker' ); ?> *</label><br>
				<input type="date" required id="vit_date_served" name="date_served" max="<?php echo esc_attr( vit_today() ); ?>" value="<?php echo esc_attr( vit_today() ); ?>">
			</p>
			<p>
				<label for="vit_notes"><?php esc_html_e( 'Notes (optional)', 'volunteer-impact-tracker' ); ?></label><br>
				<textarea id="vit_notes" name="notes" rows="2"></textarea>
			</p>

			<?php
			// Honeypot field against basic spam bots.
			?>
			<p class="vit-hp" aria-hidden="true">
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
			self::redirect_back( 'approved' );
		}

		$name  = isset( $_POST['volunteer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['volunteer_name'] ) ) : '';
		$email = isset( $_POST['volunteer_email'] ) ? sanitize_email( wp_unslash( $_POST['volunteer_email'] ) ) : '';
		$hours = isset( $_POST['hours'] ) ? vit_sanitize_hours( wp_unslash( $_POST['hours'] ) ) : 0;
		$date  = isset( $_POST['date_served'] ) ? vit_sanitize_date( wp_unslash( $_POST['date_served'] ) ) : '';

		if ( empty( $name ) || empty( $email ) || $hours <= 0 || empty( $date ) || $date > vit_today() ) {
			self::redirect_back( '', true );
		}

		$opportunity_id = ! empty( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : 0;
		if ( $opportunity_id && VIT_CPT_OPPORTUNITY !== get_post_type( $opportunity_id ) ) {
			$opportunity_id = 0;
		}

		global $wpdb;
		$table            = $wpdb->prefix . VIT_TABLE_HOURS;
		$require_approval = (int) VIT_Settings::get( 'require_approval', 1 );
		$status           = $require_approval ? 'pending' : 'approved';
		$user_id          = get_current_user_id();

		$wpdb->insert(
			$table,
			array(
				'opportunity_id'  => $opportunity_id ? $opportunity_id : null,
				'user_id'         => $user_id ? $user_id : null,
				'volunteer_name'  => $name,
				'volunteer_email' => $email,
				'hours'           => $hours,
				'date_served'     => $date,
				'notes'           => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
				'status'          => $status,
				'created_at'      => current_time( 'mysql' ),
				'approved_by'     => $require_approval ? null : 0,
				'approved_at'     => $require_approval ? null : current_time( 'mysql' ),
			)
		);

		self::redirect_back( $status );
	}

	/**
	 * @param string $mode  approved|pending|empty.
	 * @param bool   $error Redirect with error flag.
	 */
	private static function redirect_back( $mode = '', $error = false ) {
		$redirect = isset( $_POST['vit_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['vit_redirect'] ) ) : home_url( '/' );
		$args     = array();
		if ( $error ) {
			$args['vit_error'] = '1';
		} else {
			$args['vit_submitted'] = '1';
			if ( $mode ) {
				$args['vit_mode'] = $mode;
			}
		}
		wp_safe_redirect( add_query_arg( $args, $redirect ) );
		exit;
	}
}
