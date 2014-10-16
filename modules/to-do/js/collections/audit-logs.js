var o2 = o2 || {};

o2.Collections = o2.Collections || {};

o2.Collections.AuditLogs = ( function( $, Backbone ) {
	return Backbone.Collection.extend( {
		model: o2.Models.AuditLog,

		comparator: function( m1, m2 ) {
			return o2.Utilities.compareTimes( m1.get( 'timestamp' ), m2.get( 'timestamp' ) );
		}
	} );
} )( jQuery, Backbone );
