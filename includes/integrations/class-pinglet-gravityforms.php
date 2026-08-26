<?php
/**
 * Gravity Forms integration.
 *
 * @package Pinglet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a push when a Gravity Forms entry is created.
 */
class Pinglet_GravityForms {

	/**
	 * Whether Gravity Forms is active.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( 'GFForms' );
	}

	/**
	 * Whether the integration is switched on in the Pinglet settings.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = pinglet_get_settings();
		return ! empty( $settings['enable_gravityforms'] );
	}

	/**
	 * Hook into Gravity Forms when available and enabled.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_available() || ! self::is_enabled() ) {
			return;
		}
		add_action( 'gform_after_submission', array( __CLASS__, 'handle_submission' ), 10, 2 );
	}

	/**
	 * Build and send the notification for an entry.
	 *
	 * @param array $entry The submitted entry.
	 * @param array $form  The form meta.
	 * @return void
	 */
	public static function handle_submission( $entry, $form ) {
		$pairs = array();

		if ( is_array( $form ) && ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				if ( ! is_object( $field ) ) {
					continue;
				}

				$label = isset( $field->label ) ? (string) $field->label : '';
				if ( '' === $label ) {
					continue;
				}

				if ( is_callable( array( $field, 'get_value_export' ) ) ) {
					$value = $field->get_value_export( $entry );
				} else {
					$value = isset( $entry[ (string) $field->id ] ) ? $entry[ (string) $field->id ] : '';
				}

				if ( ! is_scalar( $value ) ) {
					continue;
				}

				$pairs[ $label ] = (string) $value;
			}
		}

		$form_title = ( is_array( $form ) && ! empty( $form['title'] ) ) ? sanitize_text_field( $form['title'] ) : '';
		$summary    = pinglet_summarize_fields( $pairs );

		$message = $form_title;
		if ( '' !== $summary ) {
			$message = '' !== $message ? $message . "\n" . $summary : $summary;
		}
		if ( '' === $message ) {
			$message = __( 'A form was submitted.', 'pinglet' );
		}

		pinglet_notify(
			array(
				'title'   => __( 'New Gravity Forms submission', 'pinglet' ),
				'message' => $message,
				'level'   => 'info',
			)
		);
	}
}
