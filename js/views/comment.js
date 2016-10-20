/*
 * Views.Comment renders a Models.Comment
 */

/* global o2Editor, Gravatar */
var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.Comment = ( function( $ ) {
	return wp.Backbone.View.extend( {
		model: o2.Models.Fragment,

		defaults: function() {
			return {
				editingAllowed: false,
				userMustBeLoggedInToComment: true,
				requireUserNameAndEmailIfNotLoggedIn: false,
				loginRedirectCommentUrl: '',
				commentingAllowed: false,
				showTitle: true,
				isEditing: false,
				ignoreEdit: false,
				isSaving: false,
				isTrashedAction: false,
				avatarSize: 32,
				currentUser: {
					userLogin: '',
					canEditPosts: false,
					canEditOthersPosts: false,
					canPublishPosts: false
				}
			};
		},

		initialize: function( options ) {
			_.bindAll( this, 'onSaveSuccess', 'onSaveError' );

			this.parent = options.parent; // save a reference to the o2.Views.Post view we belong to
			this.options = this.defaults();
			this.options = _.extend( this.options, options );

			this.listenTo( this.model, 'change',  this.render );
			this.listenTo( this.model, 'destroy', this.remove );
			this.listenTo( this.model, 'remove',  this.remove );
			this.listenTo( this, 'ignoreEditAction', this.onIgnoreEditAction );

			// Update editingAllowed
			if ( this.options.currentUser.userLogin.length ) {
				if ( this.model.get( 'userLogin' ) === this.options.currentUser.userLogin ) {
					this.options.editingAllowed = this.options.currentUser.canEditPosts;
				} else {
					this.options.editingAllowed = this.options.currentUser.canEditOthersPosts;
				}
			} else {
				this.options.editingAllowed = false;
			}

			// Update commentingAllowed
			if ( this.options.commentingAllowed ) { /* set by Thread when view was created from post's commentsOpen */
				/* let's tighten a bit based on blog options and if the user is logged in */
				if ( ! this.options.userMustBeLoggedInToComment ) {
					this.options.commentingAllowed = true;
				} else if ( this.options.userMustBeLoggedInToComment && this.options.currentUser.userLogin.length ) {
					this.options.commentingAllowed = true;
				} else {
					this.options.commentingAllowed = false;
				}
			}

			// Automatically start in editing mode for new comments
			if ( this.model.isNew() ) {
				this.options.isEditing = true;
			}
		},

		events: {
			'click    .o2-comment-edit'    : 'onEdit',
			'touchend .o2-comment-edit'    : 'onEdit',
			'click    .o2-comment-trash'   : 'onTrash',
			'touchend .o2-comment-trash'   : 'onTrash',
			'click    .o2-comment-untrash' : 'onUntrash',
			'touchend .o2-comment-untrash' : 'onUntrash',
			'click    .o2-comment-cancel'  : 'onCancel',
			'click    .o2-comment-save'    : 'onSave',
			'keydown'                      : 'onKeyDown',
			'blur     .o2-comment-email'   : 'onBlurCommentorEmail'
		},

		onIgnoreEditAction: function( setting ) {
			this.options.ignoreEdit = setting;
		},

		onKeyDown: function( event ) {
			// @todo move this into the Follow extension
			// if shift+command+return is pressed, subscribe to replies and publish
			if ( event.shiftKey && event.metaKey && ! event.ctrlKey && ( 13 === event.keyCode ) ) {
				this.$( '#subscribe' ).prop( 'checked', true );
				this.onSave( event );
				return;
			}

			// if command+return were pressed, consume the event and save the form
			if ( event.metaKey && ! event.ctrlKey && ( 13 === event.keyCode ) ) {
				this.onSave( event );
				return;
			}
		},

		onEdit: function( event ) {
			event.preventDefault();
			if ( this.options.ignoreEdit ) {
				return;
			}
			o2.Events.dispatcher.trigger( 'cancel-edits.o2' ); // Ask the app to cancel other open edits
			this.options.isEditing = true;
			this.render();

			o2.Events.doAction( 'toggle-edit.o2', this.options.isEditing );
		},

		onTrash: function( event ) {
			event.preventDefault();
			event.stopImmediatePropagation();

			this.options.isSaving = true;
			this.options.isTrashedAction = true;

			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: true } );

			var updates = {
				isTrashed:      true,
				trashedSession: true
			};

			this.model.save( updates, { success: this.onSaveSuccess,error: this.onSaveError } );
		},

		onUntrash: function( event ) {
			event.preventDefault();
			event.stopImmediatePropagation();

			this.options.isSaving = true;
			this.options.isTrashedAction = true;

			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: true } );

			var updates = {
				isTrashed:      false,
				trashedSession: false,
				approved:       true
			};

			/*
			 * Attempt to untrash the comment. In the case that the comment was already deleted,
			 * remove the comment and show an error message to user.
			 */
			var commentView = this,
				postView = this.parent;

			this.model.save(
				updates,
				{
					success: this.onSaveSuccess,
					error: function( model, xhr ) {
						commentView.model.unset( 'trashedSession', { silent: true } );
						postView.model.comments.remove( model );
						postView.updateCommentVisibility();
						commentView.onSaveError( model, xhr );
					}
				}
			);
		},

		onCancel: function( event ) {
			event.preventDefault();
			if ( this.options.isEditing ) {
				this.finishEditor();
				this.options.isEditing = false;
				this.render();
			}

			o2.Events.doAction( 'toggle-edit.o2', this.options.isEditing );
		},

		finishEditor: function( args ) {
			o2Editor.finished( this.model.get( 'postID' ), this.model.get( 'parentID' ), args );
		},

		onSave: function( event ) {
			o2.Events.doAction( 'pre-comment-save.o2' );
			event.preventDefault();
			event.stopImmediatePropagation();
			var requiredInputMissing = false;

			// Clear any errors
			this.$el.find( '.o2-error' ).removeClass( 'o2-error' );

			// Assemble a new, temporary model from the form content
			// Grab the content from the actual textarea (the last one), not the autosize hidden one
			var modelToSave = {};
			modelToSave.author = {};
			modelToSave.contentRaw = this.$el.find( '.o2-editor-text' ).last().val();

			if ( this.$el.find( '#subscribe_blog' ).prop( 'checked' ) ) {
				o2.options.followingBlog = true;
			}

			if ( modelToSave.contentRaw.length < 1 ) {
				this.$el.find( '.o2-editor-text' ).addClass( 'o2-error' );
				requiredInputMissing  = true;
			}

			if ( ! this.options.currentUser.userLogin.length ) {
				var commentName = this.$el.find( '.o2-comment-name' ).val();
				var commentEmail = this.$el.find( '.o2-comment-email' ).val();
				var commentUrl = this.$el.find( '.o2-comment-url' ).val();

				if ( this.options.requireUserNameAndEmailIfNotLoggedIn ) {
					if ( commentName.length < 1 ) {
						this.$el.find( '.o2-comment-name' ).addClass( 'o2-error' );
						requiredInputMissing = true;
					}

					if ( ! o2.Utilities.isValidEmail( commentEmail ) ) {
						this.$el.find( '.o2-comment-email' ).addClass( 'o2-error' );
						requiredInputMissing = true;
					}
				}

				modelToSave.author = {
					name: commentName,
					email: commentEmail,
					url: commentUrl
				};
			} else {
				modelToSave.author = this.options.currentUser;
			}

			if ( ! requiredInputMissing ) {
				// @todo write a validate() method for Fragment model?
				// note:  we did the above to make it easier to highlight
				// bad fields in the view

				// Render a temporary filtered version of the content to display while
				// we are saving
				modelToSave.contentFiltered = o2.Utilities.rawToFiltered( modelToSave.contentRaw );

				this.options.isEditing = false;
				this.options.isSaving = true;
				o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: true } );
				this.model.save( modelToSave, { success: this.onSaveSuccess, error: this.onSaveError } );
			}
		},

		onSaveSuccess: function( model ) {

			/*
			 * If the comment was deleted, let's find the comments collection this comment
			 * is a part of, and remove the comment model.
			 */

			if ( this.model.get( 'isDeleted' ) ) {
				this.parent.model.comments.remove( model );
				this.parent.updateCommentVisibility();
			}

			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: false } );
			o2Editor.finished( this.model.get( 'postID' ), this.model.get( 'parentID' ) );
			this.options.isEditing = false;
			this.options.isSaving = false;
			this.render();
			this.options.isTrashedAction = false;

			// Update saved model container
			var container = $( '#respond' );
			container.attr( 'id', 'comment-' + model.get( 'id' ) );
			container.removeClass().addClass( 'o2-comment ' + model.get( 'cssClasses' ) );

			o2.Events.doAction( 'post-comment-save.o2', model );
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
				// e.g. this occurs if you attempt to post the same comment twice - you get
				// a non JSON error back in the response
				errorText = xhr.responseText;
			}

			o2.Notifications.add( {
				model: model,
				type: 'error',
				text: errorText,
				sticky: true
			} );

			// Turn editing back on
			this.options.isSaving = false;
			this.options.isEditing = true;
			this.render();

			o2.Events.doAction( 'post-comment-save.o2' );
		},

		onBlurCommentorEmail: function( event ) {
			event.preventDefault();
			event.stopImmediatePropagation();

			if ( 'undefined' !== typeof Gravatar ) {
				var eventTarget = $( event.target );
				var emailAddress = $.trim( eventTarget.val().toLowerCase() );

				if ( 0 === emailAddress.length ) {
					emailAddress = 'unknown@gravatar.com';
				}

				// Fetch the gravatar
				var gravatarBase = ( 'https:' === location.protocol ? 'https://secure' : 'http://www' ) + '.gravatar.com/';
				var gravatarSrc = gravatarBase + 'avatar/' + Gravatar.md5( emailAddress ) + '?s=32&d=' + encodeURIComponent( o2.options.defaultAvatar );
				this.$el.find( 'img.avatar' ).attr( 'src', gravatarSrc );
			}
		},

		render: function() {
			var template;
			// unsaved models should not get a reply button, nor an edit button
			// until the save completes at the server and we get the "real" model back that way
			if ( this.options.currentUser.userLogin.length ) {
				template = this.options.isEditing ? o2.Utilities.Template( 'comment-edit' ) : o2.Utilities.Template( 'comment' );
			} else {
				template = this.options.isEditing ? o2.Utilities.Template( 'logged-out-create-comment' ) : o2.Utilities.Template( 'comment' );
			}

			var domID   = ( 'undefined' === typeof this.model.get( 'id' ) ) ? 'respond' : 'comment-' + this.model.get( 'id' );
			var created = ( 'undefined' === typeof this.model.get( 'commentCreated' ) ) ? ( $.now() / 1000 ) : this.model.get( 'commentCreated' );

			var cssClasses = this.model.get( 'cssClasses' );
			if ( ! _.isUndefined( cssClasses ) ) {
				this.$el.attr( 'class', cssClasses );
			}

			if ( this.options.isTrashedAction ) {
				var isTrashed = this.model.get( 'isTrashed' );
				if ( !_.isUndefined( isTrashed ) && isTrashed ) {
					this.$el.addClass( 'o2-trashed' );
				} else {
					this.$el.removeClass( 'o2-trashed' );
				}
			}

			this.$el.attr( 'id', domID );
			this.$el.data( 'created', created );
			this.$el.addClass( 'o2-comment' );

			var uniqueClass = ( 'undefined' === typeof this.model.get( 'id' ) ) ? 'comment-new' : 'comment-' + this.model.get( 'id' );
			this.$el.addClass( uniqueClass );

			var someoneElsesComment = false;
			if ( this.options.currentUser.userLogin.length && this.options.isEditing ) {
				someoneElsesComment = ( this.options.currentUser.userLogin !== this.model.get( 'userLogin' ) );
			}

			// JSONify the model, add view options, and selected attributes
			var jsonifiedModel = this.model.toJSON();
			$.extend( true, jsonifiedModel, this.options );

			jsonifiedModel.isNew = this.model.isNew();
			jsonifiedModel.isAnonymousAuthor = ( 0 === jsonifiedModel.userLogin.length ) && ( 0 === jsonifiedModel.noprivUserName.length );
			jsonifiedModel.strings = o2.strings;
			jsonifiedModel.commentFormBefore = o2.commentFormBefore;
			jsonifiedModel.commentFormExtras = o2.commentFormExtras;
			jsonifiedModel.someoneElsesComment = someoneElsesComment;

			// If this is a new (not persisted to the server) comment, use a temporary id of new
			// to avoid emitting "#comment-undefined" into the DOM as its id
			if ( 'undefined' === typeof jsonifiedModel.id ) {
				jsonifiedModel.id = 'new';
			}

			if ( 'post' === this.model.get( 'type' ) ) {
				jsonifiedModel.avatarSize = 48;
			}

			if ( this.model.get( 'is_xpost' ) ) {
				jsonifiedModel.avatarSize = 22;
			}

			// Grab the comment author info from the user cache and add it to the
			// jsonified model
			jsonifiedModel.author = o2.UserCache.getUserFor( this.model.attributes, this.options.avatarSize );

			// Remove every child that is not .o2-child-comments (leave the children alone!)
			this.$el.children().not( '.o2-child-comments' ).remove();
			// Prepend the templated model
			this.$el.prepend( template( jsonifiedModel ) );

			if ( this.options.isEditing ) {
				o2Editor.detectAndRender( this.$el );
				o2Editor.create(
					this.$el.find( '.o2-editor-text' ),
					this.model.get( 'postID' ),
					this.model.get( 'parentID' )
				);
			}

			// Format unixtime dates to localized date and time
			this.$( '.o2-timestamp' ).each( function() {
				o2.Utilities.timestamp( $( this ) );
			} );

			// Mark this view so it (and its model) can be found later
			this.$el.data( 'cid', this.model.cid );

			this.model.trigger( 'o2-comment-rendered' );

			return this;
		},

		remove: function() {
			/*
			 * If comment is a reply box or is being reflowed due to
			 * a permanently deleted comment, then just remove without animation.
			 */
			var isReply  = ! this.model.has( 'id' );
			if ( isReply ) {
				this.$el.remove();
			} else {
				this.$el.slideUp( function(){
					$(this).remove();
				});
			}

			// Delete the subview reference in the post parent
			if ( ! isReply ) {
				delete this.parent.subviews[ this.model.cid ];
			}

			this.unbind();
		}
	} );
} )( jQuery );
