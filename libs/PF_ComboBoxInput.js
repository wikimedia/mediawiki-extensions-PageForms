/**
 * PF_ComboBoxInput.js
 *
 * JavaScript code to use OOUI ComboBoxInput widget for comboBox.
 *
 * @class
 * @extends OO.ui.ComboBoxInputWidget
 * @param {jQuery} $
 * @param {Object} mw
 * @param {Object} pf
 * @license GNU GPL v2+
 * @author Jatin Mehta
 * @author Priyanshu Varshney
 * @author Yaron Koren
 * @author Sahaj Khandelwal
 * @author Yash Varshney
 * @author Dennis Groenewegen
 */
(function($, mw, pf) {
	var apiRequest = null;

	pf.ComboBoxInput = function(config) {
		this.config = config || {}
		OO.ui.ComboBoxInputWidget.call(this, config);
	};
	OO.inheritClass(pf.ComboBoxInput, OO.ui.ComboBoxInputWidget);

	/**
	 * Transform select/options to OOUI widget
	 *
	 * @param {HTMLElement} element
	 */
	pf.ComboBoxInput.prototype.apply = function(element) {
		var curVal = element.val(); // 'value'
		var curOptionLabel = element.find('option:selected').text();
		var curLabel = (curOptionLabel !== undefined) ? curOptionLabel : curVal;

		// Add hidden input containing the value
		var hiddenInput = new OO.ui.HiddenInputWidget({
			id: element.attr('id') + '-hidden',
			name: element.attr('name'),
			value: curVal
		});
		element.before(hiddenInput.$element);

		// Apply ComboBoxInput to the element. Omit name.
		this.setInputId(element.attr('id'));
		this.setValueAndLabel(curVal, curLabel);
		this.setInputAttribute('origname', element.attr('origname'));
		this.config['autocompletesettings'] = (element.attr('autocompletesettings') || '').replace(/\\'/g, "'");
		this.config['autocompletedatatype'] = element.attr('autocompletedatatype');
		this.config['existingvaluesonly'] = element.attr('existingvaluesonly');
		this.setInputAttribute('autocompletesettings', this.config['autocompletesettings']);
		this.setInputAttribute('placeholder', element.attr('placeholder'));
		this.setInputAttribute('tabIndex', element.attr('tabindex'));
		this.setInputAttribute('mappingproperty', element.attr('mappingproperty'));
		this.setInputAttribute('mappingtemplate', element.attr('mappingtemplate'));

		// Initialize values in the combobox
		this.setValues();

		if (this.config.autocompletesettings == 'external data') {
			// this is especially set for dependent on settings
			// when the source field has external data autocompletion
			var input_id = "#" + this.getInputId();
			var name = $(input_id).attr(this.nameAttr($(input_id)));
			var positionOfBracket = name.indexOf('[');
			var sliceFirst = name.slice(0, Math.max(0, positionOfBracket));
			// Previously using substring() :
			var sliceSecond = name.slice(positionOfBracket + 1, name.length - 1);
			var data_autocomplete = sliceFirst + '|' + sliceSecond;
			this.setInputAttribute('data-autocomplete', data_autocomplete);
		}

		this.bindEvents();

		var $loadingIcon = $('<img>').attr( {
			src: mw.config.get( 'wgPageFormsScriptPath' ) + '/skins/loading.gif',
			id: 'loading-' + this.getInputId()
		} );
		$loadingIcon.hide();
		$(document.getElementById(this.getInputId())).parent().append( $loadingIcon );

	};

	/**
	 * Bind events (blur, focus, keyup, focusout, etc.)
	 */
	pf.ComboBoxInput.prototype.bindEvents = function() {

		this.$input.blur(function() {
			var presentLabel = this.$input.val().trim();
			var selectedLabel = this.$input.attr('data-label').trim();
			if (presentLabel !== selectedLabel && this.config['existingvaluesonly']) {
				//Disallows non-existing value
				this.setValueAndLabel("", "");
			} else if (presentLabel !== selectedLabel) {
				//Update change to non-existing value
				this.setValueAndLabel(presentLabel, presentLabel);
				this.adjustWidth();
			} else {
				// Just resize input according to the value
				this.adjustWidth();
			}
		}.bind(this));

		this.$input.focus( function() {
			this.setValues();
		}.bind(this));

		this.$input.keyup( function(event) {
			if (event.keyCode !== 38 && event.keyCode !== 40 && event.keyCode !== 37 && event.keyCode !== 39) {
				this.setValues(false);
			}
		}.bind(this));

		// Mouseup - click input
		this.$element.mouseup( function(event) {
			// Avoid re-fetching values if the user clicks on the scrollbar.
			if ( $( event.target ).hasClass( 'oo-ui-labelElement-label' ) ) {
				this.setValues( false );
			}
		}.bind(this));

		this.$element.focusout( function() {
			$( '.combobox_map_feed' ).val( this.$input.val() );
		}.bind(this));

	};

	/**
	 * Sets the values for combobox
	 *
	 * @param {boolean} showAllValues
	 */
	pf.ComboBoxInput.prototype.setValues = function( showAllValues = true ) {
		var input_id = "#" + this.getInputId(),
			values = [],
			dep_on = this.dependentOn(),
			self = this,
			data,
			i,
			curValue,
			my_server,
			wgPageFormsAutocompleteOnAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );

		// First, handle "show on select" stuff.
		var $parentSpan = $(input_id).closest('span');
		if ( $parentSpan.hasClass('pfShowIfSelected') ) {
			mw.hook('pf.comboboxChange').fire($parentSpan);
		}

		this.itemFound = false;
		if (this.config.autocompletedatatype !== undefined) {
			var data_source = this.config.autocompletesettings,
				data_type = this.config.autocompletedatatype;
			curValue = this.getValue(); // current label or substring being typed
			var curHiddenVal = this.getHiddenInputValue(); //submitted

			if (curValue.length == 0) {
				values.push({
					data: self.getHiddenInputValue(),
					label: mw.message('pf-autocomplete-input-too-short', 1).text(),
					disabled: true
				});
				this.setOptions(values);
				return;
			}

			my_server = mw.util.wikiScript( 'api' );
			// Cargo field, wikidata, ...
			if (data_type === 'cargo field') {
				var table_and_field = data_source.split('|');
				my_server += "?action=pfautocomplete&format=json&cargo_table=" + table_and_field[0] + "&cargo_field=" + table_and_field[1] + "&substr=" + curValue;
				if ( table_and_field.length > 2 ) {
					my_server += '&cargo_where=' + table_and_field[2];
				}
			} else {
				if ( data_type === 'wikidata' ) {
					// Support for getting query values from an existing field in the form
					var terms = data_source.split( "&" );
					terms.forEach( function(element) {
						var subTerms = element.split( "=" );
						var matches = subTerms[1].match( /\[(.*?)\]/ );
						if ( matches ) {
							var dep_value = $( '[name="' + subTerms[1] + '"]' ).val();
							if ( dep_value && dep_value.trim().length ) {
								data_source = data_source.replace( subTerms[1], dep_value );
							}
							return;
						}
					} );
					data_source = encodeURIComponent( data_source );
				}
				my_server += "?action=pfautocomplete&format=json&" + data_type + "=" + data_source + "&substr=" + curValue;

				// Mapping property (Semantic MediaWiki)
				var mappingProperty = this.$input.attr('mappingproperty');
				if (typeof mappingProperty !== 'undefined' && mappingProperty !== false) {
					my_server += "&mappingproperty=" + mappingProperty;
				}
				// Mapping template (exclusive to autocompletion?)
				var mappingTemplate = this.$input.attr('mappingtemplate');
				if (typeof mappingTemplate !== 'undefined' && mappingTemplate !== false) {
					my_server += "&mappingtemplate=" + mappingTemplate;
				}

			}

			apiRequest = $.ajax({
				url: my_server,
				dataType: 'json',
				beforeSend: function() {
					if ( apiRequest !== null ) {
						apiRequest.abort();
					}
					$( '#loading-' + input_id.replace( '#', '' ) ).show();
				},
				success: function(Data) {
					$( '#loading-' + input_id.replace( '#', '' ) ).hide();
					if (Data.pfautocomplete !== undefined) {
						Data = Data.pfautocomplete;
						if (Data.length == 0) {
							values.push({
								data: self.getHiddenInputValue(),
								label: mw.message('pf-autocomplete-no-matches').text(),
								disabled: true
							});
						} else {
							for ( i = 0; i < Data.length; i++ ) {
								var optionVal = Data[i].title;
								var optionLabel = (Data[i].displaytitle !== undefined) ? Data[i].displaytitle : Data[i].title;
								if (optionLabel == curValue) {
									self.itemFound = true;
								}
								var item = {
									data: optionVal,
									label: optionLabel,
									highlighted: self.highlightText(optionLabel),
									disabled: false
								};
								values.push(item);
							}
						}
					} else {
						values.push({
							data: self.getHiddenInputValue(),
							label: mw.message('pf-autocomplete-no-matches').text(),
							disabled: true
						});
					}
					self.setOptions(values);
				}
			});
		} else {
			// Autocompletedatatype undefined
			if (dep_on === null) {
				if (this.config['autocompletesettings'] === 'external data') {
					// External data
					curValue = this.getValue();
					if ( showAllValues ) {
						curValue = "";
					}
					var name = $(input_id).attr(this.nameAttr($(input_id)));
					var wgPageFormsEDSettings = mw.config.get('wgPageFormsEDSettings');
					var edgValues = mw.config.get('edgValues');
					data = {};
					if (wgPageFormsEDSettings[name].title !== undefined && wgPageFormsEDSettings[name].title !== "") {
						data.title = edgValues[wgPageFormsEDSettings[name].title];
						if (data.title !== undefined && data.title !== null) {
							i = 0;
							data.title.forEach(function() {
								if ( data.title[i] == curValue ) {
									self.itemFound = true;
								}
								if (wgPageFormsAutocompleteOnAllChars) {
									if (self.getConditionForAutocompleteOnAllChars(data.title[i], curValue)) {
										values.push({
											data: data.title[i],
											label: data.title[i],
											highlighted: self.highlightText(data.title[i])
										});
									}
								} else {
									if (self.checkIfAnyWordStartsWithInputValue(data.title[i], curValue)) {
										values.push({
											data: data.title[i],
											label: data.title[i],
											highlighted: self.highlightText(data.title[i])
										});
									}
								}
								i++;
							});
						}
					}
				} else {
					// Local autocompletion, not dependent, not external data
					var wgPageFormsAutocompleteValues = mw.config.get('wgPageFormsAutocompleteValues');
					data = wgPageFormsAutocompleteValues[this.config['autocompletesettings']];
					curValue = this.getValue();
					if ( showAllValues ) {
						curValue = "";
					}
					var arrayType = (Array.isArray(data)) ? 'indexed' : 'associative';
					if (Array.isArray(data) || typeof data == 'object') {
						for (let key in data) {
							var optionVal = (arrayType == 'indexed') ? data[key] : key;
							var optionLabel = data[key];
							if (optionLabel == curValue) {
								self.itemFound = true;
							}
							if (
								(wgPageFormsAutocompleteOnAllChars && (this.getConditionForAutocompleteOnAllChars(optionLabel, curValue))) ||
								(this.checkIfAnyWordStartsWithInputValue(optionLabel, curValue))
							) {
								values.push({
									data: optionVal,
									label: optionLabel,
									highlighted: this.highlightText(optionLabel)
								});
							}
						}
					}
				}
			} else {
				// Dependent field autocompletion (dep_on is not null)
				var dep_field_opts = this.getDependentFieldOpts(dep_on);
				my_server = mw.config.get('wgScriptPath') + "/api.php";
				my_server += "?action=pfautocomplete&format=json";
				// URL depends on whether Cargo or Semantic MediaWiki
				// is being used.
				if (dep_field_opts.prop !== undefined && dep_field_opts.base_prop !== undefined && dep_field_opts.base_value !== undefined) {
					if (dep_field_opts.prop.indexOf('|') === -1) {
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
						success: function(response) {
							if ( response.error !== undefined || response.pfautocomplete.length == 0 ) {
								values.push({
									data: self.getHiddenInputValue(),
									label: mw.message('pf-autocomplete-no-matches').text(),
									disabled: true
								});
								return values;
							}
							response.pfautocomplete.forEach(function(item) {
								curValue = self.getValue();
								if ( item.displaytitle == curValue || item.title == curValue ) {
									self.itemFound = true;
								}
								if (wgPageFormsAutocompleteOnAllChars) {
									// dependent
									if (item.displaytitle !== undefined) {
										if (self.getConditionForAutocompleteOnAllChars(item.displaytitle, curValue)) {
											values.push({
												data: item.displaytitle,
												label: item.displaytitle,
												highlighted: self.highlightText(item.displaytitle)
											});
										}
									} else {
										// no displaytitle
										if (self.getConditionForAutocompleteOnAllChars(item.title, curValue)) {
											values.push({
												data: item.title,
												label: item.title,
												highlighted: self.highlightText(item.title)
											});
										}
									}
								} else {
									// dependent, wgPageFormsAutocompleteOnAllChars = false
									if (item.displaytitle !== undefined) {
										if (self.checkIfAnyWordStartsWithInputValue(item.displaytitle, curValue)) {
											values.push({
												data: item.displaytitle,
												label: item.displaytitle,
												highlighted: self.highlightText(item.displaytitle)
											});
										}
									} else {
										// displaytitle undefined
										if (self.checkIfAnyWordStartsWithInputValue(item.title, curValue)) {
											values.push({
												data: item.title,
												label: item.title,
												highlighted: self.highlightText(item.title)
											});
										}
									}
								}
							});
							return values;
						}
					});
				} else {
					// this condition will come when the wrong parameters are used in form definition
					values.push({
						data: self.getHiddenInputValue(),
						label: mw.message('pf-autocomplete-no-matches').text(),
						disabled: true
					});
				}
			}
			if (values.length == 0) {
				values.push({
					data: self.getHiddenInputValue(),
					label: mw.message('pf-autocomplete-no-matches').text(),
					disabled: true
				});
			}
			this.setOptions(values);
		}
	};

	/**
	 * Returns the name attribute of the field depending on
	 * whether it is a part of a multiple instance template or not
	 *
	 * @param {HTMLElement} element
	 *
	 * @return {string}
	 *
	 */
	pf.ComboBoxInput.prototype.nameAttr = function(element) {
		return this.partOfMultiple(element) ? "origname" : "name";
	};

	/**
	 * Checks whether the field is part of a multiple instance template or not
	 *
	 * @param {HTMLElement} element
	 *
	 * @return {boolean}
	 *
	 */
	pf.ComboBoxInput.prototype.partOfMultiple = function(element) {
		return element.attr("origname") !== undefined ? true : false;
	};

	/**
	 * If a field is dependent on some other field in the form
	 * then it returns its name.
	 *
	 * @return {string}
	 *
	 */
	pf.ComboBoxInput.prototype.dependentOn = function() {
		var input_id = "#" + this.getInputId();
		var name_attr = this.nameAttr($(input_id));
		var name = $(input_id).attr(name_attr);

		var wgPageFormsDependentFields = mw.config.get('wgPageFormsDependentFields');
		for (var i = 0; i < wgPageFormsDependentFields.length; i++) {
			var dependentFieldPair = wgPageFormsDependentFields[i];
			if (dependentFieldPair[1] === name) {
				return dependentFieldPair[0];
			}
		}
		return null;
	};

	/**
	 * Gives dependent field options which include
	 * property, base property and base value
	 *
	 * @param {string} dep_on
	 *
	 * @return {Object} dep_field_opts
	 *
	 */
	pf.ComboBoxInput.prototype.getDependentFieldOpts = function(dep_on) {
		var input_id = "#" + this.getInputId();
		var dep_field_opts = {};
		var $baseElement;
		if (this.partOfMultiple($(input_id))) {
			$baseElement = $(input_id).closest(".multipleTemplateInstance")
				.find('[origname ="' + dep_on + '" ]');
		} else {
			$baseElement = $('[name ="' + dep_on + '" ]');
		}
		dep_field_opts.base_value = $baseElement.val();
		dep_field_opts.base_prop = mw.config.get('wgPageFormsFieldProperties')[dep_on] ||
			(
				$baseElement.attr("autocompletesettings") == 'external data' ?
				$baseElement.attr("data-autocomplete") : $baseElement.attr("autocompletesettings")
			);
		dep_field_opts.prop = $(input_id).attr("autocompletesettings").split(",")[0];

		return dep_field_opts;
	};

	/**
	 * Returns the array of names of fields in the form which are dependent
	 * on the field passed as a param to this function,
	 *
	 * @param {HTMLElement} element
	 *
	 * @return {Array} dependent_on_me (associative array)
	 *
	 */
	pf.ComboBoxInput.prototype.dependentOnMe = function() {
		var input_id = "#" + this.getInputId();
		var name_attr = this.nameAttr($(input_id));
		var name = $(input_id).attr(name_attr);
		var dependent_on_me = [];
		var wgPageFormsDependentFields = mw.config.get('wgPageFormsDependentFields');
		for (var i = 0; i < wgPageFormsDependentFields.length; i++) {
			var dependentFieldPair = wgPageFormsDependentFields[i];
			if (dependentFieldPair[0] === name) {
				dependent_on_me.push(dependentFieldPair[1]);
			}
		}

		return dependent_on_me;
	};

	pf.ComboBoxInput.prototype.highlightText = function(suggestion) {
		var searchTerm = this.getValue();
		var searchRegexp = new RegExp("(?![^&;]+;)(?!<[^<>]*)(" +
			searchTerm.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, "\\$1") +
			")(?![^<>]*>)(?![^&;]+;)", "gi");
		var itemLabel = suggestion.toString();
		var loc = itemLabel.search(searchRegexp);
		var t;

		if (loc >= 0) {
			t = itemLabel.slice(0, Math.max(0, loc)) +
				'<strong>' + itemLabel.substr(loc, searchTerm.length) + '</strong>' +
				itemLabel.slice(loc + searchTerm.length);
		} else {
			t = itemLabel;
		}
		return new OO.ui.HtmlSnippet(t);
	};

	pf.ComboBoxInput.prototype.checkIfAnyWordStartsWithInputValue = function(string, curValue) {
		let wordSeparators = [
			'/', '(', ')', '|', 's'
		].map(function(p) {
			return "\\" + p
		}).concat('^', '-', "'", '"');
		let regex = new RegExp('(' + wordSeparators.join('|') + ')' + curValue.toLowerCase());
		return string.toString().toLowerCase().match(regex) !== null;
	};

	pf.ComboBoxInput.prototype.getConditionForAutocompleteOnAllChars = function(str, curStr) {
		var containsSubstr = str.toLowerCase().indexOf(curStr.toLowerCase()) !== -1;
		return containsSubstr;
	};

	pf.ComboBoxInput.prototype.setInputAttribute = function(attr, value) {
		if (typeof value !== 'undefined' && value !== false) {
			this.$input.attr(attr, value);
		}
	};

	/**
	 * Override default to handle menu item 'choose' event
	 * Update two inputs to clicked item
	 * Use getTitle not getLabel to get non-highlighted label
	 *
	 * @param {OO.ui.MenuOptionWidget} item Selected item
	 */
	pf.ComboBoxInput.prototype.onMenuChoose = function(item) {
		var inputVal = item.getData();
		var inputLabel = item.getTitle();
		var inputId = this.getInputId();
		this.$input.attr("data-input-id", inputId);
		this.setValueAndLabel(inputVal, inputLabel);
		this.adjustWidth();
	};

	/**
	 * Return hidden input associated with combobox, which contains the value
	 * Accepts id of combobox input
	 *
	 * @return {HTMLElement}
	 */
	pf.ComboBoxInput.prototype.getHiddenInput = function() {
		var inputId = this.getInputId();
		return this.$input.closest('.comboboxSpan').find('#' + inputId + '-hidden');
	};

	pf.ComboBoxInput.prototype.getHiddenInputValue = function() {
		var inputId = this.getInputId();
		var $hiddenInput = this.$input.closest('.comboboxSpan').find('#' + inputId + '-hidden');
		return $hiddenInput.val();
	};

	/**
	 * Set value and label for combobox and hidden input
	 *
	 * @param {string} val
	 * @param {string} label
	 */
	pf.ComboBoxInput.prototype.setValueAndLabel = function(val, label) {
		var hiddenInput = this.getHiddenInput();
		$(hiddenInput).val(val);
		this.setValue(label);
		this.setTitle(label);
		this.$input.attr('data-value', val); // required as reference
		this.$input.attr('data-label', label); // required as reference
		var stringType = (val == label) ? 'value' : 'label';
		this.updateStringType(stringType);
	};

	/**
	 * Override default to create options in dropdown
	 * Allow for values, labels, highlighted text and boolean 'disabled'
	 *
	 * @param {Object[]} options
	 * @return {OO.ui.Widget}
	 */
	pf.ComboBoxInput.prototype.setOptions = function(options) {
		this.getMenu()
			.clearItems()
			.addItems(options.map(function(opt) {
				var isDisabled = (opt.disabled !== undefined) ? opt.disabled : false;
				var label = (opt.label !== undefined) ? opt.label : opt.data;
				var highlighted = (opt.highlighted !== undefined) ? opt.highlighted : label;
				return new OO.ui.MenuOptionWidget({
					data: opt.data,
					label: highlighted,
					title: label,
					disabled: isDisabled
				});
			}));
		return this;
	};

	/**
	 * Allow for distinct styling of labels and values
	 * Assume 'label' when value and label are not identical
	 * Not fully reality-proof as displaytitle may be
	 * identical with pagename
	 *
	 * @param {string} newType
	 */
	pf.ComboBoxInput.prototype.updateStringType = function(newType) {
		var className = 'pf-string-type--' + newType;
		// The following classes are used here:
		// * pf-string-type--label
		// * pf-string-type--value
		this.$input.removeClass([ 'pf-string-type--label', 'pf-string-type--value' ]).addClass([ className ]);
		this.setInputAttribute('data-string-type', newType);
	};

	pf.ComboBoxInput.prototype.adjustWidth = function() {
		var suggWidth = this.getValue().length * 11;
		this.$element.css("width", "100%");
		var maxWidth = parseInt(this.$element.css("width"));
		var newWidth = (suggWidth >= maxWidth) ? maxWidth : suggWidth;
		this.$element.css("width", newWidth);
	};

}(jQuery, mediaWiki, pageforms));
