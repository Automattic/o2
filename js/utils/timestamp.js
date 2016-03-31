/* global moment */
var o2 = o2 || {};

o2.Utilities = o2.Utilities || {};

o2.Utilities.phpToMoment = function( s ) {
	var m = '';
	var lookBehind = '';
	for ( var i = 0; i < s.length; i++ ) {
		switch ( s.charAt( i ) ) {
			case 'd': // Day of the month with leading zeroes
				m += 'DD';
				break;
			case 'D': // Short 3-letter textual representation of a day
				m += 'ddd';
				break;
			case 'l': // Full textual representation of the day of the week
				m += 'dddd';
				break;
			case 'N': // ISO-8601 numeric representation of the day of the week
				m += 'E';
				break;
			case 'S': // English ordinal suffix for the day of the month (usually used with 'j')
				if ( lookBehind === 'j' ) {
					m += 'Do';
				}
				break;
			case 'w': // Numeric representation of the day of the week
				m += 'd';
				break;
			case 'W': // ISO-8601 week number of a year
				m += 'w';
				break;
			case 'F': // Full textual representation of a month
				m += 'MMMM';
				break;
			case 'm': // Numeric representation of a month with leading zeroes
				m += 'MM';
				break;
			case 'M': // Short 3-letter textual representation of a month
				m += 'MMM';
				break;
			case 'n': // Numeric representation of a month without leading zeroes
				m += 'M';
				break;
			case 'o': // ISO-8601 year number
				m += 'GGGG';
				break;
			case 'Y': // A full numeric representation of a year
				m += 'YYYY';
				break;
			case 'y': // A two-digit representation of a year
				m += 'YY';
				break;
			case 'g': // 12-hour format of an hour without leading zeroes
				m += 'h';
				break;
			case 'G': // 24-hour format of an hour without leading zeroes
				m += 'H';
				break;
			case 'h': // 12-hour format of an hour with leading zeroes
				m += 'hh';
				break;
			case 'H': // 24-hour format of an hour with leading zeroes
				m += 'HH';
				break;
			case 'i': // Minutes with leading zeroes
				m += 'mm';
				break;
			case 's': // Seconds with leading zeroes
				m += 'ss';
				break;
			case 'e': // Timezone identifier approximated with timezone offset
				m += 'Z';
				break;
			case 'O': // Difference to GMT in hours
				m += 'ZZ';
				break;
			case 'P': // Formatted difference to GMT in hours
				m += 'Z';
				break;
			case 'T': // Timezone abbreviation approximated with timezone offset
				m += 'Z';
				break;
			case 'c': // ISO-8601 date
				m += 'YYYY-MM-DDTHH:MM:ssZ';
				break;
			case 'r': // RFC 2822 formatted date
				m += 'ddd, DD MMM YYYY HH:MM:ss Z';
				break;
			case 'u': // Seconds since Unix Epoch
				m += 'X';
				break;

			// No translation needed
			case 'a': // Lowercase Ante meridiem and Post meridiem
			case 'A': // Uppercase Ante meridiem and Post meridiem
				m += s.charAt( i );
				break;

			// No equivalent
			case 'z': // The day of the year (starting from 0)
			case 't': // Number of days in the given month
			case 'L': // Leap year
			case 'B': // Swatch Internet time
			case 'u': // Microseconds
			case 'I': // Daylight savings time
			case 'Z': // Timezone offset in seconds
				break;

			// Handle with lookBehind to handle 'jS'
			case 'j': // Day of the month without leading zeroes
				break;

			default:
				if ( lookBehind === 'j' && s.charAt( i ) !== 'S' ) {
					m += 'D[' + s.charAt( i ) + ']';
				} else {
					m += '[' + s.charAt( i ) + ']';
				}
		}
		lookBehind = s.charAt( i );
	}

	return m;
};

o2.Utilities.removeTimestamp = function( e ) {
	e.removeAttr( 'data-unixtime' ).removeClass( 'o2-timestamp' );
};

