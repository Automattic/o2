<?php
/**
 * @package o2
 * @subpackage o2_Checklists
 */

class o2_List_Creator {

	var $task_item_regex = "/^([ ]{0,3})([xo#*-])(\s+)(.*)/";
	var $allowed_tags_in_tasks = array(
		'a' => array(
			'href' => array(),
			'title' => array()
		),
		'b' => array(),
		'code' => array(),
		'del' => array(),
		'em' => array(),
		'pre' => array(),
		'strong' => array()
	);

	var $preserved_text = array();
	var $line_hashes = array();
	var $current_list_depth = -1;
	var $post_ID = 0;
	var $comment_ID = 0;
	var $user_can_edit_object = false;
	var $bullet_type = '';

	/**
	 * A list of fragment keys to be returned to the ajax caller
	 */
	var $key_whitelist = array(
		'contentRaw' => '',
		'contentFiltered' => '',
		'type' => '',
		'id' => ''
	);

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );
		add_action( 'wp_ajax_o2_checklist', array( $this, 'on_ajax' ) );
		add_filter( 'o2_options', array( $this, 'filter_options' ) );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'o2-extend-checklists-views-common', plugins_url( 'modules/checklists/js/views/common.js', O2__FILE__ ), array( 'o2-views-post', 'o2-views-comment', 'o2-cocktail', 'jquery-color', 'jquery-ui-sortable' ) );

		if ( wp_is_mobile() ) {
			wp_enqueue_script( 'jquery-touch-punch' );
		}
	}

	public function enqueue_style() {
		wp_register_style( 'o2-extend-checklists-styles', plugins_url( 'modules/checklists/css/style.css', O2__FILE__ ) );
		wp_style_add_data( 'o2-extend-checklists-styles', 'rtl', 'replace' );
		wp_enqueue_style( 'o2-extend-checklists-styles' );
	}

	public function parse_lists_in_post( $content ) {
		$this->post_ID = get_the_ID();
		$this->comment_ID = '';
		$this->user_can_edit_object = $this->current_user_can_edit_checklist( 'post', $this->post_ID );
		return $this->parse_lists( $content );
	}

	public function parse_lists_in_comment( $content, $comment = null ) {
		$this->post_ID = '';
		$this->comment_ID = get_comment_ID();
		$content = wp_kses_post( $content ); // we need to this this here because we removed the wp_kses_post filter in o2_Comment_List_Creator __construct
		$this->user_can_edit_object = $this->current_user_can_edit_checklist( 'comment', $this->comment_ID );
		return $this->parse_lists( $content );
	}

	function current_user_can_edit_checklist( $object_type, $object_ID ) {
		// no logged out edits, evar
		$current_user_id = get_current_user_id();
		if ( 0 == $current_user_id ) {
			return false;
		}

		// try default post and comment capabilities
		if ( ( 'post' == $object_type ) && ( current_user_can( 'edit_post', $object_ID ) ) ) {
			return true;
		} else if ( ( 'comment' == $object_type ) && ( current_user_can( 'edit_comment', $object_ID ) ) ) {
			return true;
		}

		// o2 also allows authors to do checklist things
		$user_data = get_userdata( $current_user_id );
		if ( in_array( 'author', $user_data->roles ) ) {
			return true;
		}

		return false;
	}

	function parse_lists( $content ) {
		// do a quick check to see if there are any list like things in the content
		// before we invest too much time on this item
		if ( ! preg_match( '/(^|\r\n|\r|\n)([xo#*-])/', $content ) ) {
			return $content;
		}

		$content = $this->preserve_text( $content );
		$content_array = preg_split( '/(\r\n|\r|\n)/', $content );

		$new_content_array = array();
		$this->clear_line_hashes();

		$in_a_list = false;
		$this->current_list_depth = -1; // -1 indicates not in a list

		foreach( (array) $content_array as $content_line ) {
			// does the line look like a list item?
			$matches = array();
			if ( preg_match( $this->task_item_regex, $content_line, $matches ) ) {
				$item_depth = strlen( $matches[1] ); // leading space count
				$this->bullet_type = $matches[2];
				$item_text = $matches[4];

				$checkable_item = ( ( 'x' == $this->bullet_type ) || ( 'o' == $this->bullet_type ) );

				if ( $in_a_list ) {
					$depth_delta = $this->current_list_depth - $item_depth;
					if ( $depth_delta > 0 ) { // new item is not as deep as we are.  close 1 or more lists
						$i = 0;
						while ( $i++ < $depth_delta ) {
							$new_content_array[] = $this->close_list();
						}
					} else if ( $depth_delta < 0 ) { // new item is deeper than we are.  open a lists (only open 1 even if deeper)
						$new_content_array[] = $this->open_list( $checkable_item );
					} else { // same depth as we are - close previous item
						$new_content_array[] = "</li>";
					}
					// add the item
					$new_content_array[] = $this->list_item( $this->bullet_type, $item_text, $content_line );
				} else { // not in_a_list
					if ( 0 == $item_depth ) {
						$new_content_array[] = $this->open_list( $checkable_item );
						$new_content_array[] = $this->list_item( $this->bullet_type, $item_text, $content_line );
						$in_a_list = true;
					} else {
						// non zero depth while outside a list - just emit this line
						$new_content_array[] = $content_line;
					}
				}
			} else { // doesn't look like a list item
				if ( $in_a_list ) {
					// completely close the list
					$new_content_array[] = $this->completely_close_list();
					$in_a_list = false;
				}
				$new_content_array[] = $content_line;
			}
		}

		// ok, we're out of the loop, better close any list that might still be open
		if ( $in_a_list ) {
			$new_content_array[] = $this->completely_close_list();
			$in_a_list = false;
		}

		$new_content = implode( "\n", $new_content_array );
		$new_content = $this->restore_text( $new_content );

		return $new_content;
	}

	public function filter_options( $options ) {
		$localizations = array(
			'addChecklistItem'       => __( 'Enter the text for the new item', 'o2' ),
			'editChecklistItem'      => __( 'Update the item text below', 'o2' ),
			'deleteChecklistItem'    => __( 'Are you sure you want to delete this item?', 'o2' ),
			'checklistError'         => __( 'CheckList Error', 'o2' ),
			'unknownChecklistError'  => __( 'An unknown error occurred', 'o2' ),
			'malformedChecklistResp' => __( 'A malformed response was received', 'o2' )
		);
		$localizations = array_merge( $options['strings'], $localizations );
		$options['strings'] = $localizations;

		return $options;
	}

	function preserve_text( $text ) {
		global $SyntaxHighlighter;

		if ( false !== strpos( $text, '[' ) && is_a( $SyntaxHighlighter, 'SyntaxHighlighter' ) && $SyntaxHighlighter->shortcodes ) {
			$shortcodes_regex = '#\[(' . join( '|', array_map( 'preg_quote', $SyntaxHighlighter->shortcodes ) ) . ')(?:\s|\]).*\[/\\1\]#s';
			$text = preg_replace_callback( $shortcodes_regex, array( $this, 'preserve_text_callback' ), $text );
		}

		if ( false !== strpos( $text, '<pre' ) ) {
			$text = preg_replace_callback( '#<pre(?:\s|>).*</pre>#s', array( $this, 'preserve_text_callback' ), $text );
		}

		return $text;
	}

	function preserve_text_callback( $matches ) {
		$hash = md5( $matches[0] );
		$this->preserved_text[$hash] = $matches[0];
		return "[preserved_text $hash /]";
	}

	function hash_line( $content_line ) {
		return md5( strip_tags( $content_line ) );
	}

	function open_list( $contains_checkable_items ) {
		$opener = "";
		if ( $contains_checkable_items ) {
			if ( -1 == $this->current_list_depth && $this->user_can_edit_object ) {
				// we're starting a new list - emit the form first
				$object_ID = ( empty( $this->comment_ID ) ) ? $this->post_ID : $this->comment_ID;
				$object_type = ( empty( $this->comment_ID ) ) ? "post" : "comment";
				$action_url = admin_url( 'admin-ajax.php?action=o2_checklist' );
				$opener .= "<form class='o2-tasks-form' data-object-id='" . esc_attr( $object_ID ) . "'"
					. " data-object-type='" . esc_attr( $object_type ) . "'"
					. " action='" .  esc_url( $action_url ) . "'"
					. " method='POST' >";
			}
			$opener .= "<ul class='o2-tasks'>";
		} else {
			if ( '-' === $this->bullet_type || '*' === $this->bullet_type )
				$opener .= "<ul>";
			else
				$opener .= "<ol>";
		}
		$this->current_list_depth++;

		return $opener;
	}

	function list_item( $bullet, $item_text, $content_line ) {
		$line_hash = self::hash_line( $content_line ); // note that we hash the entire content line to simplify the logic in on_ajax
		$instance = $this->add_line_hash( $line_hash );

		$sanitized_text = wp_kses( $item_text, $this->allowed_tags_in_tasks );
		$checkable_item = ( ( 'x' == $bullet ) || ( 'o' == $bullet ) );

		if ( $checkable_item ) {
			$completed = ( 'x' == $bullet );
			$completed_class = ( $completed ) ? 'o2-task-completed' : '';

			$item = "<li class='o2-task-item o2-task-sortable {$completed_class}'"
				. " data-item-hash='" . esc_attr( $line_hash ) . "'"
				. " data-hash-instance='" . esc_attr( $instance ) . "'"
				. " data-item-text='" . esc_attr( $item_text ) . "'"
				. " >";

			$checked = checked( $completed, true, false );
			$disabled = disabled( $this->user_can_edit_object, false, false );

			$item .= "<input type='checkbox' name='' value='' $checked $disabled />";
			$item .= " ";

			$item .= "<span class='o2-task-item-text'>" . $sanitized_text . "</span>";

			if ( $this->user_can_edit_object ) {
				$item .= "<div class='o2-task-tools'>";
				$item .= "<a href='#' class='o2-edit-task genericon-edit' title='" . esc_html__( 'Edit this item', 'o2' ) . "'>" . esc_html__( 'Edit', 'o2' ) . "</a>";
				$item .= "<a href='#' class='o2-delete-task genericon-trash' title='" . esc_html__( 'Delete this item', 'o2' ) . "'>" . esc_html__( 'Delete', 'o2' ) . "</a>";
				$item .= "<a href='#' class='o2-add-task' title='" . esc_html__( 'Add a new item after this item', 'o2' ) . "'><span class='o2-task-tool-char'>+</span> " . esc_html__( 'Add New After', 'o2' ) . "</a>";
				$item .= "</div>";
			}
		} else {
			$item = "<li>" . $sanitized_text;
		}

		return $item;
	}

	function close_list() {
		// first, close the item
		$closure = "</li>";

		// then, close the list
		if ( '#' === $this->bullet_type ) {
			$closure .= "</ol>";
		} else {
			$closure .= "</ul>"; // includes checklists
		}

		// update our depth and evaluate if we have just closed the outermost list and need to close a form
		$this->current_list_depth--;

		if (
			-1 == $this->current_list_depth
		&&
			$this->user_can_edit_object
		&&
			(
				'o' === $this->bullet_type
			||
				'x' === $this->bullet_type
			)
		) {
			$closure .= "</form>";
		}

		return $closure;
	}

	function completely_close_list() {
		$closure = "";
		do {
			$closure .= $this->close_list();
		} while ( -1 !== $this->current_list_depth );

		return $closure;
	}

	function restore_text( $text ) {
		if ( false === strpos( $text, '[preserved_text ' ) ) {
			return $text;
		}

		return preg_replace_callback( '#\[preserved_text (\S+) /\]#', array( $this, 'restore_text_callback' ), $text );
	}

	function restore_text_callback( $matches ) {
		if ( isset( $this->preserved_text[$matches[1]] ) ) {
			return $this->preserved_text[$matches[1]];
		}

		return $matches[0];
	}

	public function on_ajax() {
		if ( empty( $_POST ) || ( ! isset( $_POST['data'] ) ) ) {
			wp_send_json_error( array( 'errorText' => 'Invalid request.  No data. (o2 List Creator)' ) );
		}

		$data = $_POST['data'];
		$object_ID = ( isset( $data['objectID'] ) ) ? $data['objectID'] : '';
		$nonce = ( isset( $data['nonce'] ) ) ? $data['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'o2_nonce' ) ) {
			wp_send_json_error( array( 'errorText' => 'Invalid request.  Bad nonce.  Please refresh the page and try again.' ) );
		}

		$object_type = ( isset( $data['objectType'] ) ) ? $data['objectType'] : '';

		// Check user's permission to do anything to this object
		if ( 'post' == $object_type ) {
			if ( ! $this->current_user_can_edit_checklist( 'post', $object_ID ) ) {
				wp_send_json_error( array( 'errorText' => 'Invalid request.  The current user cannot edit checklists in this post.' ) );
			}
		} else if ( 'comment' == $object_type ) {
			if ( ! $this->current_user_can_edit_checklist( 'comment', $object_ID ) ) {
				wp_send_json_error( array( 'errorText' => 'Invalid request.  The current user cannot edit checklists in this comment.' ) );
			}
		} else {
			wp_send_json_error( array( 'errorText' => 'Invalid request.  Unrecognized checklist object type.' ) );
		}

		$item_hash = ( isset( $data['itemHash'] ) ) ? $data['itemHash'] : '';
		$item_hash_instance = ( isset( $data['itemHashInstance'] ) ) ? $data['itemHashInstance'] : '';
		$command = ( isset( $data['command'] ) ) ? $data['command'] : '';
		$arg1 = ( isset( $data['arg1'] ) ) ? $data['arg1'] : '';
		$arg2 = ( isset( $data['arg2'] ) ) ? $data['arg2'] : '';

		$content = $this->get_object_content( $object_type, $object_ID );

		$content = $this->preserve_text( $content );
		$content_array = preg_split( '/(\r\n|\r|\n)/', $content );

		$updated_content_array = array();
		$this->clear_line_hashes();
		$line_to_insert_later = '';
		foreach( (array) $content_array as $content_line ) {
			// get the line's hash and hash instance
			$line_hash = self::hash_line( $content_line );
			$instance = $this->add_line_hash( $line_hash );

			// is it the hash and hash instance we're looking for?
			if ( ( $line_hash == $item_hash ) && ( $instance == $item_hash_instance ) ) {
				if ( 'delete' == $command ) {
					// nothing to do, just don't append the content_line
					do_action( 'o2_checklists_command', $command, $object_type, $object_ID, $content_line );
				} else if ( 'update' == $command ) {
					$arg1 = wp_kses( $arg1, $this->allowed_tags_in_tasks );
					$content_line = preg_replace( $this->task_item_regex, '${1}${2}${3}' . $arg1, $content_line );
					$updated_content_array[] = $content_line;
					do_action( 'o2_checklists_command', $command, $object_type, $object_ID, $content_line );
				} else if ( 'add' == $command ) {
					$updated_content_array[] = $content_line;
					$arg1 = wp_kses( $arg1, $this->allowed_tags_in_tasks );
					$added_content_line = preg_replace( $this->task_item_regex, '${1}o${3}' . $arg1, $content_line );
					$updated_content_array[] = $added_content_line;
					do_action( 'o2_checklists_command', $command, $object_type, $object_ID, $added_content_line );
				} else if ( 'check' == $command ) {
					$user_mention = $this->get_current_user_mention();
					if ( 'true' == $arg1 ) { // check (x) the item
						$content_line = preg_replace( $this->task_item_regex, '${1}x${3}${4}', $content_line );
						// add their name to the end of the string if it is not there already
						if ( $user_mention != substr( $content_line, -strlen( $user_mention ) ) ) {
							$content_line .= $user_mention;
						}
						do_action( 'o2_checklists_command', 'check', $object_type, $object_ID, $content_line );
					} else { // uncheck (o) the item
						$content_line = preg_replace( $this->task_item_regex, '${1}o${3}${4}', $content_line );
						// if the user mention exists in the string, remove it
						if ( false !== strpos( $content_line, $user_mention ) ) {
							$content_line = str_replace( $user_mention, '', $content_line );
						}
						do_action( 'o2_checklists_command', 'uncheck', $object_type, $object_ID, $content_line );
					}
					$updated_content_array[] = $content_line;
				} else if ( 'moveAfter' == $command ) {
					// for this pass, just don't append the content_line - we'll insert it in the correct spot in a second
					$line_to_insert_later = $content_line;
					do_action( 'o2_checklists_command', $command, $object_type, $object_ID, $content_line );
				} else if ( 'moveBefore' == $command ) {
					// for this pass, just don't append the content_line - we'll insert it in the correct spot in a second
					$line_to_insert_later = $content_line;
					do_action( 'o2_checklists_command', $command, $object_type, $object_ID, $content_line );
				} else {
					// unrecognized command - do nothing to the line - just pass it through
					$updated_content_array[] = $content_line;
				}
			} else {
				$updated_content_array[] = $content_line;
			}
		}

		if ( ! empty( $line_to_insert_later ) ) {
			// insert the moved item in the correct spot
			// $arg1 contains the hash the item to insert before/after
			// $arg2 contains the instance

			$content_array = $updated_content_array;
			$updated_content_array = array();
			$this->clear_line_hashes();
			foreach( (array) $content_array as $content_line ) {
				// get the line's hash and hash instance
				$line_hash = self::hash_line( $content_line );
				$instance = $this->add_line_hash( $line_hash );

				// is it the hash and hash instance we're looking for?
				if ( ( $line_hash == $arg1 ) && ( $instance == $arg2 ) ) {
					if ( 'moveAfter' == $command ) {
						$updated_content_array[] = $content_line;
						$updated_content_array[] = $line_to_insert_later;
					} elseif ( 'moveBefore' == $command ) {
						$updated_content_array[] = $line_to_insert_later;
						$updated_content_array[] = $content_line;
					}
				} else {
					// just append and move on
					$updated_content_array[] = $content_line;
				}
			}
		}

		$updated_content = implode( "\n", $updated_content_array );
		$updated_content = $this->restore_text( $updated_content );

		// update the object in the database
		$updated_object = $this->update_object_content( $object_type, $object_ID, $updated_content );
		$fragment = o2_Fragment::get_fragment( $updated_object );

		if ( empty( $fragment ) ) {
			wp_send_json_error( array( 'errorText' => 'Internal error.  Empty fragment.  (o2 List Creator)' ) );
		}

		$partial_fragment = array_intersect_key( $fragment, $this->key_whitelist );
		wp_send_json_success( $partial_fragment );
	}

	function get_current_user_mention() {
		$current_user_mention = '';

		$current_user = wp_get_current_user();
		if ( $current_user instanceof WP_User ) {
			$current_user_mention = " (@" . $current_user->user_login . ")";
		}

		return $current_user_mention;
	}

	function clear_line_hashes() {
		$this->line_hashes = array();
	}

	function add_line_hash( $line_hash ) {
		$instance = 0;
		if ( isset( $this->line_hashes[$line_hash] ) ) {
			$instance = $this->line_hashes[$line_hash];
			$instance++;
		}
		$this->line_hashes[$line_hash] = $instance;

		return $instance;
	}

	function get_object_content( $object_type, $object_ID ) {
		global $post, $comment;
		if ( 'post' == $object_type ) {
			$post = get_post( $object_ID );
			$content = $post->post_content;
		} else {
			$comment = get_comment( $object_ID );
			$content = $comment->comment_content;
		}
		return $content;
	}

	function update_object_content( $object_type, $object_ID, $updated_content ) {
		$updated_object = false;

		if ( 'post' == $object_type ) {
			o2_Fragment::bump_post( $object_ID, $updated_content );
			$updated_object = get_post( $object_ID );
		} else {
			$comment_data = array(
				'comment_ID'      => $object_ID,
				'comment_content' => $updated_content
			);

			// purposefully throw away the comment global otherwise get_comment will return
			// the pre-updated copy from $GLOBALS
			if ( isset( $GLOBALS['comment'] ) ) {
				unset( $GLOBALS['comment'] );
			}

			wp_update_comment( $comment_data );
			update_comment_meta( $object_ID, 'o2_comment_gmt_modified', time() );

			$updated_object = get_comment( $object_ID );

			// update the global
			$GLOBALS['comment'] = $updated_object;
		}

		return $updated_object;
	}
}

class o2_Post_List_Creator extends o2_List_Creator {
	public function __construct() {
		parent::__construct();
		// we use a very early priority (1) to get as close as possible to the raw content
		add_filter( 'the_content', array( $this, 'parse_lists_in_post' ), 1 );
	}
}

class o2_Comment_List_Creator extends o2_List_Creator {
	public function __construct() {
		parent::__construct();
		// we use a very early priority (0) to get as close as possible to the raw content
		add_filter( 'comment_text', array( $this, 'parse_lists_in_comment' ), 0, 2 ); // important to run before mentions linkifies things (pri 9)

		// remove the default filter for wp_kses_post (which runs at priority ten)
		// as we wp_kses_post the content in our own filter - parse_lists_in_comment - which runs earlier
		// otherwise wp_kses_post will remove our <form> and <input> elements
		remove_filter( 'comment_text', 'wp_kses_post', 10 );
		remove_filter( 'comment_text', 'wpcom_filter_long_comments_on_display', 100 );
	}
}
