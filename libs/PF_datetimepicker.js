/**
 * JavaScript code to be used with input type datetimepicker.
 *
 * @param $
 * @param oo
 * @param mw
 * @param pf
 * @author Sam Wilson
 * @author Yaron Koren
 */

( function( $, oo, mw, pf ) {
	'use strict';

	const localeOptions = {
		timeZoneName: 'long',
		year: 'numeric',
		month: 'long',
		day: 'numeric',
		hour: 'numeric',
		minute: 'numeric'
	};

	jQuery.fn.applyDateTimePicker = function() {
		return this.each( function() {
			const widget = oo.ui.infuse( this );
			const $localDatetimeLabel = $( '<label>' );
			const $localDatetime = $( '<strong>' );
			// Add the label even when there isn't going to be a displayed date, to make sure it takes up vertical
			// space and avoid the form layout shifting when a date is selected.
			$localDatetimeLabel.append( mw.message( 'pf-datetimepicker-localtime' ).escaped(), ' ', $localDatetime );
			widget.$element
				.next( '.pf-datetimepicker-help' )
				.append( '<br>', $localDatetimeLabel );
			widget.connect( this, { change: function ( newDatetimeVal ) {
				if ( newDatetimeVal === '' || newDatetimeVal === undefined ) {
					$localDatetime.text( '' );
					return;
				}
				const date = new Date( Date.parse( newDatetimeVal ) );
				const localDatetime = date.toLocaleString( [], localeOptions );
				$localDatetime.text( localDatetime );
			} } );
			// Also fire the change handler once when instantiating, to operate on the default value.
			widget.emit( 'change', widget.getValue() );
		} );
	};

} )( jQuery, OO, mediaWiki, pf )
