<?php

class TimeShortcodeTest extends WP_UnitTestCase {

	function test_parse_time_now() {

		$time_string = 'now';
		$epoch_now = date( 'U' );

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string, $epoch_now );

		$this->assertEquals( 
			$epoch_now, $parsed_time,
			'Time parser should handle "Now"'
		);
	}

	function test_parse_time_date() {

		$time_string = '10 September 2000';
		$epoch_now = date( 'U' );

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string, $epoch_now );

		$this->assertEquals(
			968544000, $parsed_time,
			'Time parser should handle specific dates'
		);
	}

	function test_parse_time_invalid() {

		$time_string = '12 Bananuary 2015';
		$epoch_now = date( 'U' );

		$parsed_time = o2_Time_Shortcode::parse_time( $time_string, $epoch_now );

		$this->assertFalse(
			$parsed_time,
			'Time parser should reject made up dates'
		);

	}

}

