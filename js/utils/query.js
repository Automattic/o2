var o2 = o2 || {};

o2.Query = ( function( $ ) {
	/*
	 * Utility for plugins to get data from the server
	 */
	return {
		/*
		 * Make a request to the query endpoint
		 */
		query: function( e ) {
			$.ajax( {
				dataType:  'json',
				url:       o2.options.readURL + '&method=query',
				xhrFields: {
					withCredentials: true
				},
				data: e.data,
				success: function( response ) {

					// Raise a targeted event with the data
					e.target.trigger( e.data.callback, response.data );
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					e.target.trigger( e.data.callback + '-error', {
						jqXHR: jqXHR,
						textStatus: textStatus,
						errorThrown: errorThrown
					} );
				}
			} );
		}
	};
} )( jQuery );
