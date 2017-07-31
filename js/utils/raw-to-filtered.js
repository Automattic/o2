/* global o2Config */
var o2 = o2 || {};

o2.Utilities = o2.Utilities || {};

o2.Utilities.rawToFiltered = function( raw, context ) {
	var filtered = stripUnsafeHTML( raw, context );

	filtered = '<p>' + filtered.replace( /\n\n/g, '</p><p>' ) + '</p>';
	filtered = filtered.replace( /\n/g, 'BRABCXYZ' );

	filtered = filtered.replace( /\[(?:source)code.*?\](.*?)\[\/(?:source)?code\]/gi, function( i, match ) {
		var s = '<pre class="brush:plain; notranslate">' + o2.Utilities.htmlSpecialChars( match ) + '</pre>';
		s = s
			.replace( /<pre class="brush:plain; notranslate">((BRABCXYZ)*)?/gi, '<pre class="brush:plain; notranslate">' )
			.replace( /((BRABCXYZ)*)?<\/pre>/gi, '</pre>' );
		return s;
	} );

	filtered = filtered.replace( /<blockquote>/g, '<blockquote><p>' );
	filtered = filtered.replace( /<\/blockquote>/g, '</p></blockquote>' );

	filtered = filtered.replace( /BRABCXYZ/g, '<br />' );

	return filtered;
};

o2.Utilities.htmlSpecialChars = function( str ) {
	return str
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
};

o2.Utilities.containsHTML = function( str ) {
	return str !== stripUnsafeHTML( str, 'stripall' );
};

function stripUnsafeHTML( html, context ) {
	var parsed = true;
	var doc;

	try {
		doc = ( new DOMParser() ).parseFromString( html, 'text/html' );
	} catch( e ) {
		// IE < 11 throws an error if the parser fails
		parsed = false;
	}

	if ( ! doc ) {
		parsed = false;
	}

	if ( parsed ) {
		// Check that there weren't any parse errors.
		if ( 'html' !== doc.documentElement.tagName.toLowerCase() ) {
			parsed = false;
		} else if ( doc.getElementsByTagName( 'parsererror' ).length > 0 ) {
			parsed = false;
		}
	}

	if ( ! parsed ) {
		// Something went horribly wrong. Strip everything that looks like a tag, let the server sort it out.
		return html.replace( /<.*?>/g, '' );
	}

	if ( 'stripall' === context ) {
		return doc.body.textContent;
	}

	return removeUnsafeNodes( doc.body, context ).innerHTML;
}

function removeUnsafeNodes( node, context ) {
	var newNode, childNode, ii, attr;
	var name = node.nodeName.toLowerCase();


	// Text nodes are always safe.
	if ( '#text' === name ) {
		return node;
	}

	// HTML comments are always safe.
	if ( '#comment' === name ) {
		return node;
	}

	if ( ! ( context in o2Config.allowedTags ) ) {
		context = 'comment';
	}

	// No need to check the body element, it will be ignored when we return.
	if ( 'body' === name ) {
			newNode = document.createElement( 'body' );
	} else {
		if ( ! ( name in o2Config.allowedTags[ context ] ) ) {
			// This tag is not allowed.
			return document.createTextNode( node.textContent );
		}

		// Create a fresh node
		newNode = document.createElement( name );

		// Copy the whitelisted attributes
		for ( ii = 0; ii < node.attributes.length; ii++ ) {
			attr = node.attributes.item( ii );
			if ( o2Config.allowedTags[ context ][ name ][ attr.name ] ) {
				newNode.setAttribute( attr.name, attr.value );
			}
		}
	}

	// Sanitise the child nodes
	while ( node.childNodes.length > 0 ) {
		childNode = node.removeChild( node.firstChild );
		newNode.appendChild( removeUnsafeNodes( childNode, context ) );
	}

	return newNode;
}

/* jshint ignore:start */
/*
 * DOMParser HTML extension
 * 2012-09-04
 *
 * By Eli Grey, http://eligrey.com
 * Public domain.
 * NO WARRANTY EXPRESSED OR IMPLIED. USE AT YOUR OWN RISK.
 */

/*! @source https://gist.github.com/1129031 */
/*global document, DOMParser*/

(function(DOMParser) {
	"use strict";

	var
	  proto = DOMParser.prototype
	, nativeParse = proto.parseFromString
	;

	// Firefox/Opera/IE throw errors on unsupported types
	try {
		// WebKit returns null on unsupported types
		if ((new DOMParser()).parseFromString("", "text/html")) {
			// text/html parsing is natively supported
			return;
		}
	} catch (ex) {}

	proto.parseFromString = function(markup, type) {
		if (/^\s*text\/html\s*(?:;|$)/i.test(type)) {
			var
			  doc = document.implementation.createHTMLDocument("")
			;
	      		if (markup.toLowerCase().indexOf('<!doctype') > -1) {
        			doc.documentElement.innerHTML = markup;
      			}
      			else {
        			doc.body.innerHTML = markup;
      			}
			return doc;
		} else {
			return nativeParse.apply(this, arguments);
		}
	};
}(DOMParser));
/* jshint ignore:end */
