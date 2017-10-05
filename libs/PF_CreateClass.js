/*global alert*/

var rowNum = mediaWiki.config.get( '$numStartingRows');
var hierarchyPlaceholder =  mediaWiki.msg( 'pf_createtemplate_hierarchystructureplaceholder' );
function createClassAddRow() {
	rowNum++;
	var newRow = jQuery('#starterRow').clone().css('display', '').removeAttr( 'id' );
	var newHTML = newRow.html().replace(/starter/g, rowNum);
	newRow.html(newHTML);
	newRow.find( "input[name*='is_hierarchy_']" ).click( function () {
		toggleHierarchyInput(jQuery( this ).closest( "tr" ));
	} );
	newRow.find( ".hierarchy_structure" ).blur( function () {
		setHierarchyPlaceholder( jQuery( this ) );
	} );
	newRow.find( ".hierarchy_structure" ).click( function () {
		if (jQuery( this ).attr( 'validInput' ) === undefined || jQuery( this ).attr( 'validInput' ) !== 'true') {
			removeHierarchyPlaceholder( jQuery( this ) );
		}
	} );
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
		jQuery('td:nth-child(6),th:nth-child(5)').show('medium');
	} else {
		jQuery('#cargo_table_input').hide('medium');
		$('td:nth-child(4),th:nth-child(3)').hide('medium');
		jQuery("input[name*='is_hierarchy_']").prop('checked', false);
		jQuery('td:nth-child(6),th:nth-child(5)').hide('medium');
		jQuery("textarea[name*='hierarchy_structure_']").hide('medium');
		jQuery("input[name*='allowed_values_']").show('medium');
	}
}

function toggleHierarchyInput(containerElement) {
	if (containerElement.find( "input[name*='is_hierarchy_']" ).prop('checked')) {
		containerElement.find( "input[name*='allowed_values_']" ).hide('medium');
		containerElement.find( "textarea[name*='hierarchy_structure_']" ).show('medium');
		if (containerElement.find( "textarea[name*='hierarchy_structure_']" ).val() === "") {
			setHierarchyPlaceholder( containerElement.find( "textarea[name*='hierarchy_structure_']" ) );
		}
	} else {
		containerElement.find( "textarea[name*='hierarchy_structure_']" ).hide('medium');
		containerElement.find( "input[name*='allowed_values_']" ).show('medium');
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
	var hierarchyTextAreas = jQuery("textarea[name*='hierarchy_structure_']");
	for (var i = 0; i < hierarchyTextAreas.length; i++) {
		var structure = hierarchyTextAreas[i].value.trim();
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
						nodes[j] + "\" should have " + ( level + 1 ) + " or fewer '*'");
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
	jQuery( ".disableFormAndCategoryInputs" ).click( function () {
		disableFormAndCategoryInputs();
	} );
	jQuery( "#use_cargo" ).click( function() {
		toggleCargoInputs();
	} );
	jQuery( ".createClassAddRow" ).click( function () {
		createClassAddRow();
	} );
	jQuery( "input[name*='is_hierarchy_']" ).click( function () {
		toggleHierarchyInput(jQuery( this ).closest( "tr" ));
	} );
	jQuery( ".hierarchy_structure" ).blur( function () {
		setHierarchyPlaceholder( jQuery( this ) );
	} );
	jQuery( ".hierarchy_structure" ).click( function () {
		if (jQuery( this ).attr( 'validInput' ) === undefined || jQuery( this ).attr( 'validInput' ) !== 'true') {
			removeHierarchyPlaceholder( jQuery( this ) );
		}
	} );
	jQuery( '#createClassForm' ).submit( function () {
		return validateHierarchyStructure();
	} );
} );
