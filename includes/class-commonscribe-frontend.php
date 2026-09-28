<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end shortcodes: [commonscribe_log_hours] and [commonscribe_my_hours].
 */
class COMMONSCRIBE_Frontend {

	public static function init() {
		add_shortcode( 'commonscribe_log_hours', array( __CLASS__, 'render_log_hours' ) );
		add_shortcode( 'commonscribe_my_hours', array( __CLASS__, 'render_my_hours' ) );
		add_action( 'admin_post_commonscribe_frontend_log_hours', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_commonscribe_frontend_log_hours', array( __CLASS__, 'handle_submit' ) );
	}

	private static function enqueue_styles() {
		wp_enqueue_style( 'commonscribe-frontend', COMMONSCRIBE_PLUGIN_URL . 'assets/css/frontend.css', array(), COMMONSCRIBE_VERSION );
	}

	public static function render_log_hours( $atts ) {
		self::enqueue_styles();

		if ( (int) COMMONSCRIBE_Settings::get( 'require_login', 0 ) && ! is_user_logged_in() ) {
			$login_url = wp_login_url( get_permalink() );
			ob_start();
			echo '<p class="commonscribe-login-required">';
			printf(
				/* translators: %s: login URL */
				wp_kses_post( __( 'Please <a href="%s">log in</a> to submit volunteer hours.', 'commonscribe-volunteer-log' ) ),
				esc_url( $login_url )
			);
			echo '</p>';
			return ob_get_clean();
		}

		$atts = shortcode_atts(
			array(
				'opportunity_id' => '',
			),
			$atts,
			'commonscribe_log_hours'
		);

		$pinned_id = absint( $atts['opportunity_id'] );
		if ( $pinned_id && COMMONSCRIBE_CPT_OPPORTUNITY !== get_post_type( $pinned_id ) ) {
			$pinned_id = 0;
		}

		$opportunities = get_posts(
			array(
				'post_type'      => COMMONSCRIBE_CPT_OPPORTUNITY,
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg.
		if ( isset( $_GET['commonscribe_submitted'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg.
			$mode = isset( $_GET['commonscribe_mode'] ) ? sanitize_key( wp_unslash( $_GET['commonscribe_mode'] ) ) : '';
			if ( 'approved' === $mode ) {
				echo '<p class="commonscribe-success">' . esc_html__( 'Thanks! Your hours have been recorded.', 'commonscribe-volunteer-log' ) . '</p>';
			} else {
				echo '<p class="commonscribe-success">' . esc_html__( 'Thanks! Your hours have been submitted and are waiting for approval.', 'commonscribe-volunteer-log' ) . '</p>';
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg.
		if ( isset( $_GET['commonscribe_error'] ) ) {
			echo '<p class="commonscribe-error">' . esc_html__( 'Please check your entries and try again. Hours must be between 0.25 and 24, and the date cannot be in the future.', 'commonscribe-volunteer-log' ) . '</p>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="commonscribe-frontend-form">
			<input type="hidden" name="action" value="commonscribe_frontend_log_hours">
			<input type="hidden" name="commonscribe_redirect" value="<?php echo esc_url( get_permalink() ); ?>">
			<?php wp_nonce_field( 'commonscribe_frontend_log_hours', 'commonscribe_frontend_nonce' ); ?>

			<p>
				<label for="commonscribe_volunteer_name"><?php esc_html_e( 'Your Name', 'commonscribe-volunteer-log' ); ?> *</label><br>
				<input type="text" required id="commonscribe_volunteer_name" name="volunteer_name" value="<?php echo esc_attr( $prefill_name ); ?>" maxlength="191" autocomplete="name">
			</p>
			<p>
				<label for="commonscribe_volunteer_email"><?php esc_html_e( 'Your Email', 'commonscribe-volunteer-log' ); ?> *</label><br>
				<input type="email" required id="commonscribe_volunteer_email" name="volunteer_email" value="<?php echo esc_attr( $prefill_email ); ?>" autocomplete="email">
			</p>

			<?php if ( $pinned_id ) : ?>
				<input type="hidden" name="opportunity_id" value="<?php echo esc_attr( $pinned_id ); ?>">
				<p class="commonscribe-pinned-opportunity">
					<strong><?php esc_html_e( 'Opportunity', 'commonscribe-volunteer-log' ); ?>:</strong>
					<?php echo esc_html( get_the_title( $pinned_id ) ); ?>
				</p>
			<?php else : ?>
				<p>
					<label for="commonscribe_opportunity"><?php esc_html_e( 'Opportunity', 'commonscribe-volunteer-log' ); ?></label><br>
					<select id="commonscribe_opportunity" name="opportunity_id">
						<option value=""><?php esc_html_e( '— General / Unlisted —', 'commonscribe-volunteer-log' ); ?></option>
						<?php foreach ( $opportunities as $opp ) : ?>
							<option value="<?php echo esc_attr( $opp->ID ); ?>"><?php echo esc_html( commonscribe_opportunity_label( $opp ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>

			<p>
				<label for="commonscribe_hours"><?php esc_html_e( 'Hours', 'commonscribe-volunteer-log' ); ?> *</label><br>
				<input type="number" step="0.25" min="0.25" max="<?php echo esc_attr( COMMONSCRIBE_MAX_HOURS_PER_ENTRY ); ?>" required id="commonscribe_hours" name="hours">
			</p>
			<p>
				<label for="commonscribe_date_served"><?php esc_html_e( 'Date Served', 'commonscribe-volunteer-log' ); ?> *</label><br>
				<input type="date" required id="commonscribe_date_served" name="date_served" max="<?php echo esc_attr( commonscribe_today() ); ?>" value="<?php echo esc_attr( commonscribe_today() ); ?>">
			</p>
			<p>
				<label for="commonscribe_notes"><?php esc_html_e( 'Notes (optional)', 'commonscribe-volunteer-log' ); ?></label><br>
				<textarea id="commonscribe_notes" name="notes" rows="2"></textarea>
			</p>

			<p class="commonscribe-hp" aria-hidden="true">
				<label for="commonscribe_website"><?php esc_html_e( 'Website', 'commonscribe-volunteer-log' ); ?></label>
				<input type="text" id="commonscribe_website" name="commonscribe_website" tabindex="-1" autocomplete="off">
			</p>

			<p><button type="submit"><?php esc_html_e( 'Submit Hours', 'commonscribe-volunteer-log' ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * [commonscribe_my_hours] — logged-in volunteer's own entries and totals.
	 */
	public static function render_my_hours( $atts ) {
		self::enqueue_styles();

		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( get_permalink() );
			ob_start();
			echo '<p class="commonscribe-login-required">';
			printf(
				/* translators: %s: login URL */
				wp_kses_post( __( 'Please <a href="%s">log in</a> to view your volunteer hours.', 'commonscribe-volunteer-log' ) ),
				esc_url( $login_url )
			);
			echo '</p>';
			return ob_get_clean();
		}

		$atts = shortcode_atts(
			array(
				'year' => current_time( 'Y' ),
			),
			$atts,
			'commonscribe_my_hours'
		);

		$user = wp_get_current_user();
		$year = absint( $atts['year'] );
		if ( $year < 2000 || $year > 2100 ) {
			$year = (int) current_time( 'Y' );
		}
		$start = $year . '-01-01';
		$end   = $year . '-12-31';

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i
				WHERE date_served BETWEEN %s AND %s
				AND (user_id = %d OR volunteer_email = %s)
				ORDER BY date_served DESC, id DESC',
				$wpdb->prefix . COMMONSCRIBE_TABLE_HOURS,
				$start,
				$end,
				$user->ID,
				$user->user_email
			)
		);

		$approved_hours = 0;
		$pending_hours  = 0;
		foreach ( $entries as $entry ) {
			if ( 'approved' === $entry->status ) {
				$approved_hours += (float) $entry->hours;
			} elseif ( 'pending' === $entry->status ) {
				$pending_hours += (float) $entry->hours;
			}
		}

		ob_start();
		?>
		<div class="commonscribe-my-hours">
			<h3><?php echo esc_html( sprintf( /* translators: %d: year */ __( 'Your hours in %d', 'commonscribe-volunteer-log' ), $year ) ); ?></h3>
			<p class="commonscribe-my-hours-summary">
				<strong><?php echo esc_html( number_format_i18n( $approved_hours, 2 ) ); ?></strong>
				<?php esc_html_e( 'approved', 'commonscribe-volunteer-log' ); ?>
				<?php if ( $pending_hours > 0 ) : ?>
					· <strong><?php echo esc_html( number_format_i18n( $pending_hours, 2 ) ); ?></strong>
					<?php esc_html_e( 'pending', 'commonscribe-volunteer-log' ); ?>
				<?php endif; ?>
			</p>

			<?php if ( empty( $entries ) ) : ?>
				<p><?php esc_html_e( 'No hours found for this year yet.', 'commonscribe-volunteer-log' ); ?></p>
			<?php else : ?>
				<table class="commonscribe-my-hours-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'commonscribe-volunteer-log' ); ?></th>
							<th><?php esc_html_e( 'Opportunity', 'commonscribe-volunteer-log' ); ?></th>
							<th><?php esc_html_e( 'Hours', 'commonscribe-volunteer-log' ); ?></th>
							<th><?php esc_html_e( 'Status', 'commonscribe-volunteer-log' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry->date_served ); ?></td>
								<td><?php echo $entry->opportunity_id ? esc_html( get_the_title( $entry->opportunity_id ) ) : esc_html__( 'General', 'commonscribe-volunteer-log' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $entry->hours, 2 ) ); ?></td>
								<td><span class="commonscribe-status commonscribe-status-<?php echo esc_attr( $entry->status ); ?>"><?php echo esc_html( commonscribe_status_label( $entry->status ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function handle_submit() {
		if ( ! isset( $_POST['commonscribe_frontend_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['commonscribe_frontend_nonce'] ) ), 'commonscribe_frontend_log_hours' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'commonscribe-volunteer-log' ) );
		}

		if ( (int) COMMONSCRIBE_Settings::get( 'require_login', 0 ) && ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to submit hours.', 'commonscribe-volunteer-log' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$redirect = isset( $_POST['commonscribe_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['commonscribe_redirect'] ) ) : home_url( '/' );

		if ( ! empty( $_POST['commonscribe_website'] ) ) {
			self::redirect_back( $redirect, 'approved' );
		}

		$name  = isset( $_POST['volunteer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['volunteer_name'] ) ) : '';
		$email = isset( $_POST['volunteer_email'] ) ? sanitize_email( wp_unslash( $_POST['volunteer_email'] ) ) : '';
		$hours = isset( $_POST['hours'] ) ? commonscribe_sanitize_hours( sanitize_text_field( wp_unslash( $_POST['hours'] ) ) ) : 0;
		$date  = isset( $_POST['date_served'] ) ? commonscribe_sanitize_date( sanitize_text_field( wp_unslash( $_POST['date_served'] ) ) ) : '';

		if ( empty( $name ) || empty( $email ) || $hours <= 0 || empty( $date ) || $date > commonscribe_today() ) {
			self::redirect_back( $redirect, '', true );
		}

		$opportunity_id = ! empty( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : 0;
		if ( $opportunity_id && COMMONSCRIBE_CPT_OPPORTUNITY !== get_post_type( $opportunity_id ) ) {
			$opportunity_id = 0;
		}

		global $wpdb;
		$require_approval = (int) COMMONSCRIBE_Settings::get( 'require_approval', 1 );
		$status           = $require_approval ? 'pending' : 'approved';
		$user_id          = get_current_user_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
		$wpdb->insert(
			commonscribe_table(),
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

		$entry_id = (int) $wpdb->insert_id;
		if ( $entry_id && 'pending' === $status ) {
			$entry = commonscribe_get_entry( $entry_id );
			if ( $entry ) {
				COMMONSCRIBE_Emails::notify_pending( $entry );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		self::redirect_back( $redirect, $status );
	}

	/**
	 * Redirect back to the form page with a flash query arg.
	 *
	 * @param string $redirect Safe redirect URL (already read from POST).
	 * @param string $mode     Status mode for success flash.
	 * @param bool   $error    Whether to show the error flash.
	 */
	private static function redirect_back( $redirect, $mode = '', $error = false ) {
		$args = array();
		if ( $error ) {
			$args['commonscribe_error'] = '1';
		} else {
			$args['commonscribe_submitted'] = '1';
			if ( $mode ) {
				$args['commonscribe_mode'] = $mode;
			}
		}
		wp_safe_redirect( add_query_arg( $args, $redirect ) );
		exit;
	}
}
