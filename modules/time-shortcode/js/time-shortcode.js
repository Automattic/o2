/* exported o2_format_date */
/* globals o2_get_time_settings */

/**
 * Get a nice, localized textual representation of a date
 *
 * @param date - date object
 * @return string
 **/
var o2_format_date = function( date, format ) {

	var time_settings = o2_get_time_settings();

	var zero_prefix = function( n ) {
		return ( '00' + n ).slice( -2 );
	};

	var timezone_offset = -date.getTimezoneOffset() / 60;
	if( timezone_offset >= 0 ) {
		timezone_offset = '+' + timezone_offset;
	}

	var full_date = time_settings.days[ date.getDay() ] + ', ' +
		time_settings.months[ date.getMonth() ] + ' ' +
		zero_prefix( date.getDate() ) + ', ' +
		date.getFullYear() + ' ' +
		zero_prefix( date.getHours() ) + ':' + zero_prefix( date.getMinutes() ) +
		' UTC' + timezone_offset;

	if ( typeof format === 'string' ) {
		var p = full_date.indexOf( 'UTC' );
		switch ( format ) {
			case 'time':
				return full_date.substr( p - 6 );

			case 'date':
				return full_date.substr( 0, p - 7 );

			case 'shortdate':
				var c = full_date.indexOf( ',' );
				return full_date.substr( c + 2, p - c - 9 );
		}
	}

	return full_date;
};
