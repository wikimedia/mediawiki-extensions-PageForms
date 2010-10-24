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

function isEmpty(obj) {
    for(var i in obj) {
        return false;
    }
    return true;
}

function sf_autocomplete(input_name, container_name, values, api_url, data_type, delimiter, data_source) {
    var myServer = api_url;
    jQuery.noConflict();

/* extending jquery functions for custom highlighting */
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
            
   /* delimiter != '' means multiple autocomplete */

	if (delimiter != null) {
            jQuery(document).ready(function(){
                function split(val) {
                    return val.split(delimiter);
                }
		function extractLast(term) {
			return split(term).pop();
		}

		jQuery("#" + input_name).autocomplete({
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
            } );
            
        } else{
            jQuery(document).ready(function(){
                jQuery("#" + input_name).autocomplete({
			source:values
		});
            } ) ;
        }
    } else {
                     
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
            
            jQuery(document).ready(function(){
                function split(val) {
			return val.split(delimiter);
		}
		function extractLast(term) {
			return split(term).pop();
		}
		jQuery("#" + input_name).autocomplete({
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
	    });	

              
          } );
        } else {
		jQuery(document).ready(function(){
		jQuery("#" + input_name).autocomplete({
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
		});

            } );
        }
    }
};

/*
 * Functions for handling 'show on select'
 */

// show the relevant div if any one of the relevant options are passed in
// to the relevant dropdown - otherwise, hide it
function showIfSelected(input_id, options_array, div_id) {
	the_input = document.getElementById(input_id);
	the_div = document.getElementById(div_id);
        for (var i in options_array) {
		if (the_input.value == options_array[i]) {
			the_div.style.display = ''; // return to default
			return;
		}
	}
	the_div.style.display = 'none';
}

// Like showIfSelected(), but only for list boxes
function showIfSelectedInListBox(input_id, options_array, div_id) {
	the_input = document.getElementById(input_id);
	the_div = document.getElementById(div_id);
	// Show the div if any of the list box's selected options match
	// any of the options that point to this div
	// - we have to cycle through because the field "selectedOptions" is
	// apparently not supported on all browsers.
	for (var i = 0; i < the_input.options.length; i++) {
		if ( the_input.item(i).selected ) {
			cur_option = the_input.item(i).text;
    	   		for (var j in options_array) {
				if (cur_option == options_array[j]) {
					the_div.style.display = ''; // return to default
					return;
				}
			}
		}
	}
	the_div.style.display = 'none';
}

// show the relevant div if any one of the relevant checkboxes are
// checked - otherwise, hide it
function showIfChecked(checkbox_inputs, div_id) {
	the_div = document.getElementById(div_id);
        for (var i in checkbox_inputs) {
		checkbox = document.getElementById(checkbox_inputs[i]);
		if (checkbox != null && checkbox.checked) {
			the_div.style.display = ''; // return to default
			return;
		}
	}
	the_div.style.display = 'none';
}

// Evaluate an array of passed-in JS calls - this is a hack, but I can't
// think of a better solution
for (var i = 0; i < sfgShowOnSelectCalls.length; i++ ) {
	eval(sfgShowOnSelectCalls[i]);
}

function validate_mandatory_field(field_id, info_id) {
	var field = document.getElementById(field_id);
	// if there's nothing at that field ID, ignore it - it's probably
	// a hidden field
	if (field == null) {
		return true;
	}
	if (field.value.replace(/\s+/, '') == '') {
		var info_span = document.getElementById(info_id);
		if ( info_span == null ) {
			alert ("no info span found at " + info_id + "!");
		} else {
			info_span.innerHTML = sfgBlankErrorStr;
		}
		return false;
	} else {
		return true;
	}
}

// Special handling for radiobuttons, because what's being checked
// is the first radiobutton, which has value of "None"
function validate_mandatory_radiobutton(none_button_id, info_id) {
	none_button = document.getElementById(none_button_id);
	if (none_button && none_button.checked) {
		info_span = document.getElementById(info_id);
		info_span.innerHTML = sfgBlankErrorStr;
		return false;
	} else {
		return true;
	}
}

