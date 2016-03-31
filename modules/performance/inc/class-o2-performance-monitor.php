<?php

class o2_Performance_Monitor {
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_ajax_o2_perfmon', array( $this, 'wp_ajax_o2_perfmon' ) );
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );
	}

	function register_scripts() {
		if ( ! apply_filters( 'o2_perfmon_enable', false ) ) { /* disabled by default */
			return;
		}

		wp_enqueue_script( 'o2-timer', plugins_url( 'modules/performance/js/timer.js', O2__FILE__ ), array() );
		wp_enqueue_script( 'o2-perfmon', plugins_url( 'modules/performance/js/perfmon.js', O2__FILE__ ), array( 'o2-timer', 'jquery' ) );
	}

	function wp_ajax_o2_perfmon() {
		$nonce = isset( $_GET['nonce'] ) ? $_GET['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'o2-perfmon' ) ) {
			wp_send_json_error();
		}

		if ( ! isset( $_GET['measurements'] ) ) {
			wp_send_json_error();
		}

		// It is up to the end user to add_action for o2_perfmon_save_measurement
		// by default this class records no data
		$measurements = (array) $_GET['measurements'];
		foreach( (array) $measurements as $measurement ) {
			// measurement must have kind, key and value
			if ( isset( $measurement['kind'] ) && isset( $measurement['key'] ) && isset( $measurement['value'] ) ) {
				do_action( 'o2_perfmon_save_measurement', $measurement );
			}
		}

		wp_send_json_success();
	}

	function wp_footer() {
		if ( ! apply_filters( 'o2_perfmon_enable', false ) ) { /* disabled by default */
			return;
		}

		$perfmon_options = array(
			'eventElementID' => apply_filters( 'o2_timers_event_element_id', 'content' ),
			'ajaxURL' => admin_url( 'admin-ajax.php?action=o2_perfmon' ),
			'nonce' => wp_create_nonce( 'o2-perfmon' )
		);
?>
		<script type="text/javascript">
			o2.PerformanceMonitor.start( <?php echo json_encode( $perfmon_options ); ?> );
		</script>
<?php
	}
}
