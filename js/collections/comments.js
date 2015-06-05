/*
 * Collections.Comments contains a single Post and all related Comments
 * from WP. The Collection is "flat", the Comments View handles threading things properly.
 */

var o2 = o2 || {};

o2.Collections = o2.Collections || {};

o2.Collections.Comments = ( function( $, Backbone ) {
	return Backbone.Collection.extend( {
		model: o2.Models.Comment,

		comparator: function( m1, m2 ) {
			var comment1Date = m1.get( 'commentCreated' );
			if ( 'undefined' === typeof comment1Date ) {
				return 1;
			}

			var comment2Date = m2.get( 'commentCreated' );
			if ( 'undefined' === typeof comment2Date ) {
				return -1;
			}

			var date1 = parseInt( comment1Date, 10 );
			var date2 = parseInt( comment2Date, 10 );
			return date1 < date2 ? -1 : 1;
		}
	} );
} )( jQuery, Backbone );
