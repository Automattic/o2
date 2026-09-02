<?php

class o2ChecklistsTest extends WP_UnitTestCase {

	private $list_creator;

	function setUp(): void {
		parent::setUp();
		$this->list_creator = new o2_List_Creator();
	}

	private function create_comment_on_another_authors_post( $commenter_id ) {
		$post_owner_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$post_id = $this->factory->post->create( array( 'post_author' => $post_owner_id ) );

		return $this->factory->comment->create( array(
			'comment_post_ID' => $post_id,
			'user_id' => $commenter_id,
			'comment_content' => 'o my task',
		) );
	}

	function test_author_can_edit_checklist_in_own_comment_on_another_authors_post() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$comment_id = $this->create_comment_on_another_authors_post( $author_id );

		wp_set_current_user( $author_id );

		$this->assertTrue(
			$this->list_creator->current_user_can_edit_checklist( 'comment', $comment_id ),
			'An author should be able to edit checklists in their own comment'
		);

		$rendered = $this->list_creator->parse_lists_in_comment( 'o my task', get_comment( $comment_id ) );

		$this->assertStringNotContainsString( 'disabled', $rendered, 'The checkbox should be enabled' );
		$this->assertStringContainsString( 'o2-task-tools', $rendered, 'The task tools should be rendered' );
	}

	function test_author_cannot_edit_checklist_in_another_users_comment() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$comment_id = $this->create_comment_on_another_authors_post( $other_author_id );

		wp_set_current_user( $author_id );

		$this->assertFalse(
			$this->list_creator->current_user_can_edit_checklist( 'comment', $comment_id ),
			'An author should not be able to edit checklists in another user\'s comment'
		);

		$rendered = $this->list_creator->parse_lists_in_comment( 'o my task', get_comment( $comment_id ) );

		$this->assertStringContainsString( 'disabled', $rendered, 'The checkbox should be disabled' );
		$this->assertStringNotContainsString( 'o2-task-tools', $rendered, 'The task tools should not be rendered' );
	}

	function test_editor_can_edit_checklist_in_another_users_comment() {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$comment_id = $this->create_comment_on_another_authors_post( $author_id );

		wp_set_current_user( $editor_id );

		$this->assertTrue(
			$this->list_creator->current_user_can_edit_checklist( 'comment', $comment_id ),
			'An editor should be able to edit checklists in any comment'
		);
	}

	function test_logged_out_user_cannot_edit_checklist_in_comment() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$comment_id = $this->create_comment_on_another_authors_post( $author_id );

		wp_set_current_user( 0 );

		$this->assertFalse( $this->list_creator->current_user_can_edit_checklist( 'comment', $comment_id ) );
	}
}
