var o2 = o2 || {};

o2.Offline = {
	windowUnloading: false,

	init: function() {

		jQuery( document ).ajaxError( function( event, jqxhr, settings ) {
			if ( settings.url.indexOf( 'action=o2_read' ) !== -1 ) {
				// We add a somewhat long delay since the error handler may fire
				// before a window unload that caused it.  This gives us
				// time to capture the unload first and set the windowUnloading flag
				setTimeout( function() {
					o2.Offline.onConnectionDown();
				}, 5000 );
			}
		} );

		jQuery( document ).ajaxSuccess( function( event, jqxhr, settings ) {
			if ( settings.url.indexOf( 'action=o2_read' ) !== -1 ) {
				o2.Offline.onConnectionUp();
			}
		} );

		// beforeunload is not triggered on all browsers, but gives the
		// best experience on those that do
		jQuery( window ).on( 'beforeunload', function() {
			o2.Offline.onWindowUnloading();
		} );

		jQuery( window ).unload( function() {
			o2.Offline.onWindowUnloading();
		} );
	},

	onConnectionDown: function() {
		if ( o2.Offline.windowUnloading ) {
			return;
		}

		var firstWithLostType = o2.Notifications.notifications.findFirst( 'connectionlost' );

		// If there is no connectionlost notification, add one
		if ( 'undefined' === typeof firstWithLostType ) {
			o2.Notifications.add( {
				text: o2.strings.connectionLostPrompt,
				url: false,
				type: 'connectionlost',
				sticky: true,
				popup: true,
				dismissable: false
			} );
		}
	},

	onConnectionUp: function() {
		var firstWithLostType = o2.Notifications.notifications.findFirst( 'connectionlost' );

		if ( 'undefined' !== typeof firstWithLostType ) {
			firstWithLostType.destroy();
		}
	},

	onWindowUnloading: function() {
		// Avoid false alerts when reloading
		o2.Offline.windowUnloading = true;
	}
};
