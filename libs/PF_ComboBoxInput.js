/**
 * PF_ComboBoxInput.js
 *
 * JavaScript code to use OOUI ComboBoxInput widget for comboBox.
 *
 * @class
 * @extends OO.ui.ComboBoxInputWidget
 *
 * @license GNU GPL v2+
 * @author Jatin Mehta
 * @author Priyanshu Varshney
 * @author Yaron Koren
 * @author Sahaj Khandelwal
 * @author Yash Varshney
 */

(function ($, mw, pf) {
    pf.ComboBoxInput = function (config) {
        this.config = config || {}
        OO.ui.ComboBoxInputWidget.call(this, config);
    };
    OO.inheritClass(pf.ComboBoxInput, OO.ui.ComboBoxInputWidget);
    pf.ComboBoxInput.prototype.apply = function (element) {
        // Apply ComboBoxInput to the element
        this.setInputAttribute('name', element.attr('name'));
        this.setInputAttribute('origname', element.attr('origname'));
        this.setInputId(element.attr('id'));
        this.setValue(element.val())
        this.config['autocompletesettings'] = element.attr('autocompletesettings');
        this.config['autocompletedatatype'] = element.attr('autocompletedatatype');
        this.config['existingvaluesonly'] = element.attr('existingvaluesonly');
        this.setInputAttribute('autocompletesettings', this.config['autocompletesettings']);;
        this.setInputAttribute('placeholder', element.attr('placeholder'));
        this.setInputAttribute('tabIndex', element.attr('tabindex'));
        // Initialize values in the combobox
        this.setValues();

        if (this.config.autocompletesettings == 'external data') {
            // this is especially set for dependent on settings
            // when the source field has external data autocompletion
            var input_id = "#" + this.getInputId();
            var name = $(input_id).attr(this.nameAttr($(input_id)));
            var positionOfBracket = name.indexOf('[');
            var data_autocomplete = name.substring(0,positionOfBracket)+'|'+name.substring(positionOfBracket+1,name.length-1);
            this.setInputAttribute('data-autocomplete',data_autocomplete);
        }
        // Bind the blur event to resize input according to the value
        this.$input.blur( () => {
            if ( !this.itemFound && this.config['existingvaluesonly'] ){
                this.setValue("");
            } else {
                this.$element.css("width", this.getValue().length * 11);
            }
        });
        this.$input.focus( () => {
            this.setValues();
        });
        this.$input.keyup( (event) => {
            if (event.keyCode !== 38 && event.keyCode !== 40 && event.keyCode !== 37 && event.keyCode !== 39) {
                this.setValues();
            }
        });
        this.$element.mouseup( () =>{
            this.setValues();
        })
    };
    /**
     * Sets the values for combobox
     */
    pf.ComboBoxInput.prototype.setValues = function () {
        var input_id = "#" + this.getInputId(),
            values = [],
            dep_on = this.dependentOn(),
            self = this,
            data,
            i,
            curValue,
            my_server,
            wgPageFormsAutocompleteOnAllChars = mw.config.get( 'wgPageFormsAutocompleteOnAllChars' );
        this.itemFound = false;
        if (this.config.autocompletedatatype !== undefined) {
            var data_source = this.config.autocompletesettings,
                data_type = this.config.autocompletedatatype;
            curValue = this.getValue();
            if (curValue.length == 0) {
                values.push({
                    data:self.getValue(), label: mw.message('pf-autocomplete-input-too-short',1).text(), disabled: true
                });
                this.setOptions(values);
                return;
            }

		    my_server = mw.util.wikiScript( 'api' );

            if (data_type === 'cargo field') {
                var table_and_field = data_source.split('|');
                my_server += "?action=pfautocomplete&format=json&cargo_table=" + table_and_field[0] + "&cargo_field=" + table_and_field[1] + "&substr=" + curValue;
            } else {
                my_server += "?action=pfautocomplete&format=json&" + data_type + "=" + data_source + "&substr=" + curValue;
            }
            $.ajax({
                url: my_server,
                dataType: 'json',
                success: function (Data) {
                    if (Data.pfautocomplete !== undefined) {
                        Data = Data.pfautocomplete;
                        if (Data.length == 0) {
                            values.push({
                                data:self.getValue(), label: mw.message('pf-autocomplete-no-matches').text(), disabled: true
                            });
                        } else {
                            for ( i = 0; i < Data.length; i++ ) {
                                if ( Data[i].title == self.getValue() ){
                                    self.itemFound = true;
                                }
                                values.push({
                                    data: Data[i].title, label: self.highlightText(Data[i].title)
                                })
                            }
                        }
                    } else {
                        values.push({
                            data:self.getValue(), label: mw.message('pf-autocomplete-no-matches').text(), disabled: true
                        });
                    }
                    self.setOptions(values);
                }
            });
        } else {
            if (dep_on === null) {
                if (this.config['autocompletesettings'] === 'external data') {
                    curValue = this.getValue();
                    var name = $(input_id).attr(this.nameAttr($(input_id)));
                    var wgPageFormsEDSettings = mw.config.get('wgPageFormsEDSettings');
                    var edgValues = mw.config.get('edgValues');
                    data = {};
                    if (wgPageFormsEDSettings[name].title !== undefined && wgPageFormsEDSettings[name].title !== "") {
                        data.title = edgValues[wgPageFormsEDSettings[name].title];
                        if (data.title !== undefined && data.title !== null) {
                            i = 0;
                            data.title.forEach(function () {
                                if (data.title[i] == curValue ){
                                    self.itemFound = true;
                                }
                                if (wgPageFormsAutocompleteOnAllChars) {
                                    if (self.getConditionForAutocompleteOnAllChars(data.title[i], curValue)) {
                                        values.push({
                                            data: data.title[i], label: self.highlightText(data.title[i])
                                        });
                                    }
                                } else {
                                    if (self.checkIfAnyWordStartsWithInputValue(data.title[i], curValue)) {
                                        values.push({
                                            data: data.title[i], label: self.highlightText(data.title[i])
                                        });
                                    }
                                }
                                i++;
                            });
                        }
                    }
                } else {
                    var wgPageFormsAutocompleteValues = mw.config.get('wgPageFormsAutocompleteValues');
                    data = wgPageFormsAutocompleteValues[this.config['autocompletesettings']];
                    curValue = this.getValue();
                    if (Array.isArray(data) || typeof data == 'object') {
                        if (wgPageFormsAutocompleteOnAllChars) {
                            for (let key in data) {
                                if ( data[key] == curValue ) {
                                    self.itemFound = true;
                                }
                                if (this.getConditionForAutocompleteOnAllChars(data[key], curValue )) {
                                    values.push({
                                        data: data[key], label: this.highlightText(data[key])
                                    });
                                }
                            }
                        } else {
                            for (let key in data) {
                                if ( data[key] == curValue ) {
                                    self.itemFound = true;
                                }
                                if (this.checkIfAnyWordStartsWithInputValue(data[key], curValue)) {
                                    values.push({
                                        data: data[key], label: this.highlightText(data[key])
                                    });
                                }
                            }
                        }
                    }
                }
            } else { // Dependent field autocompletion
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
                        success: function (response) {
                            if ( response.error !== undefined || response.pfautocomplete.length == 0 ) {
                                values.push({
                                    data:self.getValue(), label: mw.message('pf-autocomplete-no-matches').text(), disabled: true
                                });
                                return values;
                            }
                            response.pfautocomplete.forEach(function (item) {
                                curValue = self.getValue();
                                if ( item.displaytitle == curValue || item.title == curValue ) {
                                    self.itemFound = true;
                                }
                                if (wgPageFormsAutocompleteOnAllChars) {
                                    if (item.displaytitle !== undefined) {
                                        if (self.getConditionForAutocompleteOnAllChars(item.displaytitle, curValue)){
                                            values.push({
                                                data: item.displaytitle, label: self.highlightText(item.displaytitle)
                                            });
                                        }
                                    } else {
                                        if (self.getConditionForAutocompleteOnAllChars(item.title,curValue)) {
                                            values.push({
                                                data: item.title, label: self.highlightText(item.title)
                                            });
                                        }
                                    }
                                } else {
                                    if (item.displaytitle !== undefined) {
                                        if (self.checkIfAnyWordStartsWithInputValue(item.displaytitle, curValue)) {
                                            values.push({
                                                data: item.displaytitle, label: self.highlightText(item.displaytitle)
                                            });
                                        }
                                    } else {
                                        if (self.checkIfAnyWordStartsWithInputValue(item.title, curValue)) {
                                            values.push({
                                                data: item.title, label: self.highlightText(item.title)
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
                        data:self.getValue(), label: mw.message('pf-autocomplete-no-matches').text(), disabled: true
                    });
                }
            }
            if (values.length == 0) {
                values.push({
                    data:self.getValue(), label: mw.message('pf-autocomplete-no-matches').text(), disabled: true
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
    pf.ComboBoxInput.prototype.nameAttr = function (element) {
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
    pf.ComboBoxInput.prototype.partOfMultiple = function (element) {
        return element.attr("origname") !== undefined ? true : false;
    };
    /**
     * If a field is dependent on some other field in the form
     * then it returns its name.
     *
     * @return {string}
     *
     */
    pf.ComboBoxInput.prototype.dependentOn = function () {
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
    pf.ComboBoxInput.prototype.getDependentFieldOpts = function (dep_on) {
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
            $baseElement.attr("autocompletesettings") == 'external data' ?
            $baseElement.attr("data-autocomplete") : $baseElement.attr("autocompletesettings");
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
    pf.ComboBoxInput.prototype.dependentOnMe = function () {
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

    pf.ComboBoxInput.prototype.highlightText = function (suggestion) {
        var searchTerm = this.getValue();
        var searchRegexp = new RegExp("(?![^&;]+;)(?!<[^<>]*)(" +
            searchTerm.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, "\\$1") +
            ")(?![^<>]*>)(?![^&;]+;)", "gi");
        var itemLabel = suggestion;
        var loc = itemLabel.search(searchRegexp);
        var t;

        if (loc >= 0) {
            t = itemLabel.substr(0, loc) +
                '<strong>' + itemLabel.substr(loc, searchTerm.length) + '</strong>' +
                itemLabel.substr(loc + searchTerm.length);
        } else {
            t = itemLabel;
        }
        return new OO.ui.HtmlSnippet(t);
    };

    pf.ComboBoxInput.prototype.checkIfAnyWordStartsWithInputValue = function(string, curValue) {
        var regex = new RegExp('\\b' + curValue.toLowerCase());
        return string.toLowerCase().match(regex) !== null;
    }

    pf.ComboBoxInput.prototype.getConditionForAutocompleteOnAllChars = function(string, curValue) {
        return string.toLowerCase().includes(curValue.toLowerCase())
    }

    pf.ComboBoxInput.prototype.setInputAttribute = function (attr, value) {
        this.$input.attr(attr, value);
    };
}(jQuery, mediaWiki, pageforms));
