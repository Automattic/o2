var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.FlashItems = ( function() {
	return wp.Backbone.View.extend( {
		collection: o2.Collections.Notifications,

		tagName: 'ul',

		className: 'o2-flash-items',

		subviews: {},

		initialize: function() {
			this.listenTo( this.collection, 'add', this.addOne );
			this.listenTo( this.collection, 'reset', this.render );
		},

		addOne: function( model ) {
			if ( ! model.isNotice() ) {
				var itemView = new o2.Views.Notification( {
					model: model
				} );
				this.$el.prepend( itemView.render().el );
				this.subviews[ itemView.model.cid ] = itemView;
			}

			return this;
		},

		render: function() {
			this.collection.each( this.addOne, this );

			return this;
		}
	} );
} )();
