var o2 = o2 || {};

o2.PostActionStates = ( function( $ ) {
	return ( {
		stateDictionary: {},

		getCurrentState: function( element ) {
			var target = $( element );
			if ( 'undefined' !== typeof target ) {
				return target.data( 'actionstate' );
			}
		},

		getAction: function( element ) {
			var target = $( element );
			var action = target.data( 'action' );
			return action;
		},

		getStateData: function( action, state ) {
			var actionData = this.stateDictionary[ action ];
			if ( 'undefined' !== typeof actionData ) {
				return this.stateDictionary[ action ][ state ];
			}
		},

		getNextState: function( action, state ) {
			var stateData = this.getStateData( action, state );
			if ( 'undefined' !== typeof stateData ) {
				return stateData.nextState;
			}
		},

		setState: function( element, newState ) {
			var action = this.getAction( element );
			var currentState = this.getCurrentState( element );

			if ( ( 'undefined' !== typeof action ) && ( 'undefined' !== typeof currentState ) ) {
				var oldStateData = this.getStateData( action, currentState );
				var newStateData = this.getStateData( action, newState );
				var target = $( element );
				var post = target.closest( o2.options.threadContainer );
				var i;

				// Remove classes from post for the old state
				for ( i = 0; i < oldStateData.classes.length; i++ ) {
					post.removeClass( oldStateData.classes[i] );
				}

				// Remove genericon classes from the target for the old state
				if ( 'undefined' !== typeof oldStateData.genericon ) {
					target.removeClass( oldStateData.genericon );
				}

				// Add classes to the post for the new state
				for ( i = 0; i < newStateData.classes.length; i++ ) {
					post.addClass( newStateData.classes[i] );
				}

				// Add genericon classes to the target for the new state
				if ( 'undefined' !== typeof newStateData.genericon ) {
					target.addClass( newStateData.genericon );
				}

				target.data( 'actionstate', newState );
				target.attr( 'title', newStateData.title ); // TODO only on <a>s?
				target.text( newStateData.shortText );
			}
		} // setState
	} );
} )( jQuery );
