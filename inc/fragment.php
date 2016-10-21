<?php

/**
 * Posts and Comments are all represented as "Fragments" within o2.
 */
class o2_Fragment {

	/**
	 * Figures out if $data looks like a post or a comment, and gives you
	 * a Fragment either way.
	 */
	public static function get_fragment( $data, $args = array() ) {
		if ( !is_object( $data ) )
			return false;

		if ( property_exists( $data, 'post_author' ) ) {
			return self::get_fragment_from_post( $data, $args );
		} else if ( property_exists( $data, 'comment_ID' ) ) {
			return self::get_fragment_from_comment( $data );
		}

		return false;
	}

	/**
	 * Used in get_fragment_from_posts, avoids us picking up password protected posts
	 * in prev / next post navigation
	 */
	public static function get_adjacent_post_where( $where ) {
		return $where . " AND ( p.post_password IS NULL OR p.post_password = '' )";
	}

	/**
	 * Returns an entry array for the specified conversation fragment
	 * where fragment_type is 'post' or 'comment'
	 */
	public static function get_fragment_from_post( $my_post, $args = array() ) {

		remove_filter( 'the_content', array( 'o2', 'add_json_data' ), 999999 ); // Avoid infinite loops
		remove_filter( 'the_excerpt', array( 'o2', 'add_json_data' ), 999999 ); // Avoid infinite loops

		add_filter( 'home_url', array( 'o2_Fragment', 'home_url' ), 10, 4 );

		$post_ID = $my_post->ID;
		$post_tags = self::get_post_tags( $post_ID );
		$permalink = get_permalink( $post_ID );

		// Get a set of classes to be used when displaying.
		// We force ->post_type because it's not included in core during
		// AJAX calls (is_admin())
		$post_class_array = get_post_class( $my_post->post_type, $post_ID );
		$post_class = ! empty( $post_class_array );
		if ( $post_class ) {
			$post_class = join( ' ', $post_class_array );
		}

		// Adjacent post information (prev next) only makes sense for some pages (e.g. is_single)
		// So, we only fetch adjacent post information if we were asked to (to avoid unnecessary db queries)

		$prev_post_title = '';
		$prev_post_url = '';
		$has_prev_post = false;
		$next_post_title = '';
		$next_post_url = '';
		$has_next_post = false;

		if ( isset( $args['find-adjacent'] ) && $args['find-adjacent'] ) {
			// We can't use the loop because get_fragment_from_post could be called outside of the loop (e.g. by a ajax request from backbone)

			// Set global so that core functions work as expected
			global $post;
			$old_post = $post;
			$post = $my_post;

			// temporarily add filters to avoid picking up password protected posts
			add_filter( 'get_previous_post_where', array( 'o2_Fragment', 'get_adjacent_post_where' ) );
			add_filter( 'get_next_post_where',     array( 'o2_Fragment', 'get_adjacent_post_where' ) );

			$prev_post = get_previous_post();
			$has_prev_post = ! empty( $prev_post );
			if ( $has_prev_post ) {
				$prev_post_title = $prev_post->post_title;
				$prev_post_title = apply_filters( 'the_title', $prev_post_title, $prev_post->ID );
				$prev_post_url = get_permalink( $prev_post->ID );
			}

			$next_post = get_next_post();
			$has_next_post = ! empty( $next_post );
			if ( $has_next_post ) {
				$next_post_title = $next_post->post_title;
				$next_post_title = apply_filters( 'the_title', $next_post_title, $next_post->ID );
				$next_post_url = get_permalink( $next_post->ID );
			}

			remove_filter( 'get_previous_post_where', array( 'o2_Fragment', 'get_adjacent_post_where' ) );
			remove_filter( 'get_next_post_where',     array( 'o2_Fragment', 'get_adjacent_post_where' ) );

			// Set global scope back to whatever it was before
			$post = $old_post;
		}

		$raw_post_title = $my_post->post_title;
		$raw_content = $my_post->post_content;

		$extended_content = get_extended( $raw_content );
		$title_was_generated_from_content = ( $raw_post_title == wp_trim_words( $raw_content, 5 ) );

		$post_format = get_post_format( $post_ID );
		if ( false === $post_format ) {
			$post_format = 'standard';
		}

		// Filter the title
		$filtered_post_title = apply_filters( 'the_title', $raw_post_title, $my_post->ID );

		// Handle <!--more--> for same-page toggling
		global $more;
		if ( !$more ) {
			if ( !empty( $extended_content['extended'] ) ) {
				// Set more text
				if ( empty( $extended_content['more_text'] ) )
					$extended_content['more_text'] = __( 'Show full post', 'o2' );

				// Set more link
				$more_text = strip_tags( wp_kses_no_null( trim( $extended_content['more_text'] ) ) );
				$more_link = apply_filters( 'the_content_more_link', "<a href='{$permalink}#more-{$post_ID}' class='more-link'>{$more_text}</a>", $more_text );

				$extended_content = $extended_content['main'] . "\n\n" . $more_link . "\n<div class='o2-extended-more'>\n\n" . $extended_content['extended'] . "\n\n</div><!--.o2-extended-more-->\n";

				// If the post has more content, we need to go through the full `the_content` filter again
				if ( isset( $args['the_content'] ) ) {
					unset( $args['the_content'] );
				}

			// No <!--more--> text
			} else {
				$extended_content = $extended_content['main'];
			}
		} else {
			$extended_content = $raw_content;
		}

		// When editing the content, SyntaxHighlighter does some magic to decode
		// HTML entities within [code] blocks.
		global $SyntaxHighlighter;
		if ( is_a( $SyntaxHighlighter, 'SyntaxHighlighter' ) ) {
			$raw_content = $SyntaxHighlighter->decode_shortcode_contents( $raw_content );
		}

		// An xpost is already filtered content
		$xpost_original_permalink = get_post_meta( $post_ID, '_xpost_original_permalink', true );
		if ( ! empty( $xpost_original_permalink ) ) {
			$filtered_content = $extended_content;
		} elseif ( isset( $args['the_content'] ) ) {
			$filtered_content = make_clickable( $args['the_content'] );
		} else {
			add_filter( 'the_content', 'o2_Fragment::o2_make_clickable', 9 );
			$filtered_content = apply_filters( 'the_content', $extended_content );
			remove_filter( 'the_content', 'o2_Fragment::o2_make_clickable', 9 );
		}
		$filtered_content = apply_filters( 'o2_filtered_content', $filtered_content, $my_post );

		// Mentions
		list( $mentions, $mention_context ) = self::get_mention_data( 'post', $post_ID, $raw_content );

		$comment_models = array();
		$approved_comments = get_comments(
			array(
				'post_id' => $post_ID,
				'order'   => 'ASC',
				'status'  => 'approve'
			)
		);

		$trashed_comments = get_comments(
			array(
				'post_id'      => $post_ID,
				'order'        => 'ASC',
				'status'       => 'trash',
				'meta_query'   => array(
					array(
						'key'     => 'o2_comment_has_children',
						'compare' => 'EXISTS'
					)
				)
			)
		);

		/*
		 * For bootstrapping data, we are only interested in approved comments and
		 * trashed comments that have children for now.
		 */
		$post_comments = array_merge( $approved_comments, $trashed_comments );

		foreach ( (array) $post_comments as $post_comment ) {
			$comment_models[] = o2_Fragment::get_fragment( $post_comment );
		}

		// Set a post date for previewing drafts
		$post_date_gmt = $my_post->post_date_gmt;
		if ( '0000-00-00 00:00:00' === $post_date_gmt )
			$post_date_gmt = $my_post->post_modified_gmt;
		$modified = get_post_meta( $post_ID, 'client-modified', true );
		if ( !$modified )
			$modified = strtotime( $my_post->post_modified_gmt );

		$post_actions = o2_get_post_actions( $post_ID );
		$comments_open = comments_open( $post_ID );

		// Password protected page?  Check the password before delivering the content
		$is_page = ( "page" == get_post_type( $post_ID ) );
		$is_password_protected = ! empty( $my_post->post_password );

		if ( $is_page && $is_password_protected && post_password_required( $post_ID ) ) {
			$post_actions = array();
			$comment_models = array();
			$comments_open = false;
			$raw_content = "";
			$filtered_content = get_the_password_form( $post_ID );
		}

		$fragment = array(
				'type'                         => 'post', /* fragment type - not to be confused with get_post_type */
				'id'                           => $post_ID,
				'postID'                       => $post_ID,
				'cssClasses'                   => $post_class,
				'parentID'                     => 0,
				'titleRaw'                     => $raw_post_title,
				'titleFiltered'                => $filtered_post_title,
				'titleWasGeneratedFromContent' => $title_was_generated_from_content,
				'contentRaw'                   => $raw_content,
				'contentFiltered'              => $filtered_content,
				'permalink'                    => $permalink,
				'unixtime'                     => strtotime( $post_date_gmt ),
				'unixtimeModified'             => (int) $modified,
				'entryHeaderMeta'              => '',
				'linkPages'                    => '',
				'footerEntryMeta'              => '',
				'tagsRaw'                      => self::get_post_tags_raw( $post_tags ),
				'tagsArray'                    => self::get_post_tags_array( $post_tags ),
				'loginRedirectURL'             => wp_login_url( $permalink ), // @todo Put a login URL in o2.options and then add redirect via JS
				'hasPrevPost'                  => $has_prev_post,
				'prevPostTitle'                => $prev_post_title,
				'prevPostURL'                  => $prev_post_url,
				'hasNextPost'                  => $has_next_post,
				'nextPostTitle'                => $next_post_title,
				'nextPostURL'                  => $next_post_url,
				'commentsOpen'                 => $comments_open,
				'is_xpost'                     => ! empty( $xpost_original_permalink ),
				'editURL'                      => get_edit_post_link( $post_ID ),
				'postActions'                  => $post_actions,
				'comments'                     => $comment_models,
				'postFormat'                   => $post_format,
				'postMeta'                     => array(),
				'postTerms'                    => self::get_post_terms( $post_ID ),
				'pluginData'                   => array(),
				'isPage'                       => ( "page" == get_post_type( $post_ID ) ),
				'mentions'                     => $mentions,
				'mentionContext'               => strip_tags( $mention_context ),
				'isTrashed'                    => ( "trash" == get_post_status( $post_ID ) ),
		);

		// Get author properties (and bootstrap the rest of the model)
		// e.g. userLogin
		$post_user_properties = self::get_post_user_properties( $my_post );
		$fragment = array_merge( $fragment, $post_user_properties );

		// @todo - add filter to move theme specific attributes out of here, and allow themes to add more attributes
		$fragment = apply_filters( 'o2_post_fragment', $fragment, $post_ID );

		// as they filter the templates

		add_filter( 'the_content', array( 'o2', 'add_json_data' ), 999999 );
		add_filter( 'the_excerpt', array( 'o2', 'add_json_data' ), 999999 );

		remove_filter( 'home_url', array( 'o2_Fragment', 'home_url' ), 10, 4 );

		// Force UTF8 to avoid JSON encode issues
		$fragment = self::to_utf8( $fragment );

		return $fragment;
	}

