const Sortable = require( 'ext.pageforms.sortable' );

/*
 * ext.pf.select2.tokens.js
 *
 * JavaScript utility class to handle autocomplete
 * for tokens input type using Select2 JS library
 *
 * @file
 *
 * @licence GNU GPL v2+
 * @author Jatin Mehta
 * @author Yaron Koren
 * @author Priyanshu Varshney
 */

( function( $, mw, pf ) {
	'use strict';

	/**
	 * Inheritance class for the pf.select2 constructor
	 *
	 * @class
	 */
	pf.select2 = pf.select2 || {};

	/**
	 * @class
	 * @constructor
	 */
	pf.select2.tokens = function() {

	};

	const tokens_proto = new pf.select2.base();

	/*
	 * Applies select2 to the HTML element
	 *
	 * @param {jQuery} element
	 *
	 */
	tokens_proto.apply = function( element ) {
		const existingValuesOnly = (element.attr("existingvaluesonly") == "true");
		this.existingValuesOnly = existingValuesOnly;
		this.id = element.attr( "id" );
		let inputData,
			$input;

		// This happens sometimes, although it shouldn't. If it does,
		// something went wrong, so just exit.
		if ( this.id == undefined ) {
			return;
		}

		try {
			const opts = this.setOptions();
			$input = element.select2(opts);
			inputData = $input.data("select2");
		} catch (e) {
			window.console.log(e);
		}

		// Make the tokens sortable, using the SortableJS library.
		const tokensUL = element.parent().find('ul.select2-selection__rendered');
		const tokensSelect = element.parents('span.inputSpan').find('select');
		const sortable = Sortable.create(tokensUL[0], {
			ghostClass: 'pfTokensGhost',

			// Somewhat of a @HACK - the tokens are stored in two
			// places in the DOM, a <ul> tag (which is displayed)
			// and a <select> tag (which is what gets submitted).
			// SortableJS only handles the first of these, so when
			// a rearrange is done, we rearrange the <select> layout
			// to match what's in the <ul>.
			// Is there a simpler way to do this?
			onEnd: function(event, dragEvent) {
				const newTokensOrder = [];
				tokensUL.find('li.select2-selection__choice').not('.sortable-ghost').each( function() {
					// Remove the "x" from the beginning of
					// the string.
					newTokensOrder.push($(this).text().slice(1));
				});
				const dropdownItems = {};
				tokensSelect.find('option').each( function() {
					const optionName = $(this).text();
					dropdownItems[optionName] = $(this);
				} );
				tokensSelect.prepend(dropdownItems[newTokensOrder[0]]);
				for ( let i = 1; i < newTokensOrder.length; i++ ){
					dropdownItems[newTokensOrder[i]].insertAfter(dropdownItems[newTokensOrder[i - 1]]);
				}
			}
		});

		// Make sure that entries added with "local autocompletion"
		// show up in the order they were entered, not alphabetical
		// order.
		// Copied from https://github.com/select2/select2/issues/3106#issuecomment-234702241
		element.on("select2:select", function (evt) {
			let elem = evt.params.data.element;

			if( !elem ) {
				const data = $(element).select2('data');
				elem = data.filter((obj) => obj.id === evt.params.data.id);
				if( !elem.length || !elem[0] || typeof elem[0].element == 'undefined' ) {
					return;
				}
				elem = elem[0].element;
			}

			const $element = $(elem);

			$element.detach();
			$(this).append($element);
			$(this).trigger("change");
			// In order for this selection to truly "take", the
			// full input needs to be clicked on, for some reason.
			// We click on it *twice*, though, to get rid of the
			// "Please enter..." message.
			// There's probably a less hacky way to accomplish this.
			$(this).parent().find('span.select2-selection').click().click();
		});

		$(inputData.$container[0]).on("keyup",(e) => {
			if( existingValuesOnly ){
				return ;
			}
			if( e.keyCode === 9 ){
				let rawValue = "";
				let checkIfPresent = false;
				const valHighlighted = inputData.$results.find('.select2-results__option--highlighted')[0];
				if( valHighlighted !== undefined ){
					rawValue = valHighlighted.textContent;
				}
				const newValue = $.grep(inputData.val(), (value) => {
					if( value === rawValue ){
						checkIfPresent = true;
					}
					return value !== rawValue;
				});
				if( checkIfPresent === false && rawValue !== "" ) {
					newValue.push(rawValue);
				}
				if ( !$input.find( "option[value='" + rawValue + "']" ).length ) {
					const newOption = new Option( rawValue, rawValue, false, false );
					$input.append(newOption).trigger( 'change' );
				}
				$input.val( newValue ).trigger( 'change' );
			}
		});
		if ( element.attr( "existingvaluesonly" ) !== "true" ) {
			element.parent().on( "dblclick", "li.select2-selection__choice", ( event ) => {
				let $target = $(event.target);
				// If the target element is the span within li then change it to the parent li
				if ( $target.is( $("span.select2-match-entire") ) ) {
					$target = $target.parent();
				}
				// get the text and id of the clicked value
				const targetData = $target.data();
				const clickedValue = $target[0].title;
				const clickedValueId = targetData.select2Id;

				// remove that value from select2 selection
				const newValue = $.grep(inputData.val(), (value) => value !== clickedValue);
				$input.val(newValue).trigger("change");

				// set the currently entered text to equal the clicked value
				inputData.$container.find(".select2-search__field").val(clickedValue).trigger("input").focus();
			} );
		}
		const $loadingIcon = $( '<img src = "' + mw.config.get( 'wgPageFormsScriptPath' ) + '/skins/loading.gif'
		+ '" id="loading-' + this.id + '">' );
		$loadingIcon.hide();
		$( '#' + element.attr('id') ).parent().append( $loadingIcon );
	};
	/*
	 * Returns options to be set by select2
	 *
	 * @return {object} opts
	 *
	 */
	tokens_proto.setOptions = function() {
		const self = this;
		let input_id = this.id;
		const opts = {};
		opts.language = {};
		input_id = "#" + input_id;
		const input_tagname = $(input_id).prop( "tagName" );
		const autocomplete_opts = this.getAutocompleteOpts();
		opts.escapeMarkup = function (m) {
			return self.escapeMarkupAndAddHTML(m);
		};
		if ( autocomplete_opts.autocompletedatatype !== undefined ) {
			opts.ajax = this.getAjaxOpts();
			opts.minimumInputLength = 1;
			opts.language.inputTooShort = function() {
				return mw.msg( "pf-autocomplete-input-too-short", opts.minimumInputLength );
			};
		} else if ( input_tagname === "SELECT" ) {
			opts.data = this.getData( autocomplete_opts.autocompletesettings );
		}
		const wgPageFormsAutocompleteOnAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );
		if ( !wgPageFormsAutocompleteOnAllChars ) {
			opts.matcher = function( term, text ) {
				const folded_term = pf.select2.base.prototype.removeDiacritics( term.term ).toUpperCase();
				const folded_text = pf.select2.base.prototype.removeDiacritics( text.text ).toUpperCase();
				const position = folded_text.indexOf(folded_term);
				const position_with_space = folded_text.indexOf(" " + folded_term);
				if ( (position !== -1 && position === 0 ) || position_with_space !== -1 ) {
					return text;
				} else {
					return null;
				}
			};
		}
		opts.templateResult = function( result ) {
			let term = "";
			const inputData = $( input_id ).data("select2");
			if ( inputData.results.lastParams !== undefined ){
				term = inputData.results.lastParams.term;
			}
			if ( term === "" || term === undefined ) {
				term = inputData.$dropdown[0].textContent;
			}
			if ( term === "" || term === undefined ) {
				const htmlElements = inputData.$selection[0].children[0].children;
				term = htmlElements[htmlElements.length - 1].children[0].value;
			}
			return pf.select2.base.prototype.textHighlight( result.id, term );
		};
		opts.language.searching = function() {
			return mw.msg( "pf-autocomplete-searching" );
		};
		opts.placeholder = $(input_id).attr( "placeholder" );

		let size = $(input_id).attr("data-size");
		if ( size === undefined ) {
			size = '100'; //default value
		}
		opts.containerCss = { 'min-width': size };
		opts.containerCssClass = 'pf-select2-container';
		opts.dropdownCssClass = 'pf-select2-dropdown';
		if( !this.existingValuesOnly ){
			opts.tags = true;
		}
		opts.multiple = true;
		opts.width= NaN; // A helpful way to expand tokenbox horizontally
		opts.tokenSeparators = this.getDelimiter($(input_id));
		const maxvalues = $(input_id).attr( "maxvalues" );
		if ( maxvalues !== undefined ) {
			opts.maximumSelectionLength = maxvalues;
			opts.language.maximumSelected = function() {
				return mw.msg( "pf-autocomplete-selection-too-big", maxvalues );
			};
		}
		// opts.selectOnClose = true;
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
		const input_id = "#" + this.id;
		const values = [];
		let i, data;
		const dep_on = this.dependentOn();
		if ( dep_on === null ) {
			if ( autocompletesettings === 'external data' ) {
				let name = $(input_id).attr(this.nameAttr($(input_id)));
				// Remove the final "[]".
				if (name.includes('[]')) {
					name = name.slice(0, Math.max(0, name.length - 2));
				}
				const wgPageFormsEDSettings = mw.config.get( 'wgPageFormsEDSettings' );
				const edgValues = mw.config.get( 'edgValues' );
				data = {};
				if ( wgPageFormsEDSettings[name].title !== undefined && wgPageFormsEDSettings[name].title !== "" ) {
					data.title = edgValues[wgPageFormsEDSettings[name].title];
					if ( data.title !== undefined && data.title !== null ) {
						i = 0;
						data.title.forEach(() => {
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
							data.image.forEach(() => {
								values[i].image = data.image[i];
								i++;
							});
						}
					}
					if ( wgPageFormsEDSettings[name].description !== undefined && wgPageFormsEDSettings[name].description !== "" ) {
						data.description = edgValues[wgPageFormsEDSettings[name].description];
						i = 0;
						if ( data.description !== undefined && data.description !== null ) {
							data.description.forEach(() => {
								values[i].description = data.description[i];
								i++;
							});
						}
					}
				}

			} else {
				const wgPageFormsAutocompleteValues = mw.config.get( 'wgPageFormsAutocompleteValues' );
				data = wgPageFormsAutocompleteValues[autocompletesettings];
				//Convert data into the format accepted by Select2
				if ( data !== undefined && data !== null ) {
					for (const key in data) {
						values.push({
							id: data[key], text: data[key]
						});
					}
				}
			}
		} else { // Dependent field autocompletion
			const dep_field_opts = this.getDependentFieldOpts( dep_on );
			let my_server = mw.config.get( 'wgScriptPath' ) + "/api.php";
			my_server += "?action=pfautocomplete&format=json&property=" + dep_field_opts.prop +
				"&baseprop=" + dep_field_opts.base_prop + "&basevalue=" + dep_field_opts.base_value;
			$.ajax({
				url: my_server,
				dataType: 'json',
				async: false,
				success: function(value) {
					// Convert data into the format accepted by Select2.
					value.pfautocomplete.forEach( (item) => {
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
	 * remote autocompletion of tokens
	 *
	 * @return {object} ajaxOpts
	 *
	 */
	tokens_proto.getAjaxOpts = function() {
		const input_id = this.id;
		const autocomplete_opts = this.getAutocompleteOpts();
		const data_source = autocomplete_opts.autocompletesettings.split(',')[0];
		let my_server = mw.util.wikiScript( 'api' );
		const autocomplete_type = autocomplete_opts.autocompletedatatype;
		if ( autocomplete_type === 'cargo field' ) {
			const table_and_field = data_source.split('|');
			my_server += "?action=pfautocomplete&format=json&cargo_table=" + table_and_field[0] + "&cargo_field=" + table_and_field[1];
			if ( table_and_field.length > 2 ) {
				my_server += '&cargo_where=' + table_and_field[2];
			}
		} else {
			my_server += "?action=pfautocomplete&format=json&" +
				autocomplete_opts.autocompletedatatype + "=" +
				encodeURIComponent( data_source );
		}

		const ajaxOpts = {
			url: my_server,
			dataType: 'json',
			data: function (term) {
				$( '#loading-' + input_id ).show();
				const reqParams = { substr: term.term }; // search term
				if ( autocomplete_type === 'wikidata' ) {
					// Support for getting query values from an existing field in the form
					let dsource_copy = data_source;
					const terms = dsource_copy.split( "&" );
					terms.forEach( (element) => {
						const subTerms = element.split( "=" );
						const matches = subTerms[1].match( /\[(.*?)\]/ );
						if ( matches ) {
							const dep_value = $( '[name="' + subTerms[1] + '"]' ).val();
							if ( dep_value && dep_value.trim().length ) {
								dsource_copy = dsource_copy.replace( subTerms[1], dep_value );
							}
							return;
						}
					} );
					reqParams[ 'wikidata' ] = dsource_copy;
				}
				return reqParams;
			},
			processResults: function (data) { // parse the results into the format expected by Select2.
				if (data.pfautocomplete !== undefined) {
					$( '#loading-' + input_id ).hide();
					data.pfautocomplete.forEach( (item) => {
						if (item.displaytitle !== undefined) {
							let displayTitle;
							if (item.title === item.displaytitle) {
								displayTitle = item.title;
							} else {
								const containsTitleInParentheses = item.displaytitle.includes("(" + item.title + ")");
								displayTitle = containsTitleInParentheses
									? item.displaytitle
									: item.displaytitle + " (" + item.title + ")";
							}
							item.text = displayTitle;
							item.id = displayTitle
						} else {
							item.text = item.title;
							item.id = item.title;
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
	 * Returns delimiter for the token field
	 *
	 * @return {string} delimiter
	 *
	 */
	tokens_proto.getDelimiter = function ( element ) {
		let autoCompleteSettingsIntermediate;
		if(element.attr('autocompletesettings') === undefined){
			const tokenId = element.prevObject[0].firstElementChild.id;
			autoCompleteSettingsIntermediate = $('#'+tokenId).attr('autocompletesettings');
		} else {
			autoCompleteSettingsIntermediate = element.attr('autocompletesettings');
		}
		const field_values = autoCompleteSettingsIntermediate.split( ',' );
		let delimiter = ",";
		if (field_values[1] === 'list' && field_values[2] !== undefined && field_values[2] !== "") {
			delimiter = field_values[2];
		}

		return delimiter;
	};

	pf.select2.tokens.prototype = tokens_proto;

}( jQuery, mediaWiki, pageforms ) );
