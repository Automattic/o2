var ResolvedPostExtendsPost = ( function( $ ) {
	return {
		initialize: function() {
			_.bindAll( this, 'onResolvedPostsSuccess', 'onResolvedPostsError' );

			var postMeta = this.model.get( 'postMeta' );
			if ( 'undefined' !== typeof postMeta.resolvedPostsAuditLogs ) {
				this.model.auditLogs = new o2.Collections.AuditLogs( postMeta.resolvedPostsAuditLogs );
			} else {
				this.model.auditLogs = new o2.Collections.AuditLogs();
			}

			this.listenTo( this.model, 'o2-post-rendered', this.onRenderPostView );
			this.listenTo( this.model, 'o2-post-rendered', this.addAllAuditLogViews );

			this.listenTo( this.model.auditLogs, 'add', this.addOneAuditLog );
			this.listenTo( this.model.auditLogs, 'reset', this.addAllAuditLogViews );
		},

		events: {
			'click a.o2-resolve-link':     'onClickResolvedPosts',
			'mouseenter .o2-resolve-wrap': 'onHoverResolvedPosts',
			'mouseleave .o2-resolve-wrap': 'onBlurResolvedPosts',
			'touchend a.o2-resolve-link':  'onClickResolvedPosts'
		},

		onClickResolvedPosts: function( event ) {
			event.preventDefault();
			event.stopPropagation();

			if ( $( event.target ).hasClass( 'o2-disabled-action' ) ) {
				return false;
			}

			var currentState = this.getPostModelCurrentState();
			var nextState = o2.PostActionStates.getNextState( 'resolvedposts', currentState );

			var link = this.$el.find( '.o2-resolve-link' );
			o2.PostActionStates.setState( link, nextState );
			this.setPostModelState( nextState );

			var pluginData = {};
			pluginData.callback = 'o2_resolved_posts';
			pluginData.data = {
				postID: this.model.get( 'postID' ),
				nextState: nextState
			};

			this.model.save( {
				pluginData: pluginData
			}, {
				patch: true,
				silent: true,
				success: this.onResolvedPostsSuccess,
				error: this.onResolvedPostsError
			} );
		},

		onResolvedPostsSuccess: function( model, resp ) {
			var auditLog = new o2.Models.AuditLog( resp.data.auditLog );
			this.model.auditLogs.add( auditLog );
		},

		onResolvedPostsError: function( model, xhr ) {
			var currentState = this.getPostModelCurrentState();

			var link = this.$el.find( '.o2-resolve-link' );
			o2.PostActionStates.setState( link, currentState );

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
		},

		onHoverResolvedPosts: function( event ) {
			event.preventDefault();

			if ( ( 'undefined' !== typeof o2.options.isMobileOrTablet ) && o2.options.isMobileOrTablet ) {
				return;
			}

			var log = this.$( '.o2-resolve-wrap ul' );
			if ( log.find( 'li' ).length ) {
				this.$( 'li' ).show();
				log.fadeIn( 500 );
			}
		},

		onBlurResolvedPosts: function( event ) {
			event.preventDefault();

			if ( ( 'undefined' !== typeof o2.options.isMobileOrTablet ) && o2.options.isMobileOrTablet ) {
				return;
			}

			var log = this.$( '.o2-resolve-wrap ul' );
			if ( log.find( 'li' ).length ) {
				log.fadeOut( 500 );
			}
		},

		onRenderPostView: function() {
			var currentState = this.getPostModelCurrentState();
			var link = this.$el.find( '.o2-resolve-link' );
			o2.PostActionStates.setState( link, currentState );
		},

		addOneAuditLog: function( auditLog ) {
			this.addOneAuditLogView( auditLog );
		},

		addOneAuditLogView: function( auditLog ) {
			var auditLogView = new o2.Views.AuditLog( {
				model: auditLog,
				parent: this
			} );
			var list = this.$( '.o2-resolve-wrap ul' );
			list.prepend( auditLogView.render().el );

			// Disable any hovercards
			list.find( 'img' ).addClass( 'no-grav nocard' );

			if ( auditLog.isNew() ) {
				var newAuditLog = list.children( ':first' );
				newAuditLog.hide().fadeIn( 500 );
				var originalBackgroundColor = newAuditLog.css( 'background-color' );
				newAuditLog.css( 'background-color', '#ffc' );
				newAuditLog.animate( { 'background-color': originalBackgroundColor }, 3000 );
			}
		},

		addAllAuditLogViews: function() {
			this.$( '.o2-resolve-wrap ul' ).html( '' );
			this.model.auditLogs.forEach( this.addOneAuditLogView, this );
		},

		getPostModelCurrentState: function() {
			var postMeta = this.model.get( 'postMeta' );
			var currentState = postMeta.resolvedPostsPostState;
			if ( 'undefined' === typeof currentState ) {
				currentState = 'normal';
			}
			return currentState;
		},

		setPostModelState: function( slug ) {
			var postMeta = this.model.get( 'postMeta' );
			postMeta.resolvedPostsPostState = slug;
			this.model.set( { postMeta: postMeta } );
		}
	};
} )( jQuery );

Cocktail.mixin( o2.Views.Post, ResolvedPostExtendsPost );
