<?php

class o2BaseTest extends WP_UnitTestCase {

	function test_global_o2_exists() {

		global $o2;

		$this->assertTrue( 
			is_a( $o2, 'o2' ), 
			'Global o2 object should be created when plugin initializes' 
		);
	}

	function test_disable_highlander() {

		global $o2;

		add_action( 'init', array( 'Highlander_Comments', 'init' ) );

		$o2->disable_highlander();

		$this->assertFalse( 
			has_action( 'init', array( 'Highlander_Comments', 'init' ) ), 
			'Highlander Comments should be disabled' 
		);
	}

	function test_post_flair_mute() {

		global $o2;

		if( !function_exists( 'post_flair' ) ){
			function post_flair(){ return new stdClass(); };
		}

		add_filter( 'the_content', array( post_flair(), 'display' ), 999 );

		$o2->post_flair_mute();

		$this->assertFalse(
			has_filter( 'the_content', array( post_flair(), 'display' ) ),
			'Post flair should be disabled'
		);
	}

	function test_get_app_controls() {

		global $o2;

		$controls = $o2->get_app_controls();

		$this->assertNotEmpty( 
			$controls, 
			'App controls should be present by default'
		);
	}

	function test_get_app_controls_filter() {

		global $o2;

		add_filter( 'o2_app_controls', function( $controls ){
			$controls[] = 'Foo';
			return $controls;
		});

		$controls = $o2->get_app_controls();

		$this->assertTrue(
			in_array( 'Foo', $controls ),
			'Should be able to add/modify app controls using a filter'
		);
	}

	function test_get_application_container() {

		global $o2;

		$container = $o2->get_application_container();

		$this->assertTrue(
			is_string( $container ),
			'get_application_container method should return a DOM element selector'
		);

		$this->assertGreaterThan( 
			0, strlen( $container ),
			'get_application_container method should return a valid DOM element selector'
		);
	}

	function test_get_application_container_filter() {
		
		global $o2;

		add_filter( 'o2_application_container', function( $container ){
			$container = '#modified_container';
			return $container;
		} );

		$this->assertEquals( 
			'#modified_container', $o2->get_application_container(),
			'Should be able to modify the application container selector using a filter'
		);
	}

	function test_show_comments_initially() {

		global $o2;

		$this->assertTrue( 
			$o2->show_comments_initially(),
			'Comments should be shown by default'
		);
	}

	function test_show_comments_initially_filter() {

		global $o2;

		add_filter( 'o2_show_comments_initially', function( $show ){
			return false;
		});

		$this->assertFalse(
			$o2->show_comments_initially(),
			'Should be able to modify comment display defaults with a filter'
		);
	}

	function test_body_class() {

		$classes = apply_filters( 'body_class', array() );

		$this->assertTrue(
			in_array( 'o2', $classes ),
			'The o2 class should get added to the body'
		);
	}

