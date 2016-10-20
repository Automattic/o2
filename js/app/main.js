/* global moment, o2Keyboard, o2Editor */
/*
 * o2 is oxygen for your company
 */
var o2 = o2 || {};

o2.Routers = o2.Routers || {};

o2.Routers.App = ( function( $, Backbone ) {
	/*
	 * Main, application-level router runs the show. Single instance created as o2.App (in o2.start())
	 * The App is the only component that should be accessing o2.options directly
	 */
	return Backbone.Router.extend( {
		initialAppState: function() {
			return {
				saveInProgress: false
			};
		},

		initialize: function( options ) {
			// Load post action states dictionary
			var postActionStateDict = $( '.o2-post-action-states-dict' );
			if ( postActionStateDict.length ) {
				o2.PostActionStates.stateDictionary = $.parseJSON( postActionStateDict.text() );
			}

			this.posts = options.posts || new o2.Collections.Posts();
			this.appState = this.initialAppState();

			// Set up Moment.js to handle time strings
			moment.lang( o2.options.i18nLanguage, o2.options.i18nMoment );
			o2.options.dateFormat = o2.Utilities.phpToMoment( o2.options.dateFormat );
			o2.options.timeFormat = o2.Utilities.phpToMoment( o2.options.timeFormat );

			// Create a global user cache for everyone to read from
			// and for us to bootstrap into
			o2.UserCache = new o2.Collections.Users( { userDataURL: o2.options.userDataURL } );
			this.bootstrapUsers();

			// Sign up for interesting events
			this.listenTo( o2.Events.dispatcher, 'notify-app.o2', this.onNotifyApp );
			o2.Events.doAction( 'preload.o2' );
			o2.$appContainer.html('').show();

			// Prepend the Front Side Posting box (if appropriate)
			if ( o2.options.showFrontSidePostBox && o2.currentUser.canPublishPosts ) {
				this.frontSidePost = new o2.Models.Post( {
					type: 'post',
					editing: true,
					userLogin: o2.currentUser.userLogin
				} );

				this.frontSidePostView = new o2.Views.FrontSidePost( {
					model:           this.frontSidePost,
					template:        'front-side-new-post-edit',
					postPrompt:      o2.options.frontSidePostPrompt,
					currentUser:     o2.currentUser
				} );
				// Render the editor and inject it into the DOM, either in
				// #o2-new-post-container (if it exists), or at the top of the appContainer.
				this.frontSidePostView.render();
				var newPost = $( '#o2-new-post-container' );
				if ( newPost.length > 0 ) {
					newPost.append( this.frontSidePostView.el );
				} else {
					o2.$appContainer.html( this.frontSidePostView.el );
				}
				this.frontSidePostView.activateEditor();
			}

			// Create a pageMeta model
			this.pageMeta = new o2.Models.PageMeta( {
				pageTitle : o2.options.pageTitle,
				isSingle  : 'single' === o2.options.viewType  ? true : false,
				isPage    : 'page'   === o2.options.viewType  ? true : false,
				is404     : '404'    === o2.options.viewType  ? true : false,
				isSearch  : 'search' === o2.options.viewType  ? true : false,
				isPreview : true     === o2.options.isPreview ? true : false,
				havePosts : o2.options.havePosts
			} );

			// Check for bootstrap data and set it up if found
			if ( 'undefined' !== typeof options.data ) {
				_.each( options.data, function( datum ) {
					if ( 'post' === datum.type ) {
						this.addPost( datum );
					} else if ( 'comment' === datum.type ) {
						this.addComment( datum );
					} else {
						if ( window.console ) {
							window.console.log( 'unrecognized data type in bootstrap - ignoring', datum.type );
						}
					}
				}, this );
			}

			if ( 'undefined' !== typeof o2Keyboard ) {
				o2Keyboard.init( this.pageMeta );
			}

			// @todo create an app view instead of defining this in router initialize
			// make the app view template driven
			// the app view will display a Threads View
			// the app view will replace o2.options.appContainer

			// Prepend the app header view
			// We use a pageMeta model because conceivably in a future version the title may change
			// as we navigate, and using a model means the view will update automagically
			// as we update the model
			this.appHeaderView = new o2.Views.AppHeader( {
				model:        this.pageMeta,
				template:     'app-header',
				showTitle:    'single' === o2.options.viewType ? false : true,
				showComments: o2.options.showCommentsInitially
			} );
			o2.$appContainer.append( this.appHeaderView.render().el );

			// Display the thread(s)
			this.postsView = new o2.Views.Posts( {
				collection          : this.posts,
				postTemplate        : 'post-view',
				noPostsPostTemplate : 'no-posts-post-view',
				showNavigation      : 'single' === o2.options.viewType ? true : false,
				highlightOnAdd      : false,
				showTitles          : 'page' === o2.options.viewType ? false : true, // if a page, never show title in thread - show in header
				userMustBeLoggedInToComment: o2.options.userMustBeLoggedInToComment,
				requireUserNameAndEmailIfNotLoggedIn: o2.options.requireUserNameAndEmailIfNotLoggedIn,
				loginURL            : o2.options.loginURL,
				currentUser         : o2.currentUser,
				showComments        : o2.options.showCommentsInitially,
				threadCommentsDepth : o2.options.threadCommentsDepth
			} );

			o2.Events.doAction( 'pre-postsView-render.o2' );
			o2.$appContainer.addClass( 'current-user-' + o2.currentUser.userNicename ).append( this.postsView.render().el );
			o2.Events.doAction( 'post-postsView-render.o2' );

			// If we're on the blog home page, and we have no posts, say as much
			if ( 'home' === o2.options.viewType && 0 === this.posts.length ) {
				this.postsView.addNoPostsPost();
			}

			// Inject the search form as appropriate
			if ( '404' === o2.options.viewType || ( 'search' === o2.options.viewType && ! this.posts.length ) ) {
				var invitation = '';
				if ( '404' === o2.options.viewType ) {
					invitation = o2.strings.pageNotFound;
				} else {
					invitation = o2.strings.searchFailed;
				}
				this.searchMeta = new o2.Models.SearchMeta( {
					invitation: invitation,
					lastQuery: o2.options.searchQuery
				} );
				this.SearchForm = new o2.Views.SearchForm( {
					model:    this.searchMeta,
					template: 'search-form'
				} );
				this.SearchForm.render();
				o2.$appContainer.append( this.SearchForm.el );
			}

			// Display page navigation (e.g. /page/3 /page/2)
			// create a new model with the prev and next page links
			// render the template using it
			// in the future we could use app routing instead
			if ( o2.$body.hasClass( 'infinite-scroll' ) && o2.options.infiniteScroll ) {

			} else {
				this.appFooterView = new o2.Views.AppFooter( {
					model:    this.pageMeta,
					template: 'app-footer'
				} );
				this.appFooterView.render();
				o2.$appContainer.append( this.appFooterView.el );
			}

			// If replytocom is defined as a URL parameter, open a reply to that comment
			var replytocomRegExp = new RegExp( '[?&]replytocom=(-?[0-9]+)' );
			var replytocom = replytocomRegExp.exec( Backbone.history.location.search );

			// Grab the 0th post ID for opening replies
			var openReplyPostID = 0;
			if ( this.posts.length ) {
				openReplyPostID = this.posts.at(0).get( 'id' );
			}

			// If a comment ID is in the hash, and we're allowed to comment
			// to it, then automatically open a reply to that comment
			var locationHash = Backbone.history.location.hash;
			if ( locationHash.substring(0, 9) === '#comment-' ) {
				var commentID = locationHash.split( '-' )[1],
					post = this.posts.get( openReplyPostID ),
					comment = post.comments.get( commentID );

				if ( ( 'undefined' !== typeof comment ) && ( comment.get( 'depth' ) < o2.options.threadCommentsDepth ) ){
					o2.Events.dispatcher.trigger( 'open-reply.o2', { postID: openReplyPostID, parentCommentID: commentID } );
				}
			} else if ( replytocom ) {
				o2.Events.dispatcher.trigger( 'open-reply.o2', { postID: openReplyPostID, parentCommentID: replytocom[1] } );
			} else if ( ( 'single' === o2.options.viewType ) || ( 'page' === o2.options.viewType ) ) {
				// If we don't have a comment ID, automatically open a reply on single posts or pages
				o2.Events.dispatcher.trigger( 'open-reply.o2', { postID: openReplyPostID, parentCommentID: 0, focus: false } );
			}

			// Re-enable highlighting of new content going forward
			o2.Events.dispatcher.trigger( 'update-posts-view-options.o2', { highlightOnAdd: true } );
			o2.Events.dispatcher.trigger( 'update-post-view-options.o2', { highlightOnAdd: true } );

			// Open default notifications collection
			o2.Notifications.open();

			// If a hash (fragment identifier) was provided, 1) set a trigger to scroll
			// back to it on unload so browsers don't cache unusual positions and 2)
			// actually scroll to it too.
			// See http://stackoverflow.com/questions/7035331/prevent-automatic-browser-scroll-on-refresh
			if ( window.location.hash ) {
				// Scroll to the fragment identifer
				setTimeout( function () {
					var fragment = $( document.body ).find( window.location.hash );
					if ( fragment.length ) {
						var scrollY = fragment.offset().top - 30;
						window.scrollTo( 0, scrollY );
					}
				}, 1500 );
			}

			// Highlight search terms if this is a search. Re-highlights after toggling edit
			// mode to catch "new" text on the page.
			o2.$appContainer.on( 'ready.o2 toggle-edit.o2 post-infinite-scroll-response.o2', function( event, state ) {
				if ( false === state || undefined === state ) {
					if ( 'undefined' !== typeof o2.options.queryVars && 'undefined' !== typeof o2.options.queryVars.s ) {
						var terms = o2.options.queryVars.s.split( ' ' );
						$( '.o2-posts' ).highlight( terms );
					}
				}
			} );

			// Listen for scrollTo events
			o2.$appContainer.on( 'scroll-to.o2', function( event, domRef ) {
				o2.App.postsView.scrollTo( domRef );
			} );

			// We're rendered
			o2.Events.doAction( 'ready.o2' );
		},

		bootstrapUsers: function() {
			var that = this; // keep a reference to the app
			$( '.o2-user-data' ).each( function( index, element ) {
				var me = $( element );
				var userdatum = $.parseJSON( me.text() );
				_.each( userdatum, function( frag ) {
					that.addUser( frag );
				} );
			} );
			$( '.o2-user-data' ).remove();
		},

		addPost: function( post ) {
			var incomingPost = new o2.Models.Post( post );

			// We do a manual check to avoid re-rendering things we already
			// have locally, which we probably created/edited ourselves
			var foundPost = this.posts.get( incomingPost );
			if ( 'undefined' !== typeof foundPost && foundPost.get( 'unixtimeModified' ) === incomingPost.get( 'unixtimeModified' ) ) {
				foundPost.set( post, { silent: true } );
			} else {
				this.posts.add( incomingPost, { merge: true } );
			}

			// If we are on a page ( is_page() ) then grab the title of the singular
			// "post" and set it into the pagemeta model to update our page title
			if ( 'page' === o2.options.viewType ) {
				this.pageMeta.set( 'pageTitle', incomingPost.get( 'titleFiltered' ) );
			}

			// Anyone else want this?
			o2.Events.dispatcher.trigger( 'data-received.o2', { type: 'post', data: post } );
		},

		removePost: function( post ) {
			var postToRemove = this.posts.findWhere( { id: post.id } );

			// Remove the post from the posts collection if found.
			if ( 'undefined' !== typeof postToRemove ) {

				// Redirect to home if currently on a single view.
				if ( 0 !== parseInt( o2.options.postId, 10 ) ) {

					o2.Notifications.add( {
						text: o2.strings.redirectedHomeString,
						url: false,
						type: 'redirectedHome',
						sticky: false,
						popup: false,
						dismissable: true
					} );

					// Redirect to home page if currently in a single post/page view.
					window.location.href = o2.options.homeURL;
				} else {

					// Remove the post if currently in a list view.
					this.posts.remove( postToRemove );

					// Anyone else want this?
					o2.Events.dispatcher.trigger( 'post-remove.o2', { type: 'post', data: post } );
				}
			}
		},

		addComment: function( comment ) {
			// find the thread that's handling the post this comment belongs to
			// note:  it is possible that the thread isn't on this page
			// and if so, this is a no-op
			var post = this.posts.get( comment.postID );

			if ( post ) {
				// Can't do a modified timestamp check on comments (yet) because they don't have one
				// @todo Figure out a way (commentmeta?) to track modified time.

				/*
				 * If prevDeleted is then let's update the model for the deleted comment with this comment's data
				 * Else, add the comment like normal.
				 */

				var prevDeleted     = parseInt( comment.prevDeleted, 10 ),
					commentToRemove = post.comments.get( prevDeleted );

				if ( ! _.isUndefined( commentToRemove ) ) {

					commentToRemove.set( comment );

					var childModels = this.getChildModels( post, comment.id );

					_.each( childModels, function( model ) {
						model.set( { parentID: comment.id } );
					});
				} else {
					var incomingComment = new o2.Models.Comment( comment );
					post.comments.add( incomingComment, { merge: true } );
				}
			}

			// Anyone else want this?
			o2.Events.dispatcher.trigger( 'data-received.o2', { type: 'comment', data: comment } );
		},

		childHasTrashedSessionOrApproved: function( comment ) {
			var post = this.posts.get( comment.get( 'postID' ) );

			if ( post ) {
				var children = this.getChildModels( post, comment.get( 'id' ) );

				// Searches children until finding approved or trashed session, then returns that element.
				var found = _.find( children, function( child ) {
					return ( child.get( 'approved' ) || child.has( 'trashedSession' ) );
				});

				if ( ! _.isUndefined( found ) ) {
					return true;
				}
			}

			return false;
		},

		cleanUpTrashedParents: function( post, comment ) {
			// First, let's check to make sure that no children are approved and/or in trashedSession state
			if ( ! this.childHasTrashedSessionOrApproved( post, comment ) && 0 < comment.get( 'parentID' ) ) {
				var parent = post.comments.get( comment.get( 'parentID' ) );
				if ( ! _.isUndefined( parent ) && parent.get( 'isTrashed' ) && ! parent.has( 'trashedSession' ) ) {
					var children = post.comments.where( { parentID: parent.get( 'id' ), approved: true } );

					// If the passed in comment has no approved siblings, then remove comment
					if ( 0 === children.length ) {
						this.removeComment( parent.toJSON() );
						this.cleanUpTrashedParents( post, parent );
					}
				}
			}
		},

		getChildModels: function( post, commentID ) {
			var children = post.comments.where( { parentID: commentID } );

			if ( children.length > 0 ) {
				_.each( children, function( child ){
					var temp = children.concat( o2.App.getChildModels( post, child.get( 'id' ) ) );
					children = temp;
				});
			}

			return children;
		},

		removeComment: function( comment ) {
			var post = this.posts.get( comment.postID );

			if ( post ) {
				var commentToRemove = post.comments.get( comment.id );

				// If both post and commentToRemove are set
				if ( ! _.isUndefined( commentToRemove ) ) {
					if ( ! commentToRemove.has( 'trashedSession' ) && ! this.childHasTrashedSessionOrApproved( commentToRemove ) ) {
						post.comments.remove( commentToRemove );
						o2.Events.dispatcher.trigger( 'comment-deleted.o2', { type: 'comment', data: comment } );
						this.cleanUpTrashedParents( post, commentToRemove );
					}
				}
			}
		},

		addUser: function( user ) {
			o2.UserCache.addOrUpdate( user );
		},

		suppressHighlighting: function() {
			o2.Events.dispatcher.trigger( 'update-posts-view-options.o2', { highlightOnAdd: false } );
		},

		allowHighlighting: function() {
			o2.Events.dispatcher.trigger( 'update-posts-view-options.o2', { highlightOnAdd: true } );
		},

		onNotifyApp: function( state ) {
			this.appState = _.extend( this.appState, state );
		},

		onLoggedInStateChange: function( isLoggedIn ) {
			var firstWithReloginType = o2.Notifications.notifications.findFirst( 'relogin' );

			if ( ! isLoggedIn ) {
				o2.$appContainer.removeClass( 'current-user-' + o2.currentUser.userNicename );

				// If there is no relogin notification, add one
				if ( 'undefined' === typeof firstWithReloginType ) {
					o2.Notifications.add( {
						text: o2.strings.reloginPrompt,
						url: o2.options.loginWithRedirectURL,
						type: 'relogin',
						sticky: false,
						popup: true,
						dismissable: false
					} );
				}
			} else {
				o2.$appContainer.addClass( 'current-user-' + o2.currentUser.userNicename );

				// Remove any relogin notifications
				if ( 'undefined' !== typeof firstWithReloginType ) {
					firstWithReloginType.destroy();
				}

				// Let the user know all is well again
				o2.Notifications.add( {
					text: o2.strings.reloginSuccessful,
					type: 'relogin',
					sticky: false,
					dismissable: true
				} );

				// Automatically get rid of this message in a few seconds
				setTimeout( function() {
					var firstWithReloginType = o2.Notifications.notifications.findFirst( 'relogin' );
					if ( 'undefined' !== typeof firstWithReloginType ) {
						firstWithReloginType.destroy();
					}
				}, 3000 );
			}
		},

		onLogInComplete: function() {
			// This event is triggered by the popup window completing login successfully
			// Let's remove any login notification
			var firstWithReloginType = o2.Notifications.notifications.findFirst( 'relogin' );
			if ( 'undefined' !== typeof firstWithReloginType ) {
				firstWithReloginType.destroy();
			}
		},

		/*
		 * okToNavigate returns an empty string if it is OK to navigate away
		 * from the page, or a displayable localized string if it is not
		 */
		okToNavigate: function() {
			if ( this.appState.saveInProgress ) {
				return o2.strings.saveInProgress;
			}

			if ( o2Editor.hasChanges() ) {
				return o2.strings.unsavedChanges;
			}

			return '';
		}
	} );
} )( jQuery, Backbone );

