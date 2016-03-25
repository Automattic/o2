var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.AuditLog = ( function( $ ) {
	return wp.Backbone.View.extend( {
		model: o2.Models.AuditLog,

		tagName: 'li',

		defaults: function() {
			return {};
		},

		initialize: function( options ) {
			this.parent = options.parent;
			this.options = _.extend( this.defaults, options );
		},

		render: function() {
			var template = o2.Utilities.Template( 'extend-resolved-posts-audit-log' );

			var jsonifiedModel = this.model.toJSON();
			this.$el.html( template( jsonifiedModel ) ).show();

			this.$( '.o2-timestamp' ).each( function() {
				o2.Utilities.timestamp( $( this ) );
			} );

			return this;
		}
	} );
} )( jQuery );
