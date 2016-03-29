var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.DockCount = ( function() {
	return wp.Backbone.View.extend( {
		collection: o2.Collections.Notifications,

		className: 'o2-dock-count genericon genericon-chat',

		initialize: function() {
			this.listenTo( this.collection, 'add', this.render );
			this.listenTo( this.collection, 'remove', this.render );
			this.listenTo( this.collection, 'reset', this.render );
		},

		render: function() {
			this.$el.empty();

			var count = this.collection.where( { type: 'notice' } ).length;
			this.$el.append( count );

			return this;
		}
	} );
} )();
