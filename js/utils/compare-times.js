var o2 = o2 || {};

o2.Utilities = o2.Utilities || {};

o2.Utilities.compareTimes = function( t1, t2 ) {
	if ( t1 === t2 ) {
		return 0;
	}
	return t1 < t2 ? -1 : 1;
};
