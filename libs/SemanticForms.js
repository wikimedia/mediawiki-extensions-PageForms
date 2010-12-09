/**
 * SemanticForms.js
 *
 * Javascript utility functions for the Semantic Forms extension.
 *
 * @author Yaron Koren
 * @author Sanyam Goyal
 * @author Jeffrey Stuckman
 * @author Harold Solbrig
 * @author Eugene Mednikov
 */

jQuery.fn.sfAutocomplete = function(values, api_url, data_type, delimiter, data_source) {
    var myServer = api_url;
    jQuery.noConflict();

    /* extending jQuery functions for custom highlighting */
    jQuery.ui.autocomplete.prototype._renderItem = function( ul, item) {

	var re = new RegExp("(?![^&;]+;)(?!<[^<>]*)(" + this.term.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, "\\$1") + ")(?![^<>]*>)(?![^&;]+;)", "gi");
	var loc = item.label.search(re);
	if (loc >= 0) {
		var t = item.label.substr(0, loc) + '<strong>' + item.label.substr(loc, this.term.length) + '</strong>' + item.label.substr(loc + this.term.length);
	} else {
		var t = item.label;
	}
        return jQuery( "<li></li>" )
		.data( "item.autocomplete", item )
		.append( " <a>" + t + "</a>" )
		.appendTo( ul );
	};

	// Modify the delimiter. If it's "\n", change it to an actual
	// newline - otherwise, add a space to the end.
	// This doesn't cover the case of a delimiter that's a newline
	// plus something else, like ".\n" or "\n\n", but as far as we
	// know no one has yet needed that.
	if ( delimiter != null ) {
	   if ( delimiter == "\\n" ) {
		delimiter = "\n";
	    } else {
		delimiter += " ";
	    }
	}

	/* extending jquery functions  */
	jQuery.extend( jQuery.ui.autocomplete, {	
	    filter: function(array, term) {
		if ( autocompleteOnAllChars ) {
			var matcher = new RegExp(jQuery.ui.autocomplete.escapeRegex(term), "i" );
		} else {
			var matcher = new RegExp("\\b" + jQuery.ui.autocomplete.escapeRegex(term), "i" );
		}
		return jQuery.grep( array, function(value) {
			return matcher.test( value.label || value.value || value );
		});
	    }
	});

    if (values != null) {
        // Local autocompletion
            
	if (delimiter != null) {
		// Autocomplete for multiple values

                function split(val) {
                    return val.split(delimiter);
                }
		function extractLast(term) {
			return split(term).pop();
		}

		jQuery(this).autocomplete({
			minLength: 0,
			source: function(request, response) {

				response(jQuery.ui.autocomplete.filter(values, extractLast(request.term)));
			},
			focus: function() {
				// prevent value inserted on focus
				return false;
			},
			select: function(event, ui) {
				var terms = split( this.value );
				// remove the current input
				terms.pop();
				// add the selected item
				terms.push( ui.item.value );
				// add placeholder to get the comma-and-space at the end
				terms.push("");
				this.value = terms.join(delimiter);
				return false;
			}
		});              
            
        } else {
		// Autocomplete for a single value
                jQuery(this).autocomplete({
			source:values
		});
        }
    } else {
	// Remote autocompletion
        if (data_type == 'property')
            myServer += "?action=sfautocomplete&format=json&property=" + data_source;
        else if (data_type == 'relation')
            myServer += "?action=sfautocomplete&format=json&relation=" + data_source;
        else if (data_type == 'attribute')
            myServer += "?action=sfautocomplete&format=json&attribute=" + data_source;
        else if (data_type == 'category')
            myServer += "?action=sfautocomplete&format=json&category=" + data_source;
        else if (data_type == 'namespace')
            myServer += "?action=sfautocomplete&format=json&namespace=" + data_source;
        else if (data_type == 'external_url')
            myServer += "?action=sfautocomplete&format=json&external_url=" + data_source;                   
       
       if (delimiter != null) {
            
                function split(val) {
			return val.split(delimiter);
		}
		function extractLast(term) {
			return split(term).pop();
		}
		jQuery(this).autocomplete({
			source: function(request, response) {
				jQuery.getJSON(myServer, {
					substr: extractLast(request.term)
				}, function( data ) {
					response(jQuery.map(data.sfautocomplete, function(item) {
						return {
							value: item.title
						}
					}))
				});
			},
			search: function() {
				// custom minLength
				var term = extractLast(this.value);
				if (term.length < 1) {
					return false;
				}
			},
			focus: function() {
				// prevent value inserted on focus
				return false;
			},
			select: function(event, ui) {
				var terms = split( this.value );
				// remove the current input
				terms.pop();
				// add the selected item
				terms.push( ui.item.value );
				// add placeholder to get the comma-and-space at the end
				terms.push("");
				this.value = terms.join(delimiter);
				return false;
			}
		} );
        } else {
		jQuery(this).autocomplete({
			minLength: 1,
			source: function(request, response) {
				jQuery.ajax({
					url: myServer,
					dataType: "json",
					data: { 
                                            substr:request.term
                                        },
					success: function( data ) {
						response(jQuery.map(data.sfautocomplete, function(item) {
							return {
								value: item.title
							}
						}))
					}                                         
				});
			},
			open: function() {
				jQuery(this).removeClass("ui-corner-all").addClass("ui-corner-top");
			},
			close: function() {
				jQuery(this).removeClass("ui-corner-top").addClass("ui-corner-all");
			}
		} );
	}
    }
};

