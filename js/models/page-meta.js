var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.PageMeta = ( function( Backbone ) {
	return Backbone.Model.extend( {
		defaults: {
			pageTitle: ''
		}
	} );
} )( Backbone );
