var o2 = o2 || {};

o2.Routers = o2.Routers || {};

o2.Routers.ToDos = ( function( $, Backbone ) {
	return Backbone.Router.extend( {
		initialize: function( options ) {
			var toDos = new o2.Collections.ToDos( options.data );
			var toDosWidgetsModels = [];
			var toDosWidgetsViews = [];
			var toDosFound = {};

			_.each( options.found, function( found ) {
				toDosFound[ found.widgetID ] = found.found;
			} );

			// Initialize each widget as a new View
			var widgets = $( '.o2-extend-resolved-posts-unresolved-posts-widget' );
			if ( widgets.length > 0 ) {
				_.each( widgets, function( widget ) {
					var data = $( widget ).find( 'div' );
					var model = new o2.Models.ToDosWidget( {
						widgetID: widget.id,
						state: data.data( 'state' ),
						order: data.data( 'order' ),
						postsPerPage: data.data( 'postsPerPage' ),
						filterTags: data.data( 'filterTags' ),
						foundPosts: toDosFound[ widget.id ],
						totalPages: Math.ceil( toDosFound[ widget.id ] / data.data( 'postsPerPage' ) ),
						collection: toDos
					} );
					toDosWidgetsModels.push( model );
					var view = new o2.Views.ToDosWidget( {
						model: model
					} );
					toDosWidgetsViews.push( view );
				} );
			}

			this.toDos = toDos;                           // Collection of all resolved post fragments
			this.toDosWidgetsModels = toDosWidgetsModels; // Array of widget models
			this.toDosWidgetViews = toDosWidgetsViews;    // Array of widget views
			this.toDosFound = toDosFound;                 // Object of key state, value total found posts
		}
	} );
} )( jQuery, Backbone );

( function() {
	o2.startToDos = function( bootstrap ) {
		o2.ToDos = o2.ToDos || new o2.Routers.ToDos( bootstrap );
		return o2.ToDos;
	};
} )();
