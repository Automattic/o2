var o2 = o2 || {};

o2.Collections = o2.Collections || {};

o2.Collections.Users = ( function( $, Backbone ) {
	return Backbone.Collection.extend( {

		model: o2.Models.User,

		userLoginRequestQueue: [],
		queueTimer: false,

		defaults: {
			userDataURL: ''
		},

		initialize: function( options ) {
			this.options = _.extend( this.defaults, options );
			_.bindAll( this, 'userDataCallback', 'ajaxGetUserData' );
		},

		/**
		  * getUserModel returns the user model (attributes only) for the given object (post, comment or currentUser),
		  * with the avatar (url) and avatarSize added for easy passing to templates
		  *
		  * If not in the cache, and not nopriv, it requests it from the server and passes a temporary
		  * model back to the caller
		  */
		getUserFor: function( object, avatarSize ) {
			// construct from userLogin if present
			var user;

			if ( 'undefined' !== typeof object.userLogin && object.userLogin && object.userLogin.length ) {
				user = this.findWhere( { userLogin: object.userLogin } );
				if ( 'undefined' === typeof user ) {
					// we don't have it, create an new one with some temporary values
					user = new o2.Models.User( {
						userLogin    : object.userLogin,
						userNicename : object.userNicename,
						displayName  : object.userLogin,
						modelClass   : 'o2-incomplete-' + object.userNicename
					} );

					// add it to the collection
					this.add( user );

					if ( this.options.userDataURL.length ) {
						// Add it to the queue
						this.userLoginRequestQueue.push( object.userLogin );
						// Reset the timer
						if ( this.queueTimer ) {
							clearTimeout( this.queueTimer );
						}
						this.queueTimer = setTimeout( this.ajaxGetUserData, 1000 );
					}
				}
			} else {
				// otherwise, construct it from nopriv attributes
				user = new o2.Models.User( {
					displayName : object.noprivUserName,
					url         : object.noprivUserURL,
					urlTitle    : object.noprivUserName,
					hash        : object.noprivUserHash
				} );
			}

			var userAttributes = _.clone( user.attributes );

			// Add the avatar info
			var defaultAvatar = ( 'undefined' !== typeof o2.options.defaultAvatar ) ? o2.options.defaultAvatar : 'identicon';
			userAttributes.avatar = 'https://gravatar.com/avatar/' + userAttributes.hash + '?d=' + defaultAvatar;
			userAttributes.avatarSize = avatarSize;

			return userAttributes;
		},

		ajaxGetUserData: function() {
			$.ajax( {
				dataType: 'json',
				url: this.options.userDataURL,
				xhrFields: {
					withCredentials: true
				},
				data: {
					action: 'o2_userdata',
					userlogins: this.userLoginRequestQueue
				},
				success: this.userDataCallback
			} );
			this.userLoginRequestQueue = [];
		},

		addOrUpdate: function( user ) {
			// Since it is possible this userLogin has already been added, and with a different ID,
			// we need to check for it before we simply add it
			var foundUser = this.findWhere( { userLogin: user.userLogin } );
			if ( 'undefined' === typeof foundUser ) {
				this.add( user );
			} else {
				foundUser.set( user );
			}

			// We can update incompletes for this user now
			this.updateIncompletes( user.userLogin );
		},

		userDataCallback: function( data ) {
			var that = this;
			_.each( data.data, function( datum ) {
				that.addOrUpdate( datum );
			} );
		},

		updateIncompletes: function( userLogin ) {
			// crawl the DOM and update tags that need the deets

			var user = this.getUserFor( { userLogin: userLogin } );

			// update img src's and a href's with .o2-incomplete-{userLogin}
			var selectorClass = 'o2-incomplete-' + user.userNicename;

			$( 'a.' + selectorClass ).each( function() {
				$( this ).attr( 'href', user.url ).removeClass( selectorClass );

				// if the anchor contains text child nodes which contain text equal to the userLogin,
				// update the text to the displayName -- we use filter to be careful not
				// to damage children that might be parented by the a
				$( this ).contents().filter( function() {
					if ( this.nodeType === Node.TEXT_NODE ) {
						this.nodeValue = this.nodeValue.replace( user.userLogin, user.displayName );
					}
				} );
			} );

			$( 'img.' + selectorClass ).each( function() {
				$( this ).attr( 'src', user.avatar ).removeClass( selectorClass );
			} );
		}

	} );
} )( jQuery, Backbone );

