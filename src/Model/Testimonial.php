<?php

namespace DeliciousBrains\WPTestimonials\Model;

use DeliciousBrains\WPPostTypes\Model\Post;

class Testimonial extends Post {

	/**
	 * Testimonial constructor.
	 *
	 * @param mixed $data
	 */
	public function __construct( $data = null ) {
		parent::__construct( $data );
		if ( is_null( $this->post ) ) {
			return;
		}

		$this->name         = $this->title();
		$this->text         = $this->post_content;
		$this->avatar_url   = $this->avatar( DBI_TESTIMONIAL_PLACEHOLDER_URL );
		$this->organization = $this->meta( 'organization', '' );
		$this->organization = $this->meta( 'organization', '' );

		$this->link_text = '';
		$handle          = $this->meta( 'twitter_handle' );
		if ( $handle ) {
			$this->twitter_handle = $handle;
			$this->tweet_url      = $this->meta( 'original_url' );
		} else {
			$this->link_text = $this->get_link_text();
		}
	}

	/**
	 * Get avatar or if not set return placeholder.
	 *
	 * @return string
	 */
	protected function avatar( $placeholder_url ) {

		$featured_url = $this->featured_image_url();

		return $featured_url ? $featured_url : $placeholder_url;
	}

	/**
	 * Get the text for the testimonial link
	 *
	 * @return string
	 */
	protected function get_link_text() {
		if ( ! empty( $this->organization ) ) {
			// Use supplied link text
			return $this->organization;
		}

		// Make URL nice for display
		$url = preg_replace( '(^https?://)', '', $this->url );
		$url = untrailingslashit( $url );

		if ( false === strpos( $this->url, 'twitter.com' ) ) {
			return $url;
		}

		preg_match( "|https?://(www\.)?twitter\.com/(#!/)?@?([^/]*)|", $this->url, $matches );

		if ( isset( $matches[3] ) ) {
			return '@' . $matches[3];
		}

		return $url;
	}

	/**
	 * @param string $name
	 * @param string $text
	 * @param null   $topic
	 *
	 * @return int|\WP_Error
	 */
	public static function create( $name, $text, $topic = null ) {
		$post_id = parent::create( $name, $text );

		if ( $topic ) {
			// Topic Taxonomy
			wp_set_object_terms( $post_id, $topic, \DeliciousBrains\WPTestimonials\PostType\Testimonial::$topic_taxonomy, true );
		}

		return $post_id;
	}

	/**
	 * @param object $tweet
	 * @param null   $topic
	 *
	 * @return int|\WP_Error
	 */
	public static function create_tweet( $tweet, $topic = null ) {
		$name = $tweet->user->name;
		$text = str_replace( 'Wordpress', 'WordPress', $tweet->get_text() );

		$post_id = self::create( $name, $text, $topic );

		// Meta
		update_field( 'testimonial_date', date( 'Ymd', strtotime( $tweet->created_at ) ), $post_id );
		update_field( 'twitter_handle', $tweet->user->screen_name, $post_id );
		update_field( 'original_url', sprintf( 'https://twitter.com/%s/status/%s', $tweet->user->screen_name, $tweet->id_str ), $post_id );

		// Avatar
		$avatar_url           = $tweet->get_avatar_url();
		$avatar_attachment_id = Attachment::create_from_url( $avatar_url, $tweet->user->name, $post_id );
		add_post_meta( $post_id, '_thumbnail_id', $avatar_attachment_id );

		return $post_id;
	}
}