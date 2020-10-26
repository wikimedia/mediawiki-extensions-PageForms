/**
 * JavaScript code to be used with input type datepicker.
 *
 * @author Sam Wilson
 * @author Yaron Koren
 */

( function( $, oo, mw, pf ) {
	'use strict';

	jQuery.fn.applyDatePicker = function() {
		return this.each(function() {
			oo.ui.infuse( this );
		});
	};

} )( jQuery, OO, mediaWiki, pf )
