<?php
namespace DeliciousBrains\WPTestimonials\Admin;

use DeliciousBrains\WPTestimonials\PostType\Testimonial;

class ACF {

	public function init() {
		add_action( 'admin_init', array( $this, 'load_field_group_config' ) );
		add_filter( 'acf/fields/post_object/result/name=testimonial', array( $this, 'add_testimonial_details_to_post_object_display' ), 10, 4 );
	}

	/**
	 * Load field group config from PHP files.
	 */
	public function load_field_group_config() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		if ( ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		$post_type = Testimonial::get_post_type();

		foreach ( glob( DBI_TESTIMONIAL_BASE_DIR . '/config/*.php' ) as $file ) {
			require_once $file;
		}
	}

	/**
	 * Add testimonial topic and the testimonial content to be displayed in the ACF field admin.
	 *
	 * @param $title
	 * @param $post
	 * @param $field
	 * @param $post_id
	 *
	 * @return string
	 */
	public function add_testimonial_details_to_post_object_display( $title, $post, $field, $post_id ) {
		$topics = wp_get_post_terms( $post->ID, Testimonial::$topic_taxonomy );

		$topic = '';
		if ( ! empty( $topics ) ) {
			$topic = ' (' . $topics[0]->name . ')';
		}

		return $title . $topic . ' "' . wp_trim_words( $post->post_content, 10 ) . '"';
	}

	/**
	 * ACF frontend helper to get repeater data without the need for ACF to be active.
	 *
	 * @param string   $key
	 * @param array    $sub_fields
	 * @param bool|int $post_id
	 *
	 * @return array
	 */
	public static function get_repeater_field( $key, $sub_fields = array(), $post_id = false ) {
		if ( ! $post_id ) {
			global $post;
			$post_id = $post->ID;
		}

		$data = array();

		$repeater_count = get_post_meta( $post_id, $key, true );
		if ( ! $repeater_count || 0 === $repeater_count ) {
			return $data;
		}

		for ( $i = 0; $i < $repeater_count; $i ++ ) {
			foreach ( $sub_fields as $sub_field_key ) {
				$sub_field                = get_post_meta( $post_id, $key . '_' . $i . '_' . $sub_field_key, true );
				$data[][ $sub_field_key ] = $sub_field;
			}
		}

		return $data;
	}
}