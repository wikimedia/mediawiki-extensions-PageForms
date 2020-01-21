/*
 * JavaScript for displaying a popup warning if the user has made changes in
 * the form without saving the page.
 *
 * Heavily based on the file mediawiki.action.edit.editWarning.js in core
 * MediaWiki.
 *
 * @author Yaron Koren
 */
( function () {
	'use strict';

	var changesWereMade = false;

	mw.hook('pf.addTemplateInstance').add( function( $newInstance ) {
		changesWereMade = true;
	});


	$( function () {
		var allowCloseWindow,
			$allInputs = $( 'form#pfForm textarea, form#pfForm input[type=text], #wpSummary' );

		// Check if EditWarning is enabled and if we need it.
		if ( !mw.user.options.get( 'useeditwarning' ) ) {
			return true;
		}

		// Save the original value of the inputs.
		$allInputs.each( function ( index, element ) {
			var $element = $( element );
			$element.data( 'origtext', $element.textSelection( 'getContents' ) );
		});

		allowCloseWindow = mw.confirmCloseWindow( {
			test: function () {
				if ( mw.config.get( 'wgAction' ) === 'submit' ) {
					return false;
				}
				// We use .textSelection, because editors might not have updated the form yet.
				$allInputs.each( function( index, element ) {
					var $element = $( element );
					if ( $element.data( 'origtext' ) !== $element.textSelection( 'getContents' ) ) {
						changesWereMade = true;
						return false;
					}
				});

				return changesWereMade;
			},

			message: mw.msg( 'editwarning-warning' ),
			namespace: 'editwarning'
		} );

		// Add form submission handler
		$( '#pfForm' ).on( 'submit', function () {
			allowCloseWindow.release();
		} );
	} );

}() );
