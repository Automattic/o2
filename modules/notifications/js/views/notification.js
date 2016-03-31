var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.Notification = ( function( $ ) {
	return wp.Backbone.View.extend( {
		model: o2.Models.Notification,

		tagName: 'li',

		className: 'o2-notification',

		initialize: function() {
			this.model.on( 'change',  this.render, this );
			this.model.on( 'destroy', this.remove, this );
		},

		events: {
			'click a.o2-notification-link':  'onClickLink',
			'click a.o2-notification-close': 'onClickClose'
		},

		onClickLink: function( event ) {
			event.preventDefault();
			var url = this.model.getUrl();

			// @todo Make this a separate function for dealing with in-page/external links
			if ( '#' === url.substring( 0, 1 ) ) {
				o2.Events.doAction( 'scroll-to.o2', url );
				this.fadeAndDestroy();
			} else {
				if ( this.model.isPopup() ) {
					window.open( url, 'o2-popup', 'width=800,height=600,toolbar=0,menubar=0,location=0' );
				} else {
					window.open( url );
				}
			}
		},

		onClickClose: function( event ) {
			event.preventDefault();
			this.fadeAndDestroy();
		},

		fadeAndDestroy: function() {
			this.$el.fadeOut( 'fast', function() {
				this.model.destroy();
			}.bind( this ) );
		},

		render: function() {
			var template = o2.Utilities.Template( 'notification' );

			var jsonifiedModel = this.model.toJSON();
			this.$el.html( template( jsonifiedModel ) ).show();

			this.$( '.o2-timestamp' ).each( function() {
				o2.Utilities.timestamp( $( this ) );
			} );

			return this;
		},

		remove: function() {
			this.$el.remove();
		}
	} );
} )( jQuery );