	public static function get_fragment_from_comment( $my_comment ) {
		add_filter( 'home_url', array( 'o2_Fragment', 'home_url' ), 10, 4 );

		// Update the global comment variable for methods like get_comment_ID used by comment_like_current_user_likes
		global $comment;
		if ( isset( $comment ) ) {
			$old_comment = $comment;
		}
		$comment = $my_comment;

		// Update the global post variable for methods like $wp_embed->autoembed that need it
		global $post;
		if ( isset( $post ) ) {
			$old_post = $post;
		}
		$post = get_post( $my_comment->comment_post_ID );

		$comment_ID = $my_comment->comment_ID;
		$permalink = get_permalink( $my_comment->comment_post_ID ) . '#comment-' . $comment_ID;
		$permalink = apply_filters( 'o2_comment_permalink', $permalink, $comment_ID );

		$approved           = ( '1' == $my_comment->comment_approved );
		$is_trashed         = ( 'trash' == $my_comment->comment_approved );
		$previously_deleted = get_comment_meta( $comment_ID, 'o2_comment_prev_deleted', true );

		// Update the $comment_depth global with our findings so that get_comment_class will
		// work correctly
		global $comment_depth;
		$comment_depth = o2_Fragment::get_depth_for_comment( $comment );

		// These calls with empty args will rely on global $comment
		$edit_comment_link = get_edit_comment_link();
		$comment_class_array = get_comment_class();

		$comment_class = ! empty( $comment_class_array );

		if ( $comment_class ) {
			// Let's add trashed and deleted classes to the comment for styling.
			if ( $is_trashed ) {
				$comment_class_array[] = 'o2-trashed';
			}

			if ( $previously_deleted ) {
				$comment_class_array[] = 'o2-deleted';
			}

			$comment_class = join( ' ', $comment_class_array );
		}

		$comment_created = get_comment_meta( $comment_ID, 'o2_comment_created', true );

		if ( empty( $comment_created ) ) {
			$comment_created = strtotime( $comment->comment_date_gmt );
		}

		// Convert HTML entities in the comment_content back to their actual characters
		// So that the JSON encoded value is the same thing the user edited in their
		// comment in the first place.
		// In this way, if/when they edit the comment, they don't get things like &lt;p&gt;
		// in the editor, but get <p> instead

		$raw_content = htmlspecialchars_decode( $my_comment->comment_content, ENT_QUOTES );
		$raw_post_title = html_entity_decode( get_the_title( $my_comment->comment_post_ID ) );

		// Mentions
		list( $mentions, $mention_context ) = self::get_mention_data( 'comment', $comment_ID, $raw_content );

		$fragment = array(
				'type'                     => 'comment',
				'id'                       => $comment_ID,
				'postID'                   => $my_comment->comment_post_ID,
				'postTitleRaw'             => $raw_post_title,
				'cssClasses'               => $comment_class,
				'parentID'                 => $my_comment->comment_parent,
				'contentRaw'               => $raw_content,
				'contentFiltered'          => apply_filters( 'comment_text', $my_comment->comment_content, $my_comment, array() ),
				'permalink'                => $permalink,
				'unixtime'                 => strtotime( $my_comment->comment_date_gmt ),
				'loginRedirectURL'         => wp_login_url( $permalink ),
				'approved'                 => $approved,
				'isTrashed'                => $is_trashed,
				'prevDeleted'              => $previously_deleted,
				'editURL'                  => $edit_comment_link,
				'depth'                    => $comment_depth,
				'commentDropdownActions'   => o2_get_comment_actions( 'dropdown', $comment, $comment_depth ),
				'commentFooterActions'     => o2_get_comment_actions( 'footer', $comment, $comment_depth ),
				'commentTrashedActions'    => o2_get_comment_actions( 'trashed_dropdown', $comment, $comment_depth ),
				'mentions'                 => $mentions,
				'mentionContext'           => $mention_context,
				'commentCreated'           => $comment_created,
				'hasChildren'              => (bool) get_comment_meta( $comment_ID, 'o2_comment_has_children', true )
		);

		// Get author properties (and bootstrap the rest of the model)
		// e.g. userLogin or noprivUserName, noprivUserHash and noprivUserURL for nopriv commentor
		$comment_author_properties = self::get_comment_author_properties( $my_comment );
		$fragment = array_merge( $fragment, $comment_author_properties );

		// Put the original globals back
		if ( isset( $old_comment ) ) {
			$comment = $old_comment;
		}

		if ( isset( $old_post ) ) {
			$post = $old_post;
		}

		remove_filter( 'home_url', array( 'o2_Fragment', 'home_url' ), 10, 4 );

		$fragment = apply_filters( 'o2_comment_fragment', $fragment, $comment_ID );

		// Force UTF8 to avoid JSON encode issues
		$fragment = self::to_utf8( $fragment );

		return $fragment;
	}

