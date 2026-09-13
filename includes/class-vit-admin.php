<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens: menu registration, Log Hours, Pending Approvals.
 * Reports and Settings register their own submenus in their own classes.
 */
class VIT_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 5 );
		add_action( 'admin_post_vit_add_hours', array( __CLASS__, 'handle_add_hours' ) );
		add_action( 'admin_post_vit_update_status', array( __CLASS__, 'handle_update_status' ) );
		add_action( 'admin_post_vit_delete_entry', array( __CLASS__, 'handle_delete_entry' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
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

		add_submenu_page(
			'vit-volunteers',
			__( 'Pending Approvals', 'volunteer-impact-tracker' ),
			__( 'Pending Approvals', 'volunteer-impact-tracker' ),
			VIT_CAPABILITY,
			'vit-pending',
			array( __CLASS__, 'render_pending' )
		);
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

	/** ---------- Log Hours screen ---------- */

	public static function render_log_hours() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . VIT_TABLE_HOURS;

		if ( isset( $_GET['added'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Hours logged and approved.', 'volunteer-impact-tracker' ) . '</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry deleted.', 'volunteer-impact-tracker' ) . '</p></div>';
		}

		$opportunities = self::get_opportunities();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table, no caching layer needed for MVP.
		$entries = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY date_served DESC, id DESC LIMIT 100" );
		?>
		<div class="wrap vit-wrap">
			<h1><?php esc_html_e( 'Volunteer Hours', 'volunteer-impact-tracker' ); ?></h1>

			<h2><?php esc_html_e( 'Log Hours', 'volunteer-impact-tracker' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Entries logged here by an admin are recorded as approved immediately.', 'volunteer-impact-tracker' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vit-form">
				<input type="hidden" name="action" value="vit_add_hours">
				<?php wp_nonce_field( 'vit_add_hours', 'vit_add_hours_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="volunteer_name"><?php esc_html_e( 'Volunteer Name', 'volunteer-impact-tracker' ); ?></label></th>
						<td><input type="text" required id="volunteer_name" name="volunteer_name" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="volunteer_email"><?php esc_html_e( 'Volunteer Email (optional)', 'volunteer-impact-tracker' ); ?></label></th>
						<td><input type="email" id="volunteer_email" name="volunteer_email" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="opportunity_id"><?php esc_html_e( 'Opportunity', 'volunteer-impact-tracker' ); ?></label></th>
						<td>
							<select id="opportunity_id" name="opportunity_id">
								<option value=""><?php esc_html_e( '— General / Unlisted —', 'volunteer-impact-tracker' ); ?></option>
								<?php foreach ( $opportunities as $opp ) : ?>
									<option value="<?php echo esc_attr( $opp->ID ); ?>"><?php echo esc_html( $opp->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="hours"><?php esc_html_e( 'Hours', 'volunteer-impact-tracker' ); ?></label></th>
						<td><input type="number" step="0.25" min="0" required id="hours" name="hours" style="width:100px;"></td>
					</tr>
					<tr>
						<th><label for="date_served"><?php esc_html_e( 'Date Served', 'volunteer-impact-tracker' ); ?></label></th>
						<td><input type="date" required id="date_served" name="date_served" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="notes"><?php esc_html_e( 'Notes', 'volunteer-impact-tracker' ); ?></label></th>
						<td><textarea id="notes" name="notes" rows="2" class="large-text"></textarea></td>
					</tr>
				</table>
				<?php submit_button( __( 'Log Hours', 'volunteer-impact-tracker' ) ); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Recent Entries', 'volunteer-impact-tracker' ); ?></h2>
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
						<tr><td colspan="6"><?php esc_html_e( 'No hours logged yet.', 'volunteer-impact-tracker' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $entry->volunteer_name ); ?>
									<?php if ( $entry->volunteer_email ) : ?>
										<br><small><?php echo esc_html( $entry->volunteer_email ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo $entry->opportunity_id ? esc_html( get_the_title( $entry->opportunity_id ) ) : esc_html__( 'General', 'volunteer-impact-tracker' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $entry->hours, 2 ) ); ?></td>
								<td><?php echo esc_html( $entry->date_served ); ?></td>
								<td><?php echo esc_html( ucfirst( $entry->status ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( self::delete_url( $entry->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this entry?', 'volunteer-impact-tracker' ) ); ?>');"><?php esc_html_e( 'Delete', 'volunteer-impact-tracker' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function handle_add_hours() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'volunteer-impact-tracker' ) );
		}
		if ( ! isset( $_POST['vit_add_hours_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vit_add_hours_nonce'] ) ), 'vit_add_hours' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'volunteer-impact-tracker' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . VIT_TABLE_HOURS;

		$wpdb->insert(
			$table,
			array(
				'opportunity_id'  => ! empty( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : null,
				'user_id'         => get_current_user_id(),
				'volunteer_name'  => sanitize_text_field( wp_unslash( $_POST['volunteer_name'] ) ),
				'volunteer_email' => isset( $_POST['volunteer_email'] ) ? sanitize_email( wp_unslash( $_POST['volunteer_email'] ) ) : '',
				'hours'           => isset( $_POST['hours'] ) ? (float) $_POST['hours'] : 0,
				'date_served'     => isset( $_POST['date_served'] ) ? sanitize_text_field( wp_unslash( $_POST['date_served'] ) ) : gmdate( 'Y-m-d' ),
				'notes'           => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
				'status'          => 'approved',
				'created_at'      => current_time( 'mysql' ),
				'approved_by'     => get_current_user_id(),
				'approved_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		wp_safe_redirect( add_query_arg( 'added', '1', admin_url( 'admin.php?page=vit-volunteers' ) ) );
		exit;
	}

	/** ---------- Pending Approvals screen ---------- */

	public static function render_pending() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . VIT_TABLE_HOURS;

		if ( isset( $_GET['status_updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entry updated.', 'volunteer-impact-tracker' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		$entries = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY date_served DESC, id DESC" );
		?>
		<div class="wrap vit-wrap">
			<h1><?php esc_html_e( 'Pending Approvals', 'volunteer-impact-tracker' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Hours volunteers submitted themselves through the front-end form, waiting for review.', 'volunteer-impact-tracker' ); ?></p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Volunteer', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Opportunity', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Hours', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Date', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'volunteer-impact-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Nothing waiting for approval.', 'volunteer-impact-tracker' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
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
					<?php endif; ?>
				</tbody>
			</table>
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

		global $wpdb;
		$table = $wpdb->prefix . VIT_TABLE_HOURS;

		$data = array( 'status' => $status );
		$fmt  = array( '%s' );
		if ( 'approved' === $status ) {
			$data['approved_by'] = get_current_user_id();
			$data['approved_at'] = current_time( 'mysql' );
			$fmt[]                = '%d';
			$fmt[]                = '%s';
		}

		$wpdb->update( $table, $data, array( 'id' => $id ), $fmt, array( '%d' ) );

		wp_safe_redirect( add_query_arg( 'status_updated', '1', admin_url( 'admin.php?page=vit-pending' ) ) );
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
		$table = $wpdb->prefix . VIT_TABLE_HOURS;
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=vit-volunteers' ) ) );
		exit;
	}
}
