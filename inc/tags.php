<?php

if ( class_exists( 'o2_Terms_In_Comments' ) ) :

class o2_Tags extends o2_Terms_In_Comments {

	/**
	* We match on #tag
	*/
	const TAGS_REGEX = '/(?:^|\s|>|\()#(?!\d{1,2}(?:$|\s|<|\)|\p{P}{1}\s))([\p{L}\p{N}\_\-\.]*[\p{L}\p{N}]+)(?:$|\b|\s|<|\))/iu';


	function __construct() {
		add_action( 'wp_ajax_o2_tag_search',  array( $this, 'tag_search' ) );
		add_action( 'transition_post_status', array( $this, 'process_tags' ), 12, 3 );

		add_filter( 'the_content',            array( 'o2_Tags', 'append_old_tags' ), 14 );
		if ( ! is_admin() || ( isset( $_GET['action'] ) && ( 'o2_read' === $_GET['action'] || 'o2_write' === $_GET['action'] ) ) ) {
			add_filter( 'the_content',            array( 'o2_Tags', 'tag_links' ), 15 );
		}

		add_filter( 'comment_text',           array( 'o2_Tags', 'tag_links' ), 15 );
		add_filter( 'o2_post_fragment',       array( $this, 'append_old_tags_to_fragment' ), 10, 1 );
		add_filter( 'o2_bump_post_content',   array( 'o2_Tags', 'append_old_tags_raw' ), 10, 2 );

		parent::__construct( 'post_tag' );
	}

	/**
	 * Appends the tags of old posts to the end of the post
	 *
	 * @uses global $post, so must be run from inside the loop
	 *
	 * @param string $content The content
	 * @return string The content with any old tags appended
	 */
	static function append_old_tags( $content ) {
		global $post;
		if ( empty( $post ) ) {
			return $content;
		}

		if ( ! apply_filters( 'o2_process_the_content', true ) ) {
			return $content;
		}

		// if this is an xpost, don't bother looking for tags
		$xpost = get_post_meta( $post->ID, '_xpost_original_permalink', true );
		if ( ! empty( $xpost ) ) {
			return $content;
		}

		$content_tags = o2_Tags::find_tags( $content, true );
		$content_tags = array_map( 'strtolower', $content_tags );
		$content_tags = array_unique( $content_tags );

		$tags = o2_Fragment::get_post_tags( $post->ID );

		if ( ! empty( $tags ) ) {
			$tag_slugs = array();
			foreach ( $tags as $tag ) {
				if ( ! in_array( strtolower( $tag->slug ), $content_tags ) && ! in_array( strtolower( $tag->name ), $content_tags ) ) {
					$tag_slugs[] = '#' . $tag->slug;
				}
			}

			if ( ! empty( $tag_slugs ) ) {
				$content .= '<p class="o2-appended-tags">' . implode( ', ', $tag_slugs ) . '</p>';
			}
		}

		return $content;
	}

	/**
	 * Append the tags of old posts to the end of the post, as a comma separated list.
	 *
	 * @param string $content The raw content to be processed
	 * @param int $post_id The post ID that the content belongs to
	 * @return string The raw content, with tags appended
	 */
	function append_old_tags_raw( $content, $post_id ) {
		$content_tags = o2_Tags::find_tags( $content, true );
		$content_tags = array_map( 'strtolower', $content_tags );
		$content_tags = array_unique( $content_tags );

		$tags = o2_Fragment::get_post_tags( $post_id );

		if ( ! empty( $tags ) ) {
			$tag_slugs = array();
			foreach ( $tags as $tag ) {
				if ( ! in_array( strtolower( $tag->slug ), $content_tags ) && ! in_array( strtolower( $tag->name ), $content_tags ) ) {
					$tag_slugs[] = '#' . $tag->slug;
				}
			}

			if ( ! empty( $tag_slugs ) ) {
				$content .= "\n\n" . implode( ', ', $tag_slugs );
			}
		}

		return $content;
	}

	/**
	 * Appends the tags of old posts to the end of the post, for o2 fragments
	 *
	 * @param string $fragment The fragment
	 * @return string The fragment with any old tags appended to contentRaw
	 */
	function append_old_tags_to_fragment( $fragment ) {
		$fragment['contentRaw'] = $this->append_old_tags_raw( $fragment['contentRaw'], $fragment['id'] );
		return $fragment;
	}

