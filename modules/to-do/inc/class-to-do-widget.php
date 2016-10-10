<?php

class o2_ToDos_Widget extends WP_Widget {

	public static $loaded = array();

	private $widget_args = array(
		'title'          => '',
		'posts_per_page' => 5,
		'order'          => 'ASC',
		'filter_tags'    => '',
	);

	function __construct() {
		$widget_ops = array(
			'classname'   => 'o2-extend-resolved-posts-unresolved-posts-widget',
			'description' => __( 'Display an (optionally filtered) list of posts marked to do', 'o2' )
		);
		parent::__construct( 'o2_extend_resolved_posts_unresolved_posts', __( 'o2 To Do', 'o2' ), $widget_ops );

		if ( is_active_widget( false, false, $this->id_base, true ) ) {
			add_action( 'o2_wp_footer', array( $this, 'wp_footer' ) );
			add_action( 'o2_templates', array( $this, 'get_templates' ) );
			add_action( 'o2_read_api_o2-extend-resolved-posts-fetch', array( $this, 'fetch' ) );

			// Scripts and styles
			if ( ! is_admin() ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			}
		}
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'o2-extend-to-do-models-to-do', plugins_url( 'modules/to-do/js/models/to-do.js', O2__FILE__ ), array( 'o2-models-base' ) );
		wp_enqueue_script( 'o2-extend-to-do-collections-to-dos', plugins_url( 'modules/to-do/js/collections/to-dos.js', O2__FILE__ ), array( 'o2-extend-to-do-models-to-do' ) );
		wp_enqueue_script( 'o2-extend-to-do-views-to-do', plugins_url( 'modules/to-do/js/views/to-do.js', O2__FILE__ ), array( 'o2-extend-to-do-models-to-do', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-extend-to-do-views-pagination', plugins_url( 'modules/to-do/js/views/pagination.js', O2__FILE__ ), array( 'jquery', 'backbone', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-extend-to-do-views-to-dos', plugins_url( 'modules/to-do/js/views/to-dos.js', O2__FILE__ ), array( 'o2-extend-to-do-collections-to-dos', 'o2-extend-to-do-views-to-do', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-extend-to-do-models-widget', plugins_url( 'modules/to-do/js/models/widget.js', O2__FILE__ ), array( 'o2-extend-to-do-collections-to-dos' ) );
		wp_enqueue_script( 'o2-extend-to-do-views-widget', plugins_url( 'modules/to-do/js/views/widget.js', O2__FILE__ ), array( 'o2-extend-to-do-views-to-dos', 'o2-extend-to-do-views-pagination', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-extend-to-do-app', plugins_url( 'modules/to-do/js/app/main.js', O2__FILE__ ), array( 'o2-extend-to-do-views-widget', 'o2-app' ) );
	}

	/**
	 * Form for the widget settings
	 */
	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->widget_args );
		if ( ! isset( $instance['state'] ) )
			$state = o2_ToDos::get_next_state_slug( o2_ToDos::get_first_state_slug() );
		else
			$state = $instance['state'];
		$title = $instance['title'];
		$posts_per_page = $instance['posts_per_page'];
		$order = $instance['order'];
		$filter_tags = $instance['filter_tags'];

		$states = o2_ToDos::get_state_slugs();
		?>

		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'o2' ); ?>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</label></p>

		<p><label for="<?php echo esc_attr( $this->get_field_id( 'state' ) ); ?>"><?php esc_html_e( 'State', 'o2' ); ?>
		<select id="<?php echo esc_attr( $this->get_field_id( 'state' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'state' ) ); ?>">
		<?php foreach ( (array) $states as $state_slug ) {
			$select_state = o2_ToDos::get_state( $state_slug );
			echo '<option value="' . esc_attr( $state_slug ) . '"' . selected( $state, $state_slug, false ) . '>' . esc_html( $select_state['name'] ) . '</option>';
		} ?>
		</select>
		</label></p>

		<p><label for="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>"><?php esc_html_e( 'Order:', 'o2' ); ?>
		<select id="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>">
		<option value="ASC" <?php selected( $order, 'ASC' ); ?>><?php esc_html_e( 'Ascending (oldest first)', 'o2' ); ?></option>
		<option value="DESC" <?php selected( $order, 'DESC' ); ?>><?php esc_html_e( 'Descending (newest first)', 'o2' ); ?></option>
		</select>
		</label></p>

		<p><label for="<?php echo esc_attr( $this->get_field_id( 'posts_per_page' ) ); ?>"><?php esc_html_e( 'Posts Per Page', 'o2' ); ?>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'posts_per_page' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'posts_per_page' ) ); ?>" type="number" value="<?php echo esc_attr( $posts_per_page ); ?>" maxlength="2" />
		</label></p>

