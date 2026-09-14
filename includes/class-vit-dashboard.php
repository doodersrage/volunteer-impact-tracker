<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Admin dashboard widget: YTD hours, pending count, top volunteers.
 */
class VIT_Dashboard {

	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		if ( ! current_user_can( VIT_CAPABILITY ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'vit_dashboard',
			__( 'Volunteer Impact', 'volunteer-impact-tracker' ),
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		global $wpdb;
		$table      = $wpdb->prefix . VIT_TABLE_HOURS;
		$year_start = current_time( 'Y' ) . '-01-01';
		$today      = vit_today();
		$pending    = vit_pending_count();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
		$ytd_hours = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(hours),0) FROM %i WHERE status = 'approved' AND date_served BETWEEN %s AND %s",
				$table,
				$year_start,
				$today
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
		$top = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT volunteer_name, volunteer_email, SUM(hours) AS total_hours
				FROM %i
				WHERE status = 'approved' AND date_served BETWEEN %s AND %s
				GROUP BY volunteer_email, volunteer_name
				ORDER BY total_hours DESC
				LIMIT 5",
				$table,
				$year_start,
				$today
			)
		);

		$hourly = (float) VIT_Settings::get( 'hourly_value', 33.49 );
		?>
		<p>
			<strong><?php echo esc_html( number_format_i18n( $ytd_hours, 2 ) ); ?></strong>
			<?php esc_html_e( 'approved hours YTD', 'volunteer-impact-tracker' ); ?>
			·
			$<?php echo esc_html( number_format_i18n( $ytd_hours * $hourly, 0 ) ); ?>
			<?php esc_html_e( 'est. in-kind', 'volunteer-impact-tracker' ); ?>
		</p>
		<?php if ( $pending > 0 ) : ?>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vit-pending' ) ); ?>">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: pending count */
							_n( '%d entry awaiting approval', '%d entries awaiting approval', $pending, 'volunteer-impact-tracker' ),
							$pending
						)
					);
					?>
				</a>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $top ) ) : ?>
			<p><strong><?php esc_html_e( 'Top volunteers (YTD)', 'volunteer-impact-tracker' ); ?></strong></p>
			<ol class="vit-dash-top">
				<?php foreach ( $top as $row ) : ?>
					<li>
						<?php echo esc_html( $row->volunteer_name ); ?>
						— <?php echo esc_html( number_format_i18n( (float) $row->total_hours, 2 ) ); ?>
						<?php esc_html_e( 'hrs', 'volunteer-impact-tracker' ); ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No approved hours yet this year.', 'volunteer-impact-tracker' ); ?></p>
		<?php endif; ?>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=vit-volunteers' ) ); ?>"><?php esc_html_e( 'Log Hours', 'volunteer-impact-tracker' ); ?></a>
			|
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=vit-reports' ) ); ?>"><?php esc_html_e( 'Reports', 'volunteer-impact-tracker' ); ?></a>
		</p>
		<?php
	}
}
