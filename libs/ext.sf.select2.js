/*
 * ext.sf.select2.js
 *
 * Javascript utility classes to handle autocomplete
 * for various input types using Select2 JS library
 *
 * @file
 *
 *
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
	sf.select2 = sf.select2 = sf.select2 || {};

	/**
	 * Class constructor
	 *
	 *
	 * @class
	 * @constructor
	 */
	sf.select2.combobox = function() {

	};

	/* Public methods */

	sf.select2.combobox.prototype = {
		/*
		 * Applies select2 to the input HTML element
		 * and make it a combobox
		 *
		 * @param {HTMLInputElement} element
		 *
		 */
		apply: function( element ) {
			this.id = element.attr( "id" );
			var opts = this.setOptions();

			element.select2(opts);
			element.on( "change", this.onChange );
		},
		/*
		 * Returns data to be used by select2 for combobox autocompletion
		 *
		 * @param {string} autocompletesettings
		 * @return {associative array} values
		 *
		 */
		getData: function( autocompletesettings ) {
			var values = [{id: 0, text: ""}];
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
					        id: i + 1, text: data[i]
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
						var id = 1;
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
		},
		/*
		 * Returns ajax options to be used by select2 for
		 * remote autocompletion of combobox
		 *
		 * @return {object} ajaxOpts
		 *
		 */
		getAjaxOpts: function() {
			var input_id = "#" + this.id;
			var autocomplete_opts = this.getAutocompleteOpts();
			var my_server = mw.util.wikiScript( 'api' );
			my_server += "?action=sfautocomplete&format=json&" + autocomplete_opts.autocompletedatatype + "=" + autocomplete_opts.autocompletesettings;

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
		},
		/*
		 * Returns HTML text to be used by select2 for
		 * showing remote data retrieved
		 *
		 * @param {object} value
		 * @param {object} container
		 * @param {object} query
		 *
		 * @return {string} markup
		 *
		 */
		formatResult: function(value, container, query) {
			var term = query.term.toLowerCase();
			var result = value.text;
			var start = result.toLowerCase().indexOf(term);
			var end = start + term.length - 1;
			var markup = result.substr(0, start) +
				'<span class="select2-match">' +
					result.substr(start, end - start + 1) +
				'</span>' +
				result.substr(end + 1);

			return markup;
		},
		/*
		 * Returns string/HTML text to be used by select2 to
		 * show selection
		 *
		 * @param {object} value (The selected result object)
		 *
		 * @return {string}
		 *
		 */
		formatSelection: function(value) {
			return value.text;
		},
		/*
		 * Returns options to be set by select2
		 *
		 * @return {object} opts
		 *
		 */
		setOptions: function() {
			var input_id = this.id;
			var opts = {};
			var input_id = "#" + input_id;
			var input_tagname = $(input_id).prop( "tagName" );
			var autocomplete_opts = this.getAutocompleteOpts();

			if ( autocomplete_opts.autocompletedatatype != undefined ) {
				opts.ajax = this.getAjaxOpts();
				opts.minimumInputLength = 1;
				opts.formatInputTooShort = mw.msg( "sf-select2-input-too-short", opts.minimumInputLength );
				opts.formatResult = this.formatResult;
				opts.formatSelection = this.formatSelection;
				opts.escapeMarkup = function (m) { return m; };
			} else if ( input_tagname == "INPUT" ) {
				opts.data = this.getData( autocomplete_opts.autocompletesettings );
			}
			var sfgAutocompleteOnAllChars = mw.config.get( 'sfgAutocompleteOnAllChars' );
			if ( !sfgAutocompleteOnAllChars ) {
				opts.matcher = function( term, text ) { return text.toUpperCase().indexOf(term.toUpperCase())==0; };
			}
			opts.formatSearching = mw.msg( "sf-select2-searching" );
			opts.formatNoMatches = mw.msg( "sf-select2-no-matches" );
			opts.placeholder = $(input_id).attr( "placeholder" );
			if ( $(input_id).attr( "existingvaluesonly" ) !== "true" && input_tagname == "INPUT" ) {
				opts.createSearchChoice = function( term, data ) { if ( $(data).filter(function() { return this.text.localeCompare( term )===0; }).length===0 ) {return { id:term, text:term };} };
			}
			if ( $(input_id).val() != "" && input_tagname == "INPUT" ) {
				opts.initSelection = function ( element, callback ) { var data = {id: element.val(), text: element.val()}; callback(data); };
			}
			opts.allowClear = true;
			var size = $(input_id).attr("size");
			if ( size == undefined ) {
				size = 35; //default value
			}
			opts.containerCss = { 'min-width': size * 6 };
			opts.containerCssClass = 'sf-select2-container';
			opts.dropdownCssClass = 'sf-select2-dropdown';

			return opts;
		},
		/*
		 * Used to set the value of the HTMLInputElement
		 * when there is a change in the select2 value
		 *
		 */
		onChange: function() {
			var self = this;
			var data = $(this).select2( "data" );
			if (data != null) {
				$(this).val( data.text );
			} else {
				$(this).val( '' );
			}

			// Set the corresponding values for any other field
			// in the form which is deoendent on this element
			var cmbox = new sf.select2.combobox();
			var dep_on_me = cmbox.dependentOnMe( $(this) );
			dep_on_me.forEach( function( dependent_field_name ) {
				if ( cmbox.partOfMultiple( $(self) ) ) {
					var dependent_field = $(self).closest( ".multipleTemplateInstance" )
								.find( '[origname ="' + dependent_field_name + '" ]' );
				} else {
					var dependent_field = $('[name ="' + dependent_field_name + '" ]');
				}
				cmbox.destroy($(dependent_field));
				$(dependent_field).val( '' );
				cmbox.apply($(dependent_field));
			});
		},
		/*
		 * Used to remove the select2 applied to the Input HTML element,
		 * the selected value will remain preserved.
		 *
		 * @param {HTMLInputElement} element
		 *
		 */
		destroy: function( element ) {
			element.select2( "destroy" );
		},
		/*
		 * If the combobox is dependent on some other field in the form
		 * then it returns its name.
		 *
		 * @return {string}
		 *
		 */
		dependentOn: function() {
			var input_id = "#" + this.id;
			var name_attr = this.nameAttr( $(input_id) );
			var name = $(input_id).attr( name_attr );
			var dependent_on_me = [];

			var sfgDependentFields = mw.config.get( 'sfgDependentFields' );
			for ( var i = 0; i < sfgDependentFields.length; i++ ) {
				var dependentFieldPair = sfgDependentFields[i];
				if ( dependentFieldPair[1] == name ) {
					 return dependentFieldPair[0];
				}
			}
			return null;
		},
		/*
		 * Returns the array of names of fields in the form which are dependent
		 * on the field passed as a param to this function,
		 *
		 * @param {HTMLInputElement} element
		 *
		 * @return {associative array} dependent_on_me
		 *
		 */
		dependentOnMe: function( element ) {
			var name_attr = this.nameAttr(element);
			var name = element.attr( name_attr );
			var dependent_on_me = [];

			var sfgDependentFields = mw.config.get( 'sfgDependentFields' );
			for ( var i = 0; i < sfgDependentFields.length; i++ ) {
				var dependentFieldPair = sfgDependentFields[i];
				if ( dependentFieldPair[0] == name ) {
					dependent_on_me.push(dependentFieldPair[1]);
				}
			}

			return dependent_on_me;
		},
		/*
		 * Returns the name attribute of the combobox depending on
		 * whether it is a part of multiple instance template or not
		 *
		 * @param {HTMLInputElement} element
		 *
		 * @return {string}
		 *
		 */
		nameAttr: function( element ) {
			return  this.partOfMultiple( element ) ? "origname" : "name";
		},
		/*
		 * Checks whether the combobox is part of multiple instance template or not
		 *
		 * @param {HTMLInputElement} element
		 *
		 * @return {boolean}
		 *
		 */
		partOfMultiple: function( element ) {
			return element.attr( "origname" ) != undefined ? true : false;
		},
		/*
		 * Gives dependent field options which include
		 * property, base property and base value
		 *
		 * @param {string} dep_on
		 *
		 * @return {object} dep_field_opts
		 *
		 */
		getDependentFieldOpts: function( dep_on ) {
			var input_id = "#" + this.id;
			var dep_field_opts = {};
			if ( this.partOfMultiple($(input_id)) ) {
				var base_element = $(input_id).closest( ".multipleTemplateInstance" )
								.find( '[origname ="' + dep_on + '" ]' );
			} else {
				var base_element = $('[name ="' + dep_on + '" ]');
			}
			dep_field_opts.base_value = base_element.val();
			dep_field_opts.base_prop = base_element.attr( "autocompletesettings" );
			dep_field_opts.prop = $(input_id).attr( "autocompletesettings" );

			return dep_field_opts;
		},
		/*
		 * Gives autocomplete options for a field
		 *
		 *
		 * @return {object} autocomplete_opts
		 *
		 */
		getAutocompleteOpts: function() {
			var input_id = "#" + this.id;
			var autocomplete_opts = {};
			autocomplete_opts.autocompletedatatype = $(input_id).attr( "autocompletedatatype" );
			autocomplete_opts.autocompletesettings = $(input_id).attr( "autocompletesettings" );

			return autocomplete_opts;
		},
	};
} )( jQuery, mediaWiki, semanticforms );