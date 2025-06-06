/**
 * Javascript handler for the checkboxes input type
 *
 * @param $
 * @param mw
 * @author Stephan Gambke
 */

( function ( $, mw ) {

	'use strict';

	// jQuery plugin that will attach a select all/select none switch to all checkboxes in "this" element
	$.fn.appendSelectionSwitches = function () {

		function insertSwitch( switchesWrapper, label, checked  ) {
			// create a link element that will trigger the selection of all checkboxes
			const $link = $( '<a href="#">' + label + '</a>' );

			// will be initialized only when the event is triggered to avoid lag during page loading
			let $checkboxes;

			// attach an event handler
			$link.click( ( event ) => {
				event.preventDefault();

				// store checkboxes during first method call so the DOM is not searched on every click on the link
				$checkboxes = $checkboxes || switchesWrapper.siblings().find( 'input[type="checkbox"]' );

				$checkboxes.prop( 'checked', checked );
			} );

			// wrap the link into a span to simplify styling
			const $switchWrapper = $('<span class="checkboxSwitch">' ).append( $link );

			// insert the complete switch into the DOM
			switchesWrapper.append( $switchWrapper );
		}

		this.each( ( index, element ) => {
			const $switchesWrapper = $( '<span class="checkboxSwitches">' ).prependTo( element );
			insertSwitch( $switchesWrapper, mw.message( 'pf_forminputs_checkboxes_select_all' ).escaped(), true );
			insertSwitch( $switchesWrapper, mw.message( 'pf_forminputs_checkboxes_select_none' ).escaped(), false );
		} );

		return this;
	};

	$( () => {
		$( '.checkboxesSpan.select-all' ).appendSelectionSwitches();
	} );

}( jQuery, mediaWiki ) );