/*
 * Functions for handling 'show on select'
 */

// Display a div that would otherwise be hidden by "show on select".
function showDiv(div_id) {
	jQuery('#' + div_id).find(".hiddenBySF").removeClass('hiddenBySF');
	jQuery('#' + div_id).show();
}

// Hide a div due to "show on select". The CSS class is there so that SF can
// ignore the div's contents when the form is submitted.
function hideDiv(div_id) {
	jQuery('#' + div_id).find("span, div").addClass('hiddenBySF');
	jQuery('#' + div_id).hide();
}

// Show this div if the current value is any of the relevant options -
// otherwise, hide it.
function showDivIfSelected(options, div_id, inputVal) {
	for (var j in options) {
		// If it's a listbox and the user has selected more than one
		// value, it'll be an array - handle either case.
		if ((jQuery.isArray(inputVal) && jQuery.inArray(options[j], inputVal) >= 0) ||
		    (!jQuery.isArray(inputVal) && (inputVal == options[j]))) {
			showDiv(div_id);
			return;
		}
	}
	hideDiv(div_id);
}

// Used for handling 'show on select' for the 'dropdown' and 'listbox' inputs.
jQuery.fn.showIfSelected = function() {
	var inputVal = this.val();
	var showOnSelectVals = sfgShowOnSelect[this.attr("id")];
	for (i in showOnSelectVals) {
		var options = showOnSelectVals[i][0];
		var div_id = showOnSelectVals[i][1];
		showDivIfSelected(options, div_id, inputVal);
	}
}

// Show this div if any of the relevant selections are checked -
// otherwise, hide it.
jQuery.fn.showDivIfChecked = function(options, div_id) {
	for (var i in options) {
		if (jQuery(this).find('[value="' + options[i] + '"]').is(":checked")) {
			showDiv(div_id);
			return;
		}
	}
	hideDiv(div_id);
}

// Used for handling 'show on select' for the 'checkboxes' and 'radiobutton'
// inputs.
jQuery.fn.showIfChecked = function() {
	var showOnSelectVals = sfgShowOnSelect[this.attr("id")];
	for (i in showOnSelectVals) {
		var options = showOnSelectVals[i][0];
		var div_id = showOnSelectVals[i][1];
		this.showDivIfChecked(options, div_id);
	}
}

// Used for handling 'show on select' for the 'checkbox' input.
jQuery.fn.showIfCheckedCheckbox = function() {
	var div_id = sfgShowOnSelect[this.attr("id")];
	if (jQuery(this).is(":checked")) {
		showDiv(div_id);
	} else {
		hideDiv(div_id);
	}
}

/*
 * Validation functions
 */

// Display an error message on the end of an input.
jQuery.fn.addErrorMessage = function(msg) {
	this.append(' <span class="errorMessage">' + msg + '</span>');
}

jQuery.fn.validateMandatoryField = function() {
	var fieldVal = this.find(".mandatoryField").val();
	if (fieldVal == null) {
		var isEmpty = true;
	} else if (jQuery.isArray(fieldVal)) {
		var isEmpty = (fieldVal.length == 0);
	} else {
		var isEmpty = (fieldVal.replace(/\s+/, '') == '');
	}
	if (isEmpty) {
		this.addErrorMessage(sfgBlankErrorStr);
		return false;
	} else {
		return true;
	}
}

