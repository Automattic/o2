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

QUnit.test( 'getUserFor omits d for gravatar_default', function( assert ) {
	assert.strictEqual( o2AvatarFor( 'gravatar_default' ), 'https://gravatar.com/avatar/' + o2TestHash );
	assert.strictEqual( o2AvatarFor( 'identicon' ), 'https://gravatar.com/avatar/' + o2TestHash + '?d=identicon' );
} );

QUnit.test( 'onBlurCommentorEmail omits d for gravatar_default', function( assert ) {
	assert.ok( /\/avatar\/205e460b479e2e5b48aec07710c08d50\?s=32$/.test( o2BlurAvatarFor( 'gravatar_default' ) ) );
	assert.ok( /\/avatar\/205e460b479e2e5b48aec07710c08d50\?s=32&d=identicon$/.test( o2BlurAvatarFor( 'identicon' ) ) );
} );
