/*
 * ext.pf.select2.base.js
 *
 * Base class to handle autocomplete for various input types using the Select2
 * JS library.
 *
 * @file
 *
 * @licence GNU GPL v2+
 * @author Jatin Mehta
 * @author Priyanshu Varshney
 * @author Yaron Koren
 */

( function ( $, mw, pf ) {
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
	pf.select2.base = function() {

	};

	pf.select2.base.prototype = {
		/*
		 * Applies select2 to the HTML element
		 *
		 * @param {HTMLElement} element
		 *
		 */
		apply: function( element ) {
			const existingValuesOnly = (element.attr("existingvaluesonly") == "true");
			this.existingValuesOnly = existingValuesOnly;
			this.id = element.attr( "id" );
			try {
				const opts = this.setOptions();
				// element.val() would be simpler, but for some
				// reason it returns the wrong value.
				let origValue = element.attr('value');
				// We call empty() in case this input was
				// modified due to "values dependent on", and
				// the old set of allowed values needs to
				// be removed.
				element.empty();
				const $input = element.select2(opts);

				// We need an empty string as the first option; otherwise,
				// if it's non-empty string, Select2 might pick it and
				// display it as its value even if we remove it.
				const newEmptyOption = new Option( "", "", false, false );
				$input.append(newEmptyOption).trigger('change');

				if ( origValue === undefined ) {
					origValue = "";
				}
				if ( this.getAutocompleteOpts().autocompletedatatype !== undefined && this.dependentOn() === null ) {
					const data = {
						id: origValue,
						text: origValue
					};
					// This is needed after the empty() call,
					// to create an option element to restore
					// correct value in remote autocompletion.
					const newOption = new Option(data.text, data.id, false, false);
					$input.append(newOption).trigger('change');
				}
				// This is required so that the existing value
				// can be displayed.
				$input.val(origValue).trigger('change');
				const inputData = $input.data("select2");
				let rawValue = "";

				$(inputData.dropdown.$searchContainer).on("keydown",(e) => {
					if ( existingValuesOnly ) {
						return ;
					}
					if ( e.keyCode === 9 ) { // Tab.
						const valHighlighted = inputData.$results.find('.select2-results__option--highlighted')[0];
						if ( valHighlighted !== undefined ){
							rawValue = valHighlighted.textContent;
						};
						if ( !$input.find( "option[value='" + rawValue + "']" ).length ) {
							// Does this ever get called?
							const newOption = new Option( rawValue, rawValue, false, false );
							$input.append(newOption).trigger( 'change' );
						}
						if ( rawValue !== '' ) {
							$input.val(rawValue).trigger("change");
						}
					}
				});
				element.on( "change", this.onChange );
			} catch (e) {
				window.console.log(e);
			}
		},
		/*
		 * Used to remove the Select2 applied to the HTML element;
		 * the selected value will remain preserved.
		 *
		 * @param {HTMLElement} element
		 *
		 */
		destroy: function( element ) {
			element.select2( "destroy" );
		},
	 	/*
		 * If a field is dependent on some other field in the form
		 * then it returns its name.
		 *
		 * @return {string}
		 *
		 */
		dependentOn: function() {
			const input_id = "#" + this.id;
			const name_attr = this.nameAttr( $(input_id) );
			const name = $(input_id).attr( name_attr );

			const wgPageFormsDependentFields = mw.config.get( 'wgPageFormsDependentFields' );
			for ( let i = 0; i < wgPageFormsDependentFields.length; i++ ) {
				const dependentFieldPair = wgPageFormsDependentFields[i];
				if ( dependentFieldPair[1] === name ) {
					return dependentFieldPair[0];
				}
			}
			return null;
		},
		/*
		 * Returns the array of names of fields in the form which are dependent
		 * on the field passed as a param to this function,
		 *
		 * @param {HTMLElement} element
		 *
		 * @return {associative array} dependent_on_me
		 *
		 */
		dependentOnMe: function( element ) {
			const name_attr = this.nameAttr(element);
			const name = element.attr( name_attr );
			const dependent_on_me = [];

			const wgPageFormsDependentFields = mw.config.get( 'wgPageFormsDependentFields' );
			for ( let i = 0; i < wgPageFormsDependentFields.length; i++ ) {
				const dependentFieldPair = wgPageFormsDependentFields[i];
				if ( dependentFieldPair[0] === name ) {
					dependent_on_me.push(dependentFieldPair[1]);
				}
			}

			return dependent_on_me;
		},
		/*
		 * Returns the name attribute of the field depending on
		 * whether it is a part of a multiple instance template or not
		 *
		 * @param {HTMLElement} element
		 *
		 * @return {string}
		 *
		 */
		nameAttr: function( element ) {
			return this.partOfMultiple( element ) ? "origname" : "name";
		},
		/*
		 * Checks whether the field is part of a multiple instance template or not
		 *
		 * @param {HTMLElement} element
		 *
		 * @return {boolean}
		 *
		 */
		partOfMultiple: function( element ) {
			return element.attr( "origname" ) !== undefined ? true : false;
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
			const input_id = "#" + this.id;
			const dep_field_opts = {};
			let $base_element;

			if ( this.partOfMultiple($(input_id)) ) {
				$base_element = $(input_id).closest( ".multipleTemplateInstance" )
					.find( '[origname ="' + dep_on + '" ]' );
			} else {
				$base_element = $('[name ="' + dep_on + '" ]');
			}
			dep_field_opts.base_value = $base_element.val();
			dep_field_opts.base_prop = mw.config.get( 'wgPageFormsFieldProperties' )[dep_on] ||
				$base_element.attr( "autocompletesettings" );
			dep_field_opts.prop = $(input_id).attr( "autocompletesettings" ).split( "," )[0];

			return dep_field_opts;
		},
		/*
		 * Gives autocomplete options for a field
		 *
		 * @return {object} autocomplete_opts
		 *
		 */
		getAutocompleteOpts: function() {
			const input_id = "#" + this.id;
			const autocomplete_opts = {};

			if ( $(input_id).attr( "autocompletesettings" ) === undefined ) {
				throw "Error: No autocomplete settings set for input " + input_id;
			}

			autocomplete_opts.autocompletedatatype = $(input_id).attr( "autocompletedatatype" );
			autocomplete_opts.autocompletesettings = $(input_id).attr( "autocompletesettings" );
			return autocomplete_opts;
		},

		/**
		 * Escape out any HTML, and then add our own HTML to display
		 * the correct bolding.
		 * The first part of this function is directly copied from
		 * Utils::escapeMarkup() in the Select2 code. @TODO: figure out
		 * how to just call that code directly.
		 *
		 * @param {Mixed} markup
		 * @return {Mixed}
		 */
		escapeMarkupAndAddHTML: function( markup ) {
			const replaceMap = {
				'\\': '&#92;',
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				'\'': '&#39;',
				'/': '&#47;'
			};

			// Do not try to escape the markup if it's not a string
			if (typeof markup !== 'string') {
				return markup;
			}

			const escapedMarkup = String(markup).replace(/[&<>"'\/\\]/g, (match) => replaceMap[match])

			const boldStart = String.fromCharCode(1);
			const boldEnd = String.fromCharCode(2);
			return '<span class="select2-match-entire">' +
				escapedMarkup
				.replace(boldStart, '<span class="select2-match"><b>')
				.replace(boldEnd, '</b></span>') +
				'</span>';
		},

		/*
		 * Refreshes the field if there is a change
		 * in the autocomplete values.
		 *
		 * @param {HTMLElement} element
		 *
		 */
		refresh: function( element ) {
			this.destroy($(element));
			this.apply($(element));
		},
		/*
		 * Removes diacritics from the string and replaces
		 * them with English characters.
		 * This code is basically copied from:
		 * http://jpfiddle.net/potherca/Gtmr2/
		 *
		 * @param {string} text
		 *
		 * @return {string}
		 *
		 */
		removeDiacritics: function( text ) {
			const diacriticsMap = { 'Á': 'A', 'Ă': 'A', 'Ắ': 'A', 'Ặ': 'A', 'Ằ': 'A', 'Ẳ': 'A', 'Ẵ': 'A', 'Ǎ': 'A', 'Â': 'A', 'Ấ': 'A', 'Ậ': 'A', 'Ầ': 'A', 'Ẩ': 'A', 'Ẫ': 'A', 'Ä': 'A', 'Ǟ': 'A', 'Ȧ': 'A', 'Ǡ': 'A', 'Ạ': 'A', 'Ȁ': 'A', 'À': 'A', 'Ả': 'A', 'Ȃ': 'A', 'Ā': 'A', 'Ą': 'A', 'Å': 'A', 'Ǻ': 'A', 'Ḁ': 'A', 'Ⱥ': 'A', 'Ã': 'A', 'Ꜳ': 'AA', 'Æ': 'AE', 'Ǽ': 'AE', 'Ǣ': 'AE', 'Ꜵ': 'AO', 'Ꜷ': 'AU', 'Ꜹ': 'AV', 'Ꜻ': 'AV', 'Ꜽ': 'AY', 'Ḃ': 'B', 'Ḅ': 'B', 'Ɓ': 'B', 'Ḇ': 'B', 'Ƀ': 'B', 'Ƃ': 'B', 'Ć': 'C', 'Č': 'C', 'Ç': 'C', 'Ḉ': 'C', 'Ĉ': 'C', 'Ċ': 'C', 'Ƈ': 'C', 'Ȼ': 'C', 'Ď': 'D', 'Ḑ': 'D', 'Ḓ': 'D', 'Ḋ': 'D', 'Ḍ': 'D', 'Ɗ': 'D', 'Ḏ': 'D', 'ǲ': 'D', 'ǅ': 'D', 'Đ': 'D', 'Ƌ': 'D', 'Ǳ': 'DZ', 'Ǆ': 'DZ', 'É': 'E', 'Ĕ': 'E', 'Ě': 'E', 'Ȩ': 'E', 'Ḝ': 'E', 'Ê': 'E', 'Ế': 'E', 'Ệ': 'E', 'Ề': 'E', 'Ể': 'E', 'Ễ': 'E', 'Ḙ': 'E', 'Ë': 'E', 'Ė': 'E', 'Ẹ': 'E', 'Ȅ': 'E', 'È': 'E', 'Ẻ': 'E', 'Ȇ': 'E', 'Ē': 'E', 'Ḗ': 'E', 'Ḕ': 'E', 'Ę': 'E', 'Ɇ': 'E', 'Ẽ': 'E', 'Ḛ': 'E', 'Ꝫ': 'ET', 'Ḟ': 'F', 'Ƒ': 'F', 'Ǵ': 'G', 'Ğ': 'G', 'Ǧ': 'G', 'Ģ': 'G', 'Ĝ': 'G', 'Ġ': 'G', 'Ɠ': 'G', 'Ḡ': 'G', 'Ǥ': 'G', 'Ḫ': 'H', 'Ȟ': 'H', 'Ḩ': 'H', 'Ĥ': 'H', 'Ⱨ': 'H', 'Ḧ': 'H', 'Ḣ': 'H', 'Ḥ': 'H', 'Ħ': 'H', 'Í': 'I', 'Ĭ': 'I', 'Ǐ': 'I', 'Î': 'I', 'Ï': 'I', 'Ḯ': 'I', 'İ': 'I', 'Ị': 'I', 'Ȉ': 'I', 'Ì': 'I', 'Ỉ': 'I', 'Ȋ': 'I', 'Ī': 'I', 'Į': 'I', 'Ɨ': 'I', 'Ĩ': 'I', 'Ḭ': 'I', 'Ꝺ': 'D', 'Ꝼ': 'F', 'Ᵹ': 'G', 'Ꞃ': 'R', 'Ꞅ': 'S', 'Ꞇ': 'T', 'Ꝭ': 'IS', 'Ĵ': 'J', 'Ɉ': 'J', 'Ḱ': 'K', 'Ǩ': 'K', 'Ķ': 'K', 'Ⱪ': 'K', 'Ꝃ': 'K', 'Ḳ': 'K', 'Ƙ': 'K', 'Ḵ': 'K', 'Ꝁ': 'K', 'Ꝅ': 'K', 'Ĺ': 'L', 'Ƚ': 'L', 'Ľ': 'L', 'Ļ': 'L', 'Ḽ': 'L', 'Ḷ': 'L', 'Ḹ': 'L', 'Ⱡ': 'L', 'Ꝉ': 'L', 'Ḻ': 'L', 'Ŀ': 'L', 'Ɫ': 'L', 'ǈ': 'L', 'Ł': 'L', 'Ǉ': 'LJ', 'Ḿ': 'M', 'Ṁ': 'M', 'Ṃ': 'M', 'Ɱ': 'M', 'Ń': 'N', 'Ň': 'N', 'Ņ': 'N', 'Ṋ': 'N', 'Ṅ': 'N', 'Ṇ': 'N', 'Ǹ': 'N', 'Ɲ': 'N', 'Ṉ': 'N', 'Ƞ': 'N', 'ǋ': 'N', 'Ñ': 'N', 'Ǌ': 'NJ', 'Ó': 'O', 'Ŏ': 'O', 'Ǒ': 'O', 'Ô': 'O', 'Ố': 'O', 'Ộ': 'O', 'Ồ': 'O', 'Ổ': 'O', 'Ỗ': 'O', 'Ö': 'O', 'Ȫ': 'O', 'Ȯ': 'O', 'Ȱ': 'O', 'Ọ': 'O', 'Ő': 'O', 'Ȍ': 'O', 'Ò': 'O', 'Ỏ': 'O', 'Ơ': 'O', 'Ớ': 'O', 'Ợ': 'O', 'Ờ': 'O', 'Ở': 'O', 'Ỡ': 'O', 'Ȏ': 'O', 'Ꝋ': 'O', 'Ꝍ': 'O', 'Ō': 'O', 'Ṓ': 'O', 'Ṑ': 'O', 'Ɵ': 'O', 'Ǫ': 'O', 'Ǭ': 'O', 'Ø': 'O', 'Ǿ': 'O', 'Õ': 'O', 'Ṍ': 'O', 'Ṏ': 'O', 'Ȭ': 'O', 'Ƣ': 'OI', 'Ꝏ': 'OO', 'Ɛ': 'E', 'Ɔ': 'O', 'Ȣ': 'OU', 'Ṕ': 'P', 'Ṗ': 'P', 'Ꝓ': 'P', 'Ƥ': 'P', 'Ꝕ': 'P', 'Ᵽ': 'P', 'Ꝑ': 'P', 'Ꝙ': 'Q', 'Ꝗ': 'Q', 'Ŕ': 'R', 'Ř': 'R', 'Ŗ': 'R', 'Ṙ': 'R', 'Ṛ': 'R', 'Ṝ': 'R', 'Ȑ': 'R', 'Ȓ': 'R', 'Ṟ': 'R', 'Ɍ': 'R', 'Ɽ': 'R', 'Ꜿ': 'C', 'Ǝ': 'E', 'Ś': 'S', 'Ṥ': 'S', 'Š': 'S', 'Ṧ': 'S', 'Ş': 'S', 'Ŝ': 'S', 'Ș': 'S', 'Ṡ': 'S', 'Ṣ': 'S', 'Ṩ': 'S', 'ẞ': 'SS', 'Ť': 'T', 'Ţ': 'T', 'Ṱ': 'T', 'Ț': 'T', 'Ⱦ': 'T', 'Ṫ': 'T', 'Ṭ': 'T', 'Ƭ': 'T', 'Ṯ': 'T', 'Ʈ': 'T', 'Ŧ': 'T', 'Ɐ': 'A', 'Ꞁ': 'L', 'Ɯ': 'M', 'Ʌ': 'V', 'Ꜩ': 'TZ', 'Ú': 'U', 'Ŭ': 'U', 'Ǔ': 'U', 'Û': 'U', 'Ṷ': 'U', 'Ü': 'U', 'Ǘ': 'U', 'Ǚ': 'U', 'Ǜ': 'U', 'Ǖ': 'U', 'Ṳ': 'U', 'Ụ': 'U', 'Ű': 'U', 'Ȕ': 'U', 'Ù': 'U', 'Ủ': 'U', 'Ư': 'U', 'Ứ': 'U', 'Ự': 'U', 'Ừ': 'U', 'Ử': 'U', 'Ữ': 'U', 'Ȗ': 'U', 'Ū': 'U', 'Ṻ': 'U', 'Ų': 'U', 'Ů': 'U', 'Ũ': 'U', 'Ṹ': 'U', 'Ṵ': 'U', 'Ꝟ': 'V', 'Ṿ': 'V', 'Ʋ': 'V', 'Ṽ': 'V', 'Ꝡ': 'VY', 'Ẃ': 'W', 'Ŵ': 'W', 'Ẅ': 'W', 'Ẇ': 'W', 'Ẉ': 'W', 'Ẁ': 'W', 'Ⱳ': 'W', 'Ẍ': 'X', 'Ẋ': 'X', 'Ý': 'Y', 'Ŷ': 'Y', 'Ÿ': 'Y', 'Ẏ': 'Y', 'Ỵ': 'Y', 'Ỳ': 'Y', 'Ƴ': 'Y', 'Ỷ': 'Y', 'Ỿ': 'Y', 'Ȳ': 'Y', 'Ɏ': 'Y', 'Ỹ': 'Y', 'Ź': 'Z', 'Ž': 'Z', 'Ẑ': 'Z', 'Ⱬ': 'Z', 'Ż': 'Z', 'Ẓ': 'Z', 'Ȥ': 'Z', 'Ẕ': 'Z', 'Ƶ': 'Z', 'Ĳ': 'IJ', 'Œ': 'OE', 'ᴀ': 'A', 'ᴁ': 'AE', 'ʙ': 'B', 'ᴃ': 'B', 'ᴄ': 'C', 'ᴅ': 'D', 'ᴇ': 'E', 'ꜰ': 'F', 'ɢ': 'G', 'ʛ': 'G', 'ʜ': 'H', 'ɪ': 'I', 'ʁ': 'R', 'ᴊ': 'J', 'ᴋ': 'K', 'ʟ': 'L', 'ᴌ': 'L', 'ᴍ': 'M', 'ɴ': 'N', 'ᴏ': 'O', 'ɶ': 'OE', 'ᴐ': 'O', 'ᴕ': 'OU', 'ᴘ': 'P', 'ʀ': 'R', 'ᴎ': 'N', 'ᴙ': 'R', 'ꜱ': 'S', 'ᴛ': 'T', 'ⱻ': 'E', 'ᴚ': 'R', 'ᴜ': 'U', 'ᴠ': 'V', 'ᴡ': 'W', 'ʏ': 'Y', 'ᴢ': 'Z', 'á': 'a', 'ă': 'a', 'ắ': 'a', 'ặ': 'a', 'ằ': 'a', 'ẳ': 'a', 'ẵ': 'a', 'ǎ': 'a', 'â': 'a', 'ấ': 'a', 'ậ': 'a', 'ầ': 'a', 'ẩ': 'a', 'ẫ': 'a', 'ä': 'a', 'ǟ': 'a', 'ȧ': 'a', 'ǡ': 'a', 'ạ': 'a', 'ȁ': 'a', 'à': 'a', 'ả': 'a', 'ȃ': 'a', 'ā': 'a', 'ą': 'a', 'ᶏ': 'a', 'ẚ': 'a', 'å': 'a', 'ǻ': 'a', 'ḁ': 'a', 'ⱥ': 'a', 'ã': 'a', 'ꜳ': 'aa', 'æ': 'ae', 'ǽ': 'ae', 'ǣ': 'ae', 'ꜵ': 'ao', 'ꜷ': 'au', 'ꜹ': 'av', 'ꜻ': 'av', 'ꜽ': 'ay', 'ḃ': 'b', 'ḅ': 'b', 'ɓ': 'b', 'ḇ': 'b', 'ᵬ': 'b', 'ᶀ': 'b', 'ƀ': 'b', 'ƃ': 'b', 'ɵ': 'o', 'ć': 'c', 'č': 'c', 'ç': 'c', 'ḉ': 'c', 'ĉ': 'c', 'ɕ': 'c', 'ċ': 'c', 'ƈ': 'c', 'ȼ': 'c', 'ď': 'd', 'ḑ': 'd', 'ḓ': 'd', 'ȡ': 'd', 'ḋ': 'd', 'ḍ': 'd', 'ɗ': 'd', 'ᶑ': 'd', 'ḏ': 'd', 'ᵭ': 'd', 'ᶁ': 'd', 'đ': 'd', 'ɖ': 'd', 'ƌ': 'd', 'ı': 'i', 'ȷ': 'j', 'ɟ': 'j', 'ʄ': 'j', 'ǳ': 'dz', 'ǆ': 'dz', 'é': 'e', 'ĕ': 'e', 'ě': 'e', 'ȩ': 'e', 'ḝ': 'e', 'ê': 'e', 'ế': 'e', 'ệ': 'e', 'ề': 'e', 'ể': 'e', 'ễ': 'e', 'ḙ': 'e', 'ë': 'e', 'ė': 'e', 'ẹ': 'e', 'ȅ': 'e', 'è': 'e', 'ẻ': 'e', 'ȇ': 'e', 'ē': 'e', 'ḗ': 'e', 'ḕ': 'e', 'ⱸ': 'e', 'ę': 'e', 'ᶒ': 'e', 'ɇ': 'e', 'ẽ': 'e', 'ḛ': 'e', 'ꝫ': 'et', 'ḟ': 'f', 'ƒ': 'f', 'ᵮ': 'f', 'ᶂ': 'f', 'ǵ': 'g', 'ğ': 'g', 'ǧ': 'g', 'ģ': 'g', 'ĝ': 'g', 'ġ': 'g', 'ɠ': 'g', 'ḡ': 'g', 'ᶃ': 'g', 'ǥ': 'g', 'ḫ': 'h', 'ȟ': 'h', 'ḩ': 'h', 'ĥ': 'h', 'ⱨ': 'h', 'ḧ': 'h', 'ḣ': 'h', 'ḥ': 'h', 'ɦ': 'h', 'ẖ': 'h', 'ħ': 'h', 'ƕ': 'hv', 'í': 'i', 'ĭ': 'i', 'ǐ': 'i', 'î': 'i', 'ï': 'i', 'ḯ': 'i', 'ị': 'i', 'ȉ': 'i', 'ì': 'i', 'ỉ': 'i', 'ȋ': 'i', 'ī': 'i', 'į': 'i', 'ᶖ': 'i', 'ɨ': 'i', 'ĩ': 'i', 'ḭ': 'i', 'ꝺ': 'd', 'ꝼ': 'f', 'ᵹ': 'g', 'ꞃ': 'r', 'ꞅ': 's', 'ꞇ': 't', 'ꝭ': 'is', 'ǰ': 'j', 'ĵ': 'j', 'ʝ': 'j', 'ɉ': 'j', 'ḱ': 'k', 'ǩ': 'k', 'ķ': 'k', 'ⱪ': 'k', 'ꝃ': 'k', 'ḳ': 'k', 'ƙ': 'k', 'ḵ': 'k', 'ᶄ': 'k', 'ꝁ': 'k', 'ꝅ': 'k', 'ĺ': 'l', 'ƚ': 'l', 'ɬ': 'l', 'ľ': 'l', 'ļ': 'l', 'ḽ': 'l', 'ȴ': 'l', 'ḷ': 'l', 'ḹ': 'l', 'ⱡ': 'l', 'ꝉ': 'l', 'ḻ': 'l', 'ŀ': 'l', 'ɫ': 'l', 'ᶅ': 'l', 'ɭ': 'l', 'ł': 'l', 'ǉ': 'lj', 'ſ': 's', 'ẜ': 's', 'ẛ': 's', 'ẝ': 's', 'ḿ': 'm', 'ṁ': 'm', 'ṃ': 'm', 'ɱ': 'm', 'ᵯ': 'm', 'ᶆ': 'm', 'ń': 'n', 'ň': 'n', 'ņ': 'n', 'ṋ': 'n', 'ȵ': 'n', 'ṅ': 'n', 'ṇ': 'n', 'ǹ': 'n', 'ɲ': 'n', 'ṉ': 'n', 'ƞ': 'n', 'ᵰ': 'n', 'ᶇ': 'n', 'ɳ': 'n', 'ñ': 'n', 'ǌ': 'nj', 'ó': 'o', 'ŏ': 'o', 'ǒ': 'o', 'ô': 'o', 'ố': 'o', 'ộ': 'o', 'ồ': 'o', 'ổ': 'o', 'ỗ': 'o', 'ö': 'o', 'ȫ': 'o', 'ȯ': 'o', 'ȱ': 'o', 'ọ': 'o', 'ő': 'o', 'ȍ': 'o', 'ò': 'o', 'ỏ': 'o', 'ơ': 'o', 'ớ': 'o', 'ợ': 'o', 'ờ': 'o', 'ở': 'o', 'ỡ': 'o', 'ȏ': 'o', 'ꝋ': 'o', 'ꝍ': 'o', 'ⱺ': 'o', 'ō': 'o', 'ṓ': 'o', 'ṑ': 'o', 'ǫ': 'o', 'ǭ': 'o', 'ø': 'o', 'ǿ': 'o', 'õ': 'o', 'ṍ': 'o', 'ṏ': 'o', 'ȭ': 'o', 'ƣ': 'oi', 'ꝏ': 'oo', 'ɛ': 'e', 'ᶓ': 'e', 'ɔ': 'o', 'ᶗ': 'o', 'ȣ': 'ou', 'ṕ': 'p', 'ṗ': 'p', 'ꝓ': 'p', 'ƥ': 'p', 'ᵱ': 'p', 'ᶈ': 'p', 'ꝕ': 'p', 'ᵽ': 'p', 'ꝑ': 'p', 'ꝙ': 'q', 'ʠ': 'q', 'ɋ': 'q', 'ꝗ': 'q', 'ŕ': 'r', 'ř': 'r', 'ŗ': 'r', 'ṙ': 'r', 'ṛ': 'r', 'ṝ': 'r', 'ȑ': 'r', 'ɾ': 'r', 'ᵳ': 'r', 'ȓ': 'r', 'ṟ': 'r', 'ɼ': 'r', 'ᵲ': 'r', 'ᶉ': 'r', 'ɍ': 'r', 'ɽ': 'r', 'ↄ': 'c', 'ꜿ': 'c', 'ɘ': 'e', 'ɿ': 'r', 'ś': 's', 'ṥ': 's', 'š': 's', 'ṧ': 's', 'ş': 's', 'ŝ': 's', 'ș': 's', 'ṡ': 's', 'ṣ': 's', 'ṩ': 's', 'ʂ': 's', 'ᵴ': 's', 'ᶊ': 's', 'ȿ': 's', 'ß': 'ss', 'ɡ': 'g', 'ᴑ': 'o', 'ᴓ': 'o', 'ᴝ': 'u', 'ť': 't', 'ţ': 't', 'ṱ': 't', 'ț': 't', 'ȶ': 't', 'ẗ': 't', 'ⱦ': 't', 'ṫ': 't', 'ṭ': 't', 'ƭ': 't', 'ṯ': 't', 'ᵵ': 't', 'ƫ': 't', 'ʈ': 't', 'ŧ': 't', 'ᵺ': 'th', 'ɐ': 'a', 'ᴂ': 'ae', 'ǝ': 'e', 'ᵷ': 'g', 'ɥ': 'h', 'ʮ': 'h', 'ʯ': 'h', 'ᴉ': 'i', 'ʞ': 'k', 'ꞁ': 'l', 'ɯ': 'm', 'ɰ': 'm', 'ᴔ': 'oe', 'ɹ': 'r', 'ɻ': 'r', 'ɺ': 'r', 'ⱹ': 'r', 'ʇ': 't', 'ʌ': 'v', 'ʍ': 'w', 'ʎ': 'y', 'ꜩ': 'tz', 'ú': 'u', 'ŭ': 'u', 'ǔ': 'u', 'û': 'u', 'ṷ': 'u', 'ü': 'u', 'ǘ': 'u', 'ǚ': 'u', 'ǜ': 'u', 'ǖ': 'u', 'ṳ': 'u', 'ụ': 'u', 'ű': 'u', 'ȕ': 'u', 'ù': 'u', 'ủ': 'u', 'ư': 'u', 'ứ': 'u', 'ự': 'u', 'ừ': 'u', 'ử': 'u', 'ữ': 'u', 'ȗ': 'u', 'ū': 'u', 'ṻ': 'u', 'ų': 'u', 'ᶙ': 'u', 'ů': 'u', 'ũ': 'u', 'ṹ': 'u', 'ṵ': 'u', 'ᵫ': 'ue', 'ꝸ': 'um', 'ⱴ': 'v', 'ꝟ': 'v', 'ṿ': 'v', 'ʋ': 'v', 'ᶌ': 'v', 'ⱱ': 'v', 'ṽ': 'v', 'ꝡ': 'vy', 'ẃ': 'w', 'ŵ': 'w', 'ẅ': 'w', 'ẇ': 'w', 'ẉ': 'w', 'ẁ': 'w', 'ⱳ': 'w', 'ẘ': 'w', 'ẍ': 'x', 'ẋ': 'x', 'ᶍ': 'x', 'ý': 'y', 'ŷ': 'y', 'ÿ': 'y', 'ẏ': 'y', 'ỵ': 'y', 'ỳ': 'y', 'ƴ': 'y', 'ỷ': 'y', 'ỿ': 'y', 'ȳ': 'y', 'ẙ': 'y', 'ɏ': 'y', 'ỹ': 'y', 'ź': 'z', 'ž': 'z', 'ẑ': 'z', 'ʑ': 'z', 'ⱬ': 'z', 'ż': 'z', 'ẓ': 'z', 'ȥ': 'z', 'ẕ': 'z', 'ᵶ': 'z', 'ᶎ': 'z', 'ʐ': 'z', 'ƶ': 'z', 'ɀ': 'z', 'ﬀ': 'ff', 'ﬃ': 'ffi', 'ﬄ': 'ffl', 'ﬁ': 'fi', 'ﬂ': 'fl', 'ĳ': 'ij', 'œ': 'oe', 'ﬆ': 'st', 'ₐ': 'a', 'ₑ': 'e', 'ᵢ': 'i', 'ⱼ': 'j', 'ₒ': 'o', 'ᵣ': 'r', 'ᵤ': 'u', 'ᵥ': 'v', 'ₓ': 'x' };

			return text.toString().replace(/[\u007F-\uFFFF]/g, (key) => diacriticsMap[key] || key);
		},
		textHighlight: function( text, term ) {
			if ( text === undefined ) {
				text = "";
			}
			let markup = "";
			const remove_diacritics = pf.select2.base.prototype.removeDiacritics;
			const no_diac_text = remove_diacritics(text);
			const no_diac_term = remove_diacritics(term);
			let start = no_diac_text.toUpperCase().indexOf(no_diac_term.toString().toUpperCase());
			if ( start !== 0 && !mw.config.get( 'wgPageFormsAutocompleteOnAllChars' ) ) {
				start = no_diac_text.toUpperCase().indexOf(" " + no_diac_term.toString().toUpperCase());
				if ( start !== -1 ) {
					start = start + 1;
				}
			}

			if ( start !== -1 ) {
				const boldStart = String.fromCharCode(1);
				const boldEnd = String.fromCharCode(2);
				markup = text.slice(0, Math.max(0, start)) + boldStart +
					text.substr(start,term.length) + boldEnd +
					text.substr(start + term.length, text.length);
			} else {
				markup = (text);
			}
			return markup;
		},
	};
}( jQuery, mediaWiki, pageforms ) );
