jQuery( document ).ready( function() {
	// Disable certain inputs on the discussion-settings page

	var elementsToDisable = [
		'#page_comments',
		'#comments_per_page',
		'#default_comments_page',
		'#comment_order'
	];

	jQuery.each( elementsToDisable, function( index, value ) {
		jQuery( value ).prop( 'disabled', true );
	} );
} );