/* global enquire */
var o2 = o2 || {};

o2.Views = o2.Views || {};

o2.Views.NoPostsPost = ( function() {
	return wp.Backbone.View.extend( {
		tagName: 'article',

		className: 'o2-no-posts-post',

		defaults: function() {
			return {
				isNarrowScreen: false
			};
		},

		initialize: function( options ) {
			this.options = this.defaults();
			this.options = _.extend( this.options, options );

			if ( 'undefined' !== typeof enquire ) {
				_.bindAll( this, 'onScreenNarrowed', 'onScreenWidened' );
				enquire.register( 'screen and (max-width:640px)', {
					match:  this.onScreenNarrowed,
					unmatch: this.onScreenWidened
				} );
			}
		},

		onScreenWidened: function() {
			this.options.isNarrowScreen = false;
			this.render();
		},

		onScreenNarrowed: function() {
			this.options.isNarrowScreen = true;
			this.render();
		},

		render: function() {
			var data = {};
			data.text = ( this.options.isNarrowScreen ? o2.strings.noPostsMobile : o2.strings.noPosts );
			var template = o2.Utilities.Template( this.options.template );
			this.$el.html( template( data ) );
			return this;
		}
	} );
} )();
