var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.ToDosWidget = ( function( $, Backbone ) {
	return wp.Backbone.View.extend( {
		model: o2.Models.ToDosWidget,

		paginationView: o2.Views.Pagination,

		toDosView: o2.Views.ToDos,

		events: {
			'mouseenter ul': 'fetchAll',
			'mouseenter div': 'fetchAll',
			'click .page': 'thisPage',
			'click .prev': 'prevPage',
			'click .next': 'nextPage',
			'click .first': 'firstPage',
			'click .last': 'lastPage'
		},

		initialize: function() {
			this.paginationView = new o2.Views.Pagination( {
				model: this.model,
				padding: -1,
				prevText: '&larr;',
				nextText: '&rarr;',
				template: 'extend-resolved-posts-pagination'
			} );
			this.toDosView = new o2.Views.ToDos( { model: this.model } );

			this.render();

			this.listenTo( this.model, 'change', this.render );
			this.listenTo( this.model, 'o2-extend-resolved-posts-fetch', this.fetched );
		},

		render: function() {
			this.$el.empty();

			this.$el.prepend( this.paginationView.render().el );
			this.$el.append( this.toDosView.render().el );

			$( '#' + this.model.get( 'widgetID' ) ).append( this.el );

			return this;
		},

		thisPage: function( e ) {
			e.preventDefault();
			var p = parseInt( e.currentTarget.innerText, 10 );
			this.model.set( 'currentPage', p );
		},

		prevPage: function( e ) {
			e.preventDefault();
			var p = this.model.get( 'currentPage' );
			p--;
			if ( p < 1 )
				p = 1;
			this.model.set( 'currentPage', p );
		},

		nextPage: function( e ) {
			e.preventDefault();
			var p = this.model.get( 'currentPage' );
			var lastPage = Math.ceil( this.model.get( 'foundPosts' ) / this.model.get( 'postsPerPage' ) );
			p++;
			if ( p > lastPage )
				p = lastPage;
			this.model.set( 'currentPage', p );
		},

		firstPage: function( e ) {
			e.preventDefault();
			this.model.set( 'currentPage', 1 );
		},

		lastPage: function( e ) {
			e.preventDefault();
			var lastPage = Math.ceil( this.model.get( 'foundPosts' ) / this.model.get( 'postsPerPage' ) );
			this.model.set( 'currentPage', lastPage );
		},

		fetchAll: function() {
			// Only fetch more fragments if we don't have all of them
			if ( o2.ToDos.toDosFound[ this.model.get( 'state' ) ] > _.size( this.model.get( 'collection' ).where( { state: this.model.get( 'state' ) } ) ) ) {
				var data = {
					callback: 'o2-extend-resolved-posts-fetch',
					currentPage: this.model.get( 'currentPage' ),
					postsPerPage: this.model.get( 'postsPerPage' ),
					state: this.model.get( 'state' )
				};
				o2.Query.query( {
					target: this.model,
					data: data
				} );
			}
		},

		fetched: function( e ) {
			var foundPosts = _.size( e.data.posts );
			this.model.set( 'foundPosts', foundPosts );
			_.each( e.data.posts, function( post ) {
				o2.ToDos.toDos.add( post );
			} );
			o2.ToDos.toDosFound[ this.model.get( 'state' ) ] = foundPosts;

			// Fetched fragments should be ready to render now
			this.render();
		}
	} );
} )( jQuery, Backbone );
