<?php

class FragmentTest extends WP_UnitTestCase {

	function test_get_fragment_post() {

		global $post;

		$author_id = $this->factory->user->create( array(
			'user_login' => 'Test User'
		) );

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id
		) );

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
		) );

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id
		) );

		$comment = $this->factory->comment->create_and_get( array(
			'comment_post_ID' => $post->ID,
			'comment_content' => 'Test comment content',
			'comment_approved' => 1
		) );

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
		) );

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
		) );

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id
		) );

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
		) );

		$previous_post = $this->factory->post->create_and_get( array(
			'post_title' => 'Previous Post',
			'post_author' => $author_id,
			'post_status' => 'publish',
			'post_date' => '2016-01-01 06:00:00'
		) );

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id,
			'post_status' => 'publish',
			'post_date' => '2016-01-02 06:00:00'
		) );

		$next_post = $this->factory->post->create_and_get( array(
			'post_title' => 'Next Post',
			'post_author' => $author_id,
			'post_status' => 'publish',
			'post_date' => '2016-01-03 06:00:00'
		) );

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
		) );

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id,
			'post_status' => 'publish',
			'post_date' => '2016-01-02 06:00:00'
		) );

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

	function test_get_fragment_from_comment() {

		global $comment;

		$author_id = $this->factory->user->create( array(
			'user_login' => 'Test User'
		) );

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id,
			'post_status' => 'publish',
			'post_date' => '2016-01-02 06:00:00'
		) );

		$comment = $this->factory->comment->create_and_get( array(
			'comment_content' => 'Test Comment Content',
			'comment_approved' => 1,
			'comment_post_ID' => $post->ID
		) );

		$fragment = o2_Fragment::get_fragment_from_comment( $comment );

		$this->assertEquals(
			$comment->comment_ID, $fragment['id'],
			"Comment fragment should have original comment ID"
		);

		$this->assertEquals(
			$post->post_title, $fragment['postTitleRaw'],
			"Comment fragment should have parent post title"
		);

		$this->assertEquals(
			$comment->comment_content, $fragment['contentRaw'],
			"Comment fragment should have original comment content"
		);

		$this->assertGreaterThanOrEqual(
			0, stripos( get_permalink( $post->ID ).'#', $fragment['permalink'] ),
			"Comment permalink should be located within parent post"
		);
	}

	function test_get_depth_for_comment() {

		$grandparent = $this->factory->comment->create_and_get( array(
			'comment_content' => 'Test Comment Content 1',
			'comment_approved' => 1
		) );

		$parent = $this->factory->comment->create_and_get( array(
			'comment_content' => 'Test Comment Content 2',
			'comment_approved' => 1,
			'comment_parent' => $grandparent->comment_ID
		) );

		$child = $this->factory->comment->create_and_get( array(
			'comment_content' => 'Test Comment Content 3',
			'comment_approved' => 1,
			'comment_parent' => $parent->comment_ID
		) );

		$this->assertEquals(
			1, o2_Fragment::get_depth_for_comment( $grandparent ),
			'get_depth_for_comment should return the correct comment depth for top level comment'
		);

		$this->assertEquals(
			2, o2_Fragment::get_depth_for_comment( $parent ),
			'get_depth_for_comment should return the correct comment depth for second level comment'
		);

		$this->assertEquals(
			3, o2_Fragment::get_depth_for_comment( $child ),
			'get_depth_for_comment should return the correct comment depth for third level comment'
		);
	}

	function test_get_current_user_properties_logged_in() {

    		global $current_user;

		$current_user = $this->factory->user->create_and_get( array(
			'user_login' => 'Test User'
		) );
		wp_set_current_user( $current_user->ID );

		$properties = o2_Fragment::get_current_user_properties();

		$this->assertEquals(
			$current_user->user_login, $properties['userLogin'],
			'Current user info should be in properties when current user is set'
		);

		$this->assertEquals(
			"", $properties['noprivUserName'],
			'Nopriv info should not be in properties when current user is set'
		);
	}

	function test_get_current_user_properties_nopriv() {

		add_filter( 'wp_get_current_commenter', function() {
			return array (
				'comment_author' => 'Test Guest',
				'comment_author_email' => 'test@example.com',
				'comment_author_url' => 'http://example.com'
			);
		} );

		$properties = o2_Fragment::get_current_user_properties();

		$this->assertEquals(
			'Test Guest', $properties['noprivUserName'],
			'Commenter info should be present if no user logged in'
		);

		$this->assertEquals(
			"", $properties['userLogin'],
			'Current user info should not be present for nopriv users'
		);
	}

	function test_add_to_user_bootstrap() {

		global $o2_userdata;
		$o2_userdata = null;

		$user = $this->factory->user->create_and_get( array(
			'user_login' => 'Test User'
		) );

		o2_Fragment::add_to_user_bootstrap( $user );

		$this->assertArrayHasKey(
			$user->user_login, $o2_userdata,
			'Users should get added to the global o2_userdata when added to user bootstrap'
		);
	}

	function test_get_model_from_userdata() {

		$user = $this->factory->user->create_and_get( array(
			'user_login' => 'Test User'
		) );

		$model = o2_Fragment::get_model_from_userdata( $user );

		$this->assertEquals(
			$user->ID, $model['id'],
			'Model should have input user id'
		);

		$this->assertEquals(
			$user->user_login, $model['userLogin'],
			"Model should have input user's login"
		);
	}

	function test_get_post_user_properties() {

		$user = $this->factory->user->create_and_get( array(
			'user_login' => 'Test User'
		) );

		$post = $this->factory->post->create_and_get( array(
			'post_title' => 'Test Post',
			'post_author' => $user->ID
		) );

		$properties = o2_Fragment::get_post_user_properties( $post );

		$this->assertEquals(
			$user->user_login, $properties['userLogin'],
			"Properties should have the input post's author user info"
		);
	}

	function test_get_comment_author_properties_user() {

		$user = $this->factory->user->create_and_get( array(
			'user_login' => 'Test User'
		) );

		$comment = $this->factory->comment->create_and_get( array(
			'comment_content' => 'Test Comment Content',
			'comment_approved' => 1,
			'user_id' => $user->ID
		) );

		$properties = o2_Fragment::get_comment_author_properties( $comment );

		$this->assertEquals(
			$user->user_login, $properties['userLogin'],
			"Properties should have input comment's user info for comments made by users"
		);
	}

	function test_get_comment_author_properties_nopriv() {

		$comment = $this->factory->comment->create_and_get( array(
			'comment_content' => 'Test Comment Content',
			'comment_approved' => 1,
			'comment_author' => 'Test Guest'
		) );

		$properties = o2_Fragment::get_comment_author_properties( $comment );

		$this->assertEquals(
			$comment->comment_author, $properties['noprivUserName'],
			"Properties should have input comment's author info for comments made by guests"
		);
	}

	function test_get_post_tags_raw() {
		
		$tag1 = $this->factory->tag->create_and_get( array(
			'name' => 'First Tag'
		) );
		$tag2 = $this->factory->tag->create_and_get( array(
			'name' => 'Second Tag'
		) );
		$tag3 = $this->factory->tag->create_and_get( array(
			'name' => 'Third Tag'
		) );
		$tags = array( $tag1, $tag2, $tag3 );

		$raw = o2_Fragment::get_post_tags_raw( $tags );

		$this->assertEquals(
			'First Tag, Second Tag, Third Tag', $raw,
			'Tags should be combined into one comma delimited list'
		);
	}

	function test_get_post_tags_array() {

		$tag = $this->factory->tag->create_and_get( array(
			'name' => 'Test Tag'
		) );

		$tag_array = o2_Fragment::get_post_tags_array( array( $tag ) );

		$this->assertEquals(
			$tag->name, $tag_array[0]['label'],
			'Tags should get converted into an array'
		);
	}

	function test_get_post_tags() {

		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test Post'
		) );

		wp_set_post_tags( $post_id, 'Test Tag' );

		$start_time = microtime( true );

		$first_time_tags = o2_Fragment::get_post_tags( $post_id );
		$first_time = microtime( true );

		$second_time_tags = o2_Fragment::get_post_tags( $post_id );
		$second_time = microtime( true );

		$this->assertEquals(
			'Test Tag', $first_time_tags[0]->name,
			'Input post tags should be returned'
		);

		$this->assertSame(
			$first_time_tags, $second_time_tags,
			'Caching should not degrade integrity'
		);

		$this->assertGreaterThan( 
			$second_time - $first_time, $first_time - $start_time,
			'Warm cache should take less time than cold cache'
		);
	}

	function test_get_post_terms() {

		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test Post'
		) );

		$category = $this->factory->category->create_and_get( array(
			'name' => 'Test Category'
		) );

		wp_set_post_tags( $post_id, 'Test Tag' );
		wp_set_post_categories( $post_id, array( $category->term_id ) );

		$start_time = microtime( true );

		$first_time_terms = o2_Fragment::get_post_terms( $post_id );
		$first_time = microtime( true );

		$second_time_terms = o2_Fragment::get_post_terms( $post_id );
		$second_time = microtime( true );

		$this->assertEquals(
			$category->name, $first_time_terms['category'][0]['label'],
			'Input post terms should be returned and categorized correctly'
		);

		$this->assertEquals(
			'Test Tag', $first_time_terms['post_tag'][0]['label'],
			'Input post terms should be returned and categorized correctly'
		);

		$this->assertSame(
			$first_time_terms, $second_time_terms,
			'Caching should not degrade integrity'
		);

		$this->assertGreaterThan( 
			$second_time - $first_time, $first_time - $start_time,
			'Warm cache should take less time than cold cache'
		);
	}
}
