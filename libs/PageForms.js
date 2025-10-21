const Sortable = require( 'ext.pageforms.sortable' );

/**
 * PageForms.js
 *
 * Javascript utility functions for the Page Forms extension.
 *
 * @author Yaron Koren
 * @author Sanyam Goyal
 * @author Stephan Gambke
 * @author Jeffrey Stuckman
 * @author Harold Solbrig
 * @author Eugene Mednikov
 */
/*global wgPageFormsShowOnSelect, wgPageFormsFieldProperties, wgPageFormsCargoFields, wgPageFormsDependentFields, validateAll, alert, mwTinyMCEInit, pf, Sortable*/

( function( $, mw ) {

/*
 * Functions to register/unregister methods for the initialization and
 * validation of inputs.
 */

// Initialize data object to hold initialization and validation data
function setupPF() {

	$("#pfForm").data("PageForms",{
		initFunctions : [],
		validationFunctions : []
	});

}

// Register a validation method
//
// More than one method may be registered for one input by subsequent calls to
// PageForms_registerInputValidation.
//
// Validation functions and their data are stored in a numbered array
//
// @param valfunction The validation functions. Must take a string (the input's id) and an object as parameters
// @param param The parameter object given to the validation function
$.fn.PageForms_registerInputValidation = function(valfunction, param) {

	if ( ! this.attr("id") ) {
		return this;
	}

	if ( ! $("#pfForm").data("PageForms") ) {
		setupPF();
	}

	$("#pfForm").data("PageForms").validationFunctions.push({
		input : this.attr("id"),
		valfunction : valfunction,
		parameters : param
	});

	return this;
};

// Register an initialization method
//
// More than one method may be registered for one input by subsequent calls to
// PageForms_registerInputInit. This method also executes the initFunction
// if the element referenced by /this/ is not part of a multipleTemplateStarter.
//
// Initialization functions and their data are stored in a associative array
//
// @param initFunction The initialization function. Must take a string (the input's id) and an object as parameters
// @param param The parameter object given to the initialization function
// @param noexecute If set, the initialization method will not be executed here
$.fn.PageForms_registerInputInit = function( initFunction, param, noexecute ) {

	// return if element has no id
	if ( ! this.attr("id") ) {
		return this;
	}

	// setup data structure if necessary
	if ( ! $("#pfForm").data("PageForms") ) {
		setupPF();
	}

	// if no initialization function for this input was registered yet,
	// create entry
	if ( ! $("#pfForm").data("PageForms").initFunctions[this.attr("id")] ) {
		$("#pfForm").data("PageForms").initFunctions[this.attr("id")] = [];
	}

	// record initialization function
	$("#pfForm").data("PageForms").initFunctions[this.attr("id")].push({
		initFunction : initFunction,
		parameters : param
	});

	// execute initialization if input is not part of multipleTemplateStarter
	// and if not forbidden
	if ( this.closest(".multipleTemplateStarter").length === 0 && !noexecute) {
		const $input = this;
		// ensure initFunction is only executed after doc structure is complete
		$(() => {
			if ( initFunction !== undefined ) {
				initFunction( $input.attr("id"), param );
			}
		});
	}

	return this;
};

// Unregister all validation methods for the element referenced by /this/
$.fn.PageForms_unregisterInputValidation = function() {

	const pfdata = $("#pfForm").data("PageForms");

	if ( this.attr("id") && pfdata ) {
		// delete every validation method for this input
		for ( let i = 0; i < pfdata.validationFunctions.length; i++ ) {
			if ( typeof pfdata.validationFunctions[i] !== 'undefined' &&
				pfdata.validationFunctions[i].input === this.attr("id") ) {
				delete pfdata.validationFunctions[i];
			}
		}
	}

	return this;
};

// Unregister all initialization methods for the element referenced by /this/
$.fn.PageForms_unregisterInputInit = function() {

	if ( this.attr("id") && $("#pfForm").data("PageForms") ) {
		delete $("#pfForm").data("PageForms").initFunctions[this.attr("id")];
	}

	return this;
};

// Called from within PF_ComboBoxInput.php.
mw.hook('pf.comboboxChange').add( ( $parentSpan ) => {
	const initPage = $parentSpan.find('select').length > 0;
	const partOfMultiple = $parentSpan.attr('data-origid') !== undefined;
	$parentSpan.showIfSelected( partOfMultiple, initPage );
});

/*
 * Functions for handling 'show on select'
 */

// Display a div that would otherwise be hidden by "show on select".
function showDiv( div_id, $instanceWrapperDiv, initPage ) {
	const speed = initPage ? 0 : 'fast';
	let $elem;
	if ( $instanceWrapperDiv !== null ) {
		$elem = $('[data-origID="' + div_id + '"]', $instanceWrapperDiv);
	} else {
		$elem = $('#' + div_id);
	}

	$elem
	.addClass('shownByPF')

	.find(".hiddenByPF")
	.removeClass('hiddenByPF')
	.addClass('shownByPF')

	.find(".disabledByPF")
	.prop('disabled', false)
	.removeClass('disabledByPF');

	$elem.each( function() {
		if ( $(this).css('display') === 'none' ) {

			$(this).slideDown(speed, function() {
				$(this).fadeTo(speed,1);
			});

		}
	});

	// Now re-show any form elements that are meant to be shown due
	// to the current value of form inputs in this div that are now
	// being uncovered.
	const wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' );
	$elem.find(".pfShowIfSelected, .pfShowIfChecked").each( function() {
		const $uncoveredInput = $(this);
		let uncoveredInputID = null;
		if ( $instanceWrapperDiv === null ) {
			uncoveredInputID = $uncoveredInput.attr("id");
		} else {
			uncoveredInputID = $uncoveredInput.attr("data-origID");
		}
		const showOnSelectVals = wgPageFormsShowOnSelect[uncoveredInputID];

		if ( showOnSelectVals !== undefined ) {
			const inputVal = $uncoveredInput.val();
			for ( let i = 0; i < showOnSelectVals.length; i++ ) {
				const options = showOnSelectVals[i][0];
				const div_id2 = showOnSelectVals[i][1];
				if ( $uncoveredInput.hasClass( 'pfShowIfSelected' ) ) {
					showDivIfSelected( options, div_id2, inputVal, $instanceWrapperDiv, initPage );
				} else {
					$uncoveredInput.showDivIfChecked( options, div_id2, $instanceWrapperDiv, initPage );
				}
			}
		}
	});
}

// Hide a div due to "show on select". The CSS class is there so that PF can
// ignore the div's contents when the form is submitted.
function hideDiv( div_id, $instanceWrapperDiv, initPage ) {
	const speed = initPage ? 0 : 'fast';
	let $elem;
	// IDs can't contain spaces, and jQuery won't work with such IDs - if
	// this one has a space, display an alert.
	if ( div_id.includes(' ') ) {
		// TODO - this should probably be a language value, instead of
		// hardcoded in English.
		alert( "Warning: this form has \"show on select\" pointing to an invalid element ID (\"" + div_id + "\") - IDs in HTML cannot contain spaces." );
	}

	if ( $instanceWrapperDiv !== null ) {
		$elem = $instanceWrapperDiv.find('[data-origID=' + div_id + ']');
	} else {
		$elem = $('#' + div_id);
	}

	// If we're just setting up the page, and this element has already
	// been marked to be shown by some other input, don't hide it.
	if ( initPage && $elem.hasClass('shownByPF') ) {
		return;
	}

	$elem.find("span, div").addClass('hiddenByPF');

	$elem.each( function() {
		if ( $(this).css('display') !== 'none' ) {

			// if 'display' is not 'hidden', but the element is hidden otherwise
			// (e.g. by having height = 0), just hide it, else animate the hiding
			if ( $(this).is(':hidden') ) {
				$(this).hide();
			} else {
				$(this).fadeTo(speed, 0, function() {
					$(this).slideUp(speed);
				});
			}
		}
	});

	// Also, recursively hide further elements that are only shown because
	// inputs within this now-hidden div were checked/selected.
	const wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' );
	$elem.find(".pfShowIfSelected, .pfShowIfChecked").each( function() {
		let showOnSelectVals;
		if ( $instanceWrapperDiv === null ) {
			showOnSelectVals = wgPageFormsShowOnSelect[$(this).attr("id")];
		} else {
			showOnSelectVals = wgPageFormsShowOnSelect[$(this).attr("data-origID")];
		}

		if ( showOnSelectVals !== undefined ) {
			for ( let i = 0; i < showOnSelectVals.length; i++ ) {
				//var options = showOnSelectVals[i][0];
				const div_id2 = showOnSelectVals[i][1];
				hideDiv( div_id2, $instanceWrapperDiv, initPage );
			}
		}
	});
}

// Show this div if the current value is any of the relevant options -
// otherwise, hide it.
function showDivIfSelected(options, div_id, inputVal, $instanceWrapperDiv, initPage) {
	for ( let i = 0; i < options.length; i++ ) {
		// If it's a listbox and the user has selected more than one
		// value, it'll be an array - handle either case.
		if (($.isArray(inputVal) && $.inArray(options[i], inputVal) >= 0) ||
			(!$.isArray(inputVal) && (inputVal === options[i]))) {
			showDiv( div_id, $instanceWrapperDiv, initPage );
			return;
		}
	}
	hideDiv( div_id, $instanceWrapperDiv, initPage );
}

// Used for handling 'show on select' for the 'dropdown', 'listbox',
// 'combobox' and 'tokens' input types.
$.fn.showIfSelected = function(partOfMultiple, initPage) {
	let inputVal,
		wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' ),
		showOnSelectVals,
		$instanceWrapperDiv;

	if ( this.attr( 'data-input-type' ) == 'combobox' ) {
		if ( initPage ) {
			inputVal = $(this).find('select').val();
		} else {
			inputVal = $(this).find('input').val();
		}
	} else if ( this.attr( 'data-input-type' ) == 'tokens' ) {
		if ( initPage ) {
			inputVal = $(this).find('select').val();
		} else {
			inputVal = [];
			$(this).find('li.select2-selection__choice').each( function() {
				inputVal.push( $(this).attr('title') );
			});
		}
	} else {
		inputVal = this.val();
	}

	if ( partOfMultiple ) {
		showOnSelectVals = wgPageFormsShowOnSelect[this.attr("data-origID")];
		$instanceWrapperDiv = this.closest('.multipleTemplateInstance');
	} else {
		showOnSelectVals = wgPageFormsShowOnSelect[this.attr("id")];
		$instanceWrapperDiv = null;
	}

	if ( showOnSelectVals !== undefined ) {
		for ( let i = 0; i < showOnSelectVals.length; i++ ) {
			const options = showOnSelectVals[i][0];
			const div_id = showOnSelectVals[i][1];
			showDivIfSelected( options, div_id, inputVal, $instanceWrapperDiv, initPage );
		}
	}

	return this;
};

// Show this div if any of the relevant selections are checked -
// otherwise, hide it.
$.fn.showDivIfChecked = function(options, div_id, $instanceWrapperDiv, initPage ) {
	for ( let i = 0; i < options.length; i++ ) {
		if ($(this).find('[value="' + options[i] + '"]').is(":checked")) {
			showDiv( div_id, $instanceWrapperDiv, initPage );
			return this;
		}
	}
	hideDiv( div_id, $instanceWrapperDiv, initPage );

	return this;
};

// Used for handling 'show on select' for the 'checkboxes' and 'radiobutton'
// inputs.
$.fn.showIfChecked = function(partOfMultiple, initPage) {
	let wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' ),
		showOnSelectVals,
		$instanceWrapperDiv,
		i;

	if ( partOfMultiple ) {
		showOnSelectVals = wgPageFormsShowOnSelect[this.attr("data-origID")];
		$instanceWrapperDiv = this.closest('.multipleTemplateInstance');
	} else {
		showOnSelectVals = wgPageFormsShowOnSelect[this.attr("id")];
		$instanceWrapperDiv = null;
	}

	if ( showOnSelectVals !== undefined ) {
		for ( i = 0; i < showOnSelectVals.length; i++ ) {
			const options = showOnSelectVals[i][0];
			const div_id = showOnSelectVals[i][1];
			this.showDivIfChecked( options, div_id, $instanceWrapperDiv, initPage );
		}
	}

	return this;
};

// Used for handling 'show on select' for the 'checkbox' input.
$.fn.showIfCheckedCheckbox = function( partOfMultiple, initPage ) {
	let wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' ),
		divIDs,
		$instanceWrapperDiv = null,
		i;
	if ( partOfMultiple ) {
		divIDs = wgPageFormsShowOnSelect[this.attr( "data-origID" )];
		$instanceWrapperDiv = this.closest( ".multipleTemplateInstance" );
	}
	if ( divIDs === undefined ) {
		divIDs = wgPageFormsShowOnSelect[this.attr( "id" )];
	}
	for ( i = 0; i < divIDs.length; i++ ) {
		const divID = divIDs[i];
		if ( $( this ).find( '[value]' ).is( ":checked" ) ) {
			showDiv( divID, $instanceWrapperDiv, initPage );
		} else {
			hideDiv( divID, $instanceWrapperDiv, initPage );
		}
	}

	return this;
};

/*
 * Validation functions
 */

// Set the error message for an input.
$.fn.setErrorMessage = function(msg, val) {
	const container = this.find('.pfErrorMessages');
	container.html($('<div>').addClass( 'errorMessage' ).text( mw.msg( msg, val ) ));
};

// Append an error message to the end of an input.
$.fn.addErrorMessage = function(msg, val) {
	this.find('input').addClass('inputError');
	this.find('select2-container').addClass('inputError');
	this.append($('<div>').addClass( 'errorMessage' ).text( mw.msg( msg, val ) ));
	// If this is part of a minimized multiple-template instance, add a
	// red border around the instance rectangle to make it easier to find.
	this.parents( '.multipleTemplateInstance.minimized' ).css( 'border', '1px solid red' );
};

$.fn.isAtMaxInstances = function() {
	const numInstances = this.find("div.multipleTemplateInstance").length;
	const maximumInstances = this.attr("maximumInstances");
	if ( numInstances >= maximumInstances ) {
		this.parent().setErrorMessage( 'pf_too_many_instances_error', maximumInstances );
		return true;
	}
	return false;
};

$.fn.validateNumInstances = function() {
	const minimumInstances = this.attr("minimumInstances");
	const maximumInstances = this.attr("maximumInstances");
	const numInstances = this.find("div.multipleTemplateInstance").length;
	if ( numInstances < minimumInstances ) {
		this.parent().addErrorMessage( 'pf_too_few_instances_error', minimumInstances );
		return false;
	} else if ( numInstances > maximumInstances ) {
		this.parent().addErrorMessage( 'pf_too_many_instances_error', maximumInstances );
		return false;
	} else {
		return true;
	}
};

$.fn.validateMandatoryField = function() {
	const fieldVal = this.find(".mandatoryField").val();
	let isEmpty;

	if (fieldVal === null) {
		isEmpty = true;
	} else if ($.isArray(fieldVal)) {
		isEmpty = (fieldVal.length === 0);
	} else {
		isEmpty = (fieldVal.replace(/\s+/, '') === '');
	}
	if (isEmpty) {
		this.addErrorMessage( 'pf_blank_error' );
		return false;
	} else {
		return true;
	}
};

$.fn.validateUniqueField = function() {

	const UNDEFINED = "undefined";
	const field = this.find(".uniqueField");
	const fieldVal = field.val();

	if (typeof fieldVal === UNDEFINED || fieldVal.replace(/\s+/, '') === '') {
		return true;
	}

	const fieldOrigVal = field.prop("defaultValue");
	if (fieldVal === fieldOrigVal) {
		return true;
	}

	const categoryFieldName = field.prop("id") + "_unique_for_category";
	const $categoryField = $("[name=" + categoryFieldName + "]");
	let category = $categoryField.val();

	const namespaceFieldName = field.prop("id") + "_unique_for_namespace";
	const $namespaceField = $("[name=" + namespaceFieldName + "]");
	const namespace = $namespaceField.val();

	let url = mw.config.get( 'wgScriptPath' ) + "/api.php?format=json&action=";

	let query,
		isNotUnique;

	// SMW
	const propertyFieldName = field.prop("id") + "_unique_property",
		$propertyField = $("[name=" + propertyFieldName + "]"),
		property = $propertyField.val();
	if (typeof property !== UNDEFINED && property.replace(/\s+/, '') !== '') {

		query = "[[" + property + "::" + fieldVal + "]]";

		if (typeof category !== UNDEFINED &&
			category.replace(/\s+/, '') !== '') {
			query += "[[Category:" + category + "]]";
		}

		if (typeof namespace !== UNDEFINED) {
			if (namespace.replace(/\s+/, '') !== '') {
				query += "[[:" + namespace + ":+]]";
			} else {
				query += "[[:+]]";
			}
		}

		const conceptFieldName = field.prop("id") + "_unique_for_concept";
		const $conceptField = $("[name=" + conceptFieldName + "]");
		const concept = $conceptField.val();
		if (typeof concept !== UNDEFINED &&
			concept.replace(/\s+/, '') !== '') {
			query += "[[Concept:" + concept + "]]";
		}

		query += "|limit=1";
		query = encodeURIComponent(query);

		url += "ask&query=" + query;
		isNotUnique = true;
		$.ajax({
			url: url,
			dataType: 'json',
			async: false,
			success: function(data) {
				if (data.query.meta.count === 0) {
					isNotUnique = false;
				}
			}
		});
		if (isNotUnique) {
			this.addErrorMessage( 'pf_not_unique_error' );
			return false;
		} else {
			return true;
		}
	}

	// Cargo
	const cargoTableFieldName = field.prop("id") + "_unique_cargo_table";
	const $cargoTableField = $("[name=" + cargoTableFieldName + "]");
	let cargoTable = $cargoTableField.val();
	const cargoFieldFieldName = field.prop("id") + "_unique_cargo_field";
	const $cargoFieldField = $("[name=" + cargoFieldFieldName + "]");
	const cargoField = $cargoFieldField.val();
	if (typeof cargoTable !== UNDEFINED && cargoTable.replace(/\s+/, '') !== ''
		&& typeof cargoField !== UNDEFINED
		&& cargoField.replace(/\s+/, '') !== '') {

		query = "&where=" + cargoField + "+=+'" + fieldVal + "'";

		if (typeof category !== UNDEFINED &&
			category.replace(/\s+/, '') !== '') {
			category = category.replace(/\s/, '_');
			query += "+AND+cl_to=" + category + "+AND+cl_from=_pageID";
			cargoTable += ",categorylinks";
		}

		if (typeof namespace !== UNDEFINED) {
			query += "+AND+_pageNamespace=";
			if (namespace.replace(/\s+/, '') !== '') {
				const ns = mw.config.get('wgNamespaceIds')[namespace.toLowerCase()];
				if (typeof ns !== UNDEFINED) {
					query += ns;
				}
			} else {
				query += "0";
			}
		}

		query += "&limit=1";

		url += "cargoquery&tables=" + cargoTable + "&fields=" + cargoField +
			query;
		isNotUnique = true;
		$.ajax({
			url: url,
			dataType: 'json',
			async: false,
			success: function(data) {
				if (data.cargoquery.length === 0) {
					isNotUnique = false;
				}
			}
		});
		if (isNotUnique) {
			this.addErrorMessage( 'pf_not_unique_error' );
			return false;
		} else {
			return true;
		}
	}

	return true;

};

$.fn.validateMandatoryComboBox = function() {
	const $combobox = this.find(':input');
	if ($combobox.val() === null || $combobox.val() === '') {
		this.addErrorMessage( 'pf_blank_error' );
		return false;
	} else {
		return true;
	}
};

$.fn.validateMandatoryDateField = function() {
	if (this.find(".dayInput").val() === '' ||
		this.find(".monthInput").val() === '' ||
		this.find(".yearInput").val() === '') {
		this.addErrorMessage( 'pf_blank_error' );
		return false;
	} else {
		return true;
	}
};

$.fn.validateMandatoryRadioButton = function() {
	const checkedValue = this.find("input:checked").val();
	if (!checkedValue || checkedValue == '') {
		this.addErrorMessage('pf_blank_error');
		return false;
	} else {
		return true;
	}
};

$.fn.validateMandatoryCheckboxes = function() {
	// Get the number of checked checkboxes within this span - must
	// be at least one.
	const numChecked = this.find("input:checked").length;
	if (numChecked === 0) {
		this.addErrorMessage('pf_blank_error');
		return false;
	} else {
		return true;
	}
};

$.fn.validateMandatoryTree = function() {
	const input_value = this.find( 'input' ).attr( 'value' );
	if ( input_value === undefined || input_value === '' ) {
		this.addErrorMessage( 'pf_blank_error' );
		return false;
	} else {
		return true;
	}
};

$.fn.validateMandatoryDatePicker = function() {
	const input = this.find('input');
	if (input.val() === null || input.val() === '') {
		this.addErrorMessage( 'pf_blank_error' );
		return false;
	} else {
		return true;
	}
};

/*
 * Type-based validation
 */

$.fn.validateURLField = function() {
	const fieldVal = this.find("input").val();
	let url_protocol = mw.config.get( 'wgUrlProtocols' );
	//removing backslash before colon from url_protocol string
	url_protocol = url_protocol.replace( /\\:/, ':' );
	//removing '//' from wgUrlProtocols as this causes to match any protocol in regexp
	url_protocol = url_protocol.replace( /\|\\\/\\\//, '' );
	const url_regexp = new RegExp( '(' + url_protocol + ')' + '(\\w+:{0,1}\\w*@)?(\\S+)(:[0-9]+)?(\/|\/([\\w#!:.?+=&%@!\\-\/]))?' );
	if (fieldVal === "" || url_regexp.test(fieldVal)) {
		return true;
	} else {
		this.addErrorMessage( 'pf_bad_url_error' );
		return false;
	}
};

$.fn.validateEmailField = function() {
	const fieldVal = this.find("input").val();
	// code borrowed from http://javascript.internet.com/forms/email-validation---basic.html
	const email_regexp = /^\s*\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,63})+\s*$/;
	if (fieldVal === '' || email_regexp.test(fieldVal)) {
		return true;
	} else {
		this.addErrorMessage( 'pf_bad_email_error' );
		return false;
	}
};

$.fn.validateNumberField = function() {
	const fieldVal = this.find("input").val();
	// Handle "E notation"/scientific notation ("1.2e-3") in addition
	// to regular numbers
	if (fieldVal === '' ||
	fieldVal.match(/^\s*[\-+]?((\d{1,3}(,\d{3})+[\.,]?\d*)|(\d*[\.,]?\d+))([eE]?[\-\+]?\d+)?\s*$/)) {
		return true;
	} else {
		this.addErrorMessage( 'pf_bad_number_error' );
		return false;
	}
};

$.fn.validateIntegerField = function() {
	const fieldVal = this.find("input").val();
	if ( fieldVal === '' || fieldVal == parseInt( fieldVal, 10 ) ) {
		return true;
	} else {
		this.addErrorMessage( 'pf_bad_integer_error' );
		return false;
	}
};

$.fn.validateDateField = function() {
	// validate only if day and year fields are both filled in
	const dayVal = this.find(".dayInput").val();
	const yearVal = this.find(".yearInput").val();
	if (dayVal === '' || yearVal === '') {
		return true;
	} else if (dayVal.match(/^\d+$/) && dayVal <= 31) {
		// no year validation, since it can also include
		// 'BC' and possibly other non-number strings
		return true;
	} else {
		this.addErrorMessage( 'pf_bad_date_error' );
		return false;
	}
};

// Standalone pipes are not allowed, because they mess up the template
// parsing; unless they're part of a call to a template or a parser function.
$.fn.checkForPipes = function() {
	let fieldVal = this.find("input, textarea").val();
	// We need to check for a few different things because this is
	// called for a variety of different input types.
	if ( fieldVal === undefined || fieldVal === '' ) {
		fieldVal = this.text();
	}
	if ( fieldVal === undefined || fieldVal === '' ) {
		return true;
	}
	if ( !fieldVal.includes('|') ) {
		return true;
	}

	// Also allow pipes within special tags, like <pre> or <syntaxhighlight>.
	// Code copied, more or less, from PFTemplateInForm::escapeNonTemplatePipes().
	const startAndEndTags = [
		[ '<pre', 'pre>' ],
		[ '<syntaxhighlight', 'syntaxhighlight>' ],
		[ '<source', 'source>' ],
		[ '<ref', 'ref>' ]
	];

	for ( const i in startAndEndTags ) {
		const startTag = startAndEndTags[i][0];
		const endTag = startAndEndTags[i][1];
		const pattern = RegExp( "(" + startTag + "[^]*?)\\|([^]*?" + endTag + ")", 'i' );
		var matches;
		while ( ( matches = fieldVal.match( pattern ) ) !== null ) {
			// Special handling, to avoid escaping pipes
			// within a string that looks like:
			// startTag ... endTag | startTag ... endTag
			if ( matches[1].includes( endTag ) &&
				matches[2].includes( startTag ) ) {
				fieldVal = fieldVal.replace( pattern, "$1" + "\2" + "$2");
			} else {
				fieldVal = fieldVal.replace( pattern, "$1" + "\1" + "$2" );
			}
		}
	}
	fieldVal = fieldVal.replace( "\2", '|' );

	// Now check for pipes outside of brackets.
	let nextPipe,
		nextDoubleBracketsStart,
		nextDoubleBracketsEnd;

	// There's at least one pipe - here's where the real work begins.
	// We do a mini-parsing of the string to try to make sure that every
	// pipe is within either double square brackets (links) or double
	// curly brackets (parser functions, template calls).
	// For simplicity's sake, turn all curly brackets into square brackets,
	// so we only have to check for one thing.
	// This will incorrectly allow bad text like "[[a|b}}", but hopefully
	// that's not a major problem.
	fieldVal = fieldVal.replace( /{{/g, '[[' );
	fieldVal = fieldVal.replace( /}}/g, ']]' );
	let curIndex = 0;
	let numUnclosedBrackets = 0;
	while ( true ) {
		nextDoubleBracketsStart = fieldVal.indexOf( '[[', curIndex );

		if ( numUnclosedBrackets === 0 ) {
			nextPipe = fieldVal.indexOf( '|', curIndex );
			if ( nextPipe < 0 ) {
				return true;
			}
			if ( nextDoubleBracketsStart < 0 || nextPipe < nextDoubleBracketsStart ) {
				// There's a pipe where it shouldn't be.
				this.addErrorMessage( 'pf_pipe_error' );
				return false;
			}
		}
		if ( nextDoubleBracketsEnd < 0 ) {
			// Something is malformed - might as well throw
			// an error.
			this.addErrorMessage( 'pf_pipe_error' );
			return false;
		}

		nextDoubleBracketsEnd = fieldVal.indexOf( ']]', curIndex );

		if ( nextDoubleBracketsStart >= 0 && nextDoubleBracketsStart < nextDoubleBracketsEnd ) {
			numUnclosedBrackets++;
			curIndex = nextDoubleBracketsStart + 2;
		} else {
			numUnclosedBrackets--;
			curIndex = nextDoubleBracketsEnd + 2;
		}
	}

	// We'll never get here, but let's have this line anyway.
	return true;
};

function leftPad( number, targetLength ) {
	var negative = false;
	if ( number < 0 ) {
		number = number * -1;
		var negative = true;
	}
	let output = number + '';
	while ( output.length < targetLength ) {
		output = '0' + output;
	}
	if ( negative ) {
		output = '-' + output
	}
	return output;
}

function validateStartEndDateField( startInput, endInput ) {
	if ( !startInput.length || !endInput.length ) {
		return true;
	}

	// We get the index, instead of the actual value, of the month dropdown in
	// case it's a text value (i.e., with $wgAmericanDates.)
	const startYearVal = leftPad( startInput.find(".yearInput").val(),4 );
	const startMonthVal = leftPad( startInput.find(".monthInput").prop('selectedIndex'),2 );
	const startDayVal = leftPad( startInput.find(".dayInput").val(),2 );

	const endYearVal = leftPad( endInput.find(".yearInput").val(),4 );
	const endMonthVal = leftPad( endInput.find(".monthInput").prop('selectedIndex'),2 );
	const endDayVal = leftPad( endInput.find(".dayInput").val(),2 );

	const startDate = startYearVal + "/" + startMonthVal + "/" + startDayVal;

	const endDate = endYearVal + "/" + endMonthVal + "/" + endDayVal;

	if ( startDate <= endDate || endDate == "0000/00/00") {
		return true;
	} else {
		if ( endInput ) {
			endInput.addErrorMessage( 'pf_start_end_date_error' )
		} else if ( startInput ) {
			startInput.addErrorMessage( 'pf_start_end_date_error' )
		}
		return false;
	}
}

function validateStartEndDateTimeField( startInput, endInput ) {
	if ( !startInput.length || !endInput.length ) {
		return true;
	}
	const startYearVal = leftPad( startInput.find(".yearInput").val(),4 );
	const startMonthVal = leftPad( startInput.find(".monthInput").val(),2 );
	const startDayVal = leftPad( startInput.find(".dayInput").val(),2 );
	const startHoursVal = leftPad( startInput.find(".hoursInput").val(),2 );
	const startMinutesVal = leftPad( startInput.find(".minutesInput").val(),2 );
	const startSecondsVal = leftPad( startInput.find(".secondsInput").val(),2 );
	const startAmPmVal = startInput.find(".ampmInput").val();

	const endYearVal = leftPad( endInput.find(".yearInput").val(),4 );
	const endMonthVal = leftPad( endInput.find(".monthInput").val(),2 );
	const endDayVal = leftPad( endInput.find(".dayInput").val(),2 );
	const endHoursVal = leftPad( endInput.find(".hoursInput").val(),2 );
	const endMinutesVal = leftPad( endInput.find(".minutesInput").val(),2 );
	const endSecondsVal = leftPad( endInput.find(".secondsInput").val(),2 );
	const endAmPmVal = endInput.find(".ampmInput").val();

	const startDateTime = startYearVal + "/" + startMonthVal + "/" + startDayVal + " " +
	startHoursVal + ":" + startMinutesVal + ":" + startSecondsVal + " " + startAmPmVal;

	const endDateTime = endYearVal + "/" + endMonthVal + "/" + endDayVal + " " +
		endHoursVal + ":" + endMinutesVal + ":" + endSecondsVal + " " + endAmPmVal;

	if ( startDateTime <= endDateTime || endDateTime == "0000/00/00 00:00:00 " ) {
		return true;
	} else {
		if ( endInput ) {
			endInput.addErrorMessage( 'pf_start_end_datetime_error' )
		} else if ( startInput ) {
			startInput.addErrorMessage( 'pf_start_end_datetime_error' )
		}
		return false;
	}

}

window.validateAll = function() {

	// Remove all old error messages.
	$(".errorMessage").remove();

	// Hook that fires on form submission, before the validation.
	mw.hook('pf.formValidationBefore').fire();

	const args = {numErrors: 0};
	mw.hook('pf.formValidation').fire( args );
	let num_errors = args.numErrors;

	// Make sure all inputs are ignored in the "starter" instance
	// of any multiple-instance template.
	$(".multipleTemplateStarter").find("span, div").addClass("hiddenByPF");

	$(".multipleTemplateList").each( function() {
		if (! $(this).validateNumInstances() ) {
			num_errors += 1;
		}
	});

	$("span.inputSpan.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryField() ) {
			num_errors += 1;
		}
	});
	$("span.comboboxSpan.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryComboBox() ) {
			num_errors += 1;
		}
	});
	$("span.dateInput.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryDateField() ) {
			num_errors += 1;
		}
	});
	$("span.radioButtonSpan.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryRadioButton() ) {
			num_errors += 1;
		}
	});
	$("span.checkboxesSpan.mandatoryFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryCheckboxes() ) {
			num_errors += 1;
		}
	});
	$("div.pfTreeInputWrapper.mandatory").not(".hiddenByPF").each( function() {
		if (! $(this).validateMandatoryTree() ) {
			num_errors += 1;
		}
	});
	$("div.pfPickerWrapper.mandatory").not(".hiddenByPF").each( function() {
		if (! $(this).find('.pfPicker').validateMandatoryDatePicker() ) {
			num_errors += 1;
		}
	});
	$("span.inputSpan.uniqueFieldSpan").not(".hiddenByPF").each( function() {
		if (! $(this).validateUniqueField() ) {
			num_errors += 1;
		}
	});
	$("span.inputSpan, div.pfComboBox").not(".hiddenByPF, .freeText, .pageSection").each( function() {
		if (! $(this).checkForPipes() ) {
			num_errors += 1;
		}
	});
	$("span.URLInput").not(".hiddenByPF").each( function() {
		if (! $(this).validateURLField() ) {
			num_errors += 1;
		}
	});
	$("span.emailInput").not(".hiddenByPF").each( function() {
		if (! $(this).validateEmailField() ) {
			num_errors += 1;
		}
	});
	$("span.numberInput").not(".hiddenByPF").each( function() {
		if (! $(this).validateNumberField() ) {
			num_errors += 1;
		}
	});
	$("span.integerInput").not(".hiddenByPF").each( function() {
		if (! $(this).validateIntegerField() ) {
			num_errors += 1;
		}
	});
	$("span.dateInput").not(".hiddenByPF").each( function() {
		if (! $(this).validateDateField() ) {
			num_errors += 1;
		}
	});
	$("input.modifiedInput").not(".hiddenByPF").each( function() {
		// No separate function needed.
		$(this).parent().addErrorMessage( 'pf_modified_input_error' );
		num_errors += 1;
	});

	const startDateInput = $("span.startDateInput").not(".hiddenByPF")
	const endDateInput = $("span.endDateInput").not(".hiddenByPF")

	if ( !validateStartEndDateField( startDateInput, endDateInput ) ) {
		num_errors += 1;
	}

	const startDateTimeInput = $("span.startDateTimeInput").not(".hiddenByPF")
	const endDateTimeInput = $("span.endDateTimeInput").not(".hiddenByPF")

	if ( !validateStartEndDateTimeField( startDateTimeInput, endDateTimeInput ) ) {
		num_errors += 1;
	}
	// call registered validation functions
	const pfdata = $("#pfForm").data('PageForms');

	if ( pfdata && pfdata.validationFunctions.length > 0 ) { // found data object?

		// for every registered input
		for ( let i = 0; i < pfdata.validationFunctions.length; i++ ) {

			// if input is not part of multipleTemplateStarter
			if ( typeof pfdata.validationFunctions[i] !== 'undefined' &&
				$("#" + pfdata.validationFunctions[i].input).closest(".multipleTemplateStarter").length === 0 &&
				$("#" + pfdata.validationFunctions[i].input).closest(".hiddenByPF").length === 0 ) {

				if (! pfdata.validationFunctions[i].valfunction(
						pfdata.validationFunctions[i].input,
						pfdata.validationFunctions[i].parameters)
					) {
					num_errors += 1;
				}
			}
		}
	}

	if (num_errors > 0) {
		// add error header, if it's not there already
		if ($("#form_error_header").length === 0) {
			$("#contentSub").append('<div id="form_error_header" class="errorbox" style="font-size: medium"><img src="' + mw.config.get( 'wgPageFormsScriptPath' ) + '/skins/MW-Icon-AlertMark.png" />&nbsp;' + mw.message( 'pf_formerrors_header' ).escaped() + '</div><br clear="both" />');
		}
		// The "Save page", etc. buttons were disabled to prevent
		// double-clicking; since there has been an error, re-enable
		// them so that the form can be submitted again after the
		// user tries to fix these errors.
		$( '.editButtons > .oo-ui-buttonElement' ).removeClass( 'oo-ui-widget-disabled' ).addClass( 'oo-ui-widget-enabled' );
		// Also undo the indicator that the form was submitted.
		$( '#pfForm' ).data('submitted', false);
		scroll(0, 0);
	} else {
		// Disable inputs hidden due to either "show on select" or
		// because they're part of the "starter" div for
		// multiple-instance templates, so that they aren't
		// submitted by the form.
		$('.hiddenByPF').find("input, select, textarea").not(':disabled')
		.prop('disabled', true)
		.addClass('disabledByPF');
		//remove error box if it exists because there are no errors in the form now
		$("#contentSub").find(".errorbox").remove();
	}

	// Hook that fires on form submission, after the validation.
	mw.hook('pf.formValidationAfter').fire();

	return (num_errors === 0);
};

