/* global o2Editor */
var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.FrontSidePost = ( function( $ ) {
	return wp.Backbone.View.extend( {
		model: o2.Models.Post,

		defaults: {
			postPrompt: '',
			avatarSize: 48,
			isEditing: true,
			isSaving: false,
			viewFormat: 'aside',
			currentUser: {
				userLogin: '',
				canEditPosts: false,
				canEditOthersPosts: false,
				canPublishPosts: false
			}
		},

		initialize: function( options ) {
			_.bindAll( this, 'onSaveSuccess', 'onSaveError' ); // to ensure we get the appropriate this in onSaveSuccess
			this.listenTo( this.model, 'change', this.render );
			this.options = _.extend( this.defaults, options );

			// Copy the model's postFormat into the viewFormat
			this.options.viewFormat = this.model.get( 'postFormat' );
		},

		tagName: function() {
			if ( 'undefined' !== typeof o2.options.threadContainer ) {
				return o2.options.threadContainer;
			}
			return 'article';
		},

		className: 'o2-app-new-post hentry',

		events: {
			'click .o2-save':          'onSave',
			'keydown':                 'onKeyDown',
			'click .o2-editor-format': 'onFormat'
		},

		onKeyDown: function( event ) {
			// if shift+command+return is pressed, subscribe to replies and publish
			if ( event.shiftKey && event.metaKey && ! event.ctrlKey && ( 13 === event.keyCode ) ) {
				this.$( '#post_subscribe' ).prop( 'checked', true );
				this.onSave( event );
				return;
			}

			// if command+return were pressed, consume the event and save the form
			if ( event.metaKey && ! event.ctrlKey && ( 13 === event.keyCode ) ) {
				this.onSave( event );
			}
		},

		activateEditor: function( ) {
			this.$el.find( '.o2-editor-toolbar' ).show();
			this.$el.find( '.o2-post-form-options' ).show();
		},

		onSave: function( event ) {
			o2.Events.doAction( 'pre-post-save.o2' );
			event.preventDefault();
			event.stopImmediatePropagation();

			// Grab the content from the actual textarea (the last one), not the autosize hidden one
			var contentRaw = this.$el.find( '.o2-editor-text' ).last().val();
			var titleRaw = this.$el.find( '.o2-title' ).val();

			var previewContentRaw = contentRaw;
			var title = '';

			// If no title has been set, see if one is going to be auto-generated
			if ( ! titleRaw && o2Editor.firstLineIsProbablyATitle( contentRaw ) ) {
				title = contentRaw.split( '\n' )[0];
				previewContentRaw = previewContentRaw.replace( title, '' ).trim();
			}

			var contentFiltered = o2.Utilities.rawToFiltered( previewContentRaw );
			// If there is an auto-title, add it to the content
			if ( title ) {
				contentFiltered = '<h1><a href="#">' + title + '</a></h1>' + contentFiltered;
			}

			var isFollowing = $( '#post_subscribe' ).prop( 'checked' );

			// Clear the editor
			o2Editor.finished( 'new', 0 );

			this.options.isEditing = false;
			this.options.isSaving = true;
			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: true } );

			// Create a model for the new post, and immediately add to collection
			var clientModel = new o2.Models.Post( {
				userLogin: this.options.currentUser.userLogin,
				unixtime: Math.round( +new Date() / 1000 ),
				contentRaw: contentRaw,
				titleRaw: titleRaw,
				titleFiltered: titleRaw,
				contentFiltered: contentFiltered,
				isFollowing: isFollowing,
				disableAutoTitle: o2Editor.isFirefox, // Force auto-title off in Firefox
				postFormat: this.options.viewFormat // retrieve from the view
			} );
			o2.App.posts.add( clientModel ); // @todo we've made the view coupled to the app here - could we use an event?

			var data = {
				contentRaw: contentRaw,
				titleRaw: titleRaw,
				isFollowing: isFollowing
			};
			data = o2.Events.applyFilters( 'post-save-data.o2', data, this );
			clientModel.save( data, {
				success: this.onSaveSuccess,
				error: this.onSaveError
			} );

			// Scroll to the new post, which will be rendered immediately
			// Delay required to allow the editor to resize
			setTimeout( function() {
				$( 'html, body' ).animate( {
					scrollTop: $( '#post-new' ).offset().top - 50
				}, 1000 );
			}, 200 );

			// Reset things and get ready for another post
			this.options.isEditing = true;
			this.options.isSaving = false;
			this.model.trigger( 'change', this.model ); // kicks off a re-render
		},

		onSaveSuccess: function( model ) {
			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: false } );

			o2.$body.trigger( 'pd-script-load' ).trigger( 'post-load', { 'html' : '' } );
			o2.Events.doAction( 'post-post-save.o2', model );
		},

		onSaveError: function( model, xhr ) {
			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: false } );

			// Remove the temporarily-added version
			o2.App.posts.remove( o2.App.posts.first(), { silent: true } );
			$( '#post-new' ).remove();

			// Reset things ready to edit
			this.options.isEditing = true;
			this.model = model; // Re-render what we tried to save
			this.render();

			// Notify the user
			var responseText = '';
			var errorText = '';
			try {
				responseText = $.parseJSON( xhr.responseText );
				errorText = responseText.errorText;
			} catch ( e ) {
				// Not JSON - use the responseText directly
				errorText = xhr.responseText;
			}
			o2.Notifications.add( {
				type: 'error',
				text: errorText,
				sticky: true
			} );
		},

		onFormat: function( event ) {
			event.preventDefault();
			// get the current view format and switch to the opposite
			if ( 'standard' === this.options.viewFormat ) {
				this.options.viewFormat = 'aside';
			} else {
				this.options.viewFormat = 'standard';
			}
			this.updateFormattedControls();
		},

		updateFormattedControls: function() {
			var formatControl = this.$el.find( '.o2-editor-format' );
			var titleWrapper = this.$el.find( '.o2-editor-title-wrapper' );

			formatControl.removeClass( 'o2-editor-format-standard' ).removeClass( 'o2-editor-format-aside' ).removeClass( 'o2-editor-format-status' );

			var editor = this.$el.find( '.o2-editor-text' ).last();
			var text = editor.val();
			var title;

			if ( 'standard' === this.options.viewFormat ) {
				if ( o2Editor.firstLineIsProbablyATitle( text ) ) {
					title = text.split( '\n' )[0];
					this.$el.find( '.o2-editor-title' ).val( title );

					text = text.replace( title, '' ).trim();
					editor.val( text );
				} else {
					this.$el.find( '.o2-editor-title' ).val( '' );
				}
				editor.removeClass( 'o2-editor-title-line' );
				editor.data( 'autoTitleDisabled', true );

				formatControl.addClass( 'o2-editor-format-standard' );
				titleWrapper.slideDown( 'fast' );
			} else {
				editor.data( 'autoTitleDisabled', false );

				title = this.$el.find( '.o2-editor-title' ).val();
				if ( title ) {
					this.$el.find( '.o2-editor-title' ).val( ' ' );
					text = title + '\n\n' + text;
					editor.val( text );
				}
				if ( o2Editor.firstLineIsProbablyATitle( text ) ) {
					editor.addClass( 'o2-editor-title-line' );
				}

				formatControl.addClass( 'o2-editor-format-aside' );
				titleWrapper.slideUp( 'fast', function() {
					$( this ).hide();
				} );
			}
		},

		render: function() {
			var template = o2.Utilities.Template( this.options.template );
			var jsonifiedModel = this.model.toJSON();
			jsonifiedModel.userLogin = this.options.currentUser.userLogin;
			jsonifiedModel.author = o2.UserCache.getUserFor( this.model.attributes, this.options.avatarSize );
			if ( 'firstName' in jsonifiedModel.author && jsonifiedModel.author.firstName ) {
				jsonifiedModel.postPrompt = this.options.postPrompt.replace( '{name}', jsonifiedModel.author.firstName );
			} else if ( 'displayName' in jsonifiedModel.author && jsonifiedModel.author.displayName ) {
				jsonifiedModel.postPrompt = this.options.postPrompt.replace( '{name}', jsonifiedModel.author.displayName );
			} else {
				jsonifiedModel.postPrompt = this.options.postPrompt.replace( '{name}', jsonifiedModel.author.userLogin );
			}
			jsonifiedModel.avatarSize = this.options.avatarSize;
			jsonifiedModel.strings = o2.strings;
			jsonifiedModel.postFormBefore = o2.postFormBefore;
			jsonifiedModel.postFormExtras = o2.postFormExtras;

			// If we are on a tag archive page, add the tag automatically to an empty editor
			if ( ( 'archive' === o2.options.viewType ) && ( 'undefined' !== typeof o2.options.queryVars.tag ) ) {
				if ( 0 === jsonifiedModel.contentRaw.length ) {
					jsonifiedModel.contentRaw = '#' + o2.options.queryVars.tag;
				}
			}

			this.$el.html( template( jsonifiedModel ) );

			o2Editor.detectAndRender( this.$el );
			o2Editor.create( this.$el.find( '.o2-editor-text' ).last(), 'new', 0 );

			// Format unixtime dates to localized date and time
			this.$( '.o2-timestamp' ).each( function() {
				o2.Utilities.timestamp( $( this ) );
			} );

			this.updateFormattedControls();

			o2.Events.doAction( 'frontside-post-rendered.o2' );
			return this;
		}
	} );
} )( jQuery );
