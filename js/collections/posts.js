/*
 * The Posts collection contains all the posts on the page (and their comments via nesting)
 */

var o2 = o2 || {};

o2.Collections = o2.Collections || {};

o2.Collections.Posts = ( function( $, Backbone ) {
	return Backbone.Collection.extend( {
		model: o2.Models.Post,

		comparison: 'post',

		actions: {
			initPost: {
				handle: 'initPost',
				on: 'initialize',
				doThis: function() {},
				priority: 1 // Default o2.Collection.Posts initialization
			}
		},

		comparators: {
			post: function( post1, post2 ) {
				var orderSign = ( 'DESC' === o2.options.order ) ? -1 : 1;
				return orderSign * o2.Utilities.compareTimes( post1.get( 'unixtime' ), post2.get( 'unixtime' ) );
			}
		},

		initialize: function( options ) {
			this.doAction( 'initialize', { options: options, collection: this } );
		},

		comparator: function( post1, post2 ) {
			return this.comparators[this.comparison]( post1, post2 );
		},

		doAction: function() {
			// Only doAction if there is an action
			var action = arguments[0];
			if ( 'undefined' === typeof action ) {
				return;
			}

			// Handle priority
			var sortable = [];
			_.each( _.where( this.actions, { on: action } ), function( action ) {
				sortable.push( action );
			}, this );
			sortable.sort( function( a, b ) { return a.priority - b.priority; } );

			// Handle doThis
			for ( var i = 0; i < sortable.length; i++ ) {
				if ( 'function' === typeof( sortable[i].doThis ) ) {
					sortable[i].doThis( arguments[1] );
				}
			}
		}
	} );
} )( jQuery, Backbone );
