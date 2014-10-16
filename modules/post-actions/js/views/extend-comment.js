var CommentActionsExtendsComment = ( function( $, Backbone ) {
	return {
		events: {
			'mouseenter': 'onHoverComment',
			'mouseleave': 'onLeaveComment',
		},

		initialize: function( options ) {
			this.listenTo( this.model, 'o2-comment-rendered', this.onCommentRender );
		},

		/* Since .is( ':hover' ) is no longer supported in jQuery, and since our comments are nested */
		/* we need to do some fancy stuff to get the comment actions disclosure to appear on the */
		/* correct comment on hover */

		onHoverComment: function( event ) {
			this.$el.addClass( 'hovered' );
		},

		onLeaveComment: function( event ) {
			this.$el.removeClass( 'hovered' );
		},

		/* Hide the comment dropdown disclosure until the comment gets hovered */
		onCommentRender: function( event ) {
			var isTouch = false;
			if ( 'undefined' !== typeof o2.options.isMobileOrTablet ) {
				isTouch = o2.options.isMobileOrTablet;
			}
			if ( ! isTouch ) {
				if ( ! this.$el.hasClass( 'hovered' ) ) {
					this.$el.find( '.o2-dropdown-actions-disclosure' ).first().hide();
				}
			}
		}
	};
} )( jQuery, Backbone );

Cocktail.mixin( o2.Views.Comment, CommentActionsExtendsComment );