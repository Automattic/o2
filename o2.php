<?php
/*
Plugin Name: o2
Plugin URI: http://geto2.com
Description: Front-page editing and live-updating posts/comments for your site.
Version: 0.2
Author: Automattic
Author URI: http://wordpress.com/
License: GNU General Public License v2 or later
*/

/*  Copyright 2013 Automattic

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

define( 'O2__PLUGIN_LOADED', true );
define( 'O2__FILE__', __FILE__ );
define( 'O2__DIR__', dirname( O2__FILE__ ) );

class o2 {
	private $editor;
	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	function __construct() {
		require O2__DIR__ . '/inc/fragment.php';
		require O2__DIR__ . '/inc/api-base.php';
		require O2__DIR__ . '/inc/read-api.php';
		require O2__DIR__ . '/inc/write-api.php';
		require O2__DIR__ . '/inc/nopriv-write-api.php';
		require O2__DIR__ . '/inc/templates.php';
		require O2__DIR__ . '/inc/template-tags.php';
		require O2__DIR__ . '/inc/editor.php';
		require O2__DIR__ . '/inc/keyboard.php';
		require O2__DIR__ . '/inc/text-helpers.php';
		require O2__DIR__ . '/inc/search.php';
		require O2__DIR__ . '/inc/widget-helper.php';

		// Terms in Comments powers the next group of files (must be loaded first)
		// @todo: Remove mention here once fully refactored. Wrapping in conditionals
		// in case these were already loaded in mu-plugins/inline-terms.php
		if ( ! class_exists( 'o2_Terms_In_Comments' ) ) {
			require O2__DIR__ . '/inc/terms-in-comments.php';
		}
		if ( ! class_exists( 'o2_Xposts' ) ) {
			require O2__DIR__ . '/inc/xposts.php';
			$this->xposts = new o2_Xposts();
		}
		if ( ! class_exists( 'o2_Tags' ) ) {
			require O2__DIR__ . '/inc/tags.php';
			$this->tags = new o2_Tags();
		}

		// Conditionaly load WPCOM-specifics
		if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) {
			require O2__DIR__ . '/inc/wpcom.php';
		}

		// Autoload o2 modules -- must have a load.php file to be loaded
		foreach ( glob( O2__DIR__ . '/modules/*/load.php' ) as $module ) {
			require $module;
		}

		// Load plugin text domain
		// add_action( 'init', array( $this, 'plugin_textdomain' ) );

		$this->editor               = new o2_Editor();
		$this->keyboard             = new o2_Keyboard();
		$this->templates            = new o2_Templates();
		$this->search               = new o2_Search();
		$this->post_list_creator    = new o2_Post_List_Creator;
		$this->comment_list_creator = new o2_Comment_List_Creator;

		// We handle comments ourselves, so Highlander shouldn't be involved
		$this->disable_highlander();

		// Post flair requires some special juggling
		add_action( 'init', array( $this, 'post_flair_mute' ), 11 );

		// Remove oembed handlers or cached results that are incompatible with o2
		add_action( 'init', array( $this, 'remove_oembed_handlers' ) );
		add_filter( 'embed_oembed_html', array( $this, 'remove_cached_incompatible_oembed' ), 10, 3 );

		// Add our read-only AJAX endpoint, for everyone
		add_action( 'wp_ajax_o2_read', array( 'o2_Read_API', 'init' ) );
		add_action( 'wp_ajax_nopriv_o2_read', array( 'o2_Read_API', 'init' ) );

		// And our AJAX endpoint for write operations, separate ones for authed and not-authed users
		add_action( 'wp_ajax_o2_write', array( 'o2_Write_API', 'init' ) );
		add_action( 'wp_ajax_nopriv_o2_write', array( 'o2_NoPriv_Write_API', 'init' ) );

		// And our AJAX handler for userdata
		add_action( 'wp_ajax_o2_userdata', array( $this, 'ajax_get_o2_userdata' ) );
		add_action( 'wp_ajax_nopriv_o2_userdata', array( $this, 'ajax_get_o2_userdata' ) );

		// Query var for login popup
		add_action( 'init', array( $this, 'add_query_vars' ) );

		// Handle Infinite Scroll requests
		add_action( 'infinite_scroll_render', array( $this, 'infinite_scroll_render' ) );

		// Register site styles and scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_scripts' ) );

		// Add body and post CSS classes
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'post_class', array( $this, 'post_class' ), 10, 3 );

		// On activation of an o2 compatible theme, set some defaults
		add_action( 'admin_init', array( $this, 'on_activating_o2_compatible_theme' ) );

		// Handle attachments differently
		add_action( 'template_redirect', array( $this, 'attachment_redirect' ) );

		// Register our custom functionality
		add_action( 'wp_head',     array( $this, 'wp_head' ), 100 );
		add_action( 'wp_footer',   array( $this, 'wp_footer' ) );
		add_action( 'wp_footer',   array( $this, 'scripts_and_styles' ) );
		add_filter( 'the_excerpt', array( 'o2', 'add_json_data' ), 999999 );
		add_filter( 'the_content', array( 'o2', 'add_json_data' ), 999999 );

		// Admin Options
		add_action( 'customize_register', array( $this, 'customize_register' ) );
		add_action( 'admin_init', array( $this, 'update_discussion_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_filter( 'o2_options', array( $this, 'required_comment_fields' ) );

		// Trashed comments functionality.
		add_action( 'delete_comment',    array( $this, 'delete_comment_override' ) );
		add_action( 'wp_insert_comment', array( $this, 'insert_comment_actions' ), 10, 2 );
		add_action( 'untrashed_comment', array( 'o2_Fragment', 'bump_comment_modified_time' ) );
		add_action( 'trashed_comment',   array( 'o2_Fragment', 'bump_comment_modified_time' ) );
		add_action( 'trash_comment',     array( $this, 'remove_trashed_parents' ) );
		add_action( 'trash_comment',     array( $this, 'maybe_set_comment_has_children' ) );
		add_action( 'untrash_comment',   array( $this, 'add_trashed_parents' ) );

		// After everything else has done its init, we need to run some first-load stuff
		add_action( 'init', array( $this, 'first_load' ), 100 );

		// o2 Loaded
		do_action( 'o2_loaded' );
	} // end constructor

	public function disable_highlander() {
		remove_action( 'init', array( 'Highlander_Comments', 'init' ) );
	}

	public function post_flair_mute() {
		if ( function_exists( 'post_flair' ) )
			remove_filter( 'the_content', array( post_flair(), 'display' ), 999 );
	}

	public function register_plugin_styles() {
		wp_register_style( 'o2-plugin-styles', plugins_url( 'css/style.css', O2__FILE__ ), array( 'genericons' ) );
		wp_style_add_data( 'o2-plugin-styles', 'rtl', 'replace' );
		wp_enqueue_style( 'o2-plugin-styles' );
	}

	public function register_plugin_scripts() {
		global $wp_locale;

		// Utils
		wp_enqueue_script( 'o2-compare-times',        plugins_url( 'js/utils/compare-times.js', O2__FILE__ ),       array( 'jquery' ) );
		wp_enqueue_script( 'o2-events',               plugins_url( 'js/utils/events.js', O2__FILE__ ),              array( 'backbone', 'jquery' ) );
		wp_enqueue_script( 'o2-highlight-on-inview',  plugins_url( 'js/utils/highlight-on-inview.js', O2__FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'o2-highlight',            plugins_url( 'js/utils/jquery.highlight.js', O2__FILE__ ),    array( 'jquery' ) );
		wp_enqueue_script( 'o2-is-valid-email',       plugins_url( 'js/utils/is-valid-email.js', O2__FILE__ ),      array( 'jquery' ) );
		wp_enqueue_script( 'o2-moment',               plugins_url( 'js/utils/moment.js', O2__FILE__ ),              array( 'jquery' ) );
		wp_enqueue_script( 'o2-raw-to-filtered',      plugins_url( 'js/utils/raw-to-filtered.js', O2__FILE__ ),     array( 'jquery' ) );
		wp_enqueue_script( 'o2-plugin-caret',         plugins_url( 'js/utils/caret.js', O2__FILE__ ),               array( 'jquery' ) );
		wp_enqueue_script( 'o2-plugin-placeholder',   plugins_url( 'js/utils/jquery.placeholder.js', O2__FILE__ ),  array( 'jquery' ) );
		wp_enqueue_script( 'o2-page-visibility',      plugins_url( 'js/utils/page-visibility.js', O2__FILE__ ),     array( 'jquery' ) );
		wp_enqueue_script( 'o2-timestamp',            plugins_url( 'js/utils/timestamp.js', O2__FILE__ ),           array( 'jquery', 'o2-moment' ) );
		wp_enqueue_script( 'o2-polling',              plugins_url( 'js/utils/polling.js', O2__FILE__ ),             array( 'backbone', 'jquery', 'o2-events' ) );
		wp_enqueue_script( 'o2-query',                plugins_url( 'js/utils/query.js', O2__FILE__ ),               array( 'backbone', 'jquery' ) );
		wp_enqueue_script( 'o2-template',             plugins_url( 'js/utils/template.js', O2__FILE__ ),            array( 'backbone', 'jquery', 'wp-util' ) );
		wp_enqueue_script( 'o2-enquire',              plugins_url( 'js/utils/enquire.js', O2__FILE__ ) );

		// Models
		wp_enqueue_script( 'o2-models-base',          plugins_url( 'js/models/base.js', O2__FILE__ ),        array( 'backbone', 'jquery', 'o2-highlight', 'o2-events' ) );
		wp_enqueue_script( 'o2-models-post',          plugins_url( 'js/models/post.js', O2__FILE__ ),        array( 'o2-models-base', 'backbone', 'jquery' ) );
		wp_enqueue_script( 'o2-models-comment',       plugins_url( 'js/models/comment.js', O2__FILE__ ),     array( 'o2-models-base', 'backbone', 'jquery' ) );
		wp_enqueue_script( 'o2-models-page-meta',     plugins_url( 'js/models/page-meta.js', O2__FILE__ ),   array( 'backbone', 'jquery' ) );
		wp_enqueue_script( 'o2-models-user',          plugins_url( 'js/models/user.js', O2__FILE__ ),        array( 'backbone', 'jquery' ) );
		wp_enqueue_script( 'o2-models-search-meta',   plugins_url( 'js/models/search-meta.js', O2__FILE__ ), array( 'backbone', 'jquery' ) );

		// Collections
		wp_enqueue_script( 'o2-collections-comments', plugins_url( 'js/collections/comments.js', O2__FILE__ ), array( 'o2-models-comment', 'o2-compare-times' ) );
		wp_enqueue_script( 'o2-collections-posts',    plugins_url( 'js/collections/posts.js', O2__FILE__ ),    array( 'o2-models-post', 'o2-compare-times' ) );
		wp_enqueue_script( 'o2-collections-users',    plugins_url( 'js/collections/users.js', O2__FILE__ ),    array( 'o2-models-user', 'underscore' ) );

		// Views
		wp_enqueue_script( 'o2-views-app-header',     plugins_url( 'js/views/app-header.js', O2__FILE__ ),    array( 'o2-models-page-meta', 'o2-events', 'utils', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-views-comment',        plugins_url( 'js/views/comment.js', O2__FILE__ ),       array( 'o2-models-comment', 'o2-editor', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-views-new-post',       plugins_url( 'js/views/new-post.js', O2__FILE__ ),      array( 'o2-models-post', 'o2-editor', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-views-post',           plugins_url( 'js/views/post.js', O2__FILE__ ),          array( 'o2-models-post', 'o2-collections-comments', 'o2-editor', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-views-no-posts-post',  plugins_url( 'js/views/no-posts-post.js', O2__FILE__ ), array( 'backbone', 'jquery', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-views-posts',          plugins_url( 'js/views/posts.js', O2__FILE__ ),         array( 'o2-collections-posts', 'jquery-color', 'o2-notifications', 'o2-views-no-posts-post', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-views-app-footer',     plugins_url( 'js/views/app-footer.js', O2__FILE__ ),    array( 'o2-models-page-meta', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-views-search-form',    plugins_url( 'js/views/search-form.js', O2__FILE__ ),   array( 'o2-models-search-meta', 'wp-backbone' ) );

		// Core application
		wp_enqueue_script(
			'o2-app',
			plugins_url( 'js/app/main.js', O2__FILE__ ),
			array(
				'o2-collections-users',
				'o2-events',
				'o2-keyboard', // @todo Re-write this as a module and load later
				'o2-models-page-meta',
				'o2-moment',
				'o2-views-app-footer',
				'o2-views-app-header',
				'o2-views-comment',
				'o2-views-new-post',
				'o2-views-post',
				'o2-views-posts',
				'utils',
			)
		);

		// Extend o2 by writing modules. Use o2-cocktail to load everything else first.
		wp_enqueue_script(
			'o2-cocktail',
			plugins_url( 'js/utils/cocktail.js', O2__FILE__ ),
			array(
				'o2-app',
				'o2-collections-comments',
				'o2-collections-posts',
				'o2-collections-users',
				'o2-compare-times',
				'o2-events',
				'o2-highlight',
				'o2-highlight-on-inview',
				'o2-is-valid-email',
				'o2-models-base',
				'o2-models-comment',
				'o2-models-page-meta',
				'o2-models-post',
				'o2-models-search-meta',
				'o2-models-user',
				'o2-moment',
				'o2-page-visibility',
				'o2-plugin-caret',
				'o2-plugin-placeholder',
				'o2-polling',
				'o2-query',
				'o2-raw-to-filtered',
				'o2-template',
				'o2-views-app-footer',
				'o2-views-app-header',
				'o2-views-comment',
				'o2-views-new-post',
				'o2-views-post',
				'o2-views-posts',
				'o2-views-search-form',
			)
		);

		// previous_posts and next_posts can return nonsense for pages, 404 and failed search pages.  This fixes that.
		$prev_page_url = previous_posts( false );
		$next_page_url = next_posts( null, false );
		$have_posts = have_posts();
		$view_type = $this->get_view_type();
		if ( 'page' == $view_type || '404' == $view_type || ( 'search' == $view_type && ! $have_posts ) ) {
			$prev_page_url = NULL;
			$next_page_url = NULL;
		}

		// Comment threading depth
		$thread_comments_depth = 1;
		if ( 1 == get_option( 'thread_comments' ) ) {
			$thread_comments_depth = get_option( 'thread_comments_depth' );
		}
		if ( 1 > $thread_comments_depth ) {
			$thread_comments_depth = 1;
		} elseif ( 10 < $thread_comments_depth ) {
			$thread_comments_depth = 10;
		}

		// Keep the query vars from this page so we can use them for polling, etc.
		global $wp_query;
		$query_vars = $wp_query->query_vars;
		$sanitized_query_vars = array();
		$allowed_query_vars = apply_filters( 'o2_query_vars', array(
			'author', 'author_name',                                           // Author
			'cat', 'category_name', 'tag', 'tag_id', 'tax_query',              // Taxonomy
			'year', 'monthnum', 'day', 'hour', 'minute', 'second', 'm', 'w',   // Time
			'p', 'name', 'page_id', 'pagename', 'page', 'paged',               // Post
			's',                                                               // Search
		) );
		foreach ( $query_vars as $query_var => $value ) {
			if ( in_array( $query_var, $allowed_query_vars ) && ! empty( $value ) )
				$sanitized_query_vars[ $query_var ] = $value;
		}
		$query_vars = apply_filters( 'o2_sanitized_query_vars', $sanitized_query_vars );

		$default_polling_interval = ( $this->is_mobile() || $this->is_tablet() ) ? 60000 : 10000;

		$show_front_side_post_box = false;

		if ( is_user_logged_in() && current_user_can( 'publish_posts' ) ) {
			$show_front_side_post_box = ( is_home() || is_search() || is_tag() );
		}

		$order = strtoupper( get_query_var( 'order' ) );
		if ( ! in_array( $order, array( 'DESC', 'ASC' ) ) ) {
			$order = 'DESC';
		}

		// Theme options
		// @todo init default options
		$defaults   = self::get_settings();
		$options    = get_option( 'o2_options', $defaults );
		$o2_options = array(
			'options' => array(
				'nonce'                                => wp_create_nonce( 'o2_nonce' ),
				'loadTime'                             => time() * 1000, // javascript time is millisecond resolution
				'readURL'                              => admin_url( 'admin-ajax.php?action=o2_read' ),
				'writeURL'                             => admin_url( 'admin-ajax.php?action=o2_write' ),
				'userDataURL'                          => admin_url( 'admin-ajax.php?action=o2_userdata' ),
				'loginURL'                             => wp_login_url(),
				'loginWithRedirectURL'                 => wp_login_url( home_url() . "?o2_login_complete=true" ),
				'pollingInterval'                      => (int) apply_filters( 'o2_polling_interval', $default_polling_interval ),
				'timestampFormat'                      => strip_tags( wp_kses_no_null( trim( __( '%1$s on %2$s', 'o2' ) ) ) ),
				'dateFormat'                           => apply_filters( 'o2_date_format', get_option( 'date_format' ) ),
				'timeFormat'                           => apply_filters( 'o2_time_format', get_option( 'time_format' ) ),
				'todayFormat'                          => strip_tags( wp_kses_no_null( trim( _x( '%s', 'time ago today', 'o2' ) ) ) ),
				'yesterdayFormat'                      => strip_tags( wp_kses_no_null( trim( __( 'Yesterday at %s', 'o2' ) ) ) ),
				'compactFormat'                        => array(
					'seconds'  => strip_tags( wp_kses_no_null( trim( __( 'Now', 'o2' ) ) ) ),
					'minutes'  => strip_tags( wp_kses_no_null( trim( _x( '%sm', 'time in minutes abbreviation', 'o2' ) ) ) ),
					'hours'    => strip_tags( wp_kses_no_null( trim( _x( '%sh', 'time in hours abbreviation', 'o2' ) ) ) ),
					'days'     => strip_tags( wp_kses_no_null( trim( _x( '%sd', 'time in days abbreviation', 'o2' ) ) ) ),
					'weeks'    => strip_tags( wp_kses_no_null( trim( _x( '%sw', 'time in weeks abbreviation', 'o2' ) ) ) ),
					'months'   => strip_tags( wp_kses_no_null( trim( _x( '%smon', 'time in months abbreviation', 'o2' ) ) ) ),
					'years'    => strip_tags( wp_kses_no_null( trim( _x( '%sy', 'time in years abbreviation', 'o2' ) ) ) ),
				),
				'i18nMoment'                           => $this->get_i18n_moment( $wp_locale, false ),
				'i18nLanguage'                         => apply_filters( 'o2_moment_language', 'en-o2' ),
				'infiniteScroll'                       => get_option( 'infinite_scroll' ) ? true : false,
				'prevPageURL'                          => $prev_page_url,
				'nextPageURL'                          => $next_page_url,
				'pageTitle'                            => $this->get_current_page_title(),
				'appContainer'                         => $this->get_application_container(),
				'threadContainer'                      => apply_filters( 'o2_thread_container', 'article' ),
				'showAvatars'                          => apply_filters( 'o2_show_avatars', get_option( 'show_avatars' ) ),
				'frontSidePostPrompt'                  => $options['front_side_post_prompt'] ? $options['front_side_post_prompt'] : '',
				'showFrontSidePostBox'                 => $show_front_side_post_box,
				'showCommentsInitially'                => $this->show_comments_initially(),
				'userMustBeLoggedInToComment'          => ( "1" == get_option( 'comment_registration' ) ),
				'requireUserNameAndEmailIfNotLoggedIn' => ( "1" == get_option( 'require_name_email' ) ),
				'viewType'                             => $view_type,
				'isPreview'                            => is_preview(),
				'showExtended'                         => strip_tags( wp_kses_no_null( trim( __( 'Show full post', 'o2' ) ) ) ),
				'hideExtended'                         => strip_tags( wp_kses_no_null( trim( __( 'Hide extended post', 'o2' ) ) ) ),
				'searchQuery'                          => get_search_query(),
				'havePosts'                            => $have_posts,
				'queryVars'                            => $query_vars,
				'order'                                => $order, /* surprisingly not in $query_vars */
				'threadCommentsDepth'                  => $thread_comments_depth,
				'isMobileOrTablet'                     => ( $this->is_mobile() || $this->is_tablet() ),
				'defaultAvatar'                        => get_option( 'avatar_default', 'identicon' ),
				'searchURL'                            => home_url( '/' ),
				'homeURL'                              => home_url( '/' ),
				'postId'                               => is_singular( array( 'post', 'page' ) ) ? get_the_ID() : 0,
				'mimeTypes'                            => get_allowed_mime_types(),
				'currentBlogId'                        => get_current_blog_id(),
			),
			'currentUser'       => o2_Fragment::get_current_user_properties(),
			'appControls'       => self::get_app_controls(),
			'postFormBefore'    => apply_filters( 'o2_post_form_before', '' ),
			'postFormExtras'    => apply_filters( 'o2_post_form_extras', '' ),
			'commentFormBefore' => apply_filters( 'o2_comment_form_before', '' ),
			'commentFormExtras' => apply_filters( 'o2_comment_form_extras', '' ),
			'strings' => array(
				'unsavedChanges'                       => __( 'You have unsaved changes.', 'o2' ),
				'saveInProgress'                       => __( 'Not all changes have been saved to the server yet. Please stay on this page until they are saved.', 'o2' ),
				'reloginPrompt'                        => __( 'Your session has expired. Click here to log in again. Your changes will not be lost.', 'o2' ),
				'reloginSuccessful'                    => __( 'You have successfully logged back in.', 'o2' ),
				'newCommentBy'                         => __( 'New comment by %s', 'o2' ),
				'newAnonymousComment'                  => __( 'New comment by someone', 'o2' ),
				'newPostBy'                            => __( 'New post by %s', 'o2' ),
				'newMentionBy'                         => __( '%1$s mentioned you: "%2$s"', 'o2' ),
				'filenameNotUploadedWithType'          => __( '%1$s was not uploaded (%2$s files are not allowed).', 'o2' ),
				'filenameNotUploadedNoType'            => __( '%1$s was not uploaded (unrecognized file type).', 'o2' ),
				'fileTypeNotSupported'                 => __( 'Sorry, %1$s files are not allowed.', 'o2' ),
				'unrecognizedFileType'                 => __( 'Sorry, file not uploaded (unrecognized file type).', 'o2' ),
				'pageNotFound'                         => __( 'Apologies, but the page you requested could not be found. Perhaps searching will help.', 'o2' ),
				'searchFailed'                         => __( 'Apologies, but I could not find any results for that search term. Please try again.', 'o2' ),
				'defaultError'                         => __( 'An unexpected error occurred. Please refresh the page and try again.', 'o2' ),
				'previewPlaceholder'                   => __( 'Generating preview...', 'o2' ),
				'bold'                                 => __( 'Bold (ctrl/⌘-b)', 'o2' ),
				'italics'                              => __( 'Italics (ctrl/⌘-i)', 'o2' ),
				'link'                                 => __( 'Link (⌘-shift-a)', 'o2' ),
				'image'                                => __( 'Image', 'o2' ),
				'blockquote'                           => __( 'Blockquote', 'o2' ),
				'code'                                 => __( 'Code', 'o2' ),
				'addPostTitle'                         => __( 'Add a post title', 'o2' ),
				'enterTitleHere'                       => __( 'Enter title here', 'o2' ),
				'noPosts'                              => __( 'Ready to publish your first post? Simply use the form above.', 'o2' ),
				'noPostsMobile'                        => __( 'Tap the new post control below to begin writing your first post.', 'o2' ),
				'awaitingApproval'                     => __( 'This comment is awaiting approval.', 'o2' ),
				'isTrashed'                            => __( 'This comment was trashed.', 'o2' ),
				'prevDeleted'                          => __( 'This comment was deleted.', 'o2' ),
				'cancel'                               => __( 'Cancel', 'o2' ),
				'edit'                                 => __( 'Edit', 'o2' ),
				'email'                                => __( 'Email', 'o2' ),
				'name'                                 => __( 'Name', 'o2' ),
				'permalink'                            => __( 'Permalink', 'o2' ),
				'post'                                 => _x( 'Post', 'Verb, to post', 'o2' ),
				'reply'                                => __( 'Reply', 'o2' ),
				'save'                                 => __( 'Save', 'o2' ),
				'saving'                               => __( 'Saving', 'o2' ),
				'website'                              => __( 'Website', 'o2' ),
				'search'                               => __( 'Search', 'o2' ),
				'anonymous'                            => __( 'Someone', 'o2' ),
				'preview'                              => __( 'Preview', 'o2' ),
				'olderPosts'                           => __( 'Older posts', 'o2' ),
				'newerPosts'                           => __( 'Newer posts', 'o2' ),
				'loginToComment'                       => __( 'Login to leave a comment.', 'o2' ),
				'fillDetailsBelow'                     => __( 'Fill in your details below.', 'o2' ),
				'editingOthersComment'                 => __( "Careful! You are editing someone else's comment.", 'o2' ),
				'commentURL'                           => __( 'Website', 'o2' ),
				'showComments'                         => __( 'Show Comments', 'o2' ),
				'hideComments'                         => __( 'Hide Comments', 'o2' ),
				'redirectedHomePostTrashed'            => __( 'This post was trashed. You will be redirected home now.', 'o2' ),
				'redirectedHomePageTrashed'            => __( 'This page was trashed. You will be redirected home now.', 'o2' ),
				'postBeingTrashed'                     => __( 'This post is being trashed.', 'o2' ),
				'pageBeingTrashed'                     => __( 'This page is being trashed.', 'o2' ),
				'postTrashedFailed'                    => __( 'There was an error trashing that post. Please try again in a moment.', 'o2' ),
				'pageTrashedFailed'                    => __( 'There was an error trashing that page. Please try again in a moment.', 'o2' ),
			),
		);

		$o2_options = apply_filters( 'o2_options', $o2_options );
		// We cannot "localize" directly into the o2 object here, since that would cause
		// our early loaded models, views, etc to be blown away
		// So.... we "localize" into a o2Config object that will be "extended" into o2 itself
		// on o2.App.start
		wp_localize_script( 'o2-app', 'o2Config', $o2_options );
	}

	/**
	 * Get the list of scripts and styles already on the page
	 */
	public static function scripts_and_styles() {
		global $wp_scripts, $wp_styles;

		$scripts = is_a( $wp_scripts, 'WP_Scripts' ) ? $wp_scripts->done : array();
		$scripts = apply_filters( 'infinite_scroll_existing_scripts', $scripts );

		$styles = is_a( $wp_styles, 'WP_Styles' ) ? $wp_styles->done : array();
		$styles = apply_filters( 'infinite_scroll_existing_stylesheets', $styles );

		?><script type="text/javascript">
			o2Config.options.scripts = <?php echo json_encode( $scripts ); ?>;
			o2Config.options.styles = <?php echo json_encode( $styles ); ?>;
		</script><?php
	}

	function get_app_controls() {
		$app_controls = array(
			'<a href="#" class="o2-toggle-comments" data-alternate-text="' . esc_html__( 'Show comment threads', 'o2' ) . '">' . esc_html__( 'Hide comment threads', 'o2' ) . '</a>',
		);
		return apply_filters( 'o2_app_controls', $app_controls );
	}

	/**
	 * Return only o2 containers with Infinite Scroll queries
	 */
	function infinite_scroll_render() {
		global $post;
		while ( have_posts() ) {
			the_post();

			$fragment = o2_Fragment::get_fragment( $post );
			?><script class='o2-data' type='application/json' style='display:none;'><?php echo json_encode( $fragment ); ?></script><?php
		}
	}

	function get_application_container() {
		return apply_filters( 'o2_application_container', '#content' );
	}

	function show_comments_initially() {
		$show_comments = true;

		if ( isset( $_COOKIE['showComments'] ) && 'false' === $_COOKIE['showComments'] )
			$show_comments = false;

		if ( ( $this->is_mobile() || $this->is_tablet() ) && ( ! is_single() || ! is_page() ) )
			$show_comments = false;

		return apply_filters( 'o2_show_comments_initially', $show_comments );
	}

	/**
	 * Adds an 'o2' class to the body.
	 */
	function body_class( $classes ) {
		$classes[] = 'o2';
		return $classes;
	}

	/**
	 * Add an author class to posts.
	 */
	function post_class( $classes, $class, $post_id ) {
		$post = get_post( $post_id );
		if ( empty( $post ) )
			return $classes;

		$user = get_user_by( 'id', $post->post_author );
		if ( empty( $user ) )
			return $classes;

		$classes[] = 'author-' . sanitize_html_class( $user->user_nicename, $user->ID );
		return $classes;
	}

	public static function get_defaults() {
		return array(
			'o2_enabled'             => true, //enable o2 functionality by default
			'front_side_post_prompt' => __( 'Hi, {name}! What\'s happening?', 'o2' ),
			'enable_resolved_posts'  => false,
			'mark_posts_unresolved'  => false
		);
	}

	/**
	 * Set o2_option defaults, merge with get_theme_support, merge with pre-existing o2_options
	 */
	public function get_settings() {
		// Set o2 defaults
		$settings = $defaults = self::get_defaults();

		// Get theme support options, merge with $settings
		if ( is_array( get_theme_support( 'o2' ) ) ) {
			$_settings = current( get_theme_support( 'o2' ) );
			$settings  = self::settings_merge( $defaults, $settings, $_settings );
		}

		// Get pre-existing o2_options, merge with $settings
		$_settings = get_option( 'o2_options', $settings );
		$settings  = self::settings_merge( $settings, $settings, $_settings );

		$settings = apply_filters( 'o2_get_settings', $settings );

		update_option( 'o2_options', $settings );

		return $settings;
	}

	/**
	 * Add the required strings for logged out commenters
	 */
	public static function required_comment_fields( $options ) {
		if ( 1 == get_option( 'require_name_email' ) ) {
			$options['strings']['commentName']  = __( 'Name (required)', 'o2' );
			$options['strings']['commentEmail'] = __( 'Email (required)', 'o2' );
		} else {
			$options['strings']['commentName']  = __( 'Name', 'o2' );
			$options['strings']['commentEmail'] = __( 'Email', 'o2' );
		}
		return $options;
	}

	/**
	 * Logic to merge option/default arrays
	 */
	public function settings_merge( $defaults, $settings, $_settings ) {
		if ( isset( $_settings ) && is_array( $_settings ) ) {
			foreach ( $_settings as $key => $value ) {
				switch ( $key ) {
					case 'o2_enabled' :
						if ( isset( $value ) )
							$settings[ $key ] = (bool) $value;

						break;

					case 'front_side_post_prompt' :
						if ( isset( $value ) )
							$settings[ $key ] = esc_attr( $value );

						break;

					case 'enable_resolved_posts' :
						if ( isset( $value ) )
							$settings[ $key ] = (bool) $value;

						break;

					case 'mark_posts_unresolved' :
						if ( isset( $value ) )
							$settings[ $key ] = (bool) $value;

						break;

					default:
						continue;

						break;
				}
			}
		}

		$settings = wp_parse_args( $settings, $defaults );

		return $settings;
	}

	/**
	 * Set up options for Customizer
	 */
	public function customize_register( $wp_customize ) {
		$defaults = self::get_defaults();

		$wp_customize->add_section( 'o2_options', array(
			'title'         => __( 'Theme Options', 'o2' ),
			'priority'      => 35,
		) );

		$wp_customize->add_setting( 'o2_options[front_side_post_prompt]', array(
			'default'       => esc_attr( $defaults['front_side_post_prompt'] ),
			'type'          => 'option',
		) );

		$wp_customize->add_control( 'o2_options[front_side_post_prompt]', array(
			'label'         => __( 'Front-end Post Prompt: Use {name} for user\'s name', 'o2' ),
			'section'       => 'o2_options',
			'priority'      => 1,
		) );

		$wp_customize->add_setting( 'o2_options[enable_resolved_posts]', array(
			'default'       => (bool) $defaults['enable_resolved_posts'],
			'type'          => 'option',
		) );

		$wp_customize->add_control( 'o2_options[enable_resolved_posts]', array(
			'label'         => __( 'Enable "To Do" Module', 'o2' ),
			'section'       => 'o2_options',
			'type'          => 'checkbox',
			'priority'      => 2,
		) );

		$wp_customize->add_setting( 'o2_options[mark_posts_unresolved]', array(
			'default'       => (bool) $defaults['mark_posts_unresolved'],
			'type'          => 'option',
		) );

		$wp_customize->add_control( 'o2_options[mark_posts_unresolved]', array(
			'label'         => __( 'Mark New Posts "To Do"', 'o2' ),
			'section'       => 'o2_options',
			'type'          => 'checkbox',
			'priority'      => 3,
		) );
	}

	/**
	* Returns what type of 'view'/'page' is being returned by WordPress (home, single, search, etc)
	*/
	public static function get_view_type( ) {
		$type = 'home';
		if ( is_home() )
			$type = 'home';
		else if ( is_page() )
			$type = 'page';
		else if ( is_singular() )
			$type = 'single';
		else if ( is_search() )
			$type = 'search';
		else if ( is_archive() )
			$type = 'archive';
		else if ( is_404() )
			$type = '404';
		return apply_filters( 'o2_view_type', $type );
	}

	/*
	 *
	 */
	public function get_current_page_title() {
		global $wp_query;
		$page_title = '';
		$obj = get_queried_object();

		if ( is_home() )
			$page_title = get_bloginfo( 'name', 'display' );
		else if ( is_page() )
			$page_title = the_title( '', '', false );
		elseif ( is_author() ) {
			$page_title = sprintf( __( 'Posts by %s', 'o2' ), $obj->display_name );
			$current_user = wp_get_current_user();
			if ( $current_user instanceof WP_User ) {
				if ( $current_user->display_name === $obj->display_name ) {
					$page_title = __( 'My Posts', 'o2' );
				}
			}
		} elseif ( is_category() ) {
			$page_title = sprintf( __( 'Posts categorized as %s', 'o2' ), single_cat_title( '', false ) );
		} elseif ( is_tag() ) {
			$query_slugs = explode( ',', get_query_var( 'tag' ) );
			$tag_titles = array();
			foreach( (array) $query_slugs as $query_slug ) {
				if ( ! $query_slug ) {
					continue;
				}
				$query_slug = strip_tags( wp_kses_no_null( trim( $query_slug ) ) );
				$found_tag = get_term_by( 'slug', $query_slug, 'post_tag' );
				if ( $found_tag ) {
					$tag_titles[] = sprintf( '#%s', $found_tag->name );
				}
			}
			$page_title = implode( ', ', $tag_titles );
		} elseif ( is_search() ) {
			$page_title = sprintf( __( "Posts containing &lsquo;%s&rsquo; (%d)", 'o2' ), get_search_query(), $wp_query->found_posts );
		} elseif ( is_404() ) {
			$page_title = __( 'Page Not Found', 'o2' );
		} elseif ( is_archive() ) {
			if ( is_day() )
				$page_title =  __( 'Daily Archives: ', 'o2' ) . get_the_date();
			elseif ( is_month() )
				$page_title = __( 'Monthly Archives: ', 'o2' ) . get_the_date( 'F Y' );
			elseif ( is_year() )
				$page_title = __( 'Yearly Archives: ', 'o2' ) . get_the_date( 'Y' );
			else
				$page_title = __( 'Post Archives', 'o2' );
		}

		$page_title = apply_filters( 'the_title', $page_title );

		// We run a separate filter for the page title because the_title filter
		// will change other content on the page (like menu items) that we don't
		// want to change

		$page_title = apply_filters( 'o2_page_title', $page_title );

		return $page_title;
	}

	/*
	 * Add our special query vars (i.e. for login complete)
	 */
	public function add_query_vars() {
		global $wp;
		$wp->add_query_var( 'o2_login_complete' );
	}

	public function wp_head() {
		// Suppress output; will show things once we're fully loaded
		?>
		<style>
		<?php echo esc_js( $this->get_application_container() ); ?> {
			display: none;
		}
		</style>
		<?php
	}

	/**
	 * Start setting up o2 on the client side.
	 */
	public function wp_footer() {
		$this->run_browser_check();

		// Bootstrap the users
		global $o2_userdata;
		if ( is_array( $o2_userdata ) && count( $o2_userdata ) > 0 ) {
			echo "<script class='o2-user-data' type='application/json' style='display:none'>";
			echo json_encode( $o2_userdata );
			echo "</script>\n";
		}

		$login_complete_var = get_query_var( 'o2_login_complete' );
		if ( ! empty( $login_complete_var ) ) {
			?>
			<script>
				jQuery( document ).ready( function( $ ) {
					if ( "undefined" != typeof window.opener && null != window.opener ) {
						window.opener.o2.App.onLogInComplete();
					}
					window.close();
				});
			</script>
			<?php
			do_action( 'o2_wp_footer_nopriv' );
		} else {
			?>
			<script>
				jQuery(document).ready(function($) {
					var bootstrap = { data: [] };
					o2Data = $( '.o2-data' );
					if ( o2Data.length > 0 ) {
						o2Data.each( function() {
							// Parse the JSON that's embedded in the page and add it to the bootstrap data
							var me = $( this );
							var thread;
							try {
								thread = $.parseJSON( me.text() );
							} catch( e ) {
								thread = false;
								console.log( '$.parseJSON failure: ' + me.text() );
							}
							if ( false !== thread ) {
								_.each( thread, function( frag ) {
									bootstrap.data.push( frag );
								} );
							}
							me.remove();
						} );
					}

					// Merge o2Config into o2 itself
					o2 = $.extend( o2, o2Config );

					// Some generally-useful references
					o2.$body = $( 'body' );
					o2.$appContainer = $( o2.options.appContainer );

					// As soon as o2 loads, poll for new content to account
					// for Chrome's caching weirdness on back/tab-recovery.
					o2.$appContainer.on( 'ready.o2', function() {
						o2.Polling.poll();
					} );

					// Bootstrap o2 with any in-page content
					o2.start( bootstrap );
				});
			</script>
			<?php
			do_action( 'o2_wp_footer' );
		}
	}

	/**
	 * Check for IE8, IE9 and inject a little Javascript to warn of compatibility issues
	 */
	public function run_browser_check() {

		if ( ! preg_match_all( "/.*MSIE\s*([0-9.]+).*/", $_SERVER['HTTP_USER_AGENT'], $matches ) ) {
			return;
		}

		if ( (int) $matches[1][0] > 9 ) {
			return;
		}

		$text = esc_html__( 'Your browser is not up-to-date.  As a result, some features may not work correctly.  Please switch to an updated browser.', 'o2' );

		?>
		<script type="text/javascript">
			jQuery( document ).ready( function() {
				jQuery( 'body' ).prepend( '<div class="o2-browser-warning"><?php echo esc_js( $text ); ?></div>' );
			} );
		</script>
		<?php
	}

	/**
	 * Embed JSONified post+comment data into each thread (post) for backbone consumption
	 */
	public static function add_json_data( $content ) {
		global $post;

		// password protected post? return immediately (password protected pages are OK)
		if ( ! is_page() && ! empty( $post->post_password ) ) {
			return $content;
		}

		$conversation = array();
		if ( is_single() || is_category() || is_archive() || is_author() || is_home() || is_page() || is_search() ) {
			if ( current_filter() === 'the_content' ) {
				$conversation[] = o2_Fragment::get_fragment( $post, array( 'find-adjacent' => is_single(), 'the_content' => $content ) );
			} else {
				// Other content filters (particularly, the_excerpt) may try to strip tags, which causes issues
				// for the various actions that o2 adds to the end of the content.
				$conversation[] = o2_Fragment::get_fragment( $post, array( 'find-adjacent' => is_single() ) );
			}

			// Append the encoded conversation to the content in a hidden script block
			$content .= "<script class='o2-data' id='o2-data-{$post->ID}' data-post-id='{$post->ID}' type='application/json' style='display:none'>";
			$content .= json_encode( $conversation );
			$content .= "</script>\n";
		}

		return $content;
	}

	/**
	* Remove oembed handlers that are incompatible with o2
	*/
	public function remove_oembed_handlers() {
		wp_oembed_remove_provider( '#https?://(.+\.)?polldaddy\.com/.*#i' );
		wp_oembed_remove_provider( '#https?://poll\.fm/.*#i' );
	}

	/**
	* Remove cached incompatible oembed results that a previous theme
	* may have saved
	*/
	public function remove_cached_incompatible_oembed( $html, $url, $args ) {
		if ( false !== strpos( $html, 'polldaddy.com' ) || false !== strpos( $html, 'poll.fm' ) ) {
			return $url;
		}
		return $html;
	}

	/**
	 * Transforms the WP_Locale translations for the Moment.js JavaScript class.
	 *
	 * @param $locale WP_Locale - A locale object
	 * @param $json_encode bool - Whether to encode the result. Default true.
	 * @return string|array     - The translations object.
	 */
	function get_i18n_moment( $locale, $json_encode = true ) {
		$moment = array(
			'months'         => array_values( $locale->month ),
			'monthsShort'    => array_values( $locale->month_abbrev ),
			'weekdays'       => array_values( $locale->weekday ),
			'weekdaysShort'  => array_values( $locale->weekday_abbrev ),
			'weekdaysMin'    => array_values( $locale->weekday ),
			'relativeTime'   => array(
				'future' => strip_tags( wp_kses_no_null( trim( _x( 'in %s', 'time from now', 'o2' ) ) ) ),
				'past'   => strip_tags( wp_kses_no_null( trim( _x( '%s ago', 'time ago', 'o2' ) ) ) ),
				's'      => strip_tags( wp_kses_no_null( trim( _x( 'a few seconds', 'unit of time', 'o2' ) ) ) ),
				'm'      => strip_tags( wp_kses_no_null( trim( _x( 'a min', 'abbreviation of minute', 'o2' ) ) ) ),
				'mm'     => strip_tags( wp_kses_no_null( trim( _x( '%d mins', 'abbreviation of minutes', 'o2' ) ) ) ),
				'h'      => strip_tags( wp_kses_no_null( trim( __( 'an hour', 'o2' ) ) ) ),
				'hh'     => strip_tags( wp_kses_no_null( trim( __( '%d hours', 'o2' ) ) ) ),
				'd'      => strip_tags( wp_kses_no_null( trim( __( 'a day', 'o2' ) ) ) ),
				'dd'     => strip_tags( wp_kses_no_null( trim( __( '%d days', 'o2' ) ) ) ),
				'M'      => strip_tags( wp_kses_no_null( trim( __( 'a month', 'o2' ) ) ) ),
				'MM'     => strip_tags( wp_kses_no_null( trim( __( '%d months', 'o2' ) ) ) ),
				'y'      => strip_tags( wp_kses_no_null( trim( __( 'a year', 'o2' ) ) ) ),
				'yy'     => strip_tags( wp_kses_no_null( trim( __( '%d years', 'o2' ) ) ) ),
			),
		);

		if ( $json_encode )
			return json_encode( $moment );
		else
			return $moment;
	}

	/**
	 * Redirect to attachments. Rather than trying to render them somehow,
	 * if someone links to an "attachment page", just intercept that and send
	 * the user directly the actual attachment resource (image, PDF, whatever)
	 */
	function attachment_redirect() {
		if ( is_attachment() ) {
			wp_safe_redirect( wp_get_attachment_url( get_the_ID() ) );
			exit;
		}
	}

	/**
	 * Enforce selected discussion settings to fixed values
	 */
	function update_discussion_settings() {
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			// for most options, update_option will not write the db if the option hasn't changed,
			// so it is OK to simply call update_option without checking if the
			// option needs updating first

			// page_comments - o2 doesn't page - so should always be empty = not paged
			update_option( 'page_comments', '' );

			// comments_per_page, default_comments_page - we don't use these, but let's make
			// sure a reasonable value is present
			$comments_per_page = get_option( 'comments_per_page' );
			if ( 50 != $comments_per_page ) {
				update_option( 'comments_per_page', 50 );
			}
			update_option( 'default_comments_page', 'newest' );

			// comment_order - o2 always does older at top, so enforce "asc"
			update_option( 'comment_order', 'asc' );
		}
	}

	function admin_enqueue_scripts( $hook ) {
		// Settings > Discussion
		if ( 'options-discussion.php' == $hook ) {
			$script_url = plugins_url( 'js/admin/discussion-settings.js', O2__FILE__ );
			wp_enqueue_script( 'o2-admin-discussion', $script_url, array( 'jquery' ) );
		}
	}

	/**
	 * If an o2 compatible theme has just been activated, set some defaults
	 * If it has no widgets set up in any of its widget areas, add some
	 */
	public function on_activating_o2_compatible_theme() {
		// Only on the themes page in wp admin
		if ( ! is_admin() || FALSE === strpos( $_SERVER['REQUEST_URI'], '/wp-admin/themes.php' ) ) {
			return;
		}

		// Only on activation
		if ( FALSE === strpos( $_SERVER['REQUEST_URI'], 'activated=' ) ) {
			return;
		}

		// If they have just activated an o2 capable theme,
		if ( ! current_theme_supports( 'o2' ) ) {
			return;
		}

		// Set some defaults
		// thread_comments - turn on initially on for o2
		update_option( 'thread_comments', '1' );

		// comment_whitelist - turn off initially for o2
		delete_option( 'comment_whitelist' );

		// thread_comments_depth - set initially to 10 for o2
		$thread_comments_depth = get_option( 'thread_comments_depth' );
		if ( 10 != $thread_comments_depth ) {
			update_option( 'thread_comments_depth', 10 );
		}

		// Enable Post Likes on the homepage
		// The format for storing this option is ugly, and there's no API to access it
		if ( function_exists( 'wpl_is_enabled_sitewide' ) ) {
			// Turn on 'index' display for Post Likes if any sharing options are configured
			if ( $sharing_options = get_option( 'sharing-options' ) ) {
				if (
					empty( $sharing_options['global']['show'] )
				||
					(
						'index' != $sharing_options['global']['show' ]
					&&
						!in_array( 'index', (array) $sharing_options['global']['show'] )
					)
				) {
					if ( is_array( $sharing_options['global']['show'] ) ) {
						// Add index to the array of where it's enabled
						$sharing_options['global']['show'][] = 'index';
					} else if ( is_string( $sharing_options['global']['show'] ) ) {
						// Default is 'posts' but when it's an array it's 'post', so let's
						// just force the array and activate everywhere
						$sharing_options['global']['show'] = array( 'index', 'post', 'page', 'attachment' );
					}
					update_option( 'sharing-options', $sharing_options );
				}
			}
		}

		// Enable Comment Likes
		if ( function_exists( 'wpl_is_comments_enabled_sitewide' ) ) {
			update_option( 'jetpack_comment_likes_enabled', true );
		}

		// Use Widget Helper to add some widgets
		$widget_helper = new o2_Widget_Helper();

		// Has P2 been run on this blog before?  If so, let's start with
		// its widgets (except for p2_ widgets)
		$p2_mods = get_option( 'theme_mods_pub/p2' );
		if ( false === $p2_mods ) {
			$p2_mods = get_option( 'theme_mods_p2' );
		}

		if ( false !== $p2_mods ) {
			$p2_sidebar_widgets = array();
			if ( isset( $p2_mods['sidebars_widgets']['data']['sidebar-1'] ) ) {
				$p2_sidebar_widgets = $p2_mods['sidebars_widgets']['data']['sidebar-1'];
			}

			foreach( (array) $p2_sidebar_widgets as $p2_sidebar_widget ) {
				// filter out any p2_ widget
				if ( 'p2_' != substr( $p2_sidebar_widget, 0, 3 ) ) {
					$widget_helper->add_existing_widget( $p2_sidebar_widget );
				}
			}
		}

		// Determine which o2 widgets need to be added
		$search_added     = $widget_helper->has_widget( 'search', 'sidebar-1' );
		$filter_added     = $widget_helper->has_widget( 'o2-filter-widget', 'sidebar-1' );
		$activity_added   = $widget_helper->has_widget( 'o2-live-comments', 'sidebar-1' );

		// Add the Search widget
		if ( ! $search_added ) {
			$widget_helper->add_new_widget(
				'search' /* widget base ID from widget class def */,
				true, /* true: multiwidget, false: not */
				array(
					'title' => __( 'Search', 'o2' ) /* widget settings */
				),
				'sidebar-1' /* widget area to add to, empty for default */
			);
		}

		// Add the o2 Filter Widget
		if ( ! $filter_added ) {
			$widget_helper->add_new_widget(
				'o2-filter-widget', /* widget base ID from widget class def */
				true,   /* true: multiwidget, false: not */
				array(
					'title' => '' /* widget settings */
				),
				'sidebar-1'
			);
		}

		// Add the o2 Recent Activity Widget
		if ( ! $activity_added ) {
			$widget_helper->add_new_widget(
				'o2-live-comments-widget' /* widget base ID from widget class def */,
				true, /* true: multiwidget, false: not */
				array(
					'title' => __( 'Recent Activity', 'o2' ), /* widget settings */
					'kind' => 'both',
					'number' => 10
				),
				'sidebar-1' /* widget area to add to, empty for default */
			);
		}

		// Remove Recent Posts and Recent Comments widgets
		if ( $widget_helper->has_widget( 'recent-comments' ) ) {
			$widget_helper->remove_widget_instances( 'recent-comments' );
		}
		if ( $widget_helper->has_widget( 'recent-posts' ) ) {
			$widget_helper->remove_widget_instances( 'recent-posts' );
		}
	}

	/*
	 * Returns true if the user agent is recognized as a mobile device (or a tablet)
	 */
	public static function is_mobile() {
		$is_mobile = ( function_exists( 'jetpack_is_mobile' ) ) ? jetpack_is_mobile() : wp_is_mobile();
		return $is_mobile;
	}

	/*
	 * Returns true if the user agent is recognized as a tablet
	 */
	public static function is_tablet() {
		$is_tablet = ( class_exists( 'Jetpack_User_Agent_Info' ) ) ? Jetpack_User_Agent_Info::is_tablet() : false;
		return $is_tablet;
	}

	/**
	 * Create a new comment with message "This comment has been permanently deleted."
	 * and put it in place of deleted comment.
	 *
	 * @param $comment_ID
	 */
	public function delete_comment_override( $comment_ID ) {
		$children = get_comments( array( 'status' => 'approve', 'parent' => $comment_ID ) );
		if ( ! empty( $children ) ) {
			$old_comment    = get_comment( $comment_ID );
			$comment_to_add = $old_comment;

			unset( $comment_to_add->comment_ID );
			$comment_to_add->comment_approved = 1;
			$comment_to_add->comment_content = __( 'This comment was deleted.', 'o2' );

			$new_comment_id = wp_insert_comment( (array) $comment_to_add );
			$comment_created = get_comment_meta( $comment_ID, 'o2_comment_created', true );
			if ( empty ( $comment_created ) ) {
				$comment = get_comment( $comment_ID );
				$comment_created = strtotime( $comment->comment_date_gmt );
			}

			update_comment_meta( $new_comment_id, 'o2_comment_created', $comment_created );

			o2_Fragment::bump_comment_modified_time( $new_comment_id );
			update_comment_meta( $new_comment_id, 'o2_comment_prev_deleted', $comment_ID );

			$clean_comment_ids = array();

			foreach ( $children as $child ) {
				$clean_comment_ids[] = $child->comment_ID;

				$child->comment_parent = $new_comment_id;
				wp_update_comment( (array) $child );
			}

			$clean_comment_ids[] = $new_comment_id;
			clean_comment_cache( $clean_comment_ids );
		}
	}

	/**
	 * Add a timestamp named o2_comment_created so we can maintain
	 * when the comment was created even if comment is later modified.
	 *
	 * @param $comment_ID
	 * @param $comment
	 */
	public function insert_comment_actions( $comment_ID, $comment ) {
		update_comment_meta( $comment_ID, 'o2_comment_created', current_time( 'timestamp', true ) );
	}

	/**
	 * Returns true if comment has approved child.
	 *
	 * @param $comment_ID
	 *
	 * @return bool
	 */
	public function has_approved_child( $comment_ID ) {
		$children = get_comments( array( 'status' => 'approve', 'parent' => $comment_ID ) );

		return ! empty( $children );
	}

	/**
	 * When un-trashing a comment, traverse through this comment's parents and
	 * add o2_comment_has_children flag where needed.
	 *
	 * @param $comment_ID
	 * @param bool $comment
	 */
	public function add_trashed_parents( $comment_ID, $comment = false ) {
		$comment = ! empty( $comment ) ? $comment : get_comment( $comment_ID );
		if (  0 < $comment->comment_parent ) {
			$parent = get_comment( $comment->comment_parent );
			if ( 'trash' == $parent->comment_approved ) {
				$this->add_trashed_parents( $parent->comment_ID, $parent );
				update_comment_meta( $parent->comment_ID, 'o2_comment_has_children', true );
				o2_Fragment::bump_comment_modified_time( $parent->comment_ID );
			}
		}
	}

	/**
	 * If this comment has no approved siblings, then go ahead and delete its parent as well.
	 *
	 * @param $comment_ID
	 * @param bool|object $comment
	 */
	public function remove_trashed_parents( $comment_ID, $comment = false ) {
		$child_count = 0;
		$has_approved_children = false;

		if ( empty( $comment ) ) {

			// If $comment isn't set, then assume we haven't recursed and setup vars.
			$child_count = 1;
			$comment = get_comment( $comment_ID );
			$has_approved_children = $this->has_approved_child( $comment_ID );
		}

		if ( ! $has_approved_children && 0 < $comment->comment_parent ) {
			$parent = get_comment( $comment->comment_parent );
			if ( 'trash' == $parent->comment_approved ) {
				$children = get_comments(
					array(
						'count' => true,
						'parent' => $parent->comment_ID
					)
				);
				if ( $child_count == $children ) {
					delete_comment_meta( $parent->comment_ID, 'o2_comment_has_children', true );
					o2_Fragment::bump_comment_modified_time( $parent->comment_ID );
					$this->remove_trashed_parents( $parent->comment_ID, $parent );
				}
			}
		}
	}

	/**
	 * Add has_children flag to deleted comments so that we can query for only trashed comments
	 * that have children in o2_Fragent:get_fragment_from_post() for bootstrapping.
	 *
	 * @param $comment_ID
	 */
	public function maybe_set_comment_has_children( $comment_ID ) {
		$children = get_comments( array( 'parent' => $comment_ID ) );

		if ( ! empty( $children ) ) {
			update_comment_meta( $comment_ID, 'o2_comment_has_children', true );
		}
	}

	public function ajax_get_o2_userdata() {
		// returns the o2 userdata for the given userLogin, or an error if a bad userLogin was given
		// note:  both priv and nopriv hit this, since o2s can be read by nopriv users

		$ok_to_serve_data = apply_filters( 'o2_read_api_ok_to_serve_data', true );
		if ( ! $ok_to_serve_data ) {
			wp_send_json_error( array( 'errorText' => 'Unauthorized' ) );
		}

		if ( isset( $_GET['userlogin'] ) ) {
			// V1 userlogin (singular)
			// it will be OK to remove this case after the new V2 has been deployed
			// for a day or two, so as to not disrupt V1 clients that are still active
			$user_login = $_GET['userlogin'];
			$user = get_user_by( 'login', $user_login );

			if ( false === $user ) {
				wp_send_json_error( array( 'errorText' => 'Invalid request' ) );
			}

			// Otherwise, create and send the model
			$user_data = get_userdata( $user->ID );
			$user_model = o2_Fragment::get_model_from_userdata( $user_data );
			wp_send_json_success( $user_model );
		} else {
			// V2 userlogins (array of 1 or more)
			$user_logins = $_GET['userlogins'];
			$user_models = array();

			foreach( (array) $user_logins as $user_login ) {
				$user = get_user_by( 'login', $user_login );
				if ( false != $user ) {
					$user_data = get_userdata( $user->ID );
					$user_models[] = o2_Fragment::get_model_from_userdata( $user_data );
				}
			}

			if ( 0 == count( $user_models ) ) {
				wp_send_json_error( array( 'errorText' => 'Invalid request' ) );
			}

			wp_send_json_success( $user_models );
		}
	}

	/**
	 * Check if o2 is active on the current site.
	 */
	function is_enabled() {
		$o2_is_enabled = false;

		$o2_options = get_option( 'o2_options' );
		if ( is_array( $o2_options ) ) {
			if ( isset( $o2_options['o2_enabled'] ) ) {
				$o2_is_enabled = $o2_options['o2_enabled'];
			}
		}

		return $o2_is_enabled;
	}

	/**
	 * This is really only needed for the first load of o2 after activation.
	 * Useful for flushing rewrite rules, and that kind of thing.
	 */
	public function first_load() {
		if ( ! get_option( 'o2-just-activated', false ) ) {
			return;
		}

		delete_option( 'o2-just-activated' );

		flush_rewrite_rules( false );
	}
}

/*
 * Breathe
 */
global $o2;
$o2 = new o2();

/**
 * Plugin activation
 */
function o2_plugin_activation_hook() {

	// We need to do some work after o2 is activated.
	// See o2::first_load()
	update_option( 'o2-just-activated', true );

	// Set the default post format to 'aside' on plugin init
	$default_post_format = apply_filters( 'o2_default_post_format', 'aside' );
	$current_post_format = get_option( 'default_post_format' );

	// Only update the post format if a default is not set
	if ( empty( $current_post_format ) ) {
		$post_formats = get_theme_support( 'post-formats' );
		if ( in_array( $default_post_format, $post_formats[0] ) ) {
			update_option( 'default_post_format', $default_post_format );
		}
	}

	// Disable the Jetpack option for using a mobile theme if Jetpack loaded
	if ( defined( 'JETPACK__VERSION' ) ) {
		update_option( 'wp_mobile_disable', true );
	}
}
register_activation_hook( O2__FILE__, 'o2_plugin_activation_hook' );
