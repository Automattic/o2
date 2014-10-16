<?php
/**
 * Search comments.
 *
 * @package o2
 */

class o2_Search {

	function __construct() {

		// Bind search query filters
		add_filter( 'posts_distinct', array( &$this, 'search_comments_distinct' ) );
		add_filter( 'posts_where',    array( &$this, 'search_comments_where'    ) );
		add_filter( 'posts_join',     array( &$this, 'search_comments_join'     ) );
	}

	function search_comments_distinct( $distinct ) {
		global $wp_query;
		if ( ! empty( $wp_query->query_vars['s'] ) )
			return 'DISTINCT';
	}

	function search_comments_where( $where ) {
		global $wp_query, $wpdb;

		$q = $wp_query->query_vars;
		if ( empty( $q['s'] ) )
			return $where;
		$n = empty( $q['exact'] ) ? '%' : '';

		$search = array( "comment_post_ID = $wpdb->posts.ID AND comment_approved = '1'" );

		foreach ( (array) $q['search_terms'] as $term ) {
			$term     = esc_sql( like_escape( $term ) );
			$search[] = "( comment_content LIKE '{$n}{$term}{$n}' )";
		}

		$search = " OR ( " . implode( " AND ", $search ) . " )";
		$where = preg_replace( "/\bor\b/i", "$search OR", $where, 1 );
		$where = str_replace(')) AND ((', ')) OR ((', $where);

		return $where;
	}

	function search_comments_join( $join ) {
		global $wp_query, $wpdb, $request;
		if ( ! empty( $wp_query->query_vars['s'] ) )
			$join .= " LEFT JOIN $wpdb->comments ON ( comment_post_ID = ID  AND comment_approved =  '1' )";
		return $join;
	}
}