	/**
	 * Parses and links tags within a string.
	 * Run on the_content and comment_text.
	 *
	 * @param string $content The content.
	 * @return string The linked content.
	 */
	static function tag_links( $content ) {
		if ( empty( $content ) )
			return $content;

		if ( ! apply_filters( 'o2_process_the_content', true ) ) {
			return $content;
		}

		$tags = o2_Tags::find_tags( $content, true );
		$tags = array_unique( $tags );
		usort( $tags, array( 'o2_Tags', '_sortByLength' ) );

		static $tag_links = array();
		static $tag_info = array();

		foreach ( $tags as $tag ) {
			if ( isset( $tag_info[ $tag ] ) ) {
				continue;
			}

			$info = get_term_by( 'slug', $tag, 'post_tag' );
			if ( ! $info ) {
				$info = get_term_by( 'name', $tag, 'post_tag' );
			}
			$tag_info[ $tag ] = $info;
		}

		$content = wp_pre_kses_less_than( $content );
		$content = wp_kses_normalize_entities( $content );

		if ( class_exists( 'WPCOM_Safe_DOMDocument' ) ) {
			$dom = new WPCOM_Safe_DOMDocument;
		} else {
			$dom = new DOMDocument;
		}
		@$dom->loadHTML( '<?xml encoding="UTF-8">' . $content );

		$xpath = new DOMXPath( $dom );
		$textNodes = $xpath->query( '//text()' );

		foreach( $textNodes as $textNode ) {
			if ( ! $textNode->parentNode ) {
				continue;
			}

			$parent = $textNode;
			while( $parent ) {
				if ( ! empty( $parent->tagName ) && in_array( strtolower( $parent->tagName ), array( 'pre', 'code', 'a', 'script', 'style', 'head' ) ) ) {
					continue 2;
				}
				$parent = $parent->parentNode;
			}

			$text = $textNode->nodeValue;

			$totalCount = 0;
			foreach ( $tags as $tag ) {
				if ( empty( $tag_info[ $tag ] ) ) {
					continue;
				}

				if ( empty( $tag_links[ $tag ] ) ) {
					$tag_url = get_tag_link( $tag_info[ $tag ] );

					$replacement = "<a href='" . esc_url( $tag_url ) . "' class='tag'><span class='tag-prefix'>#</span>" . htmlentities( $tag ) . "</a>";
					$replacement = apply_filters( 'o2_tag_link', $replacement, $tag );

					$tag_links[ $tag ] = $replacement;
				} else {
					$replacement = $tag_links[ $tag ];
				}

				$count = 0;
				$text = preg_replace( "/(^|\s|>|\()#$tag(($|\b|\s|<|\)))/", '$1' . $replacement . '$2', $text, -1, $count );
				$totalCount += $count;
			}

			if ( ! $totalCount ) {
				continue;
			}

			$text = wp_pre_kses_less_than( $text );
			$text = wp_kses_normalize_entities( $text );

			if ( class_exists( 'WPCOM_Safe_DOMDocument' ) ) {
				$newNodes = new WPCOM_Safe_DOMDocument;
			} else {
				$newNodes = new DOMDocument;
			}
			@$newNodes->loadHTML( '<?xml encoding="UTF-8"><div>' . $text . '</div>' );

			foreach( $newNodes->getElementsByTagName( 'body' )->item( 0 )->childNodes->item( 0 )->childNodes as $newNode ) {
				$cloneNode = $dom->importNode( $newNode, true );
				if ( ! $cloneNode ) {
					continue 2;
				}
				$textNode->parentNode->insertBefore( $cloneNode, $textNode );
			}

			$textNode->parentNode->removeChild( $textNode );
		}

		$html = '';

		// Sometime, DOMDocument will put things in the head instead of the body.
		// We still need to keep them in our output.
		$search_tags = array( 'head', 'body' );
		foreach ( $search_tags as $tag ) {
			$list = $dom->getElementsByTagName( $tag );
			if ( 0 === $list->length ) {
				continue;
			}
			foreach ( $list->item( 0 )->childNodes as $node ) {
				$html .= $dom->saveHTML( $node );
			}
		}

		return $html;
	}

	static function _sortByLength( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	}

	function update_post_terms( $post_id, $post ) {
		return $this->gather_all_tags( $post );
	}

	function update_comment_terms( $comment_id, $comment ) {
		$tags = array();

		$inline_tags = o2_Tags::find_tags( $comment->comment_content, true );
		if ( ! empty( $inline_tags ) ) {
			$tags = array_unique( $inline_tags );
		}
		return $tags;
	}

