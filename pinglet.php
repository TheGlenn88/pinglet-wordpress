<?php
/**
 * Plugin Name: Pinglet
 * Plugin URI: https://pinglet.dev
 * Description: Get a push notification on your phone when someone submits a form on your WordPress site or places a WooCommerce order. Powered by Pinglet, the webhook-to-push service.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Pinglet
 * Author URI: https://pinglet.dev
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pinglet
 *
 * @package Pinglet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PINGLET_VERSION', '1.0.0' );
define( 'PINGLET_PLUGIN_FILE', __FILE__ );
define( 'PINGLET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once PINGLET_PLUGIN_DIR . 'includes/class-pinglet-sender.php';
require_once PINGLET_PLUGIN_DIR . 'includes/class-pinglet-settings.php';
require_once PINGLET_PLUGIN_DIR . 'includes/integrations/class-pinglet-cf7.php';
require_once PINGLET_PLUGIN_DIR . 'includes/integrations/class-pinglet-wpforms.php';
require_once PINGLET_PLUGIN_DIR . 'includes/integrations/class-pinglet-gravityforms.php';
require_once PINGLET_PLUGIN_DIR . 'includes/integrations/class-pinglet-elementor.php';
require_once PINGLET_PLUGIN_DIR . 'includes/integrations/class-pinglet-woocommerce.php';

/**
 * Default plugin settings.
 *
 * @return array
 */
function pinglet_default_settings() {
	return array(
		'api_key'             => '',
		'namespace'           => '',
		'topic'               => 'wordpress',
		'enable_cf7'          => 1,
		'enable_wpforms'      => 1,
		'enable_gravityforms' => 1,
		'enable_elementor'    => 1,
		'enable_woocommerce'  => 1,
	);
}

/**
 * Fetch plugin settings merged with defaults.
 *
 * @return array
 */
function pinglet_get_settings() {
	$settings = get_option( Pinglet_Settings::OPTION, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	return wp_parse_args( $settings, pinglet_default_settings() );
}

/**
 * Whether the plugin is configured well enough to send anything.
 *
 * @return bool
 */
function pinglet_is_configured() {
	$settings = pinglet_get_settings();
	return ( '' !== $settings['api_key'] && '' !== $settings['namespace'] && '' !== $settings['topic'] );
}

/**
 * Public helper: send a push notification through Pinglet.
 *
 * @param array $args {
 *     Notification arguments.
 *
 *     @type string $message  Required. Notification body.
 *     @type string $title    Optional. Notification title.
 *     @type string $level    Optional. One of info, success, warning, error.
 *     @type string $priority Optional. One of silent, normal, urgent.
 *     @type array  $badges   Optional. Flat key => value map, at most 3 pairs.
 *     @type string $topic    Optional. Override the configured default topic.
 * }
 * @return true|WP_Error True when the request was dispatched, WP_Error otherwise.
 */
function pinglet_notify( $args ) {
	return Pinglet_Sender::send( $args );
}

/**
 * Public hook so other plugins and themes can send custom pushes:
 *
 *     do_action( 'pinglet_send', array( 'message' => 'Hello from my plugin' ) );
 */
add_action( 'pinglet_send', 'pinglet_notify', 10, 1 );

/**
 * Boot the admin screens and the integrations.
 *
 * Runs on plugins_loaded so that "is the source plugin active" checks
 * inside each integration see the final set of loaded plugins.
 *
 * @return void
 */
function pinglet_boot() {
	if ( is_admin() ) {
		Pinglet_Settings::init();
	}

	Pinglet_CF7::init();
	Pinglet_WPForms::init();
	Pinglet_GravityForms::init();
	Pinglet_Elementor::init();
	Pinglet_WooCommerce::init();
}
add_action( 'plugins_loaded', 'pinglet_boot' );

/**
 * Add a Settings link on the Plugins screen row.
 *
 * @param array $links Existing action links.
 * @return array
 */
function pinglet_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=pinglet' ) ),
		esc_html__( 'Settings', 'pinglet' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pinglet_plugin_action_links' );
