var o2 = o2 || {};

o2.Socket = ( function ( $ ) {
	lasagna = null;

	/*
	 * Methods used to control the Lasagna client are all contained within this object.
	 */
	return {
		/*
		 * Setup the Lasagna client
		 */
		setup: function () {
			var jwtFetcher = function ( jwtType, params ) {
				$.ajax( {
					method: 'POST',
					dataType: 'json',
					url: o2.options.lasagnaJwtURL,
					xhrFields: {
						withCredentials: true,
					},
					data: { jwt_type: jwtType, payload: params },
					success: function ( response ) {
						console.log( response );
					},
					error: function () {
						return false;
					},
				} );
			};

			lasagna = new Lasagna( jwtFetcher );
		},

		/*
		 * Connect client to Lasagna webservice
		 */
		connect: function ( period ) {
			lasagna
				.initSocket(
					{ jwt: getCurrentUserLasagnaJwt( store.getState() ) },
					{
						onOpen: function () {
							console.log( 'socket opened' );
						},
						onClose: function ( reason ) {
							console.log( 'socket closed', reason );
						},
						onError: function ( reason ) {
							console.log( 'socket error', reason );
						},
					}
				)
				.then( lasagna.connect() );

			o2.Events.doAction( 'socketConnect.o2' );
		},

		/*
		 * Disconnect client from Lasagna webservice
		 */
		disconnect: function () {
			lasagna.disconnect( function () {
				console.log( 'socket disconnected' );
			} );
			o2.Events.doAction( 'socketDisconnect.o2' );
		},
	};
} )( jQuery );
