<?php

class o2_Unreplied_Posts {
	function __construct() {
		add_action( 'init', array( $this, 'register_query_var' ) );
		add_filter( 'o2_sanitized_query_vars', array( $this, 'o2_sanitized_query_vars' ) );
		add_filter( 'posts_where', array( $this, 'posts_where' ) );
		add_filter( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'infinite_scroll_query_args', array( $this, 'infinite_scroll_query_args' ) );
		add_filter( 'infinite_scroll_allowed_vars', array( $this, 'infinite_scroll_allowed_vars' ) );
		add_filter( 'infinite_scroll_ajax_url', array( $this, 'infinite_scroll_ajax_url' ) );
		add_filter( 'o2_page_title', array( $this, 'page_title' ) );
	}

	function register_query_var() {
		global $wp;
		$wp->add_query_var( 'replies' );
	}

	function o2_sanitized_query_vars( $vars ) {
		$vars['replies'] = get_query_var( 'replies' );
		return $vars;
	}

	function posts_where( $where ) {
		global $wpdb;

		// Look for our own version, and for the IS version
		if ( 'none' === get_query_var( 'replies' ) || ( !empty( $_GET['query_args']['replies'] ) && 'none' === $_GET['query_args']['replies'] ) ) {
			$where .= " AND {$wpdb->posts}.comment_count < 1 AND ( {$wpdb->posts}.post_title NOT LIKE 'x-post%' AND {$wpdb->posts}.post_content NOT LIKE 'x-post%' AND {$wpdb->posts}.post_content NOT LIKE 'x-comment%' )";
		}

		return $where;
	}

	function pre_get_posts( $query ) {
		if ( 'none' === get_query_var( 'replies' ) || ( !empty( $_GET['query_args']['replies'] ) && 'none' === $_GET['query_args']['replies'] ) ) {
			// Manipulating WP_Query directly like this isn't great, but it works
			$query->query_vars['ignore_sticky_posts'] = true;
		}

		return $query;
	}

	function infinite_scroll_query_args( $query_args ) {
		if ( ! is_array( $query_args ) )
			return $query_args;

		// IS passes query args around inside its own parameter name
		if ( isset( $_GET['query_args']['replies'] ) && 'none' === $_GET['query_args']['replies'] ) {
			$query_args['replies'] = 'none';
		}
		return $query_args;
	}

	function infinite_scroll_allowed_vars( $vars ) {
		if ( ! is_array( $vars ) )
			return $vars;

		$vars[] = 'replies';
		return $vars;
	}

	function infinite_scroll_ajax_url( $ajax_url ) {
		if ( isset( $_GET['replies'] ) && 'none' == $_GET['replies'] ) {
			$ajax_url = add_query_arg( array( 'replies' => 'none' ), $ajax_url );
		}
		return $ajax_url;
	}

	function page_title( $page_title ) {
		$replies = get_query_var( 'replies' );
		if ( "none" === $replies ) {
			$page_title = __( 'No Replies', 'o2' );
		}
		return $page_title;
	}
}

new o2_Unreplied_posts;
