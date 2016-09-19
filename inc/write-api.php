<?php

class o2_Write_API extends o2_API_Base {
	public static function init() {
		do_action( 'o2_write_api' );

		// Need a 'method' of some sort
		if ( empty( $_REQUEST['method'] ) ) {
			self::die_failure( 'no_method', __( 'No method supplied', 'o2' ) );
		}
		$method = strtolower( $_REQUEST['method'] );

		// Only allow Backbone core methods. Read is not supported (use o2_read feed for that)
		if ( in_array( $method, array( 'create', 'update', 'patch', 'delete' ) ) ) {
			// Need a 'message' to work with
			if ( empty( $_REQUEST['message'] ) ) {
				self::die_failure( 'no_message', __( 'No message supplied', 'o2' ) );
			}

			// Check the nonce
			$nonce = stripslashes( $_REQUEST['nonce'] );
			if ( ! wp_verify_nonce( $nonce, 'o2_nonce' ) ) {
				self::die_failure( 'invalid_nonce', __( 'Invalid or expired nonce', 'o2' ) );
			}

			// Make sure we can decode the Message
			$message = json_decode( stripslashes( $_REQUEST['message'] ) );
			if ( null === $message ) {
				self::die_failure( 'invalid_message', __( 'Message could not be decoded', 'o2' ) );
			}

			// We only support post and comment messages (for now)
			if ( !in_array( $message->type, apply_filters( 'o2_supported_message_types', array( 'post', 'comment', 'upload' ) ) ) ) {
				self::die_failure( 'unknown_message_type', __( 'Unknown message type passed', 'o2' ) );
			}

			// Handle different message types
			switch ( $message->type ) {
			case 'comment':
				o2_Write_API::init_comment( $message );
				o2_Write_API::write_comment( $message, $method );
				break;

			case 'post':
				o2_Write_API::write_post( $message, $method );
				break;

			case 'upload':
				o2_Write_API::write_upload();
				break;

			default:
				self::die_success( '1' );
			}
		}

		self::die_failure( 'unknown_method', __( 'Unknown/unsupported method supplied', 'o2' ) );
	}

