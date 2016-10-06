var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.ToDos = ( function() {
	return wp.Backbone.View.extend( {
		model: o2.Models.ToDosWidget,

		tagName: 'ul',

		events: {
		},

		initialize: function() {
			this.childViews = [];
		},

		render: function() {
			// Remove rendered children if necessary
			_.each( this.childViews, function( childView ) {
				childView.remove();
				childView.unbind();
			} );
			this.$el.empty();

			// Render the current page's children
			var foundPosts = this.model.get( 'foundPosts' );
			var postsPerPage = this.model.get( 'postsPerPage' );
			var currentPage = this.model.get( 'currentPage' );
			var order = this.model.get( 'order' );
			var mine = _.toArray( this.model.get( 'collection' ).where( { widgetID: this.model.get( 'widgetID' ) } ) );

			// Reverse array if descending
			if ( 'DESC' === order ) {
				mine.reverse();
			}

			var start = ( currentPage - 1 ) * postsPerPage;
			var end = start + postsPerPage - 1;
			if ( end > ( foundPosts - 1 ) ) {
				end = foundPosts - 1;
			}
			for ( var i = start; i <= end; i++ ) {
				if ( i in mine ) {
					var toDoView = new o2.Views.ToDo( {
						model: mine[i]
					} );
					this.childViews.push( toDoView );
					this.$el.append( toDoView.render().el );
				}
			}

			return this;
		}
	} );
} )();
