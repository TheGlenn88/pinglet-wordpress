<?php
/**
 * Admin settings screen: Settings -> Pinglet.
 *
 * @package Pinglet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pinglet settings page.
 */
class Pinglet_Settings {

	const OPTION = 'pinglet_settings';
	const PAGE   = 'pinglet';

	/**
	 * Hook everything up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_pinglet_test_notification', array( __CLASS__, 'handle_test_notification' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_test_result' ) );
	}

	/**
	 * Add the submenu under Settings.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_options_page(
			__( 'Pinglet', 'pinglet' ),
			__( 'Pinglet', 'pinglet' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the option, sections and fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => pinglet_default_settings(),
			)
		);

		add_settings_section(
			'pinglet_account',
			__( 'Pinglet account', 'pinglet' ),
			array( __CLASS__, 'render_account_section_intro' ),
			self::PAGE
		);

		add_settings_field(
			'pinglet_api_key',
			__( 'API key', 'pinglet' ),
			array( __CLASS__, 'render_api_key_field' ),
			self::PAGE,
			'pinglet_account'
		);

		add_settings_field(
			'pinglet_namespace',
			__( 'Namespace', 'pinglet' ),
			array( __CLASS__, 'render_namespace_field' ),
			self::PAGE,
			'pinglet_account'
		);

		add_settings_field(
			'pinglet_topic',
			__( 'Default topic', 'pinglet' ),
			array( __CLASS__, 'render_topic_field' ),
			self::PAGE,
			'pinglet_account'
		);

		add_settings_section(
			'pinglet_integrations',
			__( 'Integrations', 'pinglet' ),
			array( __CLASS__, 'render_integrations_section_intro' ),
			self::PAGE
		);

		foreach ( self::integrations() as $key => $integration ) {
			add_settings_field(
				'pinglet_' . $key,
				$integration['label'],
				array( __CLASS__, 'render_integration_field' ),
				self::PAGE,
				'pinglet_integrations',
				array(
					'key'       => $key,
					'available' => $integration['available'],
				)
			);
		}
	}

	/**
	 * The integrations shown on the settings screen.
	 *
	 * @return array
	 */
	public static function integrations() {
		return array(
			'enable_cf7'          => array(
				'label'     => __( 'Contact Form 7', 'pinglet' ),
				'available' => Pinglet_CF7::is_available(),
			),
			'enable_wpforms'      => array(
				'label'     => __( 'WPForms', 'pinglet' ),
				'available' => Pinglet_WPForms::is_available(),
			),
			'enable_gravityforms' => array(
				'label'     => __( 'Gravity Forms', 'pinglet' ),
				'available' => Pinglet_GravityForms::is_available(),
			),
			'enable_elementor'    => array(
				'label'     => __( 'Elementor Pro forms', 'pinglet' ),
				'available' => Pinglet_Elementor::is_available(),
			),
			'enable_woocommerce'  => array(
				'label'     => __( 'WooCommerce orders', 'pinglet' ),
				'available' => Pinglet_WooCommerce::is_available(),
			),
		);
	}

	/**
	 * Sanitize the whole settings array.
	 *
	 * @param mixed $input Raw input from the form.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$existing = pinglet_get_settings();
		$clean    = pinglet_default_settings();

		if ( ! is_array( $input ) ) {
			return $existing;
		}

		// API key: pinglet_ followed by 64 hex characters.
		$api_key = isset( $input['api_key'] ) ? trim( sanitize_text_field( $input['api_key'] ) ) : '';
		if ( '' === $api_key || preg_match( '/^pinglet_[0-9a-f]{64}$/', $api_key ) ) {
			$clean['api_key'] = $api_key;
		} else {
			$clean['api_key'] = $existing['api_key'];
			add_settings_error(
				self::OPTION,
				'pinglet_bad_api_key',
				__( 'The API key was not saved: it must look like pinglet_ followed by 64 hex characters.', 'pinglet' )
			);
		}

		// Namespace: same character set as topics, a little more length headroom.
		$namespace = isset( $input['namespace'] ) ? strtolower( trim( sanitize_text_field( $input['namespace'] ) ) ) : '';
		$namespace = substr( preg_replace( '/[^a-z0-9_-]/', '', $namespace ), 0, 64 );
		$clean['namespace'] = $namespace;

		// Default topic.
		$raw_topic = isset( $input['topic'] ) ? trim( sanitize_text_field( $input['topic'] ) ) : '';
		$topic     = pinglet_sanitize_topic( $raw_topic );
		if ( '' !== $raw_topic && '' === $topic ) {
			$clean['topic'] = $existing['topic'];
			add_settings_error(
				self::OPTION,
				'pinglet_bad_topic',
				__( 'The topic was not saved: use 1 to 32 characters (a-z, 0-9, hyphen or underscore), starting and ending with a letter or digit.', 'pinglet' )
			);
		} else {
			$clean['topic'] = $topic;
		}

		foreach ( array_keys( self::integrations() ) as $key ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		return $clean;
	}

	/**
	 * Intro text for the account section.
	 *
	 * @return void
	 */
	public static function render_account_section_intro() {
		printf(
			'<p>%s</p>',
			wp_kses(
				sprintf(
					/* translators: %s: link to pinglet.dev. */
					__( 'Enter the API key and namespace from your <a href="%s" target="_blank" rel="noopener noreferrer">Pinglet</a> account. Topics are created automatically the first time a notification is published to them.', 'pinglet' ),
					esc_url( 'https://pinglet.dev' )
				),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			)
		);
	}