	public static function write_post( $message, $method ) {
		global $post;
		switch ( $method ) {
		case 'delete':
			if ( ! current_user_can( 'delete_post', $message->postID ) ) {
				self::die_failure( 'cannot_trash_post', __( 'You are not allowed to trash this post', 'o2' ) );
			}
			apply_filters( 'o2_trash_post', $post, $message, $method );

			wp_trash_post( $message->postID );

			do_action( 'o2_writeapi_post_trashed', $message->postID );

			die( '1' );

		case 'update':
			if ( ! current_user_can( 'edit_post', $message->postID ) ) {
				self::die_failure( 'cannot_edit_post', __( 'You are not allowed to edit this post', 'o2' ) );
			}

			// Load existing post
			$post = get_post( $message->postID );
			if ( ! $post ) {
				self::die_failure( 'post_not_found', __( 'Post not found.', 'o2' ) );
			}

			// Allow plugins to hook in
			apply_filters( 'o2_update_post', $post, $message, $method );

			// Merge data that the user can modify on the front end
			$post->post_content = $message->contentRaw; // Don't addslashes() here
			$post->post_title   = $message->titleRaw;

			if ( empty( $post->post_title ) ) {
				$post->post_title = wp_trim_words( $message->contentRaw, 5 );
			}

			// Save it
			$id = wp_update_post( $post );

			// Set post format
			$postFormat = $message->postFormat;
			if ( 'standard' == $postFormat ) {
				$postFormat = ''; // a "standard" post actually takes no format at all
			}
			set_post_format( $id, $postFormat );

			// We must store this in postmeta, because WP doesn't allow us to manually
			// control wp_posts.post_modified[_gmt].
			if ( !empty( $message->unixtimeModified ) ) {
				update_post_meta( $id, 'client-modified', $message->unixtimeModified );
			}

			// Reload full object from WP
			$post = get_post( $id );
			setup_postdata( $post );

			o2_Write_API::update_orphan_attachments( $post );

			do_action( 'o2_writeapi_post_updated', $id, $message );

			// Send back updated Fragment
			self::die_success( o2_Fragment::get_fragment( $post ) );

		case 'patch':
			if ( ! current_user_can( 'edit_post', $message->postID ) ) {
				self::die_failure( 'cannot_edit_post', __( 'You are not allowed to edit this post', 'o2' ) );
			}

			// We must store this in postmeta, because WP doesn't allow us to manually
			// control wp_posts.post_modified[_gmt].
			if ( !empty( $message->unixtimeModified ) ) {
				update_post_meta( $message->postID, 'client-modified', $message->unixtimeModified );
			}

			do_action( 'o2_callback_' . $message->pluginData->callback, $message->pluginData->data );
			self::die_failure( 'no_callback_action_taken', __( 'No callback action taken.', 'o2' ) );

		case 'create':
			if ( ! current_user_can( 'publish_posts' ) ) {
				self::die_failure( 'cannot_publish_posts', __( 'You are not allowed to publish new posts', 'o2' ) );
			}

			if ( 'standard' !== $message->postFormat || empty( $message->titleRaw ) ) {
				$message->titleRaw = wp_trim_words( $message->contentRaw, 5, '...' );
			}

			if ( 'aside' === $message->postFormat && ! $message->disableAutoTitle ) {
				$message = o2_Write_API::generate_title( $message );
			}

			// Set up a basic Post object
			$post = get_default_post_to_edit( 'post', true );

			$post->post_author  = get_current_user_id();
			$post->post_content = addslashes( $message->contentRaw );
			$post->post_title   = $message->titleRaw;
			$post->post_status  = 'publish';

			$post = apply_filters( 'o2_create_post', $post, $message, $method );

			// if syntaxhighlighter is being used...
			// because we use get_default_post_to_edit above,
			// syntax highlighter thinks it has already applied its encoding magic
			// to tags inside code blocks

			// so we need to tell it to do it again when we hit content_save_pre
			// during wp_insert_post below in order for tags inside code blocks
			// to survive the trip

			global $SyntaxHighlighter;
			if ( is_a( $SyntaxHighlighter, 'SyntaxHighlighter' ) ) {
				$SyntaxHighlighter->content_save_pre_ran = false;
			}

			// Save it
			$id = wp_insert_post( $post );

			// Set post format
			if ( 'standard' == $message->postFormat ) {
				$message->postFormat = ''; // a "standard" post actually takes no format at all
			}
			set_post_format( $id, $message->postFormat );

			// We must store this in postmeta, because WP doesn't allow us to manually
			// control wp_posts.post_modified[_gmt].
			if ( !empty( $message->unixtimeModified ) ) {
				update_post_meta( $id, 'client-modified', $message->unixtimeModified );
			}

			// Reload full object from WP
			$post = get_post( $id );
			setup_postdata( $post );

			o2_Write_API::update_orphan_attachments( $post );

			do_action( 'o2_writeapi_post_created', $id, $message );

			// Send back updated Message in standard form
			self::die_success( o2_Fragment::get_fragment( $post ) );
		}
	}

