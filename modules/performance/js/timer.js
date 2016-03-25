/* global console */
var o2 = o2 || {};

o2.Timing = {
	timers: [],
	increments: [],

	timer: function( key, description, isNew ) {
		var now = Math.round( +new Date() );
		var elapsed;
		if ( 'undefined' === typeof description ) {
			description = '';
		}

		if ( 'master' !== key ) {
			if ( 'undefined' === typeof this.increments[ key ] ) {
				this.increments[ key ] = 0;
			}

			if ( true === isNew ) {
				this.increments[ key ] = this.increments[key] + 1;
			}

			key = key + ' ' + this.increments[ key ];
		}

		if ( 'undefined' === typeof this.timers[ key ] ) {
			this.timers[ key ] = now;
			elapsed = 0;
		} else {
			var then = this.timers[ key ];
			elapsed = now - then;
		}

		console.log( 'Timer ' + key + ': ' + elapsed + 'ms elapsed (' + description + ')' );

		return elapsed;
	}
};

o2.Timing.timer( 'master', 'start master', true );
