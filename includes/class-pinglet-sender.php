<?php
/**
 * Builds and dispatches Pinglet publish requests.
 *
 * @package Pinglet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pinglet sender.
 */
class Pinglet_Sender {

	const API_BASE = 'https://pinglet.dev';

	const MAX_MESSAGE_LENGTH     = 1500;
	const MAX_TITLE_LENGTH       = 120;
	const MAX_BADGES             = 3;
	const MAX_BADGE_KEY_LENGTH   = 24;
	const MAX_BADGE_VALUE_LENGTH = 32;

	/**
	 * Send a push notification.
	 *
	 * By default the HTTP request is non blocking so a visitor submitting a
	 * form never waits on the push. Pass $blocking = true (used by the test
	 * button) to wait for the response and report real success or failure.
	 *
	 * @param array $args     See pinglet_notify() for the accepted keys.
	 * @param bool  $blocking Whether to wait for the HTTP response.
	 * @return true|WP_Error
	 */
	public static function send( $args, $blocking = false ) {
		if ( ! is_array( $args ) ) {
			return new WP_Error( 'pinglet_invalid_args', __( 'Notification arguments must be an array.', 'pinglet' ) );
		}

		$settings = pinglet_get_settings();

		if ( '' === $settings['api_key'] ) {
			return new WP_Error( 'pinglet_no_api_key', __( 'No Pinglet API key is configured.', 'pinglet' ) );
		}
		if ( '' === $settings['namespace'] ) {
			return new WP_Error( 'pinglet_no_namespace', __( 'No Pinglet namespace is configured.', 'pinglet' ) );
		}

		$topic = isset( $args['topic'] ) ? pinglet_sanitize_topic( $args['topic'] ) : '';
		if ( '' === $topic ) {
			$topic = $settings['topic'];
		}
		if ( '' === $topic ) {
			return new WP_Error( 'pinglet_no_topic', __( 'No Pinglet topic is configured.', 'pinglet' ) );
		}

		$message = isset( $args['message'] ) ? trim( (string) $args['message'] ) : '';
		if ( '' === $message ) {
			return new WP_Error( 'pinglet_no_message', __( 'A notification message is required.', 'pinglet' ) );
		}

		$payload = array(
			'message' => pinglet_truncate( $message, self::MAX_MESSAGE_LENGTH ),
		);

		if ( ! empty( $args['title'] ) ) {
			$payload['title'] = pinglet_truncate( trim( (string) $args['title'] ), self::MAX_TITLE_LENGTH );
		}

		if ( ! empty( $args['level'] ) && in_array( $args['level'], array( 'info', 'success', 'warning', 'error' ), true ) ) {
			$payload['level'] = $args['level'];
		}

		if ( ! empty( $args['priority'] ) && in_array( $args['priority'], array( 'silent', 'normal', 'urgent' ), true ) ) {
			$payload['priority'] = $args['priority'];
		}

		if ( ! empty( $args['badges'] ) && is_array( $args['badges'] ) ) {
			$badges = self::sanitize_badges( $args['badges'] );
			if ( ! empty( $badges ) ) {
				$payload['badges'] = $badges;
			}
		}

		$body = wp_json_encode( $payload );
		if ( false === $body ) {
			return new WP_Error( 'pinglet_encode_failed', __( 'Could not encode the notification payload.', 'pinglet' ) );
		}

		/**
		 * Filters the Pinglet API base URL. Intended for tests and staging.
		 *
		 * @param string $base The base URL, no trailing slash.
		 */
		$base = apply_filters( 'pinglet_api_base', self::API_BASE );
		$url  = rtrim( $base, '/' ) . '/' . rawurlencode( $settings['namespace'] ) . '/' . rawurlencode( $topic );

		$response = wp_remote_post(
			$url,
			array(
				'blocking'   => (bool) $blocking,
				'timeout'    => 5,
				'user-agent' => 'pinglet-wordpress/' . PINGLET_VERSION,
				'headers'    => array(
					'Authorization' => 'Bearer ' . $settings['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'       => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! $blocking ) {
			// The request was handed off; there is no response to inspect.
			return true;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		switch ( $code ) {
			case 400:
				$error = __( 'Pinglet rejected the payload (HTTP 400). Check the topic and field lengths.', 'pinglet' );
				break;
			case 401:
				$error = __( 'Pinglet rejected the API key (HTTP 401). Check the key and namespace in the settings.', 'pinglet' );
				break;
			case 429:
				$error = __( 'Pinglet rate limited the request (HTTP 429). Try again in a moment.', 'pinglet' );
				break;
			default:
				/* translators: %d: HTTP status code. */
				$error = sprintf( __( 'Pinglet returned an unexpected HTTP status: %d.', 'pinglet' ), $code );
				break;
		}

		return new WP_Error( 'pinglet_http_' . $code, $error );
	}

	/**
	 * Clamp badges to the API limits: at most 3 pairs, keys at most 24
	 * characters, string values at most 32 characters.
	 *
	 * @param array $badges Raw badge pairs.
	 * @return array
	 */
	public static function sanitize_badges( $badges ) {
		$clean = array();

		foreach ( $badges as $key => $value ) {
			if ( count( $clean ) >= self::MAX_BADGES ) {
				break;
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			$key = pinglet_truncate( sanitize_text_field( (string) $key ), self::MAX_BADGE_KEY_LENGTH );
			if ( '' === $key || pinglet_is_sensitive_key( $key ) ) {
				continue;
			}

			$value = pinglet_truncate( sanitize_text_field( (string) $value ), self::MAX_BADGE_VALUE_LENGTH );
			if ( '' === $value ) {
				continue;
			}

			$clean[ $key ] = $value;
		}

		return $clean;
	}
}

/**
 * Multibyte-safe truncation.
 *
 * @param string $text   Input text.
 * @param int    $length Maximum length in characters.
 * @return string
 */
function pinglet_truncate( $text, $length ) {
	$text = (string) $text;
	if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
		if ( mb_strlen( $text, 'UTF-8' ) <= $length ) {
			return $text;
		}
		return mb_substr( $text, 0, $length, 'UTF-8' );
	}
	if ( strlen( $text ) <= $length ) {
		return $text;
	}
	return substr( $text, 0, $length );
}

/**
 * Normalize a topic to the Pinglet format: 1 to 32 characters of a-z, 0-9,
 * hyphen or underscore, starting and ending with a letter or digit.
 *
 * @param string $topic Raw topic.
 * @return string Sanitized topic, or an empty string when nothing valid remains.
 */
function pinglet_sanitize_topic( $topic ) {
	$topic = strtolower( trim( (string) $topic ) );
	$topic = preg_replace( '/[^a-z0-9_-]/', '', $topic );
	$topic = trim( $topic, '-_' );
	$topic = substr( $topic, 0, 32 );
	$topic = trim( $topic, '-_' );

	if ( ! preg_match( '/^[a-z0-9]([a-z0-9_-]{0,30}[a-z0-9])?$/', $topic ) ) {
		return '';
	}

	return $topic;
}

/**
 * Whether a field key looks like it holds a secret or payment detail.
 *
 * Deliberately aggressive: it is better to drop a harmless field than to
 * push a password or card number to a phone.
 *
 * @param string $key Field key or label.
 * @return bool
 */
function pinglet_is_sensitive_key( $key ) {
	$pattern = '/(pass(word|wd|phrase|code)?|pwd|secret|token|api[_-]?key|auth|credential|card|cc[_-]?(num|number)?$|cvv|cvc|cvn|pin$|ssn|social[_-]?security|iban|swift|routing|account[_-]?number|otp|2fa|captcha)/i';
	return (bool) preg_match( $pattern, (string) $key );
}

/**
 * Build a compact "Label: value" summary of submitted form fields.
 *
 * Skips internal keys, sensitive keys and long values, flattens array
 * values, and caps the number of fields included.
 *
 * @param array $fields     Flat label => value map.
 * @param int   $max_fields Maximum number of fields to include.
 * @param int   $max_value  Skip values longer than this many characters.
 * @return string One field per line, possibly an empty string.
 */
function pinglet_summarize_fields( $fields, $max_fields = 8, $max_value = 200 ) {
	$lines = array();

	foreach ( $fields as $key => $value ) {
		if ( count( $lines ) >= $max_fields ) {
			break;
		}

		$key = (string) $key;
		if ( '' === $key || '_' === $key[0] || pinglet_is_sensitive_key( $key ) ) {
			continue;
		}

		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'sanitize_text_field', array_filter( $value, 'is_scalar' ) ) );
		} elseif ( is_scalar( $value ) ) {
			$value = sanitize_text_field( (string) $value );
		} else {
			continue;
		}

		$value = trim( $value );
		if ( '' === $value ) {
			continue;
		}
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) > $max_value : strlen( $value ) > $max_value ) {
			continue;
		}

		$lines[] = sanitize_text_field( $key ) . ': ' . $value;
	}

	return implode( "\n", $lines );
}
