var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.LiveCommentsItem = ( function( $, Backbone ) {
	return Backbone.Model.extend( {
		defaults: function() {
			return {
				unixtime: Math.round( +new Date() / 1000 ),
				author: {},
				title: '',
				domRef: '',
				permalink: '',
				type: '',
				externalID: '',
				data : {}
			};
		}
	} );
} )( jQuery, Backbone );
