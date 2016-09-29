<?php

if ( class_exists( 'o2_Terms_In_Comments' ) ) :

class o2_Xposts extends o2_Terms_In_Comments {

	/**
	* Stores the blogs, keyed by blog_id, and a list of the subdomains only
	*/
	private $blog_suggestions = array();
	private $subdomains       = array();
	private $registered_blogs = array();

	/**
	* We match on +blogname
	*/
	const XPOSTS_REGEX = '/(?:^|\s|>|\()\+([\w-]+)(?:$|\b|\s|<|\))/';

	function __construct() {
		add_action( 'init',                      array( $this, 'register_taxonomy'           ), 0 );
		add_action( 'switch_blog',               array( $this, 'register_taxonomy'           ) );
		add_action( 'init',                      array( $this, 'get_data'                    ) );
		add_action( 'template_redirect',         array( $this, 'redirect_permalink'          ), 1 );
		add_action( 'wp_footer',                 array( $this, 'inline_js'                   ) );
		add_filter( 'the_content',               array( $this, 'xpost_link_post'             ), 5 );
		add_filter( 'comment_text',              array( $this, 'xpost_link_comment'          ), 5 );
		add_action( 'transition_post_status',    array( $this, 'process_xpost'               ), 12, 3 );
		add_action( 'wp_insert_comment',         array( $this, 'process_xcomment'            ), 12, 2 );
		add_action( 'transition_comment_status', array( $this, 'process_xcomment_transition' ), 12, 3 );
		add_filter( 'o2_found_xposts',           array( $this, 'filter_xposts'               ), 5 );

		// Don't let xposts mess up single-page navigation
		add_filter( 'get_previous_post_where',   array( $this, 'get_adjacent_post_where'     ) );
		add_filter( 'get_next_post_where',       array( $this, 'get_adjacent_post_where'     ) );

		// Filter xcomment permalinks to point immediately at the originating comment
		add_filter( 'o2_comment_permalink', array( $this, 'filter_xcomment_permalink' ), 10, 2 );

		// Don't let xposts participate in resolved/unresolved
		add_filter( 'o2_resolved_posts_maybe_mark_new_as_unresolved', array( $this, 'o2rpx_dont_mark_xposts' ), 10, 2 );

		parent::__construct( 'xposts' );
	}

	/**
	* Generates an array of site information that can be used for the +mentions autocomplete feature
	*
	* @return array Site Information.
	*/
	public function site_suggestions() {
		global $o2;

		if ( ! empty( $this->blog_suggestions[ get_current_blog_id() ] ) )
			return $this->blog_suggestions[ get_current_blog_id() ];

		$this->blog_suggestions[ get_current_blog_id() ] = array();

	 	// @todo convert to MS-compatible, using the subdomain OR the path, depending on the configuration of the site
	 	// @todo move blavatar stuff into wpcom.php filter
	 	$suggestions = array();
 		$user_blogs = get_blogs_of_user( get_current_user_id() );
		foreach ( $user_blogs as $_blog_id => $details ) {

			$blavatar = '';
			if ( function_exists( 'get_blavatar' ) ) {
				$blavatar = get_blavatar( $details->siteurl, 32, 'https://i2.wp.com/wordpress.com/wp-content/themes/h4/tabs/images/defaultavatar.png' );
			}

			$suggestions[ $_blog_id ] = array(
				'blog_id'   => $_blog_id,
				'title'     => $details->blogname,
				'siteurl'   => $details->siteurl,
				'subdomain' => str_replace( '.wordpress.com', '', $details->domain ),
				'blavatar'  => $blavatar
			);
		}

		$combined_suggestions = apply_filters( 'o2_xposts_site_list', $suggestions );

		// Enforce rules for what can/cannot be listed
		$this_privacy = get_option( 'blog_public' );
		$this_id      = get_current_blog_id();
		foreach ( $combined_suggestions as $_id => $blog ) {
			// Never include the current blog in the list
			if ( $_id == $this_id ) {
				continue;
			}

			switch_to_blog( $_id );

			// Must be able to post on the receiving blog
			if ( ! current_user_can( 'edit_posts' ) ) {
				restore_current_blog();
				continue;
			}

			// Receiving blog must have the same privacy settings as the source blog
			if ( get_option( 'blog_public' ) !== $this_privacy ) {
				restore_current_blog();
				continue;
			}

			// Receiving blog must also be running o2 (to handle the xpost properly)
			if ( $o2->is_enabled() )  {
				restore_current_blog();
				continue;
			}

			restore_current_blog();

			// This is OK to include in our suggestions list
			$this->blog_suggestions[ get_current_blog_id() ][ $_id ] = $blog;

			// Cache a list of subdomains, based on full blog list, for matching purposes
			$this->subdomains[ get_current_blog_id() ][] = $blog['subdomain'];
		}

		return $this->blog_suggestions[ get_current_blog_id() ];
	}