function validate_mandatory_combobox(field_id, info_id) {
	var field = jQuery('input#' + field_id);
	// if there's nothing at that field ID, ignore it - it's probably
	// a hidden field
	if (field == null) {
		return true;
	}
	// FIXME
	// field.val() unfortunately doesn't work in IE - it just returns
	// "undefined". For now, if that happens, just exit
	var value = field.val();
	if (value == undefined) {
		//alert(field.html());
		return true;
	}
	if (value.replace(/\s+/, '') == '') {
		var info_span = document.getElementById(info_id);
		info_span.innerHTML = sfgBlankErrorStr;
		return false;
	} else {
		return true;
	}
}

function validate_mandatory_checkboxes(field_id, info_id) {
	// get all checkboxes - the "field_id" in this case is the span
	// surrounding all the checkboxes
	var checkboxes = jQuery('span#' + field_id + " > span > input");
	var all_unchecked = true;
	for (var i = 0; i < checkboxes.length; i++) {
		if (checkboxes[i].checked) {
			all_unchecked = false;
		}
	}
	if (all_unchecked) {
		info_span = document.getElementById(info_id);
		info_span.innerHTML = sfgBlankErrorStr;
		return false;
	} else {
		return true;
	}
}

// validate a mandatory field that exists across multiple instances of
// a template - we have to find each one, matching on the pattern of its
// ID, and validate it
function validate_multiple_mandatory_fields(field_num) {
	var num_errors = 0;
	elems = document.getElementsByTagName("*");
	if ( ! elems) { elems = []; }
	var field_pattern = new RegExp('input_(.*)_' + field_num);
	for (var i = 0; i < elems.length; i++) {
		id = elems[i].id;
		if (matches = field_pattern.exec(id)) {
			instance_num = matches[1];
			var input_name = "input_" + instance_num + "_" + field_num;
			var info_name = "info_" + instance_num + "_" + field_num;
			if (! validate_mandatory_field(input_name, info_name)) {
				num_errors += 1;
			}
		}
	}
	return (num_errors == 0);
}

function validate_field_type(field_id, type, info_id) {
	field = document.getElementById(field_id);
	if (type != 'date' && field.value == '') {
		return true;
	} else {
		if (type == 'URL') {
			// code borrowed from http://snippets.dzone.com/posts/show/452
			var url_regexp = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
			if (url_regexp.test(field.value)) {
				return true;
			} else {
				info_span = document.getElementById(info_id);
				info_span.innerHTML = sfgBadURLErrorStr;
				return false;
			}
		} else if (type == 'email') {
			// code borrowed from http://javascript.internet.com/forms/email-validation---basic.html
			var email_regexp = /^\s*\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,6})+\s*$/;
			if (email_regexp.test(field.value)) {
				return true;
			} else {
				info_span = document.getElementById(info_id);
				info_span.innerHTML = sfgBadEmailErrorStr;
				return false;
			}
		} else if (type == 'number') {
			if (field.value.match(/^\s*\-?[\d\.,]+\s*$/)) {
				return true;
			} else {
				info_span = document.getElementById(info_id);
				info_span.innerHTML = sfgBadNumberErrorStr;
				return false;
			}
		} else if (type == 'date') {
			// validate only if day and year fields are both filled in
			day_field = document.getElementById(field_id + "_day");
			year_field = document.getElementById(field_id + "_year");
			if (day_field.value == '' || year_field.value == '') {
				return true;
			} else if (day_field.value.match(/^\d+$/) &&
				day_field.value <= 31) {
				// no year validation, since it can also include
				// 'BC' and possibly other non-number strings
				return true;
			} else {
				info_span = document.getElementById(info_id);
				info_span.innerHTML = sfgBadDateErrorStr;
				return false;
			}
		} else {
			return true;
		}
	}
}