	public static function generate_title( $message ) {
		$lines = explode( "\n", trim( $message->contentRaw ) );
		if ( count( $lines ) <= 1 ) {
			return $message;
		}

		$firstLine = trim( $lines[0] );
		preg_match_all( '/\S+/', $firstLine, $words );

		// We have a bunch of reasons to not auto-title the first line...
		if ( empty( $words[0] ) ) {
			return $message;
		}

		if ( count( $words[0] ) > 8 ) {
			return $message;
		}

		// Don't auto-title if it's part of a list
		if ( preg_match( '/^([ox\-*]|1\.) /', $firstLine ) ) {
			return $message;
		}

		// Special case: don't auto-title if it's part of a numbered list, but it might be a Markdown title
		if ( '# ' === substr( $firstLine, 0, 2 ) && count( $lines ) > 1 && '# ' === substr( $lines[1], 0, 2 ) ) {
			return $message;
		}

		// Don't auto-title if there's a mention/tag/xpost
		if ( preg_match( '/(?:^|\s|>|\b|\()[@+#]([\w-\.]*\w+)(?:$|\s|<|\b|\))/', $firstLine ) ) {
			return $message;
		}

		// Don't auto-title if there's a URL
		if ( $firstLine !== make_clickable( $firstLine ) ) {
			return $message;
		}

		// Don't auto-title if there are HTML tags
		if ( $firstLine !== strip_tags( $firstLine ) ) {
			return $message;
		}

		// Don't auto-title if there's a shortcode
		if ( $firstLine !== do_shortcode( $firstLine ) ) {
			return $message;
		}

		$message->postFormat = 'standard';
		$message->titleRaw = $firstLine;
		$message->contentRaw = trim( substr_replace( $message->contentRaw, '', 0, strlen( $message->titleRaw ) ) );

		return $message;
	}

	/**
	 * Get things set up to handle a comment. We need to hook into some things because
	 * we use the core file (wp-comments-post.php) to do all the processing.
	 */
	public static function init_comment( $message ) {
		if ( !empty( $message->postID ) ) {
			global $post;
			$post = get_post( $message->postID );
		}
		// Catch a bunch of actions that are fired during errors and JSON-ify them
		$catch_actions = array(
			'comment_id_not_found',
			'comment_closed',
			'comment_on_trash',
			'comment_on_draft',
			'comment_on_password_protected'
		);
		foreach ( $catch_actions as $action )
			add_action( $action, array( 'o2_Write_API', 'comment_fail' ) );

		// This is called once a comment has successfully posted
		add_action( 'set_comment_cookies', array( 'o2_Write_API', 'comment_success' ) );
	}

	/**
	 * Handle a variety of hard comment posting failures. See ::init_comment() for the list
	 * of actions that trigger this.
	 */
	public static function comment_fail( $id ) {
		self::die_failure( 'comment_failed', sprintf( __( 'Failed to post comment on post ID %s. %s', 'o2' ), $id, current_filter() ) );
	}

	// http://scribu.net/wordpress/prevent-blog-authors-from-editing-comments.html
	// Allows all users to edit their own comments, and Editors and above to edit everyone's
	public static function restrict_comment_editing( $caps, $cap, $user_id, $args ) {
		if ( 'edit_comment' === $cap ) {
			$comment = get_comment( $args[0] );

			if ( $comment->user_id != $user_id ) {
				// Only Editors can edit all comments
				$caps[] = 'moderate_comments';
			} else {
				// Everyone can edit their own comments, always
				$caps = array( 'read' );
			}
		}

		return $caps;
	}


