var PostActionsExtendsPost = ( function( $ ) {
	return {
		events: {
			'click .o2-dropdown-actions-disclosure': 'onClickDropdown',
			'mouseenter .o2-resolve-wrap': 'onHoverResolvedPosts',
			'mouseenter .o2-post-footer-action': 'onHoverFooterAction',
			'mouseleave .o2-post-footer-action': 'onLeaveFooterAction',
			'mouseenter .o2-comment': 'updateCommentDisclosures',
			'mouseleave .o2-comment': 'updateCommentDisclosures'
		},

		initialize: function() {
			this.listenTo( this.model.comments, 'add', this.updateFooterReplyButtonCount );
			this.listenTo( this.model.comments, 'remove', this.updateFooterReplyButtonCount );
			this.listenTo( this.model, 'o2-post-rendered', this.updateFooterReplyButtonCount );

			_.bindAll( this, 'closeOpenDisclosures' );
			o2.$appContainer.on( 'dropdown-actions:closeall.o2', this.closeOpenDisclosures );
		},

		onClickDropdown: function( event ) {
			event.preventDefault();
			event.stopPropagation();

			var target = $( event.target );
			var needsOpening = ! target.hasClass( 'open' );

			// First, close any open dropdown in any post or comment
			o2.Events.doAction( 'dropdown-actions:closeall.o2', {} );

			// Next, open this dropdown if need be
			if ( needsOpening ) {
				target.siblings( 'ul' ).show();
				target.addClass( 'open' );
			}
		},

		onHoverResolvedPosts: function( event ) {
			var buttonTarget = $( event.target );
			var buttonOffset = buttonTarget.offset();
			var buttonWidth = buttonTarget.width();

			var auditList = buttonTarget.siblings( 'ul' );

			auditList.offset( {
				left: buttonOffset.left + buttonWidth + 20,
				top: buttonOffset.top
			} );
		},

		/*
		 * If a footer post action has hoverText defined for its current state, swap in that text on hover
		 */
		onHoverFooterAction: function( event ) {
			var buttonTarget = $( event.target );
			var action = buttonTarget.data( 'action' );
			var actionState = buttonTarget.data( 'actionstate' );
			if ( ( 'undefined' !== typeof action ) && ( 'undefined' !== typeof actionState ) ) {
				var hoverText = o2.PostActionStates.stateDictionary[ action ][ actionState ].hoverText;
				if ( 'undefined' !== typeof hoverText ) {
					buttonTarget.text( hoverText );
				}
			}
		},

		/*
		 * If a footer post action has hoverText defined for its current state, swap the original text
		 * back in when no longer hovering
		 */
		onLeaveFooterAction: function( event ) {
			var buttonTarget = $( event.target );
			var action = buttonTarget.data( 'action' );
			var actionState = buttonTarget.data( 'actionstate' );
			if ( ( 'undefined' !== typeof action ) && ( 'undefined' !== typeof actionState ) ) {
				var hoverText = o2.PostActionStates.stateDictionary[ action ][ actionState ].hoverText;
				if ( 'undefined' !== typeof hoverText ) {
					var shortText = o2.PostActionStates.stateDictionary[ action ][ actionState ].shortText;
					buttonTarget.text( shortText );
				}
			}
		},

		updateFooterReplyButtonCount: function() {
			var footerReplyButton = this.$el.find( '.o2-post-reply' );
			if ( footerReplyButton.length ) {
				footerReplyButton.find( '.o2-reply-count' ).remove();
				// var commentCount = this.model.comments.length;

				var commentCount = this.model.comments.filter( function( model ) {
					return ( 'undefined' !== typeof model.id );
				} ).length;

				if ( commentCount > 0 ) {
					footerReplyButton.append( '<span class="o2-reply-count">' + commentCount + '</span>' );
				}
			}
		},

		closeOpenDisclosures: function() {
			// Removes the open class from any disclosure and hide the ul
			this.$el.find( '.o2-dropdown-actions-disclosure' ).removeClass( 'open' );
			this.$el.find( '.o2-dropdown-actions > ul' ).hide();

			// Lastly, update comment disclosures (to avoid ones that were
			// tapped open but no longer hovered from remaining visible)
			this.updateCommentDisclosures();
		},

		updateCommentDisclosures: function() {
			var isTouch = false;
			if ( 'undefined' !== typeof o2.options.isMobileOrTablet ) {
				isTouch = o2.options.isMobileOrTablet;
			}

			// if we are on a touch device, don't hide anything
			if ( ! isTouch ) {
				// Otherwise, only show comment action disclosure on comments without hovered children
				for ( var subview in this.subviews ) {
					var commentEl = this.subviews[ subview ].$el;
					var disclosure = commentEl.find( '.o2-dropdown-actions-disclosure' ).first();

					// If the disclosure has the open class, leave it alone
					if ( ! disclosure.hasClass( 'open' ) ) {
						// Otherwise, do our hover logic
						if ( commentEl.hasClass( 'hovered' ) ) {
							if ( commentEl.find( '.hovered' ).length ) {
								disclosure.hide();
							} else {
								disclosure.show();
							}
						} else {
							disclosure.hide();
						}
					}
				}
			}
		}
	};
} )( jQuery );

Cocktail.mixin( o2.Views.Post, PostActionsExtendsPost );
