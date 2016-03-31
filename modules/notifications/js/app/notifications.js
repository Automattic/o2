var o2 = o2 || {};

o2.Routers = o2.Routers || {};

o2.Routers.Notifications = ( function( $, Backbone ) {
	return Backbone.Router.extend( {

		initialize: function() {

			// Acquire bootstrap data if available
			var bootstrap = [];
			var bootstrapEl = $( '.o2-notifications-data' );
			if ( bootstrapEl.length > 0 ) {
				_.each( $.parseJSON( bootstrapEl.text() ), function( fragment ) {
					bootstrap.push( fragment );
				} );
			}

			// Set up notifications collection and display bootstrapped messages
			var notifications = new o2.Collections.Notifications( bootstrap );

			// Set up the dock
			var dockView = new o2.Views.Dock( {
				collection: notifications
			} );

			// Set up the flash area
			var flashView = new o2.Views.Flash( {
				collection: notifications
			} );

			// Quell notifications until activated by o2
			notifications.closeNotifications();

			this.notifications = notifications;
			this.dockView = dockView;
			this.flashView = flashView;
		},

		add: function( options, collection ) {
			if ( 'undefined' === typeof collection ) {
				collection = o2.Notifications.notifications;
			}

			if ( collection.isOpen() ) {
				// if the collection already contains an item that points to this URL, don't add another
				if ( 'undefined' !== typeof options.url ) {
					var foundNotification = collection.findWhere( { url: options.url } );
					if ( 'undefined' !== typeof foundNotification ) {
						return;
					}
				}

				var notification = new o2.Models.Notification( options );
				collection.add( notification );
			}
		},

		close: function( collection ) {
			if ( 'undefined' === typeof collection ) {
				collection = o2.Notifications.notifications;
			}
			collection.closeNotifications();
		},

		open: function( collection ) {
			if ( 'undefined' === typeof collection ) {
				collection = o2.Notifications.notifications;
			}
			collection.openNotifications();
		}
	} );
} )( jQuery, Backbone );

jQuery( document ).on( 'preload.o2', function( bootstrap ) {
	o2.Notifications = o2.Notifications || new o2.Routers.Notifications( bootstrap );
} );

jQuery( document ).on( 'ready.o2', function() {
	o2.Notifications.open();
} );