	/**
	 * Handle a few different variations of writing a comment.
	 */
	public static function write_comment( $message, $method ) {
		switch ( $method ) {
		case 'delete':
			// @todo comment delete
			// @todo capability check
			self::die_failure( 'delete_comment_not_supported', __( 'Deleting comments is not supported yet', 'o2' ) );

		case 'update':
			// Update an existing comment

			add_filter( 'map_meta_cap', array( 'o2_Write_API', 'restrict_comment_editing' ), 10, 4 );
			if ( ! current_user_can( 'edit_comment', $message->id ) ) {
				self::die_failure( 'cannot_edit_comment', __( 'You are not allowed to edit this comment', 'o2' ) );
			}
			remove_filter( 'map_meta_cap', array( 'o2_Write_API', 'restrict_comment_editing' ), 10 );

			// Get current comment data
			$comment_status = wp_get_comment_status( $message->id );

			// Assume that if comment_status is false, that the comment has been deleted.
			if ( false == $comment_status ) {
				self::die_failure( 'comment_already_deleted', __( 'Comment has already been deleted by another session.', 'o2' ) );
			} else if ( 'trash' != $comment_status && $message->isTrashed ) {
				if ( ! wp_trash_comment( $message->id ) ) {
					self::die_failure( 'trash_comment_failed', __( 'Trashing that comment failed.', 'o2' ) );
				}
				do_action( 'o2_writeapi_comment_trashed', $message->id, $message );
			} else if ( 'trash' == $comment_status && ! $message->isTrashed ) {
				if ( ! wp_untrash_comment( $message->id ) ) {
					self::die_failure( 'untrash_comment_failed', __( 'Untrashing that comment failed.', 'o2' ) );
				}
				do_action( 'o2_writeapi_comment_untrashed', $message->id, $message );
			} else {
				// Load comment data, merge in new stuff, then save again
				$comment = get_comment( $message->id );
				$comment->comment_content = addslashes( $message->contentRaw );
				wp_update_comment( (array) $comment );

				// Modifying trash status is bumped in o2:bump_trashed_comment based on trash actions.
				o2_Fragment::bump_comment_modified_time( $message->id );

				do_action( 'o2_writeapi_comment_updated', $message->id, $message );
			}

			// Reload the full, clean object and output it
			$comment = get_comment( $message->id );

			self::die_success( o2_Fragment::get_fragment( $comment ) );

		case 'create':
			// Posting a new comment. We use the core-provided file to
			// avoid re-implementing a bunch of logic

			// Re-map some incoming data to match the expected _POST elements
			$remap = array(
				'comment_post_ID' => $message->postID,
				'comment'         => addslashes( $message->contentRaw ),
				'comment_parent'  => $message->parentID,
			);
			$_POST = array_merge( $_POST, $remap );

			// Let the core comment handler take it from here
			// Have to suppress warnings from wp-comments-post because ABSPATH gets redefined
			global $wpdb, $user_ID;
			@require_once ABSPATH . 'wp-comments-post.php';

			// If we get here, it means the core actions weren't fired, so
			// something most likely went wrong
			self::die_failure( 'unknown_comment_error', __( 'Failed to post comment.', 'o2' ) );
		}
	}

	/**
	 * Called from the end of the core commenting process. If we get here
	 * then the comment was posted successfully, so just output it and die.
	 */
	public static function comment_success( $comment ) {
		$message = json_decode( stripslashes( $_REQUEST['message'] ) );

		do_action( 'o2_writeapi_comment_created', $comment->comment_ID , $message );
		self::die_success( o2_Fragment::get_fragment( $comment ) );
	}

