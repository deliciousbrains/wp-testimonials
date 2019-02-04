<?php
/**
 * Plugin Name: Delicious Brains WP Testimonials
 * Plugin URI: https://deliciousbrains.com
 * Description: WordPress must-use plugin for managing testimonials, importing from tweets and displaying them.
 * Author: Delicious Brains
 * Version: 1.0
 * Author URI: https://deliciousbrains.com
 **/

define( 'DBI_TESTIMONIAL_BASE_DIR', WPMU_PLUGIN_DIR . '/' . basename( __DIR__ ) );
define( 'DBI_TESTIMONIAL_PLACEHOLDER_URL', WPMU_PLUGIN_URL . '/' . basename( __DIR__ ) . '/assets/images/dbi-testimonial-default-avatar.png' );

( new \DeliciousBrains\WPTestimonials\PostType\Testimonial() )->init();

if ( is_admin() ) {
	( new \DeliciousBrains\WPTestimonials\Admin\ACF() )->init();

	if ( defined( 'DBI_TWITTER_CONSUMER_KEY' ) && defined( 'DBI_TWITTER_CONSUMER_SECRET' ) && defined( 'DBI_TWITTER_ACCESS_TOKEN' ) && defined( 'DBI_TWITTER_ACCESS_TOKEN_SECRET' ) ) {
		( new \DeliciousBrains\WPTestimonials\Admin\Page\TestimonialImport() )->init();
	}
}
