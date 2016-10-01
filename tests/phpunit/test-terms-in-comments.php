<?php

class o2_Terms_In_Comments_Helper extends o2_Terms_In_Comments {

	function update_comment_terms( $comment_id, $comment ) {
		return array( 'first_comment_term', 'second_comment_term' );
	}

	function update_post_terms( $post_id, $post ) {
		return array( 'first_post_term', 'second_post_term' );
	}

};

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

	function test_update_comment() {

		$comment_post_id = $this->factory->post->create( array(
			'post_title' => 'Test Post'
		));

		$tic = new o2_Terms_In_Comments_Helper( 'category', 'test_key' );

		$comment_id = wp_insert_comment( array(
			'comment_content' => 'Test Comment',
			'comment_post_ID' => $comment_post_id
		));

		$comment_terms = get_comment_meta( $comment_id, $tic->meta_key, false );

		$this->assertTrue(
			in_array( 'first_comment_term', $comment_terms ),
			'Comments should automatically get terms added'
		);

		$this->assertTrue(
			in_array( 'second_comment_term', $comment_terms ),
			'Comments should get all terms added'
		);
	}

	function test_update_post() {

		$tic = new o2_Terms_In_Comments_Helper( 'category', 'test_key' );

		$post_id = wp_insert_post( array(
			'post_content' => 'Test Post'
		));

		$post_terms = get_post_meta( $post_id, $tic->meta_key, false );

		$this->assertTrue(
			in_array( 'first_post_term', $post_terms ),
			'Posts should automatically get terms added'
		);

		$this->assertTrue(
			in_array( 'second_post_term', $post_terms ),
			'Posts should get all terms added'
		);
	}

	function test_get_comment_meta_default() {

		$tic = new o2_Terms_In_Comments_Helper( 'category' );

		$comment_id = wp_insert_comment( array(
			'comment_content' => 'Test Comment'
		));

		add_comment_meta( $comment_id, $tic->meta_key, 'third_comment_term' );

		$comment_meta = $tic->get_comment_meta();

		$this->assertTrue(
			in_array( 'first_comment_term', $comment_meta ),
			'All the comment meta should be retrieved'
		);

		$this->assertTrue(
			in_array( 'second_comment_term', $comment_meta ),
			'All the comment meta should be retrieved'
		);

		$this->assertTrue(
			in_array( 'third_comment_term', $comment_meta ),
			'All the comment meta should be retrieved'
		);
	}

	function test_get_comment_meta_by_post_id() {

		$comment_post_id = $this->factory->post->create( array(
			'post_title' => 'Test Post'
		));

		$tic = new o2_Terms_In_Comments_Helper( 'category' );

		$comment_id = wp_insert_comment( array(
			'comment_content' => 'Test Comment',
			'comment_post_ID' => $comment_post_id
		));

		$comment2_id = wp_insert_comment( array(
			'comment_content' => 'Second Test Comment'
		));

		add_comment_meta( $comment2_id, $tic->meta_key, 'dont_fetch_this_value' );

		$comment_meta = $tic->get_comment_meta( array( 'post_id' => $comment_post_id ) );

		$this->assertTrue(
			in_array( 'first_comment_term', $comment_meta ),
			'All the comment meta for comments with a specified parent post should be retrieved'
		);

		$this->assertFalse(
			in_array( 'dont_fetch_this_value', $comment_meta ),
			'Comment meta for comments not in the specified parent post should not be retrieved'
		);

	}

	function test_get_comment_meta_with_max_number() {

		$tic = new o2_Terms_In_Comments_Helper( 'category' );

		$comment_id = wp_insert_comment( array(
			'comment_content' => 'Test Comment'
		));

		$comment_meta = $tic->get_comment_meta( array( 'number' => 1 ) );

		$this->assertTrue(
			in_array( 'first_comment_term', $comment_meta ),
			'The number param should restrict the maximum number of meta values that can be retrieved'
		);

		$this->assertFalse(
			in_array( 'second_comment_term', $comment_meta ),
			'The number param should restrict the maximum number of meta values that can be retrieved'
		);
	}


	function test_get_comment_meta_order() {

		$tic = new o2_Terms_In_Comments_Helper( 'category' );

		$comment_id = wp_insert_comment( array(
			'comment_content' => 'Test Comment'
		));

		$comment_meta = $tic->get_comment_meta( array( 'orderby' => 'meta_value', 'order' => 'DESC' ) );

		$this->assertEquals(
			'second_comment_term', $comment_meta[0],
			'The order param should allow modification of the result order'
		);
	}

	function test_get_comment_meta_offset() {

		$tic = new o2_Terms_In_Comments_Helper( 'category' );

		$comment_id = wp_insert_comment( array(
			'comment_content' => 'Test Comment'
		));

		$comment_meta = $tic->get_comment_meta( array( 'number' => '99', 'offset' => 1 ) );

		$this->assertEquals(
			'second_comment_term', $comment_meta[0],
			'The offset param should ignore the first query results'
		);
	}

	function test_update_terms() {

		$category1 = $this->factory->category->create_and_get( array(
			'name' => 'first_comment_term'
		));

		$category2 = $this->factory->category->create_and_get( array(
			'name' => 'second_comment_term'
		));

		$comment_post_id = $this->factory->post->create( array(
			'name' => 'Test Post'
		));

		$tic = new o2_Terms_In_Comments_Helper( 'category' );

		$comment_id = wp_insert_comment( array(
			'comment_content' => 'Test Comment',
			'comment_post_ID' => $comment_post_id
		));

		$object_terms = wp_get_object_terms( $comment_post_id, 'category', array( 'fields' => 'names' ) );

		$this->assertTrue(
			in_array( 'first_comment_term', $object_terms ),
			'Comment terms should propagate to the parent post'
		);

		$this->assertTrue(
			in_array( 'second_comment_term', $object_terms ),
			'Comment terms should propagate to the parent post'
		);
	}
}
