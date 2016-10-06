var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.Pagination = ( function() {
	return wp.Backbone.View.extend( {
		options: function() {
			return {
				currentPage: 1,
				totalPages: 0,
				prevText: '&lsaquo;',
				nextText: '&rsaquo;',
				firstText: '&laquo;',
				lastText: '&raquo;',
				padding: 2,
				el: ''
			};
		},

		render: function() {
			var currentPage = this.model.get( 'currentPage' );
			var totalPages = this.model.get( 'totalPages' );
			var postsPerPage = this.model.get( 'postsPerPage' );
			var foundPosts = this.model.get( 'foundPosts' );
			if ( totalPages > 1 ) {
				var pages = [];
				if ( this.options.padding >= 0 ) {
					pages[ currentPage ] = currentPage;

					for ( var i = 1; i <= this.options.padding; i++ ) {
						if ( currentPage - i > 0 ) {
							pages[ currentPage - i ] = currentPage - i;
						}

						if ( currentPage + i <= totalPages ) {
							pages[ currentPage + i ] = currentPage + i;
						}
					}
				}

				var firstView = ( currentPage - 1 ) * postsPerPage + 1;
				var lastView = currentPage * postsPerPage;
				if ( lastView >= foundPosts ) {
					lastView = foundPosts;
				}
				var rangeInView = ( firstView === lastView ) ? lastView : firstView + ' &ndash; ' + lastView;

				var data = {
					currentPage: currentPage,
					totalPages: totalPages,
					rangeInView: rangeInView,
					totalView: foundPosts,
					filterTags: this.model.get( 'filterTags' ),
					state: this.model.get( 'state' ),
					prevText: this.options.prevText,
					nextText: this.options.nextText,
					firstText: this.options.firstText,
					lastText: this.options.lastText,
					pages: pages
				};

				var template = o2.Utilities.Template( this.options.template );
				this.$el.html( template( data ) );
			}

			return this;
		}
	} );
} )();
