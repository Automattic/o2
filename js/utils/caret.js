/**
 * caret.js -- A lightweight, cross-browser library for manipulating carets.
 *
 * Author: Daryl Koopersmith
 */

( function() {

	var Caret, rWordEnd = /\S+$/, rWordStart = /^\S+/;

	Caret = window.Caret = function( element ) {
		// Factory/Constructor
		if ( ! ( this instanceof Caret ) )
			return new Caret( element );

		this.element = element;
		this.refresh();
	};

	/**
	 * Many methods exist along these lines; the pitfalls surround IE.
	 * This method accounts for the following:
	 *     - IE sometimes counts an additional \r for each newline.
	 *       This can inflate the perceived caret position.
	 *     - Certain methods ignore whitespace immediately before the cursor.
	 *     - If the value is altered and the caret is at the end of the
	 *       textarea, it will deselect when retreived.
	 */
	Caret.prototype.refresh = function() {
		var range, bookmark, original, marker, parent, result, start, end,
			element = this.element;

		// Check if W3C properties exist.
		if ( ( 'undefined' !== typeof element ) && ( 'undefined' !== typeof element.selectionStart ) && ( 'undefined' !== typeof element.selectionEnd ) ) {
			return this._set( element.selectionStart, element.selectionEnd );
		}

		// If selection API doesn't exist either, bail.
		if ( ! document.selection ) {
			return this._set( 0 );
		}

		element.focus();
		range    = document.selection.createRange();
		bookmark = range.getBookmark();
		original = element.value;
		marker   = String.fromCharCode( 28 );
		parent   = range.parentElement();

		// Check if we're inside a textarea or text input.
		if ( ( null === parent ) || ! ( 'textarea' === parent.type || 'text' === parent.type ) ) {
			return this._set( 0 );
		}

		// Add markers for start and end positions.
		// Otherwise trailing whitespace will be stripped.
		range.text = marker + range.text + marker;

		// \r's are counted for each newline... remove them.
		contents = element.value.replace( /\r/g, '' );

		// Find the caret positions
		start = contents.indexOf( marker );
		// Remove the first marker. Otherwise the end index will be wrong.
		end = contents.replace( marker, '' ).indexOf( marker );
		this._set( start, end );

		// Restore the value and selection
		element.value = original;

		// In textareas, if the caret is the final character, the bookmark
		// will shift the selected element to the body.
		//
		// This is a more efficient version of:
		//   if ( document.selection.createRange().parentElement() != element )
		//
		if ( original.length == start && 'textarea' == parent.type ) {
			this.set( element, element.value.length );
		} else {
			range.moveToBookmark( bookmark );
			range.select();
		}
	};

	/**
	 * Internal.
	 *
	 * Sets this.start and this.end.
	 * Does nothing else!
	 *
	 * @param start The starting index.
	 * @param end   Optional. The ending index. Defaults to start.
	 */
	Caret.prototype._set = function( start, end ) {
		this.start = start;
		this.end = ( 'undefined' === typeof end ) ? start : end;
	};

	/**
	 * Sets the caret position.
	 *
	 * @param start The starting index.
	 * @param end   Optional. The ending index. Defaults to start.
	 */
	Caret.prototype.set = function( start, end ) {
		var range;
		end = ( 'undefined' === typeof end ) ? start : end;

		this._set( start, end );

		// W3C
		if ( this.element.setSelectionRange ) {
			this.element.setSelectionRange( start, end );

		// IE
		} else if ( this.element.createTextRange ) {
			range = this.element.createTextRange();

			if ( start === end )
				range.collapse( true );

			range.moveEnd( 'character', end );
			range.moveStart( 'character', start );
			range.select();
		}
	};

	Caret.prototype.before = function() {
		return this.element.value.substring( 0, this.start );
	};

	Caret.prototype.after = function() {
		return this.element.value.substring( this.end, this.element.value.length );
	};

	Caret.prototype.selected = function() {
		return this.element.value.substring( this.start, this.end );
	};

	/**
	 * Inserts a value at the cursor.
	 */
	Caret.prototype.insert = function( value ) {
		// W3C
		if ( 'undefined' !== typeof this.element.selectionStart ) {
			// Chrome/Safari/Firefox/Opera
			this.element.value = this.before() + value + this.after();
		} else if ( document.selection ) {
			// IE
			this.element.focus();
			document.selection.createRange().text = value;
		}
	};

	/**
	 * Replaces the current word before the cursor with a value.
	 *
	 * @param value     The value to insert.
	 * @param options   Optional.
	 *      before - Default true.
	 *          boolean - Whether to replace the word before the cursor.
	 *          RegExp  - The RegExp to use before the cursor.
	 *      after - Default false.
	 *          boolean - Whether to replace the word after the cursor.
	 *          RegExp  - The RegExp to use after the cursor.
	 */
	Caret.prototype.replace = function( value, options ) {
		var rbefore, rafter, before = this.before(), after = this.after();

		options = options || {};
		rbefore = options.before;
		rafter  = ( options.after === true ) ? rWordStart : options.after;

		if ( 'undefined' === typeof rbefore  || true === rbefore )
			rbefore = rWordEnd;

		if ( rbefore )
			before = before.replace( rbefore, '' );
		if ( rafter )
			after = after.replace( rafter, '' );

		this.element.value = before + value + after;
		this.set( before.length + value.length );
	};

} )();
