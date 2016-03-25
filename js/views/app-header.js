/* global wpCookies */
var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.AppHeader = ( function() {
	return wp.Backbone.View.extend({
		tagName: 'header', // @todo this at least needs to be filterable, if not just a wrapper around the full template block

		className: 'o2-app-header',

		model: o2.Models.PageMeta,

		defaults: function() {
			return {
				showTitle: true,
				showComments: true
			};
		},

		initialize: function( options ) {
			this.listenTo( this.model, 'change', this.render );
			this.options = _.extend( this.defaults, options );
		},

		events: {
			'click .o2-toggle-comments': 'onToggleComments'
		},

		onToggleComments: function( event ) {
			event.preventDefault();
			this.options.showComments = ! this.options.showComments;
			o2.Events.dispatcher.trigger( 'update-posts-view-options.o2', { showComments: this.options.showComments } );
			o2.Events.dispatcher.trigger( 'update-post-view-options.o2', { showComments: this.options.showComments } );
			if ( ! this.options.showComments ) {
				wpCookies.set( 'showComments', this.options.showComments, 315360000 ); // Go big - 10 years
			} else {
				wpCookies.remove( 'showComments' ); // Defaults to showing
			}

			this.toggleCommentLabel();
		},

		toggleCommentLabel: function() {
			// Toggle label
			var altText = '',
					commentToggle = this.$el.find( '.o2-toggle-comments' );
			altText = commentToggle.text();
			commentToggle.text( commentToggle.data( 'alternateText' ) );
			commentToggle.data( 'alternateText', altText );
		},

		render: function() {
			var template               = o2.Utilities.Template( this.options.template );
			var jsonifiedModel         = this.model.toJSON();
			jsonifiedModel.showTitle   = this.options.showTitle;
			jsonifiedModel.strings     = o2.strings;
			jsonifiedModel.appControls = o2.appControls;
			this.$el.html( template( jsonifiedModel ) );

			if ( !this.options.showComments ) {
				this.toggleCommentLabel();
			}

			return this;
		}
	} );
} )();
