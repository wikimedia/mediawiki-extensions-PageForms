/**
 * @author Yaron Koren
 *
 * JavaScript for Page Forms' Special:UploadForm.
 *
 * This is a major @HACK. This code is copied from MediaWiki's file
 * mediawiki.special.upload/upload.js, which used to work directly, but no
 * longer does, due to a change in either the FancyBox JS library or
 * something in core MediaWiki. I haven't been able to get JS code defined
 * outside the popup window to either directly modify, or interact with JS
 * inside, the popup window; so instead we have to define our own set of JS,
 * including loading jQuery inside the window. The main upload.js file does
 * a number of things; this file does only one thing, which is to set the
 * "Destination filename" input when a file is specified. Still, that's the
 * most important thing.
 *
 * @todo - figure out how to get the real upload JS code to work within the
 * window, in one way or another.
 */

/* global Uint8Array */

//( function ( $, jQuery, mw ) {
	var uploadWarning, $license = $( '#wpLicense' );

	$( function () {
		// fillDestFile setup
		//mw.config.get( 'wgUploadSourceIds' ).forEach( function ( sourceId ) {
		var sourceIDs = [ 'wpUploadFile' ];
		sourceIDs.forEach( function ( sourceId ) {
			$( '#' + sourceId ).on( 'change', function () {
				var path, slash, backslash, fname;
				// Remove any previously flagged errors
				$( '#mw-upload-permitted, #mw-upload-prohibited' ).removeClass();

				path = $( this ).val();
				// Find trailing part
				slash = path.lastIndexOf( '/' );
				backslash = path.lastIndexOf( '\\' );
				if ( slash === -1 && backslash === -1 ) {
					fname = path;
				} else if ( slash > backslash ) {
					fname = path.slice( slash + 1 );
				} else {
					fname = path.slice( backslash + 1 );
				}

				// Replace spaces by underscores
				fname = fname.replace( / /g, '_' );
				// Capitalise first letter if needed
//				if ( mw.config.get( 'wgCapitalizeUploads' ) ) {
					fname = fname[ 0 ].toUpperCase() + fname.slice( 1 );
//				}

				// Output result
				if ( $( '#wpDestFile' ).length ) {
					// Call decodeURIComponent function to remove possible URL-encoded characters
					// from the file name (T32390). Especially likely with upload-form-url.
					// decodeURIComponent can throw an exception if input is invalid utf-8
					try {
						$( '#wpDestFile' ).val( decodeURIComponent( fname ) );
					} catch ( err ) {
						$( '#wpDestFile' ).val( fname );
					}
					//uploadWarning.checkNow( fname );
				}
			} );
		} );
	} );

//}( jQuery, mediaWiki ) );
