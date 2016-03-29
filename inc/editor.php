<?php

class o2_Editor {
	function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );
	}

	public function register_scripts() {
		wp_enqueue_script( 'o2-jquery-hotkeys', plugins_url( 'js/utils/jquery.hotkeys.js', O2__FILE__ ), array( 'jquery' ) );
		// @todo Copy autoresize into position as part of the .org build process
		if ( !defined( 'IS_WPCOM' ) || !IS_WPCOM ) {
			wp_enqueue_script( 'jquery.autoresize', plugins_url( 'js/editor/jquery.autoresize.js', O2__FILE__ ), array( 'jquery' ) );
		}
		wp_enqueue_script( 'o2-editor', plugins_url( 'js/editor/editor.js', O2__FILE__ ), array( 'jquery', 'o2-jquery-hotkeys', 'jquery.autoresize', 'o2-plugin-caret', 'o2-events' ) );
	}

	function wp_footer() {
		if ( is_home() && current_user_can( 'publish_posts' ) ) {
			?><div id="o2-expand-editor"><span class="genericon genericon-edit"></span></div><?php
		}
	}
}
