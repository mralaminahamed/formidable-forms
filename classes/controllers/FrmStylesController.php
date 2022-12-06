<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmStylesController {
	public static $post_type = 'frm_styles';
	public static $screen = 'formidable_page_formidable-styles';

	public static function load_pro_hooks() {
		if ( FrmAppHelper::pro_is_installed() ) {
			FrmProStylesController::load_pro_hooks();
		}
	}

	public static function register_post_types() {
		register_post_type(
			self::$post_type,
			array(
				'label'           => __( 'Styles', 'formidable' ),
				'public'          => false,
				'show_ui'         => false,
				'capability_type' => 'page',
				'capabilities'    => array(
					'edit_post'          => 'frm_change_settings',
					'edit_posts'         => 'frm_change_settings',
					'edit_others_posts'  => 'frm_change_settings',
					'publish_posts'      => 'frm_change_settings',
					'delete_post'        => 'frm_change_settings',
					'delete_posts'       => 'frm_change_settings',
					'read_private_posts' => 'read_private_posts',
				),
				'supports'        => array(
					'title',
				),
				'has_archive'     => false,
				'labels'          => array(
					'name'          => __( 'Styles', 'formidable' ),
					'singular_name' => __( 'Style', 'formidable' ),
					'menu_name'     => __( 'Style', 'formidable' ),
					'edit'          => __( 'Edit', 'formidable' ),
					'add_new_item'  => __( 'Create a New Style', 'formidable' ),
					'edit_item'     => __( 'Edit Style', 'formidable' ),
				),
			)
		);
	}

	/**
	 * Add a "Forms" submenu to the Appearance menu.
	 * This submenu links to the page to edit the default form.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( 'themes.php', 'Formidable | ' . __( 'Styles', 'formidable' ), __( 'Forms', 'formidable' ), 'frm_change_settings', 'formidable-styles', 'FrmStylesController::route' );
	}

	/**
	 * @return void
	 */
	public static function admin_init() {
		if ( ! FrmAppHelper::is_style_editor_page() ) {
			return;
		}

		self::load_pro_hooks();

		$style_tab = FrmAppHelper::get_param( 'frm_action', '', 'get', 'sanitize_title' );
		if ( $style_tab === 'manage' || $style_tab === 'custom_css' ) {
			// we only need to load these styles/scripts on the styler page
			return;
		}

		$version = FrmAppHelper::plugin_version();
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'frm-custom-theme', admin_url( 'admin-ajax.php?action=frmpro_css' ), array(), $version );

		$style = apply_filters( 'frm_style_head', false );
		if ( $style ) {
			wp_enqueue_style( 'frm-single-custom-theme', admin_url( 'admin-ajax.php?action=frmpro_load_css&flat=1' ) . '&' . http_build_query( $style->post_content ), array(), $version );
		}
	}

	/**
	 * @param string $register Either 'enqueue' or 'register'.
	 * @param bool   $force True to enqueue/register the style if a form has not been loaded.
	 */
	public static function enqueue_css( $register = 'enqueue', $force = false ) {
		global $frm_vars;

		$register_css = ( $register == 'register' );
		$should_load  = $force || ( ( $frm_vars['load_css'] || $register_css ) && ! FrmAppHelper::is_admin() );

		if ( ! $should_load ) {
			return;
		}

		$frm_settings = FrmAppHelper::get_settings();
		if ( $frm_settings->load_style == 'none' ) {
			return;
		}

		$css = apply_filters( 'get_frm_stylesheet', self::custom_stylesheet() );

		if ( ! empty( $css ) ) {
			$css = (array) $css;

			$version = FrmAppHelper::plugin_version();

			foreach ( $css as $css_key => $file ) {
				if ( $register_css ) {
					$this_version = self::get_css_version( $css_key, $version );
					wp_register_style( $css_key, $file, array(), $this_version );
				}

				$load_on_all = ! FrmAppHelper::is_admin() && 'all' == $frm_settings->load_style;
				if ( $load_on_all || $register != 'register' ) {
					wp_enqueue_style( $css_key );
				}
				unset( $css_key, $file );
			}

			if ( $frm_settings->load_style == 'all' ) {
				$frm_vars['css_loaded'] = true;
			}
		}
		unset( $css );

		add_filter( 'style_loader_tag', 'FrmStylesController::add_tags_to_css', 10, 2 );
	}

	public static function custom_stylesheet() {
		global $frm_vars;
		$stylesheet_urls = array();

		if ( ! isset( $frm_vars['css_loaded'] ) || ! $frm_vars['css_loaded'] ) {
			//include css in head
			self::get_url_to_custom_style( $stylesheet_urls );
		}

		return $stylesheet_urls;
	}

	private static function get_url_to_custom_style( &$stylesheet_urls ) {
		$file_name = '/css/' . self::get_file_name();
		if ( is_readable( FrmAppHelper::plugin_path() . $file_name ) ) {
			$url = FrmAppHelper::plugin_url() . $file_name;
		} else {
			$url = admin_url( 'admin-ajax.php?action=frmpro_css' );
		}
		$stylesheet_urls['formidable'] = $url;
	}

	/**
	 * Use a different stylesheet per site in a multisite install
	 *
	 * @since 3.0.03
	 */
	public static function get_file_name() {
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
			$name    = 'formidableforms' . absint( $blog_id ) . '.css';
		} else {
			$name = 'formidableforms.css';
		}

		return $name;
	}

	private static function get_css_version( $css_key, $version ) {
		if ( 'formidable' == $css_key ) {
			$this_version = get_option( 'frm_last_style_update' );
			if ( ! $this_version ) {
				$this_version = $version;
			}
		} else {
			$this_version = $version;
		}

		return $this_version;
	}

	public static function add_tags_to_css( $tag, $handle ) {
		if ( ( 'formidable' == $handle || 'jquery-theme' == $handle ) && strpos( $tag, ' property=' ) === false ) {
			$frm_settings = FrmAppHelper::get_settings();
			if ( $frm_settings->use_html ) {
				$tag = str_replace( ' type="', ' property="stylesheet" type="', $tag );
			}
		}

		return $tag;
	}

	public static function new_style( $return = '' ) {
		self::style();
	}

	public static function duplicate() {
		self::style();
	}

	/**
	 * @param mixed  $style_id
	 * @param string $message
	 * @return void
	 */
	public static function edit( $style_id = false, $message = '' ) {
		// TODO deprecate this (because the params are unusable).
		self::style();
	}

	/**
	 * Render the style page for a form for assigning a style to a form, and for viewing style templates.
	 *
	 * @since x.x
	 *
	 * @return void
	 */
	public static function style() {
		if ( FrmAppHelper::get_post_param( 'style_id', 0, 'absint' ) ) {
			self::save_form_style();
		}

		self::setup_styles_and_scripts_for_style_page();

		$style_id = FrmAppHelper::simple_get( 'id', 'absint', 0 );
		$form_id  = FrmAppHelper::simple_get( 'form', 'absint', 0 );

		if ( ! $form_id ) {
			if ( ! $style_id ) {
				$action = FrmApphelper::simple_get( 'frm_action' );

				if ( 'new_style' === $action ) {
					$default_style = self::get_default_style();
					$style_id      = $default_style->ID;
				} elseif ( 'duplicate' === $action ) {
					$style_id = FrmAppHelper::simple_get( 'style_id', 'absint', 0 );
				}
			}

			$check = serialize( array( 'custom_style' => (string) $style_id ) );
			$check = substr( $check, 5, -1 );

			// TODO get a form for the target style.
			$form_id = FrmDb::get_var(
				'frm_forms',
				array(
					'options LIKE' => $check,
				)
			);
			if ( ! $form_id ) {
				// Fallback to any form.
				// TODO: Show a message why a random form is being shown (because no form is assigned to the style).
				$form_id = FrmDb::get_var( 'frm_forms', array( 'status' => 'published' ), 'id' );
			}
		}

		$form = FrmForm::getOne( $form_id );
		if ( ! is_object( $form ) ) {
			wp_die( 'This form does not exist', '', 404 );
		}

		$styles = self::get_styles_for_style_page( $form );

		if ( $style_id ) {
			$frm_style    = new FrmStyle( $style_id );
			$active_style = $frm_style->get_one();
		} else {
			$active_style  = is_callable( 'FrmProStylesController::get_active_style_for_form' ) ? FrmProStylesController::get_active_style_for_form( $form ) : reset( $styles );
		}

		$default_style = self::get_default_style();

		/**
		 * @since x.x
		 *
		 * @param array {
		 *     @type stdClass $form
		 * }
		 */
		do_action( 'frm_before_render_style_page', compact( 'form' ) );

		self::render_style_page( $active_style, $styles, $form, $default_style );
	}

	/**
	 * @since x.x
	 *
	 * @param stdClass $form
	 * @return array<WP_Post>
	 */
	private static function get_styles_for_style_page( $form ) {
		if ( is_callable( 'FrmProStylesController::get_styles_for_style_page' ) ) {
			return FrmProStylesController::get_styles_for_style_page( $form );
		}
		return array( self::get_default_style() );
	}

	/**
	 * @since x.x
	 *
	 * @return WP_Post
	 */
	private static function get_default_style() {
		$frm_style     = new FrmStyle( 'default' );
		$default_style = $frm_style->get_one();
		return $default_style;
	}

	/**
	 * Save style for form (from Style page) via an AJAX action.
	 *
	 * @since x.x
	 *
	 * @return void
	 */
	private static function save_form_style() {
		$permission_error = FrmAppHelper::permission_nonce_error( 'frm_edit_forms', 'frm_save_form_style', 'frm_save_form_style_nonce' );
		if ( $permission_error !== false ) {
			wp_die( 'Unable to save form', '', 403 );
		}

		$style_id = FrmAppHelper::get_post_param( 'style_id', 0, 'absint' );
		$form_id  = FrmAppHelper::get_post_param( 'form_id', 'absint', 0 );
		// TODO nonce / permission check.

		$form                          = FrmForm::getOne( $form_id );
		$form->options['custom_style'] = (string) $style_id; // We want to save a string for consistency. FrmStylesHelper::get_form_count_for_style expects the custom style ID is a string.

		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'frm_forms', array( 'options' => maybe_serialize( $form->options ) ), array( 'id' => $form->id ) );

		FrmForm::clear_form_cache();
	}

	/**
	 * Register and enqueue styles and scripts for the style tab page.
	 *
	 * @since x.x
	 *
	 * @return void
	 */
	private static function setup_styles_and_scripts_for_style_page() {
		$plugin_url      = FrmAppHelper::plugin_url();
		$version         = FrmAppHelper::plugin_version();
		$js_dependencies = array( 'wp-i18n', 'wp-hooks', 'formidable_dom' );

		wp_register_script( 'formidable_style', $plugin_url . '/js/admin/style.js', $js_dependencies, $version );
		wp_register_style( 'formidable_style', $plugin_url . '/css/admin/style.css', array(), $version );
		wp_print_styles( 'formidable_style' );

		wp_print_styles( 'formidable' );
		wp_enqueue_script( 'formidable_style' );
	}

	/**
	 * Render the style page (with a more limited and typed scope than calling it from self::style directly).
	 *
	 * @since x.x
	 *
	 * @param WP_Post        $active_style
	 * @param array<WP_Post> $styles
	 * @param stdClass       $form
	 * @param WP_Post        $default_style
	 * @return void
	 */
	private static function render_style_page( $active_style, $styles, $form, $default_style ) {
		$style_views_path = FrmAppHelper::plugin_path() . '/classes/views/styles/';
		$view             = FrmAppHelper::simple_get( 'frm_action', 'sanitize_text_field', 'list' ); // edit, list (default), new_style.
		$frm_style        = new FrmStyle( $active_style->ID );

		if ( in_array( $view, array( 'edit', 'new_style', 'duplicate' ), true ) ) {
			FrmStylesController::add_meta_boxes();
		}

		if ( 'edit' === $view ) {
			$style = $active_style;
		} elseif ( in_array( $view, array( 'new_style', 'duplicate' ), true ) ) {
			$style             = clone $active_style;
			$style->ID         = '';
			$style->post_title = FrmAppHelper::simple_get( 'style_name' );
			$style->post_name  = 'new-style';
		}

		if ( ! isset( $style ) ) {
			$style = $active_style;
		}

		self::force_form_style( $style );

		include $style_views_path . 'show.php';
	}

	/**
	 * Filter form classes so the form uses the preview style, not the form's active style.
	 *
	 * @since x.x
	 *
	 * @param WP_Post $style
	 * @return void
	 */
	private static function force_form_style( $style ) {
		add_filter(
			'frm_add_form_style_class',
			function( $class ) use ( $style ) {
				$split = array_filter(
					explode( ' ', $class ),
					/**
					 * @param string $class
					 */
					function( $class ) {
						return $class && 0 !== strpos( $class, 'frm_style_' );
					}
				);
				$split[] = 'frm_style_' . $style->post_name;
				return implode( ' ', $split );
			}
		);
	}

	public static function save_style() {
		$frm_style   = new FrmStyle();
		$message     = '';
		$post_id     = FrmAppHelper::get_post_param( 'ID', false, 'sanitize_title' );
		$style_nonce = FrmAppHelper::get_post_param( 'frm_style', '', 'sanitize_text_field' );

		if ( $post_id !== false && wp_verify_nonce( $style_nonce, 'frm_style_nonce' ) ) {
			$id = $frm_style->update( $post_id );
			if ( empty( $post_id ) && ! empty( $id ) ) {
				self::maybe_redirect_after_save( $id );

				$post_id = reset( $id ); // Set the post id to the new style so it will be loaded for editing.
			}

			// include the CSS that includes this style
			//echo '<link href="' . esc_url( admin_url( 'admin-ajax.php?action=frmpro_css' ) ) . '" type="text/css" rel="Stylesheet" class="frm-custom-theme" />';
			//$message = __( 'Your styling settings have been saved.', 'formidable' );
		}

		return array( $post_id, $message );
	}

	/**
	 * Show the edit view after saving.
	 * The save event is triggered earlier, on admin init where self::save_style is called.
	 *
	 * @return void
	 */
	public static function save() {
		// TODO $message from self::save_style never gets shown anywhere.
		self::edit();
	}

	/**
	 * Force a redirect after duplicating or creating a new style to avoid an old stale URL that could result in more styles than intended.
	 *
	 * @since x.x
	 *
	 * @param array $ids
	 * @return void
	 */
	private static function maybe_redirect_after_save( $ids ) {
		$referer = FrmAppHelper::get_server_value( 'HTTP_REFERER' );
		$parsed  = parse_url( $referer );
		$query   = $parsed['query'];

		$current_action      = false;
		$actions_to_redirect = array( 'duplicate', 'new_style' );
		foreach ( $actions_to_redirect as $action ) {
			if ( false !== strpos( $query, 'frm_action=' . $action ) ) {
				$current_action = $action;
				break;
			}
		}

		if ( false === $current_action ) {
			// Do not redirect as the referer URL did not match $actions_to_redirect.
			return;
		}

		$style     = new stdClass();
		$style->ID = end( $ids );
		wp_safe_redirect( esc_url_raw( FrmStylesHelper::get_edit_url( $style ) ) );
		die();
	}

	public static function load_styler( $style, $message = '' ) {
		// TODO deprecate this.
	}

	/**
	 * @param string $message
	 * @param array|object $forms
	 */
	private static function manage( $message = '', $forms = array() ) {
		$frm_style     = new FrmStyle();
		$styles        = $frm_style->get_all();
		$default_style = $frm_style->get_default_style( $styles );

		if ( empty( $forms ) ) {
			$forms = FrmForm::get_published_forms();
		}

		include( FrmAppHelper::plugin_path() . '/classes/views/styles/manage.php' );
	}

	private static function manage_styles() {
		$style_nonce = FrmAppHelper::get_post_param( 'frm_manage_style', '', 'sanitize_text_field' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $_POST || ! isset( $_POST['style'] ) || ! wp_verify_nonce( $style_nonce, 'frm_manage_style_nonce' ) ) {
			return self::manage();
		}

		global $wpdb;

		$forms = FrmForm::get_published_forms();
		foreach ( $forms as $form ) {
			$new_style      = ( isset( $_POST['style'] ) && isset( $_POST['style'][ $form->id ] ) ) ? sanitize_text_field( wp_unslash( $_POST['style'][ $form->id ] ) ) : '';
			$previous_style = ( isset( $_POST['prev_style'] ) && isset( $_POST['prev_style'][ $form->id ] ) ) ? sanitize_text_field( wp_unslash( $_POST['prev_style'][ $form->id ] ) ) : '';
			if ( $new_style == $previous_style ) {
				continue;
			}

			$form->options['custom_style'] = $new_style;

			$wpdb->update( $wpdb->prefix . 'frm_forms', array( 'options' => maybe_serialize( $form->options ) ), array( 'id' => $form->id ) );
			unset( $form );
		}

		$message = __( 'Your form styles have been saved.', 'formidable' );

		return self::manage( $message, $forms );
	}

	/**
	 * @param string        $message
	 * @param FrmStyle|null $style
	 * @return void
	 */
	public static function custom_css( $message = '', $style = null ) {
		if ( function_exists( 'wp_enqueue_code_editor' ) ) {
			$id       = 'frm_codemirror_box';
			$settings = wp_enqueue_code_editor(
				array(
					'type'       => 'text/css',
					'codemirror' => array(
						'indentUnit' => 2,
						'tabSize'    => 2,
					),
				)
			);
		} else {
			$settings = false;
		}

		if ( empty( $settings ) ) {
			$id = 'frm_custom_css_box';
		}

		if ( ! isset( $style ) ) {
			$frm_style = new FrmStyle();
			$style     = $frm_style->get_default_style();
		}

		include FrmAppHelper::plugin_path() . '/classes/views/styles/custom_css.php';
	}

	/**
	 * @return void
	 */
	public static function save_css() {
		$frm_style = new FrmStyle();

		$message = '';
		$post_id = FrmAppHelper::get_post_param( 'ID', false, 'sanitize_text_field' );
		$nonce   = FrmAppHelper::get_post_param( 'frm_custom_css', '', 'sanitize_text_field' );
		if ( wp_verify_nonce( $nonce, 'frm_custom_css_nonce' ) ) {
			$frm_style->update( $post_id );
			$message = __( 'Your styling settings have been saved.', 'formidable' );
		}

		self::custom_css( $message );
	}

	public static function route() {
		$action = FrmAppHelper::get_param( 'frm_action', '', 'get', 'sanitize_title' );
		FrmAppHelper::include_svg();

		switch ( $action ) {
			case 'edit':
			case 'save':
			case 'manage':
			case 'manage_styles':
			case 'custom_css':
			case 'save_css':
				return self::$action();
			default:
				do_action( 'frm_style_action_route', $action );
				if ( apply_filters( 'frm_style_stop_action_route', false, $action ) ) {
					return;
				}

				if ( in_array( $action, array( 'new_style', 'duplicate' ), true ) ) {
					return self::$action();
				}

				return self::edit();
		}
	}

	/**
	 * Handle AJAX routing for frm_settings_reset for resetting styles to the default settings.
	 *
	 * @since x.x This function was repurposed to actually reset a style. It now requires a target $_POST['styleId'] value.
	 * Prior so x.x it would return an array of default settings as reset would require a subsequent update with the new default settings.
	 *
	 * @return void
	 */
	public static function reset_styling() {
		FrmAppHelper::permission_check( 'frm_change_settings' );
		check_ajax_referer( 'frm_ajax', 'nonce' );

		$style_id = FrmAppHelper::get_post_param( 'styleId', '', 'absint' );
		if ( ! $style_id ) {
			wp_die( 0 );
		}

		$frm_style            = new FrmStyle();
		$defaults             = $frm_style->get_defaults();
		$default_post_content = FrmAppHelper::prepare_and_encode( $defaults );
		$where                = array(
			'ID'        => $style_id,
			'post_type' => FrmStylesController::$post_type,
		);
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_content' => $default_post_content ), $where );

		$frm_style->save_settings(); // Save the settings after resetting to default or the old style will still appear.

		wp_send_json_success(
			array(

			)
		);
		wp_die();
	}

	public static function change_styling() {
		check_ajax_referer( 'frm_ajax', 'nonce' );

		$frm_style = new FrmStyle();
		$defaults  = $frm_style->get_defaults();
		$style     = '';

		echo '<style type="text/css">';
		include FrmAppHelper::plugin_path() . '/css/_single_theme.css.php';
		echo '</style>';
		wp_die();
	}

	public static function add_meta_boxes() {

		// setup meta boxes
		$meta_boxes = array(
			'general'                => __( 'General', 'formidable' ),
			'form-title'             => __( 'Form Title', 'formidable' ),
			'form-description'       => __( 'Form Description', 'formidable' ),
			'field-labels'           => __( 'Field Labels', 'formidable' ),
			'field-description'      => __( 'Field Description', 'formidable' ),
			'field-colors'           => __( 'Field Colors', 'formidable' ),
			'field-sizes'            => __( 'Field Settings', 'formidable' ),
			'check-box-radio-fields' => __( 'Check Box & Radio Fields', 'formidable' ),
			'buttons'                => __( 'Buttons', 'formidable' ),
			'form-messages'          => __( 'Form Messages', 'formidable' ),
		);

		/**
		 * Add custom boxes to the styling settings
		 *
		 * @since 2.3
		 */
		$meta_boxes = apply_filters( 'frm_style_boxes', $meta_boxes );

		foreach ( $meta_boxes as $nicename => $name ) {
			add_meta_box( $nicename . '-style', $name, 'FrmStylesController::include_style_section', self::$screen, 'side', 'default', $nicename );
			unset( $nicename, $name );
		}
	}

	/**
	 * @param array $atts
	 * @param array $sec
	 * @return void
	 */
	public static function include_style_section( $atts, $sec ) {
		extract( $atts ); // phpcs:ignore WordPress.PHP.DontExtract
		$style = $atts['style'];
		FrmStylesHelper::prepare_color_output( $style->post_content, false );

		$current_tab = FrmAppHelper::simple_get( 'page-tab', 'sanitize_title', 'default' );
		$file_name   = FrmAppHelper::plugin_path() . '/classes/views/styles/_' . $sec['args'] . '.php';

		/**
		 * Set the location of custom styling settings right before
		 * loading onto the page. If your style box was named "progress",
		 * this hook name will be frm_style_settings_progress.
		 *
		 * @since 2.3
		 */
		$file_name = apply_filters( 'frm_style_settings_' . $sec['args'], $file_name );

		echo '<div class="frm_grid_container">';
		include $file_name;
		echo '</div>';
	}

	public static function load_css() {
		header( 'Content-type: text/css' );

		$frm_style = new FrmStyle();
		$defaults  = $frm_style->get_defaults();
		$style     = '';

		include FrmAppHelper::plugin_path() . '/css/_single_theme.css.php';
		wp_die();
	}

	/**
	 * @return void
	 */
	public static function load_saved_css() {
		$css = get_transient( 'frmpro_css' );

		ob_start();
		include FrmAppHelper::plugin_path() . '/css/custom_theme.css.php';
		$output = ob_get_clean();
		$output = self::replace_relative_url( $output );

		/**
		 * The API needs to load font icons through a custom URL.
		 *
		 * @since 5.2
		 *
		 * @param string $output
		 */
		$output = apply_filters( 'frm_saved_css', $output );

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die();
	}

	/**
	 * Replaces relative URL with absolute URL.
	 *
	 * @since 4.11.03
	 *
	 * @param string $css CSS content.
	 * @return string
	 */
	public static function replace_relative_url( $css ) {
		$plugin_url = trailingslashit( FrmAppHelper::plugin_url() );
		return str_replace(
			array(
				'url(../',
				"url('../",
				'url("../',
			),
			array(
				'url(' . $plugin_url,
				"url('" . $plugin_url,
				'url("' . $plugin_url,
			),
			$css
		);
	}

	/**
	 * Check if the Formidable styling should be loaded,
	 * then enqueue it for the footer
	 *
	 * @since 2.0
	 */
	public static function enqueue_style() {
		global $frm_vars;

		if ( isset( $frm_vars['css_loaded'] ) && $frm_vars['css_loaded'] ) {
			// The CSS has already been loaded.
			return;
		}

		$frm_settings = FrmAppHelper::get_settings();
		if ( $frm_settings->load_style != 'none' ) {
			wp_enqueue_style( 'formidable' );
			$frm_vars['css_loaded'] = true;
		}
	}

	/**
	 * Get the stylesheets for the form settings page
	 *
	 * @return array<WP_Post>
	 */
	public static function get_style_opts() {
		$frm_style = new FrmStyle();
		$styles    = $frm_style->get_all();

		return $styles;
	}

	public static function get_form_style( $form = 'default' ) {
		$style = FrmFormsHelper::get_form_style( $form );

		if ( empty( $style ) || 1 == $style ) {
			$style = 'default';
		}

		$frm_style = new FrmStyle( $style );

		return $frm_style->get_one();
	}

	/**
	 * @param string $class
	 * @param string $style
	 */
	public static function get_form_style_class( $class, $style ) {
		if ( 1 == $style ) {
			$style = 'default';
		}

		$frm_style = new FrmStyle( $style );
		$style     = $frm_style->get_one();

		if ( $style ) {
			$class .= ' frm_style_' . $style->post_name;
			self::maybe_add_rtl_class( $style, $class );
		}

		return $class;
	}

	/**
	 * @param object $style
	 * @param string $class
	 *
	 * @since 3.0
	 */
	private static function maybe_add_rtl_class( $style, &$class ) {
		$is_rtl = isset( $style->post_content['direction'] ) && 'rtl' === $style->post_content['direction'];
		if ( $is_rtl ) {
			$class .= ' frm_rtl';
		}
	}

	/**
	 * @param string $val
	 */
	public static function get_style_val( $val, $form = 'default' ) {
		$style = self::get_form_style( $form );
		if ( $style && isset( $style->post_content[ $val ] ) ) {
			return $style->post_content[ $val ];
		}
	}

	public static function show_entry_styles( $default_styles ) {
		$frm_style = new FrmStyle( 'default' );
		$style     = $frm_style->get_one();

		if ( ! $style ) {
			return $default_styles;
		}

		foreach ( $default_styles as $name => $val ) {
			$setting = $name;
			if ( 'border_width' == $name ) {
				$setting = 'field_border_width';
			} elseif ( 'alt_bg_color' == $name ) {
				$setting = 'bg_color_active';
			}
			$default_styles[ $name ] = $style->post_content[ $setting ];
			unset( $name, $val );
		}

		return $default_styles;
	}

	public static function &important_style( $important, $field ) {
		$important = self::get_style_val( 'important_style', $field['form_id'] );

		return $important;
	}

	public static function do_accordion_sections( $screen, $context, $object ) {
		return do_accordion_sections( $screen, $context, $object );
	}
}
