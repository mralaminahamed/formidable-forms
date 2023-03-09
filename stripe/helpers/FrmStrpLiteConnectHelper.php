<?php

class FrmStrpLiteConnectHelper {

	/**
	 * Track the latest error when calling stripe connect.
	 *
	 * @since 2.06 This property is public.
	 *
	 * @var string
	 */
	public static $latest_error_from_stripe_connect;

	public static function check_for_stripe_connect_webhooks() {
		if ( wp_doing_ajax() ) {
			self::check_for_stripe_connect_ajax_actions();
		} elseif ( self::user_landed_on_the_return_url() ) {
			self::redirect();
		} elseif ( self::user_landed_on_the_oauth_return_url() ) {
			self::redirect_oauth();
		}
	}

	private static function check_for_stripe_connect_ajax_actions() {
		$action = FrmAppHelper::get_param( 'action', '', 'post', 'sanitize_text_field' );
		$prefix = 'frm_stripe_connect_';

		if ( ! $action || 0 !== strpos( $action, $prefix ) ) {
			if ( 'frm_strp_connect_get_settings_button' === $action ) {
				FrmAppHelper::permission_check( 'frm_change_settings' );
				self::render_settings();
			}
			return;
		}

		FrmAppHelper::permission_check( 'frm_change_settings' );

		$action   = str_replace( $prefix, '', $action );
		$function = 'handle_' . $action;

		if ( ! is_callable( 'self::' . $function ) || ! check_admin_referer( 'frm_ajax', 'nonce' ) ) {
			wp_send_json_error();
		}

		self::$function();
	}

	private static function user_landed_on_the_return_url() {
		return isset( $_GET['frm_stripe_connect_return'] );
	}

	private static function user_landed_on_the_oauth_return_url() {
		return isset( $_GET['frm_stripe_connect_return_oauth'] );
	}

	/**
	 * Handle the request to initialize with Stripe Connect
	 */
	private static function handle_initialize() {
		$data = self::initialize();

		if ( is_string( $data ) ) {
			wp_send_json_error( $data );
		}

		if ( false === $data || empty( $data->password ) || empty( $data->account_id ) || empty( $data->connect_url ) ) {
			wp_send_json_error();
		}

		$response_data = array(
			'connect_url' => $data->connect_url,
		);
		wp_send_json_success( $response_data );
	}

	/**
	 * Initialize a Stripe Connect integration with the connect server
	 *
	 * @return object|string|false
	 */
	private static function initialize() {
		$mode = self::get_mode_value_from_post();

		if ( self::get_account_id( $mode ) ) {
			// do not allow for initialize if there is already a configured account id
			return 'Cannot initialize another account';
		}

		$additional_body = array(
			'password'              => self::generate_client_password( $mode ),
			'user_id'               => get_current_user_id(),
			'frm_strp_connect_mode' => $mode,
		);
		$data            = self::post_to_connect_server( 'initialize', $additional_body );

		if ( is_string( $data ) ) {
			return $data;
		}

		if ( ! empty( $data->password ) ) {
			update_option( self::get_server_side_token_option_name( $mode ), $data->password, 'no' );
		}

		if ( ! empty( $data->account_id ) ) {
			update_option( self::get_account_id_option_name( $mode ), $data->account_id, 'no' );
		}

		return $data;
	}

	/**
	 * Generate a new client password for authenticating with Connect Service and save it locally as an option.
	 *
	 * @param string $mode 'live' or 'test'.
	 * @return string the client password.
	 */
	private static function generate_client_password( $mode ) {
		$client_password = wp_generate_password();
		update_option( self::get_client_side_token_option_name( $mode ), $client_password, 'no' );
		return $client_password;
	}