	public static function get_depth_for_comment( $my_comment ) {
		$depth = 1;
		while ( 0 < $my_comment->comment_parent ) {
			$depth = $depth + 1;
			$my_comment = get_comment( $my_comment->comment_parent );
		}
		return $depth;
	}

	/**
	  * get_current_user_properties returns
	  * 	userLogin, userNicename, canEditPosts, canEditOthersPosts, and canPublishPosts for logged in user
	  * OR
	  * 	noprivUserName, noprivUserHash and noprivUserURL for nopriv user
	  *
	  * For logged in users, it also caches a complete user model for bootstrapping
	  */

	public static function get_current_user_properties() {
		$current_user = wp_get_current_user();

		if ( $current_user instanceof WP_User ) {
			$user_id = $current_user->ID;

			if ( 0 != $user_id ) {
				$user_data = get_userdata( $user_id );

				self::add_to_user_bootstrap( $user_data );

				$return_result = array(
					'userLogin' => $user_data->user_login,
					'userNicename' => $user_data->user_nicename,
					'noprivUserName' => '',
					'noprivUserHash' => '',
					'noprivUserURL' => '',
					'canEditPosts' => user_can( $user_id, 'edit_posts' ),
					'canEditOthersPosts' => user_can( $user_id, 'edit_others_posts' ),
					'canPublishPosts' => user_can( $user_id, 'publish_posts' )
				);

				return $return_result;
			} // if 0 != $user_id
		}

		// Try to get unlogged-in user details from cookie
		$commenter = wp_get_current_commenter();

		$return_result = array(
			'userLogin' => '',
			'userNicename' => '',
			'noprivUserName' => esc_attr( $commenter['comment_author'] ),
			'noprivUserEmail' => esc_attr( $commenter['comment_author_email'] ),
			'noprivUserURL' => esc_url( $commenter['comment_author_url'] ),
			'canEditPosts' => false,
			'canEditOthersPosts' => false,
			'canPublishPosts' => false
		);

		return $return_result;
	}