// Same as validate_multiple_mandatory_fields(), but for type validation
function validate_type_of_multiple_fields(field_num, type) {
	var num_errors = 0;
	elems = document.getElementsByTagName("*");
	if ( ! elems) { elems = []; }
	var field_pattern = new RegExp('input_(.*)_' + field_num);
	for (var i = 0; i < elems.length; i++) {
		id = elems[i].id;
		if (matches = field_pattern.exec(id)) {
			instance_num = matches[1];
			var input_name = "input_" + instance_num + "_" + field_num;
			var info_name = "info_" + instance_num + "_" + field_num;
			if (! validate_field_type(input_name, type, info_name)) {
				num_errors += 1;
			}
		}
	}
	return (num_errors == 0);
}

jQuery('#sfForm').submit( function() { return validate_all(); } );

function validate_all() {
	var num_errors = 0;

	// evaluate all the passed-in JS validation calls - as with the
	// "show on select" calls, this is a hack.
	for (var i = 0; i < sfgJSValidationCalls.length; i++ ) {
		if (! eval(sfgJSValidationCalls[i]) ) num_errors += 1;
	}

	if (num_errors > 0) {
		// add error header, if it's not there already
		if (! document.getElementById("form_error_header")) {
			var errorMsg = document.createElement('div');
			errorMsg.innerHTML = '<div id="form_error_header" class="warningMessage" style="font-size: medium">' + sfgFormErrorsHeader + '</div>';
			document.getElementById("contentSub").appendChild(errorMsg);
		}
		scroll(0, 0);
	}
	return (num_errors == 0);
}

var num_elements = 0;

for (var i = 0; i < sfgAdderButtons.length; i++) {
	var components = sfgAdderButtons[i].split(',');
	adderID = components[0];
	templateName = components[1];
	fieldNum = components[2];
	jQuery('#' + adderID).click( addInstanceEventHandler(templateName, fieldNum) );
}

function addInstanceEventHandler(templateName, fieldNum) {
	return function() {
		addInstance('starter_' + templateName, 'main_' + templateName, fieldNum);
	}
}

function addInstance(starter_div_id, main_div_id, tab_index)
{
	var starter_div = document.getElementById(starter_div_id);
	var main_div = document.getElementById(main_div_id);
	num_elements++;
	
	//Create the new instance
	var new_div = starter_div.cloneNode(true);
	var div_id = 'div_gen_' + num_elements;
	new_div.className = 'multipleTemplate';
	new_div.id = div_id;
	new_div.style.display = 'block';
	
	// make internal ID unique for the relevant divs and spans, and replace
	// the [num] index in the element names with an actual unique index
	var children = new_div.getElementsByTagName('*');
	// this array is needed to counteract an IE bug
	var orig_children = starter_div.getElementsByTagName('*');
	var fancybox_ids = new Array();
	var x;
	for (x = 0; x < children.length; x++) {
		if (children[x].name)
			children[x].name = children[x].name.replace(/\[num\]/g, '[' + num_elements + ']');
		if (children[x].id)
			children[x].id = children[x].id
				.replace(/input_/g, 'input_' + num_elements + '_')
				.replace(/info_/g, 'info_' + num_elements + '_')
				.replace(/div_/g, 'div_' + num_elements + '_');
		if (children[x].href)
			children[x].href = children[x].href
				.replace(/input_/g, 'input_' + num_elements + '_');
		if (children[x].id.match("^fancybox")) {
			fancybox_ids.push(children[x].id);
		}

		// for dropdowns, copy over selectedIndex from original div,
		// to get around a bug in IE
		if (children[x].type == 'select-one') {
			children[x].selectedIndex = orig_children[x].selectedIndex;
		}
	}
	if (children[x]) {
		//We clone the last object
		if (children[x].href) {
			children[x].href = children[x].href
				.replace(/input_/g, 'input_' + num_elements + '_')
				.replace(/info_/g, 'info_' + num_elements + '_')
				.replace(/div_/g, 'div_' + num_elements + '_');
		}
	}
	// Since we clone the first object and we have uploadable field
	// we must replace the input_ in order to let the printer return
	// the value into the right field
	//Create remove button
	var remove_button = document.createElement('input');
	remove_button.type = 'button';
	remove_button.value = sfgRemoveText;
	remove_button.tabIndex = tab_index;
	remove_button.onclick = removeInstanceEventHandler(div_id);
	new_div.appendChild(remove_button);
	
	//Add the new instance
	main_div.appendChild(new_div);
	attachAutocompleteToAllFields(new_div);

	// For each 'upload file' link in this latest instance,
	// add a call to fancybox()
	for (x = 0; x < fancybox_ids.length; x++) {
		jQuery("#" + fancybox_ids[x]).fancybox({
			'width'         : '75%',
			'height'        : '75%',
			'autoScale'     : false,
			'transitionIn'  : 'none',
			'transitionOut' : 'none',
			'type'          : 'iframe',
			'overlayColor'  : '#222',
			'overlayOpacity' : '0.8'
		});
	}
}

