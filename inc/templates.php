<?php

class o2_Templates {

	public $templates;
	public $template_dir;

	function __construct() {
		$this->templates = apply_filters( 'o2_templates', array(
			'app-header',
			'app-footer',
			'post-view',
			'no-posts-post-view',
			'post',
			'post-edit',
			'front-side-new-post-edit',
			'comment',
			'comment-edit',
			'logged-out-create-comment',
			'xpost',
			'search-form'
		) );
		$this->template_dir = plugin_dir_path( __FILE__ ) . 'tpl/';

		add_action( 'wp_footer', array( $this, 'embed_templates' ) );
	}

	/**
	 * Pass templates up for backbone to use when rendering
	 * @todo only do this on pages where we are going to use them
	 * @todo put these in a separate file just for templates/allow override from themes
	 * @todo check output with/without encoding <%- vs <%=
	 */
	public function embed_templates() {
		// Load filtered templates and output
		if ( !empty( $this->templates ) ) {
			foreach ( $this->templates as $template ) {

				// Allow plugins to filter this
				$continue = apply_filters( "o2_template_{$template}", false, $template );
				if ( $continue )
					continue;
				?>

<script type="html/template" id="tmpl-o2-<?php echo esc_html( $template ); ?>">
				<?php
				// Check theme for templates with o2- prefix
				$found = locate_template( "o2-{$template}.php", false );
				if ( !empty( $found ) )
					include( $found );

				// Otherwise load template from o2
				else
					include( $this->template_dir . "{$template}.php" );
				?>
</script>
				<?php
			}
		}

		// Load any additional templates
		do_action( 'o2_templates' );
	}
}
