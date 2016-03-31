var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.LiveCommentsWidgetItemView = ( function( $ ) {
	return wp.Backbone.View.extend( {
		model: o2.Models.Item,

		defaults: function() {
			return {
				template: ''
			};
		},

		initialize: function() {
		},

		makeLongWordsShort: function( content, limit ) {
			var contentArray = content.split( ' ' );
			for ( var i=0; i < contentArray.length; i++ ) {
				if ( contentArray[i].length > limit ) {
					contentArray[i] = contentArray[i].substr( 0, limit ) + '...';
				}
			}

			return contentArray.join( ' ' );
		},

		render: function() {
			var jsonifiedModel = this.model.toJSON();

			jsonifiedModel.title = this.makeLongWordsShort( jsonifiedModel.title, 15 );
			jsonifiedModel.author = o2.UserCache.getUserFor( this.model.attributes, 32 );

			var titleForItem = jsonifiedModel.title,
				titleTemplate;
			if ( 0 === jsonifiedModel.title.length ) {
				titleTemplate = o2.Utilities.Template( 'post' === jsonifiedModel.type ? 'live-untitled-post-title-template' : 'live-untitled-comment-title-template' );
				titleForItem = titleTemplate( jsonifiedModel );
			} else if ( 'comment' === jsonifiedModel.type ) {
				titleTemplate = o2.Utilities.Template( 'live-comment-title-template' );
				titleForItem = titleTemplate( jsonifiedModel );
			}
			jsonifiedModel.title = titleForItem;

			var template = o2.Utilities.Template( 'live-item-template' );

			this.$el.html( template( jsonifiedModel ) );

			// Format unixtime dates to localized date and time
			this.$el.find( '.o2-timestamp' ).each( function() {
				o2.Utilities.timestamp( $( this ) );
			} );

			return this;
		},

		remove: function() {
			this.$el.remove();
		}
	} );
} )( jQuery );
