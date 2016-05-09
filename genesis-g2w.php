<?php
/*
Plugin Name: Go2Webinar for Genesis
*/


class Genesis_G2W {

	/**
	 * Plugin version.
	 */
	public $plugin_version = '0.9.0';

	/**
	 * Plugin directory. Assigned in __construct().
	 */
	public $plugin_dir;

	/**
	 * Genesis Go2Webinar Admin object.
	 *
	 * @since 0.9.0
	 */
	public $admin;

	/**
	 * The GoToWebinar API key.
	 *
	 * @since 0.9.9
	 */
	public $api_key;

	/**
	 * The GoToWebinar access token.
	 *
	 * @since 0.9.9
	 */
	public $access_token;

	/**
	 * The GoToWebinar organizer key.
	 *
	 * @since 0.9.9
	 */
	public $organizer_key;

	/**
	 * Constructor. Runs when object is instantiated.
	 *
	 * @since 0.9.0
	 */
	public function __construct() {

		$this->plugin_dir = plugin_dir_path( __FILE__ );

		//register_activation_hook( __FILE__, array( $this, 'activation' ) );
		add_action( 'admin_init', array( $this, 'dependency_check' ) );

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		add_action( 'genesis_init', array( $this, 'includes' ) );
		add_action( 'genesis_init', array( $this, 'instantiate' ) );
		add_action( 'genesis_init', array( $this, 'add_shortcodes' ) );

	}

	/**
	 * Deactivate if Genesis child theme not detected.
	 *
	 * @since 0.9.0
	 */
	public function dependency_check() {

		if ( ! function_exists( 'genesis' ) ) {

			deactivate_plugins( plugin_basename( __FILE__ ) );

			if ( is_admin() ) {
				wp_die( __( 'You must be using a Genesis child theme to activate this plugin', 'genesis-g2w' ) );
			}

		}

	}


	/**
	 * Load the plugin textdomain, for translation.
	 *
	 * @since 0.9.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'genesis-g2w', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Include all the main class files.
	 *
	 * @since 0.9.0
	 */
	public function includes() {}

	/**
	 * Create the objects, assign to variables as part of the main class object.
	 *
	 * @since 0.9.0
	 */
	public function instantiate() {

		//* Create a settings page with the Genesis API.
		add_action( 'genesis_admin_menu', array( $this, 'admin_settings_page' ), 999 );

	}

	public function admin_settings_page() {

		require_once( $this->plugin_dir . 'includes/class-genesis-g2w-admin.php' );
		$this->admin = new Genesis_G2W_Admin;

	}

	/**
	 * Register the shortcode(s).
	 *
	 * @since 0.9.0
	 */
	public function add_shortcodes() {

		add_shortcode( 'webinar', array( $this, 'webinar_shortcode' ) );

	}

	/**
	 * Main [webinar] shortcode.
	 *
	 * @since 0.9.0
	 */
	public function webinar_shortcode( $atts ) {

		if ( ! is_user_logged_in() ) {
			return __( 'You must be logged in to register for the webinar.', 'genesis-g2w' );
		}

		$atts = shortcode_atts( array(
			'key'    => '',
			'button' => __( 'Register now with one click', 'genesis-g2w' ),
		), $atts );

		$user = wp_get_current_user();

		if ( isset( $_POST['submit'] ) ) {

			if ( $this->process_form( $atts['key'], $user ) ) {
				return __( 'You have successfully registered for this webinar.', 'genesis-g2w' );
			}

		}

		return $this->registration_form( $atts['button'] );

	}

	/**
	 * Show the registration form/button.
	 *
	 * @since 0.9.0
	 */
	public function registration_form( $button_text ) {

		$form  = '<form method="post">';
		$form .= sprintf( '<input type="submit" name="submit" value="%s" />', esc_attr( $button_text ) );
		$form .= '</form>';

		return $form;

	}

	/**
	 * Process the registration form.
	 *
	 * @since 0.9.0
	 */
	public function process_form( $webinar_key, $user ) {

		if ( ! $user['first_name'] || ! $user['last_name'] || ! $user['email'] ) {
			return false;
		}

		return $this->g2w_api_request( array(
			'endpoint' => sprintf( '/webinars/%s/registrants', $webinar_key ),
			'data'     => $user,
		) );

	}

	/**
	 * Check to see if user is already registered for the webinar.
	 *
	 * @since 0.9.0
	 */
	public function user_registered( $webinar_key, $user ) {

		$registrants = $this->g2w_api_request( array(
			'endpoint'    => sprintf( '/webinars/%s/registrants', $webinar_key ),
			'method'      => 'get',
		) );

		$registrants = json_decode( $registrants );

		if ( ! $registrants ) {
			return false;
		}

		foreach ( $registrants as $registrant ) {

			if ( $user->user_email == $registrant->email ) {
				return true;
			}

		}

		return false;

	}

	/**
	 * Request to GoToWebinar API.
	 *
	 * 0.9.0
	 */
	public function g2w_api_request( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'endpoint'    => '/',
			'method'      => 'post',
			'data'        => array(),
		) );

		$rest_url = sprintf( 'https://api.citrixonline.com/G2W/rest/organizers/%s', genesis_get_option( 'organizer_key' ) );

		$url = $rest_url . $args['endpoint'];

		$request_args = array(
			'body'    => json_encode( $args['data'] ),
			'headers' => array(
				'Accept'        => 'application/json',
				'Content-type'  => 'application/json',
				'Authorization' => 'OAuth oauth_token: ' . esc_html( genesis_get_option( 'access_token', 'genesis-g2w' ) ),
			),
		);

		//* Execute the POST/GET
		$response = 'get' == $args['method'] ? wp_remote_get( $url, $request_args ) : wp_remote_post( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return $response;

	}

}

function Genesis_G2W() {

	static $_genesis_g2w = null;

	if ( null == $_genesis_g2w ) {
		$_genesis_g2w = new Genesis_G2W;
	}

	return $_genesis_g2w;

}

Genesis_G2W();