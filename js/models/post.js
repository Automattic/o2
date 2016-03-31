/*
 * Models.Post is used to describe a Post
 * Note that we are reserving a reference to a collection of comments
 */

var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.Post = ( function() {
	return o2.Models.Base.extend( {
		defaults: function() {
			var now = Math.round( +new Date() / 1000 );
			return {
				'titleRaw': '',
				'titleFiltered': '',
				'titleWasGeneratedFromContent': false,
				'type': 'post',
				'comments': {},
				'userLogin': '',
				'contentRaw': '',
				'contentFiltered': '',
				'mentionContext': '',
				'permalink': '',
				'unixtime': now,
				'unixtimeModified': now,
				'postID': 0,
				'cssClasses': '',
				'hasPrevPost': false,
				'prevPostURL': '',
				'prevPostTitle': '',
				'hasNextPost': false,
				'nextPostURL': '',
				'nextPostTitle': '',
				'commentsOpen': false,
				'postActions': '',
				'entryHeaderMeta': '',
				'showTitle': false,
				'linkPages': '',
				'footerEntryMeta': '',
				'postFormat': 'aside',
				'postMeta': {},
				'postTerms': {},
				'pluginData': {},
				'isPage': false
			};
		},

		initialize: function( options ) {
			options = options || {};
			if ( 'undefined' !== typeof options.comments ) {
				this.comments = new o2.Collections.Comments( options.comments );
			} else {
				this.comments = new o2.Collections.Comments();
			}
		}
	} );
} )();
