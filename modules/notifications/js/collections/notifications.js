var o2 = o2 || {};

o2.Collections = o2.Collections || {};

o2.Collections.Notifications = ( function( $, Backbone ) {
	return Backbone.Collection.extend( {
		model: o2.Models.Notification,

		open: true,

		findFirst: function( type ) {
			return _.find( this.models, function( notification ) {
				return ( type === notification.get( 'type') );
			} );
		},

		findFirstAndDestroy: function ( type ) {
			var first = this.findFirst( type );

			if ( 'undefined' !== typeof first ) {
				first.destroy();
			}
		},

		openNotifications: function() {
			this.open = true;
		},

		closeNotifications: function() {
			this.open = false;
		},

		isOpen: function() {
			return ( this.open === true );
		},

		isClosed: function() {
			return ( ! this.isOpen() );
		}
	} );
} )( jQuery, Backbone );