jQuery.fn.validateMandatoryComboBox = function() {
	if (this.find("input").val() == '') {
		this.addErrorMessage(sfgBlankErrorStr);
		return false;
	} else {
		return true;
	}
}

jQuery.fn.validateMandatoryDateField = function() {
	if (this.find(".dayInput").val() == '' ||
	    this.find(".monthInput").val() == '' ||
	    this.find(".yearInput").val() == '') {
		this.addErrorMessage(sfgBlankErrorStr);
		return false;
	} else {
		return true;
	}
}

// Special handling for radiobuttons, because what's being checked
// is the first radiobutton, which has an empty value.
jQuery.fn.validateMandatoryRadioButton = function() {
	if (this.find("[value='']").is(':checked')) {
		this.addErrorMessage(sfgBlankErrorStr);
		return false;
	} else {
		return true;
	}
}

jQuery.fn.validateMandatoryCheckboxes = function() {
	// Get the number of checked checkboxes within this span - must
	// be at least one.
	var numChecked = this.find("input:checked").size();
	if (numChecked == 0) {
		this.addErrorMessage(sfgBlankErrorStr);
		return false;
	} else {
		return true;
	}
}

/*
 * Type-based validation
 */

jQuery.fn.validateURLField = function() {
	var fieldVal = this.find("input").val();
	// code borrowed from http://snippets.dzone.com/posts/show/452
	var url_regexp = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
	if (fieldVal == "" || url_regexp.test(fieldVal)) {
		return true;
	} else {
		this.addErrorMessage(sfgBadURLErrorStr);
		return false;
	}
}

jQuery.fn.validateEmailField = function() {
	var fieldVal = this.find("input").val();
	// code borrowed from http://javascript.internet.com/forms/email-validation---basic.html
	var email_regexp = /^\s*\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,6})+\s*$/;
	if (fieldVal == '' || email_regexp.test(fieldVal)) {
		return true;
	} else {
		this.addErrorMessage(sfgBadEmailErrorStr);
		return false;
	}
}

jQuery.fn.validateNumberField = function() {
	var fieldVal = this.find("input").val();
	if (fieldVal == '' || fieldVal.match(/^\s*\-?[\d\.,]+\s*$/)) {
		return true;
	} else {
		this.addErrorMessage(sfgBadNumberErrorStr);
		return false;
	}
}

jQuery.fn.validateDateField = function() {
	// validate only if day and year fields are both filled in
	var dayVal = this.find(".dayInput").val();
	var yearVal = this.find(".yearInput").val();
	if (dayVal == '' || yearVal == '') {
		return true;
	} else if (dayVal.match(/^\d+$/) && dayVal <= 31) {
		// no year validation, since it can also include
		// 'BC' and possibly other non-number strings
		return true;
	} else {
		this.addErrorMessage(sfgBadDateErrorStr);
		return false;
	}
}

// If the form is submitted, validate everything!
jQuery('#sfForm').submit( function() { return validateAll(); } );

function validateAll() {
	var num_errors = 0;

	// Remove all old error messages.
	jQuery(".errorMessage").remove();

	// Make sure all inputs are ignored in the "starter" instance
	// of any multiple-instance template.
	jQuery(".multipleTemplateStarter").find("span, div").addClass("hiddenBySF");

	jQuery("span.inputSpan.mandatoryFieldSpan").not(".hiddenBySF").each( function() {
		if (! jQuery(this).validateMandatoryField() ) num_errors += 1;
	});
	jQuery("div.ui-widget.mandatory").not(".hiddenBySF").each( function() {
		if (! jQuery(this).validateMandatoryComboBox() ) num_errors += 1;
	});
	jQuery("span.dateInput.mandatoryFieldSpan").not(".hiddenBySF").each( function() {
		if (! jQuery(this).validateMandatoryDateField() ) num_errors += 1;
	});
	jQuery("span.radioButtonSpan.mandatoryFieldSpan").not(".hiddenBySF").each( function() {
		if (! jQuery(this).validateMandatoryRadioButton() ) num_errors += 1;
	});
	jQuery("span.checkboxesSpan.mandatoryFieldSpan").not(".hiddenBySF").each( function() {
		if (! jQuery(this).validateMandatoryCheckboxes() ) num_errors += 1;
	});
	jQuery("span.URLInput").not(".hiddenBySF").each( function() {
		if (! jQuery(this).validateURLField() ) num_errors += 1;
	});
	jQuery("span.emailInput").not(".hiddenBySF").each( function() {
		if (! jQuery(this).validateEmailField() ) num_errors += 1;
	});
	jQuery("span.numberInput").not(".hiddenBySF").each( function() {
		if (! jQuery(this).validateNumberField() ) num_errors += 1;
	});
	jQuery("span.dateInput").not(".hiddenBySF").each( function() {
		if (! jQuery(this).validateDateField() ) num_errors += 1;
	});

	if (num_errors > 0) {
		// add error header, if it's not there already
		if (jQuery("#form_error_header").size() == 0) {
			jQuery("#contentSub").append('<div id="form_error_header" class="warningMessage" style="font-size: medium">' + sfgFormErrorsHeader + '</div>');
		}
		scroll(0, 0);
	} else {
		// Disable inputs hidden due to either "show on select" or
		// because they're part of the "starter" div for
		// multiple-instance templates, so that they aren't
		// submitted by the form.
		jQuery('.hiddenBySF').find("input, select, textarea").attr('disabled', 'disabled');
	}
	return (num_errors == 0);
}

