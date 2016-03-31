var FollowExtendsComment = ( function() {
	return {
		initialize: function() {
			setTimeout( function( that ) {
				if ( o2.options.followingAllComments ) {
					that.$( '.comment-subscription-form').text( o2.strings.followingAllComments );
				} else {
					if ( that.$el.parents( '.post-comments-subscribed' ).length ) {
						that.$( '#subscribe' ).prop( 'checked', true );
					} else {
						that.$( '#subscribe' ).prop( 'checked', false );
					}
				}
			}, 0, this );
		},

		events: {
			'change #subscribe:checkbox': 'onClickFollow'
		},

		onClickFollow: function( event ) {
			event.preventDefault();
			event.stopPropagation(); // otherwise, event bubbling out of child comments will cause a single click to multiply
			this.parent.model.trigger( 'update-follow' );
		}
	};
} )();

Cocktail.mixin( o2.Views.Comment, FollowExtendsComment );
