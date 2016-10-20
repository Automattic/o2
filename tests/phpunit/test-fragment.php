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
			$post->ID, $fragment['id'],
			"Post fragment should have original post ID"
		);

		$this->assertEquals(
			$post->post_title, $fragment['titleRaw'],
			"Post fragment should have original post title"
		);

		$this->assertEquals(
			$post->post_content, $fragment['contentRaw'],
			"Post fragment should have original post content"
		);

		$this->assertEquals(
			get_permalink( $post->ID ), $fragment['permalink'],
			"Post fragment should have original post permalink"
		);
	}

	/**
	 * When testing previous/next post stuff explicitly set different post dates otherwise get_adjacent_post won't work properly
	 * https://core.trac.wordpress.org/ticket/28026
	 */

	function test_get_fragment_from_post_adjacent_posts() {
		global $post;

		$author_id = $this->factory->user->create( array(
			'user_login' => 'Test User'
		));

		$previous_post = $this->factory->post->create_and_get( array(
			'post_title' => 'Previous Post',
			'post_author' => $author_id,
			'post_status' => 'publish',
			'post_date' => '2016-01-01 06:00:00'
		));

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id,
			'post_status' => 'publish',
			'post_date' => '2016-01-02 06:00:00'
		));

		$next_post = $this->factory->post->create_and_get( array(
			'post_title' => 'Next Post',
			'post_author' => $author_id,
			'post_status' => 'publish',
			'post_date' => '2016-01-03 06:00:00'
		));

		$fragment = o2_Fragment::get_fragment_from_post( $post, array( 'find-adjacent' => true ) );

		$this->assertTrue(
			$fragment['hasPrevPost'],
			'Fragment should correctly indicate when it has a previous post'
		);

		$this->assertEquals(
			$previous_post->post_title, $fragment['prevPostTitle'],
			'Post fragment should contain the previous post title when a previous post exists'
		);

		$this->assertEquals(
			get_permalink( $previous_post->ID ), $fragment['prevPostURL'],
			'Post fragment should contain the previous post permalink when a previous post exists'
		);

		$this->assertTrue(
			$fragment['hasNextPost'],
			'Post fragment should correctly indicate when it has a next post'
		);

		$this->assertEquals(
			$next_post->post_title, $fragment['nextPostTitle'],
			'Post fragment should contain the next post title when a next post exists'
		);

		$this->assertEquals(
			get_permalink( $next_post->ID ), $fragment['nextPostURL'],
			'Post fragment should contain the next post permalink when a next post exists'
		);
	}

	function test_get_fragment_from_post_no_adjacent_posts() {
		global $post;

		$author_id = $this->factory->user->create( array(
			'user_login' => 'Test User'
		));

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id,
			'post_status' => 'publish',
			'post_date' => '2016-01-02 06:00:00'
		));

		$fragment = o2_Fragment::get_fragment_from_post( $post, array( 'find-adjacent' => true ) );

		$this->assertFalse(
			$fragment['hasPrevPost'],
			'Post fragment should correctly indicate when there is no previous post'
		);

		$this->assertFalse(
			$fragment['hasNextPost'],
			'Post fragment should correctly indicate when there is no next post'
		);
	}

}

