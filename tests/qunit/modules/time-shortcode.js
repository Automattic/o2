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

QUnit.test( "o2_parse_date valid date", function( assert ) {

	var date_string = "2016-09-30T12:00:00+00:00";

	var date_object = o2_parse_date( date_string );

  	assert.ok( 
		date_object.toISOString().indexOf( "2016-09-30T12:00:00" ) != -1, 
		"Correct date value should be parsed on valid input" 
	);
});