/**
 * Minimize all instances if the total height of all the instances
 * is over 800 pixels - to allow for easier navigation and sorting.
 */
$.fn.possiblyMinimizeAllOpenInstances = function() {
	if ( ! this.hasClass( 'minimizeAll' ) ) {
		return;
	}

	const displayedFieldsWhenMinimized = this.attr('data-displayed-fields-when-minimized');
	let allDisplayedFields = null;
	if ( displayedFieldsWhenMinimized ) {
		allDisplayedFields = displayedFieldsWhenMinimized.split(',').map((item) => item.trim().toLowerCase());
	}

	this.find('.multipleTemplateInstance').not('.minimized').each( function() {
		const $instance = $(this);
		$instance.addClass('minimized');
		let valuesStr = '';
		$instance.find( "input[type != 'hidden'][type != 'button'], select, textarea, div.ve-ce-surface" ).each( function() {
			// If the set of fields to be displayed was specified in
			// the form definition, check against that list.
			if ( allDisplayedFields !== null ) {
				const fieldFullName = $(this).attr('name');
				if ( !fieldFullName ) {
					return;
				}
				const matches = fieldFullName.match(/.*\[.*\]\[(.*)\]/);
				const fieldRealName = matches[1].toLowerCase();
				if ( !allDisplayedFields.includes( fieldRealName ) ) {
					return;
				}
			}

			let curVal = $(this).val();
			if ( $(this).hasClass('ve-ce-surface') ) {
				// Special handling for VisualEditor/VEForAll textareas.
				curVal = $(this).text();
			}
			if ( typeof curVal !== 'string' || curVal === '' ) {
				return;
			}
			const inputType = $(this).attr('type');
			if ( inputType === 'checkbox' || inputType === 'radio' ) {
				if ( ! $(this).is(':checked') ) {
					return;
				}
			}
			if ( curVal.length > 70 ) {
				curVal = curVal.slice(0, 70) + "...";
			}
			if ( valuesStr !== '' ) {
				valuesStr += ' &middot; ';
			}
			valuesStr += curVal;
		});
		if ( valuesStr === '' ) {
			valuesStr = '<em>' + mw.message('pf-formedit-nodata').escaped() + '</em>';
		}
		$instance.find('.instanceMain').fadeOut( "medium", () => {
			$instance.find('.instanceRearranger').after('<td class="fieldValuesDisplay">' + valuesStr + '</td>');
		});
	});
};

