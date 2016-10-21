<?php

class o2_Live_Comments_Widget extends WP_Widget {
	function __construct() {
		parent::__construct(
	 		'o2-live-comments-widget', // Base ID
			'o2 Live Posts and Comments', // Name
			array( 'description' => __( 'Displays a live stream of posts and comments on your o2 powered blog', 'o2' ) )
		);

		// If the widget is not active, no need to do any enqueues
		if ( is_active_widget( false, false, 'o2-live-comments-widget', true ) ) {
			add_action( 'o2_templates', array( $this, 'live_item_templates' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_widget_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_widget_styles' ) );
		}
	}

	function live_item_templates() {

		$untitled_post_title = sprintf( __( 'Post by %1$s', 'o2' ), '{{ data.author.displayName }}' );
		$comment_title = sprintf( __( 'Comment on %1$s', 'o2' ), '{{ data.title }}' );
		$untitled_comment_title = sprintf( __( 'Comment by %1$s', 'o2' ), '{{ data.author.displayName }}' );

		?>
			<script type="html/template" id='tmpl-o2-live-untitled-post-title-template'>
				<?php echo esc_html( $untitled_post_title ); ?>
			</script>
			<script type="html/template" id='tmpl-o2-live-comment-title-template'>
				<?php echo esc_html( $comment_title ); ?>
			</script>
			<script type="html/template" id='tmpl-o2-live-untitled-comment-title-template'>
				<?php echo esc_html( $untitled_comment_title ); ?>
			</script>
			<script type="html/template" id='tmpl-o2-live-item-template'>
				<# if ( o2.options.showAvatars && data.author.avatar ) { #>
				<img src="{{ data.author.avatar }}" width="{{ data.author.avatarSize }}" height="{{ data.author.avatarSize }}" class="avatar o2-live-item-img {{ data.author.modelClass }}" />
				<# } #>
				<p class="o2-live-item-text"><a href="{{ data.permalink }}" data-domref="{{ data.domRef }}"
					<# if ( 'comment' === data.type ) { #>
						data-postid="{{ data.postID }}"
					<# } #>
					>{{{ data.title }}}</a>
					<br/>
					<span class="entry-date o2-timestamp" data-unixtime="{{ data.unixtime }}" data-domref="{{ data.domRef }}"
						<# if ( 'comment' === data.type ) { #>
							data-postid="{{ data.postID }}"
						<# } #>
					>
					</span>
				</p>
				<div class="o2-live-item-clear">
				</div>
			</script>
		<?php
	}

	function register_widget_scripts() {
		wp_enqueue_script( 'o2-live-comments-models-item',  plugins_url( 'modules/live-comments/js/models/item.js', O2__FILE__ ), array( 'backbone', 'jquery' ) );
		wp_enqueue_script( 'o2-live-comments-collections-items',  plugins_url( 'modules/live-comments/js/collections/items.js', O2__FILE__ ), array( 'o2-live-comments-models-item', 'o2-compare-times' ) );
		wp_enqueue_script( 'o2-live-comments-views-item',  plugins_url( 'modules/live-comments/js/views/item.js', O2__FILE__ ), array( 'o2-live-comments-models-item', 'o2-template', 'o2-timestamp', 'wp-backbone' ) );
		wp_enqueue_script( 'o2-live-comments-views-items', plugins_url( 'modules/live-comments/js/views/items.js', O2__FILE__ ), array( 'o2-live-comments-collections-items', 'o2-live-comments-views-item', 'wp-backbone' ) );

		// Widget "App"
		wp_enqueue_script( 'o2-live-comments', plugins_url( 'modules/live-comments/js/live-comments-widget.js', O2__FILE__ ), array( 'o2-live-comments-views-items', 'o2-events' ) );
	}

	function register_widget_styles() {
		wp_register_style( 'o2-live-comments-styles', plugins_url( 'modules/live-comments/css/style.css', O2__FILE__ ) );
		wp_style_add_data( 'o2-live-comments-styles', 'rtl', 'replace' );
		wp_enqueue_style( 'o2-live-comments-styles' );
	}

	function widget( $args, $instance ) {
		extract( $args, EXTR_SKIP );
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title );
		$kind = isset( $instance['kind'] ) ? $instance['kind'] : 'both';
		$number = isset( $instance['number'] ) ? $instance['number'] : 10;

		echo $before_widget;

		if ( ! empty( $title ) ) {
			echo $before_title . $title . $after_title;
		}

		$kind = esc_attr( $kind );
		$number = esc_attr( $number );

		echo "<div class='o2-live-comments-container' data-o2-live-comments-kind='$kind' data-o2-live-comments-count='$number'>";
		echo "</div>";

		echo $after_widget;
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['kind'] = strip_tags( $new_instance['kind'] );
		$instance['number'] = strip_tags( $new_instance['number'] );
		return $instance;
	}

	function form($instance) {
		$defaults = array( 'title' => '', 'kind' => 'both', 'number' => 10 );
		$instance = wp_parse_args( (array) $instance, $defaults );

		$title_ID = esc_attr( $this->get_field_id( 'title' ) );
		$title_name = esc_attr( $this->get_field_name( 'title' ) );
		$title_value = esc_attr( $instance['title'] );

		$kind_ID = esc_attr( $this->get_field_id( 'kind' ) );
		$kind_name = esc_attr( $this->get_field_name( 'kind' ) );
		$kind_value = esc_attr( $instance['kind'] );

		$number_ID = esc_attr( $this->get_field_id( 'number' ) );
		$number_name = esc_attr( $this->get_field_name( 'number' ) );
		$number_value = esc_attr( $instance['number'] );

		echo "<p>";
		echo "<label for='$title_ID'>" . esc_html__( 'Title:', 'o2' ) . "</label>";
		echo "<input class='widefat' type='text' id='$title_ID' name='$title_name' value='$title_value' />";
		echo "</p>";

		echo "<p>";
		echo "<label for='$kind_ID'>" . esc_html__( 'Show:', 'o2' ) . "</label>";
		echo "<br/>";

		$kinds = array(
			'comment' => __( 'Comments', 'o2' ),
			'post' => __( 'Posts', 'o2' ),
			'both' => __( 'Comments and Posts', 'o2' ),
			);

		foreach ( ( array ) $kinds as $name => $label ) {
			$checked = checked( $name, $kind_value, false );
			echo "<input type='radio' id='$kind_ID' name='$kind_name' value='$name' $checked/>";
			echo esc_html( $label );
			echo "</label>";
			echo "<br/>";
		}
		echo "</p>";

		echo "<p>";
		echo "<label for='$number_ID'>" . esc_html__( 'Number to show:', 'o2' ) ."</label>";
		echo "<input type='text' id='$number_ID' name='$number_name' value='$number_value' size='3' />";
		echo "</p>";
	}
}

function o2_live_comments_widget_widgets_init() {
	register_widget( 'o2_Live_Comments_Widget' );
}
add_action( 'widgets_init', 'o2_live_comments_widget_widgets_init' );

function o2_live_comments_widget_footer_bootstrap() {
	// embed in the footer the most recent N comments and M posts based
	// on the widget settings

	$comment_count = 0;
	$post_count = 0;

	$settings_array = get_option( 'widget_o2-live-comments-widget' );

	if ( is_array( $settings_array ) ) {
		foreach( (array) $settings_array as $settings ) {
			if ( is_array ( $settings ) ) {
				if ( isset( $settings['kind'] ) && isset( $settings['number'] ) ) {
					$kind = $settings['kind'];
					$count = intval( $settings['number'] );
					if ( 0 > $count ) {
						$count = 0;
					}
					if ( "both" == $kind ) {
						$comment_count = max( $comment_count, $count );
						$post_count = max( $post_count, $count );
					}
					else if ( "comment" == $kind ) {
						$comment_count = max( $comment_count, $count );
					}
					else if ( "post" == $kind ) {
						$post_count = max( $post_count, $count );
					}
				}
			}
		}
	}

	$live_bootstrap = array();

	// Bootstrap comments, as needed based on widget settings
	if ( 0 < $comment_count ) {
		$comments = get_comments( array(
			'status' => 'approve',
			'number' => $comment_count
			)
		);

		// instead of using get_fragment, which is terribly verbose, we use lighterweight emitters here

		foreach( (array) $comments as $comment ) {
			$comment_ID = $comment->comment_ID;
			$comment_post_ID = $comment->comment_post_ID;

			$title = html_entity_decode( get_the_title( $comment->comment_post_ID ) );

			$comment_bootstrap = array(
				'unixtime'                     => strtotime( $comment->comment_date_gmt ),
				'title'                        => $title,
				'domRef'                       => "#comment-" . $comment_ID,
				'permalink'                    => get_permalink( $comment_post_ID ) . '#comment-' . $comment_ID,
				'type'                         => 'comment',
				'externalID'                   => $comment_ID,
				'postID'                       => $comment->comment_post_ID
			);

			$commentor = o2_Fragment::get_comment_author_properties( $comment );
			$live_bootstrap[] = array_merge( $comment_bootstrap, $commentor );
		}
	}

	// Bootstrap posts, as needed based on widget settings
	if ( 0 < $post_count ) {
		$posts = get_posts( array(
			'post_status' => 'publish',
			'number'      => $post_count
			)
		);

		foreach( (array) $posts as $post ) {
			$post_ID = $post->ID;
			$title = html_entity_decode( $post->post_title );

			$post_bootstrap = array(
				'unixtime'                     => strtotime( $post->post_date_gmt ),
				'title'                        => $title,
				'domRef'                       => "#post-" . $post_ID,
				'permalink'                    => get_permalink( $post_ID ),
				'type'                         => 'post',
				'externalID'                   => $post_ID
			);

			$poster = o2_Fragment::get_post_user_properties( $post );
			$live_bootstrap[] = array_merge( $post_bootstrap, $poster );
		}
	}

	echo "<script class='o2-live-widget-bootstrap-data' type='application/json' style='display:none'>";
	echo json_encode( $live_bootstrap );
	echo "</script>\n";

	// split this and the widgets init out into a separate class.  maybe rename the containing directory to simply live-comments
}
add_action( 'wp_footer', 'o2_live_comments_widget_footer_bootstrap' );