	public static function add_to_user_bootstrap( $user_data ) {
		global $o2_userdata;
		if ( ! is_array( $o2_userdata ) ) {
			$o2_userdata = array();
		}

		$user_login = $user_data->user_login;

		if ( ! isset( $o2_userdata[$user_login] ) ) {
			$bootstrap_model = self::get_model_from_userdata( $user_data );

			$o2_userdata[$user_login] = $bootstrap_model;
		}
	}

	public static function get_model_from_userdata( $user_data ) {
		$bootstrap_model = array(
			'id' => $user_data->ID,
			'type' => 'user',
			'userLogin' => $user_data->user_login,
			'userNicename' => $user_data->user_nicename,
			'displayName' => $user_data->display_name,
			'firstName' => $user_data->user_firstname,
			'lastName' => $user_data->user_lastname,
			'url' => get_author_posts_url( $user_data->ID ),
			'urlTitle' => sprintf( __( 'Posts by %1$s (%2$s)', 'o2' ), $user_data->display_name, '@' . $user_data->user_nicename ),
			'hash' => md5( strtolower( trim( $user_data->user_email ) ) ),
			'modelClass' => '' // empty or o2-user-model-incomplete
		);

		$bootstrap_model = apply_filters( 'o2_user_model', $bootstrap_model, $user_data );

		// Force UTF8 to avoid JSON encode issues
		$bootstrap_model = self::to_utf8( $bootstrap_model );

		return $bootstrap_model;
	}