	function test_post_class() {

		$author_id = $this->factory->user->create( array(
			'user_login' => 'Test User'
		));

		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test Post',
			'post_author' => $author_id
		));

		$classes = apply_filters( 'post_class', array(), '', $post_id );

		$this->assertTrue(
			in_array( 'author-test-user', $classes ),
			'Author nicename class should get added to post classes'
		);
	}

	function test_get_defaults() {

		$defaults = o2::get_defaults();

		$this->assertArrayHasKey(
			'o2_enabled', $defaults,
			'Defaults should have whether o2 is enabled'
		);

		$this->assertArrayHasKey(
			'front_side_post_prompt', $defaults,
			'Defaults should have a default post prompt'
		);

		$this->assertArrayHasKey(
			'enable_resolved_posts', $defaults,
			'Defaults should have whether resolved posts are enabled'
		);

		$this->assertArrayHasKey(
			'mark_posts_unresolved', $defaults,
			'Defaults should have whether unresolved posts are marked'
		);
		
	}

	function test_required_comment_fields() {

		$options = array();
		$options['strings'] = array();

		update_option( 'require_name_email', 0 );

		$filtered_options = o2::required_comment_fields( $options );

		foreach( $filtered_options['strings'] as $option_string ){
			$this->assertFalse(
				stripos( $option_string, 'required' ),
				'Comment fields should not be required if option is "not required"'
			);
		}

		update_option( 'require_name_email', 1 );

		$filtered_options = o2::required_comment_fields( $options );

		foreach( $filtered_options['strings'] as $option_string ){
			$this->assertGreaterThan(
				0, stripos( $option_string, 'required' ),
				'Comment fields should be required if option is "required"'
			);
		}
	}

	function test_settings_merge() {

		global $o2;

		$defaults = o2::get_defaults();
		$settings = array();
		$_settings = array(
			'o2_enabled' => false,
			'front_side_post_prompt' => 'foo',
			'enable_resolved_posts' => true,
			'mark_posts_unresolved' => true
		);

		$final_settings = $o2->settings_merge( $defaults, $settings, $_settings );

		$this->assertFalse(
			$final_settings['o2_enabled'],
			'o2_enabled setting should take and preserve a boolean value'
		);

		$this->assertTrue(
			is_string( $final_settings['front_side_post_prompt'] ),
			'front_side_post_prompt setting should take and preserve a string value'
		);

		$this->assertTrue(
			$final_settings['enable_resolved_posts'],
			'enable_resolved_posts setting should take and preserve a boolean value'
		);

		$this->assertTrue(
			$final_settings['mark_posts_unresolved'],
			'mark_posts_unresolved setting shoult take and preserve a boolean value'
		);

	}

	function test_settings_merge_bad_input() {

		global $o2;

		$defaults = o2::get_defaults();
		$settings = array();
		$_settings = array(
			'o2_enabled' => 'foo',
			'front_side_post_prompt' => '"?>DROP',
			'enable_resolved_posts' => 'sometimes',
			'extra_bonus_input' => true
		);

		$final_settings = $o2->settings_merge( $defaults, $settings, $_settings );

		$this->assertTrue(
			is_bool( $final_settings['o2_enabled'] ),
			'o2_enabled setting should only have a boolean value'
		);

		$this->assertEquals(
			'&quot;?&gt;DROP', $final_settings['front_side_post_prompt'],
			'front_side_post_prompt setting should have sanitized string input'
		);

		$this->assertTrue(
			is_bool( $final_settings['enable_resolved_posts'] ),
			'enable_resolved_posts setting should only have a boolean value'
		);

		$this->assertArrayHasKey(
			'mark_posts_unresolved', $final_settings,
			'Omitted settings should get something as a default'
		);

		$this->assertArrayNotHasKey(
			'extra_bonus_input', $final_settings,
			'Junk settings should get discarded'
		);
	}

	function test_view_type_filter() {
		
		add_filter( 'o2_view_type', function( $type ){
			return 'test';
		} );

		$this->assertEquals( 
			'test', o2::get_view_type(),
			'The view type should be filterable'
		);
	}

	function test_add_query_var() {
		global $o2;
		global $wp;

		$o2->add_query_vars();

		$this->assertTrue(
			in_array( 'o2_login_complete', $wp->public_query_vars ),
			'The o2_login_complete query variable should exist in the $wp object'
		);
	}

	function test_add_json_data() {

		global $post;
		global $wp_query;

		$author_id = $this->factory->user->create( array(
			'user_login' => 'Test User'
		));

		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test Post',
			'post_content' => 'Some kinda post content.',
			'post_author' => $author_id
		));

		$post = get_post( $post_id );
		setup_postdata( $post );
		$wp_query->is_home = true;

		$content = apply_filters( 'the_content', get_the_content() );

		$this->assertGreaterThan(
			0, stripos( $content, "<script class='o2-data'" ),
			'Post content should get JSON info added in a script blob'
		);
	}

	function test_remove_oembed_handlers() {
                $oembed = _wp_oembed_get_object();
		$providers = array_keys( $oembed->providers );

		$this->assertFalse(
			in_array( '#https?://(.+\.)?polldaddy\.com/.*#i', $providers ),
			'Polldaddy oembeds should not be supported'
		);

		$this->assertFalse(
			in_array( '#https?://poll\.fm/.*#i', $providers ),
			'Polldaddy oembeds should not be supported'
		);
	}

/* 	TODO:

	function test_delete_comment_override() {

	}

	function test_insert_comment_actions() {

	}

	function test_has_approved_child() {

	}

	function test_add_trashed_parents() {

	}

	function test_remove_trashed_parents() {

	}

	function test_maybe_set_comment_has_children() {

	}
*/
}