	// Until WP 3.8 is widespread, this is a quick, easy, nasty way of avoiding xposts in navigation.
	// See http://core.trac.wordpress.org/ticket/17807#comment:49
	function get_adjacent_post_where( $where ) {
		if ( ! parent::should_process_terms() ) {
			return $where;
		}

		return $where . " AND ( p.post_title NOT LIKE 'x-post%' AND p.post_content NOT LIKE 'x-post%' AND p.post_content NOT LIKE 'x-comment%' )";
	}

	function xpost_link_post( $content ) {
		if ( ! parent::should_process_terms() ) {
			return $content;
		}

		global $post;
		$subdomains = get_post_meta( $post->ID, '_xposts_term_meta' );
		return $this->xpost_links( $content, $subdomains );
	}

	function xpost_link_comment( $content ) {
		if ( ! parent::should_process_terms() ) {
			return $content;
		}

		global $comment;
		$subdomains = get_comment_meta( $comment->comment_ID, '_xposts_term_meta' );
		return $this->xpost_links( $content, $subdomains );
	}

	function xposts_link_callback( $matches ) {
		$subdomain = $matches[1];
		if ( !in_array( $subdomain, $this->subdomains ) ) {
			return $matches[0];
		}

		$search = is_search() ? substr( get_search_query( false ), 1 ) : '';
		$classes = 'po-xpost';

		// If we're searching for this name, highlight it.
		if ( $subdomain === $search ) {
			$classes .= ' o2-xpost-highlight';
		}

		// @todo This is assuming WP.com, which is not compatible with .org
		$replacement = sprintf( '<a href="%s" class="%s">+%s</a>', esc_url( "//$subdomain.wordpress.com/" ), esc_attr( $classes ), esc_html( $subdomain ) );
		$replacement = apply_filters( 'o2_xpost_link', $replacement, $subdomain );
		$replacement = preg_replace( "/(^|\s|>|\()\+" . preg_quote( $subdomain, '/' ) . "($|\b|\s|<|\))/i", '$1' . $replacement . '$2', $matches[0] );

		return $replacement;
	}

	/**
	 * Parses and links mentions within a string.
	 * Run on the_content.
	 *
	 * @param string $content The content.
	 * @return string The linked content.
	 */
	function xpost_links( $content, $subdomains ) {
		if ( empty( $subdomains ) )
			return $content;

		$this->subdomains = $subdomains;
		$textarr = wp_html_split( $content );
		foreach( $textarr as &$element ) {
			if ( '' == $element || '<' === $element[0] || false === strpos( $element, '+' ) ) {
				continue;
			}

			$element = preg_replace_callback( self::XPOSTS_REGEX, array( $this, 'xposts_link_callback' ), $element );
		}
		$this->subdomains = array();
		return join( $textarr );
	}

	function get_details_from_subdomain( $subdomain ) {
		$this->site_suggestions();
		$site = false;
		foreach ( $this->blog_suggestions[ get_current_blog_id() ] as $_blog_id => $blog ) {
			if ( $subdomain === $blog['subdomain'] ) {
				$site = $blog;
				break;
			}
		}
		return $site ? $site : false;
	}

	/**
	 * Register o2 mentions taxonomy.
	 */
	function register_taxonomy() {
		global $o2;

		if ( ! did_action( 'init' ) ) {
			return;
		}

		$blog_id = get_current_blog_id();
		$registered_index = array_search( $blog_id, $this->registered_blogs, true );

		if ( $o2->is_enabled() ) {
			unregister_taxonomy_for_object_type( 'xposts', 'post' );

			if ( false !== $registered_index ) {
				// If o2 has been disabled after the taxonomy was registered,
				// remove the blog from the registered list.
				unset( $this->registered_blogs[ $registered_index ] );
			}
			return;
		}

		if ( false !== $registered_index ) {
			// If the blog has already registered the taxonomy, there's no need to register it again.
			return;
		}

		$this->registered_blogs[] = $blog_id;

		$taxonomy_args = apply_filters( 'o2_xposts_taxonomy_args', array(
			'show_ui'           => false,
			'label'             => __( 'Xposts', 'o2' ),
			'show_in_nav_menus' => false,
			'rewrite'           => array( 'slug' => 'xposts' ),
		) );

		register_taxonomy( 'xposts', 'post', $taxonomy_args );
	}

