/* global wp: true */
if ( 'undefined' === typeof wp ) {
	wp = {};
}

wp.locale = function( translations ) {
	this.date = function( format, date ) {
		if ( 'undefined' === typeof date ) {
			date = new Date();
		}
		var returnStr = '';
		var replace = this.replaceChars;
		var backslashCount = 0;
		for ( var i = 0; i < format.length; i++ ) {
			var curChar = format.charAt( i );
			if ( '\\' === curChar ) {
				backslashCount++;
				if ( 2 === backslashCount ) {
					returnStr += '\\';
					backslashCount = 0;
				}
				continue;
			}

			if ( replace[ curChar ] && 0 === backslashCount ) {
				returnStr += replace[ curChar ].call( date );
			} else {
				returnStr += curChar;
			}

			if ( '\\' !== curChar ) {
				backslashCount = 0;
			}
		}
		return returnStr;
	};

	this.parseISO8601 =  function( iso8601 ) {
		var regexp = /(\d\d\d\d)(-)?(\d\d)(-)?(\d\d)(T)?(\d\d)(:)?(\d\d)(:)?(\d\d)(\.\d+)?(Z|([+-])(\d\d)(:)?(\d\d))/;

		var matches = iso8601.match( new RegExp( regexp ) );
		if ( ! matches ) {
			return null;
		}
		var offset = 0;

		var date = new Date();

		date.setUTCDate( 1 );
		date.setUTCFullYear( parseInt( matches[1], 10 ) );
		date.setUTCMonth( parseInt( matches[3], 10 ) - 1 );
		date.setUTCDate( parseInt( matches[5], 10 ) );
		date.setUTCHours( parseInt( matches[7], 10 ) );
		date.setUTCMinutes( parseInt( matches[9], 10 ) );
		date.setUTCSeconds( parseInt( matches[11], 10 ) );
		if ( matches[12] ) {
			date.setUTCMilliseconds( parseFloat( matches[12] ) * 1000 );
		}
		if ( 'Z' !== matches[13] ) {
			offset = ( matches[15] * 60 ) + parseInt( matches[17], 10 );
			offset *= ( ( matches[14] === '-' ) ? -1 : 1 );
			date.setTime( date.getTime() - offset * 60 * 1000 );
		}
		return date;
	};

	var key;
	for ( key in translations ) {
		this[ key ] = translations[ key ];
	}

	var shortMonths = this.monthabbrev,
		longMonths  = this.month,
		shortDays   = this.weekdayabbrev,
		longDays    = this.weekday;

	this.replaceChars = {

		// Day
		d: function() { return ( this.getDate() < 10 ? '0' : '' ) + this.getDate(); },
		D: function() { return shortDays[this.getDay()]; },
		j: function() { return this.getDate(); },
		l: function() { return longDays[this.getDay()]; },
		N: function() { return this.getDay() + 1; },
		S: function() { return ( this.getDate() % 10 === 1 && this.getDate() !== 11 ? 'st' : ( this.getDate() % 10 === 2 && this.getDate() !== 12 ? 'nd' : ( this.getDate() % 10 === 3 && this.getDate() !== 13 ? 'rd' : 'th' ) ) ); },
		w: function() { return this.getDay(); },
		z: function() { return 'Not Yet Supported'; },
		// Week
		W: function() { return 'Not Yet Supported'; },
		// Month
		F: function() { return longMonths[this.getMonth()]; },
		m: function() { return ( this.getMonth() < 9 ? '0' : '' ) + ( this.getMonth() + 1 ); },
		M: function() { return shortMonths[this.getMonth()]; },
		n: function() { return this.getMonth() + 1; },
		t: function() { return 'Not Yet Supported'; },
		// Year
		L: function() { return 'Not Yet Supported'; },
		o: function() { return 'Not Supported'; },
		Y: function() { return this.getFullYear(); },
		y: function() { return ( '' + this.getFullYear() ).substr( 2 ); },
		// Time
		a: function() { return this.getHours() < 12 ? 'am' : 'pm'; },
		A: function() { return this.getHours() < 12 ? 'AM' : 'PM'; },
		B: function() { return 'Not Yet Supported'; },
		g: function() { return this.getHours() % 12 || 12; },
		G: function() { return this.getHours(); },
		h: function() { return ( ( this.getHours() % 12 || 12 ) < 10 ? '0' : '' ) + ( this.getHours() % 12 || 12 ); },
		H: function() { return ( this.getHours() < 10 ? '0' : '' ) + this.getHours(); },
		i: function() { return ( this.getMinutes() < 10 ? '0' : '' ) + this.getMinutes(); },
		s: function() { return ( this.getSeconds() < 10 ? '0' : '' ) + this.getSeconds(); },
		// Timezone
		e: function() { return 'Not Yet Supported'; },
		I: function() { return 'Not Supported'; },
		O: function() { return ( this.getTimezoneOffset() < 0 ? '+' : '-' ) + ( this.getTimezoneOffset() / 60 < 10 ? '0' : '' ) + ( this.getTimezoneOffset() / 60 ) + '00'; },
		P: function() { return ( this.getTimezoneOffset() < 0 ? '+' : '-' ) + ( this.getTimezoneOffset() / 60 < 10 ? '0' : '' ) + ( this.getTimezoneOffset() / 60 ) + ':00'; },
		T: function() { return 'Not Yet Supported'; },
		Z: function() { return this.getTimezoneOffset() * 60; },
		// Full Date/Time
		c: function() { return 'Not Yet Supported'; },
		r: function() { return this.toString(); },
		U: function() { return this.getTime() / 1000; }
	};
};