	/**
	 * Intro text for the integrations section.
	 *
	 * @return void
	 */
	public static function render_integrations_section_intro() {
		printf(
			'<p>%s</p>',
			esc_html__( 'Choose which events send a push notification. An integration only runs when its plugin is installed and active.', 'pinglet' )
		);
	}

	/**
	 * API key field.
	 *
	 * @return void
	 */
	public static function render_api_key_field() {
		$settings = pinglet_get_settings();
		printf(
			'<input type="password" id="pinglet_api_key" name="%s[api_key]" value="%s" class="regular-text" autocomplete="off" placeholder="pinglet_..." />',
			esc_attr( self::OPTION ),
			esc_attr( $settings['api_key'] )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Your publish key, in the form pinglet_ followed by 64 hex characters.', 'pinglet' )
		);
	}

	/**
	 * Namespace field.
	 *
	 * @return void
	 */
	public static function render_namespace_field() {
		$settings = pinglet_get_settings();
		printf(
			'<input type="text" id="pinglet_namespace" name="%s[namespace]" value="%s" class="regular-text" />',
			esc_attr( self::OPTION ),
			esc_attr( $settings['namespace'] )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Notifications are published to https://pinglet.dev/namespace/topic.', 'pinglet' )
		);
	}

	/**
	 * Default topic field.
	 *
	 * @return void
	 */
	public static function render_topic_field() {
		$settings = pinglet_get_settings();
		printf(
			'<input type="text" id="pinglet_topic" name="%s[topic]" value="%s" class="regular-text" />',
			esc_attr( self::OPTION ),
			esc_attr( $settings['topic'] )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( '1 to 32 characters: a-z, 0-9, hyphen or underscore, starting and ending with a letter or digit.', 'pinglet' )
		);
	}

	/**
	 * One integration enable checkbox.
	 *
	 * @param array $args Field arguments: key, available.
	 * @return void
	 */
	public static function render_integration_field( $args ) {
		$settings = pinglet_get_settings();
		$key      = $args['key'];

		printf(
			'<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label>',
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			checked( ! empty( $settings[ $key ] ), true, false ),
			esc_html__( 'Send a push notification', 'pinglet' )
		);

		if ( empty( $args['available'] ) ) {
			printf(
				' <em>%s</em>',
				esc_html__( '(plugin not detected)', 'pinglet' )
			);
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pinglet', 'pinglet' ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Send a test notification', 'pinglet' ); ?></h2>
			<p><?php esc_html_e( 'Sends a test push to the configured namespace and default topic so you can confirm the key works.', 'pinglet' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="pinglet_test_notification" />
				<?php wp_nonce_field( 'pinglet_test_notification' ); ?>
				<?php submit_button( __( 'Send test notification', 'pinglet' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the "Send test notification" button.
	 *
	 * @return void
	 */
	public static function handle_test_notification() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'pinglet' ) );
		}
		check_admin_referer( 'pinglet_test_notification' );

		$result = Pinglet_Sender::send(
			array(
				/* translators: %s: site name. */
				'message' => sprintf( __( 'Test notification from %s. Your Pinglet key and topic work.', 'pinglet' ), get_bloginfo( 'name' ) ),
				'title'   => __( 'Pinglet test', 'pinglet' ),
				'level'   => 'info',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			$notice = array(
				'type'    => 'error',
				/* translators: %s: error message. */
				'message' => sprintf( __( 'Test notification failed: %s', 'pinglet' ), $result->get_error_message() ),
			);
		} else {
			$notice = array(
				'type'    => 'success',
				'message' => __( 'Test notification sent. Check your phone.', 'pinglet' ),
			);
		}

		set_transient( 'pinglet_test_result_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Show the stored test result as an admin notice on the settings page.
	 *
	 * @return void
	 */
	public static function maybe_show_test_result() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'settings_page_' . self::PAGE !== $screen->id ) {
			return;
		}

		$key    = 'pinglet_test_result_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( $key );

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( 'success' === $notice['type'] ? 'success' : 'error' ),
			esc_html( $notice['message'] )
		);
	}
}
