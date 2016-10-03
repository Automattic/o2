QUnit.module( 'Time Shortcode' );

//Set up the time settings function manually since PHP usually generates it
var o2_get_time_settings = function(){
	return { 
		"months": [
				"January",
				"February",
				"March",
				"April",
				"May",
				"June",
				"July",
				"August",
				"September",
				"October",
				"November",
				"December"
		],
		"days": [
				"Sunday",
				"Monday",
				"Tuesday",
				"Wednesday",
				"Thursday",
				"Friday",
				"Saturday"
		]
	};
}

/**
 * A spoof of the Date object
 * Use this instead of normal Date objects to have exact control over the timezone and other properties
 * @param weekday - integer day of the week (0-6)
 * @param day - integer day of the month (1-31)
 * @param month - integer month of the year (0-11)
 * @param year - integer 4-digit full year (e.g. 2016)
 * @param hour - integer hour of the day (0-23)
 * @param minute - integer minute of the hour (0-59)
 * @param timezone_offset - integer timezone offset in minutes (e.g. 480 )
 **/
function DateMock( weekday, day, month, year, hour, minute, timezone_offset ) {

	this.weekday = weekday;
	this.day = day;
	this.month = month;
	this.year = year;
	this.hour = hour;
	this.minute = minute;
	this.timezone_offset = timezone_offset;

	this.getDay = function(){
		return this.weekday;
	}
	this.getMonth = function(){
		return this.month;
	}
	this.getDate = function(){
		return this.day;
	}
	this.getFullYear = function(){
		return this.year;
	}
	this.getHours = function(){
		return this.hour;
	}
	this.getMinutes = function(){
		return this.minute;
	}
	this.getTimezoneOffset = function(){
		return this.timezone_offset;
	}
};

QUnit.test( "o2_format_date: valid gmt date", function( assert ) {

	var date = new DateMock( 1, 3, 9, 2016, 1, 30, 0 ); // Mon, 03 Oct 2016 01:30 UTC

	assert.equal(
		o2_format_date( date ), "Monday, October 03, 2016 01:30 UTC+0",
		"Correct date should return for GMC timezone"
	);

});

QUnit.test( "o2_format_date: valid, negative-offset date", function( assert ) {

	var date = new DateMock( 4, 22, 2, 2015, 20, 12, 480 ); // Thur, 22 Mar 2015 20:12 UTC-8

	assert.equal(
		o2_format_date( date ), "Thursday, March 22, 2015 20:12 UTC-8",
		"Correct date should return for negative UTC timezone"
	);
});

QUnit.test( "o2_format_date: valid, positive-offset date", function( assert ) {

	var date = new DateMock( 0, 12, 0, 2012, 12, 00, -120 ); // Sun, 12 Jan 2012 12:00 UTC+2

	assert.equal(
		o2_format_date( date ), "Sunday, January 12, 2012 12:00 UTC+2",
		"Correct date should return for positive UTC timezone"
	);
});
