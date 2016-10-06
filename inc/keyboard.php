<?php
class o2_Keyboard {
	function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_footer', array( $this, 'help_text' ) );
		add_filter( 'o2_app_controls', array( $this, 'help_link' ) );
	}

	public function register_scripts() {
		wp_register_script( 'o2-hotkeys', plugins_url( 'js/utils/jquery.hotkeys.js', O2__FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'o2-keyboard', plugins_url( 'js/utils/keyboard.js', O2__FILE__ ), array( 'jquery' ) );
	}

	public function help_link( $controls ) {
		array_unshift( $controls, '<a href="#" class="o2-toggle-keyboard-help">' . esc_html( __( 'Keyboard Shortcuts', 'o2' ) ) . '</a>' );
		return $controls;
	}

	public function help_text() { ?>
		<div id="help">
			<dl class="directions">
				<dt><?php _e( 's', 'o2' ); ?></dt><dd><?php _e( 'search', 'o2' ); ?></dd>
				<dt>c</dt><dd><?php _e( 'compose new post', 'o2' ); ?></dd>
				<dt>r</dt> <dd><?php _e( 'reply', 'o2' ); ?></dd>
				<dt>e</dt> <dd><?php _e( 'edit', 'o2' ); ?></dd>
				<dt>t</dt> <dd><?php _e( 'go to top', 'o2' ); ?></dd>
				<dt>j</dt> <dd><?php _e( 'go to the next post or comment', 'o2' ); ?></dd>
				<dt>k</dt> <dd><?php _e( 'go to the previous post or comment', 'o2' ); ?></dd>
				<dt>o</dt> <dd><?php _e( 'toggle comment visibility', 'o2' ); ?></dd>
				<dt><?php _e( 'esc', 'o2' ); ?></dt> <dd><?php _e( 'cancel edit post or comment', 'o2' ); ?></dd>
			</dl>
		</div> <?php
	}
}