for (var i = 0; i < sfgRemoverButtons.length; i++) {
	var components = sfgRemoverButtons[i].split(',');
	removerID = components[0];
	wrapperID = components[1];
	jQuery('#' + removerID).click( removeInstanceEventHandler(wrapperID) );
}

function removeInstanceEventHandler(divID)
{
	return function() {
		 jQuery('#' + divID).remove();
	};
}

// Activate autocomplete functionality for every field on the document
function attachAutocompleteToAllDocumentFields()
{
	var forms = document.getElementsByTagName("form");
	var x;
	for (x = 0; x < forms.length; x++) {
		if (forms[x].name == "createbox") {
			attachAutocompleteToAllFields(forms[x]);
		}	
	}
}

// Activate autocomplete functionality for every field under the specified
// element
function attachAutocompleteToAllFields(base)
{
	var inputs = base.getElementsByTagName("input");
	var y;
	for (y = 0; y < inputs.length; y++) {
		attachAutocompleteToField(inputs[y].id);
	}
	// don't forget the textareas
	inputs = base.getElementsByTagName("textarea");
	for (y = 0; y < inputs.length; y++) {
		attachAutocompleteToField(inputs[y].id);
	}
}

// Activate autocomplete functionality for the specified field
function attachAutocompleteToField(input_id)
{
	// Check input id for the proper format, to ensure this is for SF
	if (input_id.substr(0,6) == 'input_')
	{
		// Extract the field ID number from the input field
		var field_num = parseInt(input_id.substring(input_id.lastIndexOf('_') + 1, input_id.length),10);
		// Add the autocomplete string, if a mapping exists.
		var field_string = sfgAutocompleteMappings[field_num];
		if (field_string) {
			var div_id = input_id.replace(/input_/g, 'div_');
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
				sf_autocomplete(input_id, div_id, sfgAutocompleteValues[field_string], null, null, delimiter, data_source);
			} else {
				sf_autocomplete(input_id, div_id, null, wgScriptPath + "/api.php", sfgAutocompleteDataTypes[field_string], delimiter, data_source);
			}
		}
	}
}

for (var i = 0; i < sfgComboBoxInputs.length; i++ ) {
	var input_num = sfgComboBoxInputs[i];
	jQuery(function() {
		jQuery("#input_" + input_num).combobox();
	});
}

jQuery.event.add(window, "load", attachAutocompleteToAllDocumentFields);

for (var i = 0; i < sfgAutogrowInputs.length; i++ ) {
	var input_num = sfgAutogrowInputs[i];
	jQuery(document).ready(function() {
		jQuery("#" + input_num).autoGrow();
	});
}



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
			.addClass("ui-widget ui-widget-content ui-corner-left");
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
			.addClass("ui-corner-right ui-button-icon")
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
