// create ext if it does not exist yet
/*global wgWikiEditorEnabledModules*/
if ( window.ext === null || typeof( window.ext ) === "undefined" ) {
	window.ext = {};
}

( function( $, mw ) {

window.ext.wikieditor = {
	// initialize the wikieditor on the specified element
	init : function init ( inputId, params ) {
		$( () => {
			if ( mw ) {
				const $input = $( '#' + inputId );

				// mw.addWikiEditor() is defined by ext.wikiEditor itself,
				// so it must be checked only after the module has loaded.
				mw.loader.using( [ 'ext.wikiEditor' ], () => {
					if ( typeof mw.addWikiEditor === 'function' &&
						!$input.closest( '.wikiEditor-ui' ).length ) {
						mw.addWikiEditor( $input );
					}
				} );
			}
		} );
	}
};
}( jQuery, mediaWiki ) );
