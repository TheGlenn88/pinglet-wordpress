<?php
/**
 * WPForms integration.
 *
 * @package Pinglet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a push when a WPForms entry is processed.
 */
class Pinglet_WPForms {

	/**
	 * Whether WPForms (Lite or Pro) is active.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'wpforms' );
	}

	/**
	 * Whether the integration is switched on in the Pinglet settings.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = pinglet_get_settings();
		return ! empty( $settings['enable_wpforms'] );
	}

	/**
	 * Hook into WPForms when available and enabled.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_available() || ! self::is_enabled() ) {
			return;
		}
		add_action( 'wpforms_process_complete', array( __CLASS__, 'handle_submission' ), 10, 4 );
	}

	/**
	 * Build and send the notification for an entry.
	 *
	 * @param array $fields    Sanitized entry field values and labels.
	 * @param array $entry     Original raw $_POST entry data.
	 * @param array $form_data Processed form settings.
	 * @param int   $entry_id  Entry ID (0 in WPForms Lite).
	 * @return void
	 */
	public static function handle_submission( $fields, $entry, $form_data, $entry_id ) {
		$pairs = array();

		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['name'], $field['value'] ) ) {
					continue;
				}
				$pairs[ (string) $field['name'] ] = $field['value'];
			}
		}

		$form_title = '';
		if ( is_array( $form_data ) && ! empty( $form_data['settings']['form_title'] ) ) {
			$form_title = sanitize_text_field( $form_data['settings']['form_title'] );
		}

		$summary = pinglet_summarize_fields( $pairs );

		$message = $form_title;
		if ( '' !== $summary ) {
			$message = '' !== $message ? $message . "\n" . $summary : $summary;
		}
		if ( '' === $message ) {
			$message = __( 'A form was submitted.', 'pinglet' );
		}

		pinglet_notify(
			array(
				'title'   => __( 'New WPForms submission', 'pinglet' ),
				'message' => $message,
				'level'   => 'info',
			)
		);
	}
}
