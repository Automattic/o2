<?php

class FragmentTest extends WP_UnitTestCase {

	function test_get_fragment_post() {
		global $post;

		$author_id = $this->factory->user->create( array(
			'user_login' => 'Test User'
		));

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id
		));

		$fragment = o2_Fragment::get_fragment( $post );

		$this->assertTrue(
			is_array( $fragment ),
			"Fragment should be an array of post info"
		);

		$this->assertEquals(
			'post', $fragment['type'],
			"Fragment from post should have the 'post' type" 
		);

		$this->assertEquals(
			$post->ID, $fragment['postID'],
			"Fragment from post should have info for the same post"
		);

	}

	function test_get_fragment_comment() {
		global $comment;
		global $post;

		$author_id = $this->factory->user->create( array(
			'user_login' => 'Test User'
		));

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id
		));

		$comment = $this->factory->comment->create_and_get( array(
			'comment_post_ID' => $post->ID,
			'comment_content' => 'Test comment content',
			'comment_approved' => 1
		));

		$fragment = o2_Fragment::get_fragment( $comment );

		$this->assertTrue(
			is_array( $fragment ),
			"Fragment should be an array of comment info"
		);

		$this->assertEquals(
			'comment', $fragment['type'],
			"Fragment from comment should have the 'comment' type" 
		);

		$this->assertEquals(
			$comment->comment_ID, $fragment['id'],
			"Fragment from comment should have info for the same comment"
		);

	}

	function test_get_fragment_neither() {

		$author = $this->factory->user->create_and_get( array(
			'user_login' => 'Test User'
		));

		$fragment = o2_Fragment::get_fragment( $author );

		$this->assertFalse(
			$fragment,
			'get_fragment should return false on non-post, non-comment input'
		);

	}

	function test_get_fragment_from_post() {
		global $post;

		$author_id = $this->factory->user->create( array(
			'user_login' => 'Test User'
		));

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id
		));

		$fragment = o2_Fragment::get_fragment_from_post( $post );

		$this->assertEquals(
			$post->post_content, $fragment['contentRaw'],
			"Fragment contentRaw should have unfiltered post content"
		);
	}
}

