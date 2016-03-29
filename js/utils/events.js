var o2 = o2 || {};

( function( $ ) {

o2.Events = {
	actions: {},
	filters: {},
	events: {},
	currentActions: [],
	currentFilters: [],
	doneActions: [],
	doneFilters: [],

	reset: function() {
	},

	compare: function( a, b ) {
		if ( a.priority < b.priority ) {
			return -1;
		}
		if ( a.priority > b.priority ) {
			return 1;
		}
		return 0;
	},

	_addCallback: function( type, hook, callback, currentObject, priority, args ) {
		var r = this._getHook( type, hook );
		if ( ! this._callbackExists( type, hook, callback, priority ) ) {
			r.push( {
				callback: callback,
				priority: priority,
				currentObject: currentObject,
				args: args
			} );
			this._setHook( type, hook, r );
		}
	},

	_callbackExists: function( type, hook, callback, priority ) {
		var r = this._getHook( type, hook );
		r.forEach( function( e ) {
			if ( e.callback.toString() === callback.toString() && e.priority === priority ) {
				return true;
			}
		} );
		return false;
	},

	_getCurrentHook: function( type ) {
		var l;
		if ( 'actions' === type ) {
			l = this.currentActions.length;
			if ( l > 0 ) {
				return this.currentActions[ l - 1 ];
			}
		} else if ( 'filters' === type ) {
			l = this.currentFilters.length;
			if ( l > 0 ) {
				return this.currentFilters[ l - 1 ];
			}
		}
		return false;
	},

	_getHook: function( type, hook ) {
		var r = this[ type ][ hook ];
		if ( 'undefined' === typeof r ) {
			r = [];
		}
		r.sort( this.compare );
		return r;
	},

	_removeCallback: function( type, hook, callback, priority ) {
		var r = this._getHook( type, hook),
			filtered = [];
		r.forEach( function( e ) {
			if ( e.callback.toString() === callback.toString() && e.priority === priority ) {
				// Do nothing.
			} else {
				filtered.push( e );
			}
		} );
		this._setHook( type, hook, filtered );
	},

	_setHook: function( type, hook, r ) {
		o2.Events[ type ][ hook ] = r;
	},

	addAction: function( /* hook, callback */ ) {
	},

	currentAction: function() {
		return this._getCurrentHook( 'actions' );
	},

	doAction: function( hook /*, args */ ) {
		if ( 'string' === typeof hook ) {
			var args = Array.prototype.slice.call( arguments, 1 );
			this.currentActions.push( hook );
			o2.$appContainer.trigger( hook, args );
			if ( ! this.didAction( hook ) ) {
				this.doneActions.push( hook );
			}
			this.currentActions.pop();
		}
	},

	didAction: function( hook ) {
		if ( -1 === $.inArray( hook, this.doneActions ) ) {
			return false;
		}
		return true;
	},

	removeAction: function( /* hook, callback */ ) {
	},

	removeAllActions: function( /* hook */ ) {
	},

	addFilter: function( hook, callback, currentObject, priority, args ) {
		if ( 'string' === typeof hook && 'function' === typeof callback ) {
			priority = parseInt( ( priority || 10 ), 10 );
			args = parseInt( ( args || 1 ), 10 );
			this._addCallback( 'filters', hook, callback, currentObject, priority, args );
		}
	},

	applyFilters: function() {
		if ( arguments.length > 1 && 'string' === typeof arguments[0] ) {
			this.currentFilters.push( arguments[0] );
			var callbacks = this._getHook( 'filters', arguments[0] );
			var args = Array.prototype.slice.call( arguments, 1 );
			$.each( callbacks, function( i, filter ) {
				if ( filter.args === args.length ) {
					args[0] = filter.callback.apply( filter.currentObject, args );
				}
			} );
			this.doneFilters.push( this.currentFilters.pop() );
			return args[0];
		}
	},

	currentFilter: function() {
		return this._getCurrentHook( 'filters' );
	},

	removeFilter: function( hook, callback, priority ) {
		if ( arguments.length > 1 && 'string' === typeof arguments[0] ) {
			priority = parseInt( ( priority || 10 ), 10 );
			this._removeCallback( 'filters', hook, callback, priority );
		}
	},

	removeAllFilters: function( /* hook, priority */ ) {
	},

	addEvent: function( hook, args ) {
		var priority = +new Date(),
			maxPriority = 0;
		var events = this._getHook( 'events', hook );
		$.each( events, function( i, e ) {
			if ( e.priority > maxPriority ) {
				maxPriority = e.priority;
			}
		} );
		if ( priority < maxPriority ) {
			priority = maxPriority + 1;
		}
		this._addCallback( 'events', hook, function() { return; }, null, priority, args );
		return priority;
	},

	hasPriorEvents: function( hook, priority ) {
		var hasPriorEvents = false;
		var events = this._getHook( 'events', hook );
		$.each( events, function( i, e ) {
			if ( e.priority < priority ) {
				hasPriorEvents = true;
				return false;
			}
		} );
		return hasPriorEvents;
	},

	removeEvent: function( hook, priority ) {
		this._removeCallback( 'events', hook, function() { return; }, priority );
	}
};

} )( jQuery );

o2.Events.dispatcher = _.clone( Backbone.Events );
