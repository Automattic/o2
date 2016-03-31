var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.ToDo = ( function( $ ) {
	return wp.Backbone.View.extend( {
		model: o2.Models.ToDo,

		tagName: 'li',

		defaults: function() {
			return {};
		},

		initialize: function( options ) {
			this.options = _.extend( this.defaults, options );
		},

		render: function() {
			var template = o2.Utilities.Template( 'extend-resolved-posts-resolved-post' );
			this.$el.html( template( this.model.toJSON() ) ).show();

			this.$( '.o2-timestamp' ).each( function() {
				o2.Utilities.timestamp( $( this ) );
			} );

			return this;
		}
	} );
} )( jQuery );