	/**
	  * get_post_user_properties returns the userLogin for the author and
	  * bootstraps the rest of the user info
	  */

	public static function get_post_user_properties( $_post ) {
		$user_id = $_post->post_author;
		$user_data = get_userdata( $user_id );
		self::add_to_user_bootstrap( $user_data );

		$return_result = array(
			'userLogin' => $user_data->user_login,
			'userNicename' => $user_data->user_nicename
		);

		return $return_result;
	}

	public static function get_comment_author_properties( $_comment ) {
		$return_result = array();
		$user_id = $_comment->user_id;

		if ( 0 != $user_id ) { // logged in commentor
			$user_data = get_userdata( $user_id );
			self::add_to_user_bootstrap( $user_data );

			$return_result = array(
				'userLogin' => $user_data->user_login,
				'userNicename' => $user_data->user_nicename
			);
		} else { // no priv commentor
			$comment_author_email_hash = !empty( $_comment->comment_author_email ) ? md5( strtolower( trim( $_comment->comment_author_email ) ) ) : '00000000000000000000000000000000';

			$return_result = array(
				'noprivUserName' => $_comment->comment_author,
				'noprivUserHash' => $comment_author_email_hash,
				'noprivUserURL' => $_comment->comment_author_url
			);
		}

		return $return_result;
	}

