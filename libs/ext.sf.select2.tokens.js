/*
 * ext.sf.select2.tokens.js
 *
 * Javascript utility class to handle autocomplete
 * for tokens input type using Select2 JS library
 *
 * @file
 *
 * @licence GNU GPL v2+
 * @author Jatin Mehta
 */

( function( $, mw, sf ) {
	'use strict';

	/**
	 * Inheritance class for the sf.select2 constructor
	 *
	 *
	 * @class
	 */
	sf.select2 = sf.select2 || {};

	/**
	 * Class constructor
	 *
	 *
	 * @class
	 * @constructor
	 */
	sf.select2.tokens = function() {

	};

	var tokens_proto = new sf.select2.base();
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
		var input_id = "#" + input_id;
		var input_tagname = $(input_id).prop( "tagName" );
		var autocomplete_opts = this.getAutocompleteOpts();

		if ( autocomplete_opts.autocompletedatatype != undefined ) {
			opts.ajax = this.getAjaxOpts();
			opts.minimumInputLength = 1;
			opts.formatInputTooShort = "";
			opts.formatResult = this.formatResult;
			opts.formatSelection = this.formatSelection;
			opts.escapeMarkup = function (m) { return m; };
		} else if ( input_tagname == "INPUT" ) {
			opts.data = this.getData( autocomplete_opts.autocompletesettings );
		}
		var sfgAutocompleteOnAllChars = mw.config.get( 'sfgAutocompleteOnAllChars' );
		if ( !sfgAutocompleteOnAllChars ) {
			opts.matcher = function( term, text ) {
				var position = text.toUpperCase().indexOf(term.toUpperCase());
				var position_with_space = text.toUpperCase().indexOf(" " + term.toUpperCase());
				if ( (position != -1 && position == 0 ) ||  position_with_space != -1 )
					return true;
				else
					return false;
			};
			opts.formatResult = this.formatResult;
		}
		opts.formatSearching = mw.msg( "sf-select2-searching" );
		opts.formatNoMatches = "";
		opts.placeholder = $(input_id).attr( "placeholder" );
		if ( $(input_id).attr( "existingvaluesonly" ) !== "true" && input_tagname == "INPUT" ) {
			opts.createSearchChoice = function( term, data ) { if ( $(data).filter(function() { return this.text.localeCompare( term )===0; }).length===0 ) {return { id:term, text:term };} };
		}
		if ( $(input_id).val() != "" && input_tagname == "INPUT" ) {
			opts.initSelection = function ( element, callback ) {
				var data = [];
				var delim = self.getDelimiter($(input_id));
				var i = 0;
				$(element.val().trim().split(delim)).each(function () {
					if ( this != "" ) {
						data.push({id: i, text: this});
						i += 1;
					}
				});
				element.val( "" );
				callback(data);
			};
		}
		var size = $(input_id).attr("size");
		if ( size == undefined ) {
			size = 100; //default value
		}
		opts.containerCss = { 'min-width': size * 6 };
		opts.containerCssClass = 'sf-select2-container';
		opts.dropdownCssClass = 'sf-select2-dropdown';

		opts.multiple = true;
		opts.tokenSeparators = this.getDelimiter($(input_id));
		opts.openOnEnter = true;
		var maxvalues = $(input_id).attr( "maxvalues" );
		if ( maxvalues != undefined ) {
			opts.maximumSelectionSize = maxvalues;
			opts.formatSelectionTooBig = mw.msg( "sf-select2-selection-too-big", maxvalues );
		}
		opts.adaptContainerCssClass = function( clazz ) { if (clazz == "mandatoryField") return ""; };

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
		var values = [];
		var dep_on = this.dependentOn();
		if ( dep_on == null ) {
			var sfgAutocompleteValues = mw.config.get( 'sfgAutocompleteValues' );
			var data = sfgAutocompleteValues[autocompletesettings];
			var i = 0;
			//Convert data into the format accepted by Select2
			if (data != undefined) {
				data.forEach(function()
				{
				    values.push({
				        id: i, text: data[i]
				    });
				    i++;
				});
			}
		} else { //Dependent field autocompletion
			var dep_field_opts = this.getDependentFieldOpts( dep_on );
			var my_server = mw.config.get( 'wgScriptPath' ) + "/api.php";
			my_server += "?action=sfautocomplete&format=json&property=" + dep_field_opts.prop + "&baseprop=" + dep_field_opts.base_prop + "&basevalue=" + dep_field_opts.base_value;
			$.ajax({
				url: my_server,
				dataType: 'json',
				async: false,
				success: function(data) {
					var id = 0;
					//Convert data into the format accepted by Select2
					data.sfautocomplete.forEach( function(item) {
						values.push({
						 	id: id++, text: item.title
						});
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
		var input_id = "#" + this.id;
		var autocomplete_opts = this.getAutocompleteOpts();
		var data_source = autocomplete_opts.autocompletesettings.split(',')[0];
		var my_server = mw.util.wikiScript( 'api' );
		my_server += "?action=sfautocomplete&format=json&" + autocomplete_opts.autocompletedatatype + "=" + data_source;

		var ajaxOpts = {
			url: my_server,
			dataType: 'jsonp',
			data: function (term) {
				return {
					substr: term, // search term
				};
			},
			results: function (data, page, query) { // parse the results into the format expected by Select2.
				var id = 0;
				data.sfautocomplete.forEach( function(item) {
					item.id = id++;
					item.text = item.title;
				});
				return {results: data.sfautocomplete};
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
		var self = this;
		var data = $(this).select2( "data" );
		var tokens = new sf.select2.tokens();
		var delim = tokens.getDelimiter( $(this) );

		if (data != null) {
			var tokens_value = "";
			data.forEach( function( token ) {
				tokens_value += token.text.trim() + delim + " ";
			});
			$(this).val( tokens_value );
		} else {
			$(this).val( '' );
		}
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
		if (field_values[1] == 'list' && field_values[2] != null) {
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
		    start: function() { $(".sfTokens").select2("onSortStart"); },
		    update: function() { $(".sfTokens").select2("onSortEnd"); }
		});
	};

	sf.select2.tokens.prototype = tokens_proto;

} )( jQuery, mediaWiki, semanticforms );