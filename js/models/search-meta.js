var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.SearchMeta = ( function( Backbone ) {
	return Backbone.Model.extend( {
		defaults: function() {
			return {
				invitation: '',
				lastQuery: ''
			};
		}
	} );
} )( Backbone );
