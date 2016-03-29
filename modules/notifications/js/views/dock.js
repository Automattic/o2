var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.Dock = ( function( $ ) {
	return wp.Backbone.View.extend( {
		collection: o2.Collections.Notifications,

		el: '#o2-dock',

		originalDocumentTitle: '',

		subviews: {},

		initialize: function() {
			this.originalDocumentTitle = document.title;

			this.listenTo( this.collection, 'add', this.count );
			this.listenTo( this.collection, 'remove', this.count );
			this.listenTo( this.collection, 'reset', this.count );

			this.render();
			this.count();
		},

		events: {
			'click .o2-dock-count': 'toggleDock',
			'click #o2-dock-controls': 'clearDock'
		},

		toggleDock: function() {
			var list = $( '.o2-dock-items' );
			var controls = $( '#o2-dock-controls' );

			// Hide dock by default if visible
			if ( list.is( ':visible' ) ) {
				list.slideUp( 200 );
				controls.hide();

			// Only show dock if it has content
			} else {
				if ( this.collection.length > 0 ) {
					controls.show();
					list.slideDown( 200 );
				} else {
					list.hide();
					controls.hide();
				}
			}
		},

		clearDock: function() {
			_.each( this.collection.where( { type: 'notice' } ), function( model ) {
				model.destroy();
			}, this );
		},

		count: function() {
			var count = this.collection.where( { type: 'notice' } ).length;

			if ( count > 0 ) {
				this.$el.removeClass( 'empty' );
				document.title = '(' + count + ') ' + this.originalDocumentTitle;
			} else {
				this.$el.addClass( 'empty' );
				this.toggleDock();
				document.title = this.originalDocumentTitle;
			}
		},

		render: function() {
			var itemsView = new o2.Views.DockItems( {
				collection: this.collection
			} );

			this.$el.append( itemsView.render().el );
			this.subviews.itemsView = itemsView;

			this.subviews.itemsView.$el.wrap( '<div id="o2-items-scroll"></div>' );

			var countView = new o2.Views.DockCount( {
				collection: this.collection
			} );

			this.$el.prepend( countView.render().el );
			this.subviews.countView = countView;

			this.$el.append( '<div id="o2-dock-controls">' + o2.strings.clearNotifications + '</div>' );

			return this;
		}
	} );
} )( jQuery );
