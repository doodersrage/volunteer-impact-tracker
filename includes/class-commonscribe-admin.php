<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens: Log Hours (add/edit/search/paginate), Pending Approvals (bulk).
 */
class COMMONSCRIBE_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 5 );
		add_action( 'admin_post_commonscribe_add_hours', array( __CLASS__, 'handle_add_hours' ) );
		add_action( 'admin_post_commonscribe_edit_hours', array( __CLASS__, 'handle_edit_hours' ) );
		add_action( 'admin_post_commonscribe_update_status', array( __CLASS__, 'handle_update_status' ) );
		add_action( 'admin_post_commonscribe_bulk_status', array( __CLASS__, 'handle_bulk_status' ) );
		add_action( 'admin_post_commonscribe_delete_entry', array( __CLASS__, 'handle_delete_entry' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'pending_notice' ) );
	}

	public static function enqueue( $hook ) {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = ( $screen && isset( $screen->post_type ) ) ? $screen->post_type : '';
		if ( false === strpos( $hook, 'commonscribe-' ) && COMMONSCRIBE_CPT_OPPORTUNITY !== $post_type ) {
			return;
		}

		wp_enqueue_style( 'commonscribe-admin', COMMONSCRIBE_PLUGIN_URL . 'assets/css/admin.css', array(), COMMONSCRIBE_VERSION );
		wp_enqueue_script( 'commonscribe-admin', COMMONSCRIBE_PLUGIN_URL . 'assets/js/admin.js', array(), COMMONSCRIBE_VERSION, true );
		wp_localize_script(
			'commonscribe-admin',
			'commonscribeAdmin',
			array(
				'copied'        => __( 'Copied!', 'commonscribe-volunteer-log' ),
				'copyPrompt'    => __( 'Copy this certificate link:', 'commonscribe-volunteer-log' ),
				'confirmDelete' => __( 'Delete this entry?', 'commonscribe-volunteer-log' ),
			)
		);
	}

	public static function add_menu() {
		add_menu_page(
			__( 'Volunteer Tracker', 'commonscribe-volunteer-log' ),
			__( 'Volunteers', 'commonscribe-volunteer-log' ),
			COMMONSCRIBE_CAPABILITY,
			'commonscribe-volunteers',
			array( __CLASS__, 'render_log_hours' ),
			'dashicons-groups',
			30
		);

		add_submenu_page(
			'commonscribe-volunteers',
			__( 'Log Hours', 'commonscribe-volunteer-log' ),
			__( 'Log Hours', 'commonscribe-volunteer-log' ),
			COMMONSCRIBE_CAPABILITY,
			'commonscribe-volunteers',
			array( __CLASS__, 'render_log_hours' )
		);

		$pending       = commonscribe_pending_count();
		$pending_label = __( 'Pending Approvals', 'commonscribe-volunteer-log' );
		if ( $pending > 0 ) {
			$pending_label = sprintf(
				/* translators: %s: pending count markup */
				__( 'Pending Approvals %s', 'commonscribe-volunteer-log' ),
				'<span class="awaiting-mod">' . number_format_i18n( $pending ) . '</span>'
			);
		}

		add_submenu_page(
			'commonscribe-volunteers',
			__( 'Pending Approvals', 'commonscribe-volunteer-log' ),
			$pending_label,
			COMMONSCRIBE_CAPABILITY,
			'commonscribe-pending',
			array( __CLASS__, 'render_pending' )
		);
	}

	public static function pending_notice() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && false !== strpos( $screen->id, 'commonscribe-pending' ) ) {
			return;
		}

		$pending = commonscribe_pending_count();
		if ( $pending < 1 ) {
			return;
		}

		$url  = admin_url( 'admin.php?page=commonscribe-pending' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Review now', 'commonscribe-volunteer-log' ) . '</a>';
		echo '<div class="notice notice-warning"><p>';
		echo wp_kses_post(
			sprintf(
				/* translators: 1: number of pending entries, 2: review link HTML */
				_n(
					'%1$d volunteer hour entry is waiting for approval. %2$s',
					'%1$d volunteer hour entries are waiting for approval. %2$s',
					$pending,
					'commonscribe-volunteer-log'
				),
				(int) $pending,
				$link
			)
		);
		echo '</p></div>';
	}

	private static function get_opportunities() {
		return get_posts(
			array(
				'post_type'      => COMMONSCRIBE_CPT_OPPORTUNITY,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);
	}

	/**
	 * Parse shared entry fields from POST; return WP_Error or data array.
	 *
	 * Callers must verify the request nonce before calling this method.
	 *
	 * @return array|WP_Error
	 */
	private static function parse_entry_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Callers verify nonce before invoking.
		$name  = isset( $_POST['volunteer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['volunteer_name'] ) ) : '';
		$email = isset( $_POST['volunteer_email'] ) ? sanitize_email( wp_unslash( $_POST['volunteer_email'] ) ) : '';
		$hours = isset( $_POST['hours'] ) ? commonscribe_sanitize_hours( sanitize_text_field( wp_unslash( $_POST['hours'] ) ) ) : 0;
		$date  = isset( $_POST['date_served'] ) ? commonscribe_sanitize_date( sanitize_text_field( wp_unslash( $_POST['date_served'] ) ) ) : '';

		if ( '' === $name || $hours <= 0 || '' === $date || $date > commonscribe_today() ) {
			return new WP_Error( 'invalid', __( 'Invalid entry data.', 'commonscribe-volunteer-log' ) );
		}

		$opportunity_id = ! empty( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : 0;
		if ( $opportunity_id && COMMONSCRIBE_CPT_OPPORTUNITY !== get_post_type( $opportunity_id ) ) {
			$opportunity_id = 0;
		}

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'approved';
		if ( ! in_array( $status, array( 'approved', 'pending', 'rejected' ), true ) ) {
			$status = 'approved';
		}

		$data = array(
			'opportunity_id'  => $opportunity_id ? $opportunity_id : null,
			'volunteer_name'  => $name,
			'volunteer_email' => $email,
			'hours'           => $hours,
			'date_served'     => $date,
			'notes'           => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'status'          => $status,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $data;
	}

	/** ---------- Log Hours screen ---------- */

	public static function render_log_hours() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . COMMONSCRIBE_TABLE_HOURS;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above.
		if ( isset( $_GET['added'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Hours logged and approved.', 'commonscribe-volunteer-log' ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above.
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry updated.', 'commonscribe-volunteer-log' ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above.
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry deleted.', 'commonscribe-volunteer-log' ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above.
		if ( isset( $_GET['error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not save entry. Check that name, hours (0.25–24), and date are valid.', 'commonscribe-volunteer-log' ) . '</p></div>';
		}

		$opportunities = self::get_opportunities();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above.
		$edit_id    = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$edit_entry = $edit_id ? commonscribe_get_entry( $edit_id ) : null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter query arg; capability checked above.
		$status_filter = isset( $_GET['commonscribe_status'] ) ? sanitize_key( wp_unslash( $_GET['commonscribe_status'] ) ) : '';
		$allowed       = array( 'approved', 'pending', 'rejected' );
		if ( ! in_array( $status_filter, $allowed, true ) ) {
			$status_filter = '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter query arg; capability checked above.
		$search = isset( $_GET['commonscribe_s'] ) ? sanitize_text_field( wp_unslash( $_GET['commonscribe_s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter query arg; capability checked above.
		$page   = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$offset = ( $page - 1 ) * COMMONSCRIBE_ENTRIES_PER_PAGE;

		$has_status = ( '' !== $status_filter );
		$has_search = ( '' !== $search );
		$like       = $has_search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		if ( $has_status && $has_search ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE status = %s AND (volunteer_name LIKE %s OR volunteer_email LIKE %s OR notes LIKE %s)',
					$table,
					$status_filter,
					$like,
					$like,
					$like
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
			$entries = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s AND (volunteer_name LIKE %s OR volunteer_email LIKE %s OR notes LIKE %s) ORDER BY date_served DESC, id DESC LIMIT %d OFFSET %d',
					$table,
					$status_filter,
					$like,
					$like,
					$like,
					COMMONSCRIBE_ENTRIES_PER_PAGE,
					$offset
				)
			);
		} elseif ( $has_status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE status = %s',
					$table,
					$status_filter
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
			$entries = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s ORDER BY date_served DESC, id DESC LIMIT %d OFFSET %d',
					$table,
					$status_filter,
					COMMONSCRIBE_ENTRIES_PER_PAGE,
					$offset
				)
			);
		} elseif ( $has_search ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE (volunteer_name LIKE %s OR volunteer_email LIKE %s OR notes LIKE %s)',
					$table,
					$like,
					$like,
					$like
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
			$entries = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE (volunteer_name LIKE %s OR volunteer_email LIKE %s OR notes LIKE %s) ORDER BY date_served DESC, id DESC LIMIT %d OFFSET %d',
					$table,
					$like,
					$like,
					$like,
					COMMONSCRIBE_ENTRIES_PER_PAGE,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i',
					$table
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
			$entries = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY date_served DESC, id DESC LIMIT %d OFFSET %d',
					$table,
					COMMONSCRIBE_ENTRIES_PER_PAGE,
					$offset
				)
			);
		}

		$total_pages = max( 1, (int) ceil( $total / COMMONSCRIBE_ENTRIES_PER_PAGE ) );
		?>
		<div class="wrap commonscribe-wrap">
			<h1><?php esc_html_e( 'Volunteer Hours', 'commonscribe-volunteer-log' ); ?></h1>

			<?php if ( $edit_entry ) : ?>
				<h2><?php esc_html_e( 'Edit Entry', 'commonscribe-volunteer-log' ); ?></h2>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=commonscribe-volunteers' ) ); ?>">&larr; <?php esc_html_e( 'Cancel edit / log new hours', 'commonscribe-volunteer-log' ); ?></a></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="commonscribe-form">
					<input type="hidden" name="action" value="commonscribe_edit_hours">
					<input type="hidden" name="entry_id" value="<?php echo esc_attr( $edit_entry->id ); ?>">
					<?php wp_nonce_field( 'commonscribe_edit_hours_' . $edit_entry->id, 'commonscribe_edit_hours_nonce' ); ?>
					<?php self::render_entry_fields( $opportunities, $edit_entry, true ); ?>
					<?php submit_button( __( 'Update Entry', 'commonscribe-volunteer-log' ) ); ?>
				</form>
			<?php else : ?>
				<h2><?php esc_html_e( 'Log Hours', 'commonscribe-volunteer-log' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Entries logged here by an admin are recorded as approved immediately.', 'commonscribe-volunteer-log' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="commonscribe-form">
					<input type="hidden" name="action" value="commonscribe_add_hours">
					<?php wp_nonce_field( 'commonscribe_add_hours', 'commonscribe_add_hours_nonce' ); ?>
					<?php self::render_entry_fields( $opportunities, null, false ); ?>
					<?php submit_button( __( 'Log Hours', 'commonscribe-volunteer-log' ) ); ?>
				</form>
			<?php endif; ?>

			<hr>

			<h2><?php esc_html_e( 'Entries', 'commonscribe-volunteer-log' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="commonscribe-filter-form">
				<input type="hidden" name="page" value="commonscribe-volunteers">
				<label for="commonscribe_s"><?php esc_html_e( 'Search', 'commonscribe-volunteer-log' ); ?>
					<input type="search" id="commonscribe_s" name="commonscribe_s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, email, or notes…', 'commonscribe-volunteer-log' ); ?>">
				</label>
				<label for="commonscribe_status"><?php esc_html_e( 'Status', 'commonscribe-volunteer-log' ); ?>
					<select id="commonscribe_status" name="commonscribe_status">
						<option value=""><?php esc_html_e( 'All', 'commonscribe-volunteer-log' ); ?></option>
						<option value="approved" <?php selected( $status_filter, 'approved' ); ?>><?php esc_html_e( 'Approved', 'commonscribe-volunteer-log' ); ?></option>
						<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'commonscribe-volunteer-log' ); ?></option>
						<option value="rejected" <?php selected( $status_filter, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'commonscribe-volunteer-log' ); ?></option>
					</select>
				</label>
				<?php submit_button( __( 'Filter', 'commonscribe-volunteer-log' ), 'secondary', '', false ); ?>
			</form>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: shown count, 2: total count */
						__( 'Showing %1$d of %2$d entries.', 'commonscribe-volunteer-log' ),
						count( $entries ),
						$total
					)
				);
				?>
			</p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Volunteer', 'commonscribe-volunteer-log' ); ?></th>
						<th><?php esc_html_e( 'Opportunity', 'commonscribe-volunteer-log' ); ?></th>
						<th><?php esc_html_e( 'Hours', 'commonscribe-volunteer-log' ); ?></th>
						<th><?php esc_html_e( 'Date', 'commonscribe-volunteer-log' ); ?></th>
						<th><?php esc_html_e( 'Status', 'commonscribe-volunteer-log' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'commonscribe-volunteer-log' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No matching entries.', 'commonscribe-volunteer-log' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $entry->volunteer_name ); ?>
									<?php if ( $entry->volunteer_email ) : ?>
										<br><small><?php echo esc_html( $entry->volunteer_email ); ?></small>
									<?php endif; ?>
									<?php if ( ! empty( $entry->notes ) ) : ?>
										<br><small class="description"><?php echo esc_html( wp_trim_words( $entry->notes, 12 ) ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo $entry->opportunity_id ? esc_html( get_the_title( $entry->opportunity_id ) ) : esc_html__( 'General', 'commonscribe-volunteer-log' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $entry->hours, 2 ) ); ?></td>
								<td><?php echo esc_html( $entry->date_served ); ?></td>
								<td><span class="commonscribe-status commonscribe-status-<?php echo esc_attr( $entry->status ); ?>"><?php echo esc_html( commonscribe_status_label( $entry->status ) ); ?></span></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'commonscribe-volunteers', 'edit' => $entry->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'commonscribe-volunteer-log' ); ?></a>
									|
									<a class="commonscribe-confirm-delete" href="<?php echo esc_url( self::delete_url( $entry->id ) ); ?>"><?php esc_html_e( 'Delete', 'commonscribe-volunteer-log' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php self::render_pagination( $page, $total_pages, array( 'page' => 'commonscribe-volunteers', 'commonscribe_status' => $status_filter, 'commonscribe_s' => $search ) ); ?>
		</div>
		<?php
	}

	/**
	 * Shared add/edit form fields.
	 *
	 * @param array       $opportunities Opportunity posts.
	 * @param object|null $entry         Existing entry or null.
	 * @param bool        $show_status   Whether to show status select.
	 */
	private static function render_entry_fields( $opportunities, $entry, $show_status ) {
		$name  = $entry ? $entry->volunteer_name : '';
		$email = $entry ? $entry->volunteer_email : '';
		$opp   = $entry ? (int) $entry->opportunity_id : 0;
		$hours = $entry ? $entry->hours : '';
		$date  = $entry ? $entry->date_served : commonscribe_today();
		$notes = $entry ? $entry->notes : '';
		$status = $entry ? $entry->status : 'approved';
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="volunteer_name"><?php esc_html_e( 'Volunteer Name', 'commonscribe-volunteer-log' ); ?></label></th>
				<td><input type="text" required id="volunteer_name" name="volunteer_name" class="regular-text" maxlength="191" value="<?php echo esc_attr( $name ); ?>"></td>
			</tr>
			<tr>
				<th><label for="volunteer_email"><?php esc_html_e( 'Volunteer Email (optional)', 'commonscribe-volunteer-log' ); ?></label></th>
				<td>
					<input type="email" id="volunteer_email" name="volunteer_email" class="regular-text" value="<?php echo esc_attr( $email ); ?>">
					<p class="description"><?php esc_html_e( 'Needed for certificates and approval emails.', 'commonscribe-volunteer-log' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="opportunity_id"><?php esc_html_e( 'Opportunity', 'commonscribe-volunteer-log' ); ?></label></th>
				<td>
					<select id="opportunity_id" name="opportunity_id">
						<option value=""><?php esc_html_e( '— General / Unlisted —', 'commonscribe-volunteer-log' ); ?></option>
						<?php foreach ( $opportunities as $o ) : ?>
							<option value="<?php echo esc_attr( $o->ID ); ?>" <?php selected( $opp, $o->ID ); ?>><?php echo esc_html( commonscribe_opportunity_label( $o ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="hours"><?php esc_html_e( 'Hours', 'commonscribe-volunteer-log' ); ?></label></th>
				<td>
					<input type="number" step="0.25" min="0.25" max="<?php echo esc_attr( COMMONSCRIBE_MAX_HOURS_PER_ENTRY ); ?>" required id="hours" name="hours" style="width:100px;" value="<?php echo esc_attr( $hours ); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="date_served"><?php esc_html_e( 'Date Served', 'commonscribe-volunteer-log' ); ?></label></th>
				<td><input type="date" required id="date_served" name="date_served" max="<?php echo esc_attr( commonscribe_today() ); ?>" value="<?php echo esc_attr( $date ); ?>"></td>
			</tr>
			<?php if ( $show_status ) : ?>
				<tr>
					<th><label for="status"><?php esc_html_e( 'Status', 'commonscribe-volunteer-log' ); ?></label></th>
					<td>
						<select id="status" name="status">
							<option value="approved" <?php selected( $status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'commonscribe-volunteer-log' ); ?></option>
							<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'commonscribe-volunteer-log' ); ?></option>
							<option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'commonscribe-volunteer-log' ); ?></option>
						</select>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th><label for="notes"><?php esc_html_e( 'Notes', 'commonscribe-volunteer-log' ); ?></label></th>
				<td><textarea id="notes" name="notes" rows="2" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea></td>
			</tr>
		</table>
		<?php
	}

	private static function render_pagination( $page, $total_pages, $args ) {
		if ( $total_pages <= 1 ) {
			return;
		}
		echo '<div class="commonscribe-pagination tablenav"><div class="tablenav-pages">';
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$url = add_query_arg( array_merge( $args, array( 'paged' => $i ) ), admin_url( 'admin.php' ) );
			if ( $i === $page ) {
				echo '<span class="tablenav-pages-navspan button disabled" aria-current="page">' . esc_html( (string) $i ) . '</span> ';
			} else {
				echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> ';
			}
		}
		echo '</div></div>';
	}

	public static function handle_add_hours() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'commonscribe-volunteer-log' ) );
		}
		if ( ! isset( $_POST['commonscribe_add_hours_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['commonscribe_add_hours_nonce'] ) ), 'commonscribe_add_hours' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'commonscribe-volunteer-log' ) );
		}

		$data = self::parse_entry_post();
		if ( is_wp_error( $data ) ) {
			wp_safe_redirect( add_query_arg( 'error', '1', admin_url( 'admin.php?page=commonscribe-volunteers' ) ) );
			exit;
		}

		global $wpdb;
		$row = array_merge(
			$data,
			array(
				'user_id'     => null,
				'status'      => 'approved',
				'created_at'  => current_time( 'mysql' ),
				'approved_by' => get_current_user_id(),
				'approved_at' => current_time( 'mysql' ),
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
		$wpdb->insert( commonscribe_table(), $row );

		wp_safe_redirect( add_query_arg( 'added', '1', admin_url( 'admin.php?page=commonscribe-volunteers' ) ) );
		exit;
	}

	public static function handle_edit_hours() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'commonscribe-volunteer-log' ) );
		}
		$id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		if ( ! $id || ! isset( $_POST['commonscribe_edit_hours_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['commonscribe_edit_hours_nonce'] ) ), 'commonscribe_edit_hours_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'commonscribe-volunteer-log' ) );
		}

		$existing = commonscribe_get_entry( $id );
		if ( ! $existing ) {
			wp_die( esc_html__( 'Entry not found.', 'commonscribe-volunteer-log' ) );
		}

		$data = self::parse_entry_post();
		if ( is_wp_error( $data ) ) {
			wp_safe_redirect( add_query_arg( array( 'edit' => $id, 'error' => '1' ), admin_url( 'admin.php?page=commonscribe-volunteers' ) ) );
			exit;
		}

		if ( 'approved' === $data['status'] && 'approved' !== $existing->status ) {
			$data['approved_by'] = get_current_user_id();
			$data['approved_at'] = current_time( 'mysql' );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
		$wpdb->update( commonscribe_table(), $data, array( 'id' => $id ) );

		if ( 'approved' === $data['status'] && 'approved' !== $existing->status ) {
			$updated = commonscribe_get_entry( $id );
			if ( $updated ) {
				COMMONSCRIBE_Emails::notify_approved( $updated );
			}
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=commonscribe-volunteers' ) ) );
		exit;
	}

	/** ---------- Pending Approvals ---------- */

	public static function render_pending() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			return;
		}
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above.
		if ( isset( $_GET['status_updated'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above.
			$status = isset( $_GET['new_status'] ) ? sanitize_key( wp_unslash( $_GET['new_status'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above.
			$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 1;
			if ( 'approved' === $status ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
					sprintf(
						/* translators: %d: number of entries */
						_n( '%d entry approved.', '%d entries approved.', $count, 'commonscribe-volunteer-log' ),
						$count
					)
				) . '</p></div>';
			} elseif ( 'rejected' === $status ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
					sprintf(
						/* translators: %d: number of entries */
						_n( '%d entry rejected.', '%d entries rejected.', $count, 'commonscribe-volunteer-log' ),
						$count
					)
				) . '</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry updated.', 'commonscribe-volunteer-log' ) . '</p></div>';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE status = 'pending' ORDER BY date_served DESC, id DESC",
				$wpdb->prefix . COMMONSCRIBE_TABLE_HOURS
			)
		);
		?>
		<div class="wrap commonscribe-wrap">
			<h1><?php esc_html_e( 'Pending Approvals', 'commonscribe-volunteer-log' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Hours volunteers submitted themselves through the front-end form, waiting for review.', 'commonscribe-volunteer-log' ); ?></p>

			<?php if ( empty( $entries ) ) : ?>
				<p><?php esc_html_e( 'Nothing waiting for approval.', 'commonscribe-volunteer-log' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="commonscribe_bulk_status">
					<?php wp_nonce_field( 'commonscribe_bulk_status', 'commonscribe_bulk_status_nonce' ); ?>
					<div class="commonscribe-bulk-bar">
						<button type="submit" name="bulk_action" value="approved" class="button button-primary"><?php esc_html_e( 'Approve selected', 'commonscribe-volunteer-log' ); ?></button>
						<button type="submit" name="bulk_action" value="rejected" class="button"><?php esc_html_e( 'Reject selected', 'commonscribe-volunteer-log' ); ?></button>
					</div>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<td class="check-column"><input type="checkbox" id="commonscribe-select-all"></td>
								<th><?php esc_html_e( 'Volunteer', 'commonscribe-volunteer-log' ); ?></th>
								<th><?php esc_html_e( 'Opportunity', 'commonscribe-volunteer-log' ); ?></th>
								<th><?php esc_html_e( 'Hours', 'commonscribe-volunteer-log' ); ?></th>
								<th><?php esc_html_e( 'Date', 'commonscribe-volunteer-log' ); ?></th>
								<th><?php esc_html_e( 'Notes', 'commonscribe-volunteer-log' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'commonscribe-volunteer-log' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $entries as $entry ) : ?>
								<tr>
									<th class="check-column"><input type="checkbox" class="commonscribe-bulk-id" name="entry_ids[]" value="<?php echo esc_attr( $entry->id ); ?>"></th>
									<td>
										<?php echo esc_html( $entry->volunteer_name ); ?>
										<?php if ( $entry->volunteer_email ) : ?>
											<br><small><?php echo esc_html( $entry->volunteer_email ); ?></small>
										<?php endif; ?>
									</td>
									<td><?php echo $entry->opportunity_id ? esc_html( get_the_title( $entry->opportunity_id ) ) : esc_html__( 'General', 'commonscribe-volunteer-log' ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (float) $entry->hours, 2 ) ); ?></td>
									<td><?php echo esc_html( $entry->date_served ); ?></td>
									<td><?php echo esc_html( $entry->notes ); ?></td>
									<td>
										<a class="button button-primary button-small" href="<?php echo esc_url( self::status_url( $entry->id, 'approved' ) ); ?>"><?php esc_html_e( 'Approve', 'commonscribe-volunteer-log' ); ?></a>
										<a class="button button-small" href="<?php echo esc_url( self::status_url( $entry->id, 'rejected' ) ); ?>"><?php esc_html_e( 'Reject', 'commonscribe-volunteer-log' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function status_url( $id, $status ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'commonscribe_update_status',
					'id'     => $id,
					'status' => $status,
				),
				admin_url( 'admin-post.php' )
			),
			'commonscribe_update_status_' . $id
		);
	}

	private static function delete_url( $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'commonscribe_delete_entry',
					'id'     => $id,
				),
				admin_url( 'admin-post.php' )
			),
			'commonscribe_delete_entry_' . $id
		);
	}

	/**
	 * Apply a status change to one entry and fire emails when newly approved.
	 *
	 * @param int    $id     Entry ID.
	 * @param string $status New status.
	 * @return bool
	 */
	private static function apply_status( $id, $status ) {
		$existing = commonscribe_get_entry( $id );
		if ( ! $existing ) {
			return false;
		}

		global $wpdb;
		$data = array( 'status' => $status );
		if ( 'approved' === $status ) {
			$data['approved_by'] = get_current_user_id();
			$data['approved_at'] = current_time( 'mysql' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
		$wpdb->update( commonscribe_table(), $data, array( 'id' => $id ) );

		if ( 'approved' === $status && 'approved' !== $existing->status ) {
			$updated = commonscribe_get_entry( $id );
			if ( $updated ) {
				COMMONSCRIBE_Emails::notify_approved( $updated );
			}
		}

		return true;
	}

	public static function handle_update_status() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'commonscribe-volunteer-log' ) );
		}
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		if ( ! $id || ! in_array( $status, array( 'approved', 'rejected', 'pending' ), true ) ) {
			wp_die( esc_html__( 'Invalid request.', 'commonscribe-volunteer-log' ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'commonscribe_update_status_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'commonscribe-volunteer-log' ) );
		}

		self::apply_status( $id, $status );

		wp_safe_redirect(
			add_query_arg(
				array(
					'status_updated' => '1',
					'new_status'     => $status,
					'count'          => 1,
				),
				admin_url( 'admin.php?page=commonscribe-pending' )
			)
		);
		exit;
	}

	public static function handle_bulk_status() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'commonscribe-volunteer-log' ) );
		}
		if ( ! isset( $_POST['commonscribe_bulk_status_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['commonscribe_bulk_status_nonce'] ) ), 'commonscribe_bulk_status' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'commonscribe-volunteer-log' ) );
		}

		$status = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) {
			wp_die( esc_html__( 'Invalid request.', 'commonscribe-volunteer-log' ) );
		}

		$ids = isset( $_POST['entry_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['entry_ids'] ) ) : array();
		$ids = array_filter( $ids );
		$count = 0;
		foreach ( $ids as $id ) {
			if ( self::apply_status( $id, $status ) ) {
				$count++;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'status_updated' => '1',
					'new_status'     => $status,
					'count'          => $count,
				),
				admin_url( 'admin.php?page=commonscribe-pending' )
			)
		);
		exit;
	}

	public static function handle_delete_entry() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'commonscribe-volunteer-log' ) );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id ) {
			wp_die( esc_html__( 'Invalid request.', 'commonscribe-volunteer-log' ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'commonscribe_delete_entry_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'commonscribe-volunteer-log' ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
		$wpdb->delete( commonscribe_table(), array( 'id' => $id ), array( '%d' ) );

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=commonscribe-volunteers' ) ) );
		exit;
	}
}
