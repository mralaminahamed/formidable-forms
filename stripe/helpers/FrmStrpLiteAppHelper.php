<?php

class FrmStrpLiteAppHelper {

	public static function plugin_path() {
		return FrmAppHelper::plugin_path() . '/stripe/';
	}

	public static function plugin_folder() {
		return basename( self::plugin_path() );
	}

	public static function plugin_url() {
		return FrmAppHelper::plugin_url() . '/stripe/';
	}

	public static function is_debug() {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * @param string $function
	 * @param array  ...$params
	 * @return mixed
	 */
	public static function call_stripe_helper_class( $function, ...$params ) {
		// TODO: Maybe call the Stripe add on here.
		if ( self::should_use_stripe_connect() ) {
			if ( is_callable( "FrmStrpLiteConnectApiAdapter::$function" ) ) {
				return FrmStrpLiteConnectApiAdapter::$function( ...$params );
			}
		}
		return false;
	}

	/**
	 * @return bool true if we're using connect (versus the legacy integration).
	 */
	public static function should_use_stripe_connect() {
		if ( ! class_exists( 'FrmStrpLiteConnectApiAdapter' ) ) {
			require dirname( __FILE__ ) . '/FrmStrpLiteConnectApiAdapter.php';
		}
		return FrmStrpLiteConnectApiAdapter::initialize_api();
	}

	/**
	 * @return bool true if either connect or the legacy integration is set up.
	 */
	public static function stripe_is_configured() {
		return self::call_stripe_helper_class( 'initialize_api' );
	}

	/**
	 * If test mode is running, save the id somewhere else
	 *
	 * @return string
	 */
	public static function get_customer_id_meta_name() {
		$meta_name = '_frmstrp_customer_id';
		if ( 'test' === self::active_mode() ) {
			$meta_name .= '_test';
		}
		return $meta_name;
	}

	public static function active_mode() {
		$settings = new FrmStrpLiteSettings();
		return $settings->settings->test_mode ? 'test' : 'live';
	}
}