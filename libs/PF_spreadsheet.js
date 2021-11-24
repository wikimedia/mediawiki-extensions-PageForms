/**
 * Code to integrate the jExcel JavaScript library into Page Forms.
 *
 * @author Yaron Koren
 * @author Balabky9
 * @author Amr El-Absy
 */

// @TODO - make this based on the API limit, which in turn is based on whether the user has the "apihighlimits" right.
const numPagesToQuery = 50;
const saveIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-check oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.msg( 'upload-dialog-button-save' ) + '"></span>';
const cancelIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-close oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.msg( 'cancel' ) + '"></span>';
const addIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-add oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.msg( 'apisandbox-add-multi' ) + '"></span>';
const upIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-upTriangle oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + 'Raise' + '"></span>';
const downIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-downTriangle oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + 'Lower' + '"></span>';
const deleteIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-trash oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.msg( 'delete' ) + '"></span>';
const manageColumnTitle = '\u2699';
var dataValues = [];

( function( jexcel, mw ) {
	var baseUrl = mw.config.get( 'wgScriptPath' );
	mw.spreadsheets = {};

	// Handle any possible Boolean values from the wiki page.
	jexcel.prototype.valueIsYes = function(value) {
		if ( value === null ) {
			return false;
		}

		if ( typeof value === 'boolean' ) {
			return value;
		}

		if ( typeof value === 'string' ) {
			value = value.toLowerCase();
		}
		var possibleYesMessages = [
			mw.config.get( 'wgPageFormsContLangYes' ).toLowerCase(),
			// Add in '1', and some hardcoded English.
			'1', 'yes', 'true'
		];
		return ( possibleYesMessages.indexOf( value ) >= 0 );
	};

	jexcel.prototype.getjExcelValue = function( mwValue, columnAttributes ) {
		var date,
			monthNum;
		if ( mwValue == null ) {
			return null;
		}
		if ( columnAttributes['type'] == 'checkbox' ) {
			return jexcel.prototype.valueIsYes(mwValue);
		} else if ( columnAttributes['list'] == true ) {
			// The list delimiter unfortunately can't be set for
			// jExcel - it's hardcoded to a semicolon - and values
			// can't have spaces around them. So we have to
			// modify the current value for it to be handled
			// correctly.
			var individualValues = mwValue.split( columnAttributes['delimiter'] );
			return $.map( individualValues, $.trim ).join(';');
		} else if ( columnAttributes['type'] == 'date' ) {
			date = new Date( mwValue );
			// Avoid timezone strangeness.
			date.setTime( date.getTime() + 60000 * date.getTimezoneOffset());
			monthNum = date.getMonth() + 1;
			return date.getFullYear() + '-' + monthNum + '-' + date.getDate();
		} else if ( columnAttributes['type'] == 'datetime' ) {
			date = new Date( mwValue );
			// Avoid timezone strangeness, if what we're handling
			// is just a date.
			if ( ! mwValue.includes(':') ) {
				date.setTime( date.getTime() + 60000 * date.getTimezoneOffset());
			}
			monthNum = date.getMonth() + 1;
			return date.getFullYear() + '-' + monthNum + '-' + date.getDate() +
				' ' + date.getHours() + ':' + date.getMinutes();
		} else {
			return mwValue;
		}
	}

	jexcel.prototype.getMWValueFromCell = function( $cell, columnAttributes ) {
		var jExcelValue;
		if ( columnAttributes['type'] == 'checkbox' ) {
			jExcelValue = $cell.find('input').prop( 'checked' );
		} else {
			jExcelValue = $cell.html();
		}
		return jexcel.prototype.getMWValueFromjExcelValue( jExcelValue, columnAttributes );
	}

	jexcel.prototype.getMWValueFromjExcelValue = function( jExcelValue, columnAttributes ) {
		if ( jExcelValue == null ) {
			return null;
		}
		if ( columnAttributes['type'] == 'checkbox' ) {
			return ( jExcelValue == true ) ?
				mw.config.get( 'wgPageFormsContLangYes' ) :
				mw.config.get( 'wgPageFormsContLangNo' );
		} else if ( columnAttributes['list'] == true ) {
			var delimiter = columnAttributes['delimiter'] + ' ';
			return jExcelValue.replace(/;/g, delimiter);
		} else if ( columnAttributes['type'] == 'date' || columnAttributes['type'] == 'datetime' ) {
			return jExcelValue;
		} else {
			var mwValue = jExcelValue.replace( /\<br\>/g, "\n" );
			return mwValue;
		}
	}

	jexcel.prototype.generateQueryStringForSave = function( formName, templateName, pageName, rowValues, columns ) {
		var queryString = 'form=' + formName + '&target=' + encodeURIComponent( pageName );
		var curColumn;
		for ( var columnName in rowValues ) {
			if ( columnName == 'page' ) {
				continue;
			}
			for ( var columnNum in columns ) {
				if ( columns[columnNum]['title'] == columnName ) {
					curColumn = columns[columnNum];
					break;
				}
			}
			queryString += '&' + templateName + '[' + columnName + ']=' +
				encodeURIComponent( jexcel.prototype.getMWValueFromjExcelValue( rowValues[columnName], curColumn ) );
		}
		return queryString;
	}

	jexcel.prototype.saveChanges = function( spreadsheetID, templateName, pageName, newPageName, formName, rowNum, rowValues, columns, editMultiplePages ) {
		$("div#" + spreadsheetID + " table.jexcel td[data-y = " + rowNum + "]").not(".jexcel_row").each( function () {
			var columnNum = $(this).attr("data-x");
			var curColumn = columns[columnNum]['title'];
			var curValue = rowValues[curColumn];
			if ( rowValues[curColumn] !== undefined ) {
				mw.spreadsheets[spreadsheetID].setValue( this, curValue );
			}
		});

		if ( editMultiplePages === undefined ) {
			return;
		}

		var data = {
			action: 'pfautoedit',
			format: 'json',
			query: jexcel.prototype.generateQueryStringForSave( formName, templateName, pageName, rowValues, columns )
		};
		return $.ajax({
			type: 'POST',
			url: baseUrl + '/api.php',
			data: data,
			dataType: 'json',
			success: function(successData) {
				if ( newPageName !== '' && newPageName !== undefined && newPageName !== pageName ) {
					jexcel.prototype.movePage( pageName, newPageName );
				}
			}
		});
	}

	jexcel.prototype.getToken = function() {
		var url = baseUrl + '/api.php?action=query&format=json&meta=tokens&type=csrf';
		return $.post( url );
	}

	jexcel.prototype.movePage = function( fromPage, toPage ) {
		return $.when( jexcel.prototype.getToken() ).then( function successHandler( postResult ){
			var data = {
				token: postResult.query.tokens.csrftoken
			};
			var query = 'from=' + encodeURIComponent( fromPage ) + "&to=" + encodeURIComponent( toPage ) + "&movetalk&noredirect";
			return $.ajax( {
				type: 'POST',
				url: baseUrl + '/api.php?action=move&format=json&' + query,
				dataType: 'json',
				data: data
			} );
		});
	}

	jexcel.prototype.cancelChanges = function( spreadsheetID, rowValues, rowNum, columnNames ) {
		$("div#" + spreadsheetID + " table.jexcel td[data-y = " + rowNum + "]").not(".jexcel_row").each( function () {
			var columnNum = $(this).attr("data-x");
			var curColumn = columnNames[columnNum];
			if ( rowValues[curColumn] !== undefined ) {
				mw.spreadsheets[spreadsheetID].setValue( this, rowValues[curColumn] );
			} else {
				mw.spreadsheets[spreadsheetID].setValue( this, "" );
			}
		} );

		$("div#" + spreadsheetID + " td[data-y = " + rowNum + "] .save-changes").each( function () {
			$(this).parent().hide();
			$(this).parent().siblings('.mit-row-icons').show();
		} );
	}

	// Add a new page.
	jexcel.prototype.saveNewRow = function( spreadsheetID, templateName, formName, rowNum, pageName, rowValues, columns, editMultiplePages ) {
		var $manageCell = $( "div#" + spreadsheetID + " td[data-y=" + rowNum + "]" ).last();

		var spanContents = '<a href="#" class="save-changes">' + saveIcon + '</a> | ' +
			'<a href="#" class="cancel-changes">' + cancelIcon + '</a>';

		$manageCell.children('span.save-or-cancel')
			.attr('id', 'page-span-' + pageName)
			.html( spanContents )
			.hide();

		if ( editMultiplePages == undefined ) {
			$manageCell.children('.mit-row-icons').show();
			return;
		}

		var data = {
			action: 'pfautoedit',
			format: 'json',
			query: jexcel.prototype.generateQueryStringForSave( formName, templateName, pageName, rowValues, columns )
		};
		return $.ajax( {
			type: 'POST',
			url: baseUrl + '/api.php',
			data: data,
			dataType: 'json'
		} );
	}


	jexcel.prototype.deleteRow = function( spreadsheetID, rowNum ) {
		rowNum = parseInt(rowNum);
		mw.spreadsheets[spreadsheetID].deleteRow(rowNum);
		dataValues[spreadsheetID].splice(rowNum, 1);
	}

	jexcel.prototype.getAutocompleteAttributes = function ( cell ) {
		var autocompletedatatype = jQuery(cell).attr('data-autocomplete-data-type');
		var autocompletesettings = jQuery(cell).attr('data-autocomplete-settings');
		if ( autocompletedatatype == undefined || autocompletesettings == undefined ) {
			// that means we are in Special:MultipageEdit
			// here we take attributes from the column head,
			// to use other types of autocompletion( apart from
			// "cargo field" and "property" ), the attributes in
			// each cell can also be set.
			var data_x = jQuery(cell).attr('data-x');
			var $table = jQuery(cell).parents().find('table');
			autocompletedatatype = jQuery($table).find('thead td[data-x="'+data_x+'"]').attr('data-autocomplete-data-type');
			autocompletesettings = jQuery($table).find('thead td[data-x="'+data_x+'"]').attr('data-autocomplete-settings');
		}
		return {
			autocompletedatatype, autocompletesettings
		};
	}

	// If a field is dependent on some other field in the form
	// then it returns its name.
	jexcel.prototype.dependenton = function (origname) {
		var wgPageFormsDependentFields = mw.config.get('wgPageFormsDependentFields');
			for (var i = 0; i < wgPageFormsDependentFields.length; i++) {
				var dependentFieldPair = wgPageFormsDependentFields[i];
				if (dependentFieldPair[1] === origname) {
					return dependentFieldPair[0];
				}
			}
	};

	jexcel.prototype.getEditorForAutocompletion = function( inputType, x, y, autocompletedatatype, autocompletesettings, cell, type ) {
		var editor;
		var pfSpreadsheetAutocomplete = false,
			widget;
		var config = {
			data_x: x,
			data_y: y,
			autocompletedatatype: autocompletedatatype,
		};
		if ( autocompletedatatype == 'category' || autocompletedatatype == 'cargo field'
			|| autocompletedatatype == 'property' || autocompletedatatype == 'concept' ) {
			pfSpreadsheetAutocomplete = true;
			config['autocompletesettings'] = autocompletesettings;
			if ( inputType == 'combobox' ) {
				widget = new pf.SpreadsheetComboBoxInput(config);
			} else {
				widget = new pf.spreadsheetAutocompleteWidget(config);
			}
		} else if ( autocompletedatatype == 'dep_on' ) {
			// values dependent on
			var dep_on_field = jexcel.prototype.dependenton(cell.getAttribute('origname'));
			if ( dep_on_field !== null ) {
				pfSpreadsheetAutocomplete = true;
				config['autocompletesettings'] = cell.getAttribute('name');
				config['dep_on_field'] = dep_on_field;
				if ( inputType == 'combobox' ) {
					widget = new pf.SpreadsheetComboBoxInput(config);
				} else {
					widget = new pf.spreadsheetAutocompleteWidget(config);
				}
			} else {
				// this is probably the case where some parameters are set
				// in a wrong way in form defintion, in that case use the default jexcel editor
				pfSpreadsheetAutocomplete = false;
			}
		} else if ( autocompletedatatype == 'external data' ) {
			// values from external data
			if ( autocompletesettings == cell.getAttribute('origname') ) {
				pfSpreadsheetAutocomplete = true;
				config['autocompletesettings'] = autocompletesettings;
				if ( inputType == 'combobox' ) {
					widget = new pf.SpreadsheetComboBoxInput(config);
				} else {
					widget = new pf.spreadsheetAutocompleteWidget(config);
				}
			} else {
				// this is probably the case where some autocomplete parameters are set
				// in a wrong way in form defintion, in that case use the default jexcel editor
				pfSpreadsheetAutocomplete = false;
			}
		}

		editor = pfSpreadsheetAutocomplete ? widget.$element[0] : document.createElement(type);

		return {
			editor, pfSpreadsheetAutocomplete
		};
	}

	jexcel.prototype.getValueToBeSavedAfterClosingEditor = function ( cell, pfSpreadsheetAutocomplete, ooui_input_val ) {
		if (pfSpreadsheetAutocomplete) {
			// setting the value to be saved after closing the editor
			return ooui_input_val;
		} else {
			return cell.children[0].value;
		}
	}

	jexcel.prototype.setAutocompleteAttributesOfColumns = function ( cell, gridParams, templateName, fieldNum ) {
		$(cell).attr( 'name', templateName + '[' + $(cell).attr('title') + ']' );
		if ( gridParams[templateName][fieldNum]['autocompletedatatype'] == undefined ) {
			$(cell).attr( 'data-autocomplete-data-type', '' );
			$(cell).attr( 'data-autocomplete-settings', '' );
		} else {
			$(cell).attr( 'data-autocomplete-data-type', gridParams[templateName][fieldNum]['autocompletedatatype'] );
			$(cell).attr( 'data-autocomplete-settings', gridParams[templateName][fieldNum]['autocompletesettings'] );
		}
	}

	jexcel.prototype.setAutocompleteAttributesOfCells = function( table, templateName, data_x, cell ) {
		var autocompletedatatype = $(table).find('thead td[data-x="'+data_x+'"]').attr('data-autocomplete-data-type'),
			autocompletesettings = $(table).find('thead td[data-x="'+data_x+'"]').attr('data-autocomplete-settings');
		$(cell).attr({
			'name': templateName +'|'+$(table).find('thead td[data-x="'+data_x+'"]').attr('title'),
			'origname': templateName +'['+$(table).find('thead td[data-x="'+data_x+'"]').attr('title')+']',
			'data-autocomplete-data-type': autocompletedatatype,
			'data-autocomplete-settings': autocompletesettings
		});
	}

})( jexcel, mediaWiki );