$.fn.displayWizardScreen = function( screenNum, $wizardNav ) {
	const $wizardScreens = $(this);
	let $curScreen;

	$wizardScreens.each( function(i) {
		// screenNum starts at 1, not 0.
		if ( i + 1 == screenNum ) {
			$curScreen = $(this);
			$(this).show();
		} else {
			$(this).hide();
		}
	});

	// The rest of this function is taken up with displaying the
	// navigation to the next and previous wizard screens.
	const numScreens = $wizardScreens.length;

	$wizardNav.empty();

	const $navButtons = $('<div class="pf-wizard-buttons"></div>');

	if ( screenNum > 1 ) {
		let backText = $curScreen.attr('data-back-text');
		if ( backText == undefined ) {
			backText = mw.msg('pf-wizard-back');
		}
		const prevButton = new OO.ui.ButtonWidget( {
			label: backText,
			icon: 'previous',
			classes: [ 'pf-wizard-back-button' ]
		} );
		prevButton.$element.click( () => {
			$wizardScreens.displayWizardScreen( screenNum - 1, $wizardNav );
		});
		$navButtons.append( prevButton.$element );
	}

	if ( screenNum < numScreens ) {
		let continueText = $curScreen.attr('data-continue-text');
		if ( continueText == undefined ) {
			continueText = mw.msg('pf-wizard-continue');
		}
		const continueButton = new OO.ui.ButtonWidget( {
			label: continueText,
			flags: [
				'primary',
				'progressive'
			],
			icon: 'next',
			classes: [ 'pf-wizard-continue-button' ]
		} );
		continueButton.$element.click( () => {
			$wizardScreens.displayWizardScreen( screenNum + 1, $wizardNav );
		});
		$navButtons.append( continueButton.$element );
	}
	$wizardNav.append( $navButtons );

	// We need this in order to clear the float from the "previous" button.
	$wizardNav.append('<br style="clear: both;" />');

	// Use progress bar if the number of screens is greater than 10 and circles in the other case
	if ( numScreens > 10 ) {
		const progressBar = new OO.ui.ProgressBarWidget( {
			progress: 100 * screenNum / numScreens
		} );
		const progressBarLayout = new OO.ui.FieldLayout(
			progressBar,
			{
				label: 'Step ' + screenNum + ' of ' + numScreens,
				align: 'inline'
			}
		);
		$wizardNav.append( progressBarLayout.$element );
	} else {
		$( '.pf-wizard-buttons' ).addClass( 'pf-wizard-buttons-circle' );
		const progressCiclesUL = $( '<ul class="pfWizardCircles"></ul>' );
		for( let i = 1; i <= numScreens; i++ ) {
			let circle = '<li>' + i + '</li>';
			if ( i == screenNum ) {
				circle = '<li class="active">' + i + '</li>';
			}
			progressCiclesUL.append( $( circle ) );
		}
		$wizardNav.append( progressCiclesUL );
	}
};

