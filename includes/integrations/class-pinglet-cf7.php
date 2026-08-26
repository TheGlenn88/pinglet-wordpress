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
 * Sends a push when a Contact Form 7 form is submitted and mailed.
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
		add_action( 'wpcf7_mail_sent', array( __CLASS__, 'handle_submission' ) );
	}

	/**
	 * Build and send the notification for a submission.
	 *
	 * @param WPCF7_ContactForm $contact_form The submitted form.
	 * @return void
	 */
	public static function handle_submission( $contact_form ) {
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

		pinglet_notify(
			array(
				'title'   => __( 'New contact form submission', 'pinglet' ),
				'message' => $message,
				'level'   => 'info',
			)
		);
	}
}
