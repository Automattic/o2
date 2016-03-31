var FollowExtendsPostModel = ( function() {
	return {
		isFollowing: function() {
			var postMeta = this.get( 'postMeta' );
			return ( true === postMeta.isFollowing );
		},

		changeFollow: function() {
			if ( this.isFollowing() ) {
				this.unfollow();
			} else {
				this.follow();
			}
		},

		follow: function() {
			var postMeta = this.get( 'postMeta' );
			postMeta.isFollowing = true;
			this.set( { postMeta: postMeta } );
			this.trigger( 'change-follow' );
		},

		unfollow: function() {
			var postMeta = this.get( 'postMeta' );
			postMeta.isFollowing = false;
			this.set( { postMeta: postMeta } );
			this.trigger( 'change-follow' );
		}
	};
} )();

Cocktail.mixin( o2.Models.Post, FollowExtendsPostModel );