let num_elements = 0;

/**
 * Functions for multiple-instance templates.
 *
 * @param {Mixed} addAboveCurInstance
 * @return {Mixed}
 */
$.fn.addInstance = function( addAboveCurInstance ) {
	const wgPageFormsShowOnSelect = mw.config.get( 'wgPageFormsShowOnSelect' );
	const wgPageFormsHeightForMinimizingInstances = mw.config.get( 'wgPageFormsHeightForMinimizingInstances' );
	const $wrapper = this.closest(".multipleTemplateWrapper");
	const $multipleTemplateList = $wrapper.find('.multipleTemplateList');

	// If the nubmer of instances is already at the maximum allowed,
	// exit here.
	if ( $multipleTemplateList.isAtMaxInstances() ) {
		return false;
	}

	if ( wgPageFormsHeightForMinimizingInstances >= 0 ) {
		if ( ! $multipleTemplateList.hasClass('minimizeAll') &&
			$multipleTemplateList.height() >= wgPageFormsHeightForMinimizingInstances ) {
			$multipleTemplateList.addClass('minimizeAll');
		}
		if ( $multipleTemplateList.hasClass('minimizeAll') ) {
			$multipleTemplateList
				.addClass('currentFocus')
				.possiblyMinimizeAllOpenInstances();
		}
	}

	// Global variable.
	num_elements++;

	// Create the new instance
	const $new_div = $wrapper
		.find(".multipleTemplateStarter")
		.clone()
		.removeClass('multipleTemplateStarter')
		.addClass('multipleTemplateInstance')
		.addClass('multipleTemplate') // backwards compatibility
		.removeAttr("id")
		.fadeTo(0,0)
		.slideDown('fast', function() {
			$(this).fadeTo('fast', 1);
		});

	// Add on a new attribute, "data-origID", representing the ID of all
	// HTML elements that had an ID; and delete the actual ID attribute
	// of any divs and spans (presumably, these exist only for the
	// sake of "show on select"). We do the deletions because no two
	// elements on the page are allowed to have the same ID.
	$new_div.find('[id!=""]').attr('data-origID', function() {
		return this.id;
	});
	$new_div.find('div[id!=""], span[id!=""]').removeAttr('id');

	$new_div.find('.hiddenByPF')
		.removeClass('hiddenByPF')
		.find('.disabledByPF')
		.prop('disabled', false)
		.removeClass('disabledByPF');

	// Make internal ID unique for the relevant form elements, and replace
	// the [num] index in the element names with an actual unique index
	$new_div.find("input, select, textarea").each(
		function() {
			// Add in a 'b' at the end of the name to reduce the
			// chance of name collision with another field
			if (this.name) {
				const old_name = this.name.replace(/\[num\]/g, '');
				$(this).attr('origName', old_name);
				this.name = this.name.replace(/\[num\]/g, '[' + num_elements + 'b]');
			}

			// Do the same thing with "feeds to map", which also
			// needs to be modified for each instance.
			const feedsToMap = $(this).attr('data-feeds-to-map');
			if ( feedsToMap !== undefined && feedsToMap !== false ) {
				$(this).attr('data-feeds-to-map', feedsToMap.replace(/\[num\]/g, '[' + num_elements + 'b]') );
			}

			if (this.id) {

				const old_id = this.id;

				this.id = this.id.replace(/input_/g, 'input_' + num_elements + '_');

				// TODO: Data in wgPageFormsShowOnSelect should probably be stored in
				// $("#pfForm").data('PageForms')
				if ( wgPageFormsShowOnSelect[ old_id ] ) {
					wgPageFormsShowOnSelect[ this.id ] = wgPageFormsShowOnSelect[ old_id ];
				}

				// register initialization and validation methods for new inputs

				const pfdata = $("#pfForm").data('PageForms');
				if ( pfdata ) { // found data object?
					let i;
					if ( pfdata.initFunctions[old_id] ) {

						// For every initialization method for
						// input with id old_id, register the
						// method for the new input.
						for ( i = 0; i < pfdata.initFunctions[old_id].length; i++ ) {

							$(this).PageForms_registerInputInit(
								pfdata.initFunctions[old_id][i].initFunction,
								pfdata.initFunctions[old_id][i].parameters,
								true //do not yet execute
							);
						}
					}

					// For every validation method for the
					// input with ID old_id, register it
					// for the new input.
					for ( i = 0; i < pfdata.validationFunctions.length; i++ ) {

						if ( typeof pfdata.validationFunctions[i] !== 'undefined' &&
							pfdata.validationFunctions[i].input === old_id ) {

							$(this).PageForms_registerInputValidation(
								pfdata.validationFunctions[i].valfunction,
								pfdata.validationFunctions[i].parameters
							);
						}
					}
				}
			}
		}
	);

	// datepicker and datetimepicker inputs require special handling.
	$new_div.find("div.pfPicker").attr('data-ooui', function() {
		return $(this).attr('data-ooui').replace(/\[num\]/g, '[' + num_elements + 'b]');
	});

	$new_div.find('a').attr('href', function() {
		// Make sure not to add a valid "href" attribute to <a> tags that don't have it.
		if ( this.href == undefined || this.href == false ) {
			return null;
		}
		return this.href.replace(/input_/g, 'input_' + num_elements + '_');
	});

	// Update the 'Upload file' link's data attribute to point to the new input ID.
	$new_div.find( '.ext-pageforms-uploadable' ).attr(
		'data-input-id',
		( index, attr ) => attr.replace( /input_/g, 'input_' + num_elements + '_' )
	);

	$new_div.find('span').attr('id', function() {
		return this.id.replace(/span_/g, 'span_' + num_elements + '_');
	});

	// Add the new instance.
	if ( addAboveCurInstance ) {
		$new_div.insertBefore(this.closest(".multipleTemplateInstance"))
			.hide().fadeIn();
	} else {
		this.closest(".multipleTemplateWrapper")
			.find(".multipleTemplateList")
			.append($new_div.hide().fadeIn());
	}

	$new_div.initializeJSElements(true);

	// Initialize new inputs.
	$new_div.find("input, select, textarea").each( function() {
		if ( ! this.id ) {
			return;
		}

		const pfdata = $("#pfForm").data('PageForms');
		if ( ! pfdata ) {
			return;
		}

		// have to store data array: the id attribute
		// of 'this' might be changed in the init function
		const thatData = pfdata.initFunctions[this.id] ;
		if ( !thatData ) {
			return;
		}

		// Call every initialization method for this input.
		for ( let i = 0; i < thatData.length; i++ ) {
			let initFunction = thatData[i].initFunction;
			if ( initFunction === undefined ) {
				continue;
			}
			// If the code attempted to store this function before
			// it was defined, only its name was stored. In that
			// case, get the function now.
			// @TODO - move getFunctionFromName() so that it can be
			// called from here, which would be better than
			// window[].
			if ( typeof initFunction === 'string' ) {
				initFunction = window[initFunction];
			}
			initFunction(
				this.id,
				thatData[i].parameters
			);
		}
	});

	// Hook that fires each time a new template instance is added.
	// The first parameter is a jQuery selection of the newly created instance div.
	mw.hook('pf.addTemplateInstance').fire($new_div);
};

