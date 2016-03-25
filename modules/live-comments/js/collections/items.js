var o2 = o2 || {};

o2.Collections = o2.Collections || {};

o2.Collections.LiveCommentsItems = ( function( $, Backbone ) {
	return Backbone.Collection.extend( {
		model: o2.Models.LiveCommentsItem,

		comparator: function( item1, item2 ) {
			// the collection should have newer items at the end of the collection
			return o2.Utilities.compareTimes( item1.get( 'unixtime' ), item2.get( 'unixtime' ) );
		},

		addOrUpdateItem: function( itemHash ) {
			// Check and see if we already have one
			// Since post and comment IDs can overlap, we cannot use the postID and commentID directly as the
			// backbone model ID, so we have to use the type and the externalID here

			var foundItem = _.find( this.models, function( item ) {
				return ( ( item.type === itemHash.type ) && ( item.externalID === itemHash.externalID ) );
			} );

			if ( 'undefined' !== typeof foundItem ) {
				foundItem.set( itemHash );
			} else {
				if ( ! o2.options.isPreview ) {
					this.add( new o2.Models.LiveCommentsItem( itemHash ) );
				}
			}	
		}
	} );
} )( jQuery, Backbone );
