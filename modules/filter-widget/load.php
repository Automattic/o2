<?php

class o2_Filter_Widget extends WP_Widget {
	var $filters = array();

	function __construct() {
		parent::__construct(
	 		'o2-filter-widget', // Base ID
			'o2 Filter Widget', // Name
			array( 'description' => __( 'Quick access to popular views of your o2', 'o2' ) )
		);

		// If the widget is not active, no need to do any enqueues
		if ( is_active_widget( false, false, 'o2-filter-widget', true ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'register_widget_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_widget_styles' ) );
		}

		// Initialize the filter array with some built-in queries.  Plugins can filter
		// this array to add their own
		$this->filters = array(
			'filter-none.o2' => array(
				'label' => __( 'Recent Updates', 'o2' ), // aka no filter
				'is_active' => false,
				'url' => esc_url( home_url() ),
				'priority' => 10,
				'css_id' => 'o2-filter-recent-updates'
			),
			'filter-recent-comments.o2' => array(
				'label' => __( 'Recent Comments', 'o2' ),
				'is_active' => array( $this, 'is_recent_comments_filter_active' ),
				'url' => esc_url( add_query_arg( 'o2_recent_comments', true, home_url() ) ),
				'priority' => 11,
				'css_id' => 'o2-filter-recent-comments',
			),
			'filter-noreplies.o2' => array(
				'label' => __( 'No Replies', 'o2' ),
				'is_active' => array( $this, 'is_no_replies_filter_active' ),
				'url' => esc_url( add_query_arg( 'replies', 'none', home_url() ) ),
				'priority' => 30,
				'css_id' => 'o2-filter-no-replies'
			)
		);

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$this->filters['filter-mentionsMe.o2'] = array(
				'label' => __( 'My @Mentions', 'o2' ),
				'is_active' => array( $this, 'is_mentions_me_filter_active' ),
				'url' => esc_url( add_query_arg( 'mentions', $user->user_login, home_url() ) ),
				'priority' => 15,
				'css_id' => 'o2-filter-my-mentions'
			);
			$this->filters['filter-myPosts.o2'] = array(
				'label' => __( 'My Posts', 'o2' ),
				'is_active' => array( $this, 'is_my_posts_filter_active' ),
				'url' => esc_url( home_url( '/author/' . $user->user_login ) ),
				'priority' => 35,
				'css_id' => 'o2-filter-my-posts'
			);
		}

		$this->filters = apply_filters( 'o2_filter_widget_filters', $this->filters );

		// sort the filters by priority
		usort( $this->filters, array( $this, 'filter_sort' ) );
	}

	function filter_sort( $filter_1, $filter_2 ) {
		if ( $filter_1['priority'] == $filter_2['priority'] ) {
			return 0;
		}
		return ( $filter_1['priority'] < $filter_2['priority'] ) ? -1 : 1;
	}

	function is_recent_comments_filter_active() {
		return ( is_home() && isset( $_GET['o2_recent_comments'] ) && $_GET['o2_recent_comments'] );
	}

	function is_no_replies_filter_active() {
		return ( is_home() && isset( $_GET['replies'] ) && $_GET['replies'] == 'none');
	}

	function is_mentions_me_filter_active() {
		$user = wp_get_current_user();
		$mentions = get_query_var( 'mentions' );
		return ( is_archive() && ( $user->user_login == $mentions ) );
	}

	function is_my_posts_filter_active() {
		$user = wp_get_current_user();
		return ( is_author( $user->ID ) );
	}

	function register_widget_scripts() {
		// Widget Script
		wp_enqueue_script( 'o2-filter-widget', plugins_url( 'modules/filter-widget/js/filter-widget.js', O2__FILE__ ), array( 'jquery' ) );
	}

	function register_widget_styles() {
		wp_register_style( 'o2-filter-widget-styles', plugins_url( 'modules/filter-widget/css/style.css', O2__FILE__ ) );
		wp_style_add_data( 'o2-filter-widget-styles', 'rtl', 'replace' );
		wp_enqueue_style( 'o2-filter-widget-styles' );
	}

	function widget( $args, $instance ) {
		extract( $args, EXTR_SKIP );
		$title = ( isset( $instance['title'] ) ) ? $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title );

		if ( 0 < count( $this->filters ) ) {
			echo $before_widget;

			if ( ! empty( $title ) ) {
				echo $before_title . $title . $after_title;
			}

			// Check if none of them are selected
			$at_least_one_filter_is_active = false;
			foreach ( (array) $this->filters as $key => $filter ) {
				$item_class = '';
				if ( is_callable( $filter['is_active'] ) ) {
					if ( call_user_func( $filter['is_active'] ) ) {
						$at_least_one_filter_is_active = true;
					}
				}
			}

			// Render a full size list-based widget for larger screens
			echo "<ul class='o2-filter-widget-list'>";
			foreach ( (array) $this->filters as $key => $filter ) {
				$item_class = '';
				if ( is_callable( $filter['is_active'] ) ) {
					if ( call_user_func( $filter['is_active'] ) ) {
						$item_class = 'o2-filter-widget-selected';
					}
				} else {
					// the default filter (filter-none.o2) has no is_active
					// callback, and should be active if none of the real filters are
					if ( ! $at_least_one_filter_is_active ) {
						$item_class = 'o2-filter-widget-selected';
					}
				}
				echo "<li id='" . esc_attr( $filter['css_id'] ) . "' class='o2-filter-widget-item'><a href='" . esc_url( $filter['url'] ) . "' data-key='" . esc_attr( $key ) . "' data-url='" . esc_url( $filter['url'] ) . "' class='" . esc_attr( $item_class ) . "'>" . esc_html( $filter['label'] ) . "</a></li>";
			}
			echo "</ul>";

			// A compact select-box-based widget for smaller screens
			echo "<select class='o2-filter-widget-select'>";
			foreach ( (array) $this->filters as $key => $filter ) {
				$selected = ( is_callable( $filter['is_active'] ) ) ? selected( call_user_func( $filter['is_active'] ), true, false ) : '';
				echo "<option value='" . esc_attr( $key ) . "' data-key='" . esc_attr( $key ) . "' data-url='" . esc_url( $filter['url'] ) . "'" . $selected . ">" . esc_html( $filter['label'] ) . "</option>";
			}
			echo "</select>";

			echo $after_widget;
		}
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		return $instance;
	}

	function form($instance) {
		$defaults = array( 'title' => '' );
		$instance = wp_parse_args( (array) $instance, $defaults );

		$title_ID = esc_attr( $this->get_field_id( 'title' ) );
		$title_name = esc_attr( $this->get_field_name( 'title' ) );
		$title_value = esc_attr( $instance['title'] );

		echo "<p>";
		echo "<label for='$title_ID'>" . esc_html__( 'Title:', 'o2' ) . "</label>";
		echo "<input class='widefat' type='text' id='$title_ID' name='$title_name' value='$title_value' />";
		echo "</p>";
	}
}

function o2_filter_widget_init() {
	register_widget( 'o2_Filter_Widget' );
}
add_action( 'widgets_init', 'o2_filter_widget_init' );
