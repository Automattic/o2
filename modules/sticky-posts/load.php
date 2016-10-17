<?php
/**
 * @package o2
 * @subpackage o2_Sticky_Posts
 */

if ( ! class_exists( 'o2_Sticky_Posts' ) ) {
class o2_Sticky_Posts extends o2_API_Base {

	function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );

		// Actions
		add_action( 'o2_callback_o2_sticky_posts', array( $this, 'callback' ) );
		add_action( 'init', array( $this, 'register_post_action_states' ) );

		// Filters
		add_filter( 'o2_options', array( $this, 'get_options' ) );
		add_filter( 'o2_filter_post_actions', array( $this, 'add_post_action' ), 10, 2 );
		add_filter( 'o2_post_fragment', array( $this, 'get_post_fragment' ), 10, 2 );

		// Options
		$o2_options = get_option( 'o2_options' );
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'o2-extend-sticky-posts-models-extend-post', plugins_url( 'modules/sticky-posts/js/models/extend-post.js', O2__FILE__ ), array( 'o2-cocktail' ) );
		wp_enqueue_script( 'o2-extend-sticky-posts-collections-extend-posts', plugins_url( 'modules/sticky-posts/js/collections/extend-posts.js', O2__FILE__ ), array( 'o2-cocktail', 'o2-extend-sticky-posts-models-extend-post' ) );
		wp_enqueue_script( 'o2-extend-sticky-posts-views-extend-post', plugins_url( 'modules/sticky-posts/js/views/extend-post.js', O2__FILE__ ), array( 'o2-cocktail', 'o2-extend-sticky-posts-models-extend-post' ) );
		wp_enqueue_script( 'o2-extend-sticky-posts-views-extend-posts', plugins_url( 'modules/sticky-posts/js/views/extend-posts.js', O2__FILE__ ), array( 'o2-cocktail', 'o2-extend-sticky-posts-collections-extend-posts' ) );
	}

	function enqueue_style() {
		wp_register_style( 'o2-extend-sticky-posts', plugins_url( 'modules/sticky-posts/css/style.css', O2__FILE__ ) );
		wp_style_add_data( 'o2-extend-sticky-posts', 'rtl', 'replace' );
		wp_enqueue_style( 'o2-extend-sticky-posts' );
	}

	/**
	 * Add sticky states to the o2 options array
	 */
	function get_options( $options ) {
		$sticky = array(
			'cssClass'       => 'sticky',
			'sticky'         => __( 'Unstick Post from Home', 'o2' ),
			'stickyTitle'    => __( 'Unstick Post from Home', 'o2' ),
			'unsticky'       => __( 'Stick Post to Home', 'o2' ),
			'unstickyTitle'  => __( 'Stick Post to Home', 'o2' ),
		);
		$options['options']['stickyPosts'] = $sticky;
		return $options;
	}

	/**
	 * Add sticky meta for each post to the o2 post fragment
	 */
	function get_post_fragment( $fragment, $post_id ) {
		$post_meta = $fragment['postMeta'];
		$post_meta['isSticky'] = is_sticky( $post_id );
		$fragment['postMeta'] = $post_meta;

		return $fragment;
	}

	/**
	 *
	 */
	function register_post_action_states() {
		o2_register_post_action_states( 'stickyposts',
			array(
				'normal' => array(
					'shortText' => __( 'Stick post to home', 'o2' ),
					'title' => __( 'Stick post to home', 'o2' ),
					'classes' => array(),
					'genericon' => 'genericon-pinned',
					'nextState' => 'sticky'
				),
				'sticky' => array(
					'shortText' => __( 'Unstick post from home', 'o2' ),
					'title' => __( 'Unstick post from home', 'o2' ),
					'classes' => array( 'sticky' ),
					'genericon' => 'genericon-pinned',
					'nextState' => 'normal'
				)
			)
		);
	}

	/**
	 * Add a link for changing the post sticky to the o2 post actions
	 *
	 * @param array The current o2 post actions
	 * @return array The filtered o2 post actions
	 */
	function add_post_action( $actions, $post_ID ) {
		// Do not show sticky post action on pages - return immediately
		if ( is_page() ) {
			return $actions;
		}

		if ( current_user_can( 'edit_others_posts' ) ) {
			$actions[41] = array(
				'action' => 'stickyposts',
				'href' => '#',
				'classes' => array( 'o2-sticky-link' ),
				'rel' => false,
				'initialState' => is_sticky( $post_ID ) ? 'sticky' : 'normal'
			);
		}

		return $actions;
	}

	/**
	 * Main AJAX callback
	 *
	 * @param object The post fragment object
	 */
	function callback( $post_data ) {
		if ( ! property_exists( $post_data, 'postID' ) || ! property_exists( $post_data, 'isSticky' ) )
			self::die_failure( 'invalid_message',  __( 'Insufficient information provided.', 'o2' ) );

		$post = get_post( absint( $post_data->postID ) );
		if ( ! $post )
			self::die_failure( 'post_not_found', __( 'Post not found.', 'o2' ) );

		if ( ! current_user_can( 'edit_others_posts' ) )
			self::die_failure( 'cannot_edit_post_sticky', __( 'You are not allowed to stick or unstick this post.', 'o2' ) );

		if ( $post_data->isSticky )
			stick_post( $post->ID );
		else
			unstick_post( $post->ID );

		// Bump the post to make it update in polling
		o2_Fragment::bump_post( $post->ID );

		$retval = array(
			'isSticky' => is_sticky( $post->ID ),
		);
		self::die_success( $retval );
	}
} }

function o2_sticky_posts() {
	new o2_Sticky_Posts();
}
add_action( 'o2_loaded', 'o2_sticky_posts' );
