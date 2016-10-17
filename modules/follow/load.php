<?php
/**
 * @package o2
 * @subpackage o2_Follow
 */

if ( ! class_exists( 'o2_Follow' ) ) {
class o2_Follow extends o2_API_Base {

	function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );

		// Filters
		add_filter( 'o2_options', array( $this, 'get_options' ) );
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'o2-extend-follow-models-post', plugins_url( 'modules/follow/js/models/post.js', O2__FILE__ ), array( 'o2-cocktail', 'o2-models-post', 'o2-notifications' ) );
		wp_enqueue_script( 'o2-extend-follow-views-comment', plugins_url( 'modules/follow/js/views/comment.js', O2__FILE__ ), array( 'o2-cocktail', 'o2-views-comment' ) );
		wp_enqueue_script( 'o2-extend-follow-views-post', plugins_url( 'modules/follow/js/views/post.js', O2__FILE__ ), array( 'o2-cocktail', 'o2-extend-follow-models-post' ) );
	}

	function enqueue_style() {
		wp_register_style( 'o2-follow', plugins_url( 'modules/follow/css/style.css', O2__FILE__ ) );
		wp_style_add_data( 'o2-follow', 'rtl', 'replace' );
		wp_enqueue_style( 'o2-follow' );
	}

	/**
	 * Add follow strings and options to the o2 options array
	 */
	function get_options( $options ) {
		$localizations = array(
			'follow'           => __( 'Follow', 'o2' ),
			'followComments'   => __( 'Follow comments', 'o2' ),
			'unfollow'         => __( 'Unfollow', 'o2' ),
			'unfollowComments' => __( 'Unfollow comments', 'o2' ),
			'followError'      => __( 'There was a problem updating your following preferences.', 'o2' ),
			'followingAll'     => __( 'Following all', 'o2' ),
			'followingAllComments' => __( 'You are already following all comments on this site.', 'o2' )
		);
		$localizations = array_merge( $options['strings'], $localizations );
		$options['strings'] = $localizations;

		if ( ! isset( $options['options']['followingBlog'] ) ) {
			$options['options']['followingBlog'] = false;
		}

		if ( ! isset( $options['options']['followingAllComments'] ) ) {
			$options['options']['followingAllComments'] = false;
		}

		return $options;
	}
} }

function o2_follow() {
	new o2_Follow();
}
add_action( 'o2_loaded', 'o2_follow' );
