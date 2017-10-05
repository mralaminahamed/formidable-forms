<?php
/**
 * @since 2.02.12
 */

class FrmShortcodeHelper {

	/**
	 * Get the shortcode attributes in key/value pairs from a string
	 *
	 * @since 2.02.12
	 * @param string $text
	 *
	 * @return array
	 */
	public static function get_shortcode_attribute_array( $text ) {
		$atts = array();
		if ( $text !== '' ) {
			$atts = shortcode_parse_atts( $text );
		}

		if ( ! is_array( $atts ) ) {
			$atts = array();
		}

		return $atts;
	}

	/**
	 * Get the name of the shortcode from the regEx
	 *
	 * @since 3.0
	 * @param array $shortcodes
	 * @param int $short_key The position in the shortcodes array
	 * @param array $args
	 *
	 * @return string
	 */
	public static function get_shortcode_tag( $shortcodes, $short_key, $args ) {
		$args = wp_parse_args( $args, array( 'conditional' => false, 'conditional_check' => false, 'foreach' => false ) );
		if ( ( $args['conditional'] || $args['foreach'] ) && ! $args['conditional_check'] ) {
			$args['conditional_check'] = true;
		}

		$prefix = '';
		if ( $args['conditional_check'] ) {
			if ( $args['conditional'] ) {
				$prefix = 'if ';
			} else if ( $args['foreach'] ) {
				$prefix = 'foreach ';
			}
		}

		$with_tags = $args['conditional_check'] ? 3 : 2;
		if ( ! empty( $shortcodes[ $with_tags ][ $short_key ] ) ) {
			$tag = str_replace( '[' . $prefix, '', $shortcodes[0][ $short_key ] );
			$tag = str_replace(']', '', $tag);
			$tags = explode(' ', $tag);
			if ( is_array($tags) ) {
				$tag = $tags[0];
			}
		} else {
			$tag = $shortcodes[ $with_tags - 1 ][ $short_key ];
		}

		return $tag;
	}
}
