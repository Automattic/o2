var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.LiveCommentsWidgetItemsView = ( function( $ ) {
	return wp.Backbone.View.extend( {
		collection: o2.Collections.LiveCommentsItems,

		defaults: function() {
			return {
				kind: 'comment',
				count: 10,
				lastItemTime : 0
			};
		},

		events: {
			'click a': 'onClick'
		},

		initialize: function( options ) {
			this.listenTo( this.collection, 'add', this.addOne );
			this.listenTo( this.collection, 'reset', this.render );

			this.options = this.defaults();
			this.options = _.extend( this.options, options );

			this.subviews = [];

			this.render();
		},

		onClick: function( event ) {
			var domRef = $( event.currentTarget ).data( 'domref' );
			if ( domRef ) {
				var domEl = $( domRef );

				// If the referenced element is found in the DOM, consume the event
				// and ask the application to do what it needs to get it visibile on the screen
				if ( domEl.length ) {
					event.preventDefault();
					o2.Events.doAction( 'scroll-to.o2', domRef );
				}
			}
		},

		render: function() {
			this.removeAll();
			this.addAll();
			return this;
		},

		removeAll: function( ) {
			this.pruneSubViews( 0 );
		},

		pruneSubViews: function( maxLength ) {
			while ( this.subviews.length > maxLength ) {
				var lastView = this.subviews.pop();
				lastView.remove();
			}
		},

		addOne: function( item ) {
			// is the item what we're supposed to be displaying? (comments, posts, or either)
			if ( 'both' === this.options.kind || item.get( 'type' ) === this.options.kind ) {

				// is it newer than anything we've seen so far?
				var itemTime = item.get( 'unixtime' );
				if ( itemTime > this.options.lastItemTime ) {
					this.options.lastItemTime = itemTime;

					var itemView = new o2.Views.LiveCommentsWidgetItemView( { model: item } );

					// Add the view to the top of the container
					this.$el.prepend( itemView.render().el );

					// Add the view to the top of the subview array
					this.subviews.unshift( itemView );

					// If we have more than this.options.count items in the subview array, prune
					if ( this.subviews.length > this.options.count ) {
						this.pruneSubViews( this.options.count );
					}
				}
			}
		},

		addAll: function() {
			this.collection.forEach( this.addOne, this );
		}
	} );
} )( jQuery );
