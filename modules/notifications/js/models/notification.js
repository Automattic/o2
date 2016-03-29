var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.Notification = ( function( $, Backbone ) {
	return Backbone.Model.extend( {
		defaults: function() {
			return {
				text: '',              // The message to display to the user; maps to chrome.notifications message
				url: '',               // (optional) URL to open or scroll to on click
				postID: 0,             // (optional) The post id associated with this notification
				type: 'notice',        // (optional) The type of notification: notice, error, or warning
				unixtime: Math.round( +new Date() / 1000 ), // (optional) The unixtime of the notification, defaults to now
				popup: false,          // (optional) Open the url in a new window
				sticky: false,         // (optional) Sticky notifications stay visible until clicked
				dismissable: true,     // (optional) Whether the user can dismiss this or not
				textClass: '',         // (optional) class to be added to the message to display
				iconClass: '',         // (optional) class to be added to the icon

				// Saving for later usage with chrome.notifications
				title: '',             // chrome.notifications string
				template: 'basic',     // chrome.notifications TemplateType: basic, image, list, or progress
				iconUrl: '',           // chrome.notifications string
				iconSize: 32           // (optional) The dimension for iconUrl
			};
		},

		initialize: function() {
			if ( this.isError() && '' === this.get( 'text' ) ) {
				this.set( 'text', o2.strings.defaultError );
			}

			// @todo This needs to be attached to the relevant model if possible; if the origin model is destroyed,
			//       the notification will point at something that doesn't exist
		},

		getPostID: function() {
			return this.get( 'postID' );
		},

		getText: function() {
			return this.get( 'text' );
		},

		getType: function() {
			return this.get( 'type' );
		},

		getUrl: function() {
			return this.get( 'url' );
		},

		hasUrl: function() {
			return ( '' !== this.getUrl );
		},

		hasPostID: function() {
			return ( 0 !== this.getPostID );
		},

		isDismissable: function() {
			return ( true === this.get( 'dismissable' ) );
		},

		isError: function() {
			return ( 'error' === this.getType() );
		},

		isNotice: function() {
			return ( 'notice' === this.getType() );
		},

		isPopup: function() {
			return ( true === this.get( 'popup' ) );
		},

		isSticky: function() {
			return ( true === this.get( 'sticky' ) );
		},

		isWarning: function() {
			return ( 'warning' === this.getType() );
		}
	} );
} )( jQuery, Backbone );
