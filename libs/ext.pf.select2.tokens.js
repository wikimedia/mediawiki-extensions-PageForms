/*
 * ext.pf.select2.tokens.js
 *
 * Javascript utility class to handle autocomplete
 * for tokens input type using Select2 JS library
 *
 * @file
 *
 * @licence GNU GPL v2+
 * @author Jatin Mehta
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
	 * Class constructor
	 *
	 *
	 * @class
	 * @constructor
	 */
	pf.select2.tokens = function() {

	};

	var tokens_proto = new pf.select2.base();

	/*
	 * Applies select2 to the HTML element
	 *
	 * @param {HTMLElement} element
	 *
	 */
	tokens_proto.apply = function( element ) {
		this.id = element.attr( "id" );
		var opts = this.setOptions();
		var cur_val = element.val();

		element.select2(opts);
		this.sortable(element);
		element.on( "change", this.onChange );
		element.val(cur_val);
		if ( element.attr( "existingvaluesonly" ) !== "true" ) {
			element.parent().on( "dblclick", "li.select2-search-choice", pfTokensTurnIntoInput );
		}
	};
	/*
	 * Returns options to be set by select2
	 *
	 * @return {object} opts
	 *
	 */
	tokens_proto.setOptions = function() {
		var self = this;
		var input_id = this.id;
		var opts = {};
		input_id = "#" + input_id;
		var input_tagname = $(input_id).prop( "tagName" );
		var autocomplete_opts = this.getAutocompleteOpts();

		if ( autocomplete_opts.autocompletedatatype !== undefined ) {
			opts.ajax = this.getAjaxOpts();
			opts.minimumInputLength = 1;
			opts.formatInputTooShort = "";
			opts.formatSelection = this.formatSelection;
			opts.escapeMarkup = function (m) { return m; };
		} else if ( input_tagname === "INPUT" ) {
			opts.data = this.getData( autocomplete_opts.autocompletesettings );
		}
		var wgPageFormsAutocompleteOnAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );
		if ( !wgPageFormsAutocompleteOnAllChars ) {
			opts.matcher = function( term, text ) {
				var no_diac_text = pf.select2.base.prototype.removeDiacritics( text );
				var position = no_diac_text.toUpperCase().indexOf(term.toUpperCase());
				var position_with_space = no_diac_text.toUpperCase().indexOf(" " + term.toUpperCase());
				if ( (position !== -1 && position === 0 ) || position_with_space !== -1 ) {
					return true;
				} else {
					return false;
				}
			};
		}
		opts.formatResult = this.formatResult;
		opts.formatSearching = mw.msg( "pf-select2-searching" );
		opts.formatNoMatches = "";
		opts.placeholder = $(input_id).attr( "placeholder" );
		if ( $(input_id).attr( "existingvaluesonly" ) !== "true" && input_tagname === "INPUT" ) {
			opts.createSearchChoice = function( term, data ) { if ( $(data).filter(function() { return this.text.localeCompare( term )===0; }).length===0 ) {return { id:term, text:term };} };
		}
		if ( $(input_id).val() !== "" && input_tagname === "INPUT" ) {
			opts.initSelection = function ( element, callback ) {
				var data = [];
				var delim = self.getDelimiter($(input_id));
				var i = 0;
				$(element.val().trim().split(delim)).each(function () {
					if ( this !== "" ) {
						data.push({id: i, text: this});
						i += 1;
					}
				});
				element.val( "" );
				callback(data);
			};
		}
		var size = $(input_id).attr("size");
		if ( size === undefined ) {
			size = 100; //default value
		}
		opts.containerCss = { 'min-width': size * 6 };
		opts.containerCssClass = 'pf-select2-container';
		opts.dropdownCssClass = 'pf-select2-dropdown';

		opts.multiple = true;
		opts.tokenSeparators = this.getDelimiter($(input_id));
		opts.openOnEnter = true;
		var maxvalues = $(input_id).attr( "maxvalues" );
		if ( maxvalues !== undefined ) {
			opts.maximumSelectionSize = maxvalues;
			opts.formatSelectionTooBig = mw.msg( "pf-select2-selection-too-big", maxvalues );
		}
		opts.adaptContainerCssClass = function( clazz ) {
			if (clazz === "mandatoryField") {
				return "";
			} else {
				return clazz;
			}
		};

		return opts;
	};

	/*
	 * Returns data to be used by select2 for tokens autocompletion
	 *
	 * @param {string} autocompletesettings
	 * @return {associative array} values
	 *
	 */
	tokens_proto.getData = function( autocompletesettings ) {
		var input_id = "#" + this.id;
		var values = [];
		var data;
		var dep_on = this.dependentOn();
		if ( dep_on === null ) {
			if ( autocompletesettings === 'external data' ) {
				var name = $(input_id).attr(this.nameAttr($(input_id)));
				var wgPageFormsEDSettings = mw.config.get( 'wgPageFormsEDSettings' );
				var edgValues = mw.config.get( 'edgValues' );
				data = {};
				if ( wgPageFormsEDSettings[name].title !== undefined && wgPageFormsEDSettings[name].title !== "" ) {
					data.title = edgValues[wgPageFormsEDSettings[name].title];
					var i = 0;
					if ( data.title !== undefined && data.title !== null ) {
						data.title.forEach(function() {
							values.push({
								id: i + 1, text: data.title[i]
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
				//Convert data into the format accepted by Select2
				if ( data !== undefined && data !== null ) {
					var index = 1;
					for (var key in data) {
						values.push({
							id: index, text: data[key]
						});
						index++;
					}
				}
			}
		} else { //Dependent field autocompletion
			var dep_field_opts = this.getDependentFieldOpts( dep_on );
			var my_server = mw.config.get( 'wgScriptPath' ) + "/api.php";
			my_server += "?action=pfautocomplete&format=json&property=" + dep_field_opts.prop + "&baseprop=" + dep_field_opts.base_prop + "&basevalue=" + dep_field_opts.base_value;
			$.ajax({
				url: my_server,
				dataType: 'json',
				async: false,
				success: function(data) {
					var id = 0;
					//Convert data into the format accepted by Select2
					data.pfautocomplete.forEach( function(item) {
						if (item.displaytitle !== undefined) {
							values.push({
								id: id++, text: item.displaytitle
							});
						} else {
							values.push({
								id: id++, text: item.title
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
	 * remote autocompletion of tokens
	 *
	 * @return {object} ajaxOpts
	 *
	 */
	tokens_proto.getAjaxOpts = function() {
		var autocomplete_opts = this.getAutocompleteOpts();
		var data_source = autocomplete_opts.autocompletesettings.split(',')[0];
		var my_server = mw.util.wikiScript( 'api' );
		var autocomplete_type = autocomplete_opts.autocompletedatatype;
		if ( autocomplete_type === 'cargo field' ) {
			var table_and_field = data_source.split('|');
			my_server += "?action=pfautocomplete&format=json&cargo_table=" + table_and_field[0] + "&cargo_field=" + table_and_field[1] + "&field_is_array=true";
		} else {
			my_server += "?action=pfautocomplete&format=json&" + autocomplete_opts.autocompletedatatype + "=" + data_source;
		}

		var ajaxOpts = {
			url: my_server,
			dataType: 'json',
			data: function (term) {
				return {
					substr: term, // search term
				};
			},
			results: function (data, page, query) { // parse the results into the format expected by Select2.
				var id = 0;
				if (data.pfautocomplete !== undefined) {
					data.pfautocomplete.forEach( function(item) {
						item.id = id++;
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
	tokens_proto.onChange = function() {
		pfTokensSaveFullValue( $(this).parent(), $(this) );
	};

	/*
	 * Returns delimiter for the token field
	 *
	 * @return {string} delimiter
	 *
	 */
	tokens_proto.getDelimiter = function ( element ) {
		var field_values = element.attr('autocompletesettings').split( ',' );
		var delimiter = ",";
		if (field_values[1] === 'list' && field_values[2] !== undefined && field_values[2] !== "") {
			delimiter = field_values[2];
		}

		return delimiter;
	};

	/*
	 * Makes the choices rearrangable in tokens
	 *
	 * @param {HTMLElement} element
	 *
	 */
	tokens_proto.sortable = function( element ) {
		element.select2("container").find("ul.select2-choices").sortable({
			containment: 'parent',
			start: function() { $(".pfTokens").select2("onSortStart"); },
			update: function() { $(".pfTokens").select2("onSortEnd"); }
		});
	};

	pf.select2.tokens.prototype = tokens_proto;

	// The following functions, for making tokens editable, are loosely
	// based on the example at:
	// https://github.com/select2/select2/issues/116#issuecomment-37440758
	// @TODO - some or all of these functions should ideally be part of
	// the pf.select2.tokens "class", but I wasn't able to get that
	// working.
	function pfTokensSaveFullValue( tokensInput, inputControl ) {
		var data = "";
		var tokens = new pf.select2.tokens();
		var publicInput = tokensInput.find( 'input.pfTokens' );
		var delim = tokens.getDelimiter( publicInput );
		var namespace = inputControl.attr( "data-namespace" );
		tokensInput.find("li.select2-search-choice").each(function() {
			var val = $(this).children("div").text().trim();
 			if ( namespace && data.id === data.text ) {
 				if (val.indexOf( namespace + ':' ) !== 0 ) {
 					val = namespace + ':' + val;
 				}
 			}
			if (data !== "") {
				data += delim + " ";
			}
			data += val;
		});
		publicInput.val(data);
	}

	function pfTokensSaveSelect( tokensInput, inputControl ) {
		inputControl.parent().html(inputControl.val());
		pfTokensSaveFullValue( tokensInput, inputControl );
	}

	function pfTokensTurnIntoInput( event ) {
		var currentSelect = $(event.target);
		var tokensInput = $(currentSelect).parent().parent().parent().parent();
		var itemValue = currentSelect.html();
		if ( itemValue.indexOf("<input type") === -1 ) {
			currentSelect.html("<input type=\"text\" value=\"" + itemValue.replace( '"', '&quot;' ) + "\">");
			var inputControl = currentSelect.children("input");
			inputControl.focus()
			// Normally, a click anywhere in the Select2 input will
			// move the focus to the end of the tokens. Prevent
			// that happening in this "sub-input".
			.click(function(event) {
				event.stopPropagation();
			})
			// Exit and save if the user clicks away, or hits
			// "enter".
			.blur(function() {
				pfTokensSaveSelect( tokensInput, inputControl );
			})
			.keypress( function( event ) {
				var keycode = (event.keyCode ? event.keyCode : event.which);
				if ( keycode === 13 ) {
					pfTokensSaveSelect( tokensInput, inputControl );
				}
			});
		}
	}

}( jQuery, mediaWiki, pageforms ) );
