/* global jQuery, o2, QUnit */

// Comment bodies are author-supplied HTML, so a view must not act on or read content that is not its own.

function o2ClickOn( el ) {
	return {
		currentTarget: el,
		target: el,
		preventDefault: function() {},
		stopPropagation: function() {},
		stopImmediatePropagation: function() {}
	};
}

// Replaces the globals these handlers reach for, and hands back a restore function.
function o2StubGlobals() {
	var original = {
		options: o2.options,
		Utilities: o2.Utilities,
		Events: o2.Events,
		Notifications: o2.Notifications,
		PostActionStates: o2.PostActionStates
	};

	// postId is load-bearing: 0 sends onTrash down a different path.
	o2.options = jQuery.extend( {}, o2.options, { postId: 7, followingBlog: false } );
	o2.Utilities = { rawToFiltered: function( raw ) { return raw; } };
	o2.Events = { doAction: function() {}, dispatcher: { trigger: function() {} } };
	o2.Notifications = { add: function() {}, notifications: { findFirstAndDestroy: function() {} } };
	o2.PostActionStates = { getNextState: function() { return 'resolved'; }, setState: function() {} };

	return function() {
		o2.options = original.options;
		o2.Utilities = original.Utilities;
		o2.Events = original.Events;
		o2.Notifications = original.Notifications;
		o2.PostActionStates = original.PostActionStates;
	};
}

QUnit.module( 'Views.Post ownership boundary', {
	beforeEach: function() {
		this.restoreGlobals = o2StubGlobals();
	},
	afterEach: function() {
		this.restoreGlobals();
	}
} );

// A post view stub carrying the real guards, recording what each handler would have done.
function o2PostUnderTest( fixture ) {
	var did = [];

	return {
		did: did,
		view: {
			$el: fixture,
			$: function( selector ) { return this.$el.find( selector ); },
			options: { isDragging: false, viewFormat: 'standard' },
			isPostControl: o2.Views.Post.prototype.isPostControl,
			isCommentContent: o2.Views.Post.prototype.isCommentContent,
			$post: o2.Views.Post.prototype.$post,
			isSameHost: function() { return true; },
			renderPost: function() {},
			updateFollowView: function() {},
			stickyPostView: function() {},
			getPostModelCurrentState: function() { return 'unresolved'; },
			setPostModelState: function() {},
			copyToClipboard: function( text ) { did.push( text ); },
			destroyViewModel: function() { did.push( 'trashed' ); },
			model: {
				save: function() { did.push( 'saved' ); },
				changeFollow: function() {},
				changeSticky: function() {},
				isSticky: function() { return true; },
				get: function() { return 7; }
			}
		}
	};
}

// Each pair is a delegated post handler and the control class it answers to.
[
	{ handler: 'onTrash', control: 'o2-trash' },
	{ handler: 'onShortLinkClick', control: 'o2-short-link' },
	{ handler: 'onClickStickyPost', control: 'o2-sticky-link' },
	{ handler: 'onClickResolvedPosts', control: 'o2-resolve-link' },
	{ handler: 'updateFollow', control: 'o2-follow' }
].forEach( function( subject ) {
	QUnit.test( subject.handler + ' ignores a control planted in a comment', function( assert ) {
		var fixture = jQuery( '#qunit-fixture' ).html(
				'<div class="o2-post"><a class="' + subject.control + '" href="http://own.test/x">Do it</a></div>' +
				'<div class="o2-post-comments"><div class="o2-comment">' +
				'<div class="comment-content">' +
				'<a class="' + subject.control + '" id="planted" href="http://evil.test/x">Do it</a>' +
				'</div>' +
				'</div></div>'
			),
			underTest = o2PostUnderTest( fixture );

		o2.Views.Post.prototype[ subject.handler ].call(
			underTest.view,
			o2ClickOn( fixture.find( '#planted' )[ 0 ] )
		);

		assert.deepEqual( underTest.did, [], 'the planted control does nothing' );
	} );
} );

// `.o2-short-link` is a post action here, but `o2_comment_actions` lets a site register the
// same control on a comment, and o2 has no separate comment-side handler for it. So this one
// handler answers to both, and the line it draws is author-supplied HTML rather than the
// comment list. A wrapper class cannot be used instead: comment content is filtered with
// `wp_filter_post_kses` wherever a site allows post HTML in comments, which permits `div` and
// the global `class` attribute, so an author could wrap a planted control to match. Ancestry
// is not forgeable in the same way.
QUnit.test( 'onShortLinkClick copies from a control rendered as a comment action', function( assert ) {
	var fixture = jQuery( '#qunit-fixture' ).html(
			'<div class="o2-post"></div>' +
			'<div class="o2-post-comments"><div class="o2-comment">' +
			'<div class="o2-comment-header"><nav class="o2-comment-actions">' +
			'<a class="o2-short-link" id="own" href="http://own.test/?p=1#comment-9">Copy shortlink</a>' +
			'</nav></div>' +
			'<div class="comment-content">A comment body.</div>' +
			'</div></div>'
		),
		underTest = o2PostUnderTest( fixture );

	o2.Views.Post.prototype.onShortLinkClick.call(
		underTest.view,
		o2ClickOn( fixture.find( '#own' )[ 0 ] )
	);

	assert.deepEqual(
		underTest.did,
		[ 'http://own.test/?p=1#comment-9' ],
		'a comment action outside .comment-content still copies'
	);
} );

