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

function o2_time_shortcode( $attr, $content = null ) {
	global $post, $time_in_comments, $comment;
	$post = get_post();

	// try to parse the time, relative to the post/comment time

	if ( $time_in_comments ) {
		$date     = strtotime( $comment->comment_date_gmt );
		$raw_date = date( 'Y-m-d H:i:s', $date );
	} else {
		$date     = get_the_date( 'U' );
		$raw_date = $post->post_date_gmt; // already in ISO format
	}
	$time = strtotime( $content, $date );

	// if strtotime doesn't work, try stronger measures
	if ( $time === false || $time === -1 ) {
		$timearray = date_parse( $content );
		foreach ( $timearray as $key => $value ) {
			if ( $value === false ) unset ( $timearray[$key] );
		}

		// merge the info from the post date with this one
		$relative = date_parse( $raw_date );
		$timearray = array_merge( $relative, $timearray );

		// use the blog's timezone if none was specified
		if ( !isset( $timearray['tz_id'] ) ) {
			$timearray['tz_id'] = get_option( 'timezone_string' );
		}

		// build a normalized time string, then parse it to an integer time using strtotime
		$time = strtotime( "{$timearray['year']}-{$timearray['month']}-{$timearray['day']}T{$timearray['hour']}:{$timearray['minute']}:{$timearray['second']} {$timearray['tz_id']}" );
	}

	// if that didn't work, give up
	if ( $time === false || $time === -1 ) {
		return $content;
	}

	// build the link and abbr microformat
	$out = '<a href="http://www.timeanddate.com/worldclock/fixedtime.html?iso=' . gmdate( 'Ymd\THi', $time ) . '"><abbr class="globalized-date" title="' . gmdate( 'c', $time ) . '">' . $content . '</abbr></a>';

	// add the time converter JS code
	add_action( 'wp_footer', 'o2_time_converter_script' );

	// return the new link
	return $out;
}

function o2_time_converter_script() {
	global $wp_locale; // These are strings that Core has already translated.
?>
	<script type="text/javascript">
	( function( $ ) {
		var ts = <?php echo json_encode( array(
			'months' => array_values( $wp_locale->month ),
			'days'   => array_values( $wp_locale->weekday ),
		) ); ?>;

		var o2_parse_date = function ( text ) {
			var m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})\+00:00$/.exec( text );
			var d = new Date();
			d.setUTCFullYear( +m[1] );
			d.setUTCDate( +m[3] );
			d.setUTCMonth( +m[2] - 1 );
			d.setUTCHours( +m[4] );
			d.setUTCMinutes( +m[5] );
			d.setUTCSeconds( +m[6] );
			return d;
		}
		var o2_format_date = function ( d ) {
			var p = function( n ) {
				return ( '00' + n ).slice( -2 );
			};
			var tz = -d.getTimezoneOffset() / 60;
			if ( tz >= 0 ) {
				tz = "+" + tz;
			}
			return "" + ts['days'][ d.getDay() ] + ", " + ts['months'][ d.getMonth() ] + " " + p( d.getDate() ) + ", " + d.getFullYear() + " " + p( d.getHours() ) + ":" + p( d.getMinutes() ) + " UTC" + tz;
		}

		$( 'body' ).on( 'ready post-load ready.o2', function() {
			$( 'abbr.globalized-date' ).each( function() {
				t = $( this );
				var d = o2_parse_date( t.attr( 'title' ) );
				if ( d ) {
					t.text( o2_format_date( d ) );
				}
			} );
		} );
	} )( jQuery );
	</script>
<?php
}
add_shortcode( 'time', 'o2_time_shortcode' );

function o2_time_shortcode_in_comments( $comment ) {
	global $shortcode_tags, $post, $time_in_comments;
	$time_in_comments = true;
	$post = get_post();

	// save the shortcodes
	$saved_tags = $shortcode_tags;

	// only process the time shortcode
	$shortcode_tags = array( 'time' => 'o2_time_shortcode' );

	// do the time shortcode on the comment
	$comment = do_shortcode( $comment );

	// restore the normal shortcodes
	$shortcode_tags = $saved_tags;

	$time_in_comments = false;
	return $comment;
}
add_filter( 'comment_text', 'o2_time_shortcode_in_comments' );
