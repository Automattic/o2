<?php
/**
 * Terms in comments.
 */

class o2_Terms_In_Comments {
	var $taxonomy;
	var $meta_key;

	function __construct( $taxonomy, $meta_key = false ) {
		$this->taxonomy = $taxonomy;
		$this->meta_key = empty( $meta_key ) ? "_{$taxonomy}_term_meta" : $meta_key;

		add_action( 'wp_insert_comment',    array( &$this, 'update_comment' ) );
		add_action( 'edit_comment',         array( &$this, 'update_comment' ) );
		add_action( 'wp_insert_post',       array( &$this, 'update_post'    ), 10, 2 );
	}

	/**
	 * @return boolean true if we should process TiC on this site
	 */
	function should_process_terms() {
		return apply_filters( 'o2_should_process_terms', true );
	}

	function update_comment( $comment_id ) {
		if ( ! $this->should_process_terms() ) {
			return;
		}

		$comment = get_comment( $comment_id );

		delete_comment_meta( $comment_id, $this->meta_key );

		$terms = $this->update_comment_terms( $comment_id, $comment );
		foreach( $terms as $term ) {
			add_comment_meta( $comment_id, $this->meta_key, $term );
		}

		$this->update_terms( $comment->comment_post_ID );
	}

	/**
	 * Returns the new comment terms.
	 * Implement in a subclass!
	 *
	 * @param int $comment_id
	 * @param object $comment
	 * @return array The comment terms.
	 */
	function update_comment_terms( $comment_id, $comment ) {}

	function update_post( $post_id, $post ) {
		if ( ! $this->should_process_terms() ) {
			return;
		}

		delete_post_meta( $post_id, $this->meta_key );

		$terms = $this->update_post_terms( $post_id, $post );
		foreach( $terms as $term ) {
			add_post_meta( $post_id, $this->meta_key, $term );
		}

		$this->update_terms( $post_id );
	}

	/**
	 * Returns the new post terms.
	 * Implement in a subclass!
	 *
	 * @param int $post_id
	 * @param object $post
	 * @return array The post terms.
	 */
	function update_post_terms( $post_id, $post ) {}

	function update_terms( $post_id ) {
		// if this is an xpost, don't bother updating post terms
		// (because doing so will strip tags we want to keep)
		$xpost = get_post_meta( $post_id, '_xpost_original_permalink', true );
		if ( ! empty( $xpost ) || '_xposts_term_meta' === $this->meta_key ) {
			return;
		}

		$comment_meta = $this->get_comment_meta( array( 'post_id' => $post_id ) );
		$post_meta    = wp_get_post_terms( $post_id, $this->taxonomy, array( 'fields' => 'names' ) );
		$terms        = array_unique( array_merge( (array) $post_meta, (array) $comment_meta ) );

		wp_set_object_terms( $post_id, $terms, $this->taxonomy );
	}


	/**
	 * get_comment_meta()
	 *
	 * Queries comments based upon post id and meta key
	 *
	 * @param array $args Arguments to modify the query
	 *    'number' int Default 0 (all)
	 *         The maximum number of comments to query.
	 *    'order' string 'ASC' or 'DESC'. Default 'DESC'.
	 *         Whether to sort the results in ascending or descending order.
	 *    'offset' int Default 0
	 *         Ignore the first X results.
	 *
	 * @return array An array of comment meta.
	 */
	function get_comment_meta( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'post_id'   => '',
			'number'    => 0,
			'order'     => 'DESC',
			'orderby'   => '',
			'offset'    => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Generate basic query clauses
		$join       = " AS a JOIN $wpdb->comments AS b ON a.comment_ID = b.comment_id";
		$where      = $wpdb->prepare( "a.meta_key = %s", $this->meta_key );
		$limit      = '';
		$orderby    = $args['orderby'];
		$order      = '';

		if ( ! empty( $orderby ) ) {
			$orderby  = "ORDER BY $orderby ";
			$orderby .= ( 'ASC' == strtoupper($args['order']) ) ? 'ASC' : 'DESC';
		}

		// Process args to modify $where
		if ( ! empty( $args['post_id'] ) )
			$where .= $wpdb->prepare( " AND b.comment_post_ID = %s", $args['post_id'] );

		// Generate $limit
		$number = absint( $args['number'] );
		$offset = absint( $args['offset'] );

		if ( ! empty( $args['number'] ) && ! empty( $args['offset'] ) )
			$limit = "LIMIT $offset,$number";
		elseif ( ! empty( $args['number'] ) )
			$limit = "LIMIT $number";

		$results = $wpdb->get_results( "SELECT meta_value FROM $wpdb->commentmeta $join WHERE $where $orderby $order $limit", ARRAY_A );

		$meta = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$meta[] = $row['meta_value'];
			}
		}
		return $meta;
	}
}
