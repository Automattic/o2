<?php
/**
 * Tests for o2_Read_API.
 *
 * @package o2
 */

declare( strict_types = 1 );

/**
 * Tests for o2_Read_API.
 */
class ReadAPITest extends WP_UnitTestCase {

	/**
	 * Removes the global comment the test sets.
	 */
	public function tear_down(): void {
		unset( $GLOBALS['comment'] );

		parent::tear_down();
	}

	/**
	 * The preview shows backslashes and quotes as typed.
	 *
	 * @dataProvider data_preview_keeps_slashes
	 *
	 * @param string $type     Preview type, 'comment' or 'post'.
	 * @param string $role     Role of the previewing user.
	 * @param string $data     Text the user typed.
	 * @param string $expected Expected preview HTML.
	 */
	public function test_preview_keeps_slashes( string $type, string $role, string $data, string $expected ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

		// The xposts comment_text filter reads the global comment.
		$GLOBALS['comment'] = self::factory()->comment->create_and_get();

		// Requests arrive slashed.
		$_REQUEST['type'] = $type;
		$_REQUEST['data'] = wp_slash( $data );

		$this->assertSame( $expected, $this->get_preview() );
	}

	/**
	 * Data provider for test_preview_keeps_slashes().
	 *
	 * @return array<string, array<int, string>>
	 */
	public function data_preview_keeps_slashes(): array {
		return array(
			'comment, filtered HTML' => array( 'comment', 'author', '<code>/\*{1}\n</code>', '<p><code>/\*{1}\n</code></p>' ),
			'post, filtered HTML'    => array( 'post', 'author', "Don't panic\n" . '<code>/\*{1}\n</code>', "<h1>Don't panic</h1>" . '<p><code>/\*{1}\n</code></p>' ),
			'post, unfiltered HTML'  => array( 'post', 'editor', "Don't panic\n" . '<code>/\*{1}\n</code>', "<h1>Don't panic</h1>" . '<p><code>/\*{1}\n</code></p>' ),
		);
	}

	/**
	 * Runs o2_Read_API::preview() and returns the HTML it sends.
	 *
	 * @return string Preview HTML.
	 */
	private function get_preview(): string {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_die_handler' ) );

		$response = null;

		ob_start();
		try {
			o2_Read_API::preview();
		} catch ( WPDieException $e ) {
			$response = json_decode( (string) ob_get_contents(), true );
		} finally {
			ob_end_clean();
		}

		$this->assertTrue( $response['success'] ?? false );

		return $response['data'];
	}

	/**
	 * Returns the wp_die() handler for Ajax requests.
	 *
	 * @return callable Die handler.
	 */
	public function get_die_handler(): callable {
		return array( $this, 'die_handler' );
	}

	/**
	 * Turns wp_die() into an exception so the test keeps running.
	 *
	 * @throws WPDieException Always.
	 */
	public function die_handler(): void {
		throw new WPDieException();
	}
}
