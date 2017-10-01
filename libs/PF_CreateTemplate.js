function toggleCargoInputs() {
	if (jQuery('#use_cargo').prop('checked')) {
		jQuery('#cargo_table_input').show('medium');
		jQuery('label.cargo_field_type').show('medium');
		jQuery('.allowed_values_input').show('medium');
		jQuery('.is_hierarchy').show('medium');
	} else {
		jQuery('#cargo_table_input').hide('medium');
		jQuery('label.cargo_field_type').hide('medium');
		jQuery("input[name*='is_hierarchy_']").prop('checked', false);
		jQuery('.is_hierarchy').hide('medium');
		jQuery('.hierarchy_structure_input').hide('medium');
		jQuery('.allowed_values_input').show('medium');
	}
}

var fieldNum = 1;
var hierarchyPlaceholder =  mediaWiki.msg( 'pf_createtemplate_hierarchystructureplaceholder' );
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
	newField.find( ".is_hierarchy" ).click( function () {
		toggleHierarchyInput(jQuery( this ).closest( ".fieldBox" ));
	} );
	newField.find( ".hierarchy_structure" ).click( function () {
		if (jQuery( this ).attr( 'validInput' ) === undefined || jQuery( this ).attr( 'validInput' ) !== 'true') {
			removeHierarchyPlaceholder( jQuery( this ) );
		}
	} );
	newField.find( ".hierarchy_structure" ).blur( function () {
		setHierarchyPlaceholder( jQuery( this ) );
	} );
	var combobox = new pf.select2.combobox();
	combobox.apply( $( newField.find( '.pfComboBox' ) ) );
	jQuery( '#fieldsList' ).append( newField );
}

function validateCreateTemplateForm() {
	var blankTemplateName = ( jQuery( '#template_name' ).val() === '' );
	var blankCargoTableName = ( jQuery( '#use_cargo' ).is(':checked') &&
		jQuery( '#cargo_table' ).val() === '' );
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

function toggleHierarchyInput(containerElement) {
	if (containerElement.find( "input[name*='is_hierarchy_']" ).prop('checked')) {
		containerElement.find( ".allowed_values_input" ).hide('medium');
		containerElement.find( ".hierarchy_structure_input" ).show('medium');
		if (containerElement.find( "textarea[name*='hierarchy_structure_']" ).val() === "") {
			setHierarchyPlaceholder( containerElement.find( "textarea[name*='hierarchy_structure_']" ) );
		}
	} else {
		containerElement.find( ".hierarchy_structure_input" ).hide('medium');
		containerElement.find( ".allowed_values_input" ).show('medium');
	}
}

function setHierarchyPlaceholder( textareaElement ) {
	if (textareaElement.val() === "") {
		textareaElement.val( hierarchyPlaceholder );
		textareaElement.css( 'color', 'gray' );
		textareaElement.attr( 'validInput', 'false' );
	}
}

function removeHierarchyPlaceholder( textareaElement ) {
	textareaElement.val( '' );
	textareaElement.css( 'color', 'black' );
	textareaElement.attr( 'validInput', 'true' );
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
	jQuery( ".is_hierarchy" ).click( function () {
		toggleHierarchyInput( jQuery( this ).closest( ".fieldBox" ) );
	} );
	jQuery( ".hierarchy_structure" ).click( function () {
		if (jQuery( this ).attr( 'validInput' ) === undefined || jQuery( this ).attr( 'validInput' ) !== 'true') {
			removeHierarchyPlaceholder( jQuery( this ) );
		}
	} );
	jQuery( ".hierarchy_structure" ).blur( function () {
		setHierarchyPlaceholder( jQuery( this ) );
	} );
	jQuery( '#createTemplateForm' ).submit( function () {
		return validateCreateTemplateForm();
	} );
} );
