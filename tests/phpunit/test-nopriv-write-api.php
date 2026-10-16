<?php

class NoPrivWriteAPITest extends WP_UnitTestCase {

	function test_get_comment_fields_from_logged_out_client() {
		$message = json_decode( '{"type":"comment","postID":5,"parentID":3,"contentRaw":"Hello","author":{"name":"Jane","email":"jane@example.com","url":"https://example.com"}}' );

		$this->assertSame(
			array(
				'comment_post_ID' => 5,
				'comment'         => 'Hello',
				'comment_parent'  => 3,
				'author'          => 'Jane',
				'email'           => 'jane@example.com',
				'url'             => 'https://example.com',
			),
			o2_NoPriv_Write_API::get_comment_fields( $message )
		);
	}

	function test_get_comment_fields_defaults_missing_fields() {
		$message = json_decode( '{"type":"comment","postID":999999999,"contentRaw":"test"}' );

		$this->assertSame(
			array(
				'comment_post_ID' => 999999999,
				'comment'         => 'test',
				'comment_parent'  => 0,
				'author'          => '',
				'email'           => '',
				'url'             => '',
			),
			o2_NoPriv_Write_API::get_comment_fields( $message )
		);
	}

	function test_create_comment_from_logged_in_client_reports_logged_out() {
		$message = json_decode( '{"type":"comment","postID":5,"parentID":0,"contentRaw":"Hello","author":{"userLogin":"jane","userNicename":"jane"}}' );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_die_handler' ) );

		// If the guard regresses, wp-comments-post.php is reached, and it exits on anything but POST,
		// silently ending the test run.
		$request_method            = $_SERVER['REQUEST_METHOD'] ?? null;
		$_SERVER['REQUEST_METHOD'] = 'POST';

		ob_start();
		try {
			o2_NoPriv_Write_API::create_comment( $message );
		} catch ( WPDieException $e ) {
			// Expected, die_failure() ends the request.
		} finally {
			$_SERVER['REQUEST_METHOD'] = $request_method;
		}
		$response = json_decode( ob_get_clean(), true );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'logged_out', $response['data']['error'] );
	}

	function get_die_handler() {
		return array( $this, 'die_handler' );
	}

	function die_handler( $message ) {
		throw new WPDieException( $message );
	}
}
