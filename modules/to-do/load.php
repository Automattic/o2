<?php
/**
 * Derived from the P2 Resolved Posts plugin as written by Andrew Nacin and Daniel Bachhuber, with
 * contributions from Hugo Baeta and Joey Kudish.
 *
 * @package o2
 * @subpackage o2_ToDos
 */

if ( ! class_exists( 'o2_ToDos' ) ) {
class o2_ToDos extends o2_API_Base {

	/**
	 * Backwards-compatible with P2 Resolved Posts stored information
	 */
	const taxonomy      = 'p2_resolved';
	const audit_log_key = 'p2_resolved_log';

	const post_actions_key = 'resolvedposts';

	function __construct() {
		// Taxonomy
		add_action( 'init', array( $this, 'register_taxonomy' ) );

		// Scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );

		// Actions
		add_action( 'o2_templates', array( $this, 'get_templates' ) );
		add_action( 'o2_callback_o2_resolved_posts', array( $this, 'callback' ) );
		add_action( 'init', array( $this, 'register_post_action_states' ) );

		// Widgets
		include( dirname( __FILE__ ) . '/inc/class-to-do-widget.php' );
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );

		// Filters
		add_filter( 'o2_filter_post_actions', array( $this, 'add_post_action' ), 15, 2 );
		add_filter( 'o2_filter_post_action_html', array( $this, 'wrap_post_action_html' ), 15, 2 );
		add_filter( 'o2_post_fragment', array( $this, 'get_post_fragment' ), 10, 2 );
		add_filter( 'o2_filter_widget_filters', array( $this, 'filter_widget_filters' ) );
		add_filter( 'o2_page_title', array( $this, 'page_title' ) );
		add_filter( 'o2_options', array( $this, 'show_front_side_post_box_with_results' ) );
		add_filter( 'request', array( $this, 'request' ) );

		// Infinite Scroll
		add_filter( 'infinite_scroll_ajax_url', array( $this, 'infinite_scroll_ajax_url' ) );
		add_filter( 'infinite_scroll_query_args', array( $this, 'infinite_scroll_query_args' ) );

		// Polling
		add_filter( 'o2_sanitized_query_vars', array( $this, 'polling_sanitized_query_vars' ) );

		// Options
		$o2_options = get_option( 'o2_options' );
		if ( array_key_exists( 'mark_posts_unresolved', $o2_options ) && $o2_options['mark_posts_unresolved'] )
			add_filter( 'p2_resolved_posts_mark_new_as_unresolved', '__return_true' );
		if ( apply_filters( 'p2_resolved_posts_mark_new_as_unresolved', false ) )
			add_action( 'transition_post_status', array( $this, 'mark_new_as_unresolved' ), 10, 3 );
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'o2-extend-to-do-models-audit-log', plugins_url( 'modules/to-do/js/models/audit-log.js', O2__FILE__ ), array( 'o2-models-base' ) );
		wp_enqueue_script( 'o2-extend-to-do-collections-audit-logs', plugins_url( 'modules/to-do/js/collections/audit-logs.js', O2__FILE__ ), array( 'o2-extend-to-do-models-audit-log' ) );
		wp_enqueue_script( 'o2-extend-to-do-views-audit-log', plugins_url( 'modules/to-do/js/views/audit-log.js', O2__FILE__ ), array( 'o2-extend-to-do-models-audit-log', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-extend-to-do-views-extend-post', plugins_url( 'modules/to-do/js/views/extend-post.js', O2__FILE__ ), array( 'o2-cocktail', 'o2-extend-to-do-views-audit-log', 'o2-notifications' ) );
	}

	function enqueue_style() {
		wp_register_style( 'o2-extend-to-do', plugins_url( 'modules/to-do/css/style.css', O2__FILE__ ) );
		wp_style_add_data( 'o2-extend-to-do', 'rtl', 'replace' );
		wp_enqueue_style( 'o2-extend-to-do' );
	}

	/**
	 * Get state object by slug
	 *
	 * @param string The state slug
	 * @return object The state object
	 */
	public static function get_state( $slug ) {
		global $o2_post_action_states;
		return $o2_post_action_states[ self::post_actions_key ][ $slug ];
	}

	/**
	 * Get the first state object in the carousel
	 *
	 * @return object The first state object
	 */
	public static function get_first_state_slug() {
		global $o2_post_action_states;

		$keys = array_keys( $o2_post_action_states[ self::post_actions_key ] );

		return array_shift( $keys );
	}

	/**
	 * Get the last state object in the carousel
	 *
	 * @return object The last state object
	 */
	public static function get_last_state_slug() {
		global $o2_post_action_states;

		$keys = array_keys( $o2_post_action_states[ self::post_actions_key ] );

		return array_pop( $keys );
	}

	/**
	 * Get the next state object in the carousel
	 *
	 * @param string The state slug
	 * @return object The next state object
	 */
	public static function get_next_state_slug( $slug ) {
		global $o2_post_action_states;
		return $o2_post_action_states[ self::post_actions_key ][ $slug ][ 'nextState' ];
	}

	/**
	 * Get the state slugs in the carousel
	 *
	 * @return array An ordered array of the state slugs
	 */
	public static function get_state_slugs() {
		$state_slugs = array();
		$state_slug = self::get_first_state_slug();
		$first_state_slug = $state_slug;
		do {
			$state_slugs[] = $state_slug;
			$state_slug = self::get_next_state_slug( $state_slug );
		} while ( $state_slug !== $first_state_slug );

		return $state_slugs;
	}

	/**
	 * Make this compatible with Infinite Scroll, which we use for loading pages
	 * @props tmoorewp
	 */
	function infinite_scroll_ajax_url( $ajax_url ) {
		if ( isset( $_GET['resolved'] ) && in_array( $_GET['resolved'], self::get_state_slugs() ) ) {
			$ajax_url = add_query_arg( array( 'o2_resolved' => sanitize_key( $_GET['resolved'] ) ), $ajax_url );
		}
		if ( isset( $_GET['tags'] ) ) {
			$ajax_url = add_query_arg( array( 'tags' => sanitize_key( $_GET['tags'] ) ), $ajax_url );
		}

		return $ajax_url;
	}

	function infinite_scroll_query_args( $query_args ) {
		if ( ! is_array( $query_args ) )
			return $query_args;

		if ( isset( $_GET['o2_resolved'] ) && in_array( $_GET['o2_resolved'], self::get_state_slugs() ) ) {
			$query_args['resolved'] = sanitize_key( $_GET['o2_resolved'] );
		}

		if ( isset( $_GET['tags'] ) ) {
			$query_args['tags'] = sanitize_key( $_GET['tags'] );
		}

		return $query_args;
	}

	/**
	 * Make this compatible with polling
	 */
	function polling_sanitized_query_vars( $query_vars ) {
		if ( isset( $_GET['resolved'] ) && in_array( $_GET['resolved'], self::get_state_slugs() ) ) {
			$query_vars['resolved'] = sanitize_key( $_GET['resolved'] );
		}

		if ( isset( $_GET['tags'] ) ) {
			$query_args['tags'] = sanitize_key( $_GET['tags'] );
		}

		return $query_vars;
	}

	/**
	 * Automatically mark new posts as unresolved
	 */
	function mark_new_as_unresolved( $new_status, $old_status, $post ) {
		if ( 'publish' != $new_status || 'publish' == $old_status )
			return;

		// Only apply to posts, since other types won't have controls to change it
		if ( 'post' !== $post->post_type )
			return;

		// Exclude some post types from automatic marking
		if ( ! apply_filters( 'o2_resolved_posts_maybe_mark_new_as_unresolved', true, $post ) )
			return;

		$new_state_slug = self::get_next_state_slug( self::get_first_state_slug() );
		self::set_post_state( $post->ID, $new_state_slug );
	}

	/**
	 * Register the resolution taxonomy
	 */
	public static function register_taxonomy() {
		if ( ! taxonomy_exists( self::taxonomy ) ) {
			register_taxonomy( self::taxonomy, 'post', array(
				'public'    => true,
				'label'     => __( 'o2 To Do', 'o2' ),
				'query_var' => 'resolved',
				'rewrite'   => false,
				'show_ui'   => false,
			) );
		}
	}

	/**
	 * Set a post state
	 *
	 * @param int The post ID
	 * @param string The state slug, or an empty string to unset the state
	 */
	public static function set_post_state( $post_id, $term = null ) {
		$current_state_slug = self::get_post_state_slug( $post_id );
		if ( $current_state_slug == $term ) {
			$error = new WP_Error();
			$error->add( 'duplicate_resolution_detected', __( 'The post has already been set to that resolution.', 'o2' ) );
			return $error;
		}

		do_action( 'o2_resolved_posts_set_post_state', $post_id, $term );
		return wp_set_post_terms( $post_id, $term, self::taxonomy, false );
	}

	/**
	 * Get a post state
	 *
	 * @param int The post ID
	 * @return string The post's set state object, or the default first state object
	 */
	public static function get_post_state_slug( $post_id ) {
		$args = array(
			'fields' => 'names',
		);

		$terms = wp_get_post_terms( $post_id, self::taxonomy, $args );
		if ( $terms && ! is_wp_error( $terms ) && in_array( $terms[0], self::get_state_slugs() ) )
			$state = $terms[0];
		else
			$state = self::get_first_state_slug();

		return $state;
	}

	/**
	 * Add state and audit logs for each post to the o2 post fragment
	 */
	function get_post_fragment( $fragment, $post_id ) {
		$post_meta = $fragment['postMeta'];

		$post_meta['resolvedPostsPostState'] = self::get_post_state_slug( $post_id );

		$audit_logs = get_post_meta( $post_id, self::audit_log_key );
		$audit_log_fragments = array();
		foreach ( $audit_logs as $audit_log ) {
			$audit_log_fragments[] = self::get_audit_log_fragment( $audit_log );;
		}
		$post_meta['resolvedPostsAuditLogs'] = $audit_log_fragments;
		$fragment['postMeta'] = $post_meta;

		return $fragment;
	}

	function filter_widget_filters( $filters ) {
		$filters['filter-resolved.o2'] = array(
			'label' => __( 'Done', 'o2' ),
			'is_active' => array( $this, 'is_resolved_filter_active' ),
			'url' => esc_url( add_query_arg( 'resolved', 'resolved' ) ),
			'priority' => 25,
			'css_id' => 'o2-filter-resolved-posts'
		);

		$filters['filter-unresolved.o2'] = array(
			'label' => __( 'To Do', 'o2' ),
			'is_active' => array( $this, 'is_unresolved_filter_active' ),
			'url' => esc_url( add_query_arg( 'resolved', 'unresolved' ) ),
			'priority' => 20,
			'css_id' => 'o2-filter-unresolved-posts'
		);

		return $filters;
	}

	function is_resolved_filter_active() {
		return ( is_archive() && isset( $_GET['resolved'] ) && $_GET['resolved'] == 'resolved' );
	}

	function is_unresolved_filter_active() {
		return ( is_archive() && isset( $_GET['resolved'] ) && $_GET['resolved'] == 'unresolved' );
	}

	/**
	 * Add the audit log template to the o2 templates
	 */
	function get_templates() {
		?>

<script type="html/template" id="tmpl-o2-extend-resolved-posts-audit-log">

{{{ data.avatar }}}
<span class="audit-log-text">
	{{ data.log }}<br /><span class="audit-log-date o2-timestamp" data-unixtime="{{ data.timestamp }}"></span>
</span>

</script>

		<?php
	}

	/*
	 * Register our post action
	 */
	function register_post_action_states() {
		$resolved_post_action_states = array(
			'normal' => array(
				'name' => __( 'Normal', 'o2' ),
				'shortText' => __( 'To-do', 'o2' ),
				'title' => __( 'To-do', 'o2' ),
				'classes' => array( 'state-normal' ),
				'genericon' => 'genericon-dot',
				'nextState' => 'unresolved',
				'default' => true
			),
			'unresolved' => array(
				'name' => __( 'Unresolved', 'o2' ),
				'shortText' => __( 'Mark as done', 'o2' ),
				'title' => __( 'Mark as done', 'o2' ),
				'classes' => array( 'state-unresolved'),
				'genericon' => 'genericon-checkmark',
				'nextState' => 'resolved'
			),
			'resolved' => array(
				'name' => __( 'Resolved', 'o2' ),
				'shortText' => __( 'Clear to-do', 'o2' ),
				'title' => __( 'Clear to-do', 'o2' ),
				'classes' => array( 'state-resolved'),
				'genericon' => 'genericon-close',
				'nextState' => 'normal'
			)
		);

		o2_register_post_action_states( self::post_actions_key, $resolved_post_action_states );
	}

	/**
	 * Add a link for changing the post resolution to the o2 post actions
	 *
	 * @param array The current o2 post actions
	 * @return array The filtered o2 post actions
	 */
	function add_post_action( $actions, $post_ID = false ) {
		// Do not show resolved post action on pages - return immediately
		if ( is_page() ) {
			return $actions;
		}

		if ( !$post_ID ) {
			$post_ID = get_the_ID();
		}

		// Otherwise, proceed
		// First, mark this button as disabled if the user cannot change the state
		$button_classes = array( 'o2-resolve-link' );
		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			$button_classes[] = 'o2-disabled-action';
		}

		$post_state_slug = self::get_post_state_slug( $post_ID );

		$actions[42] = array(
			'action' => self::post_actions_key,
			'href' => '#',
			'classes' => $button_classes,
			'rel' => false,
			'initialState' => $post_state_slug
		);
		return $actions;
	}

	/**
	 * Wrap the default action HTML for .o2-resolve-link actions
	 */
	function wrap_post_action_html( $html, $action ) {
		if ( self::post_actions_key === $action[ 'action' ] && ! empty( $html ) ) {
			$html = "<span class='o2-resolve-wrap'>{$html}<ul></ul></span>";
		}
		return $html;
	}

	/**
	 * Add an audit log entry to the post's audit logs in meta
	 *
	 * @param int The post ID
	 * @param array Arguments for the audit log
	 */
	function add_audit_log( $post_id, $args = array() ) {
		$defaults = array(
			'user_login' => wp_get_current_user()->user_login,
			'new_state'  => '',
			'timestamp'  => time(),
		);
		$args = array_merge( $defaults, $args );
		add_post_meta( $post_id, self::audit_log_key, $args );

		return $args;
	}

	/**
	 * Get an audit log fragment for a single audit log
	 *
	 * @param array Arguments for the audit log
	 * @return array A formatted audit log fragment
	 */
	function get_audit_log_fragment( $args ) {
		$defaults = array(
			'user_login' => '',
			'new_state'  => '',
			'timestamp'  => time(),
		);
		$args = array_merge( $defaults, $args );

		$fragment = array(
			'timestamp' => $args['timestamp'],
		);

		// Handle removed users
		$user = get_user_by( 'login', $args['user_login'] );
		if ( $user ) {
			$avatar = get_avatar( $user->ID, 32 );
			$display_name = $user->display_name;
		} else {
			$avatar = '';
			$display_name = __( 'Someone', 'o2' );
		}

		if ( ! empty( $args['new_state'] ) ) {
			$log = sprintf( __( '%1$s marked this %2$s', 'o2' ), esc_html( $display_name ), esc_html( $args['new_state'] ) );
			$log = apply_filters( 'o2_resolved_posts_audit_log_entry', $log, $args );
		 } else {
			$log = sprintf( __( '%s removed resolution', 'o2' ), esc_html( $display_name ) );
		}
		$fragment['log'] = $log;
		$fragment['avatar'] = $avatar;

		return $fragment;
	}

	/**
	 * Process a post's state change by updating the post state and adding an audit log
	 *
	 * @param int The post ID
	 * @param string The next state slug
	 */
	function change_post_state( $post_id, $next_state ) {
		$success = self::set_post_state( $post_id, $next_state );
		if ( is_wp_error( $success ) )
			return $success;

		// Record change to audit log
		$args = array(
			'new_state'  => $next_state,
		);
		$args = self::add_audit_log( $post_id, $args );

		// Update post for polling
		o2_Fragment::bump_post( $post_id );

		return $args;
	}

	/**
	 * Parse the request if it's a request for our unresolved posts
	 */
	function request( $qvs ) {
		if ( !empty( $_GET['tags'] ) || !empty( $_GET['post_tag'] ) ) {
			$filter_tags = ( !empty( $_GET['tags'] ) ) ? $_GET['tags'] : $_GET['post_tag'];
			$filter_tags = (array)explode( ',', $filter_tags );
	 		foreach( (array)$filter_tags as $filter_tag ) {
	 			$filter_tag = sanitize_key( $filter_tag );
	 			$new_tax_query = array(
						'taxonomy' => 'post_tag',
					);
	 			if ( 0 === strpos( $filter_tag, '-') )
					$new_tax_query['operator'] = 'NOT IN';
				$filter_tag = trim( $filter_tag, '-' );
				if ( is_numeric( $filter_tag ) )
					$new_tax_query['field'] = 'ID';
				else
					$new_tax_query['field'] = 'slug';
				$new_tax_query['terms'] = $filter_tag;
	 			$qvs['tax_query'][] = $new_tax_query;
	 		}
	 	}

		return $qvs;
	}

	/**
	 * Main AJAX callback
	 *
	 * Determine if a post's next state is valid, set it, and return the audit log fragment as success, or
	 * an error message as failure
	 *
	 * @param object The post fragment object
	 */
	function callback( $post_data ) {
		if ( ! property_exists( $post_data, 'postID' ) || ! property_exists( $post_data, 'nextState' ) )
			self::die_failure( 'invalid_message',  __( 'Insufficient information provided.', 'o2' ) );

		$post = get_post( absint( $post_data->postID ) );
		if ( ! $post )
			self::die_failure( 'post_not_found', __( 'Post not found.', 'o2' ) );

		if ( ! current_user_can( 'edit_post', $post->ID ) )
			self::die_failure( 'cannot_edit_post_resolution', __( 'You are not allowed to edit this post resolution', 'o2' ) );

		if ( in_array( $post_data->nextState, self::get_state_slugs() ) )
			$next_state = sanitize_key( $post_data->nextState );
		else
			self::die_failure( 'invalid_post_resolution', __( 'Invalid post resolution.', 'o2' ) );

		$success = self::change_post_state( $post->ID, $next_state );
		if ( is_wp_error( $success ) )
			self::die_failure( 'cannot_set_post_resolution', __( 'Unable to set post resolution.', 'o2' ) );
		else
			$audit_log = self::get_audit_log_fragment( $success );

		$post_terms = o2_Fragment::get_post_terms( $post->ID );
		$retval = array(
			'currentState' => $next_state,
			'auditLog'     => $audit_log,
		);
		self::die_success( $retval );
	}

	function widgets_init() {
		register_widget( 'o2_ToDos_Widget' );
	}

	function page_title( $page_title ) {
		global $wp_query;
		$resolved_query = get_query_var( 'resolved' );
		if ( ! empty( $resolved_query ) ) {
			$resolved_query = strip_tags( wp_kses_no_null( trim( $resolved_query ) ) );
			$page_title = is_tag() && $page_title ? $page_title . " | " : '';
			if ( 'unresolved' === $resolved_query ) {
				$page_title .= sprintf( _x( 'Posts Marked To Do (%d)', 'resolved/unresolved posts', 'o2' ), $wp_query->found_posts );
			} else if ( 'resolved' === $resolved_query ) {
				$page_title .= sprintf( _x( 'Posts Marked Done (%d)', 'resolved/unresolved posts', 'o2' ), $wp_query->found_posts );
			} else {
				$page_title .= sprintf( _x( '%s Posts (%d)', 'resolved/unresolved posts', 'o2' ), $resolved_query, $wp_query->found_posts );
			}
		}
		return $page_title;
	}

	/*
	 * Enable the front side posting box to appropriate users when the resolved query is included
	 */
	function show_front_side_post_box_with_results( $options ) {
		$resolved_query = get_query_var( 'resolved' );
		if ( ! empty( $resolved_query ) ) {
			$options['options']['showFrontSidePostBox'] = ( is_user_logged_in() && current_user_can( 'publish_posts' ) );
		}
		return $options;
	}
} }

function o2_todos() {
	$o2_options = get_option( 'o2_options' );
	if ( ! empty ( $o2_options['enable_resolved_posts'] ) )
		new o2_ToDos();
}
add_action( 'o2_loaded', 'o2_todos' );