function addInstanceEventHandler(templateName, fieldNum) {
	return function() {
		addInstance('starter_' + templateName, 'main_' + templateName, fieldNum);
	}
}

function addInstance(starter_div_id, main_div_id, tab_index) {
	num_elements++;
	
	// Create the new instance
	var new_div = jQuery('#' + starter_div_id).clone()
		.removeClass('multipleTemplateStarter')
		.addClass('multipleTemplate')
		.removeAttr("id")
		.css("display", "block");
	
	// Make internal ID unique for the relevant divs and spans, and replace
	// the [num] index in the element names with an actual unique index
	new_div.find("input, select, textarea").each(
		function() {
			if (this.name)
				this.name = this.name.replace(/\[num\]/g, '[' + num_elements + ']');
			if (this.id)
				this.id = this.id.replace(/input_/g, 'input_' + num_elements + '_')
		}
	);
	new_div.find('a').attr('href', function() {
		return this.href.replace(/input_/g, 'input_' + num_elements + '_');
	});
	new_div.find('span').attr('id', function() {
		return this.id.replace(/span_/g, 'span_' + num_elements + '_');
	});

	// Create remove button
	var removeButton = jQuery("<input>").attr({
		type: 'button',
		class: 'remover',
		value: sfgRemoveText,
		tabIndex: tab_index,
	});
	new_div.append(removeButton);
	
	// Add the new instance
	jQuery('#' + main_div_id).append(new_div);

	// Enable the new remover
	new_div.find('.remover').click( function() {
		jQuery(this).parent().remove();
	});

	// Enable autocompletion
	//alert(new_div.find('.autocompleteInput').size());
	new_div.find('.autocompleteInput').attachAutocomplete();

	// Apply the relevant Javascript call for all FancyBox, combobox
	// and autogrow instances in this div.
	new_div.find('.sfFancyBox').fancybox({
			'width'         : '75%',
			'height'        : '75%',
			'autoScale'     : false,
			'transitionIn'  : 'none',
			'transitionOut' : 'none',
			'type'          : 'iframe',
			'overlayColor'  : '#222',
			'overlayOpacity' : '0.8'
	});
	// Somewhat of a hack - remove the divs that the combobox() call
	// adds on, so that we can just call combobox() again without
	// duplicating anything. There's probably a nicer way to do this,
	// that doesn't involve removing and then recreating divs.
	new_div.find('.sfComboBoxActual').remove();
	new_div.find('.sfComboBox').combobox();

	// Handle AutoGrow as well.
	new_div.find('.autoGrow').autoGrow();
}

// Activate autocomplete functionality for the specified field
jQuery.fn.attachAutocomplete = function() {
	input_id = this.attr("id");
	// For some reason, the find() call that calls this function will
	// still call it even if no elements are found - if that happens,
	// escape here to avoid an error.
	if (input_id == null) return;
	// Extract the field ID number from the input field
	var field_num = parseInt(input_id.substring(input_id.lastIndexOf('_') + 1, input_id.length),10);
	// Add the autocomplete string, if a mapping exists.
	var field_string = sfgAutocompleteMappings[field_num];
	if (field_string) {
		var field_values = field_string.split(',');
		var delimiter = null;
		var data_source = field_values[0];
		if (field_values[1] == 'list') {
			delimiter = ",";
			if (field_values[2] != null) {
				delimiter = field_values[2];
			}
		}
		if (sfgAutocompleteValues[field_string] != null) {
			this.sfAutocomplete(sfgAutocompleteValues[field_string], null, null, delimiter, data_source);
		} else {
			this.sfAutocomplete(null, wgScriptPath + "/api.php", sfgAutocompleteDataTypes[field_string], delimiter, data_source);
		}
	}
}

