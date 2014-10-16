var o2Keyboard;

( function( $ ) {

o2Keyboard = {

	page:        false,
	searchInput: false,
	lastPosted:  false,
	atWhoFlag:   false,
	yOffset:     80,

	mainEditor:        false,
	mainEditorWrapper: false,
	threadContainer:   'article',

	init: function( pageMeta ) {
		var $doc = $( document );
		o2Keyboard.page = pageMeta.attributes;

		if ( o2Keyboard.page.isSingle || o2Keyboard.page.isPage ) {
			o2Keyboard.mainEditor = '.o2-editor-text';
			o2Keyboard.mainEditorWrapper = '.o2-editor';
		} else {
			o2Keyboard.mainEditor = '.o2-app-new-post .o2-editor-text';
			o2Keyboard.mainEditorWrapper = '.o2-app-new-post .o2-editor';
		}

		if ( 'undefined' !== typeof( o2.options.threadContainer ) ) {
			o2Keyboard.threadContainer = o2.options.threadContainer;
		}

		$doc.ready( function () {
			$( '.o2-toggle-keyboard-help' ).click( function( e ) {
				$( '#help' ).toggle();
				e.preventDefault();
				e.stopPropagation();
			});

			$doc.click( function( e ) {
				$( '#help' ).hide();
			});

			$( '#help' ).click( function( e ) {
				// consume, discard - just let the help stay open if they click in it
				e.preventDefault();
				e.stopPropagation();
			});

			/*
			 * Since atwho esc key handler runs first, we listen to the hidden event for atwho,
			 * and then set a flag and timeout to prevent closing atwho suggestions and reply
			 * boxes based on one esc keypress.
			*/
			$doc.on( 'hidden.atwho', function( event, flag, query ) {
				o2Keyboard.atWhoFlag = true;

				setTimeout( function(){
					o2Keyboard.atWhoFlag = false;
				}, 200);
			});
		});


		$doc.on( 'keydown', null, 't', this.jumpToTop );
		$doc.on( 'keydown', null, 'c', this.compose );
		$doc.on( 'keydown', null, 's', this.search );
		$doc.on( 'keydown', null, '/', this.search );
		$doc.on( 'keydown', null, 'e', this.edit );
		$doc.on( 'keydown', null, 'r', this.reply );
		$doc.on( 'keydown', null, 'esc', this.cancel );
		$doc.on( 'keydown', '.o2-editor-text', 'esc', this.cancel );

		o2.$appContainer.on( 'post-post-save.o2',    this.updateLastPosted );
		o2.$appContainer.on( 'post-new-post.o2',     this.updateLastPosted );
		o2.$appContainer.on( 'post-comment-save.o2', this.updateLastPosted );
	},

	jumpTo: function( y, speed ) {
		var speed = ( 'undefined' === typeof speed ) ? 600 : speed;
		$( 'html, body' ).animate( { scrollTop: y }, speed );
	},

	jumpToTop: function( e ) {
		o2Keyboard.jumpTo( 0 );
		e.preventDefault();
	},

	compose: function( e ) {
		var mainEditor = $( o2Keyboard.mainEditor );

		o2Keyboard.jumpTo( o2Keyboard.getElementTop( mainEditor ) );
		mainEditor.focus();

		e.preventDefault();
	},

	search: function( e ) {
		var inPage = $( '#s' );
		if ( inPage.length ) { // attempt widget
			o2Keyboard.jumpTo( o2Keyboard.getElementTop( inPage ) );
			o2Keyboard.searchInput = inPage;
		} else {
			o2Keyboard.searchInput = $( '#q' );
		}

		o2Keyboard.searchInput.focus();
		e.preventDefault();
	},

	edit: function( e ) {
		var editMe = false;
		if ( o2Keyboard.page.isPage || o2Keyboard.page.isSingle ) {
			editMe = $( '.o2-posts > ' + o2Keyboard.threadContainer );
			editMe.find( 'a.edit-post-link:first').click();
			editMe.find( '.o2-editor-text' ).focus();
		} else {
			if ( false !== o2Keyboard.lastPosted ) {
				var type = o2Keyboard.lastPosted.get( 'type' ),
					id   = o2Keyboard.lastPosted.get( 'id' );

				if ( 'post' == type ) {
					editMe = $( '#post-' + id );
					editMe.find( 'a.edit-post-link:first').click();
				} else {
					editMe = $( '#comment-' + id );
					editMe.find( 'a.o2-comment-edit:first').click();
				}
				editMe.find( '.o2-editor-text' ).focus();

			} else {
				o2Keyboard.reply( e );
			}
		}

		e.preventDefault();
	},

	reply: function( e ) {
		o2Keyboard.closeReplies();

		if ( o2Keyboard.page.isPage || o2Keyboard.page.isSingle ) {
			$( '.o2-posts > ' + o2Keyboard.threadContainer ).find( 'a.o2-post-reply:first' ).click();
		} else {

			//Let's create a grid of points in the top half of the viewport.
			var viewPortWidth  = $( window ).width(),
				viewPortHeight = $( window ).height(),
				xCoords = _.map( [ .2 , .4, .6, .8 ], function( num, key ){ return num * viewPortWidth; } ),
				yCoords = _.map( [ 0, .1, .2, .3, .4 ], function( num, key ){ return num * viewPortHeight; } );

			/*
			 * For each coordiante pair (x,y), get element at point,
			 * traverse up to find a post, and add post ID to elems.
			 */
			var elems = [];
			_.each( yCoords, function( y ){
				_.each( xCoords, function( x ){
					var element = $( document.elementFromPoint( x, y ) ),
						closest = element.closest( o2Keyboard.threadContainer + '.post' );

					if ( closest.length > 0 ) {
						elems.push( closest.attr( 'id' ) );
					}
				});
			});

			// Find most frequent (mode) post ID in elems array.
			// Thanks Matthew Flaschen - http://stackoverflow.com/a/1053865
			if ( elems.length > 0 ) {
				var modeMap = {};

				var maxEl = elems[0],
					maxCount = 1;

				_.each( elems, function( el ){
					if ( modeMap[ el ] == null ) {
						modeMap[ el ] = 1;
					} else {
						modeMap[ el ]++;
					}

					if ( modeMap[ el ] > maxCount ) {
						maxEl = el;
						maxCount = modeMap[ el ];
					}
				});

				$( '.o2-posts #' + maxEl ).find( 'a.o2-post-reply:first' ).click();
			}
		}

		e.preventDefault();
	},

	getElementTop: function( element ) {
		var offset = element.offset();

		if ( offset.top < o2Keyboard.yOffset ) {
			return 0;
		} else {
			return offset.top - o2Keyboard.yOffset;
		}
	},

	closeReplies: function() {
		$( '.o2-new-comment-cancel' ).click();
		$( '.o2-comment-cancel' ).click();
		$( '.o2-cancel' ).click();
	},

	cancel: function( e ) {
		var help = $( '#help' );
		if ( help.is( ':visible' ) ) {
			help.hide();
		} else if ( false === o2Keyboard.atWhoFlag ) {
			o2Keyboard.closeReplies();
		}

		e.preventDefault();
	},

	updateLastPosted: function( event, model ) {
		if ( 'undefined' !== typeof model ) {
			o2Keyboard.lastPosted = model;
		}
	}
};

} )( jQuery );
