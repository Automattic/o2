var StickyPostsExtendsViewsPosts = ( function( $, Backbone ) {
	return {
		initialize: function( options ) {
			this.listenTo( this.collection, 'change-sticky', this.changedSticky );
		},

		changedSticky: function( model, options ) {
			if ( options.scroll ) {
				$( 'html, body' ).animate( {
					scrollTop: $( '#post-' + model.get( 'id' ) ).offset().top - 50
				}, 1000 );
			}
		}
	};
} )( jQuery, Backbone );

Cocktail.mixin( o2.Views.Posts, StickyPostsExtendsViewsPosts );
