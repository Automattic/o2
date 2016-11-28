<?php

class o2_Read_API extends o2_API_Base {
	public static function init() {
		do_action( 'o2_read_api' );

		// Need a 'method' of some sort
		if ( empty( $_GET['method'] ) ) {
			self::die_failure( 'no_method', __( 'No method supplied', 'o2' ) );
		}
		$method = strtolower( $_GET['method'] );

		// ?since=unixtimestamp only return posts/comments since the specified time
		global $o2_since;
		$o2_since = false;
		if ( isset( $_REQUEST['since'] ) ) {
			// JS Date.now() sends milliseconds, so just substr to be safe
			// Also, substract two seconds to allow for differences in client and
			// server clocks
			$o2_since = absint( substr( $_REQUEST['since'], 0, 10 ) ) - 2;
		}

		// sanity check.  don't allow arbitrarily low since values
		// or we could end up serving all the posts and comments for the blog
		// so, let's lower bound since to 1 day ago
		$min_since = time() - 24 * 60 * 60;
		if ( $o2_since < $min_since ) {
			$o2_since = $min_since;
		}

		// Only allow whitelisted methods
		if ( in_array( $method, apply_filters( 'o2_read_api_methods', array( 'poll', 'query', 'preview' ) ) ) ) {
			// Handle different methods
			if ( method_exists( 'o2_Read_API', $method ) ) {
				o2_Read_API::$method();
			} else {
				self::die_success( '1' );
			}
		}

		self::die_failure( 'unknown_method', __( 'Unknown/unsupported method supplied', 'o2' ) );
	}

