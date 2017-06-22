var rowNum = mediaWiki.config.get( '$numStartingRows');
function createClassAddRow() {
	rowNum++;
	var newRow = jQuery('#starterRow').clone().css('display', '');
	var newHTML = newRow.html().replace(/starter/g, rowNum);
	newRow.html(newHTML);
	jQuery('#mainTable').append(newRow);
}

function disableFormAndCategoryInputs() {
	if (jQuery('#template_multiple').prop('checked')) {
		jQuery('#form_name').attr('disabled', 'disabled');
		jQuery('label[for="form_name"]').css('color', 'gray').css('font-style', 'italic');
		jQuery('#category_name').attr('disabled', 'disabled');
		jQuery('label[for="category_name"]').css('color', 'gray').css('font-style', 'italic');
		jQuery('#connecting_property_div').show('fast');
	} else {
		jQuery('#form_name').removeAttr('disabled');
		jQuery('label[for="form_name"]').css('color', '').css('font-style', '');
		jQuery('#category_name').removeAttr('disabled');
		jQuery('label[for="category_name"]').css('color', '').css('font-style', '');
		jQuery('#connecting_property_div').hide('fast');
	}
}

function toggleCargoInputs() {
	if (jQuery('#use_cargo').prop('checked')) {
		jQuery('#cargo_table_input').show('medium');
		$('td:nth-child(4),th:nth-child(3)').show('medium');
	} else {
		jQuery('#cargo_table_input').hide('medium');
		$('td:nth-child(4),th:nth-child(3)').hide('medium');
	}
}

jQuery( document ).ready( function () {
	jQuery( ".disableFormAndCategoryInputs" ).click( function () {
		disableFormAndCategoryInputs();
	} );
	jQuery( "#use_cargo" ).click( function() {
		toggleCargoInputs();
	} );
	jQuery( ".createClassAddRow" ).click( function () {
		createClassAddRow();
	} );
} );