// The first argument is needed, even though it's an attribute of the element
// on which this function is called, because it's the 'name' attribute for
// regular inputs, and the 'origName' attribute for inputs in multiple-instance
// templates.
$.fn.setDependentAutocompletion = function( dependentField, baseField, baseValue ) {
	// Get data from either Cargo or Semantic MediaWiki.
	let myServer = mw.config.get( 'wgScriptPath' ) + "/api.php",
		wgPageFormsCargoFields = mw.config.get( 'wgPageFormsCargoFields' ),
		wgPageFormsFieldProperties = mw.config.get( 'wgPageFormsFieldProperties' );
	myServer += "?action=pfautocomplete&format=json";
	if ( wgPageFormsCargoFields.hasOwnProperty( dependentField ) ) {
		const cargoTableAndFieldStr = wgPageFormsCargoFields[dependentField];
		const cargoTableAndField = cargoTableAndFieldStr.split('|');
		const cargoTable = cargoTableAndField[0];
		const cargoField = cargoTableAndField[1];
		const baseCargoTableAndFieldStr = wgPageFormsCargoFields[baseField];
		const baseCargoTableAndField = baseCargoTableAndFieldStr.split('|');
		const baseCargoTable = baseCargoTableAndField[0];
		const baseCargoField = baseCargoTableAndField[1];
		myServer += "&cargo_table=" + cargoTable + "&cargo_field=" + cargoField + "&is_array=true" + "&base_cargo_table=" + baseCargoTable + "&base_cargo_field=" + baseCargoField + "&basevalue=" + baseValue;
	} else {
		const propName = wgPageFormsFieldProperties[dependentField];
		const baseProp = wgPageFormsFieldProperties[baseField];
		myServer += "&property=" + propName + "&baseprop=" + baseProp + "&basevalue=" + baseValue;
	}
	const dependentValues = [];
	const $thisInput = $(this);
	// We use $.ajax() here instead of $.getJSON() so that the
	// 'async' parameter can be set. That, in turn, is set because
	// if the 2nd, "dependent" field is a combo box, it can have weird
	// behavior: clicking on the down arrow for the combo box leads to a
	// "blur" event for the base field, which causes the possible
	// values to get recalculated, but not in time for the dropdown to
	// change values - it still shows the old values. By setting
	// "async: false", we guarantee that old values won't be shown - if
	// the values haven't been recalculated yet, the dropdown won't
	// appear at all.
	// @TODO - handle this the right way, by having special behavior for
	// the dropdown - it should get delayed until the values are
	// calculated, then appear.
	$.ajax({
		url: myServer,
		dataType: 'json',
		async: false,
		success: function(data) {
			const realData = data.pfautocomplete;
			$.each(realData, (key, val) => {
				dependentValues.push(val.title);
			});
			$thisInput.data('autocompletevalues', dependentValues);
		}
	});
};

