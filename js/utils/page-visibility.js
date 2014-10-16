( function( $ ) {
	var hidden = 'hidden', $doc = $( document );

	// Standards:
	if ( hidden in document ) {
		// Do nothing, presumably the browser knows how to keep document.hidden up to date
	} else if ( ( hidden = 'mozHidden' ) in document ) {
		$doc.on( 'mozvisibilitychange', onchange );
		document.hidden = document[ hidden ];
	} else if ( ( hidden = 'webkitHidden' ) in document ) {
		$doc.on( 'webkitvisibilitychange', onchange );
		document.hidden = document[ hidden ];
	} else if ( ( hidden = 'msHidden' ) in document ) {
		$doc.on( 'msvisibilitychange', onchange );
		document.hidden = document[ hidden ];
	} else if ( 'onfocusin' in document ) {
		// IE 9 and lower:
		$doc.on( 'focusin focusout',  onchange );
		document.hidden = ! document.hasFocus();
	} else {
		// All others:
		$( window ).on( 'pageshow pagehide focus blur', onchange );

		if ( 'hasFocus' in document ) {
			document.hidden = ! document.hasFocus();
		}
	}

	function onchange ( e ) {
		var evtMap = {
			focus:    false,
			focusin:  false,
			pageshow: false,
			blur:     true,
			focusout: true,
			pagehide: true
		};

		e = e || window.event;
		if ( e.type in evtMap ) {
			document.hidden = evtMap[ e.type ];
		} else {
			document.hidden = this[ hidden ];
		}
	}
} )( jQuery );
