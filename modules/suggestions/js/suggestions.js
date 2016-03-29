/* global jetpackMentionsData, xpostData */
var o2 = o2 || {};

o2.Suggestions = ( function() {
  return function( $editor ) {
    if ( 'undefined' !== typeof jetpackMentionsData && jetpackMentionsData.length ) {
      $editor.mentions( jetpackMentionsData );
    }
    if ( 'undefined' !== typeof xpostData && xpostData.length ) {
      $editor.xposts( xpostData );
    }
    $editor.hashtags();
  };
} )();

jQuery( document ).on( 'mentionsData.jetpack', function( event, mentions ) {
  // Global event, triggered when we first get data back from the suggest endpoint
  // Wire up any currently visible editors with mentions
  jQuery( '.o2-editor-text' ).mentions( mentions.data );
}).on( 'post-editor-create.o2', function( event, $editor ) {
  // When new o2 editors are created, wire them up as well
  o2.Suggestions( $editor );
});
