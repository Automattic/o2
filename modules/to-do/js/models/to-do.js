var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.ToDo = ( function() {
	return o2.Models.Base.extend( {
		defaults: function() {
			return {
				'title': '',
				'author': {},
				'excerpt': '',
				'permalink': '',
				'timestamp': Math.round( +new Date() / 1000 ),
				'commentCount': 0,
				'state': '',
				'widgetID': ''
			};
		}
	} );
} )();