	static function find_tags( $content, $htmlified = true ) {
		if ( ! $htmlified ) {
			$current_filter = current_filter();

			// Deal with comment content
			if ( 'comment_text' === $current_filter ) {
				remove_filter( 'comment_text', array( 'o2_Tags', 'tag_links' ), 15 );
				$content = apply_filters( 'comment_text', $content );
				add_filter( 'comment_text', array( 'o2_Tags', 'tag_links' ), 15 );

			// Default to post content
			} elseif ( 'the_content' === $current_filter ) {
				remove_filter( 'the_content', array( 'o2_Tags', 'append_old_tags' ), 14 );
				remove_filter( 'the_content', array( 'o2_Tags', 'tag_links' ), 15 );
				$content = apply_filters( 'the_content', $content );
				add_filter( 'the_content', array( 'o2_Tags', 'append_old_tags' ), 14 );
				add_filter( 'the_content', array( 'o2_Tags', 'tag_links' ), 15 );
			}
		}

		$tags = array();

		$content = wp_pre_kses_less_than( $content );
		$content = wp_kses_normalize_entities( $content );

		if ( class_exists( 'WPCOM_Safe_DOMDocument' ) ) {
			$dom = new WPCOM_Safe_DOMDocument;
		} else {
			$dom = new DOMDocument;
		}
		@$dom->loadHTML( '<?xml encoding="UTF-8">' . $content );

		$xpath = new DOMXPath( $dom );
		$textNodes = $xpath->query( '//text()' );

		foreach ( $textNodes as $textNode ) {
			if ( ! $textNode->parentNode ) {
				continue;
			}

			$parent = $textNode;
			while ( $parent ) {
				if ( ! empty( $parent->tagName ) && in_array( strtolower( $parent->tagName ), array( 'pre', 'code', 'a', 'script', 'style', 'head' ) ) ) {
					continue 2;
				}
				$parent = $parent->parentNode;
			}

			$matches = array();
			if ( preg_match_all( o2_Tags::TAGS_REGEX, $textNode->nodeValue, $matches ) ) {
				$tags = array_merge( $tags, $matches[1] );
			}
		}

		// Filters found tags. Passes original found tags and content as args.
		return apply_filters( 'o2_found_tags', $tags, $content );
	}

	/**
	 * Fires when the post is published or edited
	 *
	 * @param boolean $new Status being switched to
	 * @param boolean $old Status being switched from
	 * @param object $post The full Post object
	 * @return void
	 */
	function process_tags( $new, $old, $post ) {
		if ( 'publish' !== $new )
			return;

		$tags = $this->gather_all_tags( $post );

		wp_set_post_tags( $post->ID, $tags, false );
	}

	function gather_all_tags( $post ) {
		$tags = array();

		// If we're on wp-admin, then tags are POSTed (Quick Edit) or GETed (Bulk Edit).
		$new_tags = array();
		if ( ! empty( $_POST['tax_input']['post_tag'] ) ) {
			$new_tags = $_POST['tax_input']['post_tag'];
		} else if ( ! empty( $_GET['tax_input']['post_tag'] ) ) {
			$new_tags = explode( ',', $_GET['tax_input']['post_tag'] );
			$old_tags = wp_get_post_terms( $post->ID, 'post_tag' );
			foreach ( $old_tags as $old_tag ) {
				$new_tags[] = $old_tag->slug;
			}
			$new_tags = array_unique( $new_tags );
		}

		if ( ! empty( $new_tags ) ) {
			if ( is_array( $new_tags ) ) {
				$post_tags = $new_tags;
			} else {
				$post_tags = preg_split( '/\s*,\s*/', $new_tags );
			}
			foreach ( $post_tags as $tag ) {
				if ( is_int( $tag ) ) {
					/*
					 * Deal with edit_post() which converts taxonomy input to term IDs to avoid ambiguity.
					 * See https://core.trac.wordpress.org/changeset/31359
					 */
					$term = get_term( $tag, 'post_tag' );
					if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
						$tags[] = $term->slug;
					}
				} else {
					$tags[] = trim( $tag );
				}
			}
		}

		// Extract inline tags from the post_content.
		$inline_tags = o2_Tags::find_tags( $post->post_content, true );
		if ( ! empty( $inline_tags ) ) {
			$tags = array_merge( $tags, $inline_tags );
		}

		$tags = array_unique( $tags );
		return $tags;
	}

	public function tag_search() {
		global $wpdb;
		$term = $_GET['term'];
		if ( false !== strpos( $term, ',' ) ) {
			$term = explode( ',', $term );
			$term = $term[count( $term ) - 1];
		}
		$term = trim( $term );
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array() ); // require 2 chars for matching
		}

		$tags = array();
		$like = "%" . $wpdb->esc_like( $term ) . "%";
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT name, slug, count FROM $wpdb->term_taxonomy AS tt INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = 'post_tag' AND ( t.name LIKE ( %s ) OR t.slug LIKE ( %s ) ) ORDER BY count DESC", $like, $like ) );

		foreach ( (array) $results as $result ) {
			// translators: 'tag name (count)'
			$label = sprintf( __( '%1$s (%2$s)', 'o2' ), $result->name, $result->count );

			$count_posts = sprintf( _n( '1 post', '%s posts', $result->count, 'o2' ), number_format_i18n( $result->count ) );

			$tags[] = array(
				'label'       => $label, // @todo remove
				'count'       => $result->count, // Required for sorting
				'count_posts' => $count_posts, // localized count label
				'value'       => $result->name,
				'slug'        => $result->slug
			);
		}

		wp_send_json_success( $tags );
	}
}

endif; // class_exists
