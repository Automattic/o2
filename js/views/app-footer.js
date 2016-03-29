var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.AppFooter = ( function() {
	return wp.Backbone.View.extend({
		tagName: 'footer', // @todo this at least needs to be filterable, if not just a wrapper around the full template block

		model: o2.Models.PageMeta,

		className: 'o2-app-footer',

		initialize: function() {
			this.listenTo( this.model, 'change', this.render );
		},

		render: function() {
			var template = o2.Utilities.Template( this.options.template );
			this.$el.html( template( this.model.toJSON() ) );
			return this;
		}
	} );
} )();