( function ( $, mw, pf ) {
	var baseUrl = mw.config.get( 'wgScriptPath' ),
		gridParams = mw.config.get( 'wgPageFormsGridParams' ),
		gridValues = mw.config.get( 'wgPageFormsGridValues' );

	$( '.pfSpreadsheet' ).each( function() {
		var table = this;
		var templateName = $(this).attr( 'data-template-name' ),
			formName = $(this).attr( 'data-form-name' ),
			spreadsheetID = $(this).attr( 'id' ),
			editMultiplePages = $(this).attr( 'editMultiplePages' );
		var columns = [];

		// Somewhat crude attempt at setting reasonable column widths,
		// based on the browser width and the number of columns, with
		// built-in maximum and minimum widths.
		var numColumns = Object.keys(gridParams[templateName]).length;
		var columnWidth = ( $('#content').width() - 150 ) / numColumns;
		if ( isNaN(columnWidth) ) {
			columnWidth = 200;
		}
		if ( columnWidth < 100 ) {
			columnWidth = 100;
		} else if ( columnWidth > 400 ) {
			columnWidth = 400;
		}

		var columnName;
		for ( var templateParam of gridParams[templateName] ) {
			columnName = templateParam['name'];
			var columnType = templateParam['type'];
			var jExcelType = 'text';
			var columnAttributes = {
				title: columnName,
				width: columnWidth + "px"
			};
			if ( columnType == 'checkbox' ) {
				jExcelType = 'checkbox';
			} else if ( columnType == 'date' ) {
				jExcelType = 'calendar';
				columnAttributes['options'] = {
					format: 'YYYY-MM-DD'
				}
			} else if ( columnType == 'datetime' ) {
				jExcelType = 'calendar';
				columnAttributes['options'] = {
					time: 1,
					format: 'YYYY-MM-DD HH:MI'
				}
			}
			var allowedValues = templateParam['values'];
			if ( allowedValues !== undefined ) {
				jExcelType = 'dropdown';
				columnAttributes['source'] = allowedValues;
				if ( templateParam['list'] === true ) {
					columnAttributes['multiple'] = true;
				}
			}
			if ( jExcelType == 'text' ) {
				columnAttributes['wordWrap'] = true;
			}
			columnAttributes['type'] = jExcelType;
			columns.push( columnAttributes );
		}

		// One more column, for the management icons.
		columns.push( {
			title: manageColumnTitle,
			width: "100px",
			type: "html",
			readOnly: true
		} );

		var columnNames = [];
		for ( var column of columns ) {
			columnNames.push( column.title );
		}

		var pageIDs = [];
		var pagesData = [];
		var myData = [];
		var newPageNames = [];
		var modifiedDataValues = [];

		if ( editMultiplePages == undefined ) {
			populateSpreadsheet();
		} else {
			getPagesForTemplate( templateName, null );
		}

		function getPagesForTemplate( templateNamed, continueStr ) {
			var apiUrl = baseUrl + '/api.php?action=query&format=json&list=embeddedin&eilimit=500&eititle=Template:' + templateNamed;
			if ( continueStr !== null ) {
				apiUrl += "&eicontinue=" + continueStr;
			}
			$.ajax({
				// We get 500 pages because that's the limit
				// for "prop=revision".
				url: apiUrl,
				dataType: 'json',
				type: 'POST',
				async: false,
				headers: { 'Api-User-Agent': 'Example/1.0' },
				success: function( data ) {
					var pageObjects = data.query.embeddedin;
					for ( var i = 0; i < pageObjects.length; i++ ) {
						pageIDs.push(pageObjects[i].pageid);
					}
					if ( data.continue !== undefined ) {
						getPagesForTemplate( templateNamed, data.continue.eicontinue );
					} else {
						getAllPageDataAndPopulateSpreadsheet( 0 );
					}
				},
				error: function(xhr, status, error){
					mw.notify( "ERROR: Unable to retrieve pages for the selected template", { type: 'error' } );
				}
			});
		}

		// Recursive function to get the contents of each page from
		// the API, some number of pages at a time.
		function getAllPageDataAndPopulateSpreadsheet( offset ) {
			var curPageIDs = pageIDs.slice(offset, offset + numPagesToQuery);
			var pageIDsStr = curPageIDs.join('|');
			$.ajax({
				url: baseUrl + '/api.php?action=query&format=json&prop=revisions&rvprop=content&rvslots=main&formatversion=2&pageids=' + pageIDsStr,
				dataType: 'json',
				type: 'POST',
				headers: { 'Api-User-Agent': 'Example/1.0' },
				success: function(data) {
					if ( data.query == undefined ) {
						// There are no calls to this template.
						populateSpreadsheet();
						return;
					}
					for ( var pageNum = 0; pageNum < data.query.pages.length; pageNum++ ) {
						var curRevision = data.query.pages[pageNum].revisions[0];
						var pageContents = curRevision.slots.main.content;
						pagesData.push( {
							title: data.query.pages[pageNum].title,
							contents: pageContents
						} );
					}
					if ( curPageIDs.length == numPagesToQuery ) {
						getAllPageDataAndPopulateSpreadsheet( offset + numPagesToQuery );
					} else {
						populateSpreadsheet();
					}
				}
			});
		}

		function getTemplateCalls( pageContent, pageName ) {
			// Match all the template calls and their contents
			var startDelimiter = '{{' + templateName.toLowerCase() + '\\b';
			var endDelimiter = '}}';
			var regex = new RegExp( startDelimiter, 'g' );
			var contents = [];
			var contentStart, contentEnd;
			contentStart = contentEnd = 0;
			var match;
			// Parse contents of individual templates
			while ( ( match = regex.exec( pageContent.toLowerCase() ) ) !== null ) {
				contentStart = match['index'];
				var content = '';
				var numOpenCurlyBracketPairs = 1;
				var curPos = contentStart + startDelimiter.length - 2;
				var curPair;
				do {
					var curChar = pageContent.charAt(curPos);
					curPair = curChar + pageContent.charAt(curPos + 1);
					if ( curPair == '{{' ) {
						numOpenCurlyBracketPairs++;
					} else if ( curPair == '}}' ) {
						numOpenCurlyBracketPairs--;
					}
					if ( numOpenCurlyBracketPairs > 0 ) {
						content += curChar;
					}
					curPos++;
				} while ( numOpenCurlyBracketPairs > 0 && curPair !== '' );

				content = content.trim();
				// If this is actually a call to a template with
				// a different name, ignore it.
				if ( content !== '' && content.charAt(0) !== '|' ) {
					continue;
				}
				contents.push( 'page=' + pageName + content );
			}
			return contents;
		}

		function getTemplateParams( templateText ) {
			var params = [];
			if ( templateText == '' ) {
				return params;
			}

			var numOpenCurlyBrackets = 0;
			var numOpenSquareBrackets = 0;
			var curReturnValue = '';

			for ( var i = 0; i < templateText.length; i++ ) {
				var curChar = templateText.charAt(i);
				if ( curChar == '{' ) {
					numOpenCurlyBrackets++;
				} else if ( curChar == '}' ) {
					numOpenCurlyBrackets--;
				}
				if ( curChar == '[' ) {
					numOpenSquareBrackets++;
				} else if ( curChar == ']' ) {
					numOpenSquareBrackets--;
				}

				if ( curChar == '|' && numOpenCurlyBrackets == 0 && numOpenSquareBrackets == 0 ) {
					params.push( curReturnValue.trim() );
					curReturnValue = '';
				} else {
					curReturnValue += curChar;
				}
			}
			params.push( curReturnValue.trim() );

			return params;
		}

		//(function getData () {
			var page = "";

			// Called whenever the user makes a change to the data.
			function editMade( instance, cell, x, y, value ) {
				spreadsheetID = $(instance).attr('id');
				columnName = columnNames[x];
				if ( columnName === "page" ) {
					newPageNames[y] = value;
					page = value === '' ? " " : value;
				}

				// Update either the "save" or the "add" icon,
				// depending on which one exists for this row.
				$( "div#" + spreadsheetID + " td[data-y = " + y + "] .save-changes" ).each( function () {
					if ( modifiedDataValues[spreadsheetID] === undefined ) {
						modifiedDataValues[spreadsheetID] = {};
					}
					var pageName = $(this).parent().attr("id").replace("page-span-", "");
					if ( modifiedDataValues[spreadsheetID][y] === undefined ) {
						// Hacky way to do a "deep copy".
						modifiedDataValues[spreadsheetID][y] = JSON.parse(JSON.stringify(dataValues[spreadsheetID][y]));
					}
					modifiedDataValues[spreadsheetID][y][columnName] = value;
					// @HACK - there's probably a better way to only
					// attach one click listener to this icon.
					$(this).off();
					$(this).click( function( event ) {
						event.preventDefault();
						jexcel.prototype.saveChanges(
							spreadsheetID,
							templateName,
							pageName,
							newPageNames[y],
							formName,
							y,
							modifiedDataValues[spreadsheetID][y],
							columns,
							editMultiplePages
						);
						dataValues[spreadsheetID][y] = JSON.parse(JSON.stringify(modifiedDataValues[spreadsheetID][y]));
						$(this).parent().hide();
						$(this).parent().siblings('.mit-row-icons').show();
					} );
					// Use this opportunity to make the icons appear.
					$(this).parent().show();
					$(this).parent().siblings('.mit-row-icons').hide();
				});
				$("div#" + spreadsheetID + " td[data-y = " + y + "] .save-new-row").each(function () {
					dataValues[spreadsheetID][y][columnName] = value;
					// @HACK - see above
					$(this).off();
					$(this).click( function( event ) {
						event.preventDefault();
						jexcel.prototype.saveNewRow(
							spreadsheetID,
							templateName,
							formName,
							y,
							page,
							dataValues[spreadsheetID][y],
							columns,
							editMultiplePages
						);
						$(this).parent().hide();
					} );
				});
				$( "div#" + spreadsheetID + " td[data-y = " + y + "] .cancel-changes" ).each( function () {
					// @HACK - see above
					$(this).off();
					$(this).click( function( event ) {
						event.preventDefault();
						jexcel.prototype.cancelChanges(
							spreadsheetID,
							dataValues[spreadsheetID][y],
							y,
							columnNames
						);
						$(this).parent().hide();
						$(this).parent().siblings('.mit-row-icons').show();
					} );
				});
			}

			// Populate the starting spreadsheet.
			function populateSpreadsheet() {
				if ( dataValues[spreadsheetID] == undefined ) {
					dataValues[spreadsheetID] = [];
				}
				var templateCalls = [];
				var numRows = pagesData.length;
				var columnNum;
				for (var j = 0; j < numRows; j++) {
					templateCalls = getTemplateCalls(pagesData[j].contents, pagesData[j].title);
					for (const templateCall of templateCalls) {
						var fieldArray = getTemplateParams( templateCall );
						var fieldValueObject = {};
						for (const field of fieldArray) {
							var equalPos = field.indexOf('=');
							var fieldLabel = field.substring(0, equalPos);
							var fieldValue = field.substring(equalPos + 1);
							fieldLabel = fieldLabel.trim();
							fieldValueObject[fieldLabel] = fieldValue.trim();
						}
						dataValues[spreadsheetID].push(fieldValueObject);
					}
				}

				if ( editMultiplePages == undefined ) {
					dataValues[spreadsheetID] = gridValues[templateName];
				}
				for ( var rowNum = 0; rowNum < dataValues[spreadsheetID].length; rowNum++ ) {
					var rowValues = dataValues[spreadsheetID][rowNum];
					var pageName;
					for ( columnNum = 0; columnNum < columnNames.length; columnNum++ ) {
						columnName = columnNames[columnNum];
						var curValue = rowValues[columnName];
						if ( myData[rowNum] == undefined ) {
							myData[rowNum] = [];
						}

						if ( columnName == 'page' ) {
							pageName = curValue;
						}

						if ( curValue !== undefined ) {
							var jExcelValue = jexcel.prototype.getjExcelValue( curValue, gridParams[templateName][columnNum] );
							myData[rowNum].push( jExcelValue );
							dataValues[spreadsheetID][rowNum][columnName] = jExcelValue;
						} else if ( columnName === manageColumnTitle ) {
							var cellContents = '<span class="save-or-cancel" style="display: none" id="page-span-' + pageName + '">' +
								'<a href="#" class="save-changes">' + saveIcon + '</a> | ' +
								'<a href="#" class="cancel-changes">' + cancelIcon + '</a>' +
								'</span>';

							if ( editMultiplePages === undefined ) {
								cellContents += '<span class="mit-row-icons">' + // "mit" = "multiple-instance template"
									'<a href="#" class="raise-row">' + upIcon + '</a>' +
									' <a href="#" class="lower-row">' + downIcon + '</a>' +
									' | <a href="#" class="delete-row">' + deleteIcon + '</a>' +
									'</span>';
							}
							myData[rowNum].push( cellContents );
						} else {
							myData[rowNum].push("");
						}
					}
				}

				// Called after a new row is added.
				function rowAdded(instance) {
					var $instance = $(instance);
					var spreadsheetId = $instance.attr('id');
					rowAdded2( $instance, spreadsheetId );
				}

				function rowAdded2( $instance, spreadsheetId ) {
					var $newRow = $instance.find("tr").last();
					var columnParams = gridParams[templateName];
					for ( columnNum = 0; columnNum < columnParams.length; columnNum++ ) {
						var defaultValue = columnParams[columnNum]['default'];
						if ( defaultValue == undefined ) {
							continue;
						}
						var realDefaultValue = defaultValue;
						// Special handling for some default values.
						if ( defaultValue == 'now' ) {
							var date = new Date();
							var monthNum = date.getMonth() + 1;
							realDefaultValue = date.getFullYear() + '-' + monthNum + '-' + date.getDate() +
								' ' + date.getHours() + ':' + date.getMinutes();
						} else if ( defaultValue == 'current user' ) {
							realDefaultValue = mw.config.get( 'wgUserName' );
						} else if ( defaultValue == 'uuid' ) {
							realDefaultValue = window.pfGenerateUUID();
						}
						var $curCell = $newRow.find("td:nth-child(" + ( columnNum + 2 ) + ")");
						$curCell.html(realDefaultValue);
					}
					var $cell = $newRow.find("td").last();
					var manageCellContents = '';

					if ( editMultiplePages === undefined ) {
						manageCellContents = '<span class="mit-row-icons">' +
							'<a href="#" class="raise-row">' + upIcon + '</a>' +
							' <a href="#" class="lower-row">' + downIcon + '</a>' +
							' | <a href="#" class="delete-row">' + deleteIcon + '</a>' +
							'</span>';
					} else {
						manageCellContents = '<span class="save-or-cancel">' +
							'<a class="save-new-row">' + addIcon + '</a> | ' +
							'<a class="cancel-adding">' + cancelIcon + '</a></span>';
					}
					$cell.html(manageCellContents);

					// Don't activate the "add page" icon
					// yet, because the row doesn't have a
					// page name.
					// @TODO - should the icon even be there?
					$cell.find("a.cancel-adding").click( function( event ) {
						event.preventDefault();
						jexcel.prototype.deleteRow(spreadsheetId, dataValues[spreadsheetId].length);
					} );
					if ( editMultiplePages === undefined ) {
						$cell.find("a.delete-row").click( function( event ) {
							var y = $cell.attr("data-y");
							event.preventDefault();
							jexcel.prototype.deleteRow( spreadsheetId, y );
							//dataValues[spreadsheetId].splice(y, 1);
						} );
						$cell.find("a.raise-row").click( function( event ) {
							var y = $cell.attr("data-y");
							event.preventDefault();
							if ( y > 0 ) {
								mw.spreadsheets[spreadsheetId].moveRow( y, y - 1 );
							}
						} );
						$cell.find("a.lower-row").click( function( event ) {
							var curSpreadsheet = mw.spreadsheets[spreadsheetId];
							var y = parseInt( $cell.attr("data-y") );
							event.preventDefault();
							if ( y + 1 < curSpreadsheet.getData().length ) {
								curSpreadsheet.moveRow( y, y + 1 );
							}
						} );
					}

					// Providing the autocomplete attributes whenever a new row is added
					if ( editMultiplePages === undefined ) {
						$(table).find('tbody td').not('.jexcel_row').each(function() {
							var data_x = $(this).attr('data-x');
							jexcel.prototype.setAutocompleteAttributesOfCells( table, templateName, data_x, this );
						});
					}

					dataValues[spreadsheetId].push( {} );
				}

				mw.spreadsheets[spreadsheetID] = jexcel( table, {
					data: myData,
					columns: columns,
					tableOverflow: true,
					loadingSpin: true,
					onchange: editMade,
					columnSorting: false,
					allowInsertColumn: false,
					allowDeletingAllRows: true,
					oninsertrow: rowAdded,
					contextMenu: function() { return false; },
					tableHeight: "2500px",
					pagination: (editMultiplePages === undefined ) ? false : 100,
					search: (editMultiplePages !== undefined ),
					filters: (editMultiplePages !== undefined ),
					text: {
						search: mw.msg( 'search' )
					}
				} );
				// Set the "label" for columns that have a label defined.
				var columnParams = gridParams[templateName];
				for ( columnNum = 0; columnNum < columnParams.length; columnNum++ ) {
					var columnLabel = columnParams[columnNum]['label'];
					if ( columnLabel == undefined ) {
						continue;
					}
					$(table).find('thead').find('td[data-x=' + columnNum + ']').html(columnLabel);
				}

				if ( editMultiplePages !== undefined ) {
					var numberOfColumns = $(table).find('thead tr:first td').not('.jexcel_selectall').length,
						fieldNum = 0;
					// Provide the autocomplete attributes to each column of the spreadsheet
					// which is populated at the starting.
					$(table).find('thead tr:first td').not('.jexcel_selectall').each( function() {
						// to avoid the last column, used numberOfColumns-1
						if ( fieldNum < numberOfColumns-1 ) {
							jexcel.prototype.setAutocompleteAttributesOfColumns( this, gridParams, templateName, fieldNum );
							fieldNum++;
						}
					} );
				}

				var addRowButton = new OO.ui.FieldLayout( new OO.ui.ButtonWidget( {
					classes: [ 'add-row' ],
					icon: 'add',
					label: mw.msg( 'pf-spreadsheet-addrow' )
				} ) );

				$(table).append(addRowButton.$element);

				$('div#' + spreadsheetID + ' span.add-row').click( function ( event ) {
					var curSpreadsheet = mw.spreadsheets[spreadsheetID];
					event.preventDefault();
					if ( curSpreadsheet.getData().length > 0 ) {
						curSpreadsheet.insertRow();
					} else {
						curSpreadsheet.setData([ ' ' ]);
						var $curSpreadsheetDiv = $(this).closest('.pfSpreadsheet');
						rowAdded2($curSpreadsheetDiv, spreadsheetID);
					}
				} );
				$('div#' + spreadsheetID + ' a.raise-row').click( function ( event ) {
					var y = $(this).parents('td').attr("data-y");
					event.preventDefault();
					if ( y > 0 ) {
						mw.spreadsheets[spreadsheetID].moveRow( y, y - 1 );
					}
				} );
				$('div#' + spreadsheetID + ' a.lower-row').click( function ( event ) {
					var curSpreadsheet = mw.spreadsheets[spreadsheetID];
					var y = parseInt( $(this).parents('td').attr("data-y") );
					event.preventDefault();
					if ( y + 1 < curSpreadsheet.getData().length ) {
						mw.spreadsheets[spreadsheetID].moveRow( y, y + 1 );
					}
				} );
				$('div#' + spreadsheetID + ' a.delete-row').click( function ( event ) {
					var y = $(this).parents('td').attr("data-y");
					event.preventDefault();
					jexcel.prototype.deleteRow( spreadsheetID, y );
					//dataValues[spreadsheetID].splice(y, 1);
				} );

				$('div#' + spreadsheetID + ' div.loadingImage').css( "display", "none" );

			}
		//})();
	});

	$('.pfSpreadsheet').each( function() {
		var templateName = $(this).attr( 'data-template-name' ),
			table = this,
			fieldNum = 0,
			editMultiplePages = $(this).attr('editmultiplepages');
		var numberOfColumns = $(table).find('thead tr:first td').not('.jexcel_selectall').length;

		if ( editMultiplePages == undefined ) {
			// Provide the autocomplete attributes to each column of the spreadsheet
			// which is populated at the starting.
			$(table).find('thead tr:first td').not('.jexcel_selectall').each( function() {
				// to avoid the last column, used numberOfColumns-1
				if ( fieldNum < numberOfColumns-1 ) {
					jexcel.prototype.setAutocompleteAttributesOfColumns( this, gridParams, templateName, fieldNum );
					fieldNum++;
				}
			} );
			// Providing "name" and "origname" and autocomplete attributes to every cell of the spreadsheet
			$(table).find('tbody td').not('.jexcel_row').each(function() {
				var data_x = $(this).attr('data-x');
				jexcel.prototype.setAutocompleteAttributesOfCells( table, templateName, data_x, this );
			});
		}
	} )

	// If this is a spreadsheet display within a form, create hidden
	// inputs for every cell when the form is submitted, so that all the
	// data will actually get submitted.
	$( "#pfForm" ).submit(function( event ) {
		$( '.pfSpreadsheet' ).each( function() {
			var $grid = $(this),
				templateName = $(this).attr( 'data-template-name' ),
				formName = $(this).attr( 'data-form-name' ),
				editMultiplePages = $(this).attr( 'editMultiplePages' );

			// Add a hidden input for each template, so that the PHP code can know
			// which values came from a spreadsheet.
			if ( !editMultiplePages ) {
				$('<input>').attr( 'type', 'hidden' ).attr( 'name', 'spreadsheet_templates[' + templateName + ']' ).attr( 'value', 'true' ).appendTo( '#pfForm' );
			}

			$grid.find( "td" ).not('.readonly').each( function() {
				var rowNum = $(this).attr('data-y');
				var columnNum = $(this).attr('data-x');
				if ( rowNum == undefined || columnNum == undefined ) {
					return;
				}

				var mwValue = jexcel.prototype.getMWValueFromCell( $(this), gridParams[templateName][columnNum] );
				var paramName = gridParams[templateName][columnNum].name;
				var inputName = templateName + '[' + ( rowNum + 1 ) + '][' + paramName + ']';
				$('<input>').attr( 'type', 'hidden' ).attr( 'name', inputName ).attr( 'value', mwValue ).appendTo( '#pfForm' );
			});
		});
	});


}( jQuery, mediaWiki, pf ) );
