<?php


namespace DeliciousBrains\WPTestimonials\Model;

class Attachment {

	/**
	 * Insert an attachment from an URL address.
	 *
	 * @param  String $url
	 * @param  String $filename
	 * @param  Int    $parent_post_id
	 *
	 * @return Int    Attachment ID
	 */
	public static function create_from_url( $url, $filename, $parent_post_id = null ) {
		if ( ! class_exists( 'WP_Http' ) ) {
			include_once( ABSPATH . WPINC . '/class-http.php' );
		}
		$http     = new \WP_Http();
		$response = $http->request( $url );
		if ( $response['response']['code'] != 200 ) {
			return false;
		}
		$upload = wp_upload_bits( basename( $url ), null, $response['body'] );
		if ( ! empty( $upload['error'] ) ) {
			return false;
		}

		$file_path        = $upload['file'];
		$file_name        = basename( $file_path );
		$file_type        = wp_check_filetype( $file_name, null );
		$attachment_title = sanitize_file_name( $filename );
		$wp_upload_dir    = wp_upload_dir();
		$post_info        = array(
			'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
			'post_mime_type' => $file_type['type'],
			'post_title'     => $attachment_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		// Create the attachment
		$attach_id = wp_insert_attachment( $post_info, $file_path, $parent_post_id );
		// Include image.php
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		// Define attachment metadata
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
		// Assign metadata to attachment
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return $attach_id;
	}
}