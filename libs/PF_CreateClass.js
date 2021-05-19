
function disableFormAndCategoryInputs() {
	if (jQuery('#template_multiple').attr('aria-checked') == 'true') {
		jQuery('input[name="multiple_template"]').attr('value','1');
		jQuery('#form_name').attr('disabled', 'disabled');
		jQuery('label[for="form_name"]').css('color', 'gray').css('font-style', 'italic');
		jQuery('#category_name').attr('disabled', 'disabled');
		jQuery('label[for="category_name"]').css('color', 'gray').css('font-style', 'italic');
		jQuery('#connecting_property_div').show('fast');
	} else {
		jQuery('input[name="multiple_template"]').attr('value','0');
		jQuery('#form_name').removeAttr('disabled');
		jQuery('label[for="form_name"]').css('color', '').css('font-style', '');
		jQuery('#category_name').removeAttr('disabled');
		jQuery('label[for="category_name"]').css('color', '').css('font-style', '');
		jQuery('#connecting_property_div').hide('fast');
	}
}

jQuery( document ).ready( function () {
	var toggleSwitch = new OO.ui.ToggleSwitchWidget( {
		id: 'template_multiple',
		classes: [ 'disableFormAndCategoryInputs' ],
	} );
	jQuery( '#template_multiple_p' ).prepend( toggleSwitch.$element );
	jQuery( '#template_mutliple' ).attr( 'name','template_multiple' );
	jQuery( ".disableFormAndCategoryInputs" ).click( function () {
		disableFormAndCategoryInputs();
	} );
} );
