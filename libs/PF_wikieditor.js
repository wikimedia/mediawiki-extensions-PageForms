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
				var $input = $( '#' + inputId );

				// The code below this "if" clause does not
				// work for MW 1.34 and higher. Therefore, this
				// alternative approach is needed. However, it
				// requires the presence of an addWikiEditor()
				// function, which, at the time of this writing
				// (January 2021) had not yet been added to
				// WikiEditor. Anyone who wants this code to run
				// may thus need to patch the WikiEditor code
				// themselves, with the following:
				// https://github.com/Nikerabbit/mediawiki-extensions-WikiEditor/commit/9a1188d0850418d8ae64bd06b7f39d9a8cbf127f
				if ( typeof( mw.addWikiEditor ) == 'function' ) {
					mw.loader.using( [ 'ext.wikiEditor' ], function () {
						mw.addWikiEditor( $input );
					} );
					return;
				}
				var toolbarmodules,
					dialogmodules;
				if ( mw.config.values.wgVersion < "1.33" ) {
					toolbarmodules = [ 'jquery.wikiEditor.toolbar', 'jquery.wikiEditor.toolbar.config' ];
					dialogmodules = [ 'jquery.wikiEditor.dialogs', 'jquery.wikiEditor.dialogs.config' ];
				} else {
					toolbarmodules = [ 'ext.wikiEditor' ];
					dialogmodules = [ 'ext.wikiEditor' ];
				}

				// load toolbar
				$.when( mw.loader.using( toolbarmodules ), $.ready ).then( function() {
					if ( typeof $.wikiEditor.isSupported !== 'function' || $.wikiEditor.isSupported( $.wikiEditor.modules.toolbar ) ) {
						$input.wikiEditor( 'addModule', $.wikiEditor.modules.toolbar.config.getDefaultConfig() );

						// hide sig if required
						if ( mw.config.get( 'wgWikiEditorEnabledModules' ) && mw.config.get( 'wgWikiEditorEnabledModules.hidesig' ) === true ) {
							$input.wikiEditor( 'removeFromToolbar', {
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
						$.wikiEditor.modules.dialogs.config.replaceIcons( $input );
						$input.wikiEditor( 'addModule', $.wikiEditor.modules.dialogs.config.getDefaultConfig() );
					}
				} );
			}
		} );
	}
};
}( jQuery, mediaWiki ) );
