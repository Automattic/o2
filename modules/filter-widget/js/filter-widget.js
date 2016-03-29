// listen to selections from the widget and send
// the event to the o2 app using o2.Events.doAction( hook, args )

var o2 = o2 || {};

( function( $ ) {
	$( document ).ready( function() {
		var o2FilterWidgetList = $( '.o2-filter-widget-list' );
		var o2FilterWidgetSelect = $( '.o2-filter-widget-select' );

		// Listen for clicks on the list (if present)
		o2FilterWidgetList.click( function( event ) {
			event.preventDefault();

			if ( 'undefined' !== typeof o2.Events.doAction ) {
				o2.Events.doAction( 'filter-posts.o2', {
					'action' : $( event.target ).data( 'key' ),
					'url'    : $( event.target ).data( 'url' )
				} );
			}
		} );

		// Listen for changes on the select (if present)
		o2FilterWidgetSelect.on( 'change', function() {
			var selectedOption = $( '.o2-filter-widget-select' ).find( ':selected' );

			if ( 'undefined' !== typeof o2.Events.doAction ) {
				o2.Events.doAction( 'filter-posts.o2', {
					'action' : selectedOption.data( 'key' ),
					'url'    : selectedOption.data( 'url' )
				} );
			}
		} );

		// Listen for our events
		// @todo change to o2.options.appContainer
		$( '#content' ).on( 'filter-posts.o2', function( event, data ) {
			// @todo have o2.App do something with the data.action without reloading the page
			window.location.href = data.url;
		} );

	} );
} ) ( jQuery );
