<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports screen: totals, CSV export, certificates (view / copy / email).
 */
class COMMONSCRIBE_Reports {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_commonscribe_export_csv', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_commonscribe_email_certificate', array( __CLASS__, 'email_certificate' ) );
	}

	public static function add_menu() {
		add_submenu_page(
			'commonscribe-volunteers',
			__( 'Reports', 'commonscribe-volunteer-log' ),
			__( 'Reports', 'commonscribe-volunteer-log' ),
			COMMONSCRIBE_CAPABILITY,
			'commonscribe-reports',
			array( __CLASS__, 'render' )
		);
	}

	private static function get_filters() {
		$year_start = current_time( 'Y' ) . '-01-01';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter query arg; capability checked by caller.
		$start = isset( $_GET['commonscribe_start'] ) ? commonscribe_sanitize_date( sanitize_text_field( wp_unslash( $_GET['commonscribe_start'] ) ) ) : $year_start;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter query arg; capability checked by caller.
		$end = isset( $_GET['commonscribe_end'] ) ? commonscribe_sanitize_date( sanitize_text_field( wp_unslash( $_GET['commonscribe_end'] ) ) ) : commonscribe_today();

		if ( '' === $start ) {
			$start = $year_start;
		}
		if ( '' === $end ) {
			$end = commonscribe_today();
		}
		if ( $start > $end ) {
			$tmp   = $start;
			$start = $end;
			$end   = $tmp;
		}

		return array( $start, $end );
	}

	private static function query_rows( $start, $end ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE status = 'approved' AND date_served BETWEEN %s AND %s ORDER BY date_served ASC",
				$wpdb->prefix . COMMONSCRIBE_TABLE_HOURS,
				$start,
				$end
			)
		);
	}

	/**
	 * Escape and echo one CSV row (streaming download; no file handle).
	 *
	 * @param array $fields Column values.
	 */
	private static function echo_csv_row( $fields ) {
		$escaped = array();
		foreach ( $fields as $field ) {
			$field = (string) $field;
			if ( strpos( $field, '"' ) !== false || strpos( $field, ',' ) !== false || strpos( $field, "\n" ) !== false || strpos( $field, "\r" ) !== false ) {
				$field = '"' . str_replace( '"', '""', $field ) . '"';
			}
			$escaped[] = $field;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download body; fields escaped above.
		echo implode( ',', $escaped ) . "\n";
	}

	public static function render() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			return;
		}

		list( $start, $end ) = self::get_filters();
		$rows                = self::query_rows( $start, $end );
		$hourly_value        = (float) COMMONSCRIBE_Settings::get( 'hourly_value', 33.49 );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above.
		if ( isset( $_GET['cert_sent'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Certificate email sent.', 'commonscribe-volunteer-log' ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display query arg; capability checked above.
		if ( isset( $_GET['cert_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not send certificate email.', 'commonscribe-volunteer-log' ) . '</p></div>';
		}

		$total_hours   = 0;
		$by_volunteer  = array();
		$by_opportunity = array();

		foreach ( $rows as $row ) {
			$total_hours += (float) $row->hours;

			$vkey = $row->volunteer_email ? $row->volunteer_email : $row->volunteer_name;
			if ( ! isset( $by_volunteer[ $vkey ] ) ) {
				$by_volunteer[ $vkey ] = array(
					'name'  => $row->volunteer_name,
					'email' => $row->volunteer_email,
					'hours' => 0,
					'count' => 0,
				);
			}
			$by_volunteer[ $vkey ]['hours'] += (float) $row->hours;
			$by_volunteer[ $vkey ]['count'] += 1;

			$okey = $row->opportunity_id ? $row->opportunity_id : 0;
			if ( ! isset( $by_opportunity[ $okey ] ) ) {
				$by_opportunity[ $okey ] = array(
					'title' => $okey ? get_the_title( $okey ) : __( 'General', 'commonscribe-volunteer-log' ),
					'hours' => 0,
				);
			}
			$by_opportunity[ $okey ]['hours'] += (float) $row->hours;
		}

		uasort(
			$by_volunteer,
			function ( $a, $b ) {
				return $b['hours'] <=> $a['hours'];
			}
		);
		uasort(
			$by_opportunity,
			function ( $a, $b ) {
				return $b['hours'] <=> $a['hours'];
			}
		);

		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'    => 'commonscribe_export_csv',
					'commonscribe_start' => $start,
					'commonscribe_end'   => $end,
				),
				admin_url( 'admin-post.php' )
			),
			'commonscribe_export_csv'
		);
		?>
		<div class="wrap commonscribe-wrap">
			<h1><?php esc_html_e( 'Hours Reports', 'commonscribe-volunteer-log' ); ?></h1>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="commonscribe-filter-form">
				<input type="hidden" name="page" value="commonscribe-reports">
				<label for="commonscribe_start"><?php esc_html_e( 'From', 'commonscribe-volunteer-log' ); ?>
					<input type="date" id="commonscribe_start" name="commonscribe_start" value="<?php echo esc_attr( $start ); ?>">
				</label>
				<label for="commonscribe_end"><?php esc_html_e( 'To', 'commonscribe-volunteer-log' ); ?>
					<input type="date" id="commonscribe_end" name="commonscribe_end" value="<?php echo esc_attr( $end ); ?>">
				</label>
				<?php submit_button( __( 'Filter', 'commonscribe-volunteer-log' ), 'secondary', '', false ); ?>
				<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'commonscribe-volunteer-log' ); ?></a>
			</form>

			<div class="commonscribe-summary-cards">
				<div class="commonscribe-card">
					<span class="commonscribe-card-value"><?php echo esc_html( number_format_i18n( $total_hours, 2 ) ); ?></span>
					<span class="commonscribe-card-label"><?php esc_html_e( 'Total Approved Hours', 'commonscribe-volunteer-log' ); ?></span>
				</div>
				<div class="commonscribe-card">
					<span class="commonscribe-card-value"><?php echo esc_html( number_format_i18n( count( $by_volunteer ) ) ); ?></span>
					<span class="commonscribe-card-label"><?php esc_html_e( 'Volunteers', 'commonscribe-volunteer-log' ); ?></span>
				</div>
				<div class="commonscribe-card">
					<span class="commonscribe-card-value">$<?php echo esc_html( number_format_i18n( $total_hours * $hourly_value, 2 ) ); ?></span>
					<span class="commonscribe-card-label"><?php esc_html_e( 'Estimated In-Kind Value', 'commonscribe-volunteer-log' ); ?></span>
				</div>
			</div>
			<p class="description">
				<?php
				printf(
					/* translators: %s: dollar amount per hour */
					esc_html__( 'Estimated value uses %s per hour, set on the Settings screen. Update it to the current published estimate before using this figure in a grant report.', 'commonscribe-volunteer-log' ),
					'$' . esc_html( number_format_i18n( $hourly_value, 2 ) )
				);
				?>
			</p>

			<h2><?php esc_html_e( 'Hours by Volunteer', 'commonscribe-volunteer-log' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Volunteer', 'commonscribe-volunteer-log' ); ?></th>
						<th><?php esc_html_e( 'Entries', 'commonscribe-volunteer-log' ); ?></th>
						<th><?php esc_html_e( 'Total Hours', 'commonscribe-volunteer-log' ); ?></th>
						<th><?php esc_html_e( 'Certificate', 'commonscribe-volunteer-log' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $by_volunteer ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No approved hours in this date range.', 'commonscribe-volunteer-log' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $by_volunteer as $v ) : ?>
							<tr>
								<td><?php echo esc_html( $v['name'] ); ?><?php if ( $v['email'] ) : ?><br><small><?php echo esc_html( $v['email'] ); ?></small><?php endif; ?></td>
								<td><?php echo esc_html( $v['count'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $v['hours'], 2 ) ); ?></td>
								<td>
									<?php if ( $v['email'] ) : ?>
										<?php $cert_url = COMMONSCRIBE_Certificate::get_url( $v['email'], $start, $end ); ?>
										<a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( $cert_url ); ?>"><?php esc_html_e( 'View', 'commonscribe-volunteer-log' ); ?></a>
										<button type="button" class="button button-small commonscribe-copy-link" data-url="<?php echo esc_attr( $cert_url ); ?>"><?php esc_html_e( 'Copy link', 'commonscribe-volunteer-log' ); ?></button>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="commonscribe-inline-form">
											<input type="hidden" name="action" value="commonscribe_email_certificate">
											<input type="hidden" name="email" value="<?php echo esc_attr( $v['email'] ); ?>">
											<input type="hidden" name="name" value="<?php echo esc_attr( $v['name'] ); ?>">
											<input type="hidden" name="commonscribe_start" value="<?php echo esc_attr( $start ); ?>">
											<input type="hidden" name="commonscribe_end" value="<?php echo esc_attr( $end ); ?>">
											<?php wp_nonce_field( 'commonscribe_email_certificate_' . $v['email'] ); ?>
											<button type="submit" class="button button-small"><?php esc_html_e( 'Email', 'commonscribe-volunteer-log' ); ?></button>
										</form>
									<?php else : ?>
										<span class="description"><?php esc_html_e( 'Needs email', 'commonscribe-volunteer-log' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Hours by Opportunity', 'commonscribe-volunteer-log' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Opportunity', 'commonscribe-volunteer-log' ); ?></th>
						<th><?php esc_html_e( 'Total Hours', 'commonscribe-volunteer-log' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $by_opportunity ) ) : ?>
						<tr><td colspan="2"><?php esc_html_e( 'No approved hours in this date range.', 'commonscribe-volunteer-log' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $by_opportunity as $o ) : ?>
							<tr>
								<td><?php echo esc_html( $o['title'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $o['hours'], 2 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function email_certificate() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'commonscribe-volunteer-log' ) );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( ! $email || ! isset( $_POST['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'commonscribe_email_certificate_' . $email ) ) {
			wp_die( esc_html__( 'Security check failed.', 'commonscribe-volunteer-log' ) );
		}

		$start = isset( $_POST['commonscribe_start'] ) ? commonscribe_sanitize_date( sanitize_text_field( wp_unslash( $_POST['commonscribe_start'] ) ) ) : '';
		$end   = isset( $_POST['commonscribe_end'] ) ? commonscribe_sanitize_date( sanitize_text_field( wp_unslash( $_POST['commonscribe_end'] ) ) ) : '';
		if ( '' === $start || '' === $end ) {
			list( $start, $end ) = self::get_filters();
		}

		$ok = COMMONSCRIBE_Emails::send_certificate( $email, $name, $start, $end );

		$args = array(
			'page'      => 'commonscribe-reports',
			'commonscribe_start' => $start,
			'commonscribe_end'   => $end,
		);
		if ( $ok ) {
			$args['cert_sent'] = '1';
		} else {
			$args['cert_error'] = '1';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function export_csv() {
		if ( ! current_user_can( COMMONSCRIBE_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'commonscribe-volunteer-log' ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'commonscribe_export_csv' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'commonscribe-volunteer-log' ) );
		}

		list( $start, $end ) = self::get_filters();
		$rows = self::query_rows( $start, $end );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=volunteer-hours-' . $start . '-to-' . $end . '.csv' );

		self::echo_csv_row( array( 'Volunteer Name', 'Volunteer Email', 'Opportunity', 'Hours', 'Date Served', 'Notes' ) );

		foreach ( $rows as $row ) {
			self::echo_csv_row(
				array(
					$row->volunteer_name,
					$row->volunteer_email,
					$row->opportunity_id ? get_the_title( $row->opportunity_id ) : 'General',
					$row->hours,
					$row->date_served,
					$row->notes,
				)
			);
		}

		exit;
	}
}
