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

		// True when a url resolves to the host the page was served from.
		// Assigning to an anchor lets the browser normalise relative,
		// protocol-relative and percent-encoded forms, and resolve any
		// userinfo, before the comparison.
		//
		// Host rather than full origin: the endpoint is allowed to differ in
		// protocol from the page. o2 upgrades http to https for permalinks
		// generated during ajax calls (o2_Fragment::home_url), and the
		// withCredentials note in js/models/base.js records the same case.
		isSameHost: function( url ) {
			var resolver = document.createElement( 'a' );
			resolver.href = url;
			return !! resolver.host && resolver.host === window.location.host;
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
			if ( ! href ) {
				return;
			}

			// Check the url we are actually going to send, not the href we
			// started from: appending to a href that carries no query string
			// extends its host rather than its query.
			var requestURL = href + '&ajax';

			// The model's sync() sends the o2 nonce, with credentials, to
			// whatever url it is handed, so only send to our own host.
			if ( ! this.isSameHost( requestURL ) ) {
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
				url: requestURL,
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
			var link = this.$post().find( '.o2-follow' );
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
