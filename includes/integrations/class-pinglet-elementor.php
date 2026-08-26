<?php
/**
 * Elementor Pro forms integration.
 *
 * @package Pinglet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a push when an Elementor Pro form records a submission.
 */
class Pinglet_Elementor {

	/**
	 * Whether Elementor Pro is active.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( '\\ElementorPro\\Plugin' );
	}

	/**
	 * Whether the integration is switched on in the Pinglet settings.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = pinglet_get_settings();
		return ! empty( $settings['enable_elementor'] );
	}

	/**
	 * Hook into Elementor Pro when available and enabled.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_available() || ! self::is_enabled() ) {
			return;
		}
		add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'handle_submission' ), 10, 2 );
	}

	/**
	 * Build and send the notification for a form record.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record  The submission record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $handler The AJAX handler.
	 * @return void
	 */
	public static function handle_submission( $record, $handler ) {
		if ( ! is_object( $record ) || ! is_callable( array( $record, 'get' ) ) ) {
			return;
		}

		$fields = $record->get( 'fields' );
		$pairs  = array();

		if ( is_array( $fields ) ) {
			foreach ( $fields as $id => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$label = ! empty( $field['title'] ) ? (string) $field['title'] : (string) $id;
				$value = isset( $field['value'] ) ? $field['value'] : '';
				$pairs[ $label ] = $value;
			}
		}

		$form_name = '';
		if ( is_callable( array( $record, 'get_form_settings' ) ) ) {
			$form_name = sanitize_text_field( (string) $record->get_form_settings( 'form_name' ) );
		}

		$summary = pinglet_summarize_fields( $pairs );

		$message = $form_name;
		if ( '' !== $summary ) {
			$message = '' !== $message ? $message . "\n" . $summary : $summary;
		}
		if ( '' === $message ) {
			$message = __( 'A form was submitted.', 'pinglet' );
		}

		pinglet_notify(
			array(
				'title'   => __( 'New Elementor form submission', 'pinglet' ),
				'message' => $message,
				'level'   => 'info',
				'data'    => pinglet_extract_metadata( $pairs ),
			)
		);
	}
}
