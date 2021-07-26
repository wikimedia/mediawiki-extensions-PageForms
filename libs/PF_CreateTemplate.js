
var fieldNum = 1;
var hierarchyPlaceholder =  mediaWiki.msg( 'pf_createtemplate_hierarchystructureplaceholder' );

function toggleCargoInputs() {
	if (jQuery('#use_cargo_toggle').attr('aria-checked') == 'true') {
		jQuery('input[name="use_cargo"]').attr('value', '1');
		jQuery('#cargo_table_input').show('medium');
		jQuery('label.cargo_field_type').show('medium');
		jQuery('div.pfFieldTypeDropdown').show('medium');
		jQuery('.allowed_values_input').show('medium');
		jQuery('.is_hierarchy').parent().show('medium');
	} else {
		jQuery('input[name="use_cargo"]').attr('value', '0');
		jQuery('#cargo_table_input').hide('medium');
		jQuery('label.cargo_field_type').hide('medium');
		jQuery('div.pfFieldTypeDropdown').hide('medium');
		jQuery("input[name*='is_hierarchy_']").prop('checked', false);
		jQuery('.is_hierarchy').parent().hide('medium');
		jQuery('.hierarchy_structure_input').hide('medium');
		jQuery('div.hierarchy_structure').hide('medium');
		jQuery('.allowed_values_input').show('medium');
	}
}

jQuery.fn.createTemplateAddField = function( addAboveCurInstance ) {
	fieldNum++;
	var $newField = jQuery( '#starterField' ).clone().css( 'display', '' ).removeAttr( 'id' );
	var newHTML = $newField.html().replace(/starter/g, fieldNum);
	$newField.html( newHTML );
	$newField.find( ".removeButton" ).click( function () {
		// Remove the encompassing div for this instance.
		$( this ).closest( ".fieldBox" )
			.fadeOut( 'fast', function () {
				jQuery(this).remove();
			} );
	} );
	$newField.find( ".addAboveButton" ).click( function() {
		$( this ).createTemplateAddField( true );
	} );
	$newField.find( ".isList" ).click( function () {
		$( this ).closest( ".fieldBox" ).find( ".delimiter" ).toggle();
	} );
	$newField.find( ".is_hierarchy" ).click( function () {
		toggleHierarchyInput($( this ).closest( ".fieldBox" ));
	} );
	$newField.find( ".hierarchy_structure" ).click( function () {
		if ($( this ).attr( 'validInput' ) === undefined || $( this ).attr( 'validInput' ) !== 'true') {
			removeHierarchyPlaceholder( jQuery( this ) );
		}
	} );
	$newField.find( ".hierarchy_structure" ).blur( function () {
		setHierarchyPlaceholder( $( this ) );
	} );
	var combobox = new pf.ComboBoxInput();
	combobox.apply( $( $newField.find( '.pfComboBox' ) ) );
	if ( addAboveCurInstance ){
		$newField.insertBefore(this.closest(".fieldBox"))
                        .hide().fadeIn();
	} else {
		jQuery( '#fieldsList' ).append( $newField.hide().fadeIn() );
	}
}

function validateCreateTemplateForm() {
	var blankTemplateName = ( jQuery( 'input[name="template_name"]' ).val() === '' );
	var blankCargoTableName = ( jQuery( '#use_cargo_toggle' ).attr('aria-checked') == 'true' &&
		jQuery( 'input[name="cargo_table"]' ).val() === '' );
	if ( blankTemplateName || blankCargoTableName || !validateHierarchyStructure() ) {
		scroll( 0, 0 );
		if ( blankTemplateName ) {
			jQuery( 'div.pfTemplateNameBlankError' ).show();
		} else {
			jQuery( "div.pfTemplateNameBlankError" ).hide();
		}
		if ( blankCargoTableName ) {
			jQuery( 'div.pfCargoTableNameBlankError' ).show();
		} else {
			jQuery( "div.pfCargoTableNameBlankError" ).hide();
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
		containerElement.find( ".hierarchy_structure" ).show('medium');
		if (containerElement.find( "textarea[name*='hierarchy_structure_']" ).val() === "") {
			setHierarchyPlaceholder( containerElement.find( "textarea[name*='hierarchy_structure_']" ) );
		}
	} else {
		containerElement.find( ".hierarchy_structure_input" ).hide('medium');
		containerElement.find( ".hierarchy_structure" ).hide('medium');
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

function validateHierarchyStructure() {
	var $hierarchyTextAreas = jQuery("textarea[name*='hierarchy_structure_']");
	for (var i = 0; i < $hierarchyTextAreas.length; i++) {
		var structure = $hierarchyTextAreas[i].value.trim();
		if (structure !== "") {
			var nodes = structure.split(/\n/);
			var matches = nodes[0].match(/^([*]*)[^*]*/i);
			if (matches[1].length !== 1) {
				alert("Error: The first entry of hierarchy values should start with exactly one \'*\'; the entry \"" +
					nodes[0] + "\" has " + matches[1].length + " \'*\'");
				return false;
			}
			var level = 0;
			for (var j = 0; j < nodes.length; j++) {
				matches = nodes[j].match(/^([*]*)( *)(.*)/i);
				if (matches[1].length < 1) {
					alert("Error: Each entry of hierarchy values should start with at least one \'*\'; the entry \"" +
						nodes[j] + "\" starts with none");
					return false;
				}
				if (matches[1].length - level > 1) {
					alert("Error: Level or count of '*' in hierarchy values should increase by no more than 1 at a time, so the entry \"" +
						nodes[j] + "\" should have " + (level + 1) + " or fewer '*'");
					return false;
				}
				level = matches[1].length;
				if (matches[3].length === 0) {
					alert("Error: An entry in hierarchy values cannot be empty.");
					return false;
				}
			}
		}
	}
	return true;
}

jQuery( document ).ready( function () {
	var el = document.getElementById('fieldsList');
	var sortable = Sortable.create(el, {
		handle: '.instanceRearranger',
	});
	var toggleSwitch = new OO.ui.ToggleSwitchWidget( {
		id: 'use_cargo_toggle',
		value: true,
	} );
	jQuery( '#cargo_toggle' ).prepend( toggleSwitch.$element );
	jQuery( '#use_cargo_toggle' ).attr( 'name', 'use_cargo_toggle' );
	jQuery( "div.pfTemplateNameBlankError" ).hide();
	jQuery( "div.pfCargoTableNameBlankError" ).hide();
	jQuery( "label.delimiter" ).css( 'display', 'none' );
	jQuery( "div.delimiter" ).css( {
		'display': 'none',
		'width': '35px'
	} );
	jQuery( "label.hierarchy_structure_input" ).css( 'display', 'none' );
	jQuery( "div.hierarchy_structure" ).css( 'display', 'none' );
	jQuery( 'form#createTemplateForm' ).removeAttr('style');
	jQuery( '#use_cargo_toggle' ).click( function() {
		toggleCargoInputs();
	} );
	jQuery( ".createTemplateAddField" ).click( function () {
		$( this ).createTemplateAddField( false );
	} );
	jQuery( ".addAboveButton" ).click( function () {
		$( this ).createTemplateAddField( true );
	} );
	jQuery( ".removeButton" ).click( function () {
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
