var StickyPostsExtendsCollectionsPosts = ( function() {
	return {
		actions: {
			initSticky: {
				handle: 'initSticky',
				on: 'initialize',
				doThis: function( args ) {
					var collection = args.collection;
					collection.comparison = 'sticky';
					collection.on( 'change-sticky', collection.changedSticky, collection );
				},
				priority: 10
			}
		},

		comparators: {
			sticky: function( post1, post2 ) {
				if ( post1.isSticky() === post2.isSticky() ) {
					var orderSign = ( 'DESC' === o2.options.order ) ? -1 : 1;
					return orderSign * o2.Utilities.compareTimes( post1.get( 'unixtime' ), post2.get( 'unixtime' ) );
				} else if ( post1.isSticky() ) {
					return -1;
				}
				return 1;
			}
		},

		// When stickiness is changed, we just remove it from the collection
		// and then add it back in, which triggers a resort and rerender.
		changedSticky: function( model ) {
			this.remove( model, { animate: false } ); // triggers removal of the view without animation
			this.add( model ); // will be added in the correct order based on comparator()
		}
	};
} )();

Cocktail.mixin( o2.Collections.Posts, StickyPostsExtendsCollectionsPosts );
