// create ext if it does not exist yet
if ( typeof( window.ext ) === "undefined" ) {
	window.ext = {};
}

window.ext.wikieditor = new function(){

	// initialize the wikieditor on the specified element
	this.init = function init ( input_id, params ) {

		if ( window.mediaWiki ) {
			mediaWiki.loader.using(	'ext.semanticforms.wikieditor', function(){

				var input = jQuery( '#' + input_id );

				// load toolbar
				mediaWiki.loader.using( ['jquery.wikiEditor.toolbar', 'jquery.wikiEditor.toolbar.config'] , function(){
					if ( jQuery.wikiEditor.isSupported( jQuery.wikiEditor.modules.toolbar ) ) {

						input.wikiEditor( 'addModule', jQuery.wikiEditor.modules.toolbar.config.getDefaultConfig() );

						// hide sig if required
						if ( wgWikiEditorEnabledModules && wgWikiEditorEnabledModules['hidesig'] == true ) {
							input.wikiEditor( 'removeFromToolbar', {
								'section': 'main',
								'group': 'insert',
								'tool': 'signature'
							} );
						}

					}
				});

				// load dialogs
				mediaWiki.loader.using( ['jquery.wikiEditor.dialogs', 'jquery.wikiEditor.dialogs.config'] , function(){
					if ( jQuery.wikiEditor.isSupported( jQuery.wikiEditor.modules.dialogs ) ) {

						jQuery.wikiEditor.modules.dialogs.config.replaceIcons( input );
						input.wikiEditor( 'addModule', $.wikiEditor.modules.dialogs.config.getDefaultConfig() );

					}
				});

				// load toc
				// TODO: Can this be enabled? Should it?
//				mediaWiki.loader.using( ['jquery.wikiEditor.toc' ] , function(){
//					if ( jQuery.wikiEditor.isSupported( jQuery.wikiEditor.modules.toc ) ) {
//
//						input.wikiEditor( 'addModule', 'toc' );
//
//					}
//				});



			} );
		}
	}

};
