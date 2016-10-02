var o2_parse_date = function ( text ) {
	var m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})\+00:00$/.exec( text );
	return new Date( Date.UTC( +m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6] ) );
}

var o2_format_date = function ( d ) {

	var ts = o2_get_time_settings();

	var p = function( n ) {
		return ( '00' + n ).slice( -2 );
	};

	var tz = -d.getTimezoneOffset() / 60;
	if ( tz >= 0 ) {
		tz = "+" + tz;
	}

	return "" + ts['days'][ d.getDay() ] + ", " + ts['months'][ d.getMonth() ] + " " + p( d.getDate() ) + ", " + d.getFullYear() + " " + p( d.getHours() ) + ":" + p( d.getMinutes() ) + " UTC" + tz;
}
