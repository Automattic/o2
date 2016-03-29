/* global o2Editor */
/*
 * A Post View is where we actually put the goodies on the page.
 * The Post View renders 1) the Post and 2) its Comments collection
 * If new items are added to the Comments collection, this
 * view gets them onto the screen in the right position
 * in the thread
 */

var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.Post = ( function( $ ) {
	return wp.Backbone.View.extend( {
		tagName: function() {
			if ( 'undefined' !== typeof( o2.options.threadContainer ) ) {
				return o2.options.threadContainer;
			}
			return 'article';
		},

		className: function() {
			return this.model.get( 'cssClasses' );
		},

		id: function() {
			return 'post-' + this.model.get( 'postID' );
		},

		model: o2.Models.Post,

		collection: o2.Collections.Comments,

		defaults: function() {
			return {
				showNavigation: false,
				highlightOnAdd: true, /* whether newly added fragments should be highlighted once inview */
				showComments: true,
				showTitle: true,
				userMustBeLoggedInToComment: true,
				requireUserNameAndEmailIfNotLoggedIn: false,
				threadCommentsDepth: 3,
				loginURL: '',
				isEditing: false,
				ignoreEdit: false,
				isSaving: false,
				viewFormat: 'aside',
				currentUser: {
					userLogin: '',
					canEditPosts: false,
					canEditOthersPosts: false,
					canPublishPosts: false
				},
				isDragging: false
			};
		},

		initialize: function( options ) {
			_.bindAll( this, 'onSaveSuccess', 'onSaveError' );

			// A place to keep a reference to each subview we create for this post
			this.subviews = {};

			// Events from the post model (e.g. changes to post content)
			this.listenTo( this.model,          'change',    this.renderPost );

			// Events from the comment collection
			this.listenTo( this.model.comments, 'add',       this.addOneComment );
			this.listenTo( this.model.comments, 'reset',     this.addAllCommentViews );
			this.listenTo( this.model.comments, 'change:id', this.renderCommentDisclosure );
			this.listenTo( this, 'ignoreEditAction', this.onIgnoreEditAction );

			this.options = this.defaults();
			this.options = _.extend( this.options, options );

			// Event handlers
			o2.Events.dispatcher.bind( 'open-reply.o2',               this.openReply,     this );
			o2.Events.dispatcher.bind( 'update-post-view-options.o2', this.updateOptions, this );
			o2.Events.dispatcher.bind( 'cancel-edits.o2',             this.onCancelEdits, this );

			// Copy the model's postFormat into the viewFormat
			this.options.viewFormat = this.model.get( 'postFormat' );
		},

		events: {
			'click .o2-edit':                    'onEdit',
			'click .o2-trash':                   'onTrash',
			'click .o2-cancel':                  'onCancel',
			'click .o2-save':                    'onSave',
			'click .o2-reply':                   'onReply',
			'click .o2-comment-reply':           'onReply',
			'click .o2-scroll-to-comments':      'onScrollToComments',
			'click .o2-display-comments-toggle': 'onToggleComments',
			'click .more-link':                  'onMore',
			'keydown':                           'onKeyDown',
			'click .o2-new-comment-cancel':      'onNewCommentCancel',
			'click .o2-editor-format':           'onFormat',

			'touchstart':                        'onTouchStart',
			'touchmove':                         'onTouchMove',

			'touchend .o2-edit':                 'onEdit',
			'touchend .o2-trash':                'onTrash',
			'touchend .o2-reply':                'onReply',
			'touchend .o2-comment-reply':        'onReply',
			'touchend .o2-scroll-to-comments':   'onScrollToComments',
			'touchend .o2-cancel':               'onCancel',
			'touchend .o2-save':                 'onSave',
			'touchend .o2-new-comment-cancel':   'onNewCommentCancel'
		},

		// keep track of whether a drag is in progress
		onTouchStart: function() {
			this.options.isDragging = false;
		},

		onTouchMove: function() {
			this.options.isDragging = true;
		},

		onIgnoreEditAction: function( setting ) {
			this.options.ignoreEdit = setting;
		},

		onKeyDown: function( event ) {
			// if command+return were pressed, consume the event and save the form
			if ( event.metaKey && ! event.ctrlKey && ( 13 === event.keyCode ) ) {
				this.onSave( event );
			}
		},

		onToggleComments: function( event ) {
			event.preventDefault();
			this.updateOptions( { showComments : ! this.options.showComments } );
		},

		onMore: function( event ) {
			event.preventDefault();

			var isVisible = this.$el.find( '.o2-extended-more' ).is( ':visible' );
			this.setExtendedTextVisibility( ! isVisible );
		},

		setExtendedTextVisibility: function( visible ) {
			if ( visible ) {
				this.$el.find( '.more-link' ).text( o2.options.hideExtended );
				this.$el.find( '.o2-extended-more' ).show();
			} else {
				this.$el.find( '.more-link' ).text( o2.options.showExtended );
				this.$el.find( '.o2-extended-more' ).hide();
			}
		},

		onEdit: function( event ) {
			event.preventDefault();
			event.stopPropagation();
			if ( this.options.ignoreEdit ) {
				return;
			}
			o2.Events.dispatcher.trigger( 'cancel-edits.o2' ); // Ask peer views to cancel other open edits
			this.options.isEditing = true;
			this.renderPost();
			o2.Events.doAction( 'toggle-edit.o2', this.options.isEditing );
		},

		onTrash: function( event ) {
			event.preventDefault();
			event.stopPropagation();

			var postId = parseInt( o2.options.postId, 10 );

			if ( 0 === postId ) {

				// If currently on a list view, slide the post up then proceed with the destroy.
				this.$el.slideUp( this.destroyViewModel( this, postId ) );
			} else {

				// Check if there is a postTrashedFailed notification and remove if so
				o2.Notifications.notifications.findFirstAndDestroy( 'postTrashedFailed' );

				var trashString = ( 'page' === o2.options.viewType ) ? 'pageBeingTrashed' : 'postBeingTrashed';

				o2.Notifications.add( {
					text: o2.strings[ trashString ],
					url: false,
					type: 'postBeingTrashed',
					sticky: false,
					popup: false,
					dismissable: true
				} );

				this.destroyViewModel( this, postId );
			}
		},

		destroyViewModel: function( view, postId ) {

			view.model.destroy({
				wait: true,
				success: function() {

					// If on a single post/page view, then redirect to home.
					if ( 0 !== postId ) {

						// Check if there is a postBeingTrashed notification and remove if so
						o2.Notifications.notifications.findFirstAndDestroy( 'postBeingTrashed' );

						var redirectedHomeString = ( 'page' === o2.options.viewType ) ? 'redirectedHomePageTrashed' : 'redirectedHomePostTrashed';

						o2.Notifications.add( {
							text: o2.strings[ redirectedHomeString ],
							url: false,
							type: 'redirectedHome',
							sticky: false,
							popup: false,
							dismissable: true
						} );

						window.location.href = o2.options.searchURL;
					}
				},
				error: function() {

					// Remove any actions menus that are currently open.
					view.closeOpenDisclosures();

					// Check if there is a postBeingTrashed notification and remove if so
					o2.Notifications.notifications.findFirstAndDestroy( 'postBeingTrashed' );

					// If the destroy failed, show the post again.
					view.$el.slideDown();
					o2.Notifications.add( {
						text: o2.strings.trashFailedString,
						url: false,
						type: 'postTrashedFailed',
						sticky: false,
						popup: true,
						dismissable: true
					} );
				}
			});
		},

		onCancel: function( event ) {
			if ( this.options.isDragging ) {
				return false;
			}

			event.preventDefault();
			event.stopPropagation();

			if ( this.options.isEditing ) {
				o2Editor.finished( this.model.get( 'id' ), 0 );
				this.options.isEditing = false;
				this.options.viewFormat = this.model.get( 'postFormat' );
				this.renderPost();
				o2.$body.trigger( 'post-load', { 'html' : '' } ).trigger( 'pd-script-load' );
			}

			o2.Events.doAction( 'toggle-edit.o2', this.options.isEditing );
		},

		onSave: function( event ) {
			if ( this.options.isDragging ) {
				return false;
			}

			o2.Events.doAction( 'pre-post-save.o2' );
			event.preventDefault();
			event.stopImmediatePropagation();

			var requiredInputMissing = false;

			// Clear any errors
			this.$el.find( '.o2-error' ).removeClass( 'o2-error' );

			// Assemble a new, temporary model from the form content
			// Grab the content from the actual textarea (the last one), not the autosize hidden one
			var modelToSave = {};
			modelToSave.contentRaw = this.$el.find( '.o2-editor-text' ).last().val();

			modelToSave.contentFiltered = o2.Utilities.rawToFiltered( modelToSave.contentRaw );
			modelToSave.titleRaw = this.$el.find( '.o2-title' ).val();
			modelToSave.titleFiltered = modelToSave.titleRaw;
			modelToSave.postFormat = this.options.viewFormat; // retrieve from the view

			if ( modelToSave.contentRaw.length < 1 ) {
				this.$el.find( '.o2-editor-text' ).addClass( 'o2-error' );
				requiredInputMissing  = true;
			}

			if ( ! requiredInputMissing ) {
				// @todo write a validate() method for Fragment model?
				// note:  we did the above to make it easier to highlight
				// bad fields in the view

				this.options.isEditing = false;
				this.options.isSaving = true;
				this.renderPost();
				o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: true } );
				this.model.save( modelToSave, { success: this.onSaveSuccess, error: this.onSaveError } );
			}
		},

		onSaveSuccess: function( model ) {
			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: false } );
			this.options.isEditing = false;
			this.options.isSaving = false;
			o2Editor.finished( this.model.get( 'postID' ), 0 );
			this.renderPost();
			o2.Events.doAction( 'post-post-save.o2', model );
		},

		onSaveError: function( model, xhr ) {
			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: false } );
			var errorText = '';

			try {
				// See if the XHR responseText is actually a JSONified object
				var responseObject = $.parseJSON( xhr.responseText );
				if ( ( 'undefined' !== typeof responseObject.data.errorText ) ) {
					errorText = responseObject.data.errorText;
				}
			} catch ( e ) {
				// Not JSON - use the responseText directly
				errorText = xhr.responseText;
			}

			o2.Notifications.add( {
				type: 'error',
				text: errorText,
				sticky: true
			} );

			this.options.isEditing = true;
			this.options.isSaving = false;
			this.renderPost();
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
			var titleEditWrapper = this.$el.find( '.o2-editor-title-wrapper' );
			var titleWrapper = this.$el.find( '.entry-title' );

			formatControl.removeClass( 'o2-editor-format-standard' ).removeClass( 'o2-editor-format-aside' ).removeClass( 'o2-editor-format-status' );

			if ( 'aside' === this.options.viewFormat ) {
				formatControl.addClass( 'o2-editor-format-aside' );
				titleEditWrapper.hide();
				titleWrapper.hide();
			} else {
				formatControl.addClass( 'o2-editor-format-standard' );
				titleEditWrapper.show();
				titleWrapper.show();
			}
		},

		openReply: function ( args ) {
			var newComment, editor;

			if ( ! this.model.get( 'commentsOpen' ) ) {
				return;
			}

			if ( this.options.userMustBeLoggedInToComment && ! this.options.currentUser.userLogin.length ) {
				return;
			}

			if ( this.model.get( 'id' ) === args.postID ) { // only if the message is for us
				// If we weren't showing comments on this post, force them open.
				if ( ! this.options.showComments ) {
					this.onToggleComments( jQuery.Event( 'click' ) );
				}

				// Ask peers to cancel other open edits
				o2.Events.dispatcher.trigger( 'cancel-edits.o2' );

				// Set a comment parent ID of 0 if none given in the args
				if ( 'undefined' === typeof args.parentCommentID ) {
					args.parentCommentID = 0;
				}

				// Set a parent comment depth of 0 if none given in the args
				if ( 'undefined' === typeof args.parentDepth ) {
					args.parentDepth = 0;
				}

				newComment = new o2.Models.Comment( {
					'parentID':   args.parentCommentID,
					'depth':      args.parentDepth + 1,
					'contentRaw': '',
					'postID':     this.model.get( 'id' ),
					'type':       'comment',
					'userLogin':  this.options.currentUser.userLogin
				} );

				// Add directly to the comments collection of our post
				this.model.comments.add( newComment );

				editor = this.$el.find( '.o2-editor-text' ).last();

				if ( ! o2Editor.quoteSelection( editor ) && args.focus ) {
					editor.focus();
				}

				if ( o2.options.followingBlog ) {
					$( '#respond .post-subscription-form' ).hide();
				}
			}
		},

		onReply: function( event ) {
			if ( this.options.isDragging ) {
				return false;
			}

			event.preventDefault();
			event.stopImmediatePropagation();

			// Comments directly on the post originate from .o2-post-reply targets
			// Comments on comments originate from .o2-comment-reply targets

			var parentCommentID = 0;
			var parentDepth = 0;
			var $target = $( event.target );
			if ( $target.hasClass( 'o2-comment-reply' ) ) {
				parentCommentID = $target.closest( '.o2-comment' ).find( '.o2-comment-metadata' ).first().data( 'o2-comment-id' );
				if ( 0 < parentCommentID ) {
					var parentModel = this.model.comments.get( parentCommentID );
					parentDepth = parentModel.get( 'depth' );
				}
			}

			this.openReply( { postID : this.model.get( 'id' ), parentCommentID: parentCommentID, parentDepth: parentDepth, focus: true } );
		},

		onScrollToComments: function( event ) {
			event.preventDefault();
			event.stopImmediatePropagation();

			var $target = $( event.currentTarget ).closest( 'article' ).find( '.o2-post-comments' );
			$( 'html, body' ).animate( {
				scrollTop: $target.offset().top - 50
			}, 2000 );
		},

		updateOptions: function( options ) {
			// If a postID is specifed in the options, make sure it is ours before we
			// act on the options update
			if ( 'undefined' !== typeof( options.postID ) ) {
				if ( options.postID !== this.model.get( 'id' ) ) {
					return;
				}
			}

			this.options = _.extend( this.options, options );

			// Update visibility if showComments was included
			if ( 'undefined' !== typeof( options.showComments ) ) {
				this.updateCommentVisibility();
			}
		},

		/*
		 * This method renders the entire thread, and so should be used sparingly.
		 * Only specific model changes or option changes should trigger this method.
		 * It is usually more appropriate to have a model changes trigger renderPost,
		 * or collection model changes to trigger a specific Comment View's render
		 */
		render: function() {
			// First, render the overall thread template
			var template = o2.Utilities.Template( this.options.template );

			// JSONify the model and add selected view attributes to it
			var jsonifiedModel = this.model.toJSON();
			jsonifiedModel.showNavigation = this.options.showNavigation;
			jsonifiedModel.strings = o2.strings;

			// Run the template
			this.$el.html( template( jsonifiedModel ) );

			// Render the post into its placeholder
			this.renderPost();
			this.renderCommentDisclosure();
			this.renderComments();

			return this;
		},

		renderComments: function() {
			this.$el.find( '.o2-post-comments' ).html( '' );

			// Show/hide the comments and discussion divs
			this.updateCommentVisibility();

			// Render the comment collection into placeholder elements
			// Suspend highlighting during this loop
			// (Only want it to happen when addOne is triggered by the collection event)
			var cached_highlight = this.options.highlightOnAdd;
			this.options.highlightOnAdd = false;
			this.model.comments.forEach( this.addOneCommentView, this );
			// Put the setting back
			this.options.highlightOnAdd = cached_highlight;

			return this;
		},

		updateCommentVisibility: function() {
			this.renderCommentDisclosure();

			if ( this.options.showComments ) {
				this.$el.find( '.o2-post-comments' ).show();
			} else {
				this.$el.find( '.o2-post-comments' ).hide();
			}
		},

		/*
		 * We break out renderPost separately so we can re-render just the
		 * post without disturbing / re-rendering the comments (i.e. someone
		 * might be editing when the post gets edited by someone else)
		 */
		renderPost: function() {
			var template;
			if ( this.model.get( 'is_xpost' ) ) {
				template = o2.Utilities.Template( 'xpost' );
			} else {
				template = this.options.isEditing ? o2.Utilities.Template( 'post-edit' ) : o2.Utilities.Template( 'post' );
			}

			this.options.viewFormat = this.model.get( 'postFormat' );

			// When re-rendering, make a note if extended text has already been toggled visible so we can make sure it
			// is still in that same state when we're done rendering
			var extendedIsVisible = this.$el.find( '.o2-extended-more' ).is( ':visible' );

			// JSONify the model, add view options, and selected attributes
			var jsonifiedModel = this.model.toJSON();
			$.extend( true, jsonifiedModel, this.options );

			jsonifiedModel.strings = o2.strings;

			// Grab the post author info from the user cache and add it to the jsonified model
			jsonifiedModel.author = o2.UserCache.getUserFor( this.model.attributes, 48 );

			// Some useful meta data
			jsonifiedModel.commentCount = this.model.comments.length;

			var postID = this.model.get( 'id' );
			if ( _.isUndefined( postID ) ) {
				postID = 'new';
			}

			// Run the template
			var post = this.$el.find( '.o2-post' );
			post.html( template( jsonifiedModel ) );
			post.parent().attr( 'id', 'post-' + postID );
			post.parent().attr( 'class', jsonifiedModel.cssClasses );
			post.parent().data( 'post-id', postID );

			// Restore the extended visibility as needed
			if ( extendedIsVisible ) {
				this.setExtendedTextVisibility( true );
			}

			this.renderCommentDisclosure();

			if ( 'page' === o2.options.viewType ) {
				$( '.o2-app-page-title' ).html( jsonifiedModel.titleFiltered );
			}

			if ( this.options.isEditing ) {
				// If the post is now being edited, fire up the editor and point it at the post
				o2Editor.detectAndRender( this.$el );
				o2Editor.create( this.$el.find( '.o2-editor-text' ), postID, 0 );
			}

			// Format unixtime dates to localized date and time
			this.$( '.o2-timestamp' ).each( function() {
				o2.Utilities.timestamp( $( this ) );
			} );

			this.updateFormattedControls();

			// Events
			this.model.trigger( 'o2-post-rendered' );
			o2.$body.trigger( 'pd-script-load' );
		},

		addOneComment: function( comment ) {
			var commentUserLogin = comment.get( 'userLogin' );
			var commentNoprivUserName = comment.get( 'noprivUserName' );

			if ( this.options.currentUser.userLogin !== commentUserLogin ) {
				var commentUserModel = o2.UserCache.getUserFor( comment.attributes, 32 );

				var text = '';
				if ( comment.get( 'mentionContext' ) ) {
					text = o2.strings.newMentionBy.replace( '%1$s', commentUserModel.displayName ).replace( '%2$s', comment.get( 'mentionContext' ) );
				} else if ( 0 !== commentUserModel.displayName.length ) {
					text = o2.strings.newCommentBy.replace( '%s', commentUserModel.displayName );
				} else if ( 0 !== commentUserLogin.length ) {
					text = o2.strings.newCommentBy.replace( '%s', commentUserLogin );
				} else if ( 0 !== commentNoprivUserName.length ) {
					text = o2.strings.newCommentBy.replace( '%s', commentNoprivUserName );
				} else {
					text = o2.strings.newAnonymousComment;
				}

				o2.Notifications.add( {
					source: this,
					text: text,
					textClass: commentUserModel.modelClass,
					unixtime: comment.get( 'unixtime' ),
					url: '#comment-' + comment.get( 'id' ),
					postID: comment.get( 'postID' ),
					iconUrl: commentUserModel.avatar,
					iconSize: commentUserModel.avatarSize,
					iconClass: commentUserModel.modelClass
				} );
			}

			this.addOneCommentView( comment );
		},

		/*
		 * addOne adds one comment from the comments collection to the appropriate
		 * part of this Thread View
		 */
		addOneCommentView: function( comment ) {
			var commentingAllowed = this.model.get( 'commentsOpen' ); /* initial view state is based on the post commentsOpen flag itself */
			if ( commentingAllowed ) {
				var depth = comment.get( 'depth' );
				if ( depth >= this.options.threadCommentsDepth ) {
					commentingAllowed = false;
				}
			}

			var commentView = new o2.Views.Comment( {
				model: comment,
				parent: this,
				showTitle: this.options.showTitle,
				commentingAllowed: commentingAllowed,
				currentUser: this.options.currentUser,
				userMustBeLoggedInToComment: this.options.userMustBeLoggedInToComment,
				requireUserNameAndEmailIfNotLoggedIn: this.options.requireUserNameAndEmailIfNotLoggedIn
			} );

			// Save a reference to the comment view so we can remove it properly later
			this.subviews[ commentView.model.cid ] = commentView;

			///	With the new scheme, the comment view should be added directly to .o2-post-comments
			///	which is defined in tpl/post-view.php

			// Set its id for finding later
			var commentID = comment.get( 'id' );
			if ( 'undefined' === typeof( commentID ) || ! commentID ) {
				commentID = 'new';
			}

			var parentCommentID      = comment.get( 'parentID' ),
				indexOf              = this.model.comments.indexOf( comment ),
				containerToAddTo     = this.$el.find( '.o2-post-comments' ),
				insertCommentCreated = comment.get( 'commentCreated' );

			if ( 0 < parentCommentID ) {
				//find the tag with id 'comment-xxx' where xxx is the parent comment's ID
				var parentCommentContainer = containerToAddTo.find( '#comment-' + parentCommentID );

				// see if it already has an __immediate__ child div of .o2-child-comments and if not, then create it
				// set the containerToAddTo to the __immediate__ child div of  .2-child-comments
				containerToAddTo = parentCommentContainer.children( '.o2-child-comments' );
				if ( ! containerToAddTo.length ) {
					parentCommentContainer.append( '<div class="o2-child-comments"></div>' );
					containerToAddTo = parentCommentContainer.children( '.o2-child-comments' );
				}
			}

			/*
			 * Now that we know which container to add the comment view to,
			 * we need to place the comment in the correct position within that container.
			 *
			 * If comment has index of 0, just prepend.
			 *
			 * If comment is 'new', then it is a comment we have just added, so append
			 * to end of the container.
			 *
			 * Else, the comment's correct position is determined iterating through
			 * children of container to find correct position using comment created date.
			 *
			 */
			if ( 0 === indexOf ) {
				containerToAddTo.prepend( commentView.render().el );
			} else if ( 'new' === commentID ) {
				containerToAddTo.append( commentView.render().el );
			} else {
				var containerChildren = containerToAddTo.children( '.o2-comment' ),
					beforeView        = false,
					afterView         = false;

				if ( 0 === containerChildren.length ) {
					containerToAddTo.append( commentView.render().el );
				} else {

					containerChildren.each( function(){
						var childCreated = $( this ).data( 'created' );

						if ( childCreated < insertCommentCreated ) {
							beforeView = $( this );
						} else if ( childCreated >= insertCommentCreated ) {
							afterView = $( this );
						}
					});

					if ( false !== beforeView ) {
						beforeView.after( commentView.render().el );
					} else if ( false !== afterView ) {
						afterView.before( commentView.render().el );
					}
				}
			}

			// Only highlight if the comment added has an ID
			// and comment is not being reflowed after another comment has been deleted.
			if ( ! comment.isNew() ) {
				if ( ! comment.has( 'reflow' ) && ! comment.get( 'prevDeleted' ) ) {
					if ( this.options.highlightOnAdd ) {
						commentView.$el.one( 'inview', o2.Utilities.HighlightOnInview );
					}
				}
			}

			this.renderCommentDisclosure(); // update the post discussion disclosure control

			// If there's a comment form open on this thread, then shift it to the bottom of the stack
			var replyBox = containerToAddTo.children( '.comment-new' );
			if ( replyBox.length ) {
				containerToAddTo.append( replyBox.detach() );
			}

			// Bind jquery placeholder for older, less capable browsers (e.g. IE9)
			// for nopriv commenting fields
			this.$el.find( '.o2-comment-name' ).placeholder();
			this.$el.find( '.o2-comment-email' ).placeholder();
			this.$el.find( '.o2-comment-url' ).placeholder();
		},

		addAllCommentViews: function() {
			this.model.comments.forEach( this.addOneCommentView, this );
		},

		renderCommentDisclosure: function() {
			var disclosure = this.$el.find( '.o2-display-comments-toggle' );

			// If there are no comments, or it's an xpost, then hide and bail
			if ( ! this.model.comments.length || this.model.get( 'is_xpost' ) ) {
				disclosure.hide();
				return;
			} else {
				var disclosureText = disclosure.find( '.disclosure-text' ),
					disclosureIcon = disclosure.find( '.genericon' );

				if ( this.options.showComments ) {
					disclosureText.html( o2.strings.hideComments );
					disclosureIcon.removeClass( 'genericon-expand' );
					disclosureIcon.addClass( 'genericon-collapse' );
				} else {
					disclosureText.html( o2.strings.showComments );
					disclosureIcon.removeClass( 'genericon-collapse' );
					disclosureIcon.addClass( 'genericon-expand' );
				}

				disclosure.show();
			}
		},

		onCancelEdits: function( ) {
			// cancel edits in progress on any views we are responsible for
			this.$el.find( '.o2-cancel' ).trigger( 'click' );
			this.$el.find( '.o2-new-comment-cancel' ).trigger( 'click' );
			this.$el.find( '.o2-comment-cancel' ).trigger( 'click' );
		},

		/*
		 * If a NEW comment is being cancelled, we need to kick off a destroy of the model here
		 * and not in the comment view itself, to avoid a reference that would result in a detached dom node (memory leak)
		 */
		onNewCommentCancel: function( event ) {
			if ( this.options.isDragging ) {
				return false;
			}

			event.preventDefault();

			// find the corresponding view in our subview list
			var subviewCID = $( event.target ).closest( '.o2-comment' ).data( 'cid' );
			var subview = this.subviews[ subviewCID ];

			// remove this comment view's model from our comment collection
			this.model.comments.remove( subview.model );
			// close the subview editor
			subview.finishEditor( { keepCache: true } );
			// destroy the model (which will trigger the view removal)
			subview.model.destroy();
			// update our subview list
			delete this.subviews[ subviewCID ];

			this.renderCommentDisclosure();
		}
	} );
} )( jQuery );
