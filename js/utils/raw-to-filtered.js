var o2 = o2 || {};

o2.Utilities = o2.Utilities || {};

o2.Utilities.rawToFiltered = function( raw ) {
	var filtered = '<p>' + raw.replace( /\n\n/g, '</p><p>' ) + '</p>';
	filtered = filtered.replace( /\n/g, 'BRABCXYZ' );
	filtered = filtered.replace( /\[(?:source)code.*?\](.*?)\[\/(?:source)?code\]/gi, function( i, match ) {
		var s = '<pre class="brush:plain; notranslate">' + o2.Utilities.htmlSpecialChars( match ) + '</pre>';
		s = s
			.replace( /<pre class="brush:plain; notranslate">((BRABCXYZ)*)?/gi, '<pre class="brush:plain; notranslate">' )
			.replace( /((BRABCXYZ)*)?<\/pre>/gi, '</pre>' );
		return s;
	} );
	filtered = filtered.replace( /<script.*?>(.*?)<\/script>/gi, function( i, match ) {
		return match;
	} );
	filtered = filtered.replace( /<blockquote>/g, '<blockquote><p>' );
	filtered = filtered.replace( /<\/blockquote>/g, '</p></blockquote>' );
	filtered = filtered.replace( /<[^>]*(onerror\s*=*\s*[^>]*)/gi, function() {
		return ''; // remove all onerror attributes from tags
	} );
	filtered = filtered.replace( /BRABCXYZ/g, '<br />' );
	return filtered;
};

o2.Utilities.htmlSpecialChars = function( str ) {
	return str
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
};
