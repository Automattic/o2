<?php

/*
 * Register the "core" post actions for o2
 */

if ( !function_exists( 'o2_register_post_action_states' ) ) {
	function o2_register_post_action_states( $action, $states_array ) {
		global $o2_post_action_states;
		if ( ! is_array( $o2_post_action_states ) ) {
			$o2_post_action_states = array();
		}

		// Allow others to modify the states for a particular action
		$states_array = apply_filters( 'o2_post_action_states', $states_array, $action );

		$o2_post_action_states[ $action ] = $states_array;
	}
}

if ( !function_exists( 'o2_register_default_post_action_states' ) ) {
	function o2_register_default_post_action_states() {
		o2_register_post_action_states( 'reply',
			array(
				'default' => array(
					'shortText' => __( 'Reply', 'o2' ),
					'title' => __( 'Reply', 'o2' ),
					'classes' => array(),
					'genericon' => 'genericon-reply'
				)
			)
		);

		o2_register_post_action_states( 'login-to-reply',
			array(
				'default' => array(
					'shortText' => __( 'Login to Reply', 'o2' ),
					'title' => __( 'Login to Reply', 'o2' ),
					'classes' => array(),
					'genericon' => 'genericon-reply'
				)
			)
		);

		o2_register_post_action_states( 'scrolltocomments',
			array(
				'default' => array(
					'shortText' => __( 'Scroll', 'o2' ),
					'title' => __( 'Scroll to comments', 'o2' ),
					'classes' => array(),
					'genericon' => 'genericon-downarrow'
				)
			)
		);

		o2_register_post_action_states( 'edit',
			array(
				'default' => array(
					'shortText' => __( 'Edit', 'o2' ),
					'title' => __( 'Edit', 'o2' ),
					'classes' => array(),
					'genericon' => 'genericon-edit'
				)
			)
		);

		o2_register_post_action_states( 'trash',
			array(
				'default' => array(
					'shortText' => __( 'Trash', 'o2' ),
					'title' => __( 'Trash', 'o2' ),
					'classes' => array(),
					'genericon' => 'genericon-trash'
				)
			)
		);

		o2_register_post_action_states( 'shortlink',
			array(
				'default' => array(
					'shortText' => __( 'Shortlink', 'o2' ),
					'title' => __( 'Shortlink', 'o2' ),
					'classes' => array(),
					'genericon' => 'genericon-link'
				)
			)
		);

	}

	add_action( 'init', 'o2_register_default_post_action_states' );
}

/*
 * Emit the post action states into the page footer so we can use them when
 * transitioning to new states
 */
if ( !function_exists( 'o2_post_action_states_in_footer' ) ) {
	function o2_post_action_states_in_footer() {
		global $o2_post_action_states;
		if ( is_array( $o2_post_action_states ) ) {
			echo "<script class='o2-post-action-states-dict' type='application/json' style='display:none'>";
			echo json_encode( $o2_post_action_states );
			echo "</script>\n";
		}
	}

	add_action( 'wp_footer', 'o2_post_action_states_in_footer' );
}

/*
 * Enqueue the jquery.advancestate.js utility
 */

if ( !function_exists( 'o2_post_actions_scripts' ) ) {
	function o2_post_actions_scripts() {
		wp_enqueue_script( 'jquery-actionstate', plugins_url( 'js/utils/post-action-states.js', O2__FILE__ ), array( 'jquery' ) );
	}

	add_action( 'wp_enqueue_scripts', 'o2_post_actions_scripts' );
}

/*
 * o2_get_default_post_actions returns a default set of actions for the given post ID
 * Plugins can add or modify the actions array using the o2_filter_post_actions filter
 */
if ( !function_exists( 'o2_get_default_post_actions' ) ) {
	function o2_get_default_post_actions( $actions = false, $post_ID = false ) {
		if ( false === $actions ) {
			$actions = array();
		}

		// Reply
		if ( comments_open( $post_ID ) && ! post_password_required( $post_ID ) ) {
			if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
				$actions[20] = array(
					'action' => 'login-to-reply',
					'href' => wp_login_url( get_permalink( $post_ID ) . '#respond' ),
					'classes' => array(),
					'rel' => false,
					'initialState' => 'default'
				);
			} else {
				$actions[20] = array(
					'action' => 'reply',
					'href' => get_permalink( $post_ID ) . '#respond',
					'classes' => array( 'o2-post-reply', 'o2-reply' ),
					'rel' => false,
					'initialState' => 'default'
				);
			}
		}

		// Scroll to Comments (Mobile)
		$actions[25] = array(
			'action' => 'scrolltocomments',
			'href' => get_permalink( $post_ID ),
			'classes' => array( 'o2-scroll-to-comments' ),
			'rel' => false,
			'initialState' => 'default'
		);

		// Edit
		if ( current_user_can( 'edit_post', $post_ID ) ) {
			$actions[30] = array(
				'action' => 'edit',
				'href' => get_edit_post_link( $post_ID ),
				'classes' => array( 'edit-post-link', 'o2-edit' ),
				'rel' => $post_ID,
				'initialState' => 'default'
			);
		}

		// Trash
		if ( current_user_can( 'delete_post', $post_ID ) ) {
			$actions[100] = array(
				'action' => 'trash',
				'href' => get_delete_post_link( $post_ID ),
				'classes' => array( 'trash-post-link', 'o2-trash', 'o2-actions-border-top', 'o2-warning-hover' ),
				'rel' => $post_ID,
				'initialState' => 'default'
			);
		}

		// Shortlink
		$actions[40] = array(
			'action' => 'shortlink',
			'href' => wp_get_shortlink( $post_ID, 'post' ),
			'classes' => array( 'short-link', 'o2-short-link' ),
			'rel' => false,
			'initialState' => 'default'
		);

		return $actions;
	}

	add_filter( 'o2_filter_post_actions', 'o2_get_default_post_actions', 10, 2 );
}

