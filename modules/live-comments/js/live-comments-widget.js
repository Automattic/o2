var o2 = o2 || {};

o2.Routers = o2.Routers || {};

o2.Routers.LiveComments = ( function( $, Backbone ) {
	return Backbone.Router.extend( {
		initialize: function( options ) {
			this.options = options;
			this.listenTo( o2.Events.dispatcher, 'data-received.o2', this.dataReceived );

			// consume the bootstrap data, if present
			var liveBootstrap = [];
			var bootstrapEl = $( '.o2-live-widget-bootstrap-data' );
			if ( bootstrapEl.length ) {
				liveBootstrap = $.parseJSON( bootstrapEl.text() );
			}

			var masterCollection = new o2.Collections.LiveCommentsItems( liveBootstrap );

			// for each widget in the DOM
			$( '.o2-live-comments-container').each( function() {
				// read its data attributes to know what we are hooking up
				var itemKind = $( this ).data( 'o2-live-comments-kind' );
				var itemCount = $( this ).data( 'o2-live-comments-count' );

				new o2.Views.LiveCommentsWidgetItemsView( {
					el: $( this ),
					collection: masterCollection,
					kind: itemKind,
					count: itemCount
				} );
			} );

			this.masterCollection = masterCollection;
		},

		dataReceived: function( data ) {
			// data includes type (e.g. post, comment) and data

			// Reformat the data into an item model instance
			// Common elements first
			var itemHash = {
				unixtime:  data.data.unixtime,
				userLogin: data.data.userLogin,
				permalink: data.data.permalink,
				type:      data.type
			};

			// We accept posts and comments and nothing else
			if ( 'post' === data.type ) {
				itemHash.title      = data.data.titleRaw;
				itemHash.domRef     = '#post-' + data.data.postID;
				itemHash.externalID = data.data.postID;

				this.masterCollection.addOrUpdateItem( itemHash );
			}
			else if ( 'comment' === data.type ) {
				itemHash.title      = data.data.postTitleRaw;
				itemHash.domRef     = '#comment-' + data.data.id;
				itemHash.externalID = data.data.id;

				this.masterCollection.addOrUpdateItem( itemHash );
			}
		}
	} );
} )( jQuery, Backbone );

jQuery( document ).on( 'preload.o2', function() {
	o2.LiveComments = new o2.Routers.LiveComments();
} );