QUnit.test( 'onShortLinkClick ignores a control wrapped to look like a comment action', function( assert ) {
	var fixture = jQuery( '#qunit-fixture' ).html(
			'<div class="o2-post"></div>' +
			'<div class="o2-post-comments"><div class="o2-comment">' +
			'<div class="comment-content"><nav class="o2-comment-actions">' +
			'<a class="o2-short-link" id="planted" href="http://evil.test/x">Copy shortlink</a>' +
			'</nav></div>' +
			'</div></div>'
		),
		underTest = o2PostUnderTest( fixture );

	o2.Views.Post.prototype.onShortLinkClick.call(
		underTest.view,
		o2ClickOn( fixture.find( '#planted' )[ 0 ] )
	);

	assert.deepEqual( underTest.did, [], 'the wrapper class does not buy its way out of .comment-content' );
} );

QUnit.test( 'onShortLinkClick still copies the post\'s own shortlink', function( assert ) {
	var fixture = jQuery( '#qunit-fixture' ).html(
			'<div class="o2-post">' +
			'<a class="o2-short-link" id="own" href="http://own.test/?p=1">Copy shortlink</a>' +
			'</div>' +
			'<div class="o2-post-comments"></div>'
		),
		underTest = o2PostUnderTest( fixture );

	o2.Views.Post.prototype.onShortLinkClick.call(
		underTest.view,
		o2ClickOn( fixture.find( '#own' )[ 0 ] )
	);

	assert.deepEqual( underTest.did, [ 'http://own.test/?p=1' ], 'the post\'s own control still works' );
} );

QUnit.test( 'onTrash still trashes the post from its own control', function( assert ) {
	var fixture = jQuery( '#qunit-fixture' ).html(
			'<div class="o2-post"><a class="o2-trash" id="own">Trash</a></div>' +
			'<div class="o2-post-comments"></div>'
		),
		underTest = o2PostUnderTest( fixture );

	o2.Views.Post.prototype.onTrash.call( underTest.view, o2ClickOn( fixture.find( '#own' )[ 0 ] ) );

	assert.deepEqual( underTest.did, [ 'trashed' ], 'the post\'s own control still works' );
} );

QUnit.test( 'onSave stores the post\'s own content, not a comment posing as the post', function( assert ) {
	var sent = {},
		fixture = jQuery( '#qunit-fixture' ).html(
			'<div class="o2-post">' +
			'<input class="o2-title" value="own title">' +
			'<textarea class="o2-editor-text">own body</textarea>' +
			'</div>' +
			'<div class="o2-post-comments"><div class="o2-comment"><div class="o2-post">' +
			'<input class="o2-title" value="planted title">' +
			'<textarea class="o2-editor-text">planted body</textarea>' +
			'</div></div></div>'
		),
		underTest = o2PostUnderTest( fixture );

	underTest.view.model.save = function( attrs ) { sent = attrs; };
	o2.Views.Post.prototype.onSave.call(
		underTest.view,
		o2ClickOn( fixture.find( '.o2-post .o2-editor-text' )[ 0 ] )
	);

	assert.strictEqual( sent.contentRaw, 'own body', 'the body comes from the post' );
	assert.strictEqual( sent.titleRaw, 'own title', 'and so does the title' );
} );

QUnit.module( 'Views.Comment ownership boundary', {
	beforeEach: function() {
		this.restoreGlobals = o2StubGlobals();
	},
	afterEach: function() {
		this.restoreGlobals();
	}
} );

function o2CommentUnderTest( fixture ) {
	var sent = {};

	return {
		sent: sent,
		view: {
			$el: fixture,
			// A logged-in author, so onSave takes the branch that reads the editor.
			options: { currentUser: { userLogin: 'author' } },
			$ownFind: o2.Views.Comment.prototype.$ownFind,
			model: { save: function( attrs ) { jQuery.extend( sent, attrs ); } }
		}
	};
}

QUnit.test( 'onSave stores the comment\'s own editor, not a reply\'s', function( assert ) {
	var fixture = jQuery( '#qunit-fixture' ).html(
			'<textarea class="o2-editor-text">own</textarea>' +
			'<div class="o2-child-comments"><div class="o2-comment">' +
			'<textarea class="o2-editor-text" style="display:none">planted</textarea>' +
			'</div></div>'
		),
		underTest = o2CommentUnderTest( fixture );

	o2.Views.Comment.prototype.onSave.call( underTest.view, o2ClickOn( fixture[ 0 ] ) );

	assert.strictEqual( underTest.sent.contentRaw, 'own', 'the author\'s own content is what gets saved' );
} );

QUnit.test( 'onSave is not fooled by a reply nested deeper', function( assert ) {
	var fixture = jQuery( '#qunit-fixture' ).html(
			'<textarea class="o2-editor-text">own</textarea>' +
			'<div class="o2-child-comments"><div class="o2-comment">' +
			'<div class="o2-comment-text"><span>' +
			'<textarea class="o2-editor-text">planted deeper</textarea>' +
			'</span></div>' +
			'</div></div>'
		),
		underTest = o2CommentUnderTest( fixture );

	o2.Views.Comment.prototype.onSave.call( underTest.view, o2ClickOn( fixture[ 0 ] ) );

	assert.strictEqual( underTest.sent.contentRaw, 'own', 'a reply nested below the top level does not win' );
} );

QUnit.test( 'a subscribe box planted in a reply does not follow the blog', function( assert ) {
	var fixture = jQuery( '#qunit-fixture' ).html(
			'<textarea class="o2-editor-text">own</textarea>' +
			'<div class="o2-child-comments"><div class="o2-comment">' +
			'<input type="checkbox" id="subscribe_blog" checked>' +
			'</div></div>'
		),
		underTest = o2CommentUnderTest( fixture );

	o2.Views.Comment.prototype.onSave.call( underTest.view, o2ClickOn( fixture[ 0 ] ) );

	assert.false( o2.options.followingBlog, 'the planted checkbox does not follow the blog' );
} );
