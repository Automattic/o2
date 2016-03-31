var o2 = o2 || {};

o2.PerformanceMonitor = {
	options: {},

	start: function( options ) {
		this.options = options;

		var _o2 = document.getElementById( this.options.eventElementID );

		var endMasterElapsed = o2.Timing.timer( 'master', 'end master perfmon' );
		o2.PerformanceMonitor.sendMeasurements( [ { kind: 'timer', key: 'end-master-perfmon', value: endMasterElapsed } ] );

		_o2.addEventListener( 'post-postsView-render.o2', function() {
			var renderElapsed = o2.Timing.timer( 'master', 'postsView rendered' );
			// note that we could send more than one measurement back here
			o2.PerformanceMonitor.sendMeasurements( [ { kind: 'timer', key: 'posts-view-rendered', value: renderElapsed } ] );
		} );

		jQuery( document ).ready( function() {
			var readyElapsed = o2.Timing.timer( 'master', 'documentReady' );
			o2.PerformanceMonitor.sendMeasurements( [ { kind: 'timer', key: 'document-ready', value: readyElapsed } ] );
		} );
	},

	sendMeasurements: function( measurements ) {
		jQuery.ajax( {
			dataType: 'json',
			url: this.options.ajaxURL,
			xhrFields: {
				withCredentials: true
			},
			data: {
				action: 'o2_perfmon',
				measurements: measurements,
				nonce: this.options.nonce
			}
		} );
	}

};

