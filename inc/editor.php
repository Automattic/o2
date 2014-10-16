<?php

class o2_Editor {
	function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );
	}

	public function register_scripts() {
		wp_enqueue_script( 'o2-jquery-hotkeys', plugins_url( 'o2/js/utils/jquery.hotkeys.js' ), array( 'jquery' ) );
		// @todo Copy autoresize into position as part of the .org build process
		if ( !defined( 'IS_WPCOM' ) || !IS_WPCOM ) {
			wp_enqueue_script( 'jquery.autoresize', plugins_url( 'o2/js/editor/jquery.autoresize.js' ), array( 'jquery' ) );
		}
		wp_enqueue_script( 'o2-editor', plugins_url( 'o2/js/editor/editor.js' ), array( 'jquery', 'o2-jquery-hotkeys', 'jquery.autoresize', 'o2-plugin-caret', 'o2-events' ) );
	}

	function wp_footer() {
		if ( is_home() && current_user_can( 'publish_posts' ) ) {
			?><div id="o2-expand-editor"><span class="genericon genericon-edit"></span></div><?php
		}
	}
}
