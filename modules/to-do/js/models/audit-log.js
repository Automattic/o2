var o2 = o2 || {};

o2.Models = o2.Models || {};

o2.Models.AuditLog = ( function() {
	return o2.Models.Base.extend( {
		defaults: function() {
			return {
				'avatar': '',
				'log': '',
				'timestamp': Math.round( +new Date() / 1000 )
			};
		}
	} );
} )();
