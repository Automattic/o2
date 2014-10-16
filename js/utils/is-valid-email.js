var o2 = o2 || {};

o2.Utilities = o2.Utilities || {};

o2.Utilities.isValidEmail = function( email_address_to_validate ) {
	var regExp = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
	return regExp.test( email_address_to_validate );
};
