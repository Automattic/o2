<?php

class o2_Suggestions {
	function __construct() {
		add_filter( 'jetpack_mentions_should_load_ui', array( 'o2_Suggestions', 'should_load_ui' ), 15 );
		add_action( 'jetpack_mentions_loaded_ui', array( 'o2_Suggestions', 'enqueue_scripts' ) );
	}

	static function should_load_ui( $load ) {
		// Load it all over the place on o2. is_admin() already covered by
		// core @mentions.
		if ( !is_admin() ) {
			return true;
		}

		return $load;
	}

	static function enqueue_scripts() {
		wp_enqueue_script( 'o2-suggestions', plugins_url( 'modules/suggestions/js/suggestions.js', O2__FILE__ ), array( 'jquery' ) );
	}
}

new o2_Suggestions();
