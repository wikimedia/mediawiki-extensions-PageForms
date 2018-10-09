/**
 * Overrides of PF_jsGrid.js for use in Special:MultiPageEdit.
 *
 * @author Yashdeep Thorat
 * @author Yaron Koren
 */
/* global jsGrid, mw */
/*jshint esversion: 6 */
(function(jsGrid, $, undefined) {

	// Create month selector dropdown.
	function multiPageEditBuildSelect( currentMonth ) {
		var monthNames = mw.config.get('wgMonthNamesShort');
		var str = '<select class="pf_jsGrid_month" style=" width: 100% !important; font-size:14px;">';
		for (var val=0; val<=12; val++) {
			var val2;
			if (val < 10) { //Adds a leading 0 to single digit months, ex 01 instead of 1.
				val2 = "0" + val;
			} else {
				val2 = val;
			}
			var option = '<option ';
			if (val === currentMonth) {
				option += 'selected="selected" ';
			}
			option += 'value="' + val2 + '">' + monthNames[val] + '</option>';
			str += option;
		}
		str += '</select>';
		return str;
	}

	var PFDateField = function(config) {
		jsGrid.Field.call(this, config);
	};

	PFDateField.prototype = new jsGrid.Field({
		sorter: function(date1, date2) {
			return new Date(date1) - new Date(date2);
		},

		itemTemplate: function(value) {
			return value;
		},

		insertTemplate: function(value) {
			var html_day = '<div style="float:left; width:19%;"><label style="display:block; text-align:center; font- size:14px;">DD:</label><input class="pf_jsGrid_day" style=" font-size:14px; " type="text" value="" placeholder="DD"></input></div>';
			var html_year = '<div style="float:left; width:29%;"><label style="display:block; text-align:center; width:29%; font-size:14px;">YYYY:</label><input class="pf_jsGrid_year" style=" font-size:14px; " type="text" value="" placeholder="YYYY"></input></div>';
			var html_month = '<div style="float:left; width:48%; margin-left:2%; margin-right:2%;"><label style="display:block; text-align:center; font-size:14px;">MM:</label>' + multiPageEditBuildSelect(0) + '</div>';
			var fullDateInputHTML = '<div class="pf_jsGrid_ymd_form">';
			if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
				fullDateInputHTML += html_month + html_day + html_year;
			} else {
				fullDateInputHTML += html_day + html_month + html_year;
			}
			fullDateInputHTML += '</div>';
			this.fullDateInputInsertHTML = $( fullDateInputHTML );
			return this.fullDateInputInsertHTML;
		},

		editTemplate: function(value) {
			var display_day_of_month = '';
			var display_year = '';
			var display_month = 0;
			var dateValue, dateFormat;

			// These both sometimes happen.
			if ( value === undefined || value === '' ) {
				value = null;
			}

			if ( value === null ) {
				dateValue = null;
			} else if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
				dateValue = value;
				if ( /^\d+$/.test( dateValue ) && dateValue.length < 8 ){
					dateFormat = 1;	//Year only
					// Add a fake day and month that will be ignored later
					// so that it's a valid date format in javascript
					dateValue = "January 01," + dateValue;
				} else {
					var spaceCount = ( dateValue.match(/ /g) || [] ).length;
					if ( spaceCount === 1 ) {
						dateFormat = 2;	//Month and Year only
						// Add a fake day that will be ignored later
						// so that it's a valid date format in javascript
						var temp = dateValue.split(' ');
						dateValue = temp.join(' 01,');
					} else {
						dateFormat = 3; //Complete date
					}
				}
			} else {
				dateValue = value.replace( /\//g, '-' );
				if ( /^\d+$/.test( dateValue ) && dateValue.length < 8 ){
					dateFormat = 1;	//Year only
					// Add a fake day and month that will be ignored later
					// so that it's a valid date format in javascript
					dateValue = dateValue + "-01-01";
				} else {
					var hyphenCount = ( dateValue.match(/-/g) || [] ).length;
					if ( hyphenCount === 1 ) {
						dateFormat = 2;	//Month and Year only
						// Add a fake day that will be ignored later
						// so that it's a valid date format in javascript
						dateValue = dateValue + "-01";
					} else {
						dateFormat = 3; //Complete date
					}
				}
			}
			if ( value !== null ) {
				var dateObject = new Date( dateValue );
				display_day_of_month = dateObject.getDate();
				display_year = dateObject.getFullYear();
				display_month = dateObject.getMonth();
			}
			var fullDateInputHTML = '<div class="pf_jsGrid_ymd_form">';
			var monthElement;
			var dayElement;
			if ( value === null ) {
				dayElement = '';
				monthElement = multiPageEditBuildSelect(0);
			} else if ( dateFormat === 1 ) {
				dayElement = '';
				monthElement = multiPageEditBuildSelect(0);
			} else if ( dateFormat === 2 ) {
				dayElement = '';
				monthElement = multiPageEditBuildSelect(display_month + 1);
			} else {
				dayElement = display_day_of_month;
				monthElement = multiPageEditBuildSelect(display_month + 1);
			}
			var html_day = '<div style="float:left; width:19%;"><label style="display:block; text-align:center; font-size:14px;">DD:</label><input class="pf_jsGrid_day" style=" font-size:14px; " type="text" value="' + dayElement + '" placeholder="DD"></input></div>';
			var html_month = '<div style="float:left; width:48%; margin-left:2%; margin-right:2%;"><label style="display:block; text-align:center; font-size:14px;">MM:</label>' + monthElement + '</div>';
			var html_year = '<div style="float:left; width:29%;"><label style="display:block; text-align:center; width:29%; font-size:14px;">YYYY:</label><input class="pf_jsGrid_year" style=" font-size:14px; " type="text" value=' + display_year + '></input></div>';

			if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
				fullDateInputHTML += html_month + html_day + html_year;
			} else {
				fullDateInputHTML += html_day + html_month + html_year;
			}
			fullDateInputHTML += '</div>';
			this.fullDateInputEditHTML = $( fullDateInputHTML );
			return this.fullDateInputEditHTML;
		},

		insertValue: function() {
			var Insert_year = this.fullDateInputInsertHTML.find(".pf_jsGrid_year").val();
			var Insert_month = this.fullDateInputInsertHTML.find(".pf_jsGrid_month").val();
			var Insert_day_of_month = this.fullDateInputInsertHTML.find(".pf_jsGrid_day").val();
			if ( Insert_year === undefined || Insert_year === "" ) {
				return null;
			}
			if ( Insert_month === '00' && Insert_day_of_month !== '' ) {
				return null;
			}
			var ret, day, month;
			if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
				var monthNames = mw.config.get('wgPageFormsContLangMonths');
				day = ( Insert_day_of_month === '' ) ? '' : Insert_day_of_month + ", ";
				month = ( Insert_month === '00' ) ? '' : monthNames[parseInt( Insert_month )] + " ";
				ret = month + day + Insert_year;
			} else {
				day = ( Insert_day_of_month === '' ) ? '' : "/" + Insert_day_of_month;
				month = ( Insert_month === '00' ) ? '' : "/" + Insert_month;
				ret = Insert_year + month + day;
			}
			return ret;
		},

		editValue: function(value) {
			var Edit_year = this.fullDateInputEditHTML.find(".pf_jsGrid_year").val();
			var Edit_month = this.fullDateInputEditHTML.find(".pf_jsGrid_month").val();
			var Edit_day_of_month = this.fullDateInputEditHTML.find(".pf_jsGrid_day").val();
			if ( Edit_year === undefined || Edit_year === "" ) {
				return null;
			}
			if ( Edit_month === '00' && Edit_day_of_month !== '' ) {
				return null;
			}
			var ret, day, month;
			if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
				var monthNames = mw.config.get('wgPageFormsContLangMonths');
				day = ( Edit_day_of_month === '' ) ? '' : Edit_day_of_month + ", ";
				month = ( Edit_month === '00' ) ? '' : monthNames[parseInt( Edit_month )] + " ";
				ret = month + day + Edit_year;
			} else {
				day = ( Edit_day_of_month === '' ) ? '' : "/" + Edit_day_of_month;
				month = ( Edit_month === '00' ) ? '' : "/" + Edit_month;
				ret = Edit_year + month + day;
			}
			return ret;
		}
	});

	jsGrid.fields.date = PFDateField;

	/**
	 * The following code handles the 'combobox' input type within the grid.
	 * insertTemplate preprocesses the value and returns it to the grid cell to display;
	 * editTemplate/insertTemplate generate the edition/insertion forms;
	 * editValue/insertValue is in charge of putting the final values into the grid.
	 */

	var PFComboBoxField = function(config) {
		jsGrid.Field.call(this, config);
	};

	PFComboBoxField.prototype = new jsGrid.Field({

		itemTemplate: function(value) {
			return value;
		},

		insertTemplate: function(value) {
			var autocompletedatatype = "";
			if ( this.autocompletedatatype !== undefined ) {
				autocompletedatatype = 'autocompletedatatype="' + this.autocompletedatatype + '"';
			}
			var inputHTML = '<input id="insertjsGridComboBox" class="pfCombobox" autocompletesettings="' + this.autocompletesettings + '" size="35" ' + autocompletedatatype + '>';
			return inputHTML;
		},

		editTemplate: function(value) {
			var autocompletedatatype = "";
			if ( this.autocompletedatatype !== undefined ) {
				autocompletedatatype = 'autocompletedatatype="' + this.autocompletedatatype + '"';
			}
			var inputHTML = '<input id="jsGridComboBox" class="pfCombobox" value="' + value + '" autocompletesettings="' + this.autocompletesettings + '" size="35" ' + autocompletedatatype + '>';
			return inputHTML;
		},

		insertValue: function() {
			return $('#insertjsGridComboBox').val();
		},

		editValue: function(value) {
			return $('#jsGridComboBox').val();
		}
	});

	jsGrid.fields.combobox = PFComboBoxField;

	/**
	 * The following code handles the 'tokens' input type within the grid.
	 * insertTemplate preprocesses the value and returns it to the grid cell to display;
	 * editTemplate/insertTemplate generate the edition/insertion forms;
	 * editValue/insertValue is in charge of putting the final values into the grid.
	 */

	var PFTokensField = function(config) {
		jsGrid.Field.call(this, config);
	};

	PFTokensField.prototype = new jsGrid.Field({

		itemTemplate: function(value) {
			return value;
		},

		insertTemplate: function(value) {
			var autocompletedatatype = "";
			if ( this.autocompletedatatype !== undefined ) {
				autocompletedatatype = 'autocompletedatatype="' + this.autocompletedatatype + '"';
			}
			var inputHTML = '<input id="insertjsGridTokens" class="pfTokens createboxInput" autocompletesettings="' + this.autocompletesettings + '" size="50" ' + autocompletedatatype + '>';
			return inputHTML;
		},

		editTemplate: function(value) {
			var autocompletedatatype = "";
			if ( this.autocompletedatatype !== undefined ) {
				autocompletedatatype = 'autocompletedatatype="' + this.autocompletedatatype + '"';
			}
			var inputHTML = '<input id="jsGridTokens" class="pfTokens createboxInput" value="' + value + '" autocompletesettings="' + this.autocompletesettings + '" size="50" ' + autocompletedatatype + '>';
			return inputHTML;
		},

		insertValue: function() {
			return $('#insertjsGridTokens').val();
		},

		editValue: function(value) {
			return $('#jsGridTokens').val();
		}
	});

	jsGrid.fields.tokens = PFTokensField;

	// Override checkbox functions (and add valueIsYes()) to get correct
	// handling of Yes/No/etc. values.
	jsGrid.fields.checkbox.prototype.valueIsYes = function(value) {
		// This is sometimes called with an "undefined" value.
		if ( value === undefined || value === null ) {
			return false;
		}

		value = value.toLowerCase();
		var possibleYesMessages = [
			mw.config.get( 'wgPageFormsContLangYes' ).toLowerCase(),
			// Add in '1', and some hardcoded English.
			'1', 'yes', 'true'
		];
		return ( possibleYesMessages.indexOf( value ) >= 0 );
	};

	jsGrid.fields.checkbox.prototype.itemTemplate = function(value) {
		return this._createCheckbox().prop({
			checked: this.valueIsYes( value ),
			disabled: true
		});
	};

	jsGrid.fields.checkbox.prototype.editTemplate = function(value) {
		if(!this.editing) {
			return this.itemTemplate(value);
		}

		var $result = this.editControl = this._createCheckbox();
		$result.prop("checked", this.valueIsYes( value ));
		return $result;
	};

	jsGrid.fields.checkbox.prototype.insertValue = function() {
		return this.insertControl.is(":checked") ?
			mw.config.get( 'wgPageFormsContLangYes' ) :
			mw.config.get( 'wgPageFormsContLangNo' );
	};

	jsGrid.fields.checkbox.prototype.editValue = function() {
		return this.editControl.is(":checked") ?
			mw.config.get( 'wgPageFormsContLangYes' ) :
			mw.config.get( 'wgPageFormsContLangNo' );
	};

}(jsGrid, jQuery));