/**
 * Called on a 'base' field (e.g., for a country) - sets the autocompletion
 * for its 'dependent' field (e.g., for a city).
 *
 * @param {Mixed} partOfMultiple
 * @return {Mixed}
 */
$.fn.setAutocompleteForDependentField = function( partOfMultiple ) {
	const curValue = $(this).val();
	if ( curValue === null ) {
		return this;
	}

	const nameAttr = partOfMultiple ? 'origName' : 'name';
	const name = $(this).attr(nameAttr);
	const wgPageFormsDependentFields = mw.config.get( 'wgPageFormsDependentFields' );
	let dependent_on_me = [];
	for ( let i = 0; i < wgPageFormsDependentFields.length; i++ ) {
		const dependentFieldPair = wgPageFormsDependentFields[i];
		if ( dependentFieldPair[0] === name ) {
			dependent_on_me.push(dependentFieldPair[1]);
		}
	}
	dependent_on_me = $.uniqueSort(dependent_on_me);

	const self = this;
	$.each( dependent_on_me, function() {
		let $element, cmbox, tokens,
			dependentField = this;

		if ( partOfMultiple ) {
			$element = $( self ).closest( '.multipleTemplateInstance' )
				.find('[origName="' + dependentField + '"]');
		} else {
			$element = $('[name="' + dependentField + '"]');
		}

		if ( $element.hasClass( 'pfTokens' ) ) {
			tokens = new pf.select2.tokens();
			tokens.refresh($element);
		} else {
			$element.setDependentAutocompletion(dependentField, name, curValue);
		}
	});

	return this;
};

