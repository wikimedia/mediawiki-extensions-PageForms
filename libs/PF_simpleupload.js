/**
 * Javascript Code to enable simple upload functionality using OOUI's SelectFileInputWidget
 * for "combobox" and "text" input types
 *
 * @author Nischay Nahata
 * @author Yaron Koren
 * @author Yash Varshney
 */

( function( $, mw ) {
	$.fn.initializeSimpleUpload = function() {

		var inputSpan = this.parent();
		var uploadWidget = new OO.ui.SelectFileInputWidget( {
			button: {
				flags: [
					'progressive'
				],
				icon: 'upload',
				label: mw.message( 'pf-simpleupload' ).text()
			},
			classes: [ 'simpleUpload' ]
		} );

		var removeButton = new OO.ui.ButtonWidget( {
			label: mw.message( 'htmlform-cloner-delete' ).text(),
			flags: [
				'destructive'
			],
			icon: 'trash',
			classes: [ 'simpleupload_rmv_btn' ]
		} );

		var buttonRow = new OO.ui.HorizontalLayout( {
			items: [
				uploadWidget,
				removeButton
			]
		} );

		var input,
			cur_value = '',
			loadingImage = inputSpan.find('img.loading');

		// append a row of buttons for upload and remove
		inputSpan.find('span.simpleUploadInterface').append(buttonRow.$element);

		if ( inputSpan.attr('data-input-type') == 'combobox' ) {
			input = inputSpan.find('input[role="combobox"]');
			loadingImage.remove();
			inputSpan.prepend(loadingImage);
			// this has been done to align all buttons with combobox properly
			// in a horizontal manner
			inputSpan.find('span.simpleUploadInterface > div.oo-ui-layout').css( {
				'display': 'inline',
				'margin-left': '-15px'
			} );
			inputSpan.find('div.oo-ui-textInputWidget').css( {
				'margin-top': '-15px',
				'display': 'inline-block'
			} );
			inputSpan.find('div.simpleUpload').css('margin-top','-6px');
			inputSpan.find('span.simpleupload_rmv_btn').css('margin-top','-6px');
		} else {
			input = inputSpan.find('input.createboxInput');
		}

		cur_value = input.val();

		// hide the remove button for now considering that no file is displayed
		removeButton.$element.hide()

		// remove the input part from SelectInputWidget leaving only button
		inputSpan.find('div.oo-ui-actionFieldLayout-input').remove();
		// adjust the size of the parent div
		inputSpan.find('div.simpleUpload').css('width', '100px');
		if ( inputSpan.attr('data-input-type') == 'text' ) {
			input.hide();
		}

		if ( cur_value !== '' && typeof cur_value !== 'undefined' ) {
			var previewURL = mw.config.get('wgArticlePath').replace('$1', 'Special:Redirect/file/' + encodeURIComponent( cur_value ) );
			previewURL += ( previewURL.indexOf('?') < 0 ) ? '?' : '&';
			previewURL += 'width=100';
			inputSpan.prepend($('<img class="simpleupload_prv" src="' + previewURL + '">'));

			// now display the remove button for removing the file displayed
			removeButton.$element.show();
		}

		removeButton.$element.find('a').click( function () {
			inputSpan.find('img.simpleupload_prv').remove();
			cur_value = '';
			input.val('');
			removeButton.$element.hide();
		});

		inputSpan.find('span.simpleUploadInterface').find('input[type="file"]').change( function(event) {
			var fileToUpload = event.target.files[0]; // get (first) File
			var fileName = event.target.files[0].name;

			var formdata = new FormData(); // see https://developer.mozilla.org/en-US/docs/Web/API/FormData/Using_FormData_Objects
			formdata.append("action", "upload");
			formdata.append("format", "json");
			formdata.append("ignorewarnings", "true");
			formdata.append("filename", fileName);
			formdata.append("token", mw.user.tokens.get( 'csrfToken' ) );
			formdata.append("file", fileToUpload);

			loadingImage.show();
			// As we now have created the data to send, we send it...
			$.ajax( { // http://stackoverflow.com/questions/6974684/how-to-send-formdata-objects-with-ajax-requests-in-jquery
				url: mw.util.wikiScript( 'api' ), // url to api.php
				contentType:false,
				processData:false,
				type:'POST',
				data: formdata,// the formdata object we created above
				success: function( data ) {
					// do what you like, console logs are just for demonstration :-)
					if ( !data.error ) {
						cur_value = fileName;

						// give the fileName to the field overwriting whatever was wrtitten there
						input.val(fileName);

						inputSpan.find('img.simpleupload_prv').remove();
						var imagePreviewURL = mw.config.get('wgArticlePath').replace( '$1', 'Special:Redirect/file/' + encodeURIComponent( cur_value ) );
						imagePreviewURL += ( imagePreviewURL.indexOf('?') === -1 ) ? '?' : '&';
						imagePreviewURL += 'width=100';
						inputSpan.prepend('<img class="simpleupload_prv" src="' + imagePreviewURL + '">');
						loadingImage.hide();
						removeButton.$element.show();
					} else {
						window.alert("Error: " + data.error.info);
						// if any error pops up, just hide the remove button
						removeButton.$element.hide();
						loadingImage.hide();
					}
				},
				error: function( xhr,status, error ) {
					window.alert('Something went wrong! Please check the log for errors');
					removeButton.$element.hide();
					loadingImage.hide();
					mw.log(error);
				}
			});
		});

	};

}( jQuery, mediaWiki ) );
