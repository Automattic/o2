var FollowExtendsPost = ( function() {
	return {
		initialize: function() {
			this.listenTo( this.model, 'o2-post-rendered',  this.updateFollowView );
			this.listenTo( this.model, 'change-follow',     this.updateFollowView );
			this.listenTo( this.model, 'update-follow',     this.updateFollow );

			_.bindAll( this, 'saveFollowSuccess', 'saveFollowError' );
		},

		events: {
			'click a.o2-follow':      'updateFollow',
			'mouseleave a.o2-follow': 'updateFollowView',
			'touchend a.o2-follow':   'updateFollow'
		},

		// True when a url resolves to the origin the page was served from.
		// Assigning to an anchor lets the browser normalise relative and
		// protocol-relative forms before the comparison.
		isSameOrigin: function( url ) {
			var resolver = document.createElement( 'a' );
			resolver.href = url;
			return resolver.protocol + '//' + resolver.host ===
				window.location.protocol + '//' + window.location.host;
		},

		updateFollow: function( event ) {
			if ( 'undefined' !== typeof event ) {
				event.preventDefault();
				event.stopPropagation();
			}

			if ( o2.options.followingAllComments ) {
				return; // we don't allow them to unfollow all with this ui
			}

			// Get the current AJAX link. The article this view owns also holds
			// the comment list, and comment bodies are author-supplied HTML,
			// so look the control up inside the post rather than across the
			// whole view.
			var link = this.$post().find( '.o2-follow' );
			var href = link.attr( 'href' );

			// The model's sync() sends the o2 nonce, with credentials, to
			// whatever url it is handed. The rendered control is built from
			// home_url(), so anything pointing off-origin did not come from
			// our own markup and must not receive that request.
			if ( ! href || ! this.isSameOrigin( href ) ) {
				return;
			}

			// Update the model
			this.model.changeFollow();

			// Optimistically update UI
			this.updateFollowView();

			// Notify the app that we are saving
			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: true } );

			// Using the original link, send the model changes to the server in a way it understands
			this.model.save( {}, {
				patch: true,
				silent: true,
				url: href + '&ajax',
				success: this.saveFollowSuccess,
				error: this.saveFollowError
			} );
		},

		saveFollowSuccess: function( model, response, xhr ) {
			// `1` is the only true success response
			if ( 1 !== response ) {
				this.saveFollowError( model, xhr );
				return;
			}

			// Notify the app that we have saved
			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: false } );
			this.updateFollowView();
		},

		saveFollowError: function( model, xhr ) {
			// Revert the view changes
			o2.Events.dispatcher.trigger( 'notify-app.o2', { saveInProgress: false } );
			this.model.changeFollow();
			this.updateFollowView();

			o2.Notifications.add( {
				type: 'error',
				text: xhr.responseText || o2.strings.followError,
				sticky: true
			} );
		},

		updateFollowView: function() {
			var link = this.$( '.o2-follow' );
			if ( ! link.length ) {
				return;
			}

			var newState = this.model.isFollowing() ? 'subscribed' : 'normal';
			o2.PostActionStates.setState( link, newState );

			var href = link.attr( 'href' );
			if ( this.model.isFollowing() ) {
				link.attr( 'href', href.replace( 'post-comment-subscribe', 'post-comment-unsubscribe' ) );
				this.$( '#subscribe' ).prop( 'checked', true );
			} else {
				link.attr( 'href', href.replace( 'post-comment-unsubscribe', 'post-comment-subscribe' ) );
				this.$( '#subscribe' ).prop( 'checked', false );
			}

			if ( o2.options.followingAllComments ) {
				var nextText = o2.strings.followingAll;
				link.text( nextText );
				link.attr( 'title', nextText );
			}
		}
	};
} )();

Cocktail.mixin( o2.Views.Post, FollowExtendsPost );