/**
 * Initialize all the JS-using elements contained within this block - can be
 * called for either the entire HTML body, or for a div representing an
 * instance of a multiple-instance template.
 *
 * @param {Mixed} partOfMultiple
 */
$.fn.initializeJSElements = function( partOfMultiple ) {
	this.find(".pfShowIfSelected").each( function() {
		// Avoid duplicate calls on any one element.
		if ( !partOfMultiple && $(this).parents('.multipleTemplateWrapper').length > 0 ) {
			return;
		}

		// Don't call this for combobox inputs, except when a new
		// multiple-instance template instance is created - in all
		// other cases, their "show on select" is triggered separately.
		if ( $(this).attr( 'data-input-type' ) == 'combobox' ) {
			if ( partOfMultiple ) {
				$(this).showIfSelected(true, true)
			}
			return;
		}

		$(this)
		.showIfSelected(partOfMultiple, true)
		.change( function() {
			$(this).showIfSelected(partOfMultiple, false);
		});
	});

	this.find(".pfShowIfChecked").each( function() {
		// Avoid duplicate calls on any one element.
		if ( !partOfMultiple && $(this).parents('.multipleTemplateWrapper').length > 0 ) {
			return;
		}
		$(this)
		.showIfChecked(partOfMultiple, true)
		.click( function() {
			$(this).showIfChecked(partOfMultiple, false);
		});
	});

	this.find(".pfShowIfCheckedCheckbox").each( function() {
		// Avoid duplicate calls on any one element.
		if ( !partOfMultiple && $(this).parents('.multipleTemplateWrapper').length > 0 ) {
			return;
		}
		$(this)
		.showIfCheckedCheckbox(partOfMultiple, true)
		.click( function() {
			$(this).showIfCheckedCheckbox(partOfMultiple, false);
		});
	});

	if ( partOfMultiple ) {
		// Enable the new remove button
		this.find(".removeButton").click( function() {

			// Unregister initialization and validation for deleted inputs
			$(this).parentsUntil( '.multipleTemplateInstance' ).last().parent().find("input, select, textarea").each( function() {
				$(this).PageForms_unregisterInputInit();
				$(this).PageForms_unregisterInputValidation();
			});

			// Remove the encompassing div for this instance.
			$(this).closest(".multipleTemplateInstance")
			.fadeTo('fast', 0, function() {
				$(this).slideUp('fast', function() {
					$(this).remove();
				});
			});
			return false;
		});

		// ...and the new adder
		this.find('.addAboveButton').click( function() {
			$(this).addInstance( true );
			return false; // needed to disable <a> behavior
		});
	}

	this.find('.pfComboBox').not('.multipleTemplateStarter .pfComboBox').each(function(){
		const min_width = $(this).data('size');
		const input_width = $(this).val().length*11;
		const inputType = new pf.ComboBoxInput({});
		inputType.apply($(this));
		inputType.$element.css("width", input_width > min_width ? input_width : min_width);
		inputType.$element.css("min-width", min_width);
		inputType.$element.find("a").css("margin-left", "-1px");
		$(this).after(inputType.$element);
		$(this).remove()
	});

	const tokens = new pf.select2.tokens();
	this.find('.pfTokens').not('.multipleTemplateStarter .pfTokens, .select2-container').each( function() {
		tokens.apply($(this));
	});

	// Set the end date input to the value selected in start date
	this.find("span.startDateInput").not(".hiddenByPF").find("input").last().blur( () => {
		const endInput = $(this).find("span.endDateInput").not(".hiddenByPF");
		const endYearInput = endInput.find(".yearInput");
		const endMonthInput = endInput.find(".monthInput");
		const endDayInput = endInput.find(".dayInput");

		// Update end date value only if it is not set
		if (endYearInput.val() == '' && endMonthInput.val() == '' && endDayInput.val() == ''){
			const startInput = $(this);
			const startYearVal = startInput.find(".yearInput").val();
			const startMonthVal = startInput.find(".monthInput").val();
			const startDayVal = startInput.find(".dayInput").val();

			endYearInput.val(startYearVal);
			endMonthInput.val(startMonthVal);
			endDayInput.val(startDayVal);
		}
	});

	if ( partOfMultiple ) {
		this.find('.autoGrow').autoGrow();
		this.find(".pfRating").each( function() {
			$(this).applyRatingInput();
		});
		this.find(".pfTreeInput").each( function() {
			$(this).applyJSTree();
		});
		this.find('.pfDatePicker').applyDatePicker();
		this.find('.pfDateTimePicker').applyDateTimePicker();
		this.find('a.popupformlink').click(function(evt){
			return ext.popupform.handlePopupFormLink( this.getAttribute('href'), this );
		});
		// Only defined if $wgPageFormsSimpleUpload == true.
		if ( typeof this.initializeSimpleUpload === 'function' ) {
			this.find(".simpleUploadInterface").each( function() {
				$(this).initializeSimpleUpload();
			});
		}

		// Also add support in new template instances to any non-Page
		// Forms classes that require special JS handling.
		this.find('.mw-collapsible').makeCollapsible();
	} else {
		this.find('.autoGrow').not('.multipleTemplateWrapper .autoGrow').autoGrow();
		this.find(".pfRating").not(".multipleTemplateWrapper .pfRating").each( function() {
			$(this).applyRatingInput();
		});
		this.find(".pfTreeInput").not(".multipleTemplateWrapper .pfTreeInput").each( function() {
			$(this).applyJSTree();
		});
		this.find('.pfDatePicker').not(".multipleTemplateWrapper .pfDatePicker").applyDatePicker();
		this.find('.pfDateTimePicker').not(".multipleTemplateWrapper .pfDateTimePicker").applyDateTimePicker();
		// Only defined if $wgPageFormsSimpleUpload == true.
		if ( typeof this.initializeSimpleUpload === 'function' ) {
			this.find(".simpleUploadInterface").not(".multipleTemplateWrapper .simpleUploadInterface").each( function() {
				$(this).initializeSimpleUpload();
			});
		}
	}

	// @TODO - this should ideally be called only for inputs that have
	// a dependent field - which might involve changing the storage of
	// "dependent fields" information from a global variable to a
	// per-input HTML attribute.
	this.find('input, select').each( function() {
		$(this)
		.setAutocompleteForDependentField( partOfMultiple )
		.blur( function() {
			$(this).setAutocompleteForDependentField( partOfMultiple );
		});
	});
	// The 'blur' event doesn't get triggered for radio buttons for
	// Chrome and Safari (the WebKit-based browsers) so use the 'change'
	// event in addition.
	// @TODO - blur() shuldn't be called at all for radio buttons.
	this.find('input:radio')
		.change( function() {
			$(this).setAutocompleteForDependentField( partOfMultiple );
		});

	this.find('.new-uuid').each( function() {
		$(this).val(window.pfGenerateUUID());
	});

	this.find('[data-tooltip]').not('.multipleTemplateStarter [data-tooltip]').each( function() {
		// Even if it's within a <th>, display the text unbolded.
		const tooltipText = '<p style="font-weight: normal;">' + $(this).attr('data-tooltip') + '</p>';
		const tooltip = new OO.ui.PopupButtonWidget( {
			icon: 'info',
			framed: false,
			popup: {
				padded: true,
				$content: $(tooltipText)
			}
		} );
		$(this).append( tooltip.$element )
	});

	const $myThis = this;
	if ( $.fn.applyVisualEditor ) {
		if ( partOfMultiple ) {
			$myThis.find(".visualeditor").applyVisualEditor();
		} else {
			$myThis.find(".visualeditor").not(".multipleTemplateWrapper .visualeditor").applyVisualEditor();
		}
	} else {
		$(document).on('VEForAllLoaded', (e) => {
			if ( partOfMultiple ) {
				$myThis.find(".visualeditor").applyVisualEditor();
			} else {
				$myThis.find(".visualeditor").not(".multipleTemplateWrapper .visualeditor").applyVisualEditor();
			}
		});
	}

	// @TODO - this should be in the TinyMCE extension, and use a hook.
	if ( typeof( mwTinyMCEInit ) === 'function' ) {
		if ( partOfMultiple ) {
			$myThis.find(".tinymce").each( function() {
				mwTinyMCEInit( '#' + $(this).attr('id') );
			});
		} else {
			$myThis.find(".tinymce").not(".multipleTemplateWrapper .tinymce").each( function() {
				mwTinyMCEInit( '#' + $(this).attr('id') );
			});
		}
	} else {
		$(document).on('TinyMCELoaded', (e) => {
			if ( partOfMultiple ) {
				$myThis.find(".tinymce").each( function() {
					mwTinyMCEInit( '#' + $(this).attr('id') );
				});
			} else {
				$myThis.find(".tinymce").not(".multipleTemplateWrapper .tinymce").each( function() {
					mwTinyMCEInit( '#' + $(this).attr('id') );
				});
			}
		});
	}

};

