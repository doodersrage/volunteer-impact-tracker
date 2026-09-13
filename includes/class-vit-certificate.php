<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Printable certificate of service for a volunteer, covering a date range.
 * Rendered as a standalone print-ready page (no external PDF library needed —
 * the visitor uses their browser's Print / Save as PDF).
 *
 * Links are signed with wp_hash() so the email/date-range parameters can't be
 * tampered with, but the link itself needs no login: it's meant to be handed
 * directly to the volunteer it belongs to, the same way a paper certificate would be.
 */
class VIT_Certificate {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function get_url( $email, $start, $end ) {
		$email = strtolower( trim( $email ) );
		$args  = array(
			'vit_certificate' => 1,
			'email'           => $email, // Let add_query_arg encode once — do not pre-encode.
			'start'           => $start,
			'end'             => $end,
		);
		$args['sig'] = self::sign( $email, $start, $end );

		return add_query_arg( $args, home_url( '/' ) );
	}

	private static function sign( $email, $start, $end ) {
		return wp_hash( strtolower( $email ) . '|' . $start . '|' . $end, 'vit_certificate' );
	}

	public static function maybe_render() {
		if ( empty( $_GET['vit_certificate'] ) ) {
			return;
		}

		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
		$start = isset( $_GET['start'] ) ? vit_sanitize_date( wp_unslash( $_GET['start'] ) ) : '';
		$end   = isset( $_GET['end'] ) ? vit_sanitize_date( wp_unslash( $_GET['end'] ) ) : '';
		$sig   = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

		if ( empty( $email ) || empty( $start ) || empty( $end ) || empty( $sig ) ) {
			wp_die( esc_html__( 'Invalid certificate link.', 'volunteer-impact-tracker' ) );
		}
		if ( ! hash_equals( self::sign( $email, $start, $end ), $sig ) ) {
			wp_die( esc_html__( 'This certificate link is invalid or has been altered.', 'volunteer-impact-tracker' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . VIT_TABLE_HOURS;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom plugin table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'approved' AND volunteer_email = %s AND date_served BETWEEN %s AND %s ORDER BY date_served ASC",
				$email,
				$start,
				$end
			)
		);

		$total_hours = 0;
		$name        = '';
		foreach ( $rows as $row ) {
			$total_hours += (float) $row->hours;
			$name         = $row->volunteer_name;
		}

		if ( empty( $rows ) || $total_hours <= 0 ) {
			wp_die(
				esc_html__( 'No approved volunteer hours were found for this person in the selected date range.', 'volunteer-impact-tracker' ),
				esc_html__( 'Certificate unavailable', 'volunteer-impact-tracker' ),
				array( 'response' => 404 )
			);
		}

		if ( empty( $name ) ) {
			$name = $email;
		}

		$settings     = VIT_Settings::get();
		$org_name     = $settings['org_name'];
		$message      = $settings['certificate_text'];
		$hourly_value = (float) $settings['hourly_value'];
		$dollar_value = $total_hours * $hourly_value;
		$date_range   = wp_date( get_option( 'date_format' ), strtotime( $start . ' 12:00:00' ) ) . ' – ' . wp_date( get_option( 'date_format' ), strtotime( $end . ' 12:00:00' ) );
		$issued_date  = wp_date( get_option( 'date_format' ) );

		nocache_headers();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( sprintf( /* translators: %s volunteer name */ __( 'Certificate of Service — %s', 'volunteer-impact-tracker' ), $name ) ); ?></title>
			<link rel="stylesheet" href="<?php echo esc_url( VIT_PLUGIN_URL . 'assets/css/certificate.css?ver=' . rawurlencode( VIT_VERSION ) ); ?>">
		</head>
		<body>
			<div class="vit-cert-toolbar no-print">
				<button type="button" class="vit-print-btn" onclick="window.print();"><?php esc_html_e( 'Print / Save as PDF', 'volunteer-impact-tracker' ); ?></button>
			</div>
			<div class="vit-certificate">
				<p class="vit-cert-eyebrow"><?php esc_html_e( 'Certificate of Service', 'volunteer-impact-tracker' ); ?></p>
				<h1><?php echo esc_html( $org_name ); ?></h1>
				<p class="vit-cert-presented"><?php esc_html_e( 'This certifies that', 'volunteer-impact-tracker' ); ?></p>
				<p class="vit-cert-name"><?php echo esc_html( $name ); ?></p>
				<p class="vit-cert-body">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: 1: total hours (HTML), 2: date range */
							__( 'contributed %1$s hours of volunteer service between %2$s.', 'volunteer-impact-tracker' ),
							'<strong>' . esc_html( number_format_i18n( $total_hours, 2 ) ) . '</strong>',
							esc_html( $date_range )
						),
						array( 'strong' => array() )
					);
					?>
				</p>
				<?php if ( $hourly_value > 0 ) : ?>
					<p class="vit-cert-value"><?php echo esc_html( sprintf( /* translators: %s dollar amount */ __( 'Estimated in-kind value: $%s', 'volunteer-impact-tracker' ), number_format_i18n( $dollar_value, 2 ) ) ); ?></p>
				<?php endif; ?>
				<p class="vit-cert-message"><?php echo esc_html( $message ); ?></p>
				<p class="vit-cert-issued"><?php echo esc_html( sprintf( /* translators: %s date */ __( 'Issued %s', 'volunteer-impact-tracker' ), $issued_date ) ); ?></p>
			</div>
			<p class="vit-cert-print-hint no-print"><?php esc_html_e( 'Use Print / Save as PDF above, or your browser\'s print dialog, to save this certificate.', 'volunteer-impact-tracker' ); ?></p>
		</body>
		</html>
		<?php
		exit;
	}
}
