<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens: Log Hours (add/edit/search/paginate), Pending Approvals (bulk).
 */
class VIT_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 5 );
		add_action( 'admin_post_vit_add_hours', array( __CLASS__, 'handle_add_hours' ) );
		add_action( 'admin_post_vit_edit_hours', array( __CLASS__, 'handle_edit_hours' ) );
		add_action( 'admin_post_vit_update_status', array( __CLASS__, 'handle_update_status' ) );
		add_action( 'admin_post_vit_bulk_status', array( __CLASS__, 'handle_bulk_status' ) );
		add_action( 'admin_post_vit_delete_entry', array( __CLASS__, 'handle_delete_entry' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'pending_notice' ) );
	}

	public static function enqueue( $hook ) {
		if ( strpos( $hook, 'vit-' ) === false && strpos( $hook, 'vit_opportunity' ) === false ) {
			return;
		}
		wp_enqueue_style( 'vit-admin', VIT_PLUGIN_URL . 'assets/css/admin.css', array(), VIT_VERSION );
	}

	public static function add_menu() {
		add_menu_page(
			__( 'Volunteer Tracker', 'volunteer-impact-tracker' ),
			__( 'Volunteers', 'volunteer-impact-tracker' ),
			VIT_CAPABILITY,
			'vit-volunteers',
			array( __CLASS__, 'render_log_hours' ),
			'dashicons-groups',
			30
		);

		add_submenu_page(
			'vit-volunteers',
			__( 'Log Hours', 'volunteer-impact-tracker' ),
			__( 'Log Hours', 'volunteer-impact-tracker' ),
			VIT_CAPABILITY,
			'vit-volunteers',
			array( __CLASS__, 'render_log_hours' )
		);

		$pending       = vit_pending_count();
		$pending_label = __( 'Pending Approvals', 'volunteer-impact-tracker' );
		if ( $pending > 0 ) {
			$pending_label = sprintf(
				/* translators: %s: pending count markup */
				__( 'Pending Approvals %s', 'volunteer-impact-tracker' ),
				'<span class="awaiting-mod">' . number_format_i18n( $pending ) . '</span>'
			);
		}

		add_submenu_page(
			'vit-volunteers',
			__( 'Pending Approvals', 'volunteer-impact-tracker' ),
			$pending_label,
			VIT_CAPABILITY,
			'vit-pending',
			array( __CLASS__, 'render_pending' )
		);
	}

	public static function pending_notice() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && false !== strpos( $screen->id, 'vit-pending' ) ) {
			return;
		}

		$pending = vit_pending_count();
		if ( $pending < 1 ) {
			return;
		}

		$url  = admin_url( 'admin.php?page=vit-pending' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Review now', 'volunteer-impact-tracker' ) . '</a>';
		echo '<div class="notice notice-warning"><p>';
		echo wp_kses_post(
			sprintf(
				/* translators: 1: number of pending entries, 2: review link HTML */
				_n(
					'%1$d volunteer hour entry is waiting for approval. %2$s',
					'%1$d volunteer hour entries are waiting for approval. %2$s',
					$pending,
					'volunteer-impact-tracker'
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
				'post_type'      => VIT_CPT_OPPORTUNITY,
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
	 * @return array|WP_Error
	 */
	private static function parse_entry_post() {
		$name  = isset( $_POST['volunteer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['volunteer_name'] ) ) : '';
		$email = isset( $_POST['volunteer_email'] ) ? sanitize_email( wp_unslash( $_POST['volunteer_email'] ) ) : '';
		$hours = isset( $_POST['hours'] ) ? vit_sanitize_hours( wp_unslash( $_POST['hours'] ) ) : 0;
		$date  = isset( $_POST['date_served'] ) ? vit_sanitize_date( wp_unslash( $_POST['date_served'] ) ) : '';

		if ( '' === $name || $hours <= 0 || '' === $date || $date > vit_today() ) {
			return new WP_Error( 'invalid', __( 'Invalid entry data.', 'volunteer-impact-tracker' ) );
		}

		$opportunity_id = ! empty( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : 0;
		if ( $opportunity_id && VIT_CPT_OPPORTUNITY !== get_post_type( $opportunity_id ) ) {
			$opportunity_id = 0;
		}

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'approved';
		if ( ! in_array( $status, array( 'approved', 'pending', 'rejected' ), true ) ) {
			$status = 'approved';
		}

		return array(
			'opportunity_id'  => $opportunity_id ? $opportunity_id : null,
			'volunteer_name'  => $name,
			'volunteer_email' => $email,
			'hours'           => $hours,
			'date_served'     => $date,
			'notes'           => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'status'          => $status,
		);
	}

	/** ---------- Log Hours screen ---------- */

	public static function render_log_hours() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			return;
		}
		global $wpdb;
		$table = vit_table();

		if ( isset( $_GET['added'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Hours logged and approved.', 'volunteer-impact-tracker' ) . '</p></div>';
		}
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry updated.', 'volunteer-impact-tracker' ) . '</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry deleted.', 'volunteer-impact-tracker' ) . '</p></div>';
		}
		if ( isset( $_GET['error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not save entry. Check that name, hours (0.25–24), and date are valid.', 'volunteer-impact-tracker' ) . '</p></div>';
		}

		$opportunities = self::get_opportunities();
		$edit_id       = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$edit_entry    = $edit_id ? vit_get_entry( $edit_id ) : null;

		$status_filter = isset( $_GET['vit_status'] ) ? sanitize_key( wp_unslash( $_GET['vit_status'] ) ) : '';
		$allowed       = array( 'approved', 'pending', 'rejected' );
		if ( ! in_array( $status_filter, $allowed, true ) ) {
			$status_filter = '';
		}
		$search = isset( $_GET['vit_s'] ) ? sanitize_text_field( wp_unslash( $_GET['vit_s'] ) ) : '';
		$page   = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$offset = ( $page - 1 ) * VIT_ENTRIES_PER_PAGE;

		$where  = array( '1=1' );
		$params = array();
		if ( $status_filter ) {
			$where[]  = 'status = %s';
			$params[] = $status_filter;
		}
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(volunteer_name LIKE %s OR volunteer_email LIKE %s OR notes LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$where_sql = implode( ' AND ', $where );

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$params ) );
			$query_params = array_merge( $params, array( VIT_ENTRIES_PER_PAGE, $offset ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$entries = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY date_served DESC, id DESC LIMIT %d OFFSET %d",
					...$query_params
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$entries = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY date_served DESC, id DESC LIMIT %d OFFSET %d",
					VIT_ENTRIES_PER_PAGE,
					$offset
				)
			);
		}

		$total_pages = max( 1, (int) ceil( $total / VIT_ENTRIES_PER_PAGE ) );
		?>
		<div class="wrap vit-wrap">
			<h1><?php esc_html_e( 'Volunteer Hours', 'volunteer-impact-tracker' ); ?></h1>

			<?php if ( $edit_entry ) : ?>
				<h2><?php esc_html_e( 'Edit Entry', 'volunteer-impact-tracker' ); ?></h2>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=vit-volunteers' ) ); ?>">&larr; <?php esc_html_e( 'Cancel edit / log new hours', 'volunteer-impact-tracker' ); ?></a></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vit-form">
					<input type="hidden" name="action" value="vit_edit_hours">
					<input type="hidden" name="entry_id" value="<?php echo esc_attr( $edit_entry->id ); ?>">
					<?php wp_nonce_field( 'vit_edit_hours_' . $edit_entry->id, 'vit_edit_hours_nonce' ); ?>
					<?php self::render_entry_fields( $opportunities, $edit_entry, true ); ?>
					<?php submit_button( __( 'Update Entry', 'volunteer-impact-tracker' ) ); ?>
				</form>
			<?php else : ?>
				<h2><?php esc_html_e( 'Log Hours', 'volunteer-impact-tracker' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Entries logged here by an admin are recorded as approved immediately.', 'volunteer-impact-tracker' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vit-form">
					<input type="hidden" name="action" value="vit_add_hours">
					<?php wp_nonce_field( 'vit_add_hours', 'vit_add_hours_nonce' ); ?>
					<?php self::render_entry_fields( $opportunities, null, false ); ?>
					<?php submit_button( __( 'Log Hours', 'volunteer-impact-tracker' ) ); ?>
				</form>
			<?php endif; ?>

			<hr>

			<h2><?php esc_html_e( 'Entries', 'volunteer-impact-tracker' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="vit-filter-form">
				<input type="hidden" name="page" value="vit-volunteers">
				<label for="vit_s"><?php esc_html_e( 'Search', 'volunteer-impact-tracker' ); ?>
					<input type="search" id="vit_s" name="vit_s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, email, or notes…', 'volunteer-impact-tracker' ); ?>">
				</label>
				<label for="vit_status"><?php esc_html_e( 'Status', 'volunteer-impact-tracker' ); ?>
					<select id="vit_status" name="vit_status">
						<option value=""><?php esc_html_e( 'All', 'volunteer-impact-tracker' ); ?></option>
						<option value="approved" <?php selected( $status_filter, 'approved' ); ?>><?php esc_html_e( 'Approved', 'volunteer-impact-tracker' ); ?></option>
						<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'volunteer-impact-tracker' ); ?></option>
						<option value="rejected" <?php selected( $status_filter, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'volunteer-impact-tracker' ); ?></option>
					</select>
				</label>
				<?php submit_button( __( 'Filter', 'volunteer-impact-tracker' ), 'secondary', '', false ); ?>
			</form>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: shown count, 2: total count */
						__( 'Showing %1$d of %2$d entries.', 'volunteer-impact-tracker' ),
						count( $entries ),
						$total
					)
				);
				?>
			</p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Volunteer', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Opportunity', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Hours', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Date', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Status', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'volunteer-impact-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No matching entries.', 'volunteer-impact-tracker' ); ?></td></tr>
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
								<td><?php echo $entry->opportunity_id ? esc_html( get_the_title( $entry->opportunity_id ) ) : esc_html__( 'General', 'volunteer-impact-tracker' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $entry->hours, 2 ) ); ?></td>
								<td><?php echo esc_html( $entry->date_served ); ?></td>
								<td><span class="vit-status vit-status-<?php echo esc_attr( $entry->status ); ?>"><?php echo esc_html( ucfirst( $entry->status ) ); ?></span></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'vit-volunteers', 'edit' => $entry->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'volunteer-impact-tracker' ); ?></a>
									|
									<a href="<?php echo esc_url( self::delete_url( $entry->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this entry?', 'volunteer-impact-tracker' ) ); ?>');"><?php esc_html_e( 'Delete', 'volunteer-impact-tracker' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php self::render_pagination( $page, $total_pages, array( 'page' => 'vit-volunteers', 'vit_status' => $status_filter, 'vit_s' => $search ) ); ?>
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
		$date  = $entry ? $entry->date_served : vit_today();
		$notes = $entry ? $entry->notes : '';
		$status = $entry ? $entry->status : 'approved';
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="volunteer_name"><?php esc_html_e( 'Volunteer Name', 'volunteer-impact-tracker' ); ?></label></th>
				<td><input type="text" required id="volunteer_name" name="volunteer_name" class="regular-text" maxlength="191" value="<?php echo esc_attr( $name ); ?>"></td>
			</tr>
			<tr>
				<th><label for="volunteer_email"><?php esc_html_e( 'Volunteer Email (optional)', 'volunteer-impact-tracker' ); ?></label></th>
				<td>
					<input type="email" id="volunteer_email" name="volunteer_email" class="regular-text" value="<?php echo esc_attr( $email ); ?>">
					<p class="description"><?php esc_html_e( 'Needed for certificates and approval emails.', 'volunteer-impact-tracker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="opportunity_id"><?php esc_html_e( 'Opportunity', 'volunteer-impact-tracker' ); ?></label></th>
				<td>
					<select id="opportunity_id" name="opportunity_id">
						<option value=""><?php esc_html_e( '— General / Unlisted —', 'volunteer-impact-tracker' ); ?></option>
						<?php foreach ( $opportunities as $o ) : ?>
							<option value="<?php echo esc_attr( $o->ID ); ?>" <?php selected( $opp, $o->ID ); ?>><?php echo esc_html( vit_opportunity_label( $o ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="hours"><?php esc_html_e( 'Hours', 'volunteer-impact-tracker' ); ?></label></th>
				<td>
					<input type="number" step="0.25" min="0.25" max="<?php echo esc_attr( VIT_MAX_HOURS_PER_ENTRY ); ?>" required id="hours" name="hours" style="width:100px;" value="<?php echo esc_attr( $hours ); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="date_served"><?php esc_html_e( 'Date Served', 'volunteer-impact-tracker' ); ?></label></th>
				<td><input type="date" required id="date_served" name="date_served" max="<?php echo esc_attr( vit_today() ); ?>" value="<?php echo esc_attr( $date ); ?>"></td>
			</tr>
			<?php if ( $show_status ) : ?>
				<tr>
					<th><label for="status"><?php esc_html_e( 'Status', 'volunteer-impact-tracker' ); ?></label></th>
					<td>
						<select id="status" name="status">
							<option value="approved" <?php selected( $status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'volunteer-impact-tracker' ); ?></option>
							<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'volunteer-impact-tracker' ); ?></option>
							<option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'volunteer-impact-tracker' ); ?></option>
						</select>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th><label for="notes"><?php esc_html_e( 'Notes', 'volunteer-impact-tracker' ); ?></label></th>
				<td><textarea id="notes" name="notes" rows="2" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea></td>
			</tr>
		</table>
		<?php
	}

	private static function render_pagination( $page, $total_pages, $args ) {
		if ( $total_pages <= 1 ) {
			return;
		}
		echo '<div class="vit-pagination tablenav"><div class="tablenav-pages">';
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
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'volunteer-impact-tracker' ) );
		}
		if ( ! isset( $_POST['vit_add_hours_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vit_add_hours_nonce'] ) ), 'vit_add_hours' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'volunteer-impact-tracker' ) );
		}

		$data = self::parse_entry_post();
		if ( is_wp_error( $data ) ) {
			wp_safe_redirect( add_query_arg( 'error', '1', admin_url( 'admin.php?page=vit-volunteers' ) ) );
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
		$wpdb->insert( vit_table(), $row );

		wp_safe_redirect( add_query_arg( 'added', '1', admin_url( 'admin.php?page=vit-volunteers' ) ) );
		exit;
	}

	public static function handle_edit_hours() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'volunteer-impact-tracker' ) );
		}
		$id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		if ( ! $id || ! isset( $_POST['vit_edit_hours_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vit_edit_hours_nonce'] ) ), 'vit_edit_hours_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'volunteer-impact-tracker' ) );
		}

		$existing = vit_get_entry( $id );
		if ( ! $existing ) {
			wp_die( esc_html__( 'Entry not found.', 'volunteer-impact-tracker' ) );
		}

		$data = self::parse_entry_post();
		if ( is_wp_error( $data ) ) {
			wp_safe_redirect( add_query_arg( array( 'edit' => $id, 'error' => '1' ), admin_url( 'admin.php?page=vit-volunteers' ) ) );
			exit;
		}

		if ( 'approved' === $data['status'] && 'approved' !== $existing->status ) {
			$data['approved_by'] = get_current_user_id();
			$data['approved_at'] = current_time( 'mysql' );
		}

		global $wpdb;
		$wpdb->update( vit_table(), $data, array( 'id' => $id ) );

		if ( 'approved' === $data['status'] && 'approved' !== $existing->status ) {
			$updated = vit_get_entry( $id );
			if ( $updated ) {
				VIT_Emails::notify_approved( $updated );
			}
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=vit-volunteers' ) ) );
		exit;
	}

	/** ---------- Pending Approvals ---------- */

	public static function render_pending() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			return;
		}
		global $wpdb;
		$table = vit_table();

		if ( isset( $_GET['status_updated'] ) ) {
			$status = isset( $_GET['new_status'] ) ? sanitize_key( wp_unslash( $_GET['new_status'] ) ) : '';
			$count  = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 1;
			if ( 'approved' === $status ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
					sprintf(
						/* translators: %d: number of entries */
						_n( '%d entry approved.', '%d entries approved.', $count, 'volunteer-impact-tracker' ),
						$count
					)
				) . '</p></div>';
			} elseif ( 'rejected' === $status ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
					sprintf(
						/* translators: %d: number of entries */
						_n( '%d entry rejected.', '%d entries rejected.', $count, 'volunteer-impact-tracker' ),
						$count
					)
				) . '</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry updated.', 'volunteer-impact-tracker' ) . '</p></div>';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$entries = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY date_served DESC, id DESC" );
		?>
		<div class="wrap vit-wrap">
			<h1><?php esc_html_e( 'Pending Approvals', 'volunteer-impact-tracker' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Hours volunteers submitted themselves through the front-end form, waiting for review.', 'volunteer-impact-tracker' ); ?></p>

			<?php if ( empty( $entries ) ) : ?>
				<p><?php esc_html_e( 'Nothing waiting for approval.', 'volunteer-impact-tracker' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="vit_bulk_status">
					<?php wp_nonce_field( 'vit_bulk_status', 'vit_bulk_status_nonce' ); ?>
					<div class="vit-bulk-bar">
						<button type="submit" name="bulk_action" value="approved" class="button button-primary"><?php esc_html_e( 'Approve selected', 'volunteer-impact-tracker' ); ?></button>
						<button type="submit" name="bulk_action" value="rejected" class="button"><?php esc_html_e( 'Reject selected', 'volunteer-impact-tracker' ); ?></button>
					</div>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<td class="check-column"><input type="checkbox" id="vit-select-all" onclick="document.querySelectorAll('.vit-bulk-id').forEach(function(c){c.checked=this.checked;}.bind(this));"></td>
								<th><?php esc_html_e( 'Volunteer', 'volunteer-impact-tracker' ); ?></th>
								<th><?php esc_html_e( 'Opportunity', 'volunteer-impact-tracker' ); ?></th>
								<th><?php esc_html_e( 'Hours', 'volunteer-impact-tracker' ); ?></th>
								<th><?php esc_html_e( 'Date', 'volunteer-impact-tracker' ); ?></th>
								<th><?php esc_html_e( 'Notes', 'volunteer-impact-tracker' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'volunteer-impact-tracker' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $entries as $entry ) : ?>
								<tr>
									<th class="check-column"><input type="checkbox" class="vit-bulk-id" name="entry_ids[]" value="<?php echo esc_attr( $entry->id ); ?>"></th>
									<td>
										<?php echo esc_html( $entry->volunteer_name ); ?>
										<?php if ( $entry->volunteer_email ) : ?>
											<br><small><?php echo esc_html( $entry->volunteer_email ); ?></small>
										<?php endif; ?>
									</td>
									<td><?php echo $entry->opportunity_id ? esc_html( get_the_title( $entry->opportunity_id ) ) : esc_html__( 'General', 'volunteer-impact-tracker' ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (float) $entry->hours, 2 ) ); ?></td>
									<td><?php echo esc_html( $entry->date_served ); ?></td>
									<td><?php echo esc_html( $entry->notes ); ?></td>
									<td>
										<a class="button button-primary button-small" href="<?php echo esc_url( self::status_url( $entry->id, 'approved' ) ); ?>"><?php esc_html_e( 'Approve', 'volunteer-impact-tracker' ); ?></a>
										<a class="button button-small" href="<?php echo esc_url( self::status_url( $entry->id, 'rejected' ) ); ?>"><?php esc_html_e( 'Reject', 'volunteer-impact-tracker' ); ?></a>
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
					'action' => 'vit_update_status',
					'id'     => $id,
					'status' => $status,
				),
				admin_url( 'admin-post.php' )
			),
			'vit_update_status_' . $id
		);
	}

	private static function delete_url( $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'vit_delete_entry',
					'id'     => $id,
				),
				admin_url( 'admin-post.php' )
			),
			'vit_delete_entry_' . $id
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
		$existing = vit_get_entry( $id );
		if ( ! $existing ) {
			return false;
		}

		global $wpdb;
		$data = array( 'status' => $status );
		if ( 'approved' === $status ) {
			$data['approved_by'] = get_current_user_id();
			$data['approved_at'] = current_time( 'mysql' );
		}

		$wpdb->update( vit_table(), $data, array( 'id' => $id ) );

		if ( 'approved' === $status && 'approved' !== $existing->status ) {
			$updated = vit_get_entry( $id );
			if ( $updated ) {
				VIT_Emails::notify_approved( $updated );
			}
		}

		return true;
	}

	public static function handle_update_status() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'volunteer-impact-tracker' ) );
		}
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		if ( ! $id || ! in_array( $status, array( 'approved', 'rejected', 'pending' ), true ) ) {
			wp_die( esc_html__( 'Invalid request.', 'volunteer-impact-tracker' ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'vit_update_status_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'volunteer-impact-tracker' ) );
		}

		self::apply_status( $id, $status );

		wp_safe_redirect(
			add_query_arg(
				array(
					'status_updated' => '1',
					'new_status'     => $status,
					'count'          => 1,
				),
				admin_url( 'admin.php?page=vit-pending' )
			)
		);
		exit;
	}

	public static function handle_bulk_status() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'volunteer-impact-tracker' ) );
		}
		if ( ! isset( $_POST['vit_bulk_status_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vit_bulk_status_nonce'] ) ), 'vit_bulk_status' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'volunteer-impact-tracker' ) );
		}

		$status = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) {
			wp_die( esc_html__( 'Invalid request.', 'volunteer-impact-tracker' ) );
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
				admin_url( 'admin.php?page=vit-pending' )
			)
		);
		exit;
	}

	public static function handle_delete_entry() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'volunteer-impact-tracker' ) );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id ) {
			wp_die( esc_html__( 'Invalid request.', 'volunteer-impact-tracker' ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'vit_delete_entry_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'volunteer-impact-tracker' ) );
		}

		global $wpdb;
		$wpdb->delete( vit_table(), array( 'id' => $id ), array( '%d' ) );

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=vit-volunteers' ) ) );
		exit;
	}
}