var num_elements = 0;

// Once the document has finished loading, set up everything!
jQuery(document).ready(function() {
	jQuery(".sfShowIfSelected").each( function() {
		jQuery(this).showIfSelected();
		jQuery(this).change( function() {
			jQuery(this).showIfSelected();
		});
	});
	
	jQuery(".sfShowIfChecked").each( function() {
		jQuery(this).showIfChecked();
		jQuery(this).change( function() {
			jQuery(this).showIfChecked();
		});
	});
	
	jQuery(".sfShowIfCheckedCheckbox").each( function() {
		jQuery(this).showIfCheckedCheckbox();
		jQuery(this).change( function() {
			jQuery(this).showIfCheckedCheckbox();
		});
	});

	jQuery(".remover").click( function() {
		jQuery(this).parent().remove();
	});
	jQuery(".autocompleteInput").attachAutocomplete();
	jQuery(".sfComboBox").combobox();
	jQuery(".autoGrow").autoGrow();
	jQuery(".sfFancyBox").fancybox({
		'width'         : '75%',
		'height'        : '75%',
		'autoScale'     : false,
		'transitionIn'  : 'none',
		'transitionOut' : 'none',
		'type'          : 'iframe',
		'overlayColor'  : '#222',
		'overlayOpacity' : '0.8'
	});

	// Could this be done via classes and attributes, instead of a
	// global variable?
	for (var i in sfgAdderButtons) {
		var components = sfgAdderButtons[i].split(',');
		adderID = components[0];
		templateName = components[1];
		fieldNum = components[2];
		jQuery('#' + adderID).click( addInstanceEventHandler(templateName, fieldNum) );
	}
});

/* extending jquery functions  */
    
(function(jQuery) {
     
	jQuery.widget("ui.combobox", {
		_create: function() {
			var self = this;
			var select = this.element.hide();
			var name= select[0].name;
			var id = select[0].id;
			var curval = select[0].options[0].value;
			var input = jQuery("<input id=\"" + id + "\" type=\"text\" name=\" " + name + " \" value=\"" + curval + "\">")
				.insertAfter(select)
				.attr("tabIndex", select.attr("tabIndex"))
				.autocomplete({
					source: function(request, response) {
						if ( autocompleteOnAllChars ) {
							var matcher = new RegExp(request.term, "i");
						} else {
							var matcher = new RegExp("\\b" + request.term, "i");
						}
						response(select.children("option").map(function() {
							var text = jQuery(this).text();
							if (this.value && (!request.term || matcher.test(text))) {
								return {
									id: this.value,
									label: text.replace(new RegExp("(?![^&;]+;)(?!<[^<>]*)(" + jQuery.ui.autocomplete.escapeRegex(request.term) + ")(?![^<>]*>)(?![^&;]+;)", "gi"), "<strong>$1</strong>"),
									value: text
								};
							}
						}));
					},
					delay: 0,
					change: function(event, ui) {
						if (!ui.item) {
							// remove invalid value, as it didn't match anything
							//jQuery(this).val("");
							return false;
						}
						select.val(ui.item.id);
						self._trigger("selected", event, {
							item: select.find("[value='" + ui.item.id + "']")
						});

					},
					minLength: 0
				})
			.addClass("ui-widget ui-widget-content ui-corner-left sfComboBoxActual");
		jQuery('<button type="button">&nbsp;</button>')
			.attr("tabIndex", -1)
			.attr("title", "Show All Items")
			.insertAfter(input)
			.button({
				icons: {
					primary: "ui-icon-triangle-1-s"
				},
				text: false
			}).removeClass("ui-corner-all")
			.addClass("ui-corner-right ui-button-icon sfComboBoxActual")
			.click(function() {
				// close if already visible
				if (input.autocomplete("widget").is(":visible")) {
					input.autocomplete("close");
					return;
				}
				// pass empty string as value to search for, displaying all results
				input.autocomplete("search", "");
				input.focus();
			});
		}
	});

})(jQuery);
