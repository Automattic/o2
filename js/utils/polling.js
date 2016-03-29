var o2 = o2 || {};

o2.Polling = ( function( $ ) {
	/*
	 * Methods used to control polling are all contained within this object.
	 * @todo Add websocket support
	 */
	return {
		cache: [],

		/*
		 * Make a request to the polling endpoint.
		 */
		poll: function() {
			var data = {
				queryVars: o2.options.queryVars,
				since: o2.options.loadTime,
				rando: Math.random(),
				scripts: o2.options.scripts.join(),
				styles: o2.options.styles.join(),
				postId: o2.options.postId
			};

			o2.Events.doAction( 'poll-request.o2', data );

			o2.options.currentRequest = Date.now();
			$.ajax( {
				method: 'POST',
				dataType:  'json',
				url:       o2.options.readURL + '&method=poll',
				xhrFields: {
					withCredentials: true
				},
				data: data,
				success: function( response ) {
					if ( response.success && response.data ) {
						response = response.data;
					} else {
						return;
					}

					o2.Events.doAction( 'poll-response.o2', response );

					// Update our nonce
					if ( 'undefined' !== typeof response.newNonce ) {
						o2.options.nonce = response.newNonce;
					}

					// Update our logged in state
					if ( 'undefined' === typeof o2.lastKnownLoggedInState ) {
						o2.lastKnownLoggedInState = ( o2.currentUser.userLogin.length !== 0 );
					}
					if ( 'undefined' !== typeof response.loggedIn ) {
						// If we were logged in at first, update the app on our current condition
						if ( o2.lastKnownLoggedInState !== response.loggedIn ) {
							o2.App.onLoggedInStateChange( response.loggedIn );
						}

						o2.lastKnownLoggedInState = response.loggedIn;
					}

					// Consume the data
					if ( 'undefined' !== typeof response.data && response.data.length ) {
						// Next poll starts from now, since we got current data
						o2.options.loadTime = o2.options.currentRequest;
						// Add polled data to cache
						o2.Polling.cache = o2.Polling.cache.concat( response.data );
					} else {
						// Nothing new, next check will be from previous time again
						o2.options.currentRequest = null;
					}

					// Process scripts and styles
					if ( 'undefined' !== typeof response.scripts && response.scripts.length ) {

						$( response.scripts ).each( function() {
							o2.options.scripts.push( this.handle );

							// Output extra data, if present
							if ( this.extra_data ) {
								var data = document.createElement( 'script' ),
									dataContent = document.createTextNode( '//<![CDATA[ \n' + this.extra_data + '\n//]]>' );

								data.type = 'text/javascript';
								data.appendChild( dataContent );

								document.getElementsByTagName( this.footer ? 'body' : 'head' )[0].appendChild( data );
							}

							// Build script tag and append to DOM in requested location
							var script = document.createElement( 'script' );
							script.type = 'text/javascript';
							script.src = this.src;
							script.id = this.handle;
							document.getElementsByTagName( this.footer ? 'body' : 'head' )[0].appendChild( script );
						} );
					}

					if ( 'undefined' !== typeof response.styles && response.styles.length ) {
						$( response.styles ).each( function() {
							if ( 'undefined' === typeof this.src ) {
								return;
							}

							o2.options.styles.push( this.handle );

							// Build link tag
							var style = document.createElement( 'link' );
							style.rel = 'stylesheet';
							style.href = this.src;
							style.id = this.handle + '-css';

							// @todo Handle IE conditionals

							// Append link tag if necessary
							if ( style ) {
								document.getElementsByTagName('head')[0].appendChild(style);
							}
						} );
					}

					// Check to see if we can process the cache
					var okToProcessCache = true;

					if ( 'undefined' === typeof o2.Polling.cache || 0 === o2.Polling.cache.length ) {
						// don't process if there is nothing to process
						okToProcessCache = false;
					} else if ( $( '.o2-editor-text' ).is( ':focus' ) ) {
						// don't process if someone is editing something - avoids screen moving around on people
						okToProcessCache = false;
					} else if ( o2.App.appState.saveInProgress ) {
						// don't process if we are in the middle of saving something - avoids duplicate comments appearing briefly
						okToProcessCache = false;
					}

					if ( okToProcessCache ) {
						data = o2.Polling.cache;
						o2.Polling.cache = [];
						var htmlAdded = '';

						// Add it to the collection (auto-model enabled)
						for ( var m = 0, dl = data.length; m < dl; m++ ) {
							// ask the app to add (or update) the item
							if ( 'post' === data[m].type ) {
								if ( data[m].isTrashed ) {
									o2.App.removePost( data[m] );
								} else {
									o2.App.addPost( data[m] );
									htmlAdded += data[m].contentFiltered;
								}
							} else {
								if ( data[m].isTrashed && ! data[m].hasChildren ) {
                                    o2.App.removeComment( data[m] );
								} else {
									o2.App.addComment( data[m] );
									htmlAdded += data[m].contentFiltered;
								}
							}
						}

						// Run triggers after cache is processed.  o2s don't load mejs, but infinite
						// scroll expects html to be passed with the event trigger.
						o2.$body.trigger( 'pd-script-load' ).trigger( 'post-load', { 'html' : htmlAdded } );
					}
					o2.Events.doAction( 'poll-response-processed.o2' );
					o2.options.poller = setTimeout( o2.Polling.poll, o2.options.pollingInterval );
				},
				error: function() {
					o2.options.poller = setTimeout( o2.Polling.poll, o2.options.pollingInterval );
				}
			} );
		},

		/*
		 * Turn on polling, which will happen according to the pollingInterval
		 */
		start: function( period ) {
			if ( undefined === period ) {
				period = o2.options.pollingInterval;
			} else {
				o2.options.pollingInterval = period; // Change default to last used
			}

			o2.Events.doAction( 'poll-start.o2' );

			clearTimeout( o2.poller ); // just in case start was called twice in a row
			o2.options.poller = setTimeout( o2.Polling.poll, period );
		},

		/*
		 * Stop polling the endpoint. Use o2.Polling.start() to turn it back on.
		 */
		stop: function() {
			o2.Events.doAction( 'poll-stop.o2' );
			clearTimeout( o2.options.poller );
			o2.Polling.cache = [];
		}
	};
} )( jQuery );