	/*
	 * Returns a raw string of comma delimited tags, suitable for editing (e.g. tag1, tag2, tag3)
	 */
	public static function get_post_tags_raw( $post_tags ) {
		$tag_names = array();
		$result = '';

		foreach ( (array) $post_tags as $tag ) {
			$tag_names[] = $tag->name;
		}

		$result = implode( ', ', $tag_names );

		return $result;
	}

	/*
	 * Returns an array of tags w/ properties, suitable for template driven rendering
	 */

	public static function get_post_tags_array( $post_tags ) {
		$result = array();

		foreach ( (array) $post_tags as $tag ) {
			$result[] = array(
				"label" => $tag->name,
				"count" => $tag->count,
				"link"  => get_tag_link( $tag->term_id )
				);
		}

		return $result;
	}

	/**
	 * Wrapper for wp_get_post_tags() with static cache.
	 */
	public static function get_post_tags( $post_id ) {
		static $post_tags_cache = array();

		if ( ! isset( $post_tags_cache[ $post_id ] ) ) {
			$post_tags_cache[ $post_id ] = wp_get_post_tags( $post_id );
		}

		return $post_tags_cache[ $post_id ];
	}

	/*
	 * Returns an array of the posts's taxonomy terms
	 */
	public static function get_post_terms( $post_id ) {
		$post_type = get_post_type( $post_id );
		$taxonomies = get_taxonomies( array(
				'object_type' => array( $post_type ),
				'_builtin'    => true,
			) );
		$taxonomies = apply_filters( 'o2_get_post_terms_taxonomies', $taxonomies, $post_id, $post_type );

		static $post_terms_cache = array();

		$cache_key = md5( $post_id . serialize( $taxonomies ) );
		if ( ! isset( $post_terms_cache[ $cache_key ] ) ) {
			$retval = array();
			foreach ( $taxonomies as $taxonomy ) {
				$retval[ $taxonomy ] = array();
				$terms = wp_get_post_terms( $post_id, $taxonomy );
				foreach ( $terms as $term ) {
					$arr = array(
						'label' => $term->name,
						'count' => $term->count,
					);
					$link = get_term_link( $term );
					if ( ! is_wp_error( $link ) )
						$arr['link'] = $link;
					$retval[ $taxonomy ][] = $arr;
				}
			}
			$post_terms_cache[ $cache_key ] = $retval;
		} else {
			$retval = $post_terms_cache[ $cache_key ];
		}

		$retval = apply_filters( 'o2_get_post_terms', $retval, $post_id, $taxonomies );
		return $retval;
	}

