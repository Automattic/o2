<?php

class o2_Basic_Timers {
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );
	}

	function register_scripts() {
		if ( isset( $_REQUEST['timer'] ) ) {
			wp_enqueue_script( 'o2-timer', plugins_url( 'modules/performance/js/timer.js', O2__FILE__ ), array() );
		}
	}

	function wp_footer() {
		$event_element_id = apply_filters( 'o2_timers_event_element_id', 'content' ); // allow themes to override

		if ( isset( $_REQUEST['timer'] ) ) {
?>
			<script type="text/javascript">
				// Fire the 'master' timer to keep track of overall timeline events
				o2.Timing.timer( 'master', 'end master' );

				// Hardcode because o2.options isn't available until domready
				var _o2 = document.getElementById( <?php echo json_encode( $event_element_id ); ?> );

				// Full Posts stream rendering
				_o2.addEventListener( 'pre-postsView-render.o2', function( e ) {
					o2.Timing.timer( 'postsView render', 'before postsView render', true );
				} );
				_o2.addEventListener( 'post-postsView-render.o2', function( e ) {
					o2.Timing.timer( 'postsView render', 'after postsView render' );
					o2.Timing.timer( 'master', 'postsView rendered' );
				} );

				// New Post editor is available
				_o2.addEventListener( 'frontside-post-rendered.o2', function( e ) {
					o2.Timing.timer( 'master', 'frontside post box rendered' );
				} );

				// Post+Comment save/publish
				_o2.addEventListener( 'pre-post-save.o2', function( e ) {
					o2.Timing.timer( 'post save', 'post save clicked', true );
				} );
				_o2.addEventListener( 'post-post-save.o2', function( e ) {
					o2.Timing.timer( 'post save', 'post save finished and rendered' );
				} );
				_o2.addEventListener( 'pre-comment-save.o2', function( e ) {
					o2.Timing.timer( 'comment save', 'comment save clicked', true );
				} );
				_o2.addEventListener( 'post-comment-save.o2', function( e ) {
					o2.Timing.timer( 'comment save', 'comment save finished and rendered' );
				} );

				// Infinite Scroll
				_o2.addEventListener( 'pre-infinite-scroll-response.o2', function( e ) {
					o2.Timing.timer( 'infinite scroll', 'infinite scroll response handling started', true );
				} );
				_o2.addEventListener( 'post-infinite-scroll-response.o2', function( e ) {
					o2.Timing.timer( 'infinite scroll', 'infinite scroll response full processed' );
				} );
<?php
		}

		if ( isset( $_REQUEST['timer'] ) && in_array( 'polling', (array) $_REQUEST['timer'] ) ) {
?>
				// Polling -- ?timer[]=polling to enable, because it's verbose
				_o2.addEventListener( 'poll-start.o2', function( e ) {
					o2.Timing.timer( 'master', 'polling started' );
				} );
				_o2.addEventListener( 'poll-request.o2', function( e ) {
					o2.Timing.timer( 'poll', 'poll response requested', true );
				} );
				_o2.addEventListener( 'poll-response.o2', function( e ) {
					o2.Timing.timer( 'poll', 'poll response received' );
				} );
				_o2.addEventListener( 'poll-response-processed.o2', function( e ) {
					o2.Timing.timer( 'poll', 'poll response fully processed' );
				} );
<?php
		}

		if ( isset( $_REQUEST['timer'] ) ) {
?>
			</script>
<?php
		}
	} /* wp_footer */

}
