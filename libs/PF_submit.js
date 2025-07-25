/**
 * Javascript handler for the save-and-continue button
 *
 * @author Stephan Gambke
 */

/*global validateAll */

( function( $, mw ) {

	'use strict';

	let $sacButtons;
	let $form;

	function setChanged( event ) {
		$sacButtons
			.addClass( 'pf-save_and_continue-changed' )
			.addClass( 'oo-ui-widget-enabled' )
			.removeClass( 'oo-ui-widget-disabled' );
		$sacButtons.children('button').prop( 'disabled', false );

		return true;
	}

	// Prevent multiple submission of form
	jQuery.fn.preventDoubleSubmission = function() {
		$form = $( '#pfForm' );
		$( this ).on( 'submit', (e) => {
			if ( $form.data('submitted') === true ) {
				// Previously submitted - don't submit again
				e.preventDefault();
			} else {
				// Mark it so that the next submit can be ignored
				$form.data('submitted', true);
				$( '.editButtons > .oo-ui-buttonElement' ).removeClass( 'oo-ui-widget-enabled' ).addClass( 'oo-ui-widget-disabled' );
			}
		});
		// Keep chainability
		return this;
	};
	$( '#pfForm' ).preventDoubleSubmission();
	/**
	 * Called when the server has sent the preview
	 *
	 * @param {Mixed} result
	 * @param {Mixed} textStatus
	 * @param {Mixed} jqXHR
	 */
	const resultReceivedHandler = function handleResultReceived( result, textStatus, jqXHR ) {
		// Store the target name
		let $target = $form.find( 'input[name="target"]' );

		if ( $target.length === 0 ) {
			$target = $( '<input type="hidden" name="target">' );
			$form.append ( $target );
		}

		$target.attr( 'value', result.$target );

		// Store the form name
		$target = $form.find( 'input[name="form"]' );

		if ( $target.length === 0 ) {
			$target = $( '<input type="hidden" name="form">' );
			$form.append ( $target );
		}

		$target.attr( 'value', result.form.title );

		$sacButtons
		.addClass( 'pf-save_and_continue-ok' )
		.removeClass( 'pf-save_and_continue-wait' )
		.removeClass( 'pf-save_and_continue-error' );

	};

	const resultReceivedErrorHandler = function handleError( jqXHR ){
		const errors = $.parseJSON( jqXHR.responseText ).errors;

		$sacButtons
		.addClass( 'pf-save_and_continue-error' )
		.removeClass( 'pf-save_and_continue-wait' );

		// Remove all old error messages and set new ones
		$( '.errorbox' ).remove();


		if ( errors.length > 0 ){
			let i;
			for ( i = 0; i < errors.length; i += 1 ) {
				if ( errors[i].level < 2 ) { // show errors and warnings
					$( '#contentSub' )
					.append( '<div id="form_error_header" class="errorbox" style="font-size: medium">' +
						'<img src="' + mw.config.get( 'wgPageFormsScriptPath' ) + '/skins/MW-Icon-AlertMark.png" />' +
						'&nbsp;' + errors[i].message + '</div><br clear="both" />' );
				}
			}

			$( 'html, body' ).scrollTop( $( '#contentSub' ).offset().top );
		}
	};

	function collectData() {
		const $summaryfield = jQuery( '#wpSummary', $form );
		const saveAndContinueSummary = mw.msg( 'pf_formedit_saveandcontinue_summary', mw.msg( 'pf_formedit_saveandcontinueediting' ) );
		let params;

		if ( $summaryfield.length > 0 ) {

			const oldsummary = $summaryfield.attr( 'value' );

			if ( oldsummary !== '' ) {
				$summaryfield.attr( 'value', oldsummary + ' (' + saveAndContinueSummary + ')' );
			} else {
				$summaryfield.attr( 'value', saveAndContinueSummary );
			}

			params = $form.serialize();

			$summaryfield.attr( 'value', oldsummary );
		} else {
			params = $form.serialize();
			params += '&wpSummary=' + saveAndContinueSummary;
		}

		if ( mw.config.get( 'wgAction' ) === 'formedit' ) {
			params += '&target=' + encodeURIComponent( mw.config.get( 'wgPageName' ) );
		} else if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'FormEdit' ) {
			const url = mw.config.get( 'wgPageName' );

			let start = url.indexOf( '/' ) + 1; // find start of subpage
			let stop;

			if ( start >= 0 ) {
				stop = url.indexOf( '/', start ); // find end of first subpage
			} else {
				stop = -1;
			}

			if ( stop >= 0 ) {
				params += '&form=' + encodeURIComponent( url.substring( start, stop ) );

				start = stop + 1;
				params += '&target=' + encodeURIComponent( url.slice( start ) );

			} else {
				params += '&form=' + encodeURIComponent( url.slice( start ) );
			}
		}

		params += '&wpMinoredit=1';

		return params;
	}

	function handleSaveAndContinue( event ) {

		event.stopImmediatePropagation();

		// remove old error messages
		const el = document.getElementById( 'form_error_header' );

		if ( el ) {
			el.parentNode.removeChild( el );
		}

		if ( validateAll() ) {
			// disable save and continue button
			$sacButtons
			.attr( 'disabled', 'disabled' )
			.addClass( 'pf-save_and_continue-wait' )
			.removeClass( 'pf-save_and_continue-changed' );

			const $form = $( '#pfForm' );

			const data = {
				action: 'pfautoedit',
				format: 'json',
				query: collectData() // add form values to the data
			};

			data.query += '&wpSave=' + encodeURIComponent( $( event.currentTarget ).attr( 'value' ) );

			$.ajax( {
				type: 'POST', // request type ( GET or POST )
				url: mw.util.wikiScript( 'api' ), // URL to which the request is sent
				data: data, // data to be sent to the server
				dataType: 'json', // type of data expected back from the server
				success: resultReceivedHandler, // function to be called if the request succeeds
				error: resultReceivedErrorHandler // function to be called on error
			} );

		}

		return false;
	}

	mw.pageFormsActivateVEFields = function( callback ) {
		const visualEditors = $.fn.getVEInstances();
		if ( visualEditors.length > 0 ) {
			const savingQueue = [];
			$(visualEditors).each( ( i, ve ) => {
				savingQueue.push( ve.target.updateContent() );
			});
			$.when.apply( $, savingQueue ).then( () => {
				callback();
			});
		} else {
			callback();
		}
	};

	if ( mw.config.get( 'wgAction' ) === 'formedit' || mw.config.get( 'wgCanonicalSpecialPageName' ) === 'FormEdit' ) {
		$(() => { // Wait until DOM is loaded.
			$form = $( '#pfForm' );
			$sacButtons = $( '.pf-save_and_continue', $form );
			$sacButtons.click( handleSaveAndContinue );

			$form
			.on( 'keyup', 'input,select,textarea', ( event ) => {
				if ( event.which < 32 ){
					return true;
				}

				return setChanged( event );
			} )
			.on( 'change', 'input,select,textarea', setChanged )
			.on( 'click', '.multipleTemplateAdder', setChanged )
			.on( 'mousedown', '.instanceRearranger,.removeButton', setChanged );

			// Run only when VEForAll extension is present
			$( document ).on( 'VEForAllLoaded', () => {
				// Special submit form & other actions handling when VEForAll editor is present
				if ( $('.visualeditor').length > 0 ) {
					// Interrupt "Save page", "Show preview" and "Show changes" actions
					const $formButtons = $( '#wpSave, #wpPreview, #wpDiff' );
					let canSubmit = false;

					if ( $formButtons.length > 0 ) {
						$formButtons.each( ( i, button ) => {
							$( button ).on( 'click', ( event ) => {
								if ( !canSubmit ) {
									event.preventDefault();
									mw.pageFormsActivateVEFields( () => {
										if ( validateAll() ) {
											canSubmit = true;
											$( button ).find("[type='submit']").click();
										}
									} );
								}
							} );
						} );
					}
					// Interrupt "Save and continue" action
					$sacButtons.off('click', handleSaveAndContinue).click( ( event ) => {
						mw.pageFormsActivateVEFields( () => {
							handleSaveAndContinue( event );
						});
					});
				}
			});
		});
	}

}( jQuery, mediaWiki ) );