/*
 * Start up o2 with optional bootstrap data+options (array of fragments)
 */
( function( $ ) {
	o2.start = function( bootstrap ) {
		o2.App = o2.App || new o2.Routers.App( bootstrap );
		return o2.App;
	};

	/*
	 * Pass infinite scroll data into o2
	 */
	$( document ).on( 'post-load', function() {
		o2.Events.doAction( 'pre-infinite-scroll-response.o2' );
		$( '.infinite-wrap .o2-data' ).each( function() {
			o2.Notifications.close();
			o2.App.suppressHighlighting();
			var me = $( this );
			var post;
			try {
				post = $.parseJSON( me.text() );
			} catch ( e ) {
				post = false;
				if ( window.console ) {
					window.console.log( '$.parseJSON failure: ' + me.text() );
				}
			}
			if ( false !== post ) {
				o2.App.addPost( post );
			}
			o2.Notifications.open();
			o2.App.allowHighlighting();
		} );
		$( '.infinite-wrap' ).remove();
		$( '.infinite-loader' ).remove();
		o2.$body.trigger( 'pd-script-load' );
		o2.Events.doAction( 'post-infinite-scroll-response.o2' );
	} );

	/*
	 * Check for unfinished business before allowing navigation
	 */
	$( window ).bind( 'beforeunload', function () {
		if ( 'undefined' !== typeof o2.App ) {
			var navigateWarning = o2.App.okToNavigate();
			if ( navigateWarning.length > 0 ) {
				return navigateWarning;
			}
		}
	} );

	/*
	 * Increment time since every 2 minutes
	 */
	setInterval( function() {
		$( '.o2-timestamp' ).each( function() {
			o2.Utilities.timestamp( $( this ) );
		} );
	}, 60000 );

} )( jQuery );

/* Disable document.write to avoid scripts added with posts
 * from executing it after the DOM is loaded (and thus removing
 * all the elements from the document)
 */

/* jshint ignore:start */

o2.originalWrite = document.write;

document.write = function() {
	if ( 'undefined' !== typeof jQuery && jQuery.isReady ) {
		if ( 'undefined' !== typeof console && console.warn ) {
			console.warn( 'document.write called after page load and ignored' );
		}
	} else {
		return Function.prototype.apply.call( o2.originalWrite, document, arguments );
	}
};

/* jshint ignore:end */
