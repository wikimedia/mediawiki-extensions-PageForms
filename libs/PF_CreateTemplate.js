function toggleCargoInputs() {
	if (jQuery('#use_cargo').prop('checked')) {
		jQuery('#cargo_table_input').show('medium');
		jQuery('label.cargo_field_type').show('medium');
		jQuery('p.allowed_values_input').show('medium');
	} else {
		jQuery('#cargo_table_input').hide('medium');
		jQuery('label.cargo_field_type').hide('medium');
		jQuery('p.allowed_values_input').hide('medium');
	}
}

var fieldNum = 1;
function createTemplateAddField() {
	fieldNum++;
	var newField = jQuery( '#starterField' ).clone().css( 'display', '' ).removeAttr( 'id' );
	var newHTML = newField.html().replace(/starter/g, fieldNum);
	newField.html( newHTML );
	newField.find( ".deleteField" ).click( function () {
		// Remove the encompassing div for this instance.
		jQuery( this ).closest( ".fieldBox" )
			.fadeOut( 'fast', function () {
				jQuery(this).remove();
			} );
	} );
	newField.find( ".isList" ).click( function () {
		jQuery( this ).closest( ".fieldBox" ).find( ".delimiter" ).toggle();
	} );
	var combobox = new pf.select2.combobox();
	combobox.apply( $( newField.find( '.pfComboBox' ) ) );
	jQuery( '#fieldsList' ).append( newField );
}

function validateCreateTemplateForm() {
	var blankTemplateName = ( jQuery( '#template_name' ).val() === '' );
	var blankCargoTableName = ( jQuery( '#use_cargo' ).is(':checked') ||
		jQuery( '#table_name' ).val() === '' );
	if ( blankTemplateName || blankCargoTableName ) {
		scroll( 0, 0 );
		if ( blankTemplateName ) {
			jQuery( '#template_name_p' ).append( ' <span class="error">' + mediaWiki.msg( 'pf_blank_error' ) + '</span>' );
		}
		if ( blankCargoTableName ) {
			jQuery( '#cargo_table_input' ).append( ' <span class="error">' + mediaWiki.msg( 'pf_blank_error' ) + '</span>' );
		}
		return false;
	} else {
		return true;
	}
}

jQuery( document ).ready( function () {
	jQuery( "#use_cargo" ).click( function() {
		toggleCargoInputs();
	} );
	jQuery( ".createTemplateAddField" ).click( function () {
		createTemplateAddField();
	} );
	jQuery( ".deleteField" ).click( function () {
		// Remove the encompassing div for this instance.
		jQuery( this ).closest( ".fieldBox" )
			.fadeOut( 'fast', function () {
				jQuery( this ).remove();
			} );
	} );
	jQuery( ".isList" ).click( function () {
		jQuery( this ).closest( ".fieldBox" ).find( ".delimiter" ).toggle();
	} );
	jQuery( '#createTemplateForm' ).submit( function () {
		return validateCreateTemplateForm();
	} );
} );
