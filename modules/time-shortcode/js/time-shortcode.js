/**
 * Get a nice, localized textual representation of a date
 *
 * @param date - date object
 * @return string
 **/
var o2_format_date = function ( date ) {

	var time_settings = o2_get_time_settings();

	var zero_prefix = function( n ) {
		return ( '00' + n ).slice( -2 );
	};

	timezone_offset = -date.getTimezoneOffset() / 60;
	if( timezone_offset >= 0 ) {
		timezone_offset = "+" + timezone_offset;
	}

	return  time_settings['days'][ date.getDay() ] + ", "
		+ time_settings['months'][ date.getMonth() ] + " "
		+ zero_prefix( date.getDate() ) + ", "
		+ date.getFullYear() + " "
		+ zero_prefix( date.getHours() ) + ":" + zero_prefix( date.getMinutes() )
		+ " UTC" + timezone_offset;
}
