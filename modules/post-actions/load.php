<?php

class o2_Post_Actions {
	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'o2_before_post_actions', array( $this, 'before_post_actions' ) );
		add_filter( 'o2_after_post_actions', array( $this, 'after_post_actions' ) );
		add_filter( 'o2_resolved_posts_audit_log_entry', array( $this, 'filter_resolved_posts_audit_log_entry' ), 10, 2 );

		// remove default filtering for comment likes and add our own filtering
		remove_filter( 'comment_text', 'comment_like_button', 12 );
		add_filter( 'o2_comment_actions', array( $this, 'filter_comment_actions' ), 10, 4 );

		// Hook in our override
		add_filter( 'o2_filter_post_action_html', array( $this, 'filter_post_action_html' ), 11, 2 );

		if ( function_exists( 'wpl_is_enabled_sitewide' ) ) {
			// Add our content before and after post likes (which adds at priority 30)
			// so that we bracket it
			add_filter( 'post_flair', array( $this, 'before_post_likes' ), 28 );
			add_filter( 'post_flair', array( $this, 'after_post_likes' ), 32 );
		} else {
			// Add our content at the bottom of the post (e.g. on sites not using likes)
			add_filter( 'the_content', array( $this, 'before_post_likes' ), 28 );
			add_filter( 'the_content', array( $this, 'after_post_likes' ), 32 );
		}

		// Add a special hover state for following
		add_filter( 'o2_post_action_states', array( $this, 'add_stop_following' ), 10, 2 );
	}

	function enqueue_styles() {
		wp_register_style( 'post-actions-styles', plugins_url( 'modules/post-actions/css/style.css', O2__FILE__ ) );
		wp_style_add_data( 'post-actions-styles', 'rtl', 'replace' );
		wp_enqueue_style( 'post-actions-styles' );
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'o2-extend-post-actions', plugins_url( 'modules/post-actions/js/script.js', O2__FILE__ ), array( 'jquery', 'o2-cocktail', 'o2-views-post' ) );
		wp_enqueue_script( 'o2-extend-comment-actions-views-comment', plugins_url( 'modules/post-actions/js/views/extend-comment.js', O2__FILE__ ), array( 'jquery', 'o2-cocktail', 'o2-views-comment' ) );
		wp_enqueue_script( 'o2-extend-post-actions-views-post', plugins_url( 'modules/post-actions/js/views/extend-post.js', O2__FILE__ ), array( 'jquery', 'o2-cocktail', 'o2-views-post', 'o2-extend-post-actions' ) );
	}

	function before_post_actions( $html ) {
		$html = "<nav class='o2-dropdown-actions o2-post-actions'><button class='o2-dropdown-actions-disclosure genericon genericon-ellipsis'><span>" .
			esc_html__( 'Post Actions', 'o2' ) . "</span></button><ul>";
		return $html;
	}

	function after_post_actions( $html ) {
		$html = "</ul></nav>";
		return $html;
	}

	function filter_post_action_html( $html, $action ) {
		if ( false === $html ) {
			$html = '';
		}

		$action_name = $action[ 'action' ];

		// We want to suppress some buttons from the dropdown entirely
		if ( in_array( $action_name, array( "reply", "follow", "resolvedposts" ) ) ) {
			return '';
		}

		// Wrap the original HTML for the action in an li
		$html = "<li>{$html}</li>";

		return $html;
	}

	function before_post_likes( $content ) {
		global $post;

		// Don't add post actions in the admin and only for o2 read/write requests.
		if ( is_admin() && ! ( isset( $_GET['action'] ) && ( 'o2_read' === $_GET['action'] || 'o2_write' === $_GET['action'] ) ) ) {
			return $content;
		}

		if ( ! apply_filters( 'o2_process_the_content', true ) ) {
			return $content;
		}

		$actions = apply_filters( 'o2_filter_post_actions', array(), $post->ID );

		$content .= "<nav class='o2-post-footer-actions'>";

		$content .= "<ul class='o2-post-footer-action-row'>";
		foreach ( (array) $actions as $action ) {
			if ( in_array( $action[ 'action' ], array( 'reply', 'login-to-reply', 'follow' ) ) ) {
				$content .= "<li class='o2-post-footer-action'>" . o2_default_post_action_html( '', $action ) . "</li>";
			}
		}
		$content .= "</ul>";
		$content .= "<div class='o2-post-footer-action-likes'>";

		return $content;
	}

	function after_post_likes( $content ) {
		global $post;

		// Don't add post actions in the admin and only for o2 read/write requests.
		if ( is_admin() && ! ( isset( $_GET['action'] ) && ( 'o2_read' === $_GET['action'] || 'o2_write' === $_GET['action'] ) ) ) {
			return $content;
		}

		if ( ! apply_filters( 'o2_process_the_content', true ) ) {
			return $content;
		}

		$actions = apply_filters( 'o2_filter_post_actions', array(), $post->ID );

		$content .= "</div>";
		$content .= "<ul class='o2-post-footer-action-row'>";
		foreach ( (array) $actions as $action ) {
			if ( in_array( $action[ 'action' ], array( 'resolvedposts' ) ) ) {
				// remove our filter for a moment (otherwise it will strip the resolved posts markup)
				remove_filter( 'o2_filter_post_action_html', array( $this, 'filter_post_action_html' ), 11 );
				$action_html = apply_filters( 'o2_filter_post_action_html', '', $action );
				// put our filter back
				add_filter( 'o2_filter_post_action_html', array( $this, 'filter_post_action_html' ), 11, 2 );
				$content .= "<li class='o2-post-footer-action'>" . $action_html . "</li>";
			}
		}
		$content .= "</ul>";

		// Close the navigation and return the markup
		$content .= "</nav>";

		return $content;
	}

	function filter_resolved_posts_audit_log_entry( $log_entry, $args ) {
		$user = get_user_by( 'login', $args['user_login'] );
		$display_name = ( $user ) ? $user->display_name : __( 'Someone', 'o2' );

		$new_state = $args['new_state'];
		if ( 'resolved' === $new_state ) {
			$new_state = __( 'done', 'o2' );
		} else if ( 'unresolved' === $new_state ) {
			$new_state = __( 'to-do', 'o2' );
		}
		$log_entry = sprintf( __( '%1$s marked this %2$s', 'o2' ), esc_html( $display_name ), esc_html( $new_state ) );

		return $log_entry;
	}

	function filter_comment_actions( $actions, $location, $comment, $comment_depth ) {
		if ( 'footer' === $location ) {
			$max_depth = get_option( 'thread_comments_depth' );

			$ok_to_reply = true;
			if ( ! comments_open( $comment->comment_post_ID ) ) {
				$ok_to_reply = false;
			}

			if ( $comment_depth >= $max_depth ) {
				$ok_to_reply = false;
			}

			if ( $ok_to_reply ) {
				if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
					$actions[] = "<a class='genericon genericon-reply' href='" . wp_login_url( get_comment_link( $comment ) ) . "' >" . esc_html__( 'Login to Reply', 'o2' ) . "</a>";
				} else {
					$actions[] = "<a class='o2-comment-reply genericon genericon-reply' href='#' >" . esc_html__( 'Reply', 'o2' ) . "</a>";
				}
			}

			// @todo if comment likes post actions gets accepted, this will need to go in wpcom
			if ( function_exists( 'comment_like_button' ) ) {
				$dummy_content = 'dummy';
				$comment_like_markup = comment_like_button( $dummy_content, $comment );
				$comment_like_markup = substr( $comment_like_markup, strlen( $dummy_content ) );
				$actions[] = $comment_like_markup;
			}
		}

		$prev_deleted = get_comment_meta( $comment->comment_ID, 'o2_comment_prev_deleted', true );

		if ( 'dropdown' === $location ) {
			// Only show actions on comments that the current user can edit and that are approved but not previously deleted placeholders.
			add_filter( 'map_meta_cap', array( 'o2_Write_API', 'restrict_comment_editing' ), 10, 4 );
			$current_user_can_edit_comments = current_user_can( 'edit_comment', $comment->comment_ID );
			remove_filter( 'map_meta_cap', array( 'o2_Write_API', 'restrict_comment_editing' ), 10 );
			if ( $current_user_can_edit_comments && '1' === $comment->comment_approved && empty( $prev_deleted ) ) {
				$actions[] = "<a class='o2-comment-edit genericon genericon-edit' href='#' >" . esc_html__( 'Edit', 'o2' ) . "</a>";
				$actions[] = "<a class='o2-comment-trash genericon genericon-trash o2-actions-border-top o2-warning-hover' href='#' >" . esc_html__( 'Trash', 'o2' ) . "</a>";
			}
		}

		if ( 'trashed_dropdown' === $location ) {
			$actions[] = "<a class='o2-comment-untrash genericon genericon-refresh' href='#' >" . esc_html__( 'Untrash', 'o2' ) . "</a>";
		}

		return $actions;
	}

	/*
	 * If the current user isn't following all the comments on the blog, add hoverText that
	 * prompts them that they can unfollow comments on a post they are following
	 *
	 * @todo WPCOM only (move to wpcom.php if/when the new post-actions are incorporated in o2 core)
	 */
	function add_stop_following( $states_array, $action ) {
		if ( 'follow' === $action ) {
			$following_all_comments = false;

			if ( is_user_logged_in() && class_exists( 'Comment_Subscription' ) ) {
				$current_user = wp_get_current_user();
				$blog_id = get_current_blog_id();
				$subscribed_blog_ids = Comment_Subscription::get_all_comment_subs_for_user( $current_user->user_email );
				$following_all_comments = in_array( $blog_id, $subscribed_blog_ids );
			}

			if ( ! $following_all_comments ) {
				$states_array['subscribed']['hoverText'] = __( 'Stop following', 'o2' );
			}
		}
		return $states_array;
	}
}

$o2_post_actions = new o2_Post_Actions();