/*
 * o2_default_post_action_html returns a default rendering of the post action supplied
 * It can be modified or replaced using the o2_filter_post_action_html filter
 */
if ( !function_exists( 'o2_default_post_action_html' ) ) {
	function o2_default_post_action_html( $html, $action ) {
		if ( false === $html ) {
			$html = '';
		}

		// Grab the appropriate state settings
		global $o2_post_action_states;
		if ( ! is_array( $o2_post_action_states ) ) {
			// no post action states have been registered with o2_register_post_action_states
			error_log( 'no post action states have been registered for any action' );
			return $html;
		}

		$action_name = $action[ 'action' ];
		if ( ! isset( $o2_post_action_states[ $action_name ] ) ) {
			error_log( "no post action states have been registered for action $action_name" );
			return $html;
		}

		if ( ! isset( $action['initialState'] ) ) {
			error_log( "no initialState was specified for action $action_name" );
			return $html;
		}

		$initial_action_state = $action[ 'initialState' ];
		if ( ! isset( $o2_post_action_states[ $action_name ][ $initial_action_state ] ) ) {
			error_log( "could not find initialState $initial_action_state for action $action_name" );
			return $html;
		}

		$initial_state_settings = $o2_post_action_states[ $action_name ][ $initial_action_state ];

		$class_array = $action[ 'classes' ]; // classes to be applied to the action (not the post)
		if ( $initial_state_settings[ 'genericon' ] ) {
			$class_array[] = 'genericon ';
			$class_array[] = $initial_state_settings[ 'genericon' ];
		}
		$class_string = implode( ' ', $class_array );

		if ( false === $action['href'] ) {
			$html .= '<span ';
		} else {
			$html .= '<a ';
			$html .= 'href="' . esc_url( $action['href'] ) . '" ';
			$html .= 'title="' . esc_attr( $initial_state_settings[ 'title' ] ) . '" ';
			if ( false !== $action['rel'] ) {
				$html .= 'rel="' . esc_attr( $action['rel'] ) . '" ';
			}
		}

		$html .= ' class="' . esc_attr( $class_string ) . '" ';
		$html .= ' data-action="' . esc_attr( $action_name ) . '" ';
		$html .= ' data-actionstate="' . esc_attr( $initial_action_state ) . '" ';
		$html .= '>' . esc_html( $initial_state_settings[ 'shortText' ] );

		if ( false === $action['href'] ) {
			$html .= '</span>';
		} else {
			$html .= '</a>';
		}

		return $html;
	}

	add_filter( 'o2_filter_post_action_html', 'o2_default_post_action_html', 10, 2 );
}

/*
 * o2_get_post_actions gets the post actions for the given post ID as HTML
 * It applies the o2_filter_post_actions to the actions and then
 * applies the o2_filter_post_actions_html to each action to produce HTML from them
 */
if ( !function_exists( 'o2_get_post_actions' ) ) {
	function o2_get_post_actions( $post_ID = false ) {
		if ( !$post_ID ) {
			$post_ID = get_the_ID();
		}

		// First get the actions for this particuar post ID
		$actions = apply_filters( 'o2_filter_post_actions', false, $post_ID );

		$before_actions_html = "<span class='actions entry-actions o2-actions'>";
		$before_actions_html = apply_filters( 'o2_before_post_actions', $before_actions_html );

		$actions_html = '';
		ksort( $actions );
		foreach( (array) $actions as $action ) {
			$actions_html .= apply_filters( 'o2_filter_post_action_html', false, $action );
		}

		$after_actions_html = "</span>";
		$after_actions_html = apply_filters( 'o2_after_post_actions', $after_actions_html );

		return $before_actions_html . $actions_html . $after_actions_html;
	}
}

/*
 * o2_get_comment_actions gets the comment actions for the given comment ID as HTML
 * $location is e.g. 'footer' or 'dropdown'
 * Since comment actions are not multistate (like post actions) and do not influence
 * the post or comment classes, we're keeping the interface a lot simpler until it
 * becomes necessary to get as complex as post actions
 */
if ( ! function_exists( 'o2_get_comment_actions' ) ) {
	function o2_get_comment_actions( $location, $comment, $comment_depth ) {
		$actions = array();
		$actions = apply_filters( 'o2_comment_actions', $actions, $location, $comment, $comment_depth );
		$actions = (array) $actions;

		if ( 0 === count( $actions ) ) {
			return '';
		}

		// no actions on xcomments please
		$xcomment_original_permalink = get_comment_meta( $comment->comment_ID, 'xcomment_original_permalink', true );
		if ( ! empty( $xcomment_original_permalink ) ) {
			return '';
		}

		$html = '';

		if ( 'dropdown' === $location || 'trashed_dropdown' === $location ) {
			$html .= "<nav class='o2-comment-actions o2-dropdown-actions o2-comment-dropdown-actions'>";
			$html .= "<button class='o2-dropdown-actions-disclosure genericon genericon-ellipsis'></button>";
		} else if ( 'footer' === $location ) {
			$html .= "<div class='o2-comment-actions o2-comment-footer-actions'>";
		}

		$html .= "<ul>";
		foreach ( (array) $actions as $action ) {
			$html .= "<li>{$action}</li>";
		}
		$html .= "</ul>";

		if ( 'dropdown' === $location || 'trashed_dropdown' === $location ) {
			$html .= "</nav>";
		} else if ( 'footer' === $location ) {
			$html .= "</div>";
		}

		return $html;
	}
}
