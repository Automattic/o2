/* global console */
var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.Base = ( function( Backbone ) {
	return Backbone.Model.extend( {
		/*
		 * Custom sync backend to handle writes back to WP.
		 * Converts all writes to POST, and sends them to a single
		 * AJAX endpoint that handles things based on the 'method' param
		 */
		sync: function( method, model, options ) {
			// Set up defaults
			var params = {
				url:      o2.options.readURL,
				type:     'GET',
				dataType: 'json',
				data: {
					method:  method,
					nonce:   o2.options.nonce,
					scripts: o2.options.scripts,
					styles:  o2.options.styles
				}
			};
			var now = Math.round( +new Date() / 1000 );
			model.attributes.unixtimeModified = now; // don't trigger a 'change'

			// Record event
			var hook = method;
			if ( 'patch' === method && 'undefined' !== typeof options.attrs && 'undefined' !== typeof options.attrs.pluginData && 'undefined' !== typeof options.attrs.pluginData.callback ) {
				hook = options.attrs.pluginData.callback;
			}

			var priority = o2.Events.addEvent(
				hook,
				{
					method: method,
					model: model,
					options: options
				}
			);

			// Write requests go to a different URL, and use POST
			if ( 'read' !== method ) {
				params.type = 'POST';
				params.url  = o2.options.writeURL;
			}

			// This is the Message we're actually working with
			var attribs = _.clone( model.attributes );

			// Always send a current unixtimeModified
			attribs.unixtimeModified = now;

			switch ( method ) {
			case 'read':
				// Read from either Websockets or via Polling endpoint
				console.log( 'Not Implemented' );
				break;

			case 'create':
				// POST to AJAX and create a new post/comment
				params.data.message = JSON.stringify( attribs );
				if ( attribs.isFollowing ) {
					params.data.post_subscribe = 'post_subscribe';
				}
				if ( o2.options.followingBlog ) {
					params.data.subscribe_blog = 'subscribe';
				}
				break;

			case 'update':
				// POST back to AJAX endpoint to update post/comment
				params.data.message = JSON.stringify( attribs );
				break;

			case 'patch':
				// POST back to AJAX endpoint to patch post
				var patch = options.attrs;

				// Add essential model properties to passed attrs
				patch.postID = attribs.id;
				patch.type = attribs.type;
				patch.unixtimeModified = now;
				params.data.message = JSON.stringify( patch );

				break;

			case 'delete':
				// POST to AJAX and delete post/comment
				// @todo Test; not currently exposed via UI
				params.data.message = JSON.stringify( {
					type:   attribs.type,
					postID: attribs.postID
				} );

				// Delete these since they do not need to be in the delete request.
				delete params.data.scripts;
				delete params.data.styles;

				break;
			}

			// Copied from BB's core sync
			var success = options.success;
			options.success = function( data, textStatus, xhr ) {
				model.parse( data );
				if ( success ) {
					success( data, textStatus, xhr );
				}
			};

			var error = options.error;
			options.error = function( model, xhr, options ) {
				if ( error ) {
					error( model, xhr, options );
				}
			};

			// We go ahead and turn on withCredentials in case the ajax writeURL
			// is not the same protocol (e.g. https) as the page itself (http).
			// This can happen if force_ssl_admin is set to true but the page
			// was accessed using http.
			options.xhrFields = options.xhrFields || {};
			options.xhrFields.withCredentials = true;
			// Make the request, allowing the user to override any Ajax options.
			var xhr = options.xhr = Backbone.ajax( _.extend( params, options ) );

			// Wait for previous events to clear
			var elapsed = 0;
			var check = setInterval( function() {
				if ( o2.Events.hasPriorEvents( hook, priority ) && elapsed < 30 ) {
					elapsed = elapsed + 2;
				} else {
					clearInterval( check );
					o2.Events.removeEvent( hook, priority );
					model.trigger( 'request', model, xhr, options );
				}
			}, 2000 );
			return xhr;
		},

		/**
		 * Parse the response from WordPress, which uses the core format of
		 * success+data in an object.
		 */
		parse: function( resp ) {
			if ( 'object' === typeof resp && 'data' in resp ) {
				return resp.data;
			}
			return resp;
		}
	} );
} )( Backbone );
