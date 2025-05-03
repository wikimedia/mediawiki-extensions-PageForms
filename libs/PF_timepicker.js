/**
 * Javascript code to be used with input type timepicker.
 *
 * @author Stephan Gambke
 */

/**
 * Initializes a timepicker input
 *
 * @param {string} inputID the id of the input to initialize
 * @param {Object} params the parameter object for the timepicker, contains
 *		minTime: (String) the minimum time to be shown (format hh:mm)
 *		maxTime: (String) the maximum time to be shown (format hh:mm)
 *		interval: (String) the interval between selectable times in minutes
 *		format: (String) a format string (unused) (do we even need it?)
 */
window.PF_TP_init = function( inputID, params ) { // minTime, maxTime, interval, format

	const inputIDshow = inputID + '_show';

	const $inputShow = jQuery( '#' + inputID );
	$inputShow.attr( 'id', inputIDshow );

	let $input;

	// set up hidden input if this timepicker is not part of a datetimepicker
	if ( ! params.partOfDTP ) {

		$input = jQuery( '<input type="hidden" >');
		$input.attr( {
			id: inputID,
			name: $inputShow.attr( 'name' ),
			value: $inputShow.val()
		} );
		$input.val($inputShow.val());

		$inputShow.after( $input );
		$inputShow.removeAttr( 'name' );

	} else {
		$input = $inputShow;
	}

	const tabindex = $inputShow.attr('tabindex');

	// append time picker button
	const $button = jQuery( '<button type="button" ></button>' );
	$button.attr({
		'class': params.userClasses,
		'id': inputID + '_button',
		'tabindex': tabindex
	});


	if ( params.disabled ) {

		$button.attr( 'disabled', 'disabled' );

	} else {

		$button.click( () => {
			if ( jQuery( '#' + inputID + '_tree>ul' ).is(':visible') ) {
				$inputShow.blur();
			} else {
				$inputShow.focus();
			}

		} );

	}

	const $img = jQuery( '<img>' );
	$img.attr( 'src', params.buttonImage );

	$button.append( $img );

	$input.after( $button );

	// sanitize inputs
	const re = /^\d+:\d\d$/;
	let minh = 0;
	let minm = 0;

	let maxh = 23;
	let maxm = 59;

	if ( re.test( params.minTime ) ) {

		const min = params.minTime.split( ':', 2 );
		minh = Number( min[0] );
		minm = Number( min[1] );

		if ( minm > 59 ) {
			minm = 59;
		}
	}

	if ( re.test( params.maxTime ) ) {

		const max = params.maxTime.split( ':', 2 );
		maxh = Number( max[0] );
		maxm = Number( max[1] );

		if ( maxm > 59 ) {
			maxm = 59;
		}
	}

	let interv = Number( params.interval );

	if ( interv < 1 ) {
		interv = 1;
	} else if ( interv > 60 ) {
		interv = 60;
	}

	// build html structure
	const $sp = jQuery( '<span class="PF_timepicker" id="' + inputID + '_tree" ></span>' ).insertBefore( $inputShow );

	const $ulh = jQuery( '<ul class="PF_timepicker_hours" >' ).appendTo( $sp );


	for ( let h = minh; h <= maxh; ++h ) {

		const $lih = jQuery( '<li class="ui-state-default PF_timepicker_hour">' + ( ( h < 10 ) ? '0' : '' ) + h + '</li>' ).appendTo( $ulh );

		//TODO: Replace value for "show" by formatted string
		$lih
		.data( 'value', ( ( h < 10 ) ? '0' : '' ) + h + ':00' )
		.data( 'show', ( ( h < 10 ) ? '0' : '' ) + h + ':00' );

		const $ulm = jQuery( '<ul class="PF_timepicker_minutes" >' ).appendTo( $lih );

		for ( let m = ( (h === minh) ? minm : 0 ) ; m <= ( (h === maxh) ? maxm : 59 ); m += interv ) {

			const $lim = jQuery( '<li class="ui-state-default PF_timepicker_minute">' + ( ( m < 10 ) ? '0' : '' ) + m  + '</li>' ).appendTo( $ulm );

			//TODO: Replace value for "show" by formatted string
			$lim
			.data( 'value', ( ( h < 10 ) ? '0' : '' ) + h + ':' + ( ( m < 10 ) ? '0' : "" ) + m )
			.data( 'show', ( ( h < 10 ) ? '0' : '' ) + h + ':' + ( ( m < 10 ) ? '0' : "" ) + m );

		}

	}

	// initially hide everything
	jQuery( '#' + inputID + '_tree ul' )
	.hide();

	// attach event handlers
	jQuery( '#' + inputID + '_tree li' ) // hours
	.mouseover((evt) => {

		// clear any timeout that may still run on the last list item
		clearTimeout( jQuery( evt.currentTarget ).data( 'timeout' ) );

		jQuery( evt.currentTarget )

		// switch classes to change display style
		.removeClass( 'ui-state-default' )
		.addClass( 'ui-state-hover' )

		// set timeout to show minutes for selected hour
		.data( 'timeout', setTimeout(
			() => {
				jQuery( evt.currentTarget ).children().fadeIn();
			}, 400 ) );

	})

	.mouseout((evt) => {

		// clear any timeout that may still run on this jQuery list item
		clearTimeout( jQuery( evt.currentTarget ).data( 'timeout' ) );

		jQuery( evt.currentTarget )

		// switch classes to change display style
		.removeClass( 'ui-state-hover' )
		.addClass( 'ui-state-default' )

		// hide minutes after a short pause
		.data( 'timeout', setTimeout(
			() => {
				jQuery(evt.currentTarget).children().fadeOut();
			}, 400 ) );

	});

	jQuery( '#' + inputID + '_tree li' ) // hours, minutes
	.mousedown(function(evt){

		// set values and leave input
		$inputShow
		// Are both these calls necessary? At least the 2nd one is.
		.attr( 'value', jQuery( this ).data( 'show' ) )
		.val(jQuery( this ).data( 'show' ) )
		.blur()
		.change();

		// clear any timeout that may still run on this jQuery list item
		clearTimeout( jQuery( evt.currentTarget ).data( 'timeout' ) );

		jQuery( evt.currentTarget )

		// switch classes to change display style
		.removeClass( 'ui-state-hover' )
		.addClass( 'ui-state-default' );

		// avoid propagation to parent list item (e.g. hours),
		// they would overwrite the input value
		return false;
	});

	// show timepicker when input gets focus
	$inputShow
	.focus(() => {
		jQuery( '#' + inputID + '_tree>ul' ).fadeIn();
	});

	// hide timepicker when input loses focus
	$inputShow
	.blur(() => {
		jQuery( '#' + inputID + '_tree ul' ).fadeOut( 'normal', function() {
			jQuery(this).hide();
		});
	});

	if ( ! params.partOfDTP ) {
		$inputShow
		.change(function() {
			jQuery( '#' + inputID ).val( jQuery(this).val() );
		});
	}

	jQuery( '#' + inputID + '_show ~ button[name="button"]' )
	.click( () => {
		$inputShow.focus();
	});

};
