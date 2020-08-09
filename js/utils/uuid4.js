var o2 = o2 || {};

o2.Utilities = o2.Utilities || {};

/**
 * "good enough" uuid4
 * see: https://stackoverflow.com/a/2117523
 */
o2.Utilities.Uuidv4 = function () {
	var cryptoObj = window.crypto || window.msCrypto; // for IE 11
	var genChar = function(c) {
		return (c ^ cryptoObj.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16);
	};

	return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, genChar);
};
