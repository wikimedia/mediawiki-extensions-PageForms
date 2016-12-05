/**
 * JavaScript code to be used with input type datetimepicker.
 *
 * @author Stephan Gambke
 *
 */
/*jshint sub:true*/

window.PF_DTP_init = function( inputId, params ) {

	var input = jQuery( '#' + inputId );

	var tabindex = input.attr('tabindex');

	var hiddenInput = jQuery( '<input type="hidden" >' );

	hiddenInput.attr( {
		id: inputId,
		name: input.attr( 'name' ),
		value: input.val()
	} );

	input.replaceWith( hiddenInput );
	input = hiddenInput;

	// create and insert subinput elements
	var subinputs = jQuery( params.subinputs );
	input.before( subinputs );

	// call initialisation functions for subinputs
	for (var subinputId in params.subinputsInitData) {
		if ( params.subinputsInitData[subinputId] ) {
			for ( var index in params.subinputsInitData[subinputId] ) {
				if( params.subinputsInitData[subinputId][index] ) {
					var fn = window[ params.subinputsInitData[subinputId][index]['name'] ];
					var param = params.subinputsInitData[subinputId][index]['param'];

					if ( typeof fn === 'function' )	{
						fn( subinputId, param );
					}
				}
			}
		}
	}

	var dp = jQuery( '#' + inputId + '_dp_show' ); // datepicker element
	var tp = jQuery( '#' + inputId + '_tp_show' ); // timepicker element

	dp.add(tp)
	.change (function(){

		var date;

		// try parsing the date value
		try {

			date = jQuery.datepicker.parseDate( dp.datepicker( 'option', 'dateFormat' ), dp.val(), null );
			date = jQuery.datepicker.formatDate( dp.datepicker( 'option', 'altFormat' ), date );

		} catch ( e ) {
			// value does not conform to specified format
			// just return the value as is
			date = dp.val();
		}

		input.val( jQuery.trim( date + ' ' + tp.val() ) );

	});

};