o2.Utilities.timestamp = function( e ) {
	var then = e.data( 'unixtime' );
	var now = Math.round( +new Date() / 1000 );

	if ( undefined === then ) {
		return;
	}

	if ( then < 0 ) {
		then = now;
	}

	// Load Moment.js language
	moment.lang( 'en', o2.options.i18nMoment );

	// Set common unixtime and Date objects
	var dateThen = new Date( then * 1000 );
	var dateNow = new Date( now * 1000 );
	var dateYesterday = new Date( ( now - 60 * 60 * 24 ) * 1000 );

	var date = moment( dateThen ).format( o2.options.dateFormat );
	var time = moment( dateThen ).format( o2.options.timeFormat );

	// Set the human-readable title attribute of the timestamp if undefined
	if ( e.attr( 'title' ) === undefined ) {

		// Force a timezone offset if not included in timestampFormat
		var timeZoned = time;
		if ( ( o2.options.dateFormat.indexOf( 'Z' ) < 0 && o2.options.timeFormat.indexOf( 'Z' ) < 0 ) &&
				( o2.options.dateFormat.indexOf( 'ZZ' ) < 0 && o2.options.timeFormat.indexOf( 'ZZ' ) < 0 ) ) {
			timeZoned = time + ' (' + moment( dateThen ).format( 'Z' ) + ')';
		}

		var timestampFormat = o2.options.timestampFormat.replace( '%1$s', timeZoned ).replace( '%2$s', date );

		e.attr( 'title', timestampFormat );
	}

	// Set the relative time since of the timestamp
	var timeSince = now - then;

	// Comparison variables for today and yesterday
	var day = dateThen.getDate();
	var today = dateNow.getDate();
	var yesterday = dateYesterday.getDate();

	// Handle compact time formats
	var compact = false;
	if ( window.innerWidth < 600 ) {
		compact = true;
	}
	var compactAllowed = false;
	if ( e.data( 'compactAllowed' ) === true ) {
		compactAllowed = true;
	}

	// Compact versus long-form
	if ( compact && compactAllowed ) {

		// Just display the largest relative unit
		if ( timeSince < 60 ) {
			e.text( o2.options.compactFormat.seconds.replace( '%s', timeSince ) );

		} else if ( timeSince < 60 * 60 ) {
			e.text( o2.options.compactFormat.minutes.replace( '%s', Math.floor( timeSince / 60 ) ) );

		} else if ( timeSince < 60 * 60 * 24 ) {
			e.text( o2.options.compactFormat.hours.replace( '%s', Math.floor( timeSince / ( 60 * 60 ) ) ) );

		} else if ( timeSince < 60 * 60 * 24 * 7 ) {
			e.text( o2.options.compactFormat.days.replace( '%s', Math.floor( timeSince / ( 60 * 60 * 24 ) ) ) );

		} else if ( timeSince < 60 * 60 * 24 * 30 * 2 ) {
			e.text( o2.options.compactFormat.weeks.replace( '%s', Math.floor( timeSince / ( 60 * 60 * 24 * 7 ) ) ) );

		} else if ( timeSince < 60 * 60 * 24 * 365 ) {
			e.text( o2.options.compactFormat.months.replace( '%s', Math.floor( timeSince / ( 60 * 60 * 24 * 30 ) ) ) );

			o2.Utilities.removeTimestamp( e );
		} else {
			e.text( o2.options.compactFormat.years.replace( '%s', Math.floor( timeSince / ( 60 * 60 * 24 * 365 ) ) ) );

			o2.Utilities.removeTimestamp( e );
		}
	} else {

		if ( timeSince < 60 ) {
			e.text( o2.options.compactFormat.seconds.replace( '%s', timeSince ) );

		// Display a relative time today
		} else if ( timeSince < 60 * 60 * 24 && day === today ) {
			e.text( moment( dateThen ).fromNow() );

		// Display a time yesterday
		} else if ( timeSince < 60 * 60 * 24 * 3 && day === yesterday ) {
			e.text( o2.options.yesterdayFormat.replace( '%s', time ) );

		// Just display a time
		} else {
			e.text( o2.options.timestampFormat.replace( '%1$s', time ).replace( '%2$s', date ) );

			o2.Utilities.removeTimestamp( e );
		}
	}

	return e;
};
