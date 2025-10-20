/**
 * Code to integrate the jExcel JavaScript library into Page Forms.
 *
 * @author Yaron Koren
 * @author Balabky9
 * @author Amr El-Absy
 */

// @TODO - make this based on the API limit, which in turn is based on whether the user has the "apihighlimits" right.
const numPagesToQuery = 50;
const saveIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-check oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.message( 'upload-dialog-button-save' ).escaped() + '"></span>';
const cancelIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-close oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.message( 'cancel' ).escaped() + '"></span>';
const addIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-add oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.message( 'apisandbox-add-multi' ).escaped() + '"></span>';
const upIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-upTriangle oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + 'Raise' + '"></span>';
const downIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-downTriangle oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + 'Lower' + '"></span>';
const deleteIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-trash oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.message( 'delete' ).escaped() + '"></span>';
const manageColumnTitle = '\u2699';
const dataValues = [];

( function( jexcel, mw ) {
	const baseUrl = mw.config.get( 'wgScriptPath' );
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
		const possibleYesMessages = [
			mw.config.get( 'wgPageFormsContLangYes' ).toLowerCase(),
			// Add in '1', and some hardcoded English.
			'1', 'yes', 'true'
		];
		return ( possibleYesMessages.includes(value) );
	};

	jexcel.prototype.getjExcelValue = function( mwValue, columnAttributes ) {
		let date,
			monthNum;
		if ( mwValue == null ) {
			return null;
		}
		mwValue = this.decodeValues( mwValue );
		if ( columnAttributes['type'] == 'checkbox' ) {
			return jexcel.prototype.valueIsYes(mwValue);
		} else if ( columnAttributes['list'] == true ) {
			// The list delimiter unfortunately can't be set for
			// jExcel - it's hardcoded to a semicolon - and values
			// can't have spaces around them. So we have to
			// modify the current value for it to be handled
			// correctly.
			const individualValues = mwValue.split( columnAttributes['delimiter'] );
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
		let jExcelValue;
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
		jExcelValue = this.decodeValues( jExcelValue );
		if ( columnAttributes['type'] == 'checkbox' ) {
			return ( jExcelValue == 'true' ) ?
				mw.config.get( 'wgPageFormsContLangYes' ) :
				mw.config.get( 'wgPageFormsContLangNo' );
		} else if ( columnAttributes['list'] == true ) {
			const delimiter = columnAttributes['delimiter'] + ' ';
			return jExcelValue.replace(/;/g, delimiter);
		} else if ( columnAttributes['type'] == 'date' || columnAttributes['type'] == 'datetime' ) {
			return jExcelValue;
		} else {
			const mwValue = jExcelValue.replace( /\<br\>/g, "\n" );
			return mwValue;
		}
	}

	jexcel.prototype.generateQueryStringForSave = function( formName, templateName, pageName, rowValues, columns ) {
		let queryString = 'form=' + formName + '&target=' + encodeURIComponent( pageName );
		let curColumn;
		for ( const columnName in rowValues ) {
			if ( columnName == 'page' ) {
				continue;
			}
			for ( const columnNum in columns ) {
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
		$("div#" + spreadsheetID + " table.jexcel td[data-y = " + rowNum + "]").not(".jexcel_row").each( function() {
			const columnNum = $(this).attr("data-x");
			const curColumn = columns[columnNum]['title'];
			const curValue = rowValues[curColumn];
			if ( rowValues[curColumn] !== undefined ) {
				mw.spreadsheets[spreadsheetID].setValue( this, curValue );
			}
		});

		if ( editMultiplePages === undefined ) {
			return;
		}

		const data = {
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
		const url = baseUrl + '/api.php?action=query&format=json&meta=tokens&type=csrf';
		return $.post( url );
	}

	jexcel.prototype.movePage = function( fromPage, toPage ) {
		return $.when( jexcel.prototype.getToken() ).then( ( postResult ) => {
			const data = {
				token: postResult.query.tokens.csrftoken
			};
			const query = 'from=' + encodeURIComponent( fromPage ) + "&to=" + encodeURIComponent( toPage ) + "&movetalk&noredirect";
			return $.ajax( {
				type: 'POST',
				url: baseUrl + '/api.php?action=move&format=json&' + query,
				dataType: 'json',
				data: data
			} );
		});
	}

	jexcel.prototype.cancelChanges = function( spreadsheetID, rowValues, rowNum, columnNames ) {
		$("div#" + spreadsheetID + " table.jexcel td[data-y = " + rowNum + "]").not(".jexcel_row").each( function() {
			const columnNum = $(this).attr("data-x");
			const curColumn = columnNames[columnNum];
			if ( rowValues[curColumn] !== undefined ) {
				mw.spreadsheets[spreadsheetID].setValue( this, rowValues[curColumn] );
			} else {
				mw.spreadsheets[spreadsheetID].setValue( this, "" );
			}
		} );

		$("div#" + spreadsheetID + " td[data-y = " + rowNum + "] .save-changes").each( function() {
			$(this).parent().hide();
			$(this).parent().siblings('.mit-row-icons').show();
		} );
	}

	// Add a new page.
	jexcel.prototype.saveNewRow = function( spreadsheetID, templateName, formName, rowNum, pageName, rowValues, columns, editMultiplePages ) {
		const $manageCell = $( "div#" + spreadsheetID + " td[data-y=" + rowNum + "]" ).last();

		const spanContents = '<a href="#" class="save-changes">' + saveIcon + '</a> | ' +
			'<a href="#" class="cancel-changes">' + cancelIcon + '</a>';

		$manageCell.children('span.save-or-cancel')
			.attr('id', 'page-span-' + pageName)
			.html( spanContents )
			.hide();

		if ( editMultiplePages == undefined ) {
			$manageCell.children('.mit-row-icons').show();
			return;
		}

		const data = {
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

	// Decode values
	jexcel.prototype.decodeValues = function( value ) {
		value = $('<div />').html( value ).text();
		return value;
	}

	jexcel.prototype.deleteRow = function( spreadsheetID, rowNum ) {
		rowNum = parseInt(rowNum);
		mw.spreadsheets[spreadsheetID].deleteRow(rowNum);
		dataValues[spreadsheetID].splice(rowNum, 1);
	}

	jexcel.prototype.getAutocompleteAttributes = function( cell ) {
		let autocompletedatatype = jQuery(cell).attr('data-autocomplete-data-type');
		let autocompletesettings = jQuery(cell).attr('data-autocomplete-settings');
		if ( autocompletedatatype == undefined || autocompletesettings == undefined ) {
			// that means we are in Special:MultipageEdit
			// here we take attributes from the column head,
			// to use other types of autocompletion( apart from
			// "cargo field" and "property" ), the attributes in
			// each cell can also be set.
			const data_x = jQuery(cell).attr('data-x');
			const $table = jQuery(cell).parents().find('table');
			autocompletedatatype = jQuery($table).find('thead td[data-x="'+data_x+'"]').attr('data-autocomplete-data-type');
			autocompletesettings = jQuery($table).find('thead td[data-x="'+data_x+'"]').attr('data-autocomplete-settings');
		}
		return {
			autocompletedatatype: autocompletedatatype, autocompletesettings: autocompletesettings
		};
	}

	// If a field is dependent on some other field in the form
	// then it returns its name.
	jexcel.prototype.dependenton = function(origname) {
		const wgPageFormsDependentFields = mw.config.get('wgPageFormsDependentFields');
			for (let i = 0; i < wgPageFormsDependentFields.length; i++) {
				const dependentFieldPair = wgPageFormsDependentFields[i];
				if (dependentFieldPair[1] === origname) {
					return dependentFieldPair[0];
				}
			}
	};

	jexcel.prototype.getEditorForAutocompletion = function( inputType, x, y, autocompletedatatype, autocompletesettings, cell, type ) {
		let editor;
		let pfSpreadsheetAutocomplete = false,
			widget;
		const config = {
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
			const dep_on_field = jexcel.prototype.dependenton(cell.getAttribute('origname'));
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
				// in a wrong way in form definition, in that case use the default jexcel editor
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
				// in a wrong way in form definition, in that case use the default jexcel editor
				pfSpreadsheetAutocomplete = false;
			}
		}

		editor = pfSpreadsheetAutocomplete ? widget.$element[0] : document.createElement(type);

		return {
			editor: editor, pfSpreadsheetAutocomplete: pfSpreadsheetAutocomplete
		};
	}

	jexcel.prototype.getValueToBeSavedAfterClosingEditor = function( cell, pfSpreadsheetAutocomplete, ooui_input_val ) {
		if (pfSpreadsheetAutocomplete) {
			// setting the value to be saved after closing the editor
			return ooui_input_val;
		} else {
			return cell.children[0].value;
		}
	}

	jexcel.prototype.setAutocompleteAttributesOfColumns = function( cell, gridParams, templateName, fieldNum ) {
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
		const autocompletedatatype = $(table).find('thead td[data-x="'+data_x+'"]').attr('data-autocomplete-data-type'),
			autocompletesettings = $(table).find('thead td[data-x="'+data_x+'"]').attr('data-autocomplete-settings');
		$(cell).attr({
			'name': templateName +'|'+$(table).find('thead td[data-x="'+data_x+'"]').attr('title'),
			'origname': templateName +'['+$(table).find('thead td[data-x="'+data_x+'"]').attr('title')+']',
			'data-autocomplete-data-type': autocompletedatatype,
			'data-autocomplete-settings': autocompletesettings
		});
	}

})( jexcel, mediaWiki );

( function( $, mw, pf ) {
	const baseUrl = mw.config.get( 'wgScriptPath' ),
		gridParams = mw.config.get( 'wgPageFormsGridParams' ),
		gridValues = mw.config.get( 'wgPageFormsGridValues' );

	$( '.pfSpreadsheet' ).each( function() {
		const table = this;
		let templateName = $(this).attr( 'data-template-name' ),
			formName = $(this).attr( 'data-form-name' ),
			spreadsheetID = $(this).attr( 'id' ),
			editMultiplePages = $(this).attr( 'editMultiplePages' );
		const columns = [];

		// Somewhat crude attempt at setting reasonable column widths,
		// based on the browser width and the number of columns, with
		// built-in maximum and minimum widths.
		const numColumns = Object.keys(gridParams[templateName]).length;
		let columnWidth = ( $('#content').width() - 150 ) / numColumns;
		if ( isNaN(columnWidth) ) {
			columnWidth = 200;
		}
		if ( columnWidth < 100 ) {
			columnWidth = 100;
		} else if ( columnWidth > 400 ) {
			columnWidth = 400;
		}

		let columnName;
		for ( const templateParam of gridParams[templateName] ) {
			columnName = templateParam['name'];
			const columnType = templateParam['type'];
			let jExcelType = 'text';
			const columnAttributes = {
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
			const allowedValues = templateParam['values'];
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

		const columnNames = [];
		for ( const column of columns ) {
			columnNames.push( column.title );
		}

		const pageIDs = [];
		const pagesData = [];
		const myData = [];
		const newPageNames = [];
		const modifiedDataValues = [];

		if ( editMultiplePages == undefined ) {
			populateSpreadsheet();
		} else {
			getPagesForTemplate( templateName, null );
		}

		function getPagesForTemplate( templateNamed, continueStr ) {
			let apiUrl = baseUrl + '/api.php?action=query&format=json&list=embeddedin&eilimit=500&eititle=Template:' + templateNamed;
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
					const pageObjects = data.query.embeddedin;
					for ( let i = 0; i < pageObjects.length; i++ ) {
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
			const curPageIDs = pageIDs.slice(offset, offset + numPagesToQuery);
			const pageIDsStr = curPageIDs.join('|');
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
					for ( let pageNum = 0; pageNum < data.query.pages.length; pageNum++ ) {
						const curRevision = data.query.pages[pageNum].revisions[0];
						const pageContents = curRevision.slots.main.content;
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
			const startDelimiter = '{{' + templateName.toLowerCase() + '\\b';
			const endDelimiter = '}}';
			const regex = new RegExp( startDelimiter, 'g' );
			const contents = [];
			let contentStart, contentEnd;
			contentStart = contentEnd = 0;
			let match;
			// Parse contents of individual templates
			while ( ( match = regex.exec( pageContent.toLowerCase() ) ) !== null ) {
				contentStart = match['index'];
				let content = '';
				let numOpenCurlyBracketPairs = 1;
				let curPos = contentStart + startDelimiter.length - 2;
				var curPair;
				do {
					const curChar = pageContent.charAt(curPos);
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
			const params = [];
			if ( templateText == '' ) {
				return params;
			}

			let numOpenCurlyBrackets = 0;
			let numOpenSquareBrackets = 0;
			let curReturnValue = '';

			for ( let i = 0; i < templateText.length; i++ ) {
				const curChar = templateText.charAt(i);
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
			let page = "";

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
				$( "div#" + spreadsheetID + " td[data-y = " + y + "] .save-changes" ).each( function() {
					if ( modifiedDataValues[spreadsheetID] === undefined ) {
						modifiedDataValues[spreadsheetID] = {};
					}
					const pageName = $(this).parent().attr("id").replace("page-span-", "");
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
				$("div#" + spreadsheetID + " td[data-y = " + y + "] .save-new-row").each(function() {
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
				$( "div#" + spreadsheetID + " td[data-y = " + y + "] .cancel-changes" ).each( function() {
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
				let templateCalls = [];
				const numRows = pagesData.length;
				let columnNum;
				for (let j = 0; j < numRows; j++) {
					templateCalls = getTemplateCalls(pagesData[j].contents, pagesData[j].title);
					for (const templateCall of templateCalls) {
						const fieldArray = getTemplateParams( templateCall );
						const fieldValueObject = {};
						for (const field of fieldArray) {
							const equalPos = field.indexOf('=');
							let fieldLabel = field.slice(0, Math.max(0, equalPos));
							const fieldValue = field.slice(Math.max(0, equalPos + 1));
							fieldLabel = fieldLabel.trim();
							fieldValueObject[fieldLabel] = fieldValue.trim();
						}
						dataValues[spreadsheetID].push(fieldValueObject);
					}
				}

				if ( editMultiplePages == undefined ) {
					dataValues[spreadsheetID] = gridValues[templateName];
				}
				for ( let rowNum = 0; rowNum < dataValues[spreadsheetID].length; rowNum++ ) {
					const rowValues = dataValues[spreadsheetID][rowNum];
					var pageName;
					for ( columnNum = 0; columnNum < columnNames.length; columnNum++ ) {
						columnName = columnNames[columnNum];
						const curValue = rowValues[columnName];
						if ( myData[rowNum] == undefined ) {
							myData[rowNum] = [];
						}

						if ( columnName == 'page' ) {
							pageName = curValue;
						}

						if ( curValue !== undefined ) {
							const jExcelValue = jexcel.prototype.getjExcelValue( curValue, gridParams[templateName][columnNum] );
							myData[rowNum].push( jExcelValue );
							dataValues[spreadsheetID][rowNum][columnName] = jExcelValue;
						} else if ( columnName === manageColumnTitle ) {
							let cellContents = '<span class="save-or-cancel" style="display: none" id="page-span-' + pageName + '">' +
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
					const $instance = $(instance);
					const spreadsheetId = $instance.attr('id');
					rowAdded2( $instance, spreadsheetId );
				}

				function rowAdded2( $instance, spreadsheetId ) {
					const $newRow = $instance.find("tr").last();
					const columnParams = gridParams[templateName];
					for ( columnNum = 0; columnNum < columnParams.length; columnNum++ ) {
						const defaultValue = columnParams[columnNum]['default'];
						if ( defaultValue == undefined ) {
							continue;
						}
						let realDefaultValue = defaultValue;
						// Special handling for some default values.
						if ( defaultValue == 'now' ) {
							const date = new Date();
							const monthNum = date.getMonth() + 1;
							realDefaultValue = date.getFullYear() + '-' + monthNum + '-' + date.getDate() +
								' ' + date.getHours() + ':' + date.getMinutes();
						} else if ( defaultValue == 'current user' ) {
							realDefaultValue = mw.config.get( 'wgUserName' );
						} else if ( defaultValue == 'uuid' ) {
							realDefaultValue = window.pfGenerateUUID();
						}
						const $curCell = $newRow.find("td:nth-child(" + ( columnNum + 2 ) + ")");
						$curCell.html(realDefaultValue);
					}
					const $cell = $newRow.find("td").last();
					let manageCellContents = '';

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
					$cell.find("a.cancel-adding").click( ( event ) => {
						event.preventDefault();
						jexcel.prototype.deleteRow(spreadsheetId, dataValues[spreadsheetId].length);
					} );
					if ( editMultiplePages === undefined ) {
						$cell.find("a.delete-row").click( ( event ) => {
							const y = $cell.attr("data-y");
							event.preventDefault();
							jexcel.prototype.deleteRow( spreadsheetId, y );
							//dataValues[spreadsheetId].splice(y, 1);
						} );
						$cell.find("a.raise-row").click( ( event ) => {
							const y = $cell.attr("data-y");
							event.preventDefault();
							if ( y > 0 ) {
								mw.spreadsheets[spreadsheetId].moveRow( y, y - 1 );
							}
						} );
						$cell.find("a.lower-row").click( ( event ) => {
							const curSpreadsheet = mw.spreadsheets[spreadsheetId];
							const y = parseInt( $cell.attr("data-y") );
							event.preventDefault();
							if ( y + 1 < curSpreadsheet.getData().length ) {
								curSpreadsheet.moveRow( y, y + 1 );
							}
						} );
					}

					// Providing the autocomplete attributes whenever a new row is added
					if ( editMultiplePages === undefined ) {
						$(table).find('tbody td').not('.jexcel_row').each(function() {
							const data_x = $(this).attr('data-x');
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
					columnSorting: true,
					allowInsertColumn: false,
					allowDeletingAllRows: true,
					oninsertrow: rowAdded,
					contextMenu: function() {
						return false;
					},
					tableHeight: "2500px",
					pagination: (editMultiplePages === undefined ) ? false : 100,
					search: (editMultiplePages !== undefined ),
					filters: (editMultiplePages !== undefined ),
					text: {
						search: mw.msg( 'search' )
					}
				} );
				// Set the "label" for columns that have a label defined.
				const columnParams = gridParams[templateName];
				for ( columnNum = 0; columnNum < columnParams.length; columnNum++ ) {
					const columnLabel = columnParams[columnNum]['label'];
					if ( columnLabel == undefined ) {
						continue;
					}
					$(table).find('thead').find('td[data-x=' + columnNum + ']').html(columnLabel);
				}

				if ( editMultiplePages !== undefined ) {
					let numberOfColumns = $(table).find('thead tr:first td').not('.jexcel_selectall').length,
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

				const addRowButton = new OO.ui.FieldLayout( new OO.ui.ButtonWidget( {
					classes: [ 'add-row' ],
					icon: 'add',
					label: mw.msg( 'pf-spreadsheet-addrow' )
				} ) );

				$(table).append(addRowButton.$element);

				$('div#' + spreadsheetID + ' span.add-row').click( function( event ) {
					const curSpreadsheet = mw.spreadsheets[spreadsheetID];
					event.preventDefault();
					if ( curSpreadsheet.getData().length > 0 ) {
						curSpreadsheet.insertRow();
					} else {
						curSpreadsheet.setData([ ' ' ]);
						const $curSpreadsheetDiv = $(this).closest('.pfSpreadsheet');
						rowAdded2($curSpreadsheetDiv, spreadsheetID);
					}
				} );
				$('div#' + spreadsheetID + ' a.raise-row').click( function( event ) {
					const y = $(this).parents('td').attr("data-y");
					event.preventDefault();
					if ( y > 0 ) {
						mw.spreadsheets[spreadsheetID].moveRow( y, y - 1 );
					}
				} );
				$('div#' + spreadsheetID + ' a.lower-row').click( function( event ) {
					const curSpreadsheet = mw.spreadsheets[spreadsheetID];
					const y = parseInt( $(this).parents('td').attr("data-y") );
					event.preventDefault();
					if ( y + 1 < curSpreadsheet.getData().length ) {
						mw.spreadsheets[spreadsheetID].moveRow( y, y + 1 );
					}
				} );
				$('div#' + spreadsheetID + ' a.delete-row').click( function( event ) {
					const y = $(this).parents('td').attr("data-y");
					event.preventDefault();
					jexcel.prototype.deleteRow( spreadsheetID, y );
					//dataValues[spreadsheetID].splice(y, 1);
				} );

				$('div#' + spreadsheetID + ' div.loadingImage').css( "display", "none" );

			}
		//})();
	});

	$('.pfSpreadsheet').each( function() {
		let templateName = $(this).attr( 'data-template-name' ),
			table = this,
			fieldNum = 0,
			editMultiplePages = $(this).attr('editmultiplepages');
		const numberOfColumns = $(table).find('thead tr:first td').not('.jexcel_selectall').length;

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
				const data_x = $(this).attr('data-x');
				jexcel.prototype.setAutocompleteAttributesOfCells( table, templateName, data_x, this );
			});
		}
	} )

	// If this is a spreadsheet display within a form, create hidden
	// inputs for every cell when the form is submitted, so that all the
	// data will actually get submitted.
	$( "#pfForm" ).submit(( event ) => {
		$( '.pfSpreadsheet' ).each( function() {
			const $grid = $(this),
				templateName = $(this).attr( 'data-template-name' ),
				formName = $(this).attr( 'data-form-name' ),
				editMultiplePages = $(this).attr( 'editMultiplePages' );

			// Add a hidden input for each template, so that the PHP code can know
			// which values came from a spreadsheet.
			if ( !editMultiplePages ) {
				$('<input>').attr( 'type', 'hidden' ).attr( 'name', 'spreadsheet_templates[' + templateName + ']' ).attr( 'value', 'true' ).appendTo( '#pfForm' );
			}

			$grid.find( "td" ).not('.readonly').each( function() {
				const rowNum = $(this).attr('data-y');
				const columnNum = $(this).attr('data-x');
				if ( rowNum == undefined || columnNum == undefined ) {
					return;
				}

				const mwValue = jexcel.prototype.getMWValueFromCell( $(this), gridParams[templateName][columnNum] );
				const paramName = gridParams[templateName][columnNum].name;
				const inputName = templateName + '[' + ( rowNum + 1 ) + '][' + paramName + ']';
				$('<input>').attr( 'type', 'hidden' ).attr( 'name', inputName ).attr( 'value', mwValue ).appendTo( '#pfForm' );
			});
		});
	});


}( jQuery, mediaWiki, pf ) );
