<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transactional emails: pending admin alert, volunteer approval, certificate send.
 */
class VIT_Emails {

	public static function init() {
		// No hooks — called from other classes.
	}

	/**
	 * Recipients for admin notifications (users who can manage volunteers).
	 *
	 * @return string[]
	 */
	public static function admin_emails() {
		$users = get_users(
			array(
				'capability' => VIT_CAPABILITY,
				'fields'     => array( 'user_email' ),
			)
		);
		$emails = array();
		foreach ( $users as $user ) {
			if ( ! empty( $user->user_email ) ) {
				$emails[] = $user->user_email;
			}
		}
		$emails = array_unique( array_filter( $emails ) );
		if ( empty( $emails ) ) {
			$admin = get_option( 'admin_email' );
			if ( $admin ) {
				$emails[] = $admin;
			}
		}
		return $emails;
	}

	/**
	 * Notify managers that a pending entry needs review.
	 *
	 * @param object $entry Hours row.
	 */
	public static function notify_pending( $entry ) {
		if ( ! (int) VIT_Settings::get( 'notify_admin_pending', 1 ) ) {
			return;
		}
		if ( ! $entry || 'pending' !== $entry->status ) {
			return;
		}

		$org     = VIT_Settings::get( 'org_name', get_bloginfo( 'name' ) );
		$subject = sprintf(
			/* translators: %s: organization name */
			__( '[%s] New volunteer hours awaiting approval', 'volunteer-impact-tracker' ),
			$org
		);
		$opp = $entry->opportunity_id ? get_the_title( $entry->opportunity_id ) : __( 'General', 'volunteer-impact-tracker' );
		$url = admin_url( 'admin.php?page=vit-pending' );

		$body = sprintf(
			/* translators: 1: volunteer name, 2: hours, 3: date, 4: opportunity, 5: review URL */
			__( "%1\$s submitted %2\$s hours on %3\$s (%4\$s).\n\nReview pending entries:\n%5\$s\n", 'volunteer-impact-tracker' ),
			$entry->volunteer_name,
			number_format_i18n( (float) $entry->hours, 2 ),
			$entry->date_served,
			$opp,
			$url
		);

		foreach ( self::admin_emails() as $to ) {
			wp_mail( $to, $subject, $body );
		}
	}

	/**
	 * Notify volunteer that their entry was approved.
	 *
	 * @param object $entry Hours row.
	 */
	public static function notify_approved( $entry ) {
		if ( ! (int) VIT_Settings::get( 'notify_volunteer_approved', 0 ) ) {
			return;
		}
		if ( ! $entry || empty( $entry->volunteer_email ) ) {
			return;
		}

		$org     = VIT_Settings::get( 'org_name', get_bloginfo( 'name' ) );
		$subject = sprintf(
			/* translators: %s: organization name */
			__( '[%s] Your volunteer hours were approved', 'volunteer-impact-tracker' ),
			$org
		);
		$opp = $entry->opportunity_id ? get_the_title( $entry->opportunity_id ) : __( 'General', 'volunteer-impact-tracker' );

		$body = sprintf(
			/* translators: 1: volunteer name, 2: hours, 3: date, 4: opportunity, 5: org name */
			__( "Hi %1\$s,\n\nYour %2\$s hours on %3\$s (%4\$s) have been approved by %5\$s. Thank you for volunteering!\n", 'volunteer-impact-tracker' ),
			$entry->volunteer_name,
			number_format_i18n( (float) $entry->hours, 2 ),
			$entry->date_served,
			$opp,
			$org
		);

		wp_mail( $entry->volunteer_email, $subject, $body );
	}

	/**
	 * Email a certificate link to a volunteer.
	 *
	 * @param string $email Volunteer email.
	 * @param string $name  Volunteer name.
	 * @param string $start Start date Y-m-d.
	 * @param string $end   End date Y-m-d.
	 * @return bool
	 */
	public static function send_certificate( $email, $name, $start, $end ) {
		$email = sanitize_email( $email );
		if ( ! $email ) {
			return false;
		}

		$org     = VIT_Settings::get( 'org_name', get_bloginfo( 'name' ) );
		$url     = VIT_Certificate::get_url( $email, $start, $end );
		$subject = sprintf(
			/* translators: %s: organization name */
			__( '[%s] Your certificate of volunteer service', 'volunteer-impact-tracker' ),
			$org
		);

		$body = sprintf(
			/* translators: 1: volunteer name, 2: org, 3: start, 4: end, 5: certificate URL */
			__( "Hi %1\$s,\n\n%2\$s has issued a certificate of service for your volunteer hours between %3\$s and %4\$s.\n\nView or print your certificate:\n%5\$s\n", 'volunteer-impact-tracker' ),
			$name ? $name : $email,
			$org,
			$start,
			$end,
			$url
		);

		return (bool) wp_mail( $email, $subject, $body );
	}
}
