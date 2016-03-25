var o2 = o2 || {};

o2.Utilities = o2.Utilities || {};

o2.Utilities.closeActionDropdowns = function() {
	// Raise an event on the o2 container to prompt post views to close open disclosures
	o2.Events.doAction( 'dropdown-actions:closeall.o2', {} );
};

jQuery( document ).click( function( event ) {
	o2.Utilities.closeActionDropdowns( event );
} ).on( 'touchend', function( event ) {
	o2.Utilities.closeActionDropdowns( event );
} );
