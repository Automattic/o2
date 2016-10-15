<?php

class TimeShortcodeTest extends WP_UnitTestCase {

	/**
	* Time Parser tests
	*/

	function test_parse_time_now() {

		$time_string = 'now';
		$epoch_now = date( 'U' );

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		//Allow 1 second max difference in case time has changed since the test started
		$this->assertLessThanOrEqual(
			1, $parsed_time - $epoch_now,
			'Time parser should handle "Now"'
		);
	}

	function test_parse_time_now_relative() {

		$time_string = 'now';

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string, 968544000 );

		$this->assertEquals(
			968544000, $parsed_time,
			'Time parser should handle "Now" relative to some timestamp'
		);
	}

	function test_parse_time_date() {

		$time_string = '10 September 2000';

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		$this->assertEquals(
			968544000, $parsed_time,
			'Time parser should handle specific dates'
		);
	}

	function test_parse_time_american() {

		$time_string = '9/10/00';

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		$this->assertEquals(
			968544000, $parsed_time,
			'Time parser should handle American-style dates'
		);
	}

	function test_parse_time_european() {

		$time_string = '10-09-2000';

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		$this->assertEquals(
			968544000, $parsed_time,
			'Time parser should handle European-style dates'
		);

	}

	function test_parse_time_invalid() {

		$time_string = '12 Bananuary 2015';

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		$this->assertFalse(
			$parsed_time,
			'Time parser should reject made up dates'
		);

	}

	function test_parse_time_null() {

		$time_string = null;

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		$this->assertFalse(
			$parsed_time,
			'Time parser should reject null'
		);

	}

	function test_parse_time_empty_string() {

		$time_string = "";

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		$this->assertFalse(
			$parsed_time,
			'Time parser should reject empty string'
		);
	}

	function test_parse_time_relative_to_timestamp() {

		$time_string = "next Tuesday";
		$reference_date = 968544000; //Sunday 10 September 2000
		$next_tuesday = 968544000 + ( 60*60*24*2 );

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string, $reference_date );

		$this->assertEquals(
			$next_tuesday, $parsed_time,
			'Time parser should handle relative dates'
		);
	}

	function test_parse_time_relative_to_timestamp_with_plus() {

		$time_string = "+2 days";
		$reference_date = 968544000; //Sunday 10 September 2000
		$next_tuesday = 968544000 + ( 60*60*24*2 );

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string, $reference_date );

		$this->assertEquals(
			$next_tuesday, $parsed_time,
			'Time parser should handle relative dates'
		);
	}

	function test_parse_time_extra_whitespace() {
		$time_string = '10     September 		2000';

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		$this->assertEquals(
			968544000, $parsed_time,
			'Time parser should handle extra whitespace gracefully'
		);
	}

	function test_parse_time_nbsp() {

		$time_string = '10 &nbsp;September 2000';

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		$this->assertEquals(
			968544000, $parsed_time,
			'Time parser should handle &nbsp; gracefully'
		);
	}

	function test_parse_time_U00A0() {

		//Add a unicode nbsp in the middle of the string
		$time_string = '10 September'.json_decode('"\u00A0"').' 2000';

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		$this->assertEquals(
			968544000, $parsed_time,
			'Time parser should handle unicode nbsp gracefully'
		);
	}

	function test_parse_time_with_at() {

		$time_string = 'April 2 2009 at 2:00 PM';

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string );

		$this->assertEquals(
			1238680800, $parsed_time,
			'Time parser should handle the word "at"'
		);
	}

	/**
	* Module logic tests
	**/

	function test_shortcode_processed() {

		global $post;

		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test Post',
			'post_content' => 'This is some [time]April 2 2009 2:00 PM[/time] test content.'
		));

		$post = get_post( $post_id );
		setup_postdata( $post );

		$content = apply_filters( 'the_content', get_the_content() );

		$this->assertFalse(
			stripos( $content, "[time]" ),
			'Time shortcode should get processed in post content'
		);
	}

	function test_comment_shortcode_processed() {

		global $comment;

		$comment_id = $this->factory->comment->create( array(
			'comment_content' => 'Test [time]April 2 2009 2:00 PM[/time] comment content',
			'comment_approved' => 1
		));

		$the_comment = get_comment( $comment_id );
		$comment = $the_comment;

		$content = apply_filters( 'comment_text', $the_comment->comment_content );

		$this->assertFalse(
			stripos( $content, "[time]" ),
			'Time shortcode should get processed in comment content'
		);
	}


	function test_relative_shortcode_arg_processed() {

		global $post;

		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test Post',
			'post_content' => 'This is some [time now]+2 days[/time] test content.',
			'post_date' => '2000-01-01 02:00:00',
			'post_date_gmt' => '2000-01-01 02:00:00'
		));

		$post = get_post( $post_id );
		setup_postdata( $post );

		$result = o2_Time_Shortcode::time_shortcode( array(), '+2 days' );
		$this->assertTrue(
			(bool) strpos( $result, "946864800000"),
			'Time shortcode should parse relative to post time when no args in shortcode'
		);

		$result = o2_Time_Shortcode::time_shortcode( array( 'now' ), '+2 days' );
		$this->assertFalse(
			(bool) strpos( $result, "946864800000"),
			'Time shortcode should parse relative to now when no args in shortcode'
		);

	}

}
