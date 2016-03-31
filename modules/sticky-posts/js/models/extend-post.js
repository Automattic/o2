var StickyPostExtendsPostModel = ( function() {
	return {
		initialize: function() {
			this.on( 'change', this.checkSticky, this );
		},

		checkSticky: function( model ) {
			if ( ! _.isUndefined( model.changed.id ) && _.isUndefined( model.changed.postMeta ) && _.isUndefined( model.changed.postMeta.isSticky ) ) {
				this.trigger( 'change-sticky', this, { scroll: false } );
			}
		},

		isSticky: function() {
			var postMeta = this.get( 'postMeta' );
			if ( 'undefined' === typeof postMeta.isSticky ) {
				return false;
			}
			return postMeta.isSticky;
		},

		changeSticky: function() {
			if ( this.isSticky() ) {
				this.unstick();
			} else {
				this.stick();
			}
		},

		stick: function() {
			var postMeta = this.get( 'postMeta' );
			postMeta.isSticky = true;
			this.set( { postMeta: postMeta } );
			this.trigger( 'change-sticky', this, { scroll: true } );
		},

		unstick: function() {
			var postMeta = this.get( 'postMeta' );
			postMeta.isSticky = false;
			this.set( { postMeta: postMeta } );
			this.trigger( 'change-sticky', this, { scroll: false } );
		}
	};
} )();

Cocktail.mixin( o2.Models.Post, StickyPostExtendsPostModel );
