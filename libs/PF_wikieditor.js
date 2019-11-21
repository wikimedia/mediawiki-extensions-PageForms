// create ext if it does not exist yet
/*global wgWikiEditorEnabledModules*/
if ( window.ext === null || typeof( window.ext ) === "undefined" ) {
	window.ext = {};
}

( function ( $, mw ) {

window.ext.wikieditor = {
	// initialize the wikieditor on the specified element
	init : function init ( inputId, params ) {
		$( function() {
			if ( mw ) {
				var input = $( '#' + inputId );

				if ( mw.config.values.wgVersion < "1.33" ) {
					var toolbarmodules = [ 'jquery.wikiEditor.toolbar', 'jquery.wikiEditor.toolbar.config' ];
					var dialogmodules = [ 'jquery.wikiEditor.dialogs', 'jquery.wikiEditor.dialogs.config' ];
				} else {
					var toolbarmodules = [ 'ext.wikiEditor' ];
					var dialogmodules = [ 'ext.wikiEditor' ];
				}

				// load toolbar
				$.when( mw.loader.using( toolbarmodules ), $.ready ).then( function() {
					if ( typeof $.wikiEditor.isSupported !== 'function' || $.wikiEditor.isSupported( $.wikiEditor.modules.toolbar ) ) {
						input.wikiEditor( 'addModule', $.wikiEditor.modules.toolbar.config.getDefaultConfig() );

						// hide sig if required
						if ( mw.config.get( 'wgWikiEditorEnabledModules' ) && mw.config.get( 'wgWikiEditorEnabledModules.hidesig' ) === true ) {
							input.wikiEditor( 'removeFromToolbar', {
								'section': 'main',
								'group': 'insert',
								'tool': 'signature'
							} );
						}
					}
				} );

				// load dialogs
				$.when( mw.loader.using( dialogmodules ), $.ready ).then( function() {
					if ( typeof $.wikiEditor.isSupported !== 'function' || $.wikiEditor.isSupported( $.wikiEditor.modules.dialogs ) ) {
						$.wikiEditor.modules.dialogs.config.replaceIcons( input );
						input.wikiEditor( 'addModule', $.wikiEditor.modules.dialogs.config.getDefaultConfig() );
					}
				} );
			}
		} );
	}
};
}( jQuery, mediaWiki ) );
