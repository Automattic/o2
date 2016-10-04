<?php
/**
 * Plugin: Time Shortcode
 * Original Author: Otto
 *
 * Usage: [time]any-valid-time-string-here[/time]
 * Will attempt to parse the time string and create a format that shows it in the viewers local time zone
 * Note that times should be complete with year, month, day, and hour and minute.. something strtotime can parse meaningfully.
 * Times are assumed to be UTC.
 * Conversion happens via Javascript and will depend on the users browser.
 **/

class o2_Time_Shortcode {

	public static function init() {

		add_shortcode( 'time', array( 'o2_Time_Shortcode', 'time_shortcode' ) );
		add_filter( 'comment_text', array( 'o2_Time_Shortcode', 'do_comment_time_shortcode' ) );
	}

	public static function time_shortcode( $attr, $content = null ) {

		global $in_comment_content, $comment;

		// try to parse the time, relative to the post/comment time
		if ( $in_comment_content ) {
			$relative_time = strtotime( $comment->comment_date_gmt );
		} else {
			$relative_time = get_the_date( 'U' );
		}

		$time = self::parse_time( $content, $relative_time );

		// if that didn't work, give up
		if ( $time === false || $time === -1 ) {
			return $content;
		}

		// build the link and abbr microformat
		$out = '<a href="http://www.timeanddate.com/worldclock/fixedtime.html?iso=' . gmdate( 'Ymd\THi', $time ) . '"><abbr class="globalized-date" title="' . gmdate( 'c', $time ) . '" data-time="' . absint( $time ) . '000">' . $content . '</abbr></a>';

		// add the time converter JS code if not already added
		if( !has_action( 'wp_footer', array( 'o2_Time_Shortcode', 'time_conversion_script' ) ) ){
			add_action( 'wp_footer', array( 'o2_Time_Shortcode', 'time_conversion_script' ) );
		}

		// return the new link
		return $out;
	}

	public static function parse_time( $date_string, $time = null ) {

		if( empty( $date_string ) ){
			return false;
		}

		if( empty( $time ) ){
			$time = time();
		}

		$html_entity_regex = '/&[^\s]*;/';
		$date_string = preg_replace( $html_entity_regex, ' ', $date_string );
		$date_string = str_ireplace( "U+00A0", ' ', $date_string );

		return strtotime( $date_string, $time );

	}

	/**
	 * Process the time shortcode and only the time shortcode in comments
	 *
	 * @param string $comment_text The comment content
	 * @return string Modified comment content
	 **/
	public static function do_comment_time_shortcode( $comment_text ) {

		global $shortcode_tags, $in_comment_content;
		$in_comment_content = true;

		// save the shortcodes
		$original_tags = $shortcode_tags;

		// only process the time shortcode
		$shortcode_tags = array( 'time' => 'o2_time_shortcode' );

		// do the time shortcode on the comment
		$comment_text = do_shortcode( $comment_text );

		// restore the normal shortcodes
		$shortcode_tags = $original_tags;

		$in_comment_content = false;
		return $comment_text;
	}

	public static function time_conversion_script() {

		global $wp_locale; // These are strings that Core has already translated.

		?>
		<script type="text/javascript">

			var o2_get_time_settings = function(){
				return <?php echo json_encode( array(
					'months' => array_values( $wp_locale->month ),
					'days'   => array_values( $wp_locale->weekday ),
				) ); ?>;
			}

			jQuery( 'body' ).on( 'ready post-load ready.o2', function() {

				jQuery( 'abbr.globalized-date' ).each( function() {

					var $this = jQuery( this );

					var time = $this.data( 'time' );
					if( ! time || isNaN( time ) ){
						return;
					}

					var date = new Date( time );
					$this.text( o2_format_date( date ) );

				} );
			} );

		</script>
		<?php

		wp_enqueue_script(
			'o2-time-shortcode',
			plugins_url( 'modules/time-shortcode/js/time-shortcode.js', O2__FILE__ ),
			array(),
			1.1,
			true
		);
	}
}
o2_Time_Shortcode::init();
