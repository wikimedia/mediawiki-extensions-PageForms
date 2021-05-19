/**
 * PF_ComboBoxInput.js
 *
 * JavaScript code to use OOUI ComboBoxInput widget for comboBox.
 *
 * @class
 * @extends OO.ui.ComboBoxInputWidget
 *
 * @licence GNU GPL v2+
 * @author Jatin Mehta
 * @author Priyanshu Varshney
 * @author Yaron Koren
 * @author Sahaj Khandelwal
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
        this.config['autocompletesettings'] = element.attr('autocompletesettings')
        this.setInputAttribute('autocompletesettings', this.config['autocompletesettings']);
        // Initialize values in the combobox
        this.setValues();
        // Bind the blur event to resize input according to the value
        this.$input.blur( () => {
            this.$element.css("width", this.getValue().length * 11);
        })
        this.$input.focus( () => {
            this.setValues();
        })
    };
    /**
     * Sets the values for combobox
     */
    pf.ComboBoxInput.prototype.setValues = function () {
        var input_id = "#" + this.getElementId();
        var values = [];
        var dep_on = this.dependentOn();
        var data, i;
        if (dep_on === null) {
            if (this.config['autocompletesettings'] === 'external data') {
                var name = $(this).attr(this.nameAttr($(input_id)))
                var wgPageFormsEDSettings = mw.config.get('wgPageFormsEDSettings');
                var edgValues = mw.config.get('edgValues');
                data = {};
                if (wgPageFormsEDSettings[name].title !== undefined && wgPageFormsEDSettings[name].title !== "") {
                    data.title = edgValues[wgPageFormsEDSettings[name].title];
                    if (data.title !== undefined && data.title !== null) {
                        i = 0;
                        data.title.forEach(function () {
                            values.push({
                                data: data.title[i], label: this.highlightText(data.title[i])
                            });
                            i++;
                        });
                    }
                    if (wgPageFormsEDSettings[name].image !== undefined && wgPageFormsEDSettings[name].image !== "") {
                        data.image = edgValues[wgPageFormsEDSettings[name].image];
                        i = 0;
                        if (data.image !== undefined && data.image !== null) {
                            data.image.forEach(function () {
                                values[i].image = data.image[i];
                                i++;
                            });
                        }
                    }
                    if (wgPageFormsEDSettings[name].description !== undefined && wgPageFormsEDSettings[name].description !== "") {
                        data.description = edgValues[wgPageFormsEDSettings[name].description];
                        i = 0;
                        if (data.description !== undefined && data.description !== null) {
                            data.description.forEach(function () {
                                values[i].description = data.description[i];
                                i++;
                            });
                        }
                    }
                }

            } else {
                var wgPageFormsAutocompleteValues = mw.config.get('wgPageFormsAutocompleteValues');
                data = wgPageFormsAutocompleteValues[this.config['autocompletesettings']];
                if (Array.isArray(data) || typeof data == 'object') {
                    // Convert data into the format accepted by ComboBoxInputWidget
                    for (var key in data) {
                        values.push({
                            data: data[key], label: this.highlightText(data[key])
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
                    var curValue = $('#' + this.getElementId()).attr('value');
                    if (curValue !== '' && curValue !== undefined && !Object.keys(data).includes(curValue)) {
                        values.push({
                            data: curValue, label: this.highlightText(curValue)
                        });
                    }
                }
            }
        } else { // Dependent field autocompletion
            var dep_field_opts = this.getDependentFieldOpts(dep_on);
            var my_server = mw.config.get('wgScriptPath') + "/api.php";
            my_server += "?action=pfautocomplete&format=json";
            // URL depends on whether Cargo or Semantic MediaWiki
            // is being used.
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
            var self = this

            $.ajax({
                url: my_server,
                dataType: 'json',
                async: false,
                success: function (response) {
                    if (response.pfautocomplete.length == 0) {
                        values.push({
                            label: mw.message('pf-select2-no-matches').text(), disabled: true
                        });
                    }
                    // Convert data into the format accepted by ComboBoxInputWidget
                    response.pfautocomplete.forEach(function (item) {
                        if (item.displaytitle !== undefined) {
                            values.push({
                                data: item.displaytitle, label: self.highlightText(item.displaytitle)
                            });
                        } else {
                            values.push({
                                data: item.title, label: self.highlightText(item.title)
                            });
                        }
                    });
                    return values;
                }
            });

        }
        this.setOptions(values);
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
     * @return {object} dep_field_opts
     *
     */
    pf.ComboBoxInput.prototype.getDependentFieldOpts = function (dep_on) {
        var input_id = "#" + this.getInputId();
        var dep_field_opts = {};
        var baseElement;
        if (this.partOfMultiple($(input_id))) {
            baseElement = $(input_id).closest(".multipleTemplateInstance")
                .find('[origname ="' + dep_on + '" ]');
        } else {
            baseElement = $('[name ="' + dep_on + '" ]');
        }
        dep_field_opts.base_value = baseElement.val();
        dep_field_opts.base_prop = mw.config.get('wgPageFormsFieldProperties')[dep_on] ||
            baseElement.attr("autocompletesettings");
        dep_field_opts.prop = $(input_id).attr("autocompletesettings").split(",")[0];

        return dep_field_opts;
    };
    /**
    * Returns the array of names of fields in the form which are dependent
    * on the field passed as a param to this function,
    *
    * @param {HTMLElement} element
    *
    * @return {associative array} dependent_on_me
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

    pf.ComboBoxInput.prototype.setInputAttribute = function (attr, value) {
        this.$input.attr(attr, value);
    };
}(jQuery, mediaWiki, pageforms));