	function update_post_terms( $post_id, $post ) {
		return $this->find_xposts( $post->post_content );
	}

	function update_comment_terms( $comment_id, $comment ) {
		return $this->find_xposts( $comment->comment_content );
	}

	function find_xposts( $content ) {
		if ( ! parent::should_process_terms() ) {
			return array();
		}

		if ( ! preg_match_all( o2_Xposts::XPOSTS_REGEX, $content, $matches ) )
			return array();

		// Filters found mentions. Passes original found mentions and content as args.
		return apply_filters( 'o2_found_xposts', $matches[1], $matches[1], $content );
	}

	function filter_xposts( $xposts ) {
		if ( ! parent::should_process_terms() ) {
			return $xposts;
		}

		$this->site_suggestions();
		return array_intersect( $xposts, (array) $this->subdomains[ get_current_blog_id() ] );
	}

	/**
	* Makes the sites available as a global JSON encoded array so we can use it with the autocomplete scripts.
	*/
	public function inline_js() {
		if ( ! parent::should_process_terms() ) {
			return;
		}
		?>
		<script type="text/javascript">
			// <![CDATA[
			var xpostData = [];
			<?php if ( is_user_logged_in() ) : ?>
			jQuery( document ).ready( function() {
				jQuery.get( './?get-xpost-data', function( response ) {
					if ( 'undefined' !== typeof response.data ) {
						xpostData = JSON.parse( response.data );
						// Wire up any visible editors with +xposts immediately
						if ( jQuery.isFunction( jQuery.fn.xposts ) ) {
							jQuery( '.o2-editor-text' ).xposts( xpostData );
						}
					}
				} );
			} );
			<?php endif; ?>
			// ]]>
		</script><?php
	}

	public function get_data() {
		if ( ! parent::should_process_terms() ) {
			return;
		}

		if ( isset( $_GET['get-xpost-data'] ) ) {
			if ( is_user_logged_in() ) {
				$response = json_encode( array_values( $this->site_suggestions() ) );
				wp_send_json_success( $response );
			} else {
				die( 0 );
			}
		}
	}

	/**
	 * Fires when the post is published or edited
	 *
	 * @uses get_post_meta and update_post_meta to track if the P2 has already been site-pinged
	 * and avoid multiple cross-posts
	 *
	 * @param boolean $new
	 * @param boolean $old
	 * @param object $post
	 * @return void
	 */
	function process_xpost( $new, $old, $post ) {
		if ( 'publish' != $new )
			return;

		$subdomains = $this->find_xposts( $post->post_content );
		if ( ! $subdomains )
			return;

		foreach ( $subdomains as $subdomain ) {
			$foreign_blog = $this->get_details_from_subdomain( $subdomain );
			if ( ! $foreign_blog )
				continue;

			// Avoid self-posting
			if ( $foreign_blog[ 'blog_id' ] == get_current_blog_id() )
				continue;

			// Check post meta to see if we've already cross-posted to this blog
			$previous_xpost = get_post_meta( $post->ID, 'xpost-' . $foreign_blog[ 'blog_id' ], true );
			if ( ! empty( $previous_xpost ) )
				continue;

			$post_tags = array( 'p2-xpost' );
			if ( $new_id = $this->create_post( $foreign_blog[ 'blog_id' ], $post, $post_tags ) )
				update_post_meta( $post->ID, 'xpost-' . $foreign_blog[ 'blog_id' ], $new_id );
		}
	}

	/**
	 * Fires when the comment is approved or edited.
	 *
	 * @uses ::process_xcomment()
	 *
	 * @param boolean $new
	 * @param boolean $old
	 * @param object $comment
	 * @return void
	 */
	function process_xcomment_transition( $new, $old, $comment ) {
		if ( 'approved' != $new )
			return;

		$this->process_xcomment( $comment->comment_ID, $comment );
	}

