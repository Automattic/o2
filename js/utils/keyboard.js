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
	currentItem:       false,

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

			$doc.click( function() {
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
			$doc.on( 'hidden.atwho', function() {
				o2Keyboard.atWhoFlag = true;

				setTimeout( function(){
					o2Keyboard.atWhoFlag = false;
				}, 200);
			});
		});


		$doc.on( 'keydown', null, 't', this.jumpToTop );
		$doc.on( 'keydown', null, 'j', this.jumpToNext );
		$doc.on( 'keydown', null, 'k', this.jumpToPrevious );
		$doc.on( 'keydown', null, 'c', this.compose );
		$doc.on( 'keydown', null, 's', this.search );
		$doc.on( 'keydown', null, '/', this.search );
		$doc.on( 'keydown', null, 'e', this.edit );
		$doc.on( 'keydown', null, 'r', this.reply );
		$doc.on( 'keydown', null, 'o', this.toggleComments );
		$doc.on( 'keydown', null, 'esc', this.cancel );
		$doc.on( 'keydown', '.o2-editor-text', 'esc', this.cancel );

		o2.$appContainer.on( 'post-post-save.o2',    this.updateLastPosted );
		o2.$appContainer.on( 'post-new-post.o2',     this.updateLastPosted );
		o2.$appContainer.on( 'post-comment-save.o2', this.updateLastPosted );
	},

	jumpTo: function( y, argSpeed ) {
		var speed = ( 'undefined' === typeof argSpeed ) ? 600 : argSpeed;
		$( 'html, body' ).animate( { scrollTop: y }, speed );
	},

	jumpToTop: function( e ) {
		o2Keyboard.jumpTo( 0 );
		e.preventDefault();
	},

	jumpToNext: function( e ) {
		var previousItem = false;

		if ( ! o2Keyboard.currentItem ) {
			// Nothing is selected, jump to the first post.
			o2Keyboard.currentItem = $( '.o2-post' ).first();
		} else if ( o2Keyboard.currentItem.hasClass( 'o2-post' ) ) {
			// A post is selected.
			if ( o2Keyboard.currentItem.parent().find( '.o2-comment' ).length ) {
				// If there are comments, jump to the first comment.
				previousItem = o2Keyboard.currentItem;
				o2Keyboard.currentItem = o2Keyboard.currentItem.parent().find( '.o2-comment' ).first();
			} else if ( o2Keyboard.currentItem.parents( '.post' ).next().find( '.o2-post' ).length ) {
				// If there are no comments, jump to the next post.
				previousItem = o2Keyboard.currentItem;
				o2Keyboard.currentItem = o2Keyboard.currentItem.parents( '.post' ).next().find( '.o2-post' ).first();
			}
			// If there are no more posts or comments, do nothing.

		} else if ( o2Keyboard.currentItem.hasClass( 'o2-comment' ) ) {
			// A comment is selected.
			if ( o2Keyboard.currentItem.find( '.o2-comment' ).length ) {
				// If there are child comments, jump to the first comment.
				previousItem = o2Keyboard.currentItem;
				o2Keyboard.currentItem = o2Keyboard.currentItem.find( '.o2-comment' ).first();
			} else if ( o2Keyboard.currentItem.next().length ) {
				// If the current comment has a next sibling, jump to it.
				previousItem = o2Keyboard.currentItem;
				o2Keyboard.currentItem = o2Keyboard.currentItem.next();
			} else if ( o2Keyboard.currentItem.parents( '.o2-comment' ).next().length ) {
				// If one of the parent comments has a next sibling, jump to that comment.
				previousItem = o2Keyboard.currentItem;
				o2Keyboard.currentItem = o2Keyboard.currentItem.parents( '.o2-comment' ).next().first();
			} else if ( o2Keyboard.currentItem.parents( '.post' ).next().find( '.o2-post' ).length ) {
				// If there are no more comments, jump to the next post.
				previousItem = o2Keyboard.currentItem;
				o2Keyboard.currentItem = o2Keyboard.currentItem.parents( '.post' ).next().find( '.o2-post' ).first();
			}
			// If there are no more posts or comments, do nothing.
		}

		if ( previousItem ) {
			previousItem.removeClass( 'keyselected' );
		}

		if ( ! o2Keyboard.currentItem.hasClass( 'keyselected' ) ) {
			o2Keyboard.currentItem.addClass( 'keyselected' );
			o2Keyboard.jumpTo( o2Keyboard.currentItem.offset().top - 50 );
		}
		e.preventDefault();
	},

	jumpToPrevious: function( e ) {
		var previousItem = false;

		if ( ! o2Keyboard.currentItem ) {
			// Nothing is selected, do nothing.
		} else if ( o2Keyboard.currentItem.hasClass( 'o2-post' ) ) {
			// A post is selected.
			var previousPost = o2Keyboard.currentItem.parent().prev();
			if ( previousPost.length ) {
				// There is a previous post
				if ( previousPost.find( '.o2-comment' ).length ) {
					// There are comments on the post, jump to the last one.
					previousItem = o2Keyboard.currentItem;
					o2Keyboard.currentItem = previousPost.find( '.o2-comment' ).last();
				} else {
					// No comments, jump to the post.
					previousItem = o2Keyboard.currentItem;
					o2Keyboard.currentItem = previousPost.find( '.o2-post' ).first();
				}
			}
			// No previous post, do nothing.

		} else if ( o2Keyboard.currentItem.hasClass( 'o2-comment' ) ) {
			// A comment is selected.
			if ( o2Keyboard.currentItem.prev().find( '.o2-comment' ).length ) {
				// If the current comment has a previous sibling with child comments, jump to the last one.
				previousItem = o2Keyboard.currentItem;
				o2Keyboard.currentItem = o2Keyboard.currentItem.prev().find( '.o2-comment' ).last();
			} else if ( o2Keyboard.currentItem.prev().length ) {
				// If the current comment has a previous sibling, jump to it.
				previousItem = o2Keyboard.currentItem;
				o2Keyboard.currentItem = o2Keyboard.currentItem.prev();
			} else if ( o2Keyboard.currentItem.parents( '.o2-comment' ).length ) {
				// If the current comment has a parent, jump to it.
				previousItem = o2Keyboard.currentItem;
				o2Keyboard.currentItem = o2Keyboard.currentItem.parents( '.o2-comment' ).first();
			} else {
				// Go to the post.
					previousItem = o2Keyboard.currentItem;
					o2Keyboard.currentItem = o2Keyboard.currentItem.parents( '.post' ).find( '.o2-post' ).first();
			}
		}

		if ( previousItem ) {
			previousItem.removeClass( 'keyselected' );
		}

		if ( ! o2Keyboard.currentItem.hasClass( 'keyselected' ) ) {
			o2Keyboard.currentItem.addClass( 'keyselected' );
			o2Keyboard.jumpTo( o2Keyboard.currentItem.offset().top - 50 );
		}
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

				if ( 'post' === type ) {
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

		if ( window.getSelection().toString().length ) {
			var target = $( window.getSelection().baseNode.parentNode );

			// First, check if there is a comment parent. If so, assume we're replying to that comment.
			// Else, we're replying to the post.
			var commentParent = target.closest( '.o2-comment' );
			if ( commentParent.length ) {
				commentParent.find( 'a.o2-comment-reply:first' ).click();
			} else {
				target.closest( '.o2-post' ).find( 'a.o2-post-reply:first' ).click();
			}
		} else {
			if ( o2Keyboard.page.isPage || o2Keyboard.page.isSingle ) {
				$( '.o2-posts > ' + o2Keyboard.threadContainer ).find( 'a.o2-post-reply:first' ).click();
			} else {

				//Let's create a grid of points in the top half of the viewport.
				var viewPortWidth  = $( window ).width(),
					viewPortHeight = $( window ).height(),
					xCoords = _.map( [    0.2, 0.4, 0.6, 0.8 ], function( num ){ return num * viewPortWidth; } ),
					yCoords = _.map( [ 0, 0.1, 0.2, 0.3, 0.4 ], function( num ){ return num * viewPortHeight; } );

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
		}

		e.preventDefault();
	},

	toggleComments: function( e ) {
		var commentToggle = $( '.o2-toggle-comments' );
		if ( commentToggle.length ) {
			commentToggle.click();
			e.preventDefault();
		}
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
