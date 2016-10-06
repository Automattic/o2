var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.ToDosWidget = ( function( $, Backbone ) {
	return Backbone.Model.extend( {
		defaults: function() {
			return {
				'widgetID': '',
				'currentPage': 1,
				'totalPages': 0,
				'postsPerPage': 5,
				'filterTags': '',
				'foundPosts': 0,
				'state': 'unresolved',
				'order': 'ASC'
			};
		}
	} );
} )( jQuery, Backbone );