	/**
	 * Fires when the comment is published
	 *
	 * Creates a new X-Post if nothing in the origin's post has been X-Posted before.
	 * Creates a new X-Comment on the target site under the appropriate X-Post otherwise.
	 *
	 * @uses get_comment_meta and update_comment_meta to track if the P2 has already been site-pinged
	 * and avoid multiple cross-comments
	 *
	 * @param boolean $new
	 * @param boolean $old
	 * @param object $comment
	 * @return void
	 */
	function process_xcomment( $comment_id, $comment ) {
		$subdomains = $this->find_xposts( $comment->comment_content );
		if ( !$subdomains )
			return;

		$post = get_post( $comment->comment_post_ID );

		foreach ( $subdomains as $subdomain ) {
			$foreign_blog = $this->get_details_from_subdomain( $subdomain );
			if ( ! $foreign_blog )
				continue;

			// Avoid self-posting
			if ( $foreign_blog['blog_id'] == get_current_blog_id() )
				continue;

			// Check comment meta to see if we've already cross-commented to this blog
			$previous_xcomment = get_comment_meta( $comment->comment_ID, 'xcomment-' . $foreign_blog['blog_id'], true );
			if ( !empty( $previous_xcomment ) )
				continue;

			// Have we already cross-posted this comment?
			$previous_xcomment_as_post = get_comment_meta( $comment->comment_ID, 'xpost-' . $foreign_blog['blog_id'], true );
			if ( !empty( $previous_xcomment_as_post ) )
				continue;

			// Is there a cross-post on the target site for this comment's post already?
			$previous_xpost = (int) get_post_meta( $post->ID, 'xpost-' . $foreign_blog['blog_id'], true );

			$post_tags = array( 'p2-xpost' );
			if ( !$new_id = $this->create_post( $foreign_blog['blog_id'], $comment, $post_tags, $previous_xpost ) )
				continue;

			if ( !$previous_xpost ) {
				// Note the x-post in post meta and in comment meta (previous_xcomment_as_post and previous_xpost above)
				update_post_meta( $post->ID, 'xpost-' . $foreign_blog['blog_id'], $new_id );
				update_comment_meta( $comment->comment_ID, 'xpost-' . $foreign_blog['blog_id'], "post-{$new_id}" );
			} else {
				// Note the x-comment in comment meta (previous_xcomment above)
				update_comment_meta( $comment->comment_ID, 'xcomment-' . $foreign_blog['blog_id'], $new_id );
			}
		}
	}

