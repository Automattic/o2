/* jshint multistr: true */
/* global enquire, Caret */
var o2Editor;

( function( $ ) {

o2Editor = {
	editors:   [],
	isFirefox: false,
	autocompleting: false,

	/*
	 * Called once, just sets everything up at the document level. Does *not* create any editor instances
	 */
	load: function() {
		// Firefox doesn't recognise ::first-line in textareas,
		// which makes auto-title kind of useless.
		// We should avoid using auto-title in Firefox.
		o2Editor.isFirefox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1;

		var $doc = $( document );

		// dragover instead of dragenter because firefox fires dragleave upon hovering over child nodes
		$doc.on( 'dragover',  '.o2-editor-wrapper', this.onDragOver );
		$doc.on( 'dragleave', '.o2-editor-wrapper', this.onDragLeave );
		$doc.on( 'drop',      '.o2-editor-wrapper', this.onDrop );

		$doc.on( 'keydown', '.o2-editor-text', 'meta+shift+a', this.shortcutKey ); // Link, a la wp-admin
		$doc.on( 'keydown', '.o2-editor-text', 'ctrl+b',       this.shortcutKey ); // Bold (strong)
		$doc.on( 'keydown', '.o2-editor-text', 'meta+b',       this.shortcutKey ); // Bold (strong)
		$doc.on( 'keydown', '.o2-editor-text', 'ctrl+i',       this.shortcutKey ); // Italic (em)
		$doc.on( 'keydown', '.o2-editor-text', 'meta+i',       this.shortcutKey ); // Italic (em)
		$doc.on( 'keydown', '.o2-editor-text', 'ctrl+m',       this.shortcutKey ); // add media

		$doc.on( 'keydown', '.o2-editor-text', 'tab',         this.focusSaveButton );
		$doc.on( 'keydown', '.o2-save',        'shift+tab',   this.focusEditor );
		$doc.on( 'keydown', '.o2-comment-save','shift+tab',   this.focusEditor );

		$doc.on( 'keyup',   '.o2-editor-text',                this.autoTitle );

		$doc.on( 'keydown',                                   this.onKeyDown );

		// Caret handling -- mostly because of Webkit bug
		$doc.on( 'blur',      '.o2-editor-text',              this.rememberCaretPosition );
		$doc.on( 'keydown',   '.o2-editor-text',              this.rememberCaretPosition );
		$doc.on( 'mouseup',   '.o2-editor-text',              this.rememberCaretPosition );
		$doc.on( 'focus',     '.o2-editor-text',              this.repositionCaret );
		$doc.on( 'paste',     '.o2-editor-text',              this.forgetCaretPosition );

		$doc.on( 'click',   '.o2-editor-toolbar-button',      this.toolbarClick );

		$doc.on( 'click',   '.o2-editor-edit-button',         this.showEditor );
		$doc.on( 'click',   '.o2-editor-preview-button',      this.showPreview );

		$doc.on( 'paste',   '.o2-editor-text',                this.paste );

		$doc.on( 'change',  '.o2-image-file-input',           this.onImageFileInputChanged );
		$doc.on( 'scroll', _.debounce( this.onDocumentScrollStopped, 250 ) );
		$doc.on( 'scroll', _.debounce( this.toggleMobileEditorIconVisibility, 250 ) );

		// Atwho event handlers to prevent duplicate tab behavior
		$doc.on( 'shown.atwho', function() {
			o2Editor.autocompleting = true;
		});
		$doc.on( 'hidden.atwho', function() {
			setTimeout( function() {
				o2Editor.autocompleting = false;
			}, 50 ); // Need a delay to let the hiding actually happen
		});

		$doc.on( 'ready', function() {
			// Wire up the global editor opener
			$( '#o2-expand-editor' ).click( function( event ) {
				event.preventDefault();

				// Remove icon/button
				$( this ).hide();

				// Display the editor and focus on it
				$( '.o2-app-new-post' ).slideDown( 'fast', function() {
					$( '.o2-app-new-post .o2-editor-text' ).last().focus();
					$( 'html, body' ).animate( { // Scroll to new post
					scrollTop: $( '.o2-app-new-post' ).offset().top - 50
				}, 100 );
				} );
			} );

			if ( 'undefined' !== typeof enquire ) {
				// On small enough screens, remove the editor once publishing finishes
				enquire.register( 'screen and ( max-width : 640px )', {
					match: function() {
						$( o2.options.appContainer ).on( 'post-post-save.o2', o2Editor.hideEditor );
					},
					unmatch: function() {
						$( o2.options.appContainer ).off( 'post-post-save.o2', o2Editor.hideEditor );
					}
				} );
			}
		} );

		if ( o2Editor.hasLocalStorage() ) {
			setInterval( o2Editor.backup, 1000 );
		}
	},

	hideEditor: function() {
		if ( o2.App.pageMeta.get( 'isSingle' ) || o2.App.pageMeta.get( 'isPage' ) ) {
			return;
		}

		$( '#o2-expand-editor' ).fadeIn( 'fast' ); // Show button
		$( '.o2-app-new-post' ).hide(); // Hide editor
		$( 'html, body' ).animate( { // Scroll to new post
			scrollTop: $( o2.options.appContainer + ' .o2-posts' ).offset().top - 50
		}, 100 );
	},

	toggleMobileEditorIconVisibility: function() {
		var $editorIcon = $( '#o2-expand-editor' );

		if ( $editorIcon.css( 'display' ) === 'block' ) {
			$editorIcon.addClass( 'hidden' );
		}

		if ( 0 === $( document ).scrollTop() ) {
			$editorIcon.removeClass( 'hidden' );
		}

	},

	/**
	 * Locates textareas with .o2-editor as a class, and converts them to
	 * o2 editor instances. Use title="" to specify a title, and placeholder=""
	 * for a prompt
	 */
	detectAndRender: function( $dom ) {
		$dom.find( 'textarea.o2-editor' ).each( function() {
			$( this ).replaceWith( o2Editor.getEditor( this ) );
		} );
	},

	getEditor: function( elem ) {
		// Prime details from the <textarea> we're replacing
		var $holder = $( elem ),
				prompt  = $holder.attr( 'placeholder' ) || '',
				title   = $holder.attr( 'title' ), // Leave attribute off <textarea> completely to remove title controls
				content = $holder.val(),
				editor;

				if ( 'string' === typeof title ) {
					title = title.replace( /"/g, '&quot;' );
				}

		editor = '<div class="o2-editor-wrapper"> \
			<div class="o2-editor-toolbar-wrapper"> \
				<div class="o2-editor-toolbar"> \
					<button class="o2-editor-toolbar-button genericon genericon-bold" value="strong" title="' + o2.strings.bold + '"></button> \
					<button class="o2-editor-toolbar-button genericon genericon-italic" value="em" title="' + o2.strings.italics + '"></button> \
					<button class="o2-editor-toolbar-button genericon genericon-link" value="a" title="' + o2.strings.link + '"></button> \
					<button class="o2-editor-toolbar-button genericon genericon-picture" value="img" title="' + o2.strings.image + '"></button> \
					<button class="o2-editor-toolbar-button genericon genericon-quote" value="blockquote" title="' + o2.strings.blockquote + '"></button> \
					<button class="o2-editor-toolbar-button genericon genericon-code" value="code" title="' + o2.strings.code + '"></button>';
					if ( 'string' === typeof title ) {
						editor += '<div class="o2-editor-format dashicon" title="' + o2.strings.addPostTitle + '">&#61969;</div>';
					}
				editor += '<div class="o2-editor-upload"><div class="o2-editor-upload-progress"></div></div> \
				</div> \
				<div style="display:none;"> \
					<input class="o2-image-file-input" style="display:none" type="file" accept="image/*,video/*"> \
				</div> \
			</div>';
			if ( 'string' === typeof title ) {
				editor += '<div class="o2-editor-title-wrapper"> \
					<input type="text" class="o2-title o2-editor-title" value="' + title + '" placeholder="' + o2.strings.enterTitleHere + '" /> \
				</div>';
				}
			editor += '<textarea class="o2-editor-text" placeholder="' + prompt + '">' + content + '</textarea> \
			<div class="o2-editor-preview"></div> \
		</div>';

		return editor;
	},

	/*
	 * Create an editor instance, given a jQuery object (of a textarea) plus a postID/commentID.
	 * Edit/create a Post: postID (edit) | 'new', false
	 * Edit/create a Comment: postID, commentID (edit or reply) | 'new' (reply to post)
	 */
	create: function( $editor, postID, commentID ) {
		if ( 0 >= $editor.length ) {
			return;
		}

		if ( 'undefined' === typeof postID ) {
			return;
		}

		if ( 'undefined' === typeof commentID ) {
			commentID = 'new';
		}

		$editor.data( 'post_id', postID );
		$editor.data( 'comment_id', commentID );

		// If there's content in localStorage, then load it up and render it
		var cacheKey = o2Editor.getKey( $editor );
		var content = localStorage.getItem( cacheKey );
		if ( content && 0 === $editor.val().length ) {
			$editor.val( content );
		}

		if ( false === o2.currentUser.canPublishPosts ) {
			$editor.parent().find( 'button.insert-media' ).hide();
		}

		o2Editor.editors[ cacheKey ] = $editor;

		if ( 'function' === typeof $editor.autoResize ) {
			// make sure resize is set to none before the autoResize is applied
			// so that autoResize doesn't set the resize attribute to horizontal
			$editor.css( 'resize', 'none' );

			$editor.autoResize( { extraSpace: 100 } );
			setTimeout( function() {
				$editor.trigger( 'resize.autosize' );
			}, 10 );
		}
		o2.Events.doAction( 'post-editor-create.o2', $editor );
	},

	/*
	 * Cleanup an editor and its storage
	 */
	finished: function( postID, commentID, args ) {
		if ( 'undefined' === typeof( commentID ) || ! commentID ) {
			commentID = 0;
		}

		var cacheKey = o2.options.currentBlogId + '-' + o2Editor.getKeyFromIDs( postID, commentID );

		if ( 'undefined' === typeof args || 'undefined' === typeof args.keepCache || ! args.keepCache ) {
			localStorage.removeItem( cacheKey );
		}
		delete o2Editor.editors[ cacheKey ];
	},

	/*
	 * Handles a change object (Event), which will update the content of the editor
	 */
	change: function( e ) {
		var carrot  = Caret( e.target );
		var curr    = carrot.start;

		// Avoid repositioning the caret based on previous position
		o2Editor.forgetCaretPosition( e.target );

		switch ( e.type ) {
			case 'insert':
				carrot.insert( e.data.text );
				curr += e.data.text.length;
				break;

			default:
				return;
		}

		var newPos = curr;
		if ( e.data.offset && 0 !== e.data.offset ) {
			newPos += e.data.offset;
		}
		carrot.set( newPos );

		$( e.target ).change();
	},

	/*
	 * hasChanges will return true if ANY editor has any text, and we don't have localStorage
	 * Useful for beforeunload prompts
	 */
	hasChanges: function() {
		if ( o2Editor.hasLocalStorage() ) {
			return false;
		}

		var anyHasChanges = false;

		$( '.o2-editor' ).each( function() {
			var $editor = $( this ).find( '.o2-editor-text' ).last();
			if ( $editor.val().length > 0 ) {
				anyHasChanges = true;
			}
		} );

		return anyHasChanges;
	},

	hasLocalStorage: function() {
		try {
			if ( 'undefined' !== typeof( window.localStorage ) ) {
				return true;
			}
		}
		catch( e ) {
			return false;
		}

		return false;
	},

	/*
	 * Client-side auto-save using localStorage
	 */
	backup: function() {
		$( '.o2-editor' ).each( function() {
			var $editor   = $( this ).find( '.o2-editor-text' ).last();

			if ( ! $editor.hasClass( 'user-typed' ) ) {
				return;
			}

			var cacheKey = o2Editor.getKey( $editor.get( 0 ) );

			if ( 'undefined' === typeof( o2Editor.editors[ cacheKey ] ) ) {
				return;
			}

			localStorage.setItem( cacheKey, $editor.val() );
		} );
	},

	// All your paste are belong to us
	paste: function( e ) {
		if ( 'undefined' === typeof( e.originalEvent ) || 'undefined' === typeof( e.originalEvent.clipboardData ) ) {
			return;
		}

		var pasted = e.originalEvent.clipboardData.getData( 'text/plain' );
		if ( '' === pasted ) {
			return;
		}

		var editor = e.target,
				carrot = Caret( editor ),
				overwrittenLength = 0;

		// If we're pasting something that looks like a URL, and we have an active selection, and we're not inside an img or a, then wrap it in an a-href
		if ( pasted.match( /^https?:\/\//i ) && carrot.end > carrot.start ) {
			overwrittenLength = carrot.end - carrot.start;

			// Make sure we are not in a tag first
			var okToPasteAsTag = true;
			var leftString = editor.value.slice( 0, carrot.start ).toLowerCase();

			// If the current selection is also a URL, assume we want to replace it (not wrap it in an anchor)
			if ( editor.value.slice( carrot.start, carrot.end ).match( /^https?:\/\/[^\s]*$/i ) ) {
				okToPasteAsTag = false;
			}

			// If we are inside a (start) tag for any HTML element, its not ok to paste as a an a-href
			if ( ( -1 < leftString.lastIndexOf( '<' ) ) && ( leftString.lastIndexOf( '<' ) > leftString.lastIndexOf( '>' ) ) ) {
				okToPasteAsTag = false;
			}

			// If we are inside an anchor's content, its not ok to paste as a an a-href
			if ( ( -1 < leftString.lastIndexOf( '<a' ) ) && ( leftString.lastIndexOf( '<a' ) > leftString.lastIndexOf( '</a>' ) ) ) {
				okToPasteAsTag = false;
			}

			if ( okToPasteAsTag ) {
				e.preventDefault();
				o2Editor.insertTag( editor, 'a', pasted );
				return;
			}
		}

		// Manually trigger the resize script to make sure the box is the correct size
		$( e.target ).trigger( 'autosize.resize' );
	},

	/*
	 * Support for a few simple keyboard shortcuts to trigger the same things as the toolbar
	 */
	shortcutKey: function( e ) {
		var tag;

		if ( e.ctrlKey && 77 === e.which ) {
			e.preventDefault();
			$( e.target ).parent().find( '.insert-media' ).click(); // cheap and dirty, but the handle to media gallery isn't otherwise clean
			return;
		} else if ( e.metaKey && e.shiftKey && 65 === e.which ) {
			tag = 'a';
		} else if ( ( e.ctrlKey || e.metaKey ) && 66 === e.which ) {
			tag = 'strong';
		} else if ( ( e.ctrlKey || e.metaKey ) && 73 === e.which ) {
			tag = 'em';
		} else {
			return;
		}

		e.preventDefault();

		o2Editor.insertTag( e.target, tag );
	},

	onKeyDown: function( e ) {
		$( e.target ).addClass( 'user-typed' );
		return;
	},

	// When we blur focus from an editor, remember the caret position so that we can
	// fix a WebKit bug that forgets it and comes back to position 0.
	rememberCaretPosition: function( e ) {
		var caret = Caret( e.target );
		$( e.srcElement ).data( 'lastCaretPosition', caret.start );
	},

	// If we have a remembered caret position, use it to set the caret correctly when
	// focus comes back to this editor.
	repositionCaret: function() {
		if ( o2Editor.autocompleting ) {
			return;
		}

		var pos = $( this ).data( 'lastCaretPosition' );
		o2Editor.forgetCaretPosition( this );
		if ( undefined !== pos && pos > 0 ) {
			var that = this;

			// Need a tiny pause for the caret to get incorrectly positioned so we can fix it
			setTimeout( function() {
				var carrot = Caret( that );
				carrot.set( pos );
			}, 1 );
		}
	},

	// Remove reference to previous caret position when we click into a textarea, since we assume
	// we click to somewhere specific
	forgetCaretPosition: function( elem ) {
		$( elem ).removeData( 'lastCaretPosition' );
	},

	toolbarClick: function( e ) {
		var $this   = $( this );
		var tag     = $this.val();
		var $editor = $this.closest( '.o2-editor-wrapper' ).find( '.o2-editor-text' ).last();

		if ( 'blockquote' === tag && o2Editor.quoteSelection( $editor ) ) {
			return;
		}

		if ( 'img' === tag ) {
			// kick off prompt
			e.preventDefault();
			$this.closest( '.o2-editor-wrapper' ).find( '.o2-image-file-input' ).click();
			return;
		}

		o2Editor.insertTag( $editor, tag );
		e.preventDefault();
		$editor.focus();
	},

	onImageFileInputChanged: function( event ) {
		var currentTarget = $( event.currentTarget );
		if ( currentTarget.val().length ) {
			var uploadProgress = currentTarget.parents( '.o2-editor' ).find( '.o2-editor-upload-progress' );
			o2Editor.uploadFiles( event, event.currentTarget.files, uploadProgress );
		}
		currentTarget.val( '' );
	},

	showEditor: function( e ) {
		e.preventDefault();

		var $this          = $( this );
		var $editor        = $this.closest( '.o2-editor' ).find( '.o2-editor-text' ).last();
		var $preview       = $this.closest( '.o2-editor' ).find( '.o2-editor-preview' );
		var $previewButton = $this.closest( '.o2-editor' ).find( '.o2-editor-preview-button' );

		$previewButton.parent().removeClass( 'selected' );

		$preview.hide();
		$editor.show().focus();

		$this.parent().children().removeClass( 'selected' );
		$this.parent().addClass( 'selected' );

		$preview.empty();
	},

	showPreview: function( e ) {
		e.preventDefault();

		var $this    = $( this );
		var $editor  = $this.closest( '.o2-editor' ).find( '.o2-editor-text' ).last();
		var $preview = $this.closest( '.o2-editor' ).find( '.o2-editor-preview' );
		var $edit = $this.closest( '.o2-editor' ).find( '.o2-editor-edit-button' );

		var type = 'post';
		if ( $this.closest( '.o2-comment' ).length ) {
			type = 'comment';
		}

		var data = {
			data: $editor.val(),
			type: type
		};

		$.ajax( {
			url: o2.options.readURL + '&method=preview',
			type: 'POST',
			dataType: 'json',
			xhrFields: {
				withCredentials: true
			},
			data: data,
			success: function( response ) {
				$preview.html( response.data );
				$preview.css( 'height', 'auto' );
			}
		} );

		$preview.css( 'height', $editor.css( 'height' ) );
		$preview.html( '<p>' + o2.strings.previewPlaceholder + '</p>' );

		$editor.hide();
		$preview.show();
		$edit.parent().removeClass( 'selected' );

		$this.parent().addClass( 'selected' );
	},

	focusSaveButton: function( e ) {
		if ( !o2Editor.autocompleting ) {
			// Logged-in users tab to post
			if ( o2.currentUser.userLogin !== '' ) {
				e.preventDefault();
				$( this ).closest( '.o2-editor' ).find( '.o2-save, .o2-comment-save' ).first().focus();
			}
		}
	},

	focusEditor: function( e ) {
		e.preventDefault();
		$( this ).closest( '.o2-editor' ).find( '.o2-editor-text' ).last().focus();
	},

	/*
	 * If there is selected text within the page, copy it into the editor and wrap it in blockquote tags.
	 *
	 * @param $editor jQuery wrapped textarea node
	 */
	quoteSelection: function( $editor ) {
		var selection, text = '';

		if ( window.getSelection ) {
			selection = window.getSelection();
			text = selection.toString();
		} else if ( document.selection ) {
			selection = document.selection;
			text = selection.createRange().text;
		}

		// Clear the selection so we don't insert it again and again
		if ( selection.removeAllRanges ) {
			selection.removeAllRanges();
		} else if ( selection.empty ) {
			selection.empty();
		}

		if ( text.length ) {
			o2Editor.insertTag( $editor, 'blockquote', text );
			$editor.change().focus();
			return true;
		}

		return false;
	},

	insertTag: function( element, tag, extra ) {
		// 'element' can be a jQuery wrapped textarea node
		extra            = extra || '';
		var editor       = ( element instanceof jQuery ) ? element.get(0) : element;
		var caret        = Caret( editor );
		var selected     = caret.selected() || '';
		var selectedLen  = selected.length;
		var newEvent     = jQuery.Event( 'insert' );
		newEvent.target  = editor;

		switch ( tag ) {

			case 'media':
				return;

			case 'strong':
			case 'em':
			case 'blockquote':
				if ( extra.length > 0 ) {
					selected = extra;
					selectedLen = selected.length;
				}
				newEvent.data = {
					text:   '<' + tag + '>' + selected + '</' + tag + '>', // wrap selected text in tag
					offset: -3 - tag.length // put the caret inside the tags, after any internal text
				};
				// put the caret outside the tags (at the end)
				if ( selectedLen > 0 ) {
					newEvent.data.offset = 0;
				}
				break;

			case 'code':
				if ( 0 === caret.start || '\n' === editor.value.slice( caret.start - 1, caret.start ) ) {
					// Start of a line, so we probably want the [code] shortcodes
					newEvent.data = {
						text:   '[code]' + selected + '[/code]',
						offset: -7 // always put the caret back inside the tags
					};
				} else {
					// In the middle of a line, so we probably want the <code> tags
					newEvent.data = {
						text:   '<code>' + selected + '</code>',
						offset: -7 // always put the caret back inside the tags
					};
				}
				break;

			case 'a':
				if ( !extra.length ) {
					extra = window.prompt( 'Enter URL:', 'http://' );
					if ( null == extra ) {
						return;
					}
				}
				newEvent.data = {
					text:   '<a href="' + extra + '">' + selected + '</a>',
					offset: -4 // caret inside the closing 'a' tag
				};
				// have to go back further because the string length is added later
				if ( selectedLen > 0 ) {
					newEvent.data.offset = -6 - selectedLen;

					// if we filled the URL, then jump to the end
					if ( extra.length > 0 ) {
						newEvent.data.offset = 0;
					}
				}
				break;

			case 'img':
				newEvent.data = {
					text:   '<img src="' + selected + '"/>',
					offset: 0
				};
				if ( ! selected.length ) {
					newEvent.data.offset = -3;
				}
				break;

			default:
				return;
		}
		o2Editor.change( newEvent );
	},

	autoTitle: function( e ) {
		var $editor = $( e.target );
		if ( $editor.data( 'autoTitleDisabled' ) ) {
			$editor.removeClass( 'o2-editor-title-line' );
			return;
		}

		var holder = '';
		if ( o2Editor.firstLineIsProbablyATitle( $editor.val() ) ) {
			// Amazingly, having a placeholder attribute breaks our :first-line
			// auto-title JS, so we have to juggle it out to avoid that.
			holder = $editor.attr( 'placeholder' );
			if ( holder ) {
				$editor.data( 'placeholder', holder );
				$editor.removeAttr( 'placeholder' );
			}

			if ( ! $editor.hasClass( 'o2-editor-title-line' ) ) {
				$editor.addClass( 'o2-editor-title-line' );

				// When removing a ::first-line class, Chrome will only repaint the
				// first line when you change it, or at some random point in the future.
				// This hideous hack forces a document reflow, so should be used sparingly.
				$( '<style></style>' ).appendTo( $( document.body ) ).remove();
			}
		} else {
			holder = $editor.data( 'placeholder' );
			if ( holder ) {
				$editor.attr( 'placeholder', holder );
			}

			if ( $editor.hasClass( 'o2-editor-title-line' ) ) {
				$editor.removeClass( 'o2-editor-title-line' );

				// Ditto.
				$( '<style></style>' ).appendTo( $( document.body ) ).remove();
			}
		}
	},

	firstLineIsProbablyATitle: function( text ) {
		// Firefox isn't doesn't obey ::first-line in textareas,
		// so let's disable auto title.
		if ( o2Editor.isFirefox ) {
			return false;
		}

		var lines = text.split('\n');
		if ( ! lines || 1 === lines.length && '' === lines[0] ) {
			return false;
		}

		var firstLine = lines[0].match(/\S+/g);
		if ( ! firstLine ) {
			return false;
		}

		if ( firstLine.length > 8 ) {
			return false;
		}

		// Don't auto-title if it's part of a list
		if ( lines[0].match( /^([ox\-*]|1\.) / ) ) {
			return false;
		}

		// Special case: don't auto-title if it's part of a numbered list, but it might be a Markdown title
		if ( '# ' === lines[0].substr( 0, 2 ) && lines.length > 1 && '# ' === lines[1].substr( 0, 2 ) ) {
			return false;
		}

		// Don't auto-title if there's a mention/tag/xpost
		if ( lines[0].match( /(?:^|\s|\b|>|\()[@+#]([\w-\.]+)(?:$|\s|\b|<|\))/ ) ) {
			return false;
		}

		// Don't auto-title if there's a URL
		if ( lines[0].match( /(http(s)?|ftp):\/\// ) ) {
			return false;
		}

		// Don't auto-title if there's a shortcode
		if ( lines[0].match( /\[.+\]/ ) ) {
			return false;
		}

		// Don't auto-title if there's HTML
		if ( $( '<div>' + lines[0] + '</div>' ).text() !== lines[0] ) {
			return false;
		}

		return true;
	},

	getKey: function( editor ) {
		var $editor   = $( editor );
		var postID    = $editor.data( 'post_id' );
		var commentID = $editor.data( 'comment_id' );

		return o2.options.currentBlogId + '-' + o2Editor.getKeyFromIDs( postID, commentID );
	},

	getKeyFromIDs: function( postID, commentID ) {
		if ( ! postID ) {
			postID = 'new';
		}

		if ( 'undefined' === typeof commentID || null === commentID ) {
			commentID = 0;
		}

		var key;
		if ( 'new' === postID ) {
			key = 'new';
		} else if ( 'new' !== commentID ) {
			key = postID + '-c'; // all replies to a thread share a bucket
		} else {
			key = postID;
		}

		return key;
	},

	/*
	 * When the scroll position of the document changes, keep visible editors' toolbars in the viewport
	 */
	onDocumentScrollStopped: function() {
		if ( ( 'undefined' !== typeof o2 ) && ( 'undefined' !== typeof o2.options ) && ( 'undefined' !== typeof o2.options.isMobileOrTablet ) && o2.options.isMobileOrTablet ) {
			// no scrolling toolbar on mobile or tablets please
			return;
		}

		var wpadminbar = $( '#wpadminbar' );
		$( '.o2-editor-wrapper:visible' ).each( function() {
			var $t            = $( this ),
					editorTopLeft = $t.offset(),
					editorHeight  = $t.height(),
					documentScrollTop = $( document ).scrollTop();

			if ( wpadminbar.length ) {
				documentScrollTop += wpadminbar.height() - 1;
			}

			var toolbarTop = 0;
			if ( documentScrollTop > editorTopLeft.top ) {
				if ( documentScrollTop < editorTopLeft.top + editorHeight ) {
					toolbarTop = parseInt( documentScrollTop - editorTopLeft.top, 10 ) - 1;
				}
			}

			var $toolbar = $t.find( '.o2-editor-toolbar' );
			// Only move the toolbar down until it would hit the bottom of the editor
			if ( documentScrollTop > editorTopLeft.top + editorHeight - $toolbar.height() ) {
				toolbarTop = 0;
			}
			$toolbar.animate( { top: toolbarTop }, 'fast' );
			if ( toolbarTop > 0 ) {
				$toolbar.addClass( 'floated' );
			} else {
				$toolbar.removeClass( 'floated' );
			}
		});
	},

	onDragOver: function( event ) {
		event.preventDefault();
		$( event.currentTarget ).addClass( 'dragging' );
	},

	onDragLeave: function( event ) {
		$( event.currentTarget ).removeClass( 'dragging' );
	},

	onDrop: function( event ) {
		// No files were dropped; was perhaps a text drag?
		if ( 0 === event.originalEvent.dataTransfer.files.length ) {
			$( event.currentTarget ).removeClass( 'dragging' );
			return;
		}

		// Remember caret so we can insert at the right place
		o2Editor.rememberCaretPosition( event );

		event.preventDefault();
		event.stopPropagation();

		// recent chrome bug requires this, see stackoverflow thread: http://bit.ly/13BU7b5
		event.originalEvent.stopPropagation();
		event.originalEvent.preventDefault();

		var files = event.originalEvent.dataTransfer.files; // jquery event doesn't have dataTransfer data, so need ['originalEvent']
		var uploadProgress = $( event.currentTarget ).find( '.o2-editor-upload-progress' );

		o2Editor.uploadFiles( event, files, uploadProgress );
	},

	isAllowedMimeType: function( mimeType ) {
		for ( var key in o2.options.mimeTypes ) {
			if ( o2.options.mimeTypes[ key ] === mimeType ) {
				return true;
			}
		}
		return false;
	},

	/*
	 * Dragging and dropping file(s) onto the editor drops us directly into this method
	 * On the other hand, clicking on the image button and then selecting one or more
	 * files causes a onImageFileInputChanged to happen that then sends us here
	 */
	uploadFiles: function( event, files, uploadProgress ) {
		var	formData = new FormData(),
			inProgress = true,
			timedProgress;

		// progress bar funciton
		timedProgress = function() {
			var progress = 0,

			runProgress = function() {
				if ( ! inProgress ) {
					return;
				}

				progress = progress + ( 90 - progress ) * 0.08; // approaches 90%, but never completes on its own
				uploadProgress.css( 'width', Math.floor( progress ) + '%' );

				setTimeout( runProgress, 200 ); // same interval as css transition for smooth progress
			};

			runProgress();
		};

		$( event.currentTarget ).removeClass( 'dragging' );

		// populate the formdata
		var appendCount = 0;
		for ( var i = 0, fl = files.length; i < fl; i++ ) {
			// Check that it is an allowed filetype
			var filetype = files[ i ].type;
			var filename = files[ i ].name;
			if ( this.isAllowedMimeType( filetype ) ) {
				formData.append( 'file_' + appendCount, files[ i ] ); // won't work as image[]
				appendCount++;
			} else {
				var errorMessage = '';
				// If this is the only file we're attempting to upload, send a shorter message
				if ( 1 === files.length ) {
					if ( 0 === filetype.length ) {
						errorMessage = o2.strings.unrecognizedFileType;
					} else {
						errorMessage = o2.strings.fileTypeNotSupported;
						errorMessage = errorMessage.replace( '%1$s', filetype );
					}
				} else { // include the filename in the error message since multiple files were selected
					if ( 0 === filetype.length ) {
						errorMessage = o2.strings.filenameNotUploadedNoType;
						errorMessage = errorMessage.replace( '%1$s', filename );
					} else {
						errorMessage = o2.strings.filenameNotUploadedWithType;
						errorMessage = errorMessage.replace( '%1$s', filename ).replace( '%2$s', filetype );
					}
				}
				o2.Notifications.add( {
					text: errorMessage,
					type: 'error',
					sticky: true
				} );
			}
		}
		// nothing to do?
		if ( 0 === appendCount ) {
			return;
		}

		formData.append( 'num_files', appendCount );

		timedProgress();

		// handle ajax post
		$.ajax( {
			url: o2.options.writeURL + '&nonce=' + o2.options.nonce + '&method=create&message=' + JSON.stringify( { type: 'upload' } ), // tucking this data here since the rest is for files
			data: formData,
			processData: false,
			contentType: false,
			type: 'POST',
			dataType: 'text',
			xhrFields: {
				withCredentials: true
			}
		} )
		.done( function( data ) {
			inProgress = false;
			uploadProgress.css( 'width', '100%' );

			// Splice the return data in at the last known caret position
			data = $.parseJSON( data ).data;
			var $editor = $( '.o2-editor-text' ).last(),
				caret = $editor.data( 'lastCaretPosition' ),
				val = $editor.val();
			if ( undefined === caret ) {
				caret = $editor.val().length; // no caret; append
			}
			if ( 0 === caret ) {
				val = data + val;
			} else {
				val = val.slice( 0, caret ) + data + val.slice( caret );
			}
			$editor.val( val ).change();

			// hide progress bar after it completes
			setTimeout( function() {
				uploadProgress.css( 'width', '0' );
			}, 200 );
		} )
		.fail( function( jqxhr ) {
			inProgress = false;

			// hide progress bar after it completes its transition
			setTimeout( function() {
				uploadProgress.css( 'width', '0' );
			}, 200 );

			// Notify the user with whatever message we got back from the server
			var response = $.parseJSON( jqxhr.responseText ),
				error    = '';
			if ( _.isArray( response.data.errorText ) ) {
				_.forEach( response.data.errorText, function( elem ) {
					error += '<li>' + elem[1] + ' (' + elem[0] + ')</li>';
				} );
				error = '<ul>' + error + '</ul>';
			} else {
				error = response.data.errorText;
			}
			o2.Notifications.add( {
				text: error,
				type: 'error',
				sticky: true
			} );
		} );
	} // end onDrop
};

} )( jQuery );

o2Editor.load();
