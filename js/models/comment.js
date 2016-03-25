/*
 * Models.Comment is used to describe a Comment
 */

var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.Comment = ( function() {
	return o2.Models.Base.extend( {
		defaults: function() {
			return {
				'type': 'comment',
				'userLogin': '',
				'noprivUserName': '',
				'noprivUserHash': '',
				'noprivUserURL': '',
				'contentRaw': '',
				'contentFiltered': '',
				'mentionContext': '',
				'permalink': '',
				'unixtime': Math.round( +new Date() / 1000 ),
				'approved': true,
				'depth': 1 /* interestingly, WP uses a 1 based value for depth, not 0 based */
			};
		}
	} );
} )();