	/**
	 * Create new post on target P2-themed site
	 * Uses the permalink of the source post for the content and title of the new post
	 *
	 * @param int $_blog_id
	 * @param object $post
	 * @param array $tags
	 * @return int $new_id
	 */
	function create_post( $_blog_id, $post, $tags = array(), $post_parent_id = 0 ) {
		$this_blog = get_blog_details( get_current_blog_id() );

		$new_id = null;

		// @todo Make this more wp.com-agnostic
		$origin_subdomain = str_replace( '.wordpress.com', '', $this_blog->domain );
		$origin_blog_id   = $this_blog->blog_id;
		$origin_post_id   = null;
		$target_details   = get_blog_details( $_blog_id );
		$target_subdomain = str_replace( '.wordpress.com', '', $target_details->domain );

		// Set up the new post's content. For ex:
		// X-post from #socialp2: <a href="permalink">Post Title</a>
		// X-comment from #socialp2: <a href="permalink">Comment on Post Title</a>

		if ( isset( $post->comment_ID ) ) {
			// It's really a comment
			$comment        = $post;
			$post           = get_post( $post->comment_post_ID );
			$origin_post_id = $post->ID;
			$x_permalink    = get_comment_link( $comment->comment_ID );
			$format         = __( 'X-comment from %1$s: Comment on %2$s', 'o2' );
			$author         = $comment->user_id;
		} else {
			// It's a post
			$format         = __( 'X-post from %1$s: %2$s', 'o2' );
			$origin_post_id = $post->ID;
			$x_permalink    = get_permalink( $post->ID );
			$author         = $post->post_author;
		}

		$post_title = wp_kses( $post->post_title, array() ); // Used as anchor text for display back to original post
		$post_long_content = trim( wp_html_excerpt( $post->post_content, 800 ) ) . '&hellip;'; // Add short preview to the original post's full content

		// Avoid triggering display re-filters for mentions and xposts
		$post_title = str_replace( array( '#', '@', '+' ), array( '&#35;', '&#64;', '&#43;' ), $post_title );
		$post_long_content = str_replace( array( '#', '@', '+' ), array( '&#35;', '&#64;', '&#43;' ), $post_long_content );

		// Strip any shortcodes out of the tooltip as they can generate invalid html
		$post_long_content = strip_shortcodes( $post_long_content );

		$origin_link = sprintf( '<a href="%1$s" title="%2$s">%3$s</a>',
			trailingslashit( esc_url( $this_blog->siteurl ) ),
			esc_attr( $this_blog->blogname ),
			"&#43;$origin_subdomain" // Avoid triggering display re-filters for xposts
		);

		$post_content = sprintf(
			$format,
			$origin_link,
			sprintf( '<a href="%1$s" title="%2$s">%3$s</a>',
				$x_permalink,
				esc_html( $post_long_content ),
				esc_html( $post_title )
			)
		);
		array_push( $tags, $origin_subdomain );

		switch_to_blog( $_blog_id );

		if ( $post_parent_id ) {
			// Create comment on original xpost
			$args = array(
				'comment_post_ID' => $post_parent_id,
				'user_id' => get_current_user_id(),
				'comment_content' => $post_content,
			);

			$new_id = wp_insert_comment( $args );

			// Prevent sending x-post *back* to origin when this comment is edited
			update_comment_meta( $new_id, 'xcomment-' . $origin_blog_id, $new_id );

			// link back to the origin
			update_comment_meta( $new_id, 'xcomment_origin', $origin_blog_id . ':' . $origin_post_id );

			// Log the original comment permalink for filtering permalinks later
			update_comment_meta( $new_id, 'xcomment_original_permalink', $x_permalink );
		} else {
			// Create new xpost
			if ( ! current_user_can( 'edit_posts' ) )
				return;

			$args = array(
				'post_name'    => 'xpost-' . $post->post_name,
				'post_content' => $post_content,
				'post_status'  => 'publish',
				'post_title'   => "X-post: $post_title",
				'post_author'  => $author, // either post or comment author
			);


			// @todo make sure notifications dont go out for xposts when we build the network features out properly
			do_action( 'xpost_notify_before', $args );
			$new_id = wp_insert_post( $args );
			do_action( 'xpost_notify_after', $args );

			// Prevent sending x-post *back* to origin when this post is edited
			update_post_meta( $new_id, 'xpost-' . $origin_blog_id, $new_id );

			// link back to the origin
			update_post_meta( $new_id, 'xpost_origin', $origin_blog_id . ':' . $origin_post_id );

			// Log the original post permalink so we can redirect to it
			update_post_meta( $new_id, '_xpost_original_permalink', $x_permalink );

			wp_set_object_terms( $new_id, array( 'status' ), 'category' );
			if ( ! empty( $tags ) )
				wp_set_object_terms( $new_id, $tags, 'post_tag' );

			clean_post_cache( $new_id );
		}

		// @todo extract into an action
		bump_stats_extras( 'a8c-p2-xpost-received', $target_subdomain );
		bump_stats_extras( 'a8c-p2-xpost-sent', $origin_subdomain );

		restore_current_blog();

		return $new_id;
	}

	/**
	 * X-posts permalinks pages are useless, let's redirect them to the original post
	 *
	 * @uses get_post_meta, wp_redirect
	 * @return void
	 */
	function redirect_permalink( $query ) {
		if ( ! parent::should_process_terms() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( is_single() && $link = get_post_meta( $post_id, '_xpost_original_permalink', true ) ) {
			wp_redirect( $link );
			exit;
		}
	}

	// Filter xcomment permalinks to point immediately at the originating comment (and not
	// rely on redirect_permalink, since we can't see the comment fragment identifer in the query
	// and because browsers will -incorrectly- preserve the fragment identifer across redirects)
	function filter_xcomment_permalink( $permalink, $comment_ID ) {
		$original_comment_permalink = get_comment_meta( $comment_ID, 'xcomment_original_permalink', true );
		if ( ! empty( $original_comment_permalink ) ) {
			$permalink = $original_comment_permalink;
		}
		return $permalink;
	}

	/* xposts should not participate in resolved/unresolved state */
	function o2rpx_dont_mark_xposts( $true, $post ) {
		if ( false !== stripos( $post->post_name, 'xpost-' ) ) {
			return false;
		}

		return $true;
	}
}

endif; // class_exists
