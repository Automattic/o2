/* global jQuery, o2, QUnit */

var o2TestHash = '205e460b479e2e5b48aec07710c08d50';

QUnit.module( 'Gravatar default', {
	beforeEach: function() {
		this.originalOptions = o2.options;
		this.originalGravatar = window.Gravatar;
		window.Gravatar = { md5: function() { return o2TestHash; } };
	},
	afterEach: function() {
		o2.options = this.originalOptions;
		window.Gravatar = this.originalGravatar;
	}
} );

function o2AvatarFor( defaultAvatar ) {
	o2.options = jQuery.extend( {}, o2.options, { defaultAvatar: defaultAvatar } );
	return new o2.Collections.Users().getUserFor( { noprivUserName: 'x', noprivUserHash: o2TestHash }, 32 ).avatar;
}

function o2BlurAvatarFor( defaultAvatar ) {
	var el = jQuery( '<div><input class="o2-comment-email" value="someone@example.com"><img class="avatar"></div>' );
	o2.options = jQuery.extend( {}, o2.options, { defaultAvatar: defaultAvatar } );
	o2.Views.Comment.prototype.onBlurCommentorEmail.call( { $el: el }, {
		target: el.find( 'input' )[0],
		preventDefault: function() {},
		stopImmediatePropagation: function() {}
	} );
	return el.find( 'img' ).attr( 'src' );
}

// logged-out-create-comment.php appends `&s=` to this URL.
QUnit.test( 'getUserFor omits d for gravatar_default and keeps appended params in the query', function( assert ) {
	var url = new URL( o2AvatarFor( 'gravatar_default' ) + '&s=32' );
	assert.strictEqual( url.pathname, '/avatar/' + o2TestHash );
	assert.strictEqual( url.searchParams.get( 's' ), '32' );
	assert.false( url.searchParams.has( 'd' ) );

	url = new URL( o2AvatarFor( 'identicon' ) + '&s=32' );
	assert.strictEqual( url.pathname, '/avatar/' + o2TestHash );
	assert.strictEqual( url.searchParams.get( 's' ), '32' );
	assert.strictEqual( url.searchParams.get( 'd' ), 'identicon' );
} );

QUnit.test( 'onBlurCommentorEmail omits d for gravatar_default', function( assert ) {
	assert.ok( /\/avatar\/205e460b479e2e5b48aec07710c08d50\?s=32$/.test( o2BlurAvatarFor( 'gravatar_default' ) ) );
	assert.ok( /\/avatar\/205e460b479e2e5b48aec07710c08d50\?s=32&d=identicon$/.test( o2BlurAvatarFor( 'identicon' ) ) );
} );
