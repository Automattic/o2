<?php

class o2_NoPriv_Write_API extends o2_API_Base {
	public static function init() {
		do_action( 'o2_write_api_nopriv' );

		if ( empty( $_REQUEST['method'] ) ) {
			self::die_failure( 'no_method', __( 'No method supplied', 'o2' ) );
		}

		$method = strtolower( $_REQUEST['method'] );

		// Only allow create method. Read, Update and Delete are not supported
		// Use o2_Read_API for Read
		if ( in_array( $method, array( 'create' ) ) ) {
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

			// We only support comment messages (for now)
			if ( !in_array( $message->type, apply_filters( 'o2_supported_nopriv_message_types', array( 'comment' ) ) ) ) {
				self::die_failure( 'unknown_message_type', __( 'Unknown message type passed', 'o2' ) );
			}

			o2_NoPriv_Write_API::init_comment();
			o2_NoPriv_Write_API::create_comment( $message ); /* only create is supported for nopriv */
		}

		self::die_failure( 'unknown_method', __( 'Unknown/unsupported method supplied', 'o2' ), 405 );
	}

	/**
	 * Get things set up to handle a comment. We need to hook into some things because
	 * we use the core file (wp-comments-post.php) to do all the processing.
	 */
	public static function init_comment() {
		// Catch a bunch of actions that are fired during errors and JSON-ify them
		$catch_actions = array(
			'comment_id_not_found',
			'comment_closed',
			'comment_on_trash',
			'comment_on_draft',
			'comment_on_password_protected'
		);
		foreach ( $catch_actions as $action )
			add_action( $action, array( 'o2_NoPriv_Write_API', 'comment_fail' ) );

		// This is called once a comment has successfully posted
		add_action( 'set_comment_cookies', array( 'o2_NoPriv_Write_API', 'comment_success' ) );
	}

	/**
	 * Handle a variety of hard comment posting failures. See ::init_comment() for the list
	 * of actions that trigger this.
	 */
	public static function comment_fail( $id ) {
		$error_text = sprintf( __( 'Failed to post comment on post ID %s. %s', 'o2' ), $id, current_filter() );
		self::die_failure( 'comment_failed', $error_text );
	}

	/**
	 * Handle a few different variations of writing a comment.
	 */
	public static function create_comment( $message ) {
		// Posting a new comment. We use the core-provided file to
		// avoid re-implementing a bunch of logic

		if ( function_exists( 'is_email_wp_emails' ) && is_email_wp_emails( $message->author->email ) ) {
			self::die_failure( 'email_associated_with_account_error',
				__( 'The email you specified is associated with a WordPress.com account.  Please login first or use a different email address.', 'o2' )
			);
		}

		// Re-map some incoming data to match the expected _POST elements
		$remap = array(
			'comment_post_ID' => $message->postID,
			'comment'         => $message->contentRaw,
			'comment_parent'  => $message->parentID,
			'author'          => $message->author->name,
			'email'           => $message->author->email,
			'url'             => $message->author->url
		);
		$_POST = array_merge( $_POST, $remap );

		// Let the core comment handler take it from here
		global $wpdb;
		require_once ABSPATH . 'wp-comments-post.php';

		// If we get here, it means the core actions weren't fired, so
		// something most likely went wrong
		self::die_failure( 'unknown_comment_error', __( 'Failed to post comment.', 'o2' ) );
	}

	/**
	 * Called from the end of the core commenting process. If we get here
	 * then the comment was posted successfully, so just output it and die.
	 */
	public static function comment_success( $comment ) {
		self::die_success( o2_Fragment::get_fragment( $comment ) );
	}
}
