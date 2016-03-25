var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.Flash = ( function() {
	return wp.Backbone.View.extend( {
		collection: o2.Collections.Notifications,

		el: '#o2-flash',

		subviews: {},

		initialize: function() {
			this.render();
		},

		render: function() {
			var itemsView = new o2.Views.FlashItems( {
				collection: this.collection
			} );

			this.$el.append( itemsView.render().el );
			this.subviews.itemsView = itemsView;

			return this;
		}
	} );
} )();
