const Booklet = require( './Booklet.js' );

const windowManager = OO.ui.getWindowManager();
const uploadDialog = new mw.Upload.Dialog( {
	classes: [ 'ext-pageforms-uploadable-dialog' ],
	bookletClass: Booklet
} );
windowManager.addWindows( [ uploadDialog ] );

const handleClick = ( e ) => {
	e.preventDefault();
	windowManager.openWindow( uploadDialog );
	const uploadLink = e.target;
	uploadDialog.uploadBooklet.setDefaultFilename( uploadLink.dataset.pageformsDefaultfilename );
	uploadDialog.uploadBooklet.connect ( this, {
		fileSaved: ( imageInfo ) => {
			const filename = new mw.Title ( imageInfo.canonicaltitle ).getMainText ();
			uploadDialog.close ();
			const $input = $ ( '#' + uploadLink.dataset.inputId );
			if ( $input[0] instanceof HTMLInputElement ) {
				// input type = text, text with autocomplete, and combobox
				$input.val ( filename );
			} else if ( $input[0] instanceof HTMLSelectElement ) {
				// input type = tokens
				$input.append ( new Option ( filename, filename, false, true ) );
				( new pf.select2.tokens () ).apply ( $input );
			}
		}
	} );
};

const addClickHandlers = ( $content ) => {
	$content.find( '.ext-pageforms-uploadable' ).on ( 'click', handleClick );
};

mw.hook( 'wikipage.content' ).add( addClickHandlers );
mw.hook('pf.addTemplateInstance').add( addClickHandlers );
