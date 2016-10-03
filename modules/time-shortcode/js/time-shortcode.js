var o2_parse_date = function ( text ) {
	var m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})\+00:00$/.exec( text );
	return new Date( Date.UTC( +m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6] ) );
}

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
