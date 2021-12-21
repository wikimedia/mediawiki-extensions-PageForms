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
		var allowCloseWindow, origText, newText, origValues = {},
			$allInputs = $( 'form#pfForm textarea, form#pfForm input[type=text], form#pfForm input:not([type]), form#pfForm select, #wpSummary' );

		// Check if EditWarning is enabled and if we need it.
		if ( !mw.user.options.get( 'useeditwarning' ) ) {
			return true;
		}

		// Save the original value of the inputs.
		$allInputs.each( function ( index, element ) {
			var $element = $( element );
			if ( $element.hasClass( 'pfComboBox' ) ) {
				// data() can't be used for combobox inputs, probably because they use OOUI.
				origValues[element.id] = $element.textSelection( 'getContents' );
			} else {
				$element.data( 'origtext', $element.textSelection( 'getContents' ) );
			}
		});

		allowCloseWindow = mw.confirmCloseWindow( {
			test: function () {
				// Don't show a warning if the form is being
				// submitted.
				if ( mw.config.get( 'wgAction' ) === 'submit' ) {
					return false;
				}
				// Don't show a warning if we're in Special:RunQuery,
				// or a page where Special:RunQuery is embedded.
				if ( mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'FormEdit' &&
					mw.config.get( 'wgAction' ) !== 'formedit' ) {
					return false;
				}
				// We use .textSelection, because editors might not have updated the form yet.
				$allInputs.each( function( index, element ) {
					var $element = $( element );

					// The setting of both origText and
					// newText have to be different for the
					// combobox input, due to its use of
					// OOUI.
					if ( $element.hasClass( 'pfComboBox' ) ) {
						origText = origValues[element.id];
						newText = $('#' + element.id).val();
					} else {
						origText = $element.data( 'origtext' );
						// For some reason, the addition of a blank string is sometimes
						// necessary, to get the type right.
						newText = $element.textSelection( 'getContents' ) + '';
					}
					if ( origText != newText ) {
						changesWereMade = true;
						return false;
					}
				});

				return changesWereMade;
			},

			namespace: 'editwarning'
		} );

		// Add form submission handler
		$( '#pfForm' ).on( 'submit', function () {
			allowCloseWindow.release();
		} );
	} );

}() );
