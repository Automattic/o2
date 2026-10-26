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

QUnit.test( 'rebuild decodes an entity-encoded placeholder without parsing markup', function( assert ) {
	var fixture = jQuery( '#qunit-fixture' );

	fixture.html(
		'<textarea class="o2-editor" placeholder="What&amp;#039;s happening?"></textarea>' +
		'<textarea class="o2-editor" placeholder="&amp;lt;img id=&quot;o2-placeholder-proof&quot;&amp;gt;"></textarea>' +
		'<textarea class="o2-editor" placeholder="&lt;img id=&quot;o2-placeholder-proof&quot;&gt;"></textarea>'
	);

	o2Editor.detectAndRender( fixture );

	var editors = fixture.find( '.o2-editor-text' );

	assert.strictEqual( editors.eq( 0 ).attr( 'placeholder' ), 'What\'s happening?', 'entities in the prompt are decoded' );
	assert.strictEqual( editors.eq( 1 ).attr( 'placeholder' ), '<img id="o2-placeholder-proof">', 'markup in the prompt stays text' );
	assert.strictEqual( editors.eq( 2 ).attr( 'placeholder' ), '<img id="o2-placeholder-proof">', 'raw markup in the attribute stays text' );
	assert.strictEqual( fixture.find( '#o2-placeholder-proof' ).length, 0, 'the prompt does not create elements' );
} );
