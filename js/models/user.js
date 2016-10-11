var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.User = ( function( Backbone ) {
	return Backbone.Model.extend( {
		defaults: function() {
			return {
				userLogin    : '',
				displayName  : '',
				userNicename : '',
				url          : '',
				urlTitle     : '',
				hash         : '00000000000000000000000000000000',
				modelClass   : '',
				avatar       : '',
				avatarSize   : 100
			};
		},

		initialize: function( options ) {
			options = options || {};
			if ( ! o2.options.showAvatars ) {
				options.avatar = false;
				options.avatarSize = false;
			}
		}
	} );
} )( Backbone );
