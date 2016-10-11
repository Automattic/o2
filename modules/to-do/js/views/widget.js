var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.ToDosWidget = ( function( $ ) {
	return wp.Backbone.View.extend( {
		model: o2.Models.ToDosWidget,

		paginationView: o2.Views.Pagination,

		toDosView: o2.Views.ToDos,

		_isFetching: false,

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
			this.listenTo( this.model, 'o2-extend-resolved-posts-fetch-error', this.fetchedError );
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
			if ( p < 1 ) {
				p = 1;
			}
			this.model.set( 'currentPage', p );
		},

		nextPage: function( e ) {
			e.preventDefault();
			var p = this.model.get( 'currentPage' );
			var lastPage = Math.ceil( this.model.get( 'foundPosts' ) / this.model.get( 'postsPerPage' ) );
			p++;
			if ( p > lastPage ) {
				p = lastPage;
			}
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
			// Avoid multiple requests for the same data.
			if ( this._isFetching ) {
				return;
			}

			// Only fetch more fragments if we don't have all of them.
			if ( o2.ToDos.toDosFound[ this.model.get( 'widgetID' ) ] > _.size( this.model.get( 'collection' ).where( { widgetID: this.model.get( 'widgetID' ) } ) ) ) {
				this._isFetching = true;
				var data = {
					callback: 'o2-extend-resolved-posts-fetch',
					currentPage: this.model.get( 'currentPage' ),
					postsPerPage: this.model.get( 'postsPerPage' ),
					filterTags: this.model.get( 'filterTags' ),
					state: this.model.get( 'state' ),
					widgetID: this.model.get( 'widgetID' )
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
			o2.ToDos.toDosFound[ this.model.get( 'widgetID' ) ] = foundPosts;

			this._isFetching = false;

			// Fetched fragments should be ready to render now
			this.render();
		},

		fetchedError: function() {
			this._isFetching = false;
		}
	} );
} )( jQuery );