	/**
	 * @return array|string
	 */
	private static function post_to_connect_server( $action, $additional_body = array() ) {
		$body    = array(
			'frm_strp_connect_action' => $action,
			'frm_strp_connect_mode'   => FrmStrpLiteAppHelper::active_mode(),
		);
		$body    = array_merge( $body, $additional_body );
		$url     = self::get_url_to_connect_server();
		$headers = self::build_headers_for_post();

		if ( ! $headers ) {
			return 'Unable to build headers for post. Is your pro license configured properly?';
		}

		$timeout = 45; // (seconds) default timeout is 5. we want a bit more time to work with.
		self::try_to_extend_server_timeout( $timeout );

		$args     = compact( 'body', 'headers', 'timeout' );
		$response = wp_remote_post( $url, $args );

		if ( ! self::validate_response( $response ) ) {
			return 'Response from server is invalid';
		}

		$body = self::pull_response_body( $response );
		if ( empty( $body->success ) ) {
			if ( ! empty( $body->data ) && is_string( $body->data ) ) {
				return $body->data;
			}
			return 'Response from server was not successful';
		}

		return isset( $body->data ) ? $body->data : array();
	}

	/**
	 * Try to make sure the server time limit exceeds the request time limit.
	 *
	 * @param int $timeout seconds.
	 */
	private static function try_to_extend_server_timeout( $timeout ) {
		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( $timeout + 10 );
		}
	}

	/**
	 * @param string $mode either 'auto', 'live', or 'test'.
	 * @return string either _test or _live.
	 */
	private static function get_active_mode_option_name_suffix( $mode = 'auto' ) {
		if ( 'auto' !== $mode ) {
			return '_' . $mode;
		}
		return '_' . FrmStrpLiteAppHelper::active_mode();
	}

	/**
	 * @param string $key 'account_id', 'client_password', 'server_password', 'details_submitted'.
	 * @param string $mode either 'auto', 'live', or 'test'.
	 * @return string
	 */
	private static function get_strp_connect_option_name( $key, $mode = 'auto' ) {
		return 'frm_strp_connect_' . $key . self::get_active_mode_option_name_suffix( $mode );
	}

	/**
	 * @param string $mode either 'auto', 'live', or 'test'.
	 * @return string
	 */
	private static function get_account_id_option_name( $mode = 'auto' ) {
		return self::get_strp_connect_option_name( 'account_id', $mode );
	}

	/**
	 * @param string $mode either 'auto', 'live', or 'test'.
	 * @return string
	 */
	private static function get_client_side_token_option_name( $mode = 'auto' ) {
		return self::get_strp_connect_option_name( 'client_password', $mode );
	}

	/**
	 * @param string $mode either 'auto', 'live', or 'test'.
	 * @return string
	 */
	private static function get_server_side_token_option_name( $mode = 'auto' ) {
		return self::get_strp_connect_option_name( 'server_password', $mode );
	}

	/**
	 * @param string $mode either 'auto', 'live', or 'test'.
	 * @return string
	 */
	private static function get_stripe_details_submitted_option_name( $mode = 'auto' ) {
		return self::get_strp_connect_option_name( 'details_submitted', $mode );
	}

	/**
	 * @return string
	 */
	private static function get_url_to_connect_server() {
		return 'https://api.strategy11.com/';
	}

	private static function handle_disconnect() {
		self::disconnect();
		self::reset_stripe_connect_integration();
		wp_send_json_success();
	}

	/**
	 * Delete every Stripe connect option, calling when disconnecting.
	 */
	public static function reset_stripe_connect_integration() {
		$mode = self::get_mode_value_from_post();
		delete_option( self::get_account_id_option_name( $mode ) );
		delete_option( self::get_server_side_token_option_name( $mode ) );
		delete_option( self::get_client_side_token_option_name( $mode ) );
		delete_option( self::get_stripe_details_submitted_option_name( $mode ) );
	}

	private static function disconnect() {
		$additional_body = array(
			'frm_strp_connect_mode' => self::get_mode_value_from_post(),
		);
		return self::post_with_authenticated_body( 'disconnect', $additional_body );
	}

	/**
	 * @return void
	 */
	private static function handle_reauth() {
		$additional_body = array(
			'frm_strp_connect_mode' => self::get_mode_value_from_post(),
		);
		$data            = self::post_with_authenticated_body( 'reauth', $additional_body );

		if ( false === $data ) {
			// check account status
			if ( self::check_server_for_connected_account_status() ) {
				wp_send_json_success();
			}
			wp_send_json_error();
		}

		$response_data = array(
			'connect_url' => $data->connect_url,
		);
		wp_send_json_success( $response_data );
	}

	/**
	 * @return array
	 */
	private static function get_standard_authenticated_body() {
		$mode = self::get_mode_value_from_post();
		return array(
			'account_id'      => get_option( self::get_account_id_option_name( $mode ) ),
			'server_password' => get_option( self::get_server_side_token_option_name( $mode ) ),
			'client_password' => get_option( self::get_client_side_token_option_name( $mode ) ),
		);
	}

	private static function redirect() {
		$connected = self::check_server_for_connected_account_status();
		header( 'Location: ' . self::get_url_for_stripe_settings( $connected ), true, 302 );
		exit;
	}

	private static function redirect_oauth() {
		$connected = self::check_server_for_oauth_account_id();
		header( 'Location: ' . self::get_url_for_stripe_settings( $connected ), true, 302 );
		exit;
	}

	/**
	 * @return bool
	 */
	private static function check_server_for_oauth_account_id() {
		$mode = FrmAppHelper::get_param( 'mode', '', 'get', 'sanitize_text_field' );
		if ( 'live' !== $mode ) {
			$mode = 'test';
		}

		if ( self::get_account_id( $mode ) ) {
			// do not allow for initialize if there is already a configured account id
			return false;
		}

		$body = array(
			'server_password'       => get_option( self::get_server_side_token_option_name( $mode ) ),
			'client_password'       => get_option( self::get_client_side_token_option_name( $mode ) ),
			'frm_strp_connect_mode' => $mode,
		);
		$data = self::post_to_connect_server( 'oauth_account_status', $body );

		if ( is_object( $data ) && ! empty( $data->account_id ) ) {
			update_option( self::get_account_id_option_name( $mode ), $data->account_id, 'no' );

			if ( ! empty( $data->details_submitted ) ) {
				self::set_stripe_details_as_submitted_and_clear_legacy_keys( $mode );
				if ( 'live' === $mode ) {
					self::dismiss_inbox_message_to_use_stripe_connect();
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * On a successful account status check, set details_submitted option and clear legacy key data
	 *
	 * @param string $mode 'live' or 'test'.
	 * @return void
	 */
	private static function set_stripe_details_as_submitted_and_clear_legacy_keys( $mode ) {
		update_option( self::get_stripe_details_submitted_option_name( $mode ), true, 'no' );

		$settings         = new FrmStrpLiteSettings();
		$settings         = $settings->settings;
		$updated_settings = false;

		if ( ! empty( $settings->{ $mode . '_publish' } ) ) {
			$settings->{ $mode . '_publish' } = '';
			$updated_settings                 = true;
		}

		if ( ! empty( $settings->{ $mode . '_secret' } ) ) {
			$settings->{ $mode . '_secret' } = '';
			$updated_settings                = true;
		}

		if ( $updated_settings ) {
			update_option( 'frm_strp_options', $settings, 'no' );
		}
	}

	/**
	 * @return void
	 */
	private static function dismiss_inbox_message_to_use_stripe_connect() {
		$key     = 'use_strp_connect';
		$message = new FrmInbox();
		$message->dismiss( $key );
	}

	/**
	 * @return void
	 */
	private static function handle_oauth() {
		$response_data = array(
			'redirect_url' => self::get_oauth_redirect_url(),
		);
		wp_send_json_success( $response_data );
	}

	/**
	 * @return string|false
	 */
	private static function get_oauth_redirect_url() {
		$mode = self::get_mode_value_from_post();

		if ( self::get_account_id( $mode ) ) {
			// do not allow for initialize if there is already a configured account id
			return false;
		}

		$additional_body = array(
			'password'              => self::generate_client_password( $mode ),
			'user_id'               => get_current_user_id(),
			'frm_strp_connect_mode' => $mode,
		);

		$data = self::post_to_connect_server( 'oauth_request', $additional_body );

		if ( is_string( $data ) ) {
			return false;
		}

		if ( ! empty( $data->password ) ) {
			update_option( self::get_server_side_token_option_name( $mode ), $data->password, 'no' );
		}

		if ( ! is_object( $data ) || empty( $data->redirect_url ) ) {
			return false;
		}

		return $data->redirect_url;
	}

	/**
	 * @return bool true if our account is onboarded
	 */
	public static function check_server_for_connected_account_status() {
		$mode = FrmAppHelper::get_param( 'mode', '', 'get', 'sanitize_text_field' );
		if ( 'live' !== $mode ) {
			$mode = 'test';
		}
		$additional_body = array(
			'frm_strp_connect_mode' => $mode,
		);
		$data            = self::post_with_authenticated_body( 'account_status', $additional_body );
		$success         = false !== $data && ! empty( $data->details_submitted );
		if ( $success ) {
			self::set_stripe_details_as_submitted_and_clear_legacy_keys( $mode );
		}
		return $success;
	}

	/**
	 * @param string $mode
	 * @return bool
	 */
	public static function stripe_connect_is_setup( $mode = 'auto' ) {
		return get_option( self::get_stripe_details_submitted_option_name( $mode ) );
	}

	/**
	 * @param mixed $response
	 * @return bool
	 */
	private static function validate_response( $response ) {
		return ! is_wp_error( $response ) && is_array( $response ) && isset( $response['http_response'] );
	}

	private static function pull_response_body( $response ) {
		$http_response   = $response['http_response'];
		$response_object = $http_response->get_response_object();
		return json_decode( $response_object->body );
	}

	/**
	 * @return array|false
	 */
	private static function build_headers_for_post() {
		$pro_license = is_callable( 'FrmAddonsController::get_pro_license' ) ? FrmAddonsController::get_pro_license() : false;

		if ( ! $pro_license ) {
			return false;
		}

		$site_url = home_url();

		if ( defined( 'ICL_SITEPRESS_VERSION' ) && ! ICL_PLUGIN_INACTIVE && class_exists( 'SitePress' ) ) {
			global $wpml_url_converter;
			$site_url = $wpml_url_converter->get_abs_home();
		}

		$site_url = preg_replace( '#^https?://#', '', $site_url ); // remove protocol from url (our url cannot include the colon).
		$site_url = preg_replace( '/:[0-9]+/', '', $site_url );     // remove port from url (mostly helpful in development)

		// wpml might add a language to the url. don't send that to the server.
		$split_on_language = explode( '/?lang=', $site_url );
		if ( 2 === count( $split_on_language ) ) {
			$site_url = $split_on_language[0];
		}

		return array(
			'Authorization' => 'Basic ' . base64_encode( $site_url . ':' . $pro_license ),
		);
	}

	/**
	 * @param bool $connected
	 * @return string
	 */
	private static function get_url_for_stripe_settings( $connected ) {
		return admin_url( 'admin.php?page=formidable-settings&t=stripe_settings&connected=' . intval( $connected ) );
	}

	public static function render_stripe_connect_settings_container() {
		self::register_settings_scripts();
		?>
			<tr>
				<td>
					<?php esc_html_e( 'Connection Status', 'formidable' ); ?>
				</td>
				<td>
					<div id="frm_strp_settings_container"></div>
				</td>
			</tr>
		<?php
	}

	private static function render_settings() {
		$modes = array( 'test', 'live' );
		$html  = '';
		foreach ( $modes as $mode ) {
			$account_id = self::get_account_id( $mode );
			$connected  = self::stripe_connect_is_setup( $mode );
			$test       = 'test' === $mode;
			$title      = $test ? __( 'TEST', 'formidable' ) : __( 'LIVE', 'formidable' );

			ob_start();
			require FrmStrpLiteAppHelper::plugin_path() . '/views/settings/connect.php';
			$html .= ob_get_contents();
			ob_end_clean();
		}

		$response_data = array(
			'html' => $html,
		);
		wp_send_json_success( $response_data );
	}

	/**
	 * @todo I can probably remove this.
	 * @return void
	 */
	public static function stripe_icon() {
		?>
		<svg height="16" aria-hidden="true" style="vertical-align:text-bottom" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path fill="currentColor" d="M155.3 154.6c0-22.3 18.6-30.9 48.4-30.9a320 320 0 01141.9 36.7V26.1A376.2 376.2 0 00203.8 0C88.1 0 11 60.4 11 161.4c0 157.9 216.8 132.3 216.8 200.4 0 26.4-22.9 34.9-54.7 34.9-47.2 0-108.2-19.5-156.1-45.5v128.5a396 396 0 00156 32.4c118.6 0 200.3-51 200.3-153.6 0-170.2-218-139.7-218-203.9z"/></svg>
		<?php
	}

	/**
	 * Check $_POST for live or test mode value as it can be updated in real time from Stripe Settings and can be configured before the update is saved.
	 *
	 * @return string 'test' or 'live'
	 */
	private static function get_mode_value_from_post() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST ) || ! array_key_exists( 'testMode', $_POST ) ) {
			return FrmStrpLiteAppHelper::active_mode();
		}
		$test_mode = FrmAppHelper::get_param( 'testMode', '', 'post', 'absint' );
		return $test_mode ? 'test' : 'live';
	}

	private static function register_settings_scripts() {
		$version = FrmAppHelper::plugin_version();
		wp_register_script( 'formidable_stripe_settings', FrmStrpLiteAppHelper::plugin_url() . '/js/connect_settings.js', array(), FrmAppHelper::plugin_version(), true );
		wp_enqueue_script( 'formidable_stripe_settings' );
	}

	/**
	 * @param string $mode
	 * @return string|bool
	 */
	public static function get_account_id( $mode = 'auto' ) {
		return get_option( self::get_account_id_option_name( $mode ) );
	}

	/**
	 * @param array $options
	 * @return string|false
	 */
	public static function get_customer_id( $options ) {
		$data    = self::post_with_authenticated_body( 'get_customer', compact( 'options' ) );
		$success = false !== $data;
		if ( ! $success ) {
			return ! empty( self::$latest_error_from_stripe_connect ) ? self::$latest_error_from_stripe_connect : false;
		}
		if ( empty( $data->customer_id ) ) {
			return false;
		}
		return $data->customer_id;
	}

	/**
	 * @param string $customer_id
	 * @return bool
	 */
	public static function validate_customer( $customer_id ) {
		$data = self::post_with_authenticated_body( 'validate_customer', compact( 'customer_id' ) );
		return is_object( $data ) && ! empty( $data->valid );
	}

	/**
	 * @return object|false
	 */
	private static function post_with_authenticated_body( $action, $additional_body = array() ) {
		$body     = array_merge( self::get_standard_authenticated_body(), $additional_body );
		$response = self::post_to_connect_server( $action, $body );
		if ( is_object( $response ) ) {
			return $response;
		}
		if ( is_array( $response ) ) {
			// reformat empty arrays as empty objects
			// if the response is an array, it's because it's empty. Everything with data is already an object.
			return new stdClass();
		}
		if ( is_string( $response ) ) {
			self::$latest_error_from_stripe_connect = $response;
			FrmTransLiteLog::log_message( 'Unable to complete call to Stripe Connect service: ' . $response );
		} else {
			self::$latest_error_from_stripe_connect = '';
		}
		return false;
	}

	/**
	 * @param array $new_charge
	 * @return mixed
	 */
	public static function create_intent( $new_charge ) {
		$data    = self::post_with_authenticated_body( 'create_intent', compact( 'new_charge' ) );
		$success = false !== $data;
		if ( ! $success ) {
			return false;
		}
		return $data;
	}

	/**
	 * @param string $payment_id
	 * @return bool
	 */
	public static function refund_payment( $payment_id ) {
		$data     = self::post_with_authenticated_body( 'refund_payment', compact( 'payment_id' ) );
		$refunded = is_object( $data );
		return $refunded;
	}

	/**
	 * @param array $new_charge
	 * @return mixed
	 */
	public static function create_subscription( $new_charge ) {
		return self::post_with_authenticated_body( 'create_subscription', compact( 'new_charge' ) );
	}

	/**
	 * @param string       $sub_id
	 * @param string|false $customer_id if specified, this will enforce a customer id match (bypassed for users with administrator permission).
	 * @return bool
	 */
	public static function cancel_subscription( $sub_id, $customer_id = false ) {
		$data     = self::post_with_authenticated_body( 'cancel_subscription', compact( 'sub_id', 'customer_id' ) );
		$canceled = false !== $data;
		return $canceled;
	}

	/**
	 * @param string $payment_id
	 * @return mixed
	 */
	public static function get_intent( $payment_id ) {
		return self::post_with_authenticated_body( 'get_intent', compact( 'payment_id' ) );
	}

	/**
	 * @return object|false
	 */
	public static function get_customer_subscriptions() {
		$user_id     = get_current_user_id();
		$meta_name   = FrmStrpLiteAppHelper::get_customer_id_meta_name();
		$customer_id = get_user_meta( $user_id, $meta_name, true );
		$data        = self::post_with_authenticated_body( 'get_customer_subscriptions', compact( 'customer_id' ) );

		if ( false === $data ) {
			return false;
		}

		return $data->subscriptions;
	}

	/**
	 * @param string $event_id
	 * @return object|false
	 */
	public static function get_event( $event_id ) {
		$event = wp_cache_get( $event_id, 'frm_strp' );
		if ( is_object( $event ) ) {
			return $event;
		}

		$event = self::post_with_authenticated_body( 'get_event', compact( 'event_id' ) );
		if ( false === $event || empty( $event->event ) ) {
			return false;
		}

		wp_cache_set( $event_id, $event->event, 'frm_strp' );
		return $event->event;
	}

	/**
	 * @param string $event_id
	 * @return mixed
	 */
	public static function process_event( $event_id ) {
		return self::post_with_authenticated_body( 'process_event', compact( 'event_id' ) );
	}

	/**
	 * @param array $plan
	 * @return string|false
	 */
	public static function maybe_create_plan( $plan ) {
		$data = self::post_with_authenticated_body( 'maybe_create_plan', compact( 'plan' ) );
		if ( false === $data || empty( $data->plan_id ) ) {
			return false;
		}
		return $data->plan_id;
	}

	/**
	 * @param array $plan
	 * @return mixed
	 */
	public static function create_plan( $plan ) {
		return self::post_with_authenticated_body( 'create_plan', compact( 'plan' ) );
	}

	/**
	 * @param string $intent_id
	 * @param array  $data
	 * @return bool
	 */
	public static function update_intent( $intent_id, $data ) {
		$data    = self::post_with_authenticated_body( 'update_intent', compact( 'intent_id', 'data' ) );
		$success = false !== $data;
		return $success;
	}

	/**
	 * @return array
	 */
	public static function get_unprocessed_event_ids() {
		$data = self::post_with_authenticated_body( 'get_unprocessed_event_ids' );
		if ( false === $data || empty( $data->event_ids ) ) {
			return array();
		}
		return $data->event_ids;
	}

	/**
	 * Create a setup intent for a Stripe link recurring payment.
	 * This is called when a form is loaded.
	 *
	 * @since 3.0
	 *
	 * @param string $customer_id
	 * @return object|string|false
	 */
	public static function create_setup_intent( $customer_id ) {
		return self::post_with_authenticated_body( 'create_setup_intent', compact( 'customer_id' ) );
	}

	/**
	 * Get a setup intent (used for Stripe link recurring payments).
	 *
	 * @since 3.0
	 *
	 * @param string $setup_id
	 * @return object|string|false
	 */
	public static function get_setup_intent( $setup_id ) {
		return self::post_with_authenticated_body( 'get_setup_intent', compact( 'setup_id' ) );
	}
}