( function( $, mw ) {

function disableFormAndCategoryInputs() {
	if ($('#template_multiple').attr('aria-checked') == 'true') {
		$('input[name="multiple_template"]').attr('value','1');
		$('#form_name').attr('disabled', 'disabled');
		$('label[for="form_name"]').css('color', 'gray').css('font-style', 'italic');
		$('#category_name').attr('disabled', 'disabled');
		$('label[for="category_name"]').css('color', 'gray').css('font-style', 'italic');
		$('#connecting_property_div').show('fast');
	} else {
		$('input[name="multiple_template"]').attr('value','0');
		$('#form_name').removeAttr('disabled');
		$('label[for="form_name"]').css('color', '').css('font-style', '');
		$('#category_name').removeAttr('disabled');
		$('label[for="category_name"]').css('color', '').css('font-style', '');
		$('#connecting_property_div').hide('fast');
	}
}

var toggleSwitch = new OO.ui.ToggleSwitchWidget( {
	id: 'template_multiple',
	classes: [ 'disableFormAndCategoryInputs' ],
} );
$( '#template_multiple_p' ).prepend( toggleSwitch.$element );
$( '#template_mutliple' ).attr( 'name','template_multiple' );
$( ".disableFormAndCategoryInputs" ).click( function() {
	disableFormAndCategoryInputs();
} );
$( '#createClassForm' ).submit( function() {

	var num_errors = 0;

	// Remove all old error messages.
	$(".errorMessage").remove();
	$("input").removeClass("inputError");

	var num_errors = 0;

	var $templateName = $( "input[name='template_name']" );
	var $form_name = $( '#form_name' );
	var $category = $( '#category_name' );
	var $cargoTableName = $( '#cargo_table' );
	var isMultipleInstanceAllowed = $( "input[name='multiple_template']" ).val();
	var isCargoBased = $( "input[name='use_cargo']" ).val();
	if ( isMultipleInstanceAllowed ) {
		isMultipleInstanceAllowed = parseInt( isMultipleInstanceAllowed );
	}
	if ( isCargoBased ) {
		isCargoBased = parseInt( isCargoBased );
	}
	num_errors += $templateName.validateField();
	if ( isCargoBased ) {
		num_errors += $cargoTableName.validateField();
	}
	if( !isMultipleInstanceAllowed ) {
		num_errors += ( $form_name.validateField() + $category.validateField() );
	}
	if (num_errors > 0) {
		// add error header, if it's not there already
		if ($("#form_error_header").length === 0) {
			$("#contentSub").append('<div id="form_error_header" class="errorbox" style="font-size: medium"><img src="' + mw.config.get( 'wgPageFormsScriptPath' ) + '/skins/MW-Icon-AlertMark.png" />&nbsp;' + mw.message( 'pf_formerrors_header' ).escaped() + '</div><br clear="both" />');
		}
		// Also undo the indicator that the form was submitted.
		$( '#createClassForm' ).data('submitted', false);
		scroll(0, 0);
	} else {
		//remove error box if it exists because there are no errors in the form now
		$("#contentSub").find(".errorbox").remove();
	}

	if (num_errors > 0) {
		// add error header, if it's not there already
		if ($("#form_error_header").length === 0) {
			$("#contentSub").append('<div id="form_error_header" class="errorbox" style="font-size: medium"><img src="' + mw.config.get( 'wgPageFormsScriptPath' ) + '/skins/MW-Icon-AlertMark.png" />&nbsp;' + mw.message( 'pf_formerrors_header' ).escaped() + '</div><br clear="both" />');
		}
		$( '#createClassForm' ).data('submitted', false);
		scroll(0, 0);
	} else {
		//remove error box if it exists because there are no errors in the form now
		$("#contentSub").find(".errorbox").remove();
	}

	return (num_errors === 0);
} );

$.fn.validateField = function() {
	var isEmpty;
	var target = $( this ).val();

	if (target === null) {
		isEmpty = true;
	} else {
		isEmpty = (target.replace(/\s+/, '') === '');
	}
	if (isEmpty) {
		$( this ).addClass('inputError');
		$( this ).parent().append($('<div>').addClass( 'errorMessage' ).text( mw.msg( 'pf_blank_error' ) ));
		return 1;
	} else {
		return 0;
	}
};

}( jQuery, mediaWiki ) );
