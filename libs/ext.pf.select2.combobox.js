/*
 * ext.pf.select2.comboboxjs
 *
 * JavaScript utility class to handle autocomplete
 * for combobox input type using Select2 JS library
 *
 * @file
 *
 * @licence GNU GPL v2+
 * @author Jatin Mehta
 * @author Priyanshu Varshney
 * @author Yaron Koren
 */

( function( $, mw, pf ) {
	'use strict';

	/**
	 * Inheritance class for the pf.select2 constructor
	 *
	 *
	 * @class
	 */
	pf.select2 = pf.select2 || {};

	/**
	 * @class
	 * @constructor
	 */
	pf.select2.combobox = function() {

	};

	var combobox_proto = new pf.select2.base();

	/*
	 * Returns options to be set by select2
	 *
	 * @return {object} opts
	 *
	 */
	combobox_proto.setOptions = function() {
		var self = this;
		var input_id = this.id;
		var opts = {};
		opts.language = {};
		input_id = "#" + input_id;
		var input_tagname = $(input_id).prop( "tagName" );
		var autocomplete_opts = this.getAutocompleteOpts();
		opts.escapeMarkup = function (m) {
			return self.escapeMarkupAndAddHTML(m);
		};
		if ( autocomplete_opts.autocompletedatatype !== undefined ) {
			opts.ajax = this.getAjaxOpts();
			opts.minimumInputLength = 1;
			opts.language.inputTooShort = function() {
				return mw.msg( "pf-select2-input-too-short", opts.minimumInputLength );
			};
		} else if ( input_tagname === "SELECT" ) {
			opts.data = this.getData( autocomplete_opts.autocompletesettings );
		}
		var wgPageFormsAutocompleteOnAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );
		if ( !wgPageFormsAutocompleteOnAllChars ) {
			opts.matcher = function( term, text ) {
				if( term.term === undefined ) {
					term.term = "";
				}
				var no_diac_text = pf.select2.base.prototype.removeDiacritics( text.text );
				var position = no_diac_text.toUpperCase().indexOf(term.term.toString().toUpperCase());
				var position_with_space = no_diac_text.toUpperCase().indexOf(" " + term.term.toString().toUpperCase());
				if ( (position !== -1 && position === 0 ) || position_with_space !== -1 ) {
					return text;
				} else {
					return null;
				}
				return null;
			};
		}
		opts.templateResult = function( result ) {
			var term = $( input_id ).data("select2").dropdown.$search.val();
			if ( term === undefined ) {
				term = "";
			}
			return pf.select2.base.prototype.textHighlight( result.id, term );
		}
		opts.language.searching = function() {
			return mw.msg( "pf-select2-searching" );
		};
		opts.language.noMatches = function() {
			return mw.msg( "pf-select2-no-matches" );
		};
		opts.placeholder = $(input_id).attr( "placeholder" );
		if( opts.placeholder === undefined ) {
			opts.placeholder = "";
		}
		opts.allowClear = true;
		var size = $(input_id).attr("data-size");
		if ( size === undefined ) {
			size = '200'; //default value
		}
		opts.containerCss = { 'min-width': size };
		opts.width= NaN;
		if( !this.existingValuesOnly ){
			opts.tags = true;
		}
		opts.containerCssClass = 'pf-select2-container';
		opts.dropdownCssClass = 'pf-select2-dropdown';
		return opts;
	};

	/*
	 * Returns data to be used by select2 for combobox autocompletion
	 *
	 * @param {string} autocompletesettings
	 * @return {associative array} values
	 *
	 */
	combobox_proto.getData = function( autocompletesettings ) {
		var input_id = "#" + this.id;
		var values = [];
		var dep_on = this.dependentOn();
		var i, data;
		if ( dep_on === null ) {
			if ( autocompletesettings === 'external data' ) {
				var name = $(input_id).attr(this.nameAttr($(input_id)));
				var wgPageFormsEDSettings = mw.config.get( 'wgPageFormsEDSettings' );
				var edgValues = mw.config.get( 'edgValues' );
				data = {};
				if ( wgPageFormsEDSettings[name].title !== undefined && wgPageFormsEDSettings[name].title !== "" ) {
					data.title = edgValues[wgPageFormsEDSettings[name].title];
					if ( data.title !== undefined && data.title !== null ) {
						i = 0;
						data.title.forEach(function() {
							values.push({
								id: data.title[i], text: data.title[i]
							});
							i++;
						});
					}
					if ( wgPageFormsEDSettings[name].image !== undefined && wgPageFormsEDSettings[name].image !== "" ) {
						data.image = edgValues[wgPageFormsEDSettings[name].image];
						i = 0;
						if ( data.image !== undefined && data.image !== null ) {
							data.image.forEach(function() {
								values[i].image = data.image[i];
								i++;
							});
						}
					}
					if ( wgPageFormsEDSettings[name].description !== undefined && wgPageFormsEDSettings[name].description !== "" ) {
						data.description = edgValues[wgPageFormsEDSettings[name].description];
						i = 0;
						if ( data.description !== undefined && data.description !== null ) {
							data.description.forEach(function() {
								values[i].description = data.description[i];
								i++;
							});
						}
					}
				}

			} else {
				var wgPageFormsAutocompleteValues = mw.config.get( 'wgPageFormsAutocompleteValues' );
				data = wgPageFormsAutocompleteValues[autocompletesettings];

				if ( Array.isArray( data ) || typeof data == 'object' ) {
					// Insert an empty string at the start of the array,
					// so that when Select2 gets the data it doesn't
					// duplicate the first option in the dropdown
					values.push({
						id: '', text: ''
					});
					// Convert data into the format accepted by Select2
					for (var key in data) {
						values.push({
							id: data[key], text: data[key]
						});
					}

					// Add the current value to the list of allowed
					// values, if it's not there already, so that the
					// combobox won't show up as blank.
					// Should this be done even if "existing values
					// only" is specified"? For now, yes - based on
					// the "principle of least astonishment", it's
					// generally better for the form to not wipe out
					// existing content when possible. However,
					// there's also an argument the other way.
					var curValue = $('#' + this.id).attr('value');
					if ( curValue !== '' && curValue !== undefined && !Object.keys(data).includes( curValue ) ) {
						values.push({
							id: curValue, text: curValue
						});
					}
				}
			}
		} else { // Dependent field autocompletion
			var dep_field_opts = this.getDependentFieldOpts( dep_on );
			var my_server = mw.config.get( 'wgScriptPath' ) + "/api.php";
			my_server += "?action=pfautocomplete&format=json";
			// URL depends on whether Cargo or Semantic MediaWiki
			// is being used.
			if ( dep_field_opts.prop.indexOf('|') === -1 ) {
				// SMW
				my_server += "&property=" + dep_field_opts.prop + "&baseprop=" + dep_field_opts.base_prop + "&basevalue=" + dep_field_opts.base_value;
			} else {
				// Cargo
				var cargoTableAndFieldStr = dep_field_opts.prop;
				var cargoTableAndField = cargoTableAndFieldStr.split('|');
				var cargoTable = cargoTableAndField[0];
				var cargoField = cargoTableAndField[1];
				var baseCargoTableAndFieldStr = dep_field_opts.base_prop;
				var baseCargoTableAndField = baseCargoTableAndFieldStr.split('|');
				var baseCargoTable = baseCargoTableAndField[0];
				var baseCargoField = baseCargoTableAndField[1];
				my_server += "&cargo_table=" + cargoTable + "&cargo_field=" + cargoField + "&base_cargo_table=" + baseCargoTable + "&base_cargo_field=" + baseCargoField + "&basevalue=" + dep_field_opts.base_value;
			}

			$.ajax({
				url: my_server,
				dataType: 'json',
				async: false,
				success: function(data) {
					//Convert data into the format accepted by Select2
					data.pfautocomplete.forEach( function(item) {
						if (item.displaytitle !== undefined) {
							values.push({
								id: item.displaytitle, text: item.displaytitle
							});
						} else {
							values.push({
								id: item.title, text: item.title
							});
						}
					});
					return values;
				}
			});
		}

		return values;
	};

	/*
	 * Returns ajax options to be used by select2 for
	 * remote autocompletion of combobox
	 *
	 * @return {object} ajaxOpts
	 *
	 */
	combobox_proto.getAjaxOpts = function() {
		var autocomplete_opts = this.getAutocompleteOpts();
		var data_source = autocomplete_opts.autocompletesettings.split(',')[0];
		var my_server = mw.util.wikiScript( 'api' );
		var autocomplete_type = autocomplete_opts.autocompletedatatype;
		if ( autocomplete_type === 'cargo field' ) {
			var table_and_field = data_source.split('|');
			my_server += "?action=pfautocomplete&format=json&cargo_table=" + table_and_field[0] + "&cargo_field=" + table_and_field[1];
		} else {
			my_server += "?action=pfautocomplete&format=json&" + autocomplete_opts.autocompletedatatype + "=" + data_source;
		}

		var ajaxOpts = {
			url: my_server,
			dataType: 'json',
			data: function (term) {
				return {
					substr: term.term, // search term
				};
			},
			processResults: function (data) { // parse the results into the format expected by Select2.
				if (data.pfautocomplete !== undefined) {
					data.pfautocomplete.forEach( function(item) {
						item.id = item.title;
						if (item.displaytitle !== undefined) {
							item.text = item.displaytitle;
						} else {
							item.text = item.title;
						}
					});
					return {results: data.pfautocomplete};
				} else {
					return {results: []};
				}
			}
		};

		return ajaxOpts;
	};

	/*
	 * Used to set the value of the HTMLInputElement
	 * when there is a change in the select2 value
	 *
	 */
	combobox_proto.onChange = function() {
		var self = this;
		var data = $(this).select2( "data" );
		var namespace = $(this).attr( "data-namespace" );
		if (data.length !== 0) {
 			var val = data[0].text;
 			if ( namespace && data[0].id === data[0].text ) {
 				if ( val.indexOf( namespace + ':' ) !== 0 ) {
 					val = namespace + ':' + val;
 				}
 			}
			$(this)[0].children[0].text=val;
			$(this)[0].children[0].value=val;

 			$(this).value = val;
		} else {
			$(this).value = '';
		}

		// Set the corresponding values for any other field
		// in the form which is dependent on this element
		var cmbox = new pf.select2.combobox();
		var dep_on_me = $.unique(cmbox.dependentOnMe( $(this) ));
		dep_on_me.forEach( function( dependent_field_name ) {
			var dependent_field;
			if ( cmbox.partOfMultiple( $(self) ) ) {
				dependent_field = $(self).closest( ".multipleTemplateInstance" )
					.find( '[origname ="' + dependent_field_name + '" ]' );
			} else {
				dependent_field = $('[name ="' + dependent_field_name + '" ]');
			}
			cmbox.dependentFieldAutocompleteHandler( dependent_field, self );
		});
	};
	/*
	 * Handles dependent field autocompletion
	 *
	 * @param {HTMLElement} dependent_field
	 * @param {HTMLElement} dependent_on
	 *
	 */
	combobox_proto.dependentFieldAutocompleteHandler = function( dependent_field, dependent_on ) {
		var class_name = $(dependent_field).attr( 'class' );
		var cmbox = new pf.select2.combobox();
		var tokens = new pf.select2.tokens();

		if ( class_name.indexOf( 'pfComboBox' ) !== -1 ) {
			cmbox.refresh(dependent_field);
		} else if ( class_name.indexOf( 'pfTokens' ) !== -1 ) {
			tokens.refresh(dependent_field);
		} else if ( class_name.indexOf( 'createboxInput' ) !== -1 ) {
			var name_attr = cmbox.nameAttr($(dependent_on));
			var field_name = $(dependent_field).attr(name_attr),
			base_field_name = $(dependent_on).attr(name_attr),
			base_value = $(dependent_on).val();
			$(dependent_field).setDependentAutocompletion(field_name, base_field_name, base_value);
		}

	};

	pf.select2.combobox.prototype = combobox_proto;

	// Open the combobox if the user tabs to it, but only if it's blank.
	// Based in part on
	// https://github.com/select2/select2/issues/4025#issuecomment-372735893
	$(document).on('focus', '.select2.select2-container', function (e) {
		if ( e.originalEvent &&
		$(this).find(".select2-selection--single").length > 0 &&
		$(this).find('.select2-selection__rendered').attr('title') == undefined ) {
			$(this).siblings('select').select2('open');
		}
	});

	// When the combobox dropdown appears, put the current selected value
	// in the entry field, so that it can be edited.
	$(document).on('click', 'span.select2-selection', function(e) {
		var comboboxAltID = $(this).attr('aria-owns');
		if ( comboboxAltID == undefined ) {
			return;
		}
		var comboboxValue = $(this).parents('span.comboboxSpan').find('select').val();
		$( "[aria-controls='" + comboboxAltID + "']" ).val(comboboxValue);
	});

}( jQuery, mediaWiki, pageforms ) );