		<p><label for="<?php echo esc_attr( $this->get_field_id( 'filter_tags' ) ); ?>"><?php esc_html_e( 'Filter to these tags', 'o2' ); ?>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'filter_tags' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'filter_tags' ) ); ?>" type="text" value="<?php echo esc_attr( $filter_tags ); ?>" />
		</label><br />
		<span class="description"><?php _e( "Separate multiple tags with commas, and prefix with '-' to exclude.", 'o2' ); ?></p>

		<?php
	}

	/**
	 * Validate any new widget form data
	 */
	function update( $new_instance, $old_instance ) {
		$instance = array();
		$new_instance = wp_parse_args( (array) $new_instance, $this->widget_args );

		// Sanitize the values
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['state'] = sanitize_key( $new_instance['state'] );
		$instance['posts_per_page'] = absint( $new_instance['posts_per_page'] );
		if ( $instance['posts_per_page'] > 99 )
			$instance['posts_per_page'] = 1;
		$instance['order'] = ( 'DESC' == $new_instance['order'] ) ? 'DESC' : 'ASC';

		$multi_tags = (array)explode( ',', $new_instance['filter_tags'] );
		$multi_tags = array_map( 'sanitize_key', $multi_tags );
		// We only want to save tags that actually exist
		foreach( $multi_tags as $key => $multi_tag ) {
			if ( empty( $multi_tag ) ) {
				unset( $multi_tags[ $key ] );
				continue;
			}
			if ( 0 === strpos( $multi_tag, '-' ) )
				$invert = '-';
			else
				$invert = '';
			if ( is_numeric( $multi_tag ) ) {
				if ( false === ( $tag = get_term_by( 'id', $multi_tag, 'post_tag' ) ) )
					unset( $multi_tags[$key] );
				if ( is_object( $tag ) )
					$multi_tags[$key] = $invert . $tag->term_id;
			} else {
				if ( false === ( $tag = get_term_by( 'slug', $multi_tag, 'post_tag' ) ) )
					unset( $multi_tags[$key] );
				if ( is_object( $tag ) )
					$multi_tags[$key] = $invert . $tag->slug;
			}
		}
		$instance['filter_tags'] = implode( ',', $multi_tags );

		return $instance;
	}

	/**
	 * Display the widget
	 */
	function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );
		$instance = wp_parse_args( (array) $instance, $this->widget_args );
		$state = $instance['state'];
		$posts_per_page = intval( $instance['posts_per_page'] );
		$order = ( 'DESC' == $instance['order'] ) ? 'DESC' : 'ASC';
		$filter_tags = $instance['filter_tags'];

		echo $args['before_widget'];
		if ( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];
		echo "<div data-state='" . esc_attr( $state ) . "' data-posts-per-page='" . esc_attr( $posts_per_page ) . "' data-order='" . esc_attr( $order ) . "' data-filter-tags='" . esc_attr( $filter_tags ) . "' style='display:none;'></div>";
		echo $args['after_widget'];

		// Track which posts we will need to bootstrap
		$loaded = self::$loaded;
		$key = $state . ':' . $order . ':' . $filter_tags . ':' . $this->id;
		if ( array_key_exists( $key, $loaded ) ) {
			if ( $posts_per_page > $loaded[ $key ] )
				$loaded[ $key ] = $posts_per_page;
		} else {
			$loaded[ $key ] = $posts_per_page;
		}
		self::$loaded = $loaded;
	}

	/**
	 * Add the templates to the o2 templates filter
	 *
	 * If you have more than a few templates, use the o2_Fragment class as a pattern for loading external template files.
	 */
	function get_templates() {
		$post_stamp = sprintf( __( '%1$s, %2$s comments', 'o2' ), '<span class="resolved-post-date o2-timestamp" data-unixtime="{{ data.timestamp }}"></span>', '{{ data.commentCount }}' );
		$showing_of = sprintf( __( 'Showing %1$s of %2$s %3$s posts', 'o2' ), '{{{ data.rangeInView }}}', '{{ data.totalView }}', '<a href="' . site_url( '/?resolved={{ data.state }}<# if ( data.filterTags ) { #>&tags={{ data.filterTags }}<# } #>' ) . '">{{ data.state }}</a>' );
		?>

		<script type="html/template" id="tmpl-o2-extend-resolved-posts-resolved-post">

			<# if ( o2.options.showAvatars && data.avatar ) { #>
			{{{ data.avatar }}}
			<# } #>
			<div class="inner">
				<a href="{{ data.permalink }}" title="{{ data.excerpt }}">{{{ data.title }}}</a><br />
				<span class="inner-sub"><?php echo $post_stamp; ?></span>
			</div>

		</script>

		<script type="html/template" id="tmpl-o2-extend-resolved-posts-pagination">

			<# if ( data.currentPage != 1 ) { #>
				<a href="#" class="prev">{{{ data.prevText }}}</a>
			<# } else { #>
				<span>{{{ data.prevText }}}</span>
			<# } #>

			<# if ( _.size( data.pages ) > 0 ) { #>
				<# _.each( data.pages, function( p ) { #>
					<# if ( data.currentPage == p ) { #>
						<span class="page selected">{{{ p }}}</span>
					<# } else { #>
						<a href="#" class="page">{{{ p }}}</a>
					<# } #>
				<# } ); #>
			<# } else { #>
				<span class="showing"><?php echo $showing_of; ?></span>
			<# } #>

			<# if ( data.currentPage != data.totalPages ) { #>
				<a href="#" class="next">{{{ data.nextText }}}</a>
			<# } else { #>
				<span>{{{ data.nextText }}}</span>
			<# } #>

		</script>

		<?php
	}

	/**
	 * Add the plugin data and startup function to the wp_footer() after o2 has started
	 */
	function wp_footer() {
		$data = array();
		$found = array();

		// Add bootstrap data needed for this widget
		if ( apply_filters( 'o2_read_api_ok_to_serve_data', true ) ) {

			// Pre-load only enough content for the front page
			$loaded = self::$loaded;

			// Exclude x-posted content
			$term = get_term_by( 'slug', 'p2-xpost', 'post_tag' );
			foreach ( $loaded as $key => $posts_per_page ) {
				list( $state, $order, $filter_tags, $widget_id ) = explode( ':', $key );
				$args = array(
					'posts_per_page' => $posts_per_page,
					'offset'         => 0,
					'order'          => $order,
					'tax_query'      => array(
						array(
							'taxonomy' => o2_ToDos::taxonomy,
							'field'    => 'slug',
							'terms'    => $state,
						),
					),
				);
				if ( $term )
					$args[ 'tag__not_in' ] = array( $term->term_id );

				$filter_tags = (array)explode( ',', $filter_tags );
				foreach( (array)$filter_tags as $filter_tag ) {
					if ( ! $filter_tag )
						continue;
					$new_tax_query = array(
							'taxonomy' => 'post_tag',
						);
					if ( 0 === strpos( $filter_tag, '-' ) )
						$new_tax_query['operator'] = 'NOT IN';
					$filter_tag = trim( $filter_tag, '-' );
					if ( is_numeric( $filter_tag ) )
						$new_tax_query['field'] = 'ID';
					else
						$new_tax_query['field'] = 'slug';
					$new_tax_query['terms'] = $filter_tag;
					$args['tax_query'][] = $new_tax_query;
				}

				// Use WP_Query instead of get_posts() so we can use $found_posts
				$query = new WP_Query( $args );
				foreach( $query->posts as $post ) {
					$data[ $post->ID . ':' . $widget_id ] = self::get_fragment_from_post( $post, $state, $widget_id );
				}
				$found[] = array(
					'widgetID' => $widget_id,
					'state' => $state,
					'filterTags' => $filter_tags,
					'found' => $query->found_posts,
				);
				wp_reset_postdata();
			}
		}
		?>

		<script class='o2-resolved-posts-data' type='application/json' style='display:none;'>
		<?php echo json_encode( $data ); ?>

		</script>

		<script class='o2-resolved-posts-found' type='application/json' style='display:none;'>
		<?php echo json_encode( $found ); ?>

		</script>

		<script type='text/javascript'>
		jQuery( document ).ready( function( $ ) {
			var bootstrap = { data: [], found: [] };
			var resolvedPostsData = $( '.o2-resolved-posts-data' );
			if ( resolvedPostsData.length > 0 ) {
				_.each( $.parseJSON( resolvedPostsData.text() ), function( fragment ) {
					bootstrap.data.push( fragment );
				} );
				resolvedPostsData.remove();
			}
			var foundPostsData = $( '.o2-resolved-posts-found' );
			if ( foundPostsData.length > 0 ) {
				_.each( $.parseJSON( foundPostsData.text() ), function( fragment ) {
					bootstrap.found.push( fragment );
				} );
				foundPostsData.remove();
			}
			o2.startToDos( bootstrap );
		} );
		</script>

		<?php
	}

	/**
	 * Get resolved post fragments and return them via AJAX
	 */
	public static function fetch() {
		$state = sanitize_key( $_REQUEST['state'] );
		$order = sanitize_key( $_REQUEST['order'] );
		$filter_tags = sanitize_text_field( $_REQUEST['filterTags'] );
		$widget_id = sanitize_key( $_REQUEST['widgetID'] );

		$posts = array();

		$args = array(
			'posts_per_page' => -1,
			'order'          => $order,
			'tax_query'      => array(
				array(
					'taxonomy' => o2_ToDos::taxonomy,
					'field'    => 'slug',
					'terms'    => $state,
				),
			),
		);

		// Exclude x-posted content.
		$term = get_term_by( 'slug', 'p2-xpost', 'post_tag' );
		if ( $term ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'post_tag',
				'terms'    => $term->term_id,
				'operator' => 'NOT IN'
			);
		}

		$filter_tags = explode( ',', $filter_tags );
		$filter_tags = array_map( 'sanitize_key', $filter_tags );
		$filter_tags = array_filter( $filter_tags );
		foreach( $filter_tags as $filter_tag ) {
			$new_tax_query = array(
				'taxonomy' => 'post_tag',
			);

			if ( 0 === strpos( $filter_tag, '-' ) ) {
				$new_tax_query['operator'] = 'NOT IN';
			}

			$filter_tag = trim( $filter_tag, '-' );
			if ( is_numeric( $filter_tag ) ) {
				$new_tax_query['field'] = 'ID';
			} else {
				$new_tax_query['field'] = 'slug';
			}
			$new_tax_query['terms'] = $filter_tag;

			$args['tax_query'][] = $new_tax_query;
		}

		$query = new WP_Query( $args );
		foreach ( $query->posts as $post ) {
			$posts[ $post->ID . ':' . $widget_id ] = self::get_fragment_from_post( $post, $state, $widget_id );
		}
		wp_reset_postdata();
		$payload = array(
			'data' => array(
				'posts' => $posts,
			),
			'callback' => 'o2-extend-resolved-posts-fetch',
		);
		o2_Read_API::die_success( $payload );
	}

	/**
	 * Get a resolved post fragment
	 *
	 * @param object Post object
	 * @param string Current post state slug
	 * @param string The ID of the widget that the current post belongs to
	 * @return array A resolved post fragment
	 */
	public static function get_fragment_from_post( $post, $state, $widget_id ) {
		$comment_count = wp_count_comments( $post->ID );
		$post_title = empty( $post->post_title ) ? __( 'Untitled', 'o2' ) : $post->post_title;
		$fragment = array(
			'type'         => 'post',
			'id'           => $post->ID . ':' . $widget_id,
			'postID'       => $post->ID,
			'avatar'       => get_avatar( $post->post_author, 32 ),
			'title'        => apply_filters( 'the_title', $post_title, $post->ID ),
			'excerpt'      => esc_attr( apply_filters( 'get_the_excerpt', strip_tags( $post->post_content ) ) ),
			'commentCount' => $comment_count->approved,
			'state'        => $state,
			'timestamp'    => strtotime( $post->post_date_gmt ),
			'permalink'    => get_permalink( $post ),
			'widgetID'     => $widget_id,
		);
		return $fragment;
	}
}
