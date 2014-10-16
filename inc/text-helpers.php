<?php

// Psuedo-Markdown: blockquote by starting a line with "> ", posts or comments
function o2_blockquote_text( $content ) {
	if ( stristr( $content, "\n<p>&gt; " ) ) {
		$content = preg_replace( '!\n<p>&gt; (.*?)</p>!sui', '<blockquote><p>\1</p></blockquote>', $content );
	}
	return $content;
}
add_filter( 'the_content', 'o2_blockquote_text', 20 );
add_filter( 'comment_text', 'o2_blockquote_text', 30 );