	/*
	 * Returns a list of an array of the mentions within the content, as well as the mention context fragment
	 * @param string $object_type The object type: post or comment
	 * @param int $object_id The object id
	 * @param string $object_content The object content
	 * @return mixed
	 */
	public static function get_mention_data( $object_type, $object_id, $object_content ) {
		$mentions = array();
		$mention_context = '';
		if ( class_exists( 'Jetpack_Mentions' ) ) {
			if ( 'post' === $object_type ) {
				$mentions = Jetpack_Mentions::get_post_mentions( $object_id );
			} else {
				$mentions = Jetpack_Mentions::get_comment_mentions( $object_id );
			}

			// Get mention context fragment for new or polled objects
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$current_user = wp_get_current_user();
				if ( $current_user instanceof WP_User && in_array( $current_user->user_login, $mentions ) ) {
					$mention_context = Jetpack_Inline_Terms::find_term_context( "@{$current_user->user_login}", $object_content );
				}
			}
		} else {
			// @todo Internal mentions library for o2
		}

		return array( $mentions, $mention_context );
	}

	public static function to_utf8( $var ) {
		if ( is_object( $var ) || is_array( $var ) ) {
			foreach ( $var as $key => $val ) {
				$var[ $key ] = self::to_utf8( $val );
			}
		} else if ( is_string( $var ) ) {
			$var = mb_convert_encoding( $var, 'UTF-8' );
		}

		return $var;
	}

	/**
	  * Update post for polling (without creating a revision)
	  * Useful for sticky state change, resolved state change, etc.
	  */
	public static function bump_post( $post_id, $post_content = '' ) {
		// We need to pass post_content ourselves to wp_update_post
		// to avoid it re-encoding already encoded content (e.g. &lt; -> &amp;lt;)
		// when "bumping" a post
		if ( empty( $post_content ) ) {
			$post = get_post( $post_id );
			$post_content = $post->post_content;
		}
		$post_content = htmlspecialchars_decode( $post_content, ENT_QUOTES );
		$post_content = apply_filters( 'o2_bump_post_content', $post_content, $post_id );

		// We don't want to create a revision, so remove the wp_save_post_revision post_updated action
		// before updating the post
		$action_tag = 'post_updated';
		$action_func = 'wp_save_post_revision';
		$save_revision_hook_priority = has_action( $action_tag, $action_func );
		if ( $save_revision_hook_priority ) {
			remove_action( $action_tag, $action_func, $save_revision_hook_priority );
		}
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $post_content ) );
		update_post_meta( $post_id, 'client-modified', time() );
		if ( $save_revision_hook_priority ) {
			add_action( $action_tag, $action_func, $save_revision_hook_priority );
		}
	}

	/**
	 * Will update the comment modified time to reflect the current time for polling.
	 *
	 * @param $comment_ID
	 */
	public static function bump_comment_modified_time( $comment_ID ) {
		// We want to leave the comment_date alone and update/create a comment
		// meta entry instead here - o2_comment_gmt_modified (numeric, timestamp, UTC)
		update_comment_meta( $comment_ID, 'o2_comment_gmt_modified', current_time( 'timestamp', true ) );
	}

	/*
	 * Filter the home_url() for permalinks generated during AJAX calls.
	 * When is_admin() is true, permalinks will not reflect the protocol
	 * of the ajax_url.
	 */
	public static function home_url( $url, $path, $orig_scheme, $blog_id ) {
		if ( is_ssl() && 0 === stripos( $url, 'http://' ) ) {
			$url = 'https://' . substr( $url, 7 );
		}

		return $url;
	}

	public static function o2_make_clickable( $content ) {
		// make urls clickable, but exclude text within square brackets - we don't want make_clickable
		// processing between brackets because it messes up not-yet-filtered shortcodes that have url
		// attributes like googlemaps

		$pieces = preg_split( '/(\[[^\]]*])/i', $content, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );

		$content = '';

		foreach( (array) $pieces as $piece ) {
			if ( '[' !== substr( $piece, 0, 1 ) ) {
				$piece = make_clickable( $piece );
			}
			$content .= $piece;
		}

		return $content;
	}
}
