<?php


namespace DeliciousBrains\WPTestimonials\Admin;

use Abraham\TwitterOAuth\TwitterOAuth;
use DeliciousBrains\WPTestimonials\Model\Attachment;
use DeliciousBrains\WPTestimonials\Model\Tweet;
use DeliciousBrains\WPTestimonials\PostType\Testimonial as Testimonials;
use DeliciousBrains\WPTestimonials\Model\Testimonial;

class TestimonialImporter {

	protected $twitter;

	protected $existing_testimonials = array();

	/**
	 * TestimonialImporter constructor.
	 *
	 * @param $twitter_consumer_key
	 * @param $twitter_consumer_secret
	 * @param $twitter_access_token
	 * @param $twitter_access_token_secret
	 */
	public function __construct( $twitter_consumer_key, $twitter_consumer_secret, $twitter_access_token, $twitter_access_token_secret ) {
		$this->twitter               = new TwitterOAuth( DBI_TWITTER_CONSUMER_KEY, DBI_TWITTER_CONSUMER_SECRET, DBI_TWITTER_ACCESS_TOKEN, DBI_TWITTER_ACCESS_TOKEN_SECRET );
		$this->existing_testimonials = Testimonials::all();
	}

	protected function sanitize_text( $text ) {
		return str_replace( 'Wordpress', 'WordPress', $text );
	}


	/**
	 * Get a batch of Tweets from Twitter
	 *
	 * @param array $urls
	 * @param bool  $get_replies
	 *
	 * @return array|mixed
	 */
	protected function get_tweets( $urls, $get_replies = true ) {
		$hash = 'dbi_tweets_' . md5( serialize( $urls ) );

		$tweets = get_site_transient( $hash );

		if ( $tweets ) {
			return $this->prepare_tweets( json_decode( $tweets ) );
		}

		$ids_dont_get_parent = array();

		if ( preg_match( '@^[0-9]+$@', $urls[0] ) ) {
			$ids = $urls;
		} else {
			$ids = array();
			foreach ( $urls as $url ) {
				$id    = substr( $url, strrpos( $url, '/' ) + 1 );
				$ids[] = $id;

				if ( '!' == substr( $url, 0, 1 ) ) {
					$ids_dont_get_parent[] = $id;
				}
			}
		}

		$ids_string = implode( ',', $ids );


		$args = array( 'id' => $ids_string, 'tweet_mode' => 'extended' );

		$tweets = $this->twitter->post( 'statuses/lookup', $args );

		if ( ! $tweets || isset( $tweets->errors ) ) {
			error_log( 'dbi_get_tweets: No tweets returned' );
			error_log( print_r( $tweets, true ) );

			return false;
		}

		// Stupid Twitter API brings back tweets in different order
		$sorted_tweets = array();
		foreach ( $ids as $id ) {
			foreach ( $tweets as $tweet ) {
				if ( $id == $tweet->id ) {
					$sorted_tweets[] = $tweet;
					continue;
				}
			}
		}
		$tweets = $sorted_tweets;

		if ( $get_replies ) {
			$tweet_indexes = array();
			$reply_ids     = array();
			foreach ( $tweets as $i => $tweet ) {
				if ( in_array( $tweet->id, $ids_dont_get_parent ) ) {
					continue;
				}

				if ( $tweet->in_reply_to_status_id ) {
					$reply_id                   = $tweet->in_reply_to_status_id;
					$tweet_indexes[ $reply_id ] = $i;
					$reply_ids[]                = $reply_id;
				}
			}

			if ( $reply_ids ) {
				$replies = $this->get_tweets( $reply_ids, false );

				if ( $replies ) {
					foreach ( $replies as $reply ) {
						$i                         = $tweet_indexes[ $reply->id ];
						$tweets[ $i ]->in_reply_to = $reply;
					}
				}
			}
		}

		// Use json_encode to handle encoding emojis
		set_site_transient( $hash, json_encode( $tweets ) );

		return $this->prepare_tweets( $tweets );
	}

	/**
	 * @param $tweets
	 *
	 * @return mixed
	 */
	protected function prepare_tweets( $tweets ) {
		foreach ( $tweets as $key => $tweet ) {
			$tweets[ $key ] = new Tweet( $tweet );
		}

		return $tweets;
	}

	/**
	 * Import a non-tweet testimonial.
	 *
	 * @param array $testimonial
	 *
	 * @return bool|int|\WP_Error
	 */
	public function import_testimonial( $testimonial ) {
		$name    = $testimonial['customer'];
		$text    = $this->sanitize_text( $testimonial['testimonial'] );
		$post_id = $this->testimonial_exists( $name, $text );

		if ( $post_id ) {
			return $post_id;
		}

		$post_id = Testimonial::create( $name, $text, $testimonial['product'] );

		$this->existing_testimonials[] = get_post( $post_id );

		if ( isset( $testimonial['url'] ) ) {
			update_field( 'url', $testimonial['url'], $post_id );
		}
		if ( isset( $testimonial['link_text'] ) ) {
			update_field( 'organization', $testimonial['link_text'], $post_id );
		}

		// Avatar
		if ( isset( $testimonial['avatar'] ) ) {
			$avatar_url = $testimonial['avatar'];
			if ( false !== strpos( $avatar_url, 'gravatar' ) ) {
				$parts      = explode( '?', $avatar_url );
				$avatar_url = $parts[0] . '.jpg';
			}
			$avatar_attachment_id = Attachment::create_from_url( $avatar_url, $testimonial['customer'], $post_id );
			add_post_meta( $post_id, '_thumbnail_id', $avatar_attachment_id );
		}

		return $post_id;
	}

	/**
	 * @param array       $urls
	 * @param string|null $topic
	 */
	public function bulk_import_tweet_urls( $urls, $topic = null ) {
		$chunks = array_chunk( $urls, 50 );
		foreach ( $chunks as $chunk ) {
			$this->import_tweets( $chunk, $topic );
		}
	}

	/**
	 * @param string $name
	 * @param string $text
	 *
	 * @return bool|integer
	 */
	protected function testimonial_exists( $name, $text ) {
		foreach ( $this->existing_testimonials as $testimonial ) {
			if ( $testimonial->post_title == $name && $testimonial->post_content == $text ) {
				return $testimonial->ID;
			}
		}

		return false;
	}

	/**
	 * Import multiple tweet URLs as Testimonials for a specific topic
	 *
	 * @param array       $urls
	 * @param string|null $topic
	 *
	 * @return array
	 */
	public function import_tweets( $urls, $topic = null ) {
		$tweets = $this->get_tweets( $urls );

		$testimonials = array();
		foreach ( $tweets as $tweet ) {
			$name    = $tweet->user->name;
			$text    = $this->sanitize_text( $tweet->get_text( $tweet ) );
			$post_id = $this->testimonial_exists( $name, $text );

			if ( $post_id ) {
				$testimonials[] = $post_id;
				continue;
			}

			$post_id = Testimonial::create_tweet( $tweet, $topic );

			$this->existing_testimonials[] = get_post( $post_id );

			$testimonials[] = $post_id;
		}

		return $testimonials;
	}
}