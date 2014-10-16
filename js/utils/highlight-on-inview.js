var o2 = o2 || {};

o2.Utilities = o2.Utilities || {};

( function( $ ) {
	o2.Utilities.HighlightOnInview = function( event, visible ) {
		if ( visible ) {
			$( this ).unbind( 'inview' );
			var originalBackgroundColor = $( this ).css( 'background-color' );
			$( this ).css( 'background-color', '#f6f3d1' );
			$( this ).animate( { 'background-color': originalBackgroundColor }, 3000 );
		}
	};
} )( jQuery );
