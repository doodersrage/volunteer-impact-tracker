<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports screen: totals by volunteer and by opportunity, date-range filter,
 * dollar-value estimate, and CSV export for grant applications / board reports.
 */
class VIT_Reports {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_vit_export_csv', array( __CLASS__, 'export_csv' ) );
	}

	public static function add_menu() {
		add_submenu_page(
			'vit-volunteers',
			__( 'Reports', 'volunteer-impact-tracker' ),
			__( 'Reports', 'volunteer-impact-tracker' ),
			VIT_CAPABILITY,
			'vit-reports',
			array( __CLASS__, 'render' )
		);
	}

	private static function get_filters() {
		$year_start = current_time( 'Y' ) . '-01-01';
		$start      = isset( $_GET['vit_start'] ) ? vit_sanitize_date( wp_unslash( $_GET['vit_start'] ) ) : $year_start;
		$end        = isset( $_GET['vit_end'] ) ? vit_sanitize_date( wp_unslash( $_GET['vit_end'] ) ) : vit_today();

		if ( '' === $start ) {
			$start = $year_start;
		}
		if ( '' === $end ) {
			$end = vit_today();
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
		$table = $wpdb->prefix . VIT_TABLE_HOURS;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'approved' AND date_served BETWEEN %s AND %s ORDER BY date_served ASC",
				$start,
				$end
			)
		);
	}

	public static function render() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			return;
		}

		list( $start, $end ) = self::get_filters();
		$rows                = self::query_rows( $start, $end );
		$hourly_value        = (float) VIT_Settings::get( 'hourly_value', 33.49 );

		$total_hours      = 0;
		$by_volunteer      = array();
		$by_opportunity    = array();

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
					'title' => $okey ? get_the_title( $okey ) : __( 'General', 'volunteer-impact-tracker' ),
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
					'action'    => 'vit_export_csv',
					'vit_start' => $start,
					'vit_end'   => $end,
				),
				admin_url( 'admin-post.php' )
			),
			'vit_export_csv'
		);
		?>
		<div class="wrap vit-wrap">
			<h1><?php esc_html_e( 'Volunteer Impact Reports', 'volunteer-impact-tracker' ); ?></h1>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="vit-filter-form">
				<input type="hidden" name="page" value="vit-reports">
				<label for="vit_start"><?php esc_html_e( 'From', 'volunteer-impact-tracker' ); ?>
					<input type="date" id="vit_start" name="vit_start" value="<?php echo esc_attr( $start ); ?>">
				</label>
				<label for="vit_end"><?php esc_html_e( 'To', 'volunteer-impact-tracker' ); ?>
					<input type="date" id="vit_end" name="vit_end" value="<?php echo esc_attr( $end ); ?>">
				</label>
				<?php submit_button( __( 'Filter', 'volunteer-impact-tracker' ), 'secondary', '', false ); ?>
				<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'volunteer-impact-tracker' ); ?></a>
			</form>

			<div class="vit-summary-cards">
				<div class="vit-card">
					<span class="vit-card-value"><?php echo esc_html( number_format_i18n( $total_hours, 2 ) ); ?></span>
					<span class="vit-card-label"><?php esc_html_e( 'Total Approved Hours', 'volunteer-impact-tracker' ); ?></span>
				</div>
				<div class="vit-card">
					<span class="vit-card-value"><?php echo esc_html( number_format_i18n( count( $by_volunteer ) ) ); ?></span>
					<span class="vit-card-label"><?php esc_html_e( 'Volunteers', 'volunteer-impact-tracker' ); ?></span>
				</div>
				<div class="vit-card">
					<span class="vit-card-value">$<?php echo esc_html( number_format_i18n( $total_hours * $hourly_value, 2 ) ); ?></span>
					<span class="vit-card-label"><?php esc_html_e( 'Estimated In-Kind Value', 'volunteer-impact-tracker' ); ?></span>
				</div>
			</div>
			<p class="description">
				<?php
				printf(
					/* translators: %s: dollar amount per hour */
					esc_html__( 'Estimated value uses %s per hour, set on the Settings screen. Update it to the current published estimate before using this figure in a grant report.', 'volunteer-impact-tracker' ),
					'$' . esc_html( number_format_i18n( $hourly_value, 2 ) )
				);
				?>
			</p>

			<h2><?php esc_html_e( 'Hours by Volunteer', 'volunteer-impact-tracker' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Volunteer', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Entries', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Total Hours', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Certificate', 'volunteer-impact-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $by_volunteer ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No approved hours in this date range.', 'volunteer-impact-tracker' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $by_volunteer as $v ) : ?>
							<tr>
								<td><?php echo esc_html( $v['name'] ); ?><?php if ( $v['email'] ) : ?><br><small><?php echo esc_html( $v['email'] ); ?></small><?php endif; ?></td>
								<td><?php echo esc_html( $v['count'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $v['hours'], 2 ) ); ?></td>
								<td>
									<?php if ( $v['email'] ) : ?>
										<?php $cert_url = VIT_Certificate::get_url( $v['email'], $start, $end ); ?>
										<a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( $cert_url ); ?>"><?php esc_html_e( 'View', 'volunteer-impact-tracker' ); ?></a>
										<button type="button" class="button button-small vit-copy-link" data-url="<?php echo esc_attr( $cert_url ); ?>"><?php esc_html_e( 'Copy link', 'volunteer-impact-tracker' ); ?></button>
									<?php else : ?>
										<span class="description"><?php esc_html_e( 'Needs email', 'volunteer-impact-tracker' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Hours by Opportunity', 'volunteer-impact-tracker' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Opportunity', 'volunteer-impact-tracker' ); ?></th>
						<th><?php esc_html_e( 'Total Hours', 'volunteer-impact-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $by_opportunity ) ) : ?>
						<tr><td colspan="2"><?php esc_html_e( 'No approved hours in this date range.', 'volunteer-impact-tracker' ); ?></td></tr>
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
		<script>
		(function () {
			document.querySelectorAll('.vit-copy-link').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var url = btn.getAttribute('data-url') || '';
					var done = function () {
						var original = btn.textContent;
						btn.textContent = <?php echo wp_json_encode( __( 'Copied!', 'volunteer-impact-tracker' ) ); ?>;
						setTimeout(function () { btn.textContent = original; }, 1500);
					};
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(url).then(done).catch(function () {
							window.prompt(<?php echo wp_json_encode( __( 'Copy this certificate link:', 'volunteer-impact-tracker' ) ); ?>, url);
						});
					} else {
						window.prompt(<?php echo wp_json_encode( __( 'Copy this certificate link:', 'volunteer-impact-tracker' ) ); ?>, url);
					}
				});
			});
		})();
		</script>
		<?php
	}

	public static function export_csv() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'volunteer-impact-tracker' ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'vit_export_csv' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'volunteer-impact-tracker' ) );
		}

		list( $start, $end ) = self::get_filters();
		$rows = self::query_rows( $start, $end );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=volunteer-hours-' . $start . '-to-' . $end . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Volunteer Name', 'Volunteer Email', 'Opportunity', 'Hours', 'Date Served', 'Notes' ) );

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
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

		fclose( $output );
		exit;
	}
}
