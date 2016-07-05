/* global enquire, console */
/*
 * o2.Views.Posts - A view that contains all the posts, iterating over a Posts Collection
 */
var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.Posts = ( function( $ ) {
	return wp.Backbone.View.extend( {
		collection: o2.Collections.Posts,

		className: 'o2-posts',

		defaults: {
			showNavigation: false, /* whether threads should show prev / next post navigation */
			highlightOnAdd: true, /* whether newly added threads should be highlighted once inview */
			showComments: true,
			showTitles: true, /* whether to have threads show their titles */
			userMustBeLoggedInToComment: true,
			requireUserNameAndEmailIfNotLoggedIn: false,
			threadCommentsDepth: 3, /* maps to thread_comments_depth option */
			loginURL: '',
			currentUser: {
				userLogin: '',
				canEditPosts: false,
				canEditOthersPosts: false,
				canPublishPosts: false
			},
			isNarrowScreen: false
		},

		lastHomeScroll: 0,

		initialize: function( options ) {
			this.subviews = {};

			this.listenTo( this.collection, 'add', this.addOne );
			this.listenTo( this.collection, 'reset', this.addAll );
			this.listenTo( this.collection, 'remove', this.removeOne );
			this.options = _.extend( this.defaults, options );

			// Event handlers
			o2.Events.dispatcher.bind( 'update-posts-view-options.o2', this.updateOptions, this );

			// If enquire is loaded (for responsive layouts), register some more listeners
			if ( 'undefined' !== typeof enquire ) {
				_.bindAll( this, 'onScreenNarrowed', 'onScreenWidened' );
				enquire.register( 'screen and (max-width:550px)', {
					match:  this.onScreenNarrowed,
					unmatch: this.onScreenWidened
				} );
			}
		},

		events: {},

		scrollTo: function( domRef ) {
			var domEl = $( domRef );
			if ( domEl.length ) {

				var postID = false;
				var commentID = false;

				// Figure out if the dom ref is for a post or a comment
				if ( '#comment-' === domRef.substr( 0, 9 ) ) {
					commentID = parseInt( domRef.substr( 9 ), 10 );
					this.collection.forEach( function( post ) {
						if ( post.comments.get( commentID ) ) {
							postID = post.get( 'postID' );
						}
					} );
				} else if ( '#post-' === domRef.substr( 0, 6 ) ) {
					postID = parseInt( domRef.substr( 6 ), 10 );
				} else {
					console.error( 'bad domRef in scrollTo, domRef=', domRef );
					return;
				}

				// Prompt the theme to close any provided modals/dockables (e.g. flyout sidebar)
				$( 'body' ).trigger( 'closemodals' );

				// If we're scrolling to a comment, make sure our post is showing its comments
				if ( commentID && domEl.is( ':hidden' ) ) {
					this.subviews[ postID ].updateOptions( { showComments: true } );
				}

				// Ok - now lets scroll to it
				$( 'html, body' ).animate( {
					scrollTop: domEl.offset().top - 50
				}, 2000 );
			}
		},

		onScreenNarrowed: function() {
			this.options.isNarrowScreen = true;
			this.options.oldShowComments = this.options.showComments;
			this.options.showComments = false;
			o2.Events.dispatcher.trigger( 'update-posts-view-options.o2', { showComments: this.options.showComments } );
			o2.Events.dispatcher.trigger( 'update-post-view-options.o2', { showComments: this.options.showComments } );

		},

		onScreenWidened: function() {
			this.options.isNarrowScreen = false;
			if ( 'undefined' !== typeof this.options.oldShowComments ) {
				this.options.showComments = this.options.oldShowComments;
				o2.Events.dispatcher.trigger( 'update-posts-view-options.o2', { showComments: this.options.showComments } );
				o2.Events.dispatcher.trigger( 'update-post-view-options.o2', { showComments: this.options.showComments } );
			}
		},

		updateOptions: function( options ) {
			this.options = _.extend( this.options, options );
		},

		render: function() {
			// Suspend highlighting during this loop
			// (Only want it to happen when addOne is triggered by the collection event)
			var cachedHighlight = this.options.highlightOnAdd;
			this.options.highlightOnAdd = false;
			this.collection.forEach( this.addOne, this );
			this.options.highlightOnAdd = cachedHighlight;

			return this;
		},

		addOne: function( post ) {
			this.removeNoPostsPost();

			var postID = post.get( 'id' );

			var postView = new o2.Views.Post( {
				model: post,
				template: this.options.postTemplate,
				showNavigation: this.options.showNavigation,
				highlightOnAdd: this.options.highlightOnAdd,
				userMustBeLoggedInToComment: this.options.userMustBeLoggedInToComment,
				requireUserNameAndEmailIfNotLoggedIn: this.options.requireUserNameAndEmailIfNotLoggedIn,
				showComments: this.options.showComments,
				showTitle: this.options.showTitles,
				currentUser: this.options.currentUser,
				threadCommentsDepth: this.options.threadCommentsDepth
			} );

			// if the indexOf is 0, insert it at the beginning of the div
			// otherwise, insert it after the indexOf - 1 child
			// Note this ONLY works because the initial rendering by addAll proceeds through the
			// models in the collection in order - and then subsequent arrivals leverage the
			// threads comparator to get inserted into the collection at the right spot
			var indexOf = this.collection.indexOf( post );
			if ( 0 === indexOf ) {
				this.$el.prepend( postView.render().el );
			} else {
				this.$el.children( o2.options.threadContainer + ':eq(' + ( indexOf - 1 ) + ')' ).after( postView.render().el );
			}

			if ( this.options.highlightOnAdd ) {
				postView.$el.one( 'inview', o2.Utilities.HighlightOnInview );
			}

			if ( post.get( 'unixtimeModified' ) <= post.get( 'unixtime' ) && /* New post (allow for timestamp diffs) */
				this.options.currentUser.userLogin !== post.get( 'userLogin' ) /* Not mine */ )
			{
				var postAuthorModel = o2.UserCache.getUserFor( post.attributes, 32 );

				var text = '';
				if ( post.get( 'mentionContext' ) ) {
					text = o2.strings.newMentionBy.replace( '%1$s', postAuthorModel.displayName ).replace( '%2$s', post.get( 'mentionContext' ) );
				} else {
					text = o2.strings.newPostBy.replace( '%s', postAuthorModel.displayName );
				}

				o2.Notifications.add( {
					source: postView,
					text: text,
					textClass: postAuthorModel.modelClass,
					unixtime: post.get( 'unixtime' ),
					url: '#post-' + post.get( 'id' ),
					iconUrl: postAuthorModel.avatar,
					iconSize: postAuthorModel.avatarSize,
					iconClass: postAuthorModel.modelClass
				} );
			}

			// Save a reference to the post view so we can find it later
			this.subviews[ postID ] = postView;
		},

		addAll: function() {
			this.collection.forEach( this.addOne, this );
		},

		addNoPostsPost: function() {
			var noPostsPost = this.$el.find( '.o2-no-posts-post' );
			if ( noPostsPost.length < 1 ) {
				var noPostsView = new o2.Views.NoPostsPost( {
					template: this.options.noPostsPostTemplate
				} );
				this.$el.append( noPostsView.render().el );
			}
		},

		removeOne: function( model, collection, options ) {
			var post = this.$el.children( o2.options.threadContainer + ':eq(' + options.index + ')' );

			if ( ! _.isUndefined( options.animate ) && false === options.animate ) {
				post.remove();
			} else {
				post.slideUp( function(){
					$(this).remove();
				});
			}

			var postID = model.get( 'id' );
			delete this.subviews[ postID ];
		},

		removeNoPostsPost: function() {
			this.$( '.o2-no-posts-post' ).remove();
		}

	} );
} )( jQuery );