// Copied from https://stackoverflow.com/a/8809472
// License: public domain/MIT
window.pfGenerateUUID = function() {
	let d = Date.now();
	let d2 = (performance && performance.now && (performance.now() * 1000)) || 0; // Time in microseconds since page-load or 0 if unsupported
	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
		let r = Math.random() * 16; // random number between 0 and 16
		if (d > 0) { // Use timestamp until depleted
			r = (d + r) % 16 | 0; // eslint-disable-line no-bitwise
			d = Math.floor(d / 16);
		} else { // Use microseconds since page-load if supported
			r = (d2 + r) % 16 | 0; // eslint-disable-line no-bitwise
			d2 = Math.floor(d2 / 16);
		}
		return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16); // eslint-disable-line no-bitwise
	});
}

// Once the document has finished loading, set up everything!
$( () => {
	let i,
		inputID,
		validationFunctionData;

	function getFunctionFromName( functionName ) {
		let func = window;
		const namespaces = functionName.split( "." );
		for ( let nsNum = 0; nsNum < namespaces.length; nsNum++ ) {
			func = func[ namespaces[ nsNum ] ];
		}
		// If this gets called before the function is defined, just
		// store the function name instead, for later lookup.
		if ( func === null ) {
			return functionName;
		}
		return func;
	}

	// Exit now if a Page Forms form is not present.
	if ( $('#pfForm').length === 0 ) {
		return;
	}

	function minimizeInstances( minHeight ) {
		if ( minHeight >= 0) {
			$('.multipleTemplateList').each( function() {
				if ( $(this).height() > minHeight ) {
					$(this).addClass('minimizeAll');
					$(this).possiblyMinimizeAllOpenInstances();
				}
			});
		}
	}

	// jQuery's .ready() function is being called before the resource was actually loaded.
	// This is a workaround for https://phabricator.wikimedia.org/T216805.
	setTimeout( () => {
		// "Mask" to prevent users from clicking while form is still loading.
		$('#loadingMask').css({'width': $(document).width(),'height': $(document).height()});

		// register init functions
		const initFunctionData = mw.config.get( 'ext.pf.initFunctionData' );
		for ( inputID in initFunctionData ) {
			for ( i in initFunctionData[inputID] ) {
				/*jshint -W069 */
				$( '#' + inputID ).PageForms_registerInputInit( getFunctionFromName( initFunctionData[ inputID ][ i ][ 'name' ] ), initFunctionData[ inputID ][ i ][ 'param' ] );
				/*jshint +W069 */
			}
		}

		// register validation functions
		validationFunctionData = mw.config.get( 'ext.pf.validationFunctionData' );
		for ( inputID in validationFunctionData ) {
			for ( i in validationFunctionData[inputID] ) {
				/*jshint -W069 */
				$( '#' + inputID ).PageForms_registerInputValidation( getFunctionFromName( validationFunctionData[ inputID ][ i ][ 'name' ] ), validationFunctionData[ inputID ][ i ][ 'param' ] );
				/*jshint +W069 */
			}
		}

		$( 'body' ).initializeJSElements(false);

		$('.multipleTemplateInstance').each( function() {
			$(this).initializeJSElements(true);
		});
		$('.multipleTemplateAdder').click( function() {
			$(this).addInstance( false );
		});
		const wgPageFormsHeightForMinimizingInstances = mw.config.get( 'wgPageFormsHeightForMinimizingInstances' );
		minimizeInstances( wgPageFormsHeightForMinimizingInstances );

		$('.multipleTemplateList').each( function() {
			const $list = $(this);
			const sortable = Sortable.create($list[0], {
				handle: '.instanceRearranger',
				onStart: function(/**Event*/evt) {
					$list.possiblyMinimizeAllOpenInstances();
				}
			});
		});

		// If the Header Tabs extension is being used in this form, minimize all the
		// relevant instances any time the tab is changed.
		if ( $( "#headertabs" ).length ) {
			$( ".oo-ui-tabOptionWidget" ).on( 'click', ( event ) => {
				minimizeInstances( wgPageFormsHeightForMinimizingInstances );
			});
		}

		// If there are any "wizard screen" elements defined in the
		// form, turn the whole form into a wizard, with successive
		// screens for each element.
		const $wizardScreens = $('form#pfForm').find('div.pf-wizard-screen');
		if ( $wizardScreens.length > 0 ) {
			const $wizardNav = $('<div class="pf-wizard-navigation"></div>');
			$('form#pfForm').append( $wizardNav );
			$wizardScreens.displayWizardScreen( 1, $wizardNav );
		}

		// If the form is submitted, validate everything!
		$('#pfForm').submit( () => validateAll() );

		// We are all done - remove the loading spinner.
		$('.loadingImage').remove();
	}, 0 );

	mw.hook('pf.formSetupAfter').fire();
});

// If some part of the form is clicked, minimize any multiple-instance
// template instances that need minimizing, and move the "focus" to the current
// instance list, if one is being clicked and it's different from the
// previous one.
// We make only the form itself clickable, instead of the whole screen, to
// try to avoid a click on a popup, like the "Upload file" window, minimizing
// the current open instance.
$('form#pfForm').click( (e) => {
	const $target = $(e.target);
	// Ignore the "add instance" buttons - those get handling of their own.
	const clickedOnAddAnother = $target.parents('.multipleTemplateAdder').length > 0;
	if ( clickedOnAddAnother || $target.hasClass('addAboveButton') ) {
		return;
	}

	const $instance = $target.closest('.multipleTemplateInstance');
	if ( $instance === null ) {
		$('.multipleTemplateList.currentFocus')
			.removeClass('currentFocus')
			.possiblyMinimizeAllOpenInstances();
		return;
	}

	const $instancesList = $instance.closest('.multipleTemplateList');
	if ( !$instancesList.hasClass('currentFocus') ) {
		$('.multipleTemplateList.currentFocus')
			.removeClass('currentFocus')
			.possiblyMinimizeAllOpenInstances();
		if ( $instancesList.hasClass('minimizeAll') ) {
			$instancesList.addClass('currentFocus');
		}
	}

	if ( $instance.hasClass('minimized') ) {
		$instancesList.possiblyMinimizeAllOpenInstances();
		$instance.removeClass('minimized');
		$instance.find('.fieldValuesDisplay').html('');
		$instance.find('.instanceMain').fadeIn();
		$instance.find('.fieldValuesDisplay').remove();
		// Remove unhelpful styling added by VisualEditor.
		$instance.find('div.oo-ui-toolbar-bar').css('left', null);
		$instance.find('div.oo-ui-toolbar-bar').css('right', null);
	}
});

$('#pf-expand-all a').click(( event ) => {
	event.preventDefault();

	// Page Forms minimized template instances.
	$('.minimized').each( function() {
		$(this).removeClass('minimized');
		$(this).find('.fieldValuesDisplay').html('');
		$(this).find('.instanceMain').fadeIn();
		$(this).find('.fieldValuesDisplay').remove();
		// Remove unhelpful styling added by VisualEditor.
		$(this).find('div.oo-ui-toolbar-bar').css('left', null);
		$(this).find('div.oo-ui-toolbar-bar').css('right', null);
	});

	// Standard MediaWiki "collapsible" sections.
	$('div.mw-collapsed a.mw-collapsible-text').click();
});

$('.pfSendBack').click( () => {
	window.history.back();
});

}( jQuery, mediaWiki ) );