	public static function poll() {
		// This is a super lightweight API to get posts and comments from WP
		// It's intended for use with o2

		// @todo Allow requesting a specific post or comment, and a post with all comments

		// Need to sort things because they're queried separately
		function o2_date_sort( $a, $b ) {
			if ( $a->unixtime == $b->unixtime )
				return 0;
			return $a->unixtime > $b->unixtime ? -1 : 1;
		}

		$ok_to_serve_data = true;
		$ok_to_serve_data = apply_filters( 'o2_read_api_ok_to_serve_data', $ok_to_serve_data );

		$data = array();

		if ( $ok_to_serve_data ) {
			$posts    = self::get_posts();
			$comments = self::get_comments();

			// Clean up posts and comments
			$data = array();
			if ( count( $posts ) ) {
				foreach ( $posts as $post ) {
					$data[] = o2_Fragment::get_fragment( $post );
				}
			}
			if ( count( $comments ) ) {
				foreach ( $comments as $comment ) {
					$data[] = o2_Fragment::get_fragment( $comment );
				}
			}

			// Shuffle up and deal
			usort( $data, 'o2_date_sort' );
		}

		// Let the client know if the user is logged in or not
		$is_logged_in = is_user_logged_in();

		// Generate an updated nonce (they expire after all, and our "app" may be open for a long time)
		$new_nonce = wp_create_nonce( 'o2_nonce' );
		if ( $is_logged_in ) {
			// @todo change to another way, and one that is less costly - see also below
			// $current_user_id = get_current_user_id();
			// update_user_meta( $current_user_id, 'o2_last_poll_gmt', time() );
		}

		$response = array(
			"data"         => $data,
			"newNonce"    => $new_nonce,
			"loggedIn"    => $is_logged_in
		);

		// Check for unloaded scripts and styles if there are polled posts
		if ( !empty( $data ) ) {

			// Attach scripts
			if ( isset( $_REQUEST['scripts'] ) ) {

				// Parse and sanitize the script handles already output
				if ( ! is_array( $_REQUEST['scripts'] ) ) {
					$_REQUEST['scripts'] = explode( ',', $_REQUEST['scripts'] );
				}
				
				$initial_scripts = is_array( $_REQUEST['scripts'] ) ? array_map( 'sanitize_text_field', $_REQUEST['scripts'] ) : null;

				if ( is_array( $initial_scripts ) ) {
					global $wp_scripts;
					if ( ! ( $wp_scripts instanceof WP_Scripts ) ) {
						$wp_scripts = new WP_Scripts();
					}

					// Identify new scripts needed by the polled posts
					$polled_scripts = array_diff( $wp_scripts->done, $initial_scripts );

					// If new scripts are needed, extract relevant data from $wp_scripts
					if ( !empty( $polled_scripts ) ) {
						$response['scripts'] = array();

						foreach ( $polled_scripts as $handle ) {
							// Abort if the handle doesn't match a registered script
							if ( !isset( $wp_scripts->registered[ $handle ] ) )
								continue;

							// Provide basic script data
							$script_data = array(
								'handle'     => $handle,
								'footer'     => ( is_array( $wp_scripts->in_footer ) && in_array( $handle, $wp_scripts->in_footer ) ),
								'extra_data' => $wp_scripts->print_extra_script( $handle, false ),
							);

							// Base source
							$src = $wp_scripts->registered[ $handle ]->src;

							// Take base_url into account
							if ( strpos( $src, '//' ) === 0 )
								$src = is_ssl() ? 'https:' . $src : 'http:' . $src;

							// Deal with root-relative URLs
							if ( strpos( $src, '/' ) === 0 )
								$src = $wp_scripts->base_url . $src;

							if ( strpos( $src, 'http' ) !== 0 )
								$src = $wp_scripts->base_url . $src;

							// Version and additional arguments
							if ( null === $wp_scripts->registered[ $handle ]->ver )
								$ver = '';
							else
								$ver = $wp_scripts->registered[ $handle ]->ver ? $wp_scripts->registered[ $handle ]->ver : $wp_scripts->default_version;

							if ( isset( $wp_scripts->args[ $handle ] ) )
								$ver = $ver ? $ver . '&amp;' . $wp_scripts->args[$handle] : $wp_scripts->args[$handle];

							// Full script source with version info
							$script_data['src'] = add_query_arg( 'ver', $ver, $src );

							// Add script to data that will be returned to o2
							array_push( $response['scripts'], $script_data );
						}
					}
				}
			}

			// Attach styles
			if ( isset( $_REQUEST['styles'] ) ) {

				// Parse and sanitize the script handles already output
				if ( ! is_array( $_REQUEST['styles'] ) ) {
					$_REQUEST['styles'] = explode( ',', $_REQUEST['styles'] );
				}
	
				// Parse and sanitize the style handles already output
				$initial_styles = is_array( $_REQUEST['styles'] ) ? array_map( 'sanitize_text_field', $_REQUEST['styles'] ) : null;

				if ( is_array( $initial_styles ) ) {
					global $wp_styles;

					// Identify new styles needed by the polled posts
					$polled_styles = array_diff( $wp_styles->done, $initial_styles );

					// If new styles are needed, extract relevant data from $wp_styles
					if ( !empty( $polled_styles ) ) {
						$response['styles'] = array();

						foreach ( $polled_styles as $handle ) {
							// Abort if the handle doesn't match a registered stylesheet
							if ( !isset( $wp_styles->registered[ $handle ] ) )
								continue;

							// Provide basic style data
							$styles_data = array(
								'handle' => $handle,
								'media'  => 'all',
							);

							// Base source
							$src = $wp_styles->registered[ $handle ]->src;

							// Take base_url into account
							if ( strpos( $src, 'http' ) !== 0 )
								$src = $wp_styles->base_url . $src;

							// Version and additional arguments
							if ( null === $wp_styles->registered[ $handle ]->ver )
								$ver = '';
							else
								$ver = $wp_styles->registered[ $handle ]->ver ? $wp_styles->registered[ $handle ]->ver : $wp_styles->default_version;

							if ( isset( $wp_styles->args[ $handle ] ) )
								$ver = $ver ? $ver . '&amp;' . $wp_styles->args[$handle] : $wp_styles->args[$handle];

							// Full script source with version info
							$script_data['src'] = add_query_arg( 'ver', $ver, $src );

							// @todo Handle parsing conditional comments

							// Parse requested media context for stylesheet
							if ( isset( $wp_styles->registered[ $handle ]->args ) )
								$style_data['media'] = esc_attr( $wp_styles->registered[ $handle ]->args );

							// Add script to data that will be returned to o2
							array_push( $response['styles'], $style_data );
						}
					}
				}
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * Checks if a post is OK to add before adding to posts array in get_posts method
	 *
	 * @param WP_Post $post
	 *
	 * @return boolean True if post is ok to add.
	 */
	public static function is_ok_to_add( $post ) {
		$ok_to_add = false;
		// No password required?  Add it right away
		if ( empty( $post->post_password ) ) {
			$ok_to_add = true;
		} else if ( $is_page ) {
			$ok_to_add = ! post_password_required( $post->ID );
		}

		return $ok_to_add;
	}

	/**
	 * Return an array of recent posts
	 */
	public static function get_posts() {

		$is_page = false;

		// Apply our 'since' parameter
		function o2_filter_posts_where( $where = '' ) {
			global $o2_since, $wpdb;

			if ( !$o2_since )
				return $where;

			$holdoff = 3; // seconds - gives time for posts to "bake" (e.g. if meta is added after post insertion or update)
			$since = date( 'Y-m-d H:i:s', $o2_since );            // start of range
			$until = date( 'Y-m-d H:i:s', time() - $holdoff );    // end of range

			$where .= $wpdb->prepare( " AND post_modified_gmt > %s AND post_modified_gmt < %s", $since, $until );
			return $where;
		}

		// Add filters to posts query
		add_filter( 'posts_where', 'o2_filter_posts_where' );

		// Post stati
		$post_stati = array( 'publish', 'trash' );
		if ( current_user_can( 'read_private_posts' ) )
			array_push( $post_stati, 'private' );

		// Override post stati on $args as read is unauthenticated
		$args = array();
		if ( isset( $_REQUEST['queryVars'] ) ) {
			$args = $_REQUEST['queryVars'];
		}

		$args['post_status'] = $post_stati;

		$defaults = array(
			'post_type'           => 'post',
			'post_status'         => $post_stati,
			'posts_per_page'      => 20,
			'ignore_sticky_posts' => true,
			'suppress_filters'    => false,
		);
		$query_args = wp_parse_args( $args, $defaults );

		// If we are on a page, change the query post_type to page, otherwise
		// we will get no data (and updates to pages will not be sent to polling
		// clients on that page) [using an array of post and page doesn't work either]
		if ( isset( $query_args['pagename'] ) ) {
			$query_args['post_type'] = 'page';
		}

		// Filter for plugins
		$query_args = apply_filters( 'o2_get_posts_query_args', $query_args );

		// Run the query for posts
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query( $query_args );

		// Set as is_home() to ensure that plugin filters run on the_content()
		$GLOBALS['wp_query']->is_home = true;

		// Remove filters from query
		remove_filter( 'pre_option_posts_per_rss', 'o2_posts_per_rss' );
		remove_filter( 'posts_where', 'o2_filter_posts_where' );

		$posts = array();

		// Run through The Loop
		global $post;
		$post = isset( $GLOBALS['wp_query']->post ) ? $GLOBALS['wp_query']->post : null;

		if ( have_posts() ) {
			ob_start();
			wp_head();
			ob_end_clean();

			while ( have_posts() ) {
				the_post();

				if ( self::is_ok_to_add( $post ) ) {
					$posts[] = $post;
				}
			}

			ob_start();
			wp_footer();
			ob_end_clean();
		} else if ( 0 != intval( $_REQUEST['postId'] ) ) {

			/*
			 * If WP_Query returned no posts and if currently on a single page, then
			 * query the postID and check if it has been trashed. This is workaround
			 * for the ticket below.
			 *
			 * @todo Remove second query with get_post when the below ticket is addressed.
			 * https://core.trac.wordpress.org/ticket/29167
			 */
			$post = get_post( intval( $_REQUEST['postId'] ) );

			if ( 'trash' == $post->post_status ) {
				if ( self::is_ok_to_add( $post ) ) {
					$posts[] = $post;
				}
			}
		}

		return $posts;
	}

	/**
	 * Return an array of recent comments
	 */
	public static function get_comments() {
		global $o2_since;

		// Apply our 'since' parameter
		function o2_comments_clauses( $clauses ) {
			global $o2_since, $wpdb;
			if ( !$o2_since )
				return $clauses;
			$clauses['where'] .= $wpdb->prepare( " AND comment_date_gmt > %s", date( 'Y-m-d H:i:s', $o2_since ) );
			return $clauses;
		}

		// First pass - retrieve comments CREATED since o2_since
		// Add o2_comments_clauses filter to the comments query
		add_filter( 'comments_clauses', 'o2_comments_clauses' );
		$query_args = array(
			'status' => 'approve',
		);

		// Filter for plugins
		$query_args = apply_filters( 'o2_get_comments_query_args', $query_args );

		// Run the query for comments
		$comments = get_comments( $query_args );

		// Remove filters from query
		remove_filter( 'comments_clauses', 'o2_comments_clauses' );

		// Second pass - add comments MODIFIED since o2_since
		if ( $o2_since ) {

			$query_args = array(
				'status'          => 'approve',
				'meta_query'      => array(
					array(
						'key'         => 'o2_comment_gmt_modified',
						'value'       => $o2_since,
						'compare'     => '>=',
						'type'        => 'numeric',
					)
				)
			);

			// Add filters for plugins
			$query_args = apply_filters( 'o2_get_comments_query_args', $query_args );
			$approved_comments = get_comments( $query_args );

			$query_args = array(
				'status'          => 'trash',
				'meta_query'      => array(
					array(
						'key'         => 'o2_comment_gmt_modified',
						'value'       => $o2_since,
						'compare'     => '>=',
						'type'        => 'numeric',
					)
				)
			);

			$query_args = apply_filters( 'o2_get_comments_query_args', $query_args );
			$trashed_comments = get_comments( $query_args );

			// Merge all of the comments together.
			$comments = array_merge( $comments, $approved_comments, $trashed_comments );
		}

		return $comments;
	}

	/**
	 * Multipurpose action hook for plugins
	 */
	public static function query() {
		if ( apply_filters( 'o2_read_api_ok_to_serve_data', true ) ) {
			if ( isset( $_REQUEST['callback'] ) && ! empty( $_REQUEST['callback'] ) ) {
				$callback = sanitize_key( $_REQUEST['callback'] );
			} else {
				$callback = 'default';
			}
			do_action( 'o2_read_api_' . $callback );
			self::die_success( '1' );
		} else {
			self::die_failure( 'noop', __( 'Unable to complete this action', 'o2' ) );
		}
	}

	/**
	 * Generates a preview version of a post or comment being created/edited
	 */
	public static function preview() {
		$response = '<p>' . __( 'Nothing to preview.', 'o2' ) . '</p>';

		if ( ! empty( $_REQUEST['data'] ) ) {
			switch ( $_REQUEST['type'] ) {
				case 'comment':
					$response = apply_filters( 'o2_preview_comment', wp_unslash( $_REQUEST['data'] ) );
					$response = trim( apply_filters( 'comment_text', $response ) );

					break;
				case 'post':
					$message = new stdClass;
					$message->titleRaw = '';
					$message->contentRaw = wp_unslash( $_REQUEST['data'] );

					$message = o2_Write_API::generate_title( $message );

					add_filter( 'o2_should_process_terms', '__return_false' );
					add_filter( 'o2_process_the_content', '__return_false' );

					$message->contentRaw = apply_filters( 'o2_preview_post', $message->contentRaw );

					$response = trim( apply_filters( 'the_content', $message->contentRaw ) );

					if ( ! empty( $message->titleRaw ) ) {
						$response = "<h1>{$message->titleRaw}</h1>" . $response;
					}

					break;
				default:
					// This page left intentionally blank
			}
		}

		self::die_success( $response );
	}
}
