var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.SearchForm = ( function() {
	return wp.Backbone.View.extend( {
		tagName: 'article',

		defaults: {},

		initialize: function( options ) {
			this.options = _.extend( this.defaults, options );
		},

		render: function() {
			var template = o2.Utilities.Template( this.options.template );
			var jsonifiedModel = this.model.toJSON();
			jsonifiedModel.strings = o2.strings;
			this.$el.html( template( jsonifiedModel ) );
			return this;
		}
	} );
} )();
