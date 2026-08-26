<?php
/**
 * Contact Form 7 integration.
 *
 * @package Pinglet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a push when a Contact Form 7 form is submitted.
 *
 * Hooks wpcf7_submit rather than wpcf7_mail_sent on purpose: mail_sent never
 * fires on sites whose outgoing email is broken, and those are exactly the
 * sites that want push notifications as the reliable channel. A submission
 * whose mail failed still notifies (with a warning level so the site owner
 * knows the email copy did not go out).
 */
class Pinglet_CF7 {

	/**
	 * Whether Contact Form 7 is active.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( 'WPCF7_Submission' );
	}

	/**
	 * Whether the integration is switched on in the Pinglet settings.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = pinglet_get_settings();
		return ! empty( $settings['enable_cf7'] );
	}

	/**
	 * Hook into Contact Form 7 when available and enabled.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_available() || ! self::is_enabled() ) {
			return;
		}
		add_action( 'wpcf7_submit', array( __CLASS__, 'handle_submission' ), 10, 2 );
	}

	/**
	 * Build and send the notification for a submission.
	 *
	 * @param WPCF7_ContactForm $contact_form The submitted form.
	 * @param array             $result       Submission result, including status.
	 * @return void
	 */
	public static function handle_submission( $contact_form, $result ) {
		$status = isset( $result['status'] ) ? $result['status'] : '';
		// Real submissions only: skip validation failures, spam, and the rest.
		if ( ! in_array( $status, array( 'mail_sent', 'mail_failed' ), true ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();
		if ( ! is_array( $posted ) ) {
			$posted = array();
		}

		$form_title = method_exists( $contact_form, 'title' ) ? $contact_form->title() : '';
		$summary    = pinglet_summarize_fields( $posted );

		$message = sanitize_text_field( $form_title );
		if ( '' !== $summary ) {
			$message = '' !== $message ? $message . "\n" . $summary : $summary;
		}
		if ( '' === $message ) {
			$message = __( 'A contact form was submitted.', 'pinglet' );
		}

		if ( 'mail_failed' === $status ) {
			$message .= "\n" . __( 'Note: the email copy failed to send.', 'pinglet' );
		}

		pinglet_notify(
			array(
				'title'   => __( 'New contact form submission', 'pinglet' ),
				'message' => $message,
				'level'   => 'mail_failed' === $status ? 'warning' : 'info',
			)
		);
	}
}
