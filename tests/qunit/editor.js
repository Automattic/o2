/* global jQuery, o2Editor, QUnit */

QUnit.module( 'Editor', {
	afterEach: function() {
		jQuery( '#qunit-fixture' ).empty();
	}
} );

QUnit.test( 'rebuild keeps stored content inside the textarea', function( assert ) {
	var content = 'Ordinary text </textarea><div id="o2-editor-proof">markup-like text</div>',
		fixture = jQuery( '#qunit-fixture' );

	fixture.html(
		'<textarea class="o2-editor" title="Quoted &quot;title&quot;" placeholder="Write something">' +
		'Ordinary text &lt;/textarea&gt;&lt;div id=&quot;o2-editor-proof&quot;&gt;markup-like text&lt;/div&gt;' +
		'</textarea>'
	);

	o2Editor.detectAndRender( fixture );

	assert.strictEqual(
		fixture.find( '#o2-editor-proof' ).length,
		0,
		'stored content does not create sibling elements'
	);
	assert.strictEqual(
		fixture.find( '.o2-editor-text' ).val(),
		content,
		'stored content remains the editor value'
	);
	assert.strictEqual(
		fixture.find( '.o2-editor-text' ).attr( 'placeholder' ),
		'Write something',
		'the placeholder is preserved'
	);
	assert.strictEqual(
		fixture.find( '.o2-editor-title' ).val(),
		'Quoted "title"',
		'the title is preserved'
	);
} );