	/**
	 *
	 * Ceate an attachment for images and return image tag or gallery shortcode to be rendered in the editor
	 */
	public static function write_upload() {
		global $content_width;
		$_POST['action'] = 'wp_handle_upload';

		$images = array();
		$files  = array();
		$videos = array();
		$errors = array();
		$output = '';

		for ( $i = 0; $i < $_POST['num_files']; $i++ ) {
			// Create attachment for the image.
			$attachment_id = media_handle_upload( "file_$i", 0 );

			if ( is_wp_error( $attachment_id ) ) {
				do_action( 'o2_error', 'o2_image_upload' );
				$error = array( $_FILES["file_$i"]['name'], $attachment_id->get_error_message() );
				array_push( $errors, $error );
			} else {
				$type = wp_check_filetype( $_FILES["file_$i"]['name'] );
				if ( wp_attachment_is_image( $attachment_id ) ) {
					// If it's an image, add it to the image stack
					array_push( $images, $attachment_id );
				} else if ( 0 === strpos( $type['type'], 'video' ) ) {
					array_push( $videos, $attachment_id );
				} else {
					// Otherwise add it to a list of files that we'll just link to directly
					array_push( $files, $attachment_id );
				}
			}
		}

		// Known upload errors (@todo allow partial success, send errors in response payload)
		if ( count( $errors ) ) {
			self::die_failure( 'upload_failed_errors', $errors );
		}

		// Nothing successfully uploaded
		if ( 0 == count( $images ) + count( $files ) + count( $videos ) ) {
			self::die_failure( 'no_valid_uploads', __( 'Your upload failed. Perhaps try from within wp-admin.', 'o2' ) );
		}

		switch ( count( $images ) ) {
		default: // return multiple images as a gallery shortcode
			$output .= '[gallery ids="' . esc_attr( implode( ',', $images ) ) . '"]' . "\n";
			break;

		case 1: // return single image as html element
			$image_id = $images[0];

			// Get the image
			$image_src = $image_src_w = get_the_guid( $image_id );
			$image_dims = wp_get_attachment_image_src( $image_id, 'full' );

			// if the image is NOT an animated gif, append the image_width to the src
			if ( ! self::is_animated_gif( get_attached_file( $image_id ) ) && !empty( $content_width ) ) {
				$image_src_w =  $image_src . '?w=' . $content_width;
			}

			$output .= '<a href="' . esc_attr( $image_src ) .'"><img src="' . esc_attr( $image_src_w ) . '" alt="' . esc_attr( get_the_title( $image_id ) ) . '" class="size-full wp-image" id="i-' . esc_attr( $image_id ) . '" /></a>' . "\n";
			break;

		case 0:
			break; // catch this so that it doesn't do a gallery (default)
		}

		// Add embed shortcodes for uploaded videos
		$video_output = '';
		foreach ( (array) $videos as $video ) {
			$video_output .= '[video src=' . esc_url( wp_get_attachment_url( $video ) ) . "]\n";
		}

		$output .= apply_filters( 'o2_video_attachment', $video_output, $videos );

		// Add links to each file on a line of their own
		foreach ( (array) $files as $file ) {
			$output .= '<a href="' . esc_url( wp_get_attachment_url( $file ) ) . '" id="i-' . esc_attr( $image_id ) . '">' . get_the_title( $file ) . '</a>' . "\n";
		}

		self::die_success( $output );
		exit;
	}

	/*
	 * is_animated_gif
	 * Based on http://www.php.net/manual/en/function.imagecreatefromgif.php#104473
	 * An animated gif contains multiple "frames", with each frame having a
	 * header made up of:
	 * * a static 4-byte sequence (\x00\x21\xF9\x04)
	 * * 4 variable bytes
	 * * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)
	 * We read through the file til we reach the end of the file, or we've found
	 * at least 2 frame headers
	 */
	public static function is_animated_gif( $image_pathname ) {
		if ( ! ( $fh = @fopen( $image_pathname, 'rb' ) ) )
			return false;

		$count = 0;
		while( ! feof( $fh ) && $count < 2 ) {
			$chunk = fread( $fh, 1024 * 100 ); //read 100kb at a time
			$count += preg_match_all( '#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches );
		}
		fclose( $fh );
		return ( $count > 1 );
	}

	/**
	 * Called after creating/editing to link orphan attachments to post/comment parent
	 */
	public static function update_orphan_attachments( $post ) {
		global $wpdb;

		// find all single-uploaded images/files
		preg_match_all( '/\sid\=\"i\-(\d+)\"/', $post->post_content, $match );
		$image_ids = $match[1];

		// find all image galleries
		preg_match_all( '/\[gallery ids\=\"([0-9,]+)\"/', $post->post_content, $match );
		$gallery_lists = $match[1];

		if ( count( $gallery_lists ) ) {
			// join gallery strings into one csv, then break into array
			$gallery_list = implode( ",", $gallery_lists );
			$gallery_ids  = explode( ",", $gallery_list );
			$image_ids    = array_merge( $image_ids, $gallery_ids );
		}

		foreach ( $image_ids as $image_id ) {
			wp_update_post( array(
				'ID' => $image_id,
				'post_parent' => $post->ID,
			) );
		}

	}
}
