<?php

class TagsTest extends WP_UnitTestCase {

	function test_tag_links_keeps_encoded_html_as_text() {
		$term = wp_insert_term( 'security-regression', 'post_tag' );
		$this->assertFalse( is_wp_error( $term ) );

		$content = '<strong>Allowed markup</strong> &lt;xss-probe data-marker=&quot;unsafe&quot;&gt; #security-regression';
		$result  = o2_Tags::tag_links( $content );

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $result );
		libxml_use_internal_errors( false );

		$this->assertEquals( 0, $dom->getElementsByTagName( 'xss-probe' )->length );
		$this->assertEquals( 1, $dom->getElementsByTagName( 'a' )->length );
		$this->assertEquals( 1, $dom->getElementsByTagName( 'strong' )->length );
		$this->assertNotFalse( strpos( $result, '&lt;xss-probe' ) );
	}

	function test_tag_links_preserves_greater_than_boundary() {
		$term = wp_insert_term( 'boundary-regression', 'post_tag' );
		$this->assertFalse( is_wp_error( $term ) );

		$result = o2_Tags::tag_links( '&gt;#boundary-regression' );

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $result );
		libxml_use_internal_errors( false );

		$this->assertEquals( 1, $dom->getElementsByTagName( 'a' )->length );
	}
}
