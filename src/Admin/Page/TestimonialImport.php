<?php

namespace DeliciousBrains\WPTestimonials\Admin\Page;

use DeliciousBrains\WPTestimonials\Admin\TestimonialImporter;
use DeliciousBrains\WPTestimonials\PostType\Testimonial;

class TestimonialImport {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_import_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_import' ) );
	}

	public function add_import_menu() {
		add_submenu_page( 'edit.php?post_type=' . Testimonial::get_post_type(), 'Import', 'Import', 'manage_options', 'import', array(
			$this,
			'render_import_page',
		) );
	}

	public function render_import_page() {
		?>
		<form action="<?php echo admin_url( 'edit.php?post_type=' . Testimonial::get_post_type() . '&page=import&action=import' ); ?>" method="post">
			<div class="wrap">
				<h2>Import Tweets</h2>
				<div>
					<label for="tweet_urls">Enter one tweet URL per line, with a maximum of 50 tweets.</label>
					<br>
					<textarea name="tweet_urls" title="tweet_urls" cols="30" rows="5" style="width: 450px; height: 200px"></textarea>
					<br><label for="topic">Topic</label><br>
					<?php wp_dropdown_categories( array( 'taxonomy'   => Testimonial::$topic_taxonomy,
					                                     'hide_empty' => false,
					                                     'show_option_all' => 'Select Topic',
					) ); ?>
					<?php wp_nonce_field( 'import_tweets' ); ?>
					<br><br><input type="submit" class="button button-primary" value="Import" />
				</div>
			</div>
		</form>
		<?php
	}

	public function handle_import() {
		if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) ) {
			return;
		}

		if ( Testimonial::get_post_type() !== filter_input( INPUT_GET, 'post_type' ) || 'import' !== filter_input( INPUT_GET, 'action' ) ) {
			return;
		}

		if ( ! check_admin_referer( 'import_tweets' ) ) {
			return;
		}

		$tweets = filter_input( INPUT_POST, 'tweet_urls' );
		if ( empty ( $tweets ) ) {
			return;
		}

		$tweets = array_map( function ( $tweet ) {
			$parts = explode( '?', $tweet );

			return trim( $parts[0] );
		}, explode( PHP_EOL, $tweets ) );

		$topic_id = filter_input( INPUT_POST, 'cat' );
		$topic    = null;
		if ( $topic_id ) {
			$topic = get_term( $topic_id, Testimonial::$topic_taxonomy );
			$topic = $topic->slug;
		}

		$importer = new TestimonialImporter( DBI_TWITTER_CONSUMER_KEY, DBI_TWITTER_CONSUMER_SECRET, DBI_TWITTER_ACCESS_TOKEN, DBI_TWITTER_ACCESS_TOKEN_SECRET );
		$importer->bulk_import_tweet_urls( $tweets, $topic );

		wp_redirect( admin_url( 'edit.php?post_type=' . Testimonial::get_post_type() ) );
	}
}