var StickyPostExtendsPost = ( function( $ ) {
	return {
		initialize: function() {
			this.listenTo( this.model, 'update-sticky-error', this.updateStickyError );
			this.listenTo( this.model, 'o2-post-rendered',    this.stickyPostView );

			if ( this.model.isSticky() && 'home' === o2.options.viewType ) {
				this.options.showComments = false;
			}
		},

		events: {
			'click a.o2-sticky-link':     'onClickStickyPost',
			'touchend a.o2-sticky-link':  'onClickStickyPost'
		},

		onClickStickyPost: function( event ) {
			event.preventDefault();
			event.stopPropagation();

			// Change the model, then update the view
			this.model.changeSticky();
			this.stickyPostView( this.model.isSticky() );

			// Send the model changes to the server
			var pluginData = {};
			pluginData.callback = 'o2_sticky_posts';
			pluginData.data = {
				postID: this.model.get( 'postID' ),
				isSticky: this.model.isSticky()
			};

			this.model.save( {
				pluginData: pluginData
			}, {
				patch: true,
				silent: true,
				success: this.onStickySaveSuccess,
				error: this.onStickySaveError
			} );
		},

		onStickySaveSuccess: function() {
			// @todo move the view if the save was successful
		},

		onStickySaveError: function( model, xhr ) {
			model.trigger( 'update-sticky-error', xhr );
		},

		getStickyText: function( sticky ) {
			var stickyText;
			if ( sticky ) {
				stickyText = o2.options.stickyPosts.sticky;
			} else {
				stickyText = o2.options.stickyPosts.unsticky;
			}
			return stickyText;
		},

		getStickyTitle: function( sticky ) {
			return ( sticky ) ? o2.options.stickyPosts.stickyTitle : o2.options.stickyPosts.unstickyTitle;
		},

		stickyPostView: function() {
			var newState = this.model.isSticky() ? 'sticky' : 'normal';
			var link = this.$el.find( '.o2-sticky-link' );
			o2.PostActionStates.setState( link, newState );
		},

		updateStickyError: function( xhr ) {
			var responseText = '';
			var errorText = '';
			try {
				responseText = $.parseJSON( xhr.responseText );
				errorText = responseText.errorText;
			} catch ( e ) {
				errorText = xhr.responseText;
			}

			o2.Notifications.add( {
				type: 'error',
				text: errorText,
				sticky: true
			} );
		}
	};
} )( jQuery );

Cocktail.mixin( o2.Views.Post, StickyPostExtendsPost );
