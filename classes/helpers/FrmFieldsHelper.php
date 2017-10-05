<?php
if ( ! defined('ABSPATH') ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmFieldsHelper {

	public static function setup_new_vars( $type = '', $form_id = '' ) {

        if ( strpos($type, '|') ) {
            list($type, $setting) = explode('|', $type);
        }

		$values = self::get_default_field( $type );

		global $wpdb;
		$field_count = FrmDb::get_var( 'frm_fields', array( 'form_id' => $form_id ), 'field_order', array( 'order_by' => 'field_order DESC' ) );

		$values['field_key'] = FrmAppHelper::get_unique_key( '', $wpdb->prefix . 'frm_fields', 'field_key' );
		$values['form_id'] = $form_id;
		$values['field_order'] = $field_count + 1;
		$values['field_options']['custom_html'] = self::get_default_html( $type );

		if ( isset( $setting ) && ! empty( $setting ) ) {
			if ( in_array( $type, array( 'data', 'lookup' ) ) ) {
				$values['field_options']['data_type'] = $setting;
			} else {
				$values['field_options'][ $setting ] = 1;
			}
		}

        return $values;
    }

	public static function get_html_id( $field, $plus = '' ) {
		return apply_filters( 'frm_field_html_id', 'field_' . $field['field_key'] . $plus, $field );
    }

    public static function setup_edit_vars( $field, $doing_ajax = false ) {
		$values = self::field_object_to_array( $field, $doing_ajax );
		return apply_filters( 'frm_setup_edit_field_vars', $values, array( 'doing_ajax' => $doing_ajax ) );
    }

	public static function field_object_to_array( $field, $doing_ajax = false ) {
		$values = (array) $field;
		$values['form_name'] = '';

		if ( ! $doing_ajax ) {
			$field_values = array( 'name', 'description', 'field_key', 'type', 'default_value', 'field_order', 'required' );
			foreach ( $field_values as $var ) {
				$values[ $var ] = FrmAppHelper::get_param( $var, $values[ $var ], 'get', 'htmlspecialchars' );
				unset( $var );
			}

			$values['form_name'] = $field->form_id ? FrmForm::getName( $field->form_id ) : '';
		}

		self::fill_field_array( $field, $values );

		$values['custom_html'] = ( isset( $field->field_options['custom_html'] ) ) ? $field->field_options['custom_html'] : self::get_default_html( $field->type );

		return $values;
	}

	private static function fill_field_array( $field, array &$field_array ) {
		$field_array['options'] = $field->options;
		$field_array['value'] = $field->default_value;

		self::fill_default_field_opts( $field, $field_array );

		$field_array['original_type'] = $field->type;
		$field_array = apply_filters( 'frm_setup_edit_fields_vars', $field_array, $field );

		$field_array = array_merge( $field->field_options, $field_array );
	}

	private static function fill_default_field_opts( $field, array &$values ) {
		$defaults = self::get_default_field_options_from_field( $field );

		foreach ( $defaults as $opt => $default ) {
			$current_opt = isset( $field->field_options[ $opt ] ) ? $field->field_options[ $opt ] : $default;
			$values[ $opt ] = ( $_POST && isset( $_POST['field_options'][ $opt . '_' . $field->id ] ) ) ? stripslashes_deep( maybe_unserialize( $_POST['field_options'][ $opt . '_' . $field->id ] ) ) : $current_opt;
			unset( $opt, $default );
		}
	}

    public static function get_default_field_opts( $type, $field, $limit = false ) {
		if ( $limit ) {
			_deprecated_function( __FUNCTION__, '3.0', 'FrmFieldHelper::get_default_field_options' );
			$field_options = self::get_default_field_options( $type );
		} else {
			_deprecated_function( __FUNCTION__, '3.0', 'FrmFieldHelper::get_default_field' );
			$field_options = self::get_default_field( $type );
		}

		return $field_options;
    }

	/**
	 * @since 3.0
	 */
	public static function get_default_field_options( $type ) {
		$field_type = FrmFieldFactory::get_field_type( $type );
		return $field_type->get_default_field_options();
	}

	/**
	 * @since 3.0
	 */
	public static function get_default_field_options_from_field( $field ) {
		if ( isset( $field->field_options['original_type'] ) && $field->type != $field->field_options['original_type'] ) {
			$field->type = $field->field_options['original_type'];
		}
		$field_type = FrmFieldFactory::get_field_object( $field );
		return $field_type->get_default_field_options();
	}

	/**
	 * @since 3.0
	 */
	public static function get_default_field( $type ) {
		$field_type = FrmFieldFactory::get_field_type( $type );
		return $field_type->get_new_field_defaults();
	}

    public static function fill_field( &$values, $field, $form_id, $new_key = '' ) {
        global $wpdb;

		$values['field_key'] = FrmAppHelper::get_unique_key( $new_key, $wpdb->prefix . 'frm_fields', 'field_key' );
        $values['form_id'] = $form_id;
        $values['options'] = maybe_serialize($field->options);
        $values['default_value'] = maybe_serialize($field->default_value);

        foreach ( array( 'name', 'description', 'type', 'field_order', 'field_options', 'required' ) as $col ) {
            $values[ $col ] = $field->{$col};
        }
    }

    /**
     * @since 2.0
     */
	public static function get_error_msg( $field, $error ) {
		$frm_settings = FrmAppHelper::get_settings();
		$default_settings = $frm_settings->default_options();
		$field_name = is_array( $field ) ? $field['name'] : $field->name;

		$conf_msg = __( 'The entered values do not match', 'formidable' );
		$defaults = array(
			'unique_msg' => array( 'full' => $default_settings['unique_msg'], 'part' => sprintf( __('%s must be unique', 'formidable' ), $field_name ) ),
			'invalid'   => array( 'full' => __( 'This field is invalid', 'formidable' ), 'part' => sprintf( __('%s is invalid', 'formidable' ), $field_name ) ),
			'blank'     => array( 'full' => $frm_settings->blank_msg, 'part' => $frm_settings->blank_msg ),
			'conf_msg'  => array( 'full' => $conf_msg, 'part' => $conf_msg ),
		);

		$msg = FrmField::get_option( $field, $error );
		$msg = empty( $msg ) ? $defaults[ $error ]['part'] : $msg;
		$msg = do_shortcode( $msg );
		return $msg;
	}

	public static function get_form_fields( $form_id, $error = array() ) {
		$fields = FrmField::get_all_for_form( $form_id );
		return apply_filters( 'frm_get_paged_fields', $fields, $form_id, $error );
	}

	public static function get_default_html( $type = 'text' ) {
		$field = FrmFieldFactory::get_field_type( $type );
		$default_html = $field->default_html();

		// these hooks are here for reverse compatibility since 3.0
		if ( ! apply_filters( 'frm_normal_field_type_html', true, $type ) ) {
			$default_html = apply_filters( 'frm_other_custom_html', '', $type );
		}

		return apply_filters('frm_custom_html', $default_html, $type);
	}

	public static function show_fields( $fields, $errors, $form, $form_action ) {
		foreach ( $fields as $field ) {
			$field_obj = FrmFieldFactory::get_field_type( $field['type'], $field );
			$field_obj->show_field( compact( 'errors', 'form', 'form_action' ) );
		}
	}

	public static function replace_shortcodes( $html, $field, $errors = array(), $form = false, $args = array() ) {
		_deprecated_function( __FUNCTION__, '3.0', 'FrmFieldType::prepare_field_html' );
		$field_obj = FrmFieldFactory::get_field_type( $field['type'], $field );
		return $field_obj->prepare_field_html( compact( 'errors', 'form' ) );
	}

	/**
	 * @since 3.0
	 */
	public static function replace_shortcodes_before_input( $args, &$html ) {
		$field = $args['field'];

		// Remove the for attribute for captcha
		if ( $field['type'] == 'captcha' ) {
			$html = str_replace( ' for="field_[key]"', '', $html );
		}

		self::replace_field_values( $field, $args, $html );
		self::add_class_to_divider( $field, $html );

		self::replace_required_label_shortcode( $field, $html );
		self::replace_required_class( $field, $html );
		self::replace_description_shortcode( $field, $html );
		self::replace_error_shortcode( $args, $html );
		self::add_class_to_label( $field, $html );
		self::add_field_div_classes( $args['field_id'], $field, $args['errors'], $html );

		self::replace_entry_key( $html );
		self::replace_form_shortcodes( $args['form'], $html );
		self::process_wp_shortcodes( $html );
	}

	/**
	 * @since 3.0
	 */
	private static function replace_field_values( $field, $args, &$html ) {
		//replace [id]
		$html = str_replace( '[id]', $args['field_id'], $html );

		// set the label for
		$html = str_replace( 'field_[key]', $args['html_id'], $html );

		//replace [key]
		$html = str_replace( '[key]', $field['field_key'], $html );

		//replace [field_name]
		$html = str_replace('[field_name]', $field['name'], $html );
	}

	/**
	 * If field type is section heading, add class so a bottom margin
	 * can be added to either the h3 or description
	 *
	 * @since 3.0
	 */
	private static function add_class_to_divider( $field, &$html ) {
		if ( $field['type'] == 'divider' ) {
			if ( FrmField::is_option_true( $field, 'description' ) ) {
				$html = str_replace( 'frm_description', 'frm_description frm_section_spacing', $html );
			} else {
				$html = str_replace( '[label_position]', '[label_position] frm_section_spacing', $html );
			}
		}
	}

	/**
	 * @since 3.0
	 */
	private static function replace_required_label_shortcode( $field, &$html ) {
		$required = FrmField::is_required( $field ) ? $field['required_indicator'] : '';
		self::remove_inline_conditions( ! empty( $required ), 'required_label', $required, $html );
	}

	/**
	 * @since 3.0
	 */
	private static function replace_description_shortcode( $field, &$html ) {
		$description = $field['description'];
		self::remove_inline_conditions( ( $description && $description != '' ), 'description', $description, $html );
	}

	/**
	 * @since 3.0
	 */
	private static function replace_error_shortcode( $args, &$html ) {
		$error = isset( $args['errors'][ 'field' . $args['field_id'] ] ) ? $args['errors'][ 'field' . $args['field_id'] ] : false;
		self::remove_inline_conditions( ! empty( $error ), 'error', $error, $html );
	}
	/**
	 * replace [required_class]
	 *
	 * @since 3.0
	 */
	private static function replace_required_class( $field, &$html ) {
		$required_class = FrmField::is_required( $field ) ? ' frm_required_field' : '';
		$html = str_replace( '[required_class]', $required_class, $html );
	}

	/**
	 * replace [entry_key]
	 *
	 * @since 3.0
	 */
	private static function replace_entry_key( &$html ) {
		$entry_key = FrmAppHelper::simple_get( 'entry', 'sanitize_title' );
		$html = str_replace( '[entry_key]', $entry_key, $html );
	}

	/**
	 * @since 3.0
	 */
	private static function replace_form_shortcodes( $form, &$html ) {
		if ( $form ) {
			$form = (array) $form;

			//replace [form_key]
			$html = str_replace( '[form_key]', $form['form_key'], $html );

			//replace [form_name]
			$html = str_replace( '[form_name]', $form['name'], $html );
		}
	}

	/**
	 * @since 3.0
	 */
	public static function replace_shortcodes_after_input( $args, &$html ) {
		$html .= "\n";

		//Return html if conf_field to prevent loop
		if ( isset( $args['field']['conf_field'] ) && $args['field']['conf_field'] == 'stop' ) {
			return;
		}

		self::filter_for_more_shortcodes( $args, $args['field'], $html);
		self::filter_html_field_shortcodes( $args['field'], $html );
		self::remove_collapse_shortcode( $html );
	}

	/**
	 * @since 3.0
	 */
	private static function filter_for_more_shortcodes( $args, $field, &$html) {
		//If field is not in repeating section
		if ( empty( $args['section_id'] ) ) {
			$args = array( 'errors' => $args['errors'], 'form' => $args['form'] );
		}
		$html = apply_filters( 'frm_replace_shortcodes', $html, $field, $args );
	}

	private static function filter_html_field_shortcodes( $field, &$html ) {
		if ( $field['type'] == 'html' ) {
			if ( apply_filters( 'frm_use_wpautop', true ) ) {
				$html = wpautop( $html );
			}
			$html = apply_filters( 'frm_get_default_value', $html, (object) $field, false );
			$html = do_shortcode( $html );
		}
	}

	/**
	 * TODO: 3.0 remove this function
	 * @since 3.0
	 */
	public static function replace_shortcodes_with_atts( $args, &$html ) {
		preg_match_all("/\[(deletelink)\b(.*?)(?:(\/))?\]/s", $html, $shortcodes, PREG_PATTERN_ORDER);

		foreach ( $shortcodes[0] as $short_key => $tag ) {
			$shortcode_atts = FrmShortcodeHelper::get_shortcode_attribute_array( $shortcodes[2][ $short_key ] );
			$tag = FrmShortcodeHelper::get_shortcode_tag( $shortcodes, $short_key, array( 'conditional' => false, 'conditional_check' => false ) );

			$replace_with = '';

			if ( $tag == 'deletelink' && FrmAppHelper::pro_is_installed() ) {
				$replace_with = FrmProEntriesController::entry_delete_link( $shortcode_atts );
			}

			$html = str_replace( $shortcodes[0][ $short_key ], $replace_with, $html );
		}
	}

	/**
	 * Get the class to use for the label position
	 * @since 2.05
	 */
	public static function &label_position( $position, $field, $form ) {
		if ( $position && $position != '' ) {
			return $position;
		}

		$position = FrmStylesController::get_style_val( 'position', $form );
		if ( $position == 'none' ) {
			$position = 'top';
		} elseif ( $position == 'no_label' ) {
			$position = 'none';
		} elseif ( $position == 'inside' && ! self::is_placeholder_field_type( $field['type'] ) ) {
			$position = 'top';
		}

		$position = apply_filters( 'frm_html_label_position', $position, $field, $form );
		$position = ( ! empty( $position ) ) ? $position : 'top';

		return $position;
	}

	/**
	 * Check if this field type allows placeholders
	 * @since 2.05
	 */
	public static function is_placeholder_field_type( $type ) {
		return ! in_array( $type, array( 'select', 'radio', 'checkbox', 'hidden' ) );
	}

	/**
	 * Add the label position class into the HTML
	 * If the label position is inside, add a class to show the label if the field has a value.
	 *
	 * @since 2.05
	 */
	private static function add_class_to_label( $field, &$html ) {
		$label_class = in_array( $field['type'], array( 'divider', 'end_divider', 'break' ) ) ? $field['label'] : ' frm_primary_label';
		$html = str_replace( '[label_position]', $label_class, $html );
		if ( $field['label'] == 'inside' && $field['value'] != '' ) {
			$html = str_replace( 'frm_primary_label', 'frm_primary_label frm_visible', $html );
		}
	}

	/**
	 * This filters shortcodes in the field HTML
	 *
	 * @since 2.02.11
	 */
	private static function process_wp_shortcodes( &$html ) {
		if ( apply_filters( 'frm_do_html_shortcodes', true ) ) {
			$html = do_shortcode( $html );
		}
	}

	/**
	 * Add classes to a field div
	 *
	 * @since 2.02.05
	 *
	 * @param string $field_id
	 * @param array $field
	 * @param array $errors
	 * @param string $html
	 */
	private static function add_field_div_classes( $field_id, $field, $errors, &$html ) {
		$classes = self::get_field_div_classes( $field_id, $field, $errors, $html );

		if ( $field['type'] == 'html' && strpos( $html, '[error_class]' ) === false ) {
			// there is no error_class shortcode for HTML fields
			$html = str_replace( 'class="frm_form_field', 'class="frm_form_field ' . $classes, $html );
		}
		$html = str_replace( '[error_class]', $classes, $html );
	}

	/**
	 * Get the classes for a field div
	 *
	 * @since 2.02.05
	 *
	 * @param string $field_id
	 * @param array $field
	 * @param array $errors
	 * @param string $html
	 * @return string $classes
	 */
	private static function get_field_div_classes( $field_id, $field, $errors, $html ) {
		// Add error class
		$classes = isset( $errors[ 'field' . $field_id ] ) ? ' frm_blank_field' : '';

		// Add label position class
		$classes .= ' frm_' . $field['label'] . '_container';

		// Add CSS layout classes
		if ( ! empty( $field['classes'] ) ) {
			if ( ! strpos( $html, 'frm_form_field ') ) {
				$classes .= ' frm_form_field';
			}
			$classes .= ' ' . $field['classes'];
		}

		// Add class to HTML field
		if ( $field['type'] == 'html' ) {
			$classes .= ' frm_html_container';
		}

		// Get additional classes
		$classes = apply_filters( 'frm_field_div_classes', $classes, $field, array( 'field_id' => $field_id ) );

		return $classes;
	}

    public static function remove_inline_conditions( $no_vars, $code, $replace_with, &$html ) {
        if ( $no_vars ) {
			$html = str_replace( '[if ' . $code . ']', '', $html );
			$html = str_replace( '[/if ' . $code . ']', '', $html );
        } else {
			$html = preg_replace( '/(\[if\s+' . $code . '\])(.*?)(\[\/if\s+' . $code . '\])/mis', '', $html );
        }

		$html = str_replace( '[' . $code . ']', $replace_with, $html );
    }

	public static function get_shortcode_tag( $shortcodes, $short_key, $args ) {
		_deprecated_function( __FUNCTION__, '3.0', 'FrmShortcodesHelper::get_shortcode_tag' );
        return FrmShortcodeHelper::get_shortcode_tag( $shortcodes, $short_key, $args );
    }

	/**
	 * Remove [collapse_this] if it's still included after all processing
	 * @since 2.0.8
	 */
	private static function remove_collapse_shortcode( &$html ) {
		if ( preg_match( '/\[(collapse_this)\]/s', $html ) ) {
			$html = str_replace( '[collapse_this]', '', $html );
		}
	}

	public static function get_checkbox_id( $field, $opt_key ) {
		$id = $field['id'];
		if ( isset( $field['in_section'] ) && $field['in_section'] ) {
			$id .= '-' . $field['in_section'];
		}
		return 'frm_checkbox_' . $id . '-' . $opt_key;
	}

	public static function display_recaptcha( $field ) {
		_deprecated_function( __FUNCTION__, '3.0', 'FrmFieldCaptcha::field_input' );
    }

	public static function show_single_option( $field ) {
		if ( ! is_array( $field['options'] ) ) {
			return;
		}

        $field_name = $field['name'];
        $html_id = self::get_html_id($field);

		foreach ( $field['options'] as $opt_key => $opt ) {
		    $field_val = self::get_value_from_array( $opt, $opt_key, $field );
		    $opt = self::get_label_from_array( $opt, $opt_key, $field );

			// Get string for Other text field, if needed
			$other_val = self::get_other_val( compact( 'opt_key', 'field' ) );

			$checked = ( $other_val || isset( $field['value'] ) && ( ( ! is_array( $field['value'] ) && $field['value'] == $field_val ) || ( is_array($field['value'] ) && in_array( $field_val, $field['value'] ) ) ) ) ? ' checked="checked"':'';

		    // If this is an "Other" option, get the HTML for it
			if ( self::is_other_opt( $opt_key ) ) {
				if ( FrmAppHelper::pro_is_installed() ) {
					require( FrmProAppHelper::plugin_path() . '/classes/views/frmpro-fields/other-option.php' );
				}
		    } else {
				require( FrmAppHelper::plugin_path() . '/classes/views/frm-fields/single-option.php' );
		    }

			unset( $checked, $other_val );
		}
    }

	public static function get_value_from_array( $opt, $opt_key, $field ) {
		$opt = apply_filters( 'frm_field_value_saved', $opt, $opt_key, $field );
		return FrmFieldsController::check_value( $opt, $opt_key, $field );
	}

	public static function get_label_from_array( $opt, $opt_key, $field ) {
		$opt = apply_filters( 'frm_field_label_seen', $opt, $opt_key, $field );
		return FrmFieldsController::check_label( $opt );
	}

	public static function get_term_link( $tax_id ) {
        $tax = get_taxonomy($tax_id);
        if ( ! $tax ) {
            return;
        }

        $link = sprintf(
            __( 'Please add options from the WordPress "%1$s" page', 'formidable' ),
			'<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . $tax->name ) ) . '" target="_blank">' . ( empty( $tax->labels->name ) ? __( 'Categories' ) : $tax->labels->name ) . '</a>'
        );
        unset($tax);

        return $link;
    }

	public static function value_meets_condition( $observed_value, $cond, $hide_opt ) {
		$hide_opt = self::get_value_for_comparision( $hide_opt );
		$observed_value = self::get_value_for_comparision( $observed_value );

        if ( is_array($observed_value) ) {
            return self::array_value_condition($observed_value, $cond, $hide_opt);
        }

        $m = false;
        if ( $cond == '==' ) {
            $m = $observed_value == $hide_opt;
        } else if ( $cond == '!=' ) {
            $m = $observed_value != $hide_opt;
        } else if ( $cond == '>' ) {
            $m = $observed_value > $hide_opt;
        } else if ( $cond == '<' ) {
            $m = $observed_value < $hide_opt;
        } else if ( $cond == 'LIKE' || $cond == 'not LIKE' ) {
            $m = stripos($observed_value, $hide_opt);
            if ( $cond == 'not LIKE' ) {
                $m = ( $m === false ) ? true : false;
            } else {
                $m = ( $m === false ) ? false : true;
            }
        }
        return $m;
    }

	/**
	 * Trim and sanitize the values
	 * @since 2.05
	 */
	private static function get_value_for_comparision( $value ) {
		// Remove white space from hide_opt
		if ( ! is_array( $value ) ) {
			$value = trim( $value );
		}

		return wp_kses_post( $value );
	}

	public static function array_value_condition( $observed_value, $cond, $hide_opt ) {
        $m = false;
        if ( $cond == '==' ) {
            if ( is_array($hide_opt) ) {
                $m = array_intersect($hide_opt, $observed_value);
                $m = empty($m) ? false : true;
            } else {
                $m = in_array($hide_opt, $observed_value);
            }
        } else if ( $cond == '!=' ) {
            $m = ! in_array($hide_opt, $observed_value);
        } else if ( $cond == '>' ) {
            $min = min($observed_value);
            $m = $min > $hide_opt;
        } else if ( $cond == '<' ) {
            $max = max($observed_value);
            $m = $max < $hide_opt;
        } else if ( $cond == 'LIKE' || $cond == 'not LIKE' ) {
            foreach ( $observed_value as $ob ) {
                $m = strpos($ob, $hide_opt);
                if ( $m !== false ) {
                    $m = true;
                    break;
                }
            }

            if ( $cond == 'not LIKE' ) {
                $m = ( $m === false ) ? true : false;
            }
        }

        return $m;
    }

    /**
     * Replace a few basic shortcodes and field ids
     * @since 2.0
     * @return string
     */
	public static function basic_replace_shortcodes( $value, $form, $entry ) {
        if ( strpos($value, '[sitename]') !== false ) {
            $new_value = wp_specialchars_decode( FrmAppHelper::site_name(), ENT_QUOTES );
            $value = str_replace('[sitename]', $new_value, $value);
        }

        $value = apply_filters('frm_content', $value, $form, $entry);
        $value = do_shortcode($value);

        return $value;
    }

	public static function get_shortcodes( $content, $form_id ) {
        if ( FrmAppHelper::pro_is_installed() ) {
            return FrmProDisplaysHelper::get_shortcodes($content, $form_id);
        }

        $fields = FrmField::getAll( array( 'fi.form_id' => (int) $form_id, 'fi.type not' => FrmField::no_save_fields() ) );

        $tagregexp = self::allowed_shortcodes($fields);

        preg_match_all("/\[(if )?($tagregexp)\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?/s", $content, $matches, PREG_PATTERN_ORDER);

        return $matches;
    }

	public static function allowed_shortcodes( $fields = array() ) {
        $tagregexp = array(
            'editlink', 'id', 'key', 'ip',
            'siteurl', 'sitename', 'admin_email',
            'post[-|_]id', 'created[-|_]at', 'updated[-|_]at', 'updated[-|_]by',
			'parent[-|_]id',
        );

        foreach ( $fields as $field ) {
            $tagregexp[] = $field->id;
            $tagregexp[] = $field->field_key;
        }

        $tagregexp = implode('|', $tagregexp);
        return $tagregexp;
    }

	public static function replace_content_shortcodes( $content, $entry, $shortcodes ) {
        $shortcode_values = array(
           'id'     => $entry->id,
           'key'    => $entry->item_key,
           'ip'     => $entry->ip,
        );

        foreach ( $shortcodes[0] as $short_key => $tag ) {
			if ( empty( $tag ) ) {
				continue;
			}

            $atts = FrmShortcodeHelper::get_shortcode_attribute_array( $shortcodes[3][ $short_key ] );

            if ( ! empty( $shortcodes[3][ $short_key ] ) ) {
				$tag = str_replace( array( '[', ']' ), '', $shortcodes[0][ $short_key ] );
                $tags = explode(' ', $tag);
                if ( is_array($tags) ) {
                    $tag = $tags[0];
                }
            } else {
                $tag = $shortcodes[2][ $short_key ];
            }

            switch ( $tag ) {
                case 'id':
                case 'key':
                case 'ip':
                    $replace_with = $shortcode_values[ $tag ];
                break;

                case 'user_agent':
                case 'user-agent':
                    $entry->description = maybe_unserialize($entry->description);
					$replace_with = FrmEntriesHelper::get_browser( $entry->description['browser'] );
                break;

                case 'created_at':
                case 'created-at':
                case 'updated_at':
                case 'updated-at':
                    if ( isset($atts['format']) ) {
                        $time_format = ' ';
                    } else {
                        $atts['format'] = get_option('date_format');
                        $time_format = '';
                    }

                    $this_tag = str_replace('-', '_', $tag);
                    $replace_with = FrmAppHelper::get_formatted_time($entry->{$this_tag}, $atts['format'], $time_format);
                    unset($this_tag);
                break;

                case 'created_by':
                case 'created-by':
                case 'updated_by':
                case 'updated-by':
                    $this_tag = str_replace('-', '_', $tag);
					$replace_with = self::get_display_value( $entry->{$this_tag}, (object) array( 'type' => 'user_id' ), $atts );
                    unset($this_tag);
                break;

                case 'admin_email':
                case 'siteurl':
                case 'frmurl':
                case 'sitename':
                case 'get':
                    $replace_with = self::dynamic_default_values( $tag, $atts );
                break;

                default:
                    $field = FrmField::getOne( $tag );
                    if ( ! $field ) {
                        break;
                    }

                    $sep = isset($atts['sep']) ? $atts['sep'] : ', ';

                    $replace_with = FrmEntryMeta::get_meta_value( $entry, $field->id );

                    $atts['entry_id'] = $entry->id;
                    $atts['entry_key'] = $entry->item_key;

                    if ( isset($atts['show']) && $atts['show'] == 'field_label' ) {
                        $replace_with = $field->name;
                    } else if ( isset($atts['show']) && $atts['show'] == 'description' ) {
                        $replace_with = $field->description;
					} else {
						$string_value = $replace_with;
						if ( is_array( $replace_with ) ) {
							$string_value = implode( $sep, $replace_with );
						}

						if ( empty( $string_value ) && $string_value != '0' ) {
							$replace_with = '';
						} else {
							$replace_with = self::get_display_value( $replace_with, $field, $atts );
						}
					}

                    unset($field);
                break;
            }

            if ( isset($replace_with) ) {
                $content = str_replace( $shortcodes[0][ $short_key ], $replace_with, $content );
            }

            unset($atts, $conditional, $replace_with);
		}

		return $content;
    }

    /**
     * Get the value to replace a few standard shortcodes
     *
     * @since 2.0
     * @return string
     */
    public static function dynamic_default_values( $tag, $atts = array(), $return_array = false ) {
        $new_value = '';
        switch ( $tag ) {
            case 'admin_email':
                $new_value = get_option('admin_email');
                break;
            case 'siteurl':
                $new_value = FrmAppHelper::site_url();
                break;
            case 'frmurl':
                $new_value = FrmAppHelper::plugin_url();
                break;
            case 'sitename':
                $new_value = FrmAppHelper::site_name();
                break;
            case 'get':
                $new_value = self::process_get_shortcode( $atts, $return_array );
                break;
        }

        return $new_value;
    }

    /**
     * Process the [get] shortcode
     *
     * @since 2.0
     * @return string|array
     */
    public static function process_get_shortcode( $atts, $return_array = false ) {
        if ( ! isset($atts['param']) ) {
            return '';
        }

        if ( strpos($atts['param'], '&#91;') ) {
            $atts['param'] = str_replace('&#91;', '[', $atts['param']);
            $atts['param'] = str_replace('&#93;', ']', $atts['param']);
        }

        $new_value = FrmAppHelper::get_param($atts['param'], '');
        $new_value = FrmAppHelper::get_query_var( $new_value, $atts['param'] );

        if ( $new_value == '' ) {
            if ( ! isset($atts['prev_val']) ) {
                $atts['prev_val'] = '';
            }

            $new_value = isset($atts['default']) ? $atts['default'] : $atts['prev_val'];
        }

        if ( is_array($new_value) && ! $return_array ) {
            $new_value = implode(', ', $new_value);
        }

        return $new_value;
    }

	public static function get_display_value( $value, $field, $atts = array() ) {

		$value = apply_filters( 'frm_get_' . $field->type . '_display_value', $value, $field, $atts );
		$value = apply_filters( 'frm_get_display_value', $value, $field, $atts );

		return self::get_unfiltered_display_value( compact( 'value', 'field', 'atts' ) );
	}

	/**
	 * @param $atts array Includes value, field, and atts
	 */
	public static function get_unfiltered_display_value( $atts ) {
		$value = $atts['value'];
		$args = isset( $atts['atts'] ) ? $atts['atts'] : array();

		if ( is_array( $atts['field'] ) ) {
			$atts['field'] = $atts['field']['id'];
		}

		$field_obj = FrmFieldFactory::get_field_object( $atts['field'] );
		return $field_obj->get_display_value( $value, $atts );
	}

	/**
	 * Get a value from the user profile from the user ID
	 * @since 3.0
	 */
	public static function get_user_display_name( $user_id, $user_info = 'display_name', $args = array() ) {
		$defaults = array(
			'blank' => false, 'link' => false, 'size' => 96
		);

		$args = wp_parse_args($args, $defaults);

		$user = get_userdata($user_id);
		$info = '';

		if ( $user ) {
			if ( $user_info == 'avatar' ) {
				$info = get_avatar( $user_id, $args['size'] );
			} elseif ( $user_info == 'author_link' ) {
				$info = get_author_posts_url( $user_id );
			} else {
				$info = isset($user->$user_info) ? $user->$user_info : '';
			}

			if ( empty($info) && ! $args['blank'] ) {
				$info = $user->user_login;
			}
		}

		if ( $args['link'] ) {
			$info = '<a href="' .  esc_url( admin_url('user-edit.php?user_id=' . $user_id ) ) . '">' . $info . '</a>';
		}

		return $info;
	}

	/**
	 * Generate the user info attribute for displaying
	 * a value from the user ID
	 *
	 * @since 3.0
	 * @param $atts
	 *
	 * @return string
	 */
	private static function prepare_user_info_attribute( $atts ) {
		if ( isset( $atts['show'] ) ) {
			if ( $atts['show'] === 'id' ) {
				$user_info = 'ID';
			} else {
				$user_info = $atts['show'];
			}
		} else {
			$user_info = 'display_name';
		}

		return $user_info;
	}

	public static function get_field_types( $type ) {
        $single_input = array(
            'text', 'textarea', 'rte', 'number', 'email', 'url',
            'image', 'file', 'date', 'phone', 'hidden', 'time',
            'user_id', 'tag', 'password',
        );
		$multiple_input = array( 'radio', 'checkbox', 'select', 'scale', 'lookup' );
		$other_type = array( 'html', 'break' );

		$field_selection = array_merge( FrmField::pro_field_selection(), FrmField::field_selection() );

        $field_types = array();
        if ( in_array($type, $single_input) ) {
            self::field_types_for_input( $single_input, $field_selection, $field_types );
        } else if ( in_array($type, $multiple_input) ) {
            self::field_types_for_input( $multiple_input, $field_selection, $field_types );
        } else if ( in_array($type, $other_type) ) {
            self::field_types_for_input( $other_type, $field_selection, $field_types );
		} else if ( isset( $field_selection[ $type ] ) ) {
            $field_types[ $type ] = $field_selection[ $type ];
        }

		$field_types = apply_filters( 'frm_switch_field_types', $field_types, compact( 'type' ) );
        return $field_types;
    }

    private static function field_types_for_input( $inputs, $fields, &$field_types ) {
        foreach ( $inputs as $input ) {
            $field_types[ $input ] = $fields[ $input ];
            unset($input);
        }
    }

	/**
	 * Check if current field option is an "other" option
	 *
	 * @since 2.0.6
	 *
	 * @param string $opt_key
	 * @return boolean Returns true if current field option is an "Other" option
	 */
	public static function is_other_opt( $opt_key ) {
		return $opt_key && strpos( $opt_key, 'other_' ) === 0;
	}

    /**
    * Get value that belongs in "Other" text box
    *
    * @since 2.0.6
    *
    * @param array $args
    */
    public static function get_other_val( $args ) {
		$defaults = array(
			'opt_key' => 0, 'field' => array(),
			'parent' => false, 'pointer' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$opt_key = $args['opt_key'];
		$field = $args['field'];
		$parent = $args['parent'];
		$pointer = $args['pointer'];
		$other_val = '';

		// If option is an "other" option and there is a value set for this field,
		// check if the value belongs in the current "Other" option text field
		if ( ! FrmFieldsHelper::is_other_opt( $opt_key ) || ! FrmField::is_option_true( $field, 'value' ) ) {
			return $other_val;
		}

		// Check posted vals before checking saved values

		// For fields inside repeating sections - note, don't check if $pointer is true because it will often be zero
		if ( $parent && isset( $_POST['item_meta'][ $parent ][ $pointer ]['other'][ $field['id'] ] ) ) {
			if ( FrmField::is_field_with_multiple_values( $field ) ) {
				$other_val = isset( $_POST['item_meta'][ $parent ][ $pointer ]['other'][ $field['id'] ][ $opt_key ] ) ? sanitize_text_field( $_POST['item_meta'][ $parent ][ $pointer ]['other'][ $field['id'] ][ $opt_key ] ) : '';
			} else {
				$other_val = sanitize_text_field( $_POST['item_meta'][ $parent ][ $pointer ]['other'][ $field['id'] ] );
			}
			return $other_val;

		} else if ( isset( $field['id'] ) && isset( $_POST['item_meta']['other'][ $field['id'] ] ) ) {
			// For normal fields

			if ( FrmField::is_field_with_multiple_values( $field ) ) {
				$other_val = isset( $_POST['item_meta']['other'][ $field['id'] ][ $opt_key ] ) ? sanitize_text_field( $_POST['item_meta']['other'][ $field['id'] ][ $opt_key ] ) : '';
			} else {
				$other_val = sanitize_text_field( $_POST['item_meta']['other'][ $field['id'] ] );
			}
			return $other_val;
		}

		// For checkboxes
		if ( $field['type'] == 'checkbox' && is_array( $field['value'] ) ) {
			// Check if there is an "other" val in saved value and make sure the
			// "other" val is not equal to the Other checkbox option
			if ( isset( $field['value'][ $opt_key ] ) && $field['options'][ $opt_key ] != $field['value'][ $opt_key ] ) {
				$other_val = $field['value'][ $opt_key ];
			}
		} else {
			/**
			 * For radio buttons and dropdowns
			 * Check if saved value equals any of the options. If not, set it as the other value.
			 */
			foreach ( $field['options'] as $opt_key => $opt_val ) {
				$temp_val = is_array( $opt_val ) ? $opt_val['value'] : $opt_val;
				// Multi-select dropdowns - key is not preserved
				if ( is_array( $field['value'] ) ) {
					$o_key = array_search( $temp_val, $field['value'] );
					if ( isset( $field['value'][ $o_key ] ) ) {
						unset( $field['value'][ $o_key ], $o_key );
					}
				} else if ( $temp_val == $field['value'] ) {
					// For radio and regular dropdowns
					return '';
				} else {
					$other_val = $field['value'];
				}
				unset( $opt_key, $opt_val, $temp_val );
			}
			// For multi-select dropdowns only
			if ( is_array( $field['value'] ) && ! empty( $field['value'] ) ) {
				$other_val = reset( $field['value'] );
			}
		}

		return $other_val;
    }

    /**
    * Check if there is a saved value for the "Other" text field. If so, set it as the $other_val.
    * Intended for front-end use
    *
    * @since 2.0.6
    *
    * @param array $args should include field, opt_key and field name
    * @param boolean $other_opt
    * @param string $checked
    * @return string $other_val
    */
    public static function prepare_other_input( $args, &$other_opt, &$checked ) {
		//Check if this is an "Other" option
		if ( ! self::is_other_opt( $args['opt_key'] ) ) {
			return;
		}

		$other_opt = true;
		$other_args = array();

		self::set_other_name( $args, $other_args );
		self::set_other_value( $args, $other_args );

		if ( $other_args['value'] ) {
			$checked = 'checked="checked" ';
		}

        return $other_args;
    }

	/**
	 * @param array $args
	 * @param array $other_args
	 * @since 2.0.6
	 */
	private static function set_other_name( $args, &$other_args ) {
		//Set up name for other field
		$other_args['name'] = str_replace( '[]', '', $args['field_name'] );
		$other_args['name'] = preg_replace('/\[' . $args['field']['id'] . '\]$/', '', $other_args['name']);
		$other_args['name'] = $other_args['name'] . '[other]' . '[' . $args['field']['id'] . ']';

		//Converts item_meta[field_id] => item_meta[other][field_id] and
		//item_meta[parent][0][field_id] => item_meta[parent][0][other][field_id]
		if ( FrmField::is_field_with_multiple_values( $args['field'] ) ) {
			$other_args['name'] .= '[' . $args['opt_key'] . ']';
		}
	}

	/**
	 * Find the parent and pointer, and get text for "other" text field
	 * @param array $args
	 * @param array $other_args
	 *
	 * @since 2.0.6
	 */
	private static function set_other_value( $args, &$other_args ) {
		$parent = '';
		$pointer = '';

		// Check for parent ID and pointer
		$temp_array = explode( '[', $args['field_name'] );

		// Count should only be greater than 3 if inside of a repeating section
		if ( count( $temp_array ) > 3 ) {
			$parent = str_replace( ']', '', $temp_array[1] );
			$pointer = str_replace( ']', '', $temp_array[2]);
		}

		// Get text for "other" text field
		$other_args['value'] = self::get_other_val( array( 'opt_key' => $args['opt_key'], 'field' => $args['field'], 'parent' => $parent, 'pointer' => $pointer ) );
	}

	/**
	 * If this field includes an other option, show it
	 * @param $args array
	 * @since 2.0.6
	 */
	public static function include_other_input( $args ) {
        if ( ! $args['other_opt'] ) {
        	return;
		}

		$classes = array( 'frm_other_input' );
		if ( ! $args['checked'] || trim( $args['checked'] ) == '' ) {
			// hide the field if the other option is not selected
			$classes[] = 'frm_pos_none';
		}
		if ( $args['field']['type'] == 'select' && $args['field']['multiple'] ) {
			$classes[] = 'frm_other_full';
		}

		// Set up HTML ID for Other field
		$other_id = self::get_other_field_html_id( $args['field']['type'], $args['html_id'], $args['opt_key'] );

		?><input type="text" id="<?php echo esc_attr( $other_id ) ?>" class="<?php echo sanitize_text_field( implode( ' ', $classes ) ) ?>" <?php
		echo ( $args['read_only'] ? ' readonly="readonly" disabled="disabled"' : '' );
		?> name="<?php echo esc_attr( $args['name'] ) ?>" value="<?php echo esc_attr( $args['value'] ); ?>" /><?php
	}

	/**
	* Get the HTML id for an "Other" text field
	* Note: This does not affect fields in repeating sections
	*
	* @since 2.0.08
	* @param string $type - field type
	* @param string $html_id
	* @param string|boolean $opt_key
	* @return string $other_id
	*/
	public static function get_other_field_html_id( $type, $html_id, $opt_key = false ) {
		$other_id = $html_id;

		// If hidden radio field, add an opt key of 0
		if ( $type == 'radio' && $opt_key === false ) {
			$opt_key = 0;
		}

		if ( $opt_key !== false ) {
			$other_id .= '-' . $opt_key;
		}

		$other_id .= '-otext';

		return $other_id;
	}

	public static function clear_on_focus_html( $field, $display, $id = '' ) {
		if ( $display['clear_on_focus'] ) {
			echo '<span id="frm_clear_on_focus_' . esc_attr( $field['id'] . $id ) . '" class="frm-show-click">';

			if ( $display['default_blank'] ) {
				self::show_default_blank_js( $field['default_blank'] );
				echo '<input type="hidden" name="field_options[default_blank_' . esc_attr( $field['id'] ) . ']" value="' . esc_attr( $field['default_blank'] ) . '" />';
			}

			self::show_onfocus_js( $field['clear_on_focus'] );
			echo '<input type="hidden" name="field_options[clear_on_focus_' . esc_attr( $field['id'] ) . ']" value="' . esc_attr( $field['clear_on_focus'] ) . '" />';

			echo '</span>';
		}
	}

	public static function show_onfocus_js( $is_selected ) {
		$atts = array(
			'icon'        => 'frm_reload_icon',
			'message'     => $is_selected ? __( 'Clear default value when typing', 'formidable' ) : __( 'Do not clear default value when typing', 'formidable' ),
			'is_selected' => $is_selected,
		);
		self::show_icon_link_js( $atts );
	}

	public static function show_default_blank_js( $is_selected ) {
		$atts = array(
			'icon'        => 'frm_error_icon',
			'message'     => $is_selected ? __( 'Default value will NOT pass form validation', 'formidable' ) : __( 'Default value will pass form validation', 'formidable' ),
			'is_selected' => $is_selected,
		);
		self::show_icon_link_js( $atts );
	}

	public static function show_icon_link_js( $atts ) {
		$atts['icon'] .= $atts['is_selected'] ? ' ' : ' frm_inactive_icon ';
		?><a href="javascript:void(0)" class="frm_bstooltip <?php echo esc_attr( $atts['icon'] ); ?>frm_default_val_icons frm_action_icon frm_icon_font" title="<?php echo esc_attr( $atts['message'] ); ?>"></a><?php
	}

	public static function switch_field_ids( $val ) {
        global $frm_duplicate_ids;
        $replace = array();
        $replace_with = array();
        foreach ( (array) $frm_duplicate_ids as $old => $new ) {
			$replace[] = '[if ' . $old . ']';
			$replace_with[] = '[if ' . $new . ']';
			$replace[] = '[if ' . $old . ' ';
			$replace_with[] = '[if ' . $new . ' ';
			$replace[] = '[/if ' . $old . ']';
			$replace_with[] = '[/if ' . $new . ']';
			$replace[] = '[foreach ' . $old . ']';
			$replace_with[] = '[foreach ' . $new . ']';
			$replace[] = '[/foreach ' . $old . ']';
			$replace_with[] = '[/foreach ' . $new . ']';
			$replace[] = '[' . $old . ']';
			$replace_with[] = '[' . $new . ']';
			$replace[] = '[' . $old . ' ';
			$replace_with[] = '[' . $new . ' ';
            unset($old, $new);
        }
		if ( is_array( $val ) ) {
			foreach ( $val as $k => $v ) {
                $val[ $k ] = str_replace( $replace, $replace_with, $v );
                unset($k, $v);
            }
        } else {
            $val = str_replace($replace, $replace_with, $val);
        }

        return $val;
    }

    public static function get_us_states() {
        return apply_filters( 'frm_us_states', array(
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AR' => 'Arkansas', 'AZ' => 'Arizona',
            'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
            'DC' => 'District of Columbia',
            'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
            'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
            'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine','MD' => 'Maryland',
            'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
            'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
            'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
            'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
            'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
            'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
            'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
            'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        ) );
    }

    public static function get_countries() {
        return apply_filters( 'frm_countries', array(
            __( 'Afghanistan', 'formidable' ), __( 'Albania', 'formidable' ), __( 'Algeria', 'formidable' ),
            __( 'American Samoa', 'formidable' ), __( 'Andorra', 'formidable' ), __( 'Angola', 'formidable' ),
            __( 'Anguilla', 'formidable' ), __( 'Antarctica', 'formidable' ), __( 'Antigua and Barbuda', 'formidable' ),
            __( 'Argentina', 'formidable' ), __( 'Armenia', 'formidable' ), __( 'Aruba', 'formidable' ),
            __( 'Australia', 'formidable' ), __( 'Austria', 'formidable' ), __( 'Azerbaijan', 'formidable' ),
            __( 'Bahamas', 'formidable' ), __( 'Bahrain', 'formidable' ), __( 'Bangladesh', 'formidable' ),
            __( 'Barbados', 'formidable' ), __( 'Belarus', 'formidable' ), __( 'Belgium', 'formidable' ),
            __( 'Belize', 'formidable' ), __( 'Benin', 'formidable' ), __( 'Bermuda', 'formidable' ),
            __( 'Bhutan', 'formidable' ), __( 'Bolivia', 'formidable' ), __( 'Bosnia and Herzegovina', 'formidable' ),
            __( 'Botswana', 'formidable' ), __( 'Brazil', 'formidable' ), __( 'Brunei', 'formidable' ),
            __( 'Bulgaria', 'formidable' ), __( 'Burkina Faso', 'formidable' ), __( 'Burundi', 'formidable' ),
            __( 'Cambodia', 'formidable' ), __( 'Cameroon', 'formidable' ), __( 'Canada', 'formidable' ),
            __( 'Cape Verde', 'formidable' ), __( 'Cayman Islands', 'formidable' ), __( 'Central African Republic', 'formidable' ),
            __( 'Chad', 'formidable' ), __( 'Chile', 'formidable' ), __( 'China', 'formidable' ),
            __( 'Colombia', 'formidable' ), __( 'Comoros', 'formidable' ), __( 'Congo', 'formidable' ),
            __( 'Costa Rica', 'formidable' ), __( 'C&ocirc;te d\'Ivoire', 'formidable' ), __( 'Croatia', 'formidable' ),
            __( 'Cuba', 'formidable' ), __( 'Cyprus', 'formidable' ), __( 'Czech Republic', 'formidable' ),
            __( 'Denmark', 'formidable' ), __( 'Djibouti', 'formidable' ), __( 'Dominica', 'formidable' ),
            __( 'Dominican Republic', 'formidable' ), __( 'East Timor', 'formidable' ), __( 'Ecuador', 'formidable' ),
            __( 'Egypt', 'formidable' ), __( 'El Salvador', 'formidable' ), __( 'Equatorial Guinea', 'formidable' ),
            __( 'Eritrea', 'formidable' ), __( 'Estonia', 'formidable' ), __( 'Ethiopia', 'formidable' ),
            __( 'Fiji', 'formidable' ), __( 'Finland', 'formidable' ), __( 'France', 'formidable' ),
            __( 'French Guiana', 'formidable' ), __( 'French Polynesia', 'formidable' ), __( 'Gabon', 'formidable' ),
            __( 'Gambia', 'formidable' ), __( 'Georgia', 'formidable' ), __( 'Germany', 'formidable' ),
            __( 'Ghana', 'formidable' ), __( 'Gibraltar', 'formidable' ), __( 'Greece', 'formidable' ),
            __( 'Greenland', 'formidable' ), __( 'Grenada', 'formidable' ), __( 'Guam', 'formidable' ),
            __( 'Guatemala', 'formidable' ), __( 'Guinea', 'formidable' ), __( 'Guinea-Bissau', 'formidable' ),
            __( 'Guyana', 'formidable' ), __( 'Haiti', 'formidable' ), __( 'Honduras', 'formidable' ),
            __( 'Hong Kong', 'formidable' ), __( 'Hungary', 'formidable' ), __( 'Iceland', 'formidable' ),
            __( 'India', 'formidable' ), __( 'Indonesia', 'formidable' ), __( 'Iran', 'formidable' ),
            __( 'Iraq', 'formidable' ), __( 'Ireland', 'formidable' ), __( 'Israel', 'formidable' ),
            __( 'Italy', 'formidable' ), __( 'Jamaica', 'formidable' ), __( 'Japan', 'formidable' ),
            __( 'Jordan', 'formidable' ), __( 'Kazakhstan', 'formidable' ), __( 'Kenya', 'formidable' ),
            __( 'Kiribati', 'formidable' ), __( 'North Korea', 'formidable' ), __( 'South Korea', 'formidable' ),
            __( 'Kuwait', 'formidable' ), __( 'Kyrgyzstan', 'formidable' ), __( 'Laos', 'formidable' ),
            __( 'Latvia', 'formidable' ), __( 'Lebanon', 'formidable' ), __( 'Lesotho', 'formidable' ),
            __( 'Liberia', 'formidable' ), __( 'Libya', 'formidable' ), __( 'Liechtenstein', 'formidable' ),
            __( 'Lithuania', 'formidable' ), __( 'Luxembourg', 'formidable' ), __( 'Macedonia', 'formidable' ),
            __( 'Madagascar', 'formidable' ), __( 'Malawi', 'formidable' ), __( 'Malaysia', 'formidable' ),
            __( 'Maldives', 'formidable' ), __( 'Mali', 'formidable' ), __( 'Malta', 'formidable' ),
            __( 'Marshall Islands', 'formidable' ), __( 'Mauritania', 'formidable' ), __( 'Mauritius', 'formidable' ),
            __( 'Mexico', 'formidable' ), __( 'Micronesia', 'formidable' ), __( 'Moldova', 'formidable' ),
            __( 'Monaco', 'formidable' ), __( 'Mongolia', 'formidable' ), __( 'Montenegro', 'formidable' ),
            __( 'Montserrat', 'formidable' ), __( 'Morocco', 'formidable' ), __( 'Mozambique', 'formidable' ),
            __( 'Myanmar', 'formidable' ), __( 'Namibia', 'formidable' ), __( 'Nauru', 'formidable' ),
            __( 'Nepal', 'formidable' ), __( 'Netherlands', 'formidable' ), __( 'New Zealand', 'formidable' ),
            __( 'Nicaragua', 'formidable' ), __( 'Niger', 'formidable' ), __( 'Nigeria', 'formidable' ),
            __( 'Norway', 'formidable' ), __( 'Northern Mariana Islands', 'formidable' ), __( 'Oman', 'formidable' ),
            __( 'Pakistan', 'formidable' ), __( 'Palau', 'formidable' ), __( 'Palestine', 'formidable' ),
            __( 'Panama', 'formidable' ), __( 'Papua New Guinea', 'formidable' ), __( 'Paraguay', 'formidable' ),
            __( 'Peru', 'formidable' ), __( 'Philippines', 'formidable' ), __( 'Poland', 'formidable' ),
            __( 'Portugal', 'formidable' ), __( 'Puerto Rico', 'formidable' ), __( 'Qatar', 'formidable' ),
            __( 'Romania', 'formidable' ), __( 'Russia', 'formidable' ), __( 'Rwanda', 'formidable' ),
            __( 'Saint Kitts and Nevis', 'formidable' ), __( 'Saint Lucia', 'formidable' ),
            __( 'Saint Vincent and the Grenadines', 'formidable' ), __( 'Samoa', 'formidable' ),
            __( 'San Marino', 'formidable' ), __( 'Sao Tome and Principe', 'formidable' ), __( 'Saudi Arabia', 'formidable' ),
            __( 'Senegal', 'formidable' ), __( 'Serbia and Montenegro', 'formidable' ), __( 'Seychelles', 'formidable' ),
            __( 'Sierra Leone', 'formidable' ), __( 'Singapore', 'formidable' ), __( 'Slovakia', 'formidable' ),
            __( 'Slovenia', 'formidable' ), __( 'Solomon Islands', 'formidable' ), __( 'Somalia', 'formidable' ),
            __( 'South Africa', 'formidable' ), __( 'South Sudan', 'formidable' ),
            __( 'Spain', 'formidable' ), __( 'Sri Lanka', 'formidable' ),
            __( 'Sudan', 'formidable' ), __( 'Suriname', 'formidable' ), __( 'Swaziland', 'formidable' ),
            __( 'Sweden', 'formidable' ), __( 'Switzerland', 'formidable' ), __( 'Syria', 'formidable' ),
            __( 'Taiwan', 'formidable' ), __( 'Tajikistan', 'formidable' ), __( 'Tanzania', 'formidable' ),
            __( 'Thailand', 'formidable' ), __( 'Togo', 'formidable' ), __( 'Tonga', 'formidable' ),
            __( 'Trinidad and Tobago', 'formidable' ), __( 'Tunisia', 'formidable' ), __( 'Turkey', 'formidable' ),
            __( 'Turkmenistan', 'formidable' ), __( 'Tuvalu', 'formidable' ), __( 'Uganda', 'formidable' ),
            __( 'Ukraine', 'formidable' ), __( 'United Arab Emirates', 'formidable' ), __( 'United Kingdom', 'formidable' ),
            __( 'United States', 'formidable' ), __( 'Uruguay', 'formidable' ), __( 'Uzbekistan', 'formidable' ),
            __( 'Vanuatu', 'formidable' ), __( 'Vatican City', 'formidable' ), __( 'Venezuela', 'formidable' ),
            __( 'Vietnam', 'formidable' ), __( 'Virgin Islands, British', 'formidable' ),
            __( 'Virgin Islands, U.S.', 'formidable' ), __( 'Yemen', 'formidable' ), __( 'Zambia', 'formidable' ),
            __( 'Zimbabwe', 'formidable' ),
        ) );
    }

	public static function get_bulk_prefilled_opts( array &$prepop ) {
		$prepop[ __( 'Countries', 'formidable' ) ] = FrmFieldsHelper::get_countries();

        $states = FrmFieldsHelper::get_us_states();
        $state_abv = array_keys($states);
        sort($state_abv);
		$prepop[ __( 'U.S. State Abbreviations', 'formidable' ) ] = $state_abv;

        $states = array_values($states);
        sort($states);
		$prepop[ __( 'U.S. States', 'formidable' ) ] = $states;
        unset($state_abv, $states);

		$prepop[ __( 'Age', 'formidable' ) ] = array(
            __( 'Under 18', 'formidable' ), __( '18-24', 'formidable' ), __( '25-34', 'formidable' ),
            __( '35-44', 'formidable' ), __( '45-54', 'formidable' ), __( '55-64', 'formidable' ),
            __( '65 or Above', 'formidable' ), __( 'Prefer Not to Answer', 'formidable' ),
        );

		$prepop[ __( 'Satisfaction', 'formidable' ) ] = array(
            __( 'Very Satisfied', 'formidable' ), __( 'Satisfied', 'formidable' ), __( 'Neutral', 'formidable' ),
            __( 'Unsatisfied', 'formidable' ), __( 'Very Unsatisfied', 'formidable' ), __( 'N/A', 'formidable' ),
        );

		$prepop[ __( 'Importance', 'formidable' ) ] = array(
            __( 'Very Important', 'formidable' ), __( 'Important', 'formidable' ), __( 'Neutral', 'formidable' ),
            __( 'Somewhat Important', 'formidable' ), __( 'Not at all Important', 'formidable' ), __( 'N/A', 'formidable' ),
        );

		$prepop[ __( 'Agreement', 'formidable' ) ] = array(
            __( 'Strongly Agree', 'formidable' ), __( 'Agree', 'formidable' ), __( 'Neutral', 'formidable' ),
            __( 'Disagree', 'formidable' ), __( 'Strongly Disagree', 'formidable' ), __( 'N/A', 'formidable' ),
        );

		$prepop = apply_filters( 'frm_bulk_field_choices', $prepop );
    }

	/**
	 * Display a field value selector
	 *
	 * @since 2.03.05
	 *
	 * @param int $selector_field_id
	 * @param array $selector_args
	 */
    public static function display_field_value_selector( $selector_field_id, $selector_args ) {
	    $field_value_selector = FrmFieldFactory::create_field_value_selector( $selector_field_id, $selector_args );
	    $field_value_selector->display();
    }

	/**
	 * Convert a field object to a flat array
	 *
	 * @since 2.03.05
	 *
	 * @param object $field
	 *
	 * @return array
	 */
	public static function convert_field_object_to_flat_array( $field ) {
		$field_options = $field->field_options;
		$field_array = get_object_vars( $field );
		unset( $field_array['field_options'] );
		return $field_array + $field_options;
	}

	public static function field_selection() {
		_deprecated_function( __FUNCTION__, '2.0.9', 'FrmField::field_selection' );
		return FrmField::field_selection();
	}

	public static function pro_field_selection() {
		_deprecated_function( __FUNCTION__, '2.0.9', 'FrmField::pro_field_selection' );
		return FrmField::pro_field_selection();
	}

	public static function is_no_save_field( $type ) {
		_deprecated_function( __FUNCTION__, '2.0.9', 'FrmField::is_no_save_field' );
		return FrmField::is_no_save_field( $type );
	}

	public static function no_save_fields() {
		_deprecated_function( __FUNCTION__, '2.0.9', 'FrmField::no_save_fields' );
		return FrmField::no_save_fields();
	}

	public static function is_multiple_select( $field ) {
		_deprecated_function( __FUNCTION__, '2.0.9', 'FrmField::is_multiple_select' );
		return FrmField::is_multiple_select( $field );
	}

	public static function is_field_with_multiple_values( $field ) {
		_deprecated_function( __FUNCTION__, '2.0.9', 'FrmField::is_field_with_multiple_values' );
		return FrmField::is_field_with_multiple_values( $field );
	}

	public static function is_required_field( $field ) {
		_deprecated_function( __FUNCTION__, '2.0.9', 'FrmField::is_required' );
		return FrmField::is_required( $field );
	}

    public static function maybe_get_field( &$field ) {
		_deprecated_function( __FUNCTION__, '2.0.9', 'FrmField::maybe_get_field' );
		FrmField::maybe_get_field( $field );
    }

	public static function dropdown_categories( $args ) {
		_deprecated_function( __FUNCTION__, '2.02.07', 'FrmProPost::get_category_dropdown' );

		if ( FrmAppHelper::pro_is_installed() ) {
			$args['location'] = 'front';
			$dropdown = FrmProPost::get_category_dropdown( $args['field'], $args );
		} else {
			$dropdown = '';
		}

		return $dropdown;
	}
}
