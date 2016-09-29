<?php

class TermsInCommentsTest extends WP_UnitTestCase {

	function test_explicit_constructor() {

		$tic = new o2_Terms_In_Comments( 'category', 'tic_category_meta_key' );

		$this->assertEquals(
			'category', $tic->taxonomy,
			'Taxonomy field should get set when object is created'
		);

		$this->assertEquals(
			'tic_category_meta_key', $tic->meta_key,
			'Meta key field should get set when object is created with a meta parameter'
		);

	}

	function test_default_constructor() {

		$tic = new o2_Terms_In_Comments( 'category' );

		$this->assertEquals(
			"_category_term_meta", $tic->meta_key,
			'Meta key should get a default key when object is created without a meta parameter'
		);
	}

	function test_should_process_terms() {

		$tic = new o2_Terms_In_Comments( 'category' );

		$this->assertTrue(
			$tic->should_process_terms(),
			'Terms should be processed by default'
		);
	}

	function test_should_process_terms_filter() {

		$tic = new o2_Terms_In_Comments( 'category' );

		add_filter( 'o2_should_process_terms', function( $process ){
			return false;
		});

		$this->assertFalse(
			$tic->should_process_terms(),
			'Whether terms should be processed should be modify-able with a filter'
		);
	}

}
