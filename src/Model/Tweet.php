<?php


namespace DeliciousBrains\WPTestimonials\Model;


class Tweet {

	/**
	 * Tweet constructor.
	 *
	 * @param object $tweet
	 */
	public function __construct( $tweet ) {
		$sourceReflection = new \ReflectionObject( $tweet );
		$sourceProperties = $sourceReflection->getProperties();
		foreach ( $sourceProperties as $sourceProperty ) {
			$name          = $sourceProperty->getName();
			$this->{$name} = $tweet->$name;
		}
	}

	public function get_text() {
		$text = $this->full_text;
		$text = preg_replace( '@:? https?://[^\s]+@', '', $text );

		// Trim @replies from beginning of tweet content
		if ( preg_match( '/^(@[^\s]+ )(@[^\s]+ )+/', $text, $matches ) ) {
			$text = str_replace( $matches[0], '', $text );
		}

		$text = ucfirst( $text );

		return $text;
	}

	public function get_avatar_url( $size = '200x200' ) {
		$url = $this->user->profile_image_url_https;

		return str_replace( '_normal', '_' . $size, $url );
	}

}