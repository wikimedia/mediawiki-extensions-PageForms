
/**
 * Code to integrate the jExcel JavaScript library into Page Forms.
 *
 * @author Yaron Koren
 * @author Balabky9
 * @author Amr El-Absy
 */
const saveIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-check oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.msg( 'upload-dialog-button-save' ) + '"></span>';
const cancelIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-close oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.msg( 'cancel' ) + '"></span>';
const addIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-add oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.msg( 'apisandbox-add-multi' ) + '"></span>';
const deleteIcon = '<span class="oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-trash oo-ui-labelElement-invisible oo-ui-iconWidget" aria-disabled="false" title="' + mw.msg( 'delete' ) + '"></span>';
const manageColumnTitle = '\u2699';

( function( jexcel, mw ) {
	var baseUrl = mw.config.get( 'wgScriptPath' );
	var queryStrings = [];
	mw.spreadsheets = {};

	// Handle any possible Boolean values from the wiki page.
	jexcel.prototype.valueIsYes = function(value) {
		if ( value === null ) {
			return false;
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

	jexcel.prototype.saveChanges = function( spreadsheetID, pageName, newPageName, queryString, formName, rowNum, rowValues, columns, editMultiplePages ) {
		$("div#" + spreadsheetID + " table.jexcel td[data-y = " + rowNum + "]").not(".jexcel_row").each( function () {
			var columnNum = $(this).attr("data-x");
			var curColumn = columns[columnNum]['title'];
			var curValue = rowValues[curColumn];
			if ( rowValues[curColumn] !== undefined ) {
				if ( columns[columnNum]['type'] == 'checkbox' ) {
					curValue = jexcel.prototype.valueIsYes(curValue);
				}
				mw.spreadsheets[spreadsheetID].setValue( this, curValue );
			}
		});

		if ( editMultiplePages === undefined ) {
			return;
		}

		if ( queryString == "" ) {
			var result = {status: 200};
			return result;
		}
		var data = {
			action: 'pfautoedit',
			format: 'json'
		};
		data.query = 'form=' + formName + '&target=' + encodeURIComponent( pageName ) + encodeURI( queryString );
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

		queryStrings[rowNum] = "";
		$("div#" + spreadsheetID + " td[data-y = " + rowNum + "] .save-changes").each( function () {
			$(this).parent().hide();
			$(this).parent().siblings('.delete-row').show();
		} );
	}

	// Add a new page.
	jexcel.prototype.saveNewRow = function( spreadsheetID, page, queryString, formName, rowNum, pageName, rowValues, columnNames, editMultiplePages ) {
		var $manageCell = $( "div#" + spreadsheetID + " td[data-y=" + rowNum + "]" ).last();

		var spanContents = "<a href=\"#\" class=\"save-changes\">" + saveIcon + "</a>" +
			" | " +
			"<a href=\"#\" class=\"cancel-changes\">" + cancelIcon + "</a>";

		$manageCell.children('span')
			.attr('id', 'page-span-' + pageName)
			.html( spanContents )
			.hide();

		if ( editMultiplePages == undefined ) {
			$manageCell.children('a.delete-row').show();
			return;
		}

		var data = {
			action: 'pfautoedit',
			format: 'json'
		};
		data.query = 'form=' + formName + '&target=' + encodeURIComponent( page ) + encodeURI( queryString );
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

})( jexcel, mediaWiki );

( function ( $, mw, pf ) {
	var baseUrl = mw.config.get( 'wgScriptPath' ),
		gridParams = mw.config.get( 'wgPageFormsGridParams' ),
		gridValues = mw.config.get( 'wgPageFormsGridValues' );

	function getjExcelType( mwType ) {
		var convert = {
			"date": "calendar",
			"checkbox": "checkbox",
			"dropdown": "dropdown"
		};
		if ( convert[mwType] !== undefined ) {
			return convert[mwType];
		}
		return "text";
	}

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

		for ( var templateParam of gridParams[templateName] ) {
			var columnName = templateParam['name'];
			var jExcelType = getjExcelType( templateParam['type'] );
			var columnAttributes = {
				title: columnName,
				width: columnWidth + "px",
				type: jExcelType
			};
			if ( columnName == 'page' ) {
				columnAttributes['readOnly'] = true;
			}
			if ( jExcelType == 'text' ) {
				columnAttributes['wordWrap'] = true;
			}
			columns.push( columnAttributes );
		}

		// One more column, for the management icons.
		columns.push( {
			title: manageColumnTitle,
			width: "90px",
			type: "html",
			readOnly: true
		} );

		var columnNames = [];
		for ( var column of columns ) {
			columnNames.push( column.title );
		}

		var pages = [];
		var queryStrings = [];
		var myData = [];
		var newPageNames = [];

		if ( editMultiplePages !== undefined ) {
			$.ajax({
				url: baseUrl + '/api.php?action=query&format=json&list=embeddedin&eilimit=500&eititle=Template:' + templateName,
				dataType: 'json',
				type: 'POST',
				async: false,
				headers: { 'Api-User-Agent': 'Example/1.0' },
				success: function( data ) {
					var pageObjects = data.query.embeddedin;
					for ( var i = 0; i < pageObjects.length; i++ ) {
						pages.push(pageObjects[i].title);
					}
				},
				error: function(xhr, status, error){
					mw.notify( "ERROR: Unable to retrieve pages for the selected template", { type: 'error' } );
				}
			});
		}

		function getGridValues( pages ) {
			var pageNamesStr = pages.join('|');
			return $.ajax({
				url: baseUrl + '/api.php?action=query&format=json&prop=revisions&rvprop=content&rvslots=main&formatversion=2&titles=' + pageNamesStr,
				dataType: 'json',
				type: 'POST',
				headers: { 'Api-User-Agent': 'Example/1.0' }
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
				do {
					var curChar = pageContent.charAt(curPos);
					var curPair = curChar + pageContent.charAt(curPos + 1);
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

		var arguments = [];
		for ( var page in pages ) {
			queryStrings.push("");
			arguments.push({});
		}
		(function getData () {
			var dataValues = [];
			var modifiedDataValues = [];
			var pageNames = "";
			var page = "";

			// Called whenever the user makes a change to the data.
			function editMade( instance, cell, x, y, value ) {
				var spreadsheetID = $(instance).attr('id');
				if ( columns[x]['type'] == 'checkbox' ) {
					value = ( value == true ) ?
						mw.config.get( 'wgPageFormsContLangYes' ) :
						mw.config.get( 'wgPageFormsContLangNo' );
				}
				var columnName = columnNames[x];
				if ( columnName === "page" ) {
					newPageNames[y] = value;
					page = value === '' ? " " : value;
				} else {
					queryStrings[y] += '&' + templateName + '[' + columnName + ']' + '=' + value;
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
					$(this).click( function( event ) {
						event.preventDefault();
						jexcel.prototype.saveChanges(
							spreadsheetID,
							pageName,
							newPageNames[y],
							queryStrings[y],
							formName,
							y,
							modifiedDataValues[spreadsheetID][y],
							columns,
							editMultiplePages
						);
						dataValues[spreadsheetID][y] = JSON.parse(JSON.stringify(modifiedDataValues[spreadsheetID][y]));
						$(this).parent().hide();
						$(this).parent().siblings('.delete-row').show();
					} );
					// Use this opportunity to make the icons appear.
					$(this).parent().show();
					$(this).parent().siblings('.delete-row').hide();
				});
				$("div#" + spreadsheetID + " td[data-y = " + y + "] .save-new-row").each(function () {
					$(this).click( function( event ) {
						dataValues[spreadsheetID][y][columnName] = value;
						event.preventDefault();
						jexcel.prototype.saveNewRow(
							spreadsheetID,
							page,
							queryStrings[y],
							formName,
							y,
							page,
							dataValues[spreadsheetID][y],
							columnNames,
							editMultiplePages
						);
						$(this).parent().hide();
					} );
				});
				$( "div#" + spreadsheetID + " td[data-y = " + y + "] .cancel-changes" ).each( function () {
					$(this).click( function( event ) {
						event.preventDefault();
						jexcel.prototype.cancelChanges(
							spreadsheetID,
							dataValues[spreadsheetID][y],
							y,
							columnNames
						);
						$(this).parent().hide();
						$(this).parent().siblings('.delete-row').show();
					} );
				});
			}

			// Populate the starting spreadsheet.
			$.when( getGridValues( pages ) ).then( function successHandler( data ) {
				if ( dataValues[spreadsheetID] == undefined ) {
					dataValues[spreadsheetID] = [];
				}
				var templateCalls = [];
				var numRows = 0;
				if ( data.query !== undefined ) {
					numRows = data.query.pages.length;
				}
				for (var j = 0; j < numRows; j++) {
					var curRevision = data.query.pages[j].revisions[0];
					if (curRevision.hasOwnProperty('slots')) {
						// MW 1.31+ (or maybe 1.32+)
						var pageContent = curRevision.slots.main.content;
					} else {
						var pageContent = curRevision.content;
					}
					templateCalls = getTemplateCalls(pageContent, data.query.pages[j].title);
					for (const templateCall of templateCalls) {
						var fieldArray = getTemplateParams( templateCall );
						var fieldValueObject = {};
						for (const field of fieldArray) {
							var equalPos = field.indexOf('=');
							var fieldLabel = field.substring(0, equalPos);
							var fieldValue = field.substring(equalPos + 1);
							fieldLabel = fieldLabel.replace(/(\r\n\t|\n|\r\t)/gm, "");
							fieldValueObject[fieldLabel] = fieldValue.replace(/(\r\n\t|\n|\r\t)/gm, "");
						}
						dataValues[spreadsheetID].push(fieldValueObject);
					}
				}

				if ( editMultiplePages == undefined ) {
					dataValues[spreadsheetID] = gridValues[templateName];
				}
				for ( var rowNum = 0; rowNum < dataValues[spreadsheetID].length; rowNum++ ) {
					var rowValues = dataValues[spreadsheetID][rowNum];
					//var notAllowed = 'page';
					var pageName = pages[rowNum];
					arguments[rowNum] = {
						previousPage: pageName
					}
					for ( var columnNum = 0; columnNum < columnNames.length; columnNum++ ) {
						var columnName = columnNames[columnNum];
						var curValue = rowValues[columnName];
						if ( myData[rowNum] == undefined ) {
							myData[rowNum] = [];
						}

						if ( curValue !== undefined ) {
							myData[rowNum].push( curValue );
							queryStrings[rowNum] += '&' + templateName + '[' + columnName + ']' + '=' + curValue;
						} else if ( columnName === manageColumnTitle ) {
							var cellContents = "<span style='display: none' id='page-span-" + pageName + "'>" +
								"<a href=\"#\" class=\"save-changes\">" + saveIcon + "</a>" +
								" | " +
								"<a class=\"cancel-changes\" href=\"#\">" + cancelIcon + "</a>" +
								"</span>";

							if ( editMultiplePages === undefined ) {
								cellContents += "<a href=\"#\" class=\"delete-row\">" + deleteIcon + "</a>";
							}
							myData[rowNum].push( cellContents );
						} else {
							myData[rowNum].push("");
							queryStrings[rowNum] += '&' + templateName + '[' + columnName + ']=';
						}
					}
				}

				// Called after a new row is added.
				function rowAdded(instance) {
					var $instance = $(instance);
					var spreadsheetID = $instance.attr('id');
					rowAdded2( $instance, spreadsheetID );
				}

				function rowAdded2( $instance, spreadsheetID ) {
					var cell = $instance.find("tr").last().find("td").last();
					var manageCellContents = "<span><a class=\"save-new-row\">" + addIcon + "</a>" + " | " +
						"<a class=\"cancel-adding\">" + cancelIcon + "</a></span>";

					if ( editMultiplePages === undefined ) {
						manageCellContents += "<a href=\"#\" class=\"delete-row\">" + deleteIcon + "</a>";
					}
					cell.html(manageCellContents);
					// Don't activate the "add page" icon
					// yet, because the row doesn't have a
					// page name.
					// @TODO - should the icon even be there?
					cell.find("a.cancel-adding").click( function( event ) {
						event.preventDefault();
						jexcel.prototype.deleteRow(spreadsheetID, dataValues[spreadsheetID].length);
					} );
					if ( editMultiplePages === undefined ) {
						cell.find("a.delete-row").hide().click( function( event ) {
							var y = cell.attr("data-y");
							event.preventDefault();
							jexcel.prototype.deleteRow( spreadsheetID, y );
							//dataValues[spreadsheetID].splice(y, 1);
						} );
					}

					queryStrings.push("");
					dataValues[spreadsheetID].push( {} );
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
					pagination: (editMultiplePages === undefined ) ? false : 100
				} );

				$(table).append('<p><a href="#" class="add-row">' + mw.msg( 'pf-spreadsheet-addrow' ) + '</a></p>');

				$('div#' + spreadsheetID + ' a.add-row').click( function ( event ) {
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
				$('div#' + spreadsheetID + ' a.delete-row').click( function ( event ) {
					var y = $(this).parents('td').attr("data-y");
					event.preventDefault();
					jexcel.prototype.deleteRow( spreadsheetID, y );
					//dataValues[spreadsheetID].splice(y, 1);
				} );

			});
		})();
	});

	// If this is a spreadsheet display within a form, create hidden
	// inputs for every cell when the form is submitted, so that all the
	// data will actually get submitted.
	$( "#pfForm" ).submit(function( event ) {
		$( '.pfSpreadsheet' ).each( function() {
			var $grid = $(this),
				templateName = $(this).attr( 'data-template-name' ),
				formName = $(this).attr( 'data-form-name' ),
				editMultiplePages = $(this).attr( 'editMultiplePages' );

			$grid.find( "td" ).not('.readonly').each( function() {
				var rowNum = $(this).attr('data-y');
				var columnNum = $(this).attr('data-x');
				if ( rowNum == undefined || columnNum == undefined ) {
					return;
				}

				var paramType = getjExcelType( gridParams[templateName][columnNum].type );
				if ( paramType == 'checkbox' ) {
					var value = $(this).find('input').prop( 'checked' ) ?
						mw.config.get( 'wgPageFormsContLangYes' ) :
						mw.config.get( 'wgPageFormsContLangNo' );
				} else {
					var value = $(this).html();
				}
				var paramName = gridParams[templateName][columnNum].name;
				var inputName = templateName + '[' + ( rowNum + 1 ) + '][' + paramName + ']';
				$('<input>').attr( 'type', 'hidden' ).attr( 'name', inputName ).attr( 'value', value ).appendTo( '#pfForm' );
			});
		});
	});


}( jQuery, mediaWiki, pf ) );
