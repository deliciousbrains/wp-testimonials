<?php


namespace DeliciousBrains\WPTestimonials\PostType;


use DeliciousBrains\WPTestimonials\Admin\ACF;
use DeliciousBrains\WPPostTypes\PostType\AbstractPostType;

class Testimonial extends AbstractPostType {

	protected $icon = 'format-quote';
	protected $supports = array( 'title', 'editor', 'thumbnail' );
	public static $topic_taxonomy;

	public function init() {
		parent::init();

		self::$topic_taxonomy = $this->type . '_topic';
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_filter( 'enter_title_here', array( $this, 'custom_enter_title_here' ) );

		add_filter( 'manage_edit-' . $this->type . '_sortable_columns', array( $this, 'get_sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'columns_orderby' ) );
		add_filter( 'wpseo_sitemap_exclude_taxonomy', array( $this, 'exclude_taxonomy_from_sitemap' ), 10, 2 );
	}

	public function register_taxonomies() {
		$labels = array(
			'name'              => __( 'Topics' ),
			'singular_name'     => __( 'Topic' ),
			'search_items'      => __( 'Search Topics' ),
			'all_items'         => __( 'All Topics' ),
			'parent_item'       => __( 'Parent Topic' ),
			'parent_item_colon' => __( 'Parent Topic:' ),
			'edit_item'         => __( 'Edit Topic' ),
			'update_item'       => __( 'Update Topic' ),
			'add_new_item'      => __( 'Add New Topic' ),
			'new_item_name'     => __( 'New Topic Name' ),
			'menu_name'         => __( 'Topics' ),
		);

		register_taxonomy( self::$topic_taxonomy, $this->type, array(
			'label'             => __( 'Topic' ),
			'rewrite'           => array( 'slug' => 'topic' ),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'labels'            => $labels,
		) );
	}

	/**
	 * Add custom column for testimonial text.
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public function get_columns( $columns ) {
		return array(
			'cb'                             => '<input type="checkbox" />',
			'title'                          => 'Author',
			'text'                           => 'Text',
			'taxonomy-dbi_testimonial_topic' => 'Topic',
			'testimonial_date'               => 'Testimonial Date',
			'date'                           => 'Date',
		);
	}

	/**
	 * Add custom columns to be sorted.
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public function get_sortable_columns( $columns ) {
		$columns['testimonial_date'] = 'testimonial_date';

		return $columns;
	}

	/**
	 * Custom ordering for meta fields
	 *
	 * @param $query
	 */
	public function columns_orderby( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		if ( 'testimonial_date' !== $query->get( 'orderby' ) ) {
			return;
		}

		$meta_query = array(
			'relation' => 'OR',
			array(
				'key'     => 'testimonial_date',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key' => 'testimonial_date',
			),
		);

		$query->set( 'meta_query', $meta_query );
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * Render the testimonial text for the column.
	 *
	 * @param string $column
	 */
	public function render_columns( $column ) {
		global $post;

		if ( 'text' === $column ) {
			echo substr( $post->post_content, 0, 100 ) . '&hellip;';
		}

		if ( 'testimonial_date' === $column ) {
			$date = get_post_meta( $post->ID, 'testimonial_date', true );
			echo $date ? date( 'Y/m/d', strtotime( $date ) ) : '0';
		}
	}

	/**
	 * Customize the title input placeholder for the author name.
	 *
	 * @param string $title
	 *
	 * @return string
	 */
	public function custom_enter_title_here( $title ) {
		if ( $this->type === get_current_screen()->post_type ) {
			$title = 'Enter author name here';
		}

		return $title;
	}

	/**
	 * Fetch tweets for a specific topic slug.
	 *
	 * @param string     $topic
	 * @param null $limit
	 *
	 * @return array
	 */
	public static function fetch_tweets_by_topic( $topic, $limit = null ) {
		$args = array(
			'meta_key' => 'twitter_handle',
		);

		if ( is_numeric( $limit ) ) {
			$args['posts_per_page'] = $limit;
		}

		return self::fetch_by_topic( $topic, $args );
	}

	/**
	 * Fetch all testimonials for a specific topic slug.
	 *
	 * @param string $topic Topic slug
	 *
	 * @param array  $args
	 *
	 * @return array
	 */
	public static function fetch_by_topic( $topic, $args = array() ) {
		$term = get_term_by( 'slug', $topic, self::$topic_taxonomy );

		$defaults = array(
			'posts_per_page' => - 1,
			'post_type'      => self::get_post_type(),
			'tax_query'      => array(
				array(
					'taxonomy' => self::$topic_taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			),
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$query = new \WP_Query( array_merge( $defaults, $args ) );

		return self::get_posts( $query );
	}

	/**
	 * Fetch Testimonials by an ACF repeater section for the current page.
	 *
	 * @param string $section
	 * @param bool   $post_id
	 *
	 * @return array
	 */
	public static function fetch_by_section( $section, $post_id = false ) {
		$all_testimonials = array();
		$testimonials = ACF::get_repeater_field( $section, array( 'testimonial' ), $post_id );
		foreach ( $testimonials as $testimonial ) {
			if ( is_array( $testimonial ) && isset( $testimonial['testimonial'] ) ) {
				$all_testimonials[] = new \DeliciousBrains\WPTestimonials\Model\Testimonial( $testimonial['testimonial'] );
			}
		}

		return $all_testimonials;
	}

	/**
	 * Exclude the taxonomy from the Yoast sitemap.
	 *
	 * @param $value
	 * @param $taxonomy
	 *
	 * @return bool
	 */
	public function exclude_taxonomy_from_sitemap( $value, $taxonomy ) {
		if ( self::$topic_taxonomy === $taxonomy ) {
			return true;
		}

		return $value;
	}
}