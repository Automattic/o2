var StickyPostsExtendsCollectionsPosts = ( function() {
	return {
		actions: {
			initRecentComments: {
				handle: 'initRecentComments',
				on: 'initialize',
				doThis: function( args ) {
					// Only change the comparison if this is a recent comments view
					if ( 'undefined' !== typeof o2.options.queryVars.o2_recent_comments ) {
						var collection = args.collection;
						collection.comparison = 'recentComments';
					}
				},
				priority: 11 // Must run after sticky-posts
			}
		},

		comparators: {
			recentComments: function( post1, post2 ) {
				return -o2.Utilities.compareTimes( _.max( post1.comments.pluck( 'unixtime' ) ), _.max( post2.comments.pluck( 'unixtime' ) ) );
			}
		}
	};
} )();

Cocktail.mixin( o2.Collections.Posts, StickyPostsExtendsCollectionsPosts );
