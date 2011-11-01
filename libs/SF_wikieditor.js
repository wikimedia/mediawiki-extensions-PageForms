// create ext if it does not exist yet
if ( typeof( window[ 'ext' ] ) == "undefined" ) {
	window[ 'ext' ] = {};
}

window.ext.wikieditor = new function(){
	
//	var config;
//	var isSetUp = false;
//	
//	// common setup for all editor instances
//	function setup () {
//		config = jQuery.wikiEditor.modules.toolbar.config.getDefaultConfig();
//		config.toolbar.advanced.groups.insert.tools.table.filters = ['textarea:not(#wpTextbox1):not(.toolbar-dialogs)'];
//	}

	// initialize the wikieditor on the specified element
	this.init = function init  ( input_id, params ) {
		
//		if ( !isSetUp ) {
//			isSetUp = true;
//			setup();
//		}
		
		jQuery( document ).ready( function() {
			if ( jQuery.wikiEditor.isSupported( jQuery.wikiEditor.modules.toolbar ) ) {
				jQuery( '#' + input_id ).wikiEditor( 'addModule', jQuery.wikiEditor.modules.toolbar.config.getDefaultConfig() );
			}
		});
	}

};
