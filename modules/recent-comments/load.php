<?php

class o2_Recent_Comments {
	function __construct() {
		// Scripts and styles
		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		// Actions
		add_action( 'init', array( $this, 'register_query_var' ) );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

		// Filters
		add_filter( 'posts_clauses', array( $this, 'posts_clauses' ), 10, 2 );
		add_filter( 'o2_page_title', array( $this, 'page_title' ) );

		// Polling
		add_filter( 'o2_sanitized_query_vars', array( $this, 'o2_sanitized_query_vars' ) );

		// Infinite Scroll
		add_filter( 'infinite_scroll_ajax_url', array( $this, 'infinite_scroll_ajax_url' ) );
		add_filter( 'infinite_scroll_query_args', array( $this, 'infinite_scroll_query_args' ) );
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'o2-extend-recent-comments-collections-posts', plugins_url( 'modules/recent-comments/js/collections/posts.js', O2__FILE__ ), array( 'o2-cocktail' ) );
	}

	public function register_query_var() {
		global $wp;
		$wp->add_query_var( 'o2_recent_comments' );
	}

	public function o2_sanitized_query_vars( $vars ) {
		if ( isset( $_GET['o2_recent_comments'] ) ) {
			$vars['o2_recent_comments'] = true;
		}
		return $vars;
	}

	public function posts_clauses( $clauses, $wp_query ) {
		global $wpdb;

		if ( isset( $wp_query->query_vars['o2_recent_comments'] ) ) {
			// $this->request = $old_request = "SELECT $found_rows $distinct $fields FROM $wpdb->posts $join WHERE 1=1 $where $groupby $orderby $limits";
			$clauses['fields'] = "$wpdb->posts.*, MAX($wpdb->comments.comment_date_gmt) cdate";
			$clauses['join'] = "RIGHT JOIN $wpdb->comments ON (ID = comment_post_id)";

			// Exclude xposts from recent comments queries
			$clauses['where'] = "AND ( $wpdb->posts.post_title NOT LIKE 'x-post%' AND $wpdb->posts.post_content NOT LIKE 'x-post%' AND $wpdb->posts.post_content NOT LIKE 'x-comment%' )" .
				"AND $wpdb->comments.comment_approved = '1'" . $clauses['where'];
			$clauses['groupby'] = "ID";
			$clauses['orderby'] = "cdate DESC";
		}
		return $clauses;
	}

	public function pre_get_posts( $query ) {
		if ( isset( $_GET['o2_recent_comments'] ) ) {
			$query->query_vars['ignore_sticky_posts'] = true;
		}
	}

	public function infinite_scroll_ajax_url( $ajax_url ) {
		if ( isset( $_GET['o2_recent_comments'] ) ) {
			$ajax_url = add_query_arg( array( 'o2_recent_comments' => true ), $ajax_url );
		}
		return $ajax_url;
	}

	public function infinite_scroll_query_args( $query_args ) {
		if ( ! is_array( $query_args ) ) {
			return $query_args;
		}

		if ( isset( $_GET['o2_recent_comments'] ) ) {
			$query_args['o2_recent_comments'] = true;
		}
		return $query_args;
	}

	function page_title( $page_title ) {
		$orc = get_query_var( 'o2_recent_comments' );
		if ( "1" === $orc ) {
			$page_title = __( 'Recent Comments', 'o2' );
		}
		return $page_title;
	}
}

new o2_Recent_Comments;
