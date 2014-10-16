<?php

class o2_API_Base {

	public static function die_success( $response, $response_code = 200 ) {
		if ( 200 !== $response_code )
			status_header( $response_code );
		wp_send_json_success( $response );
	}

	public static function die_failure( $error, $error_text, $response_code = 400 ) {
		status_header( $response_code );
		wp_send_json_error( array( 'error' => $error, 'errorText' => $error_text ) );
	}
}
