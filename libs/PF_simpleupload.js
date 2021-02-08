/**
 * @author Nischay Nahata
 * @author Yaron Koren
 */

( function( $, mw ) {
	$.fn.initializeSimpleUpload = function() {

		var uploadButton = this.find( ".simpleupload_btn" );
		var removeButton = this.find( ".simpleupload_rmv_btn" );
		var fileButton = this.find( ".simpleupload" );
		var inputSpan = this.parent();
		var input = inputSpan.find('input.createboxInput');
		var loadingImage = inputSpan.find('img.loading');

		input.hide();
		if ( input.val() !== '' && typeof input.val() !== 'undefined' ) {
			uploadButton.val( mw.message( 'pf_forminputs_change_file' ).text() );
			var previewURL = mw.config.get('wgArticlePath').replace('$1', 'Special:Redirect/file/' + encodeURIComponent( input.val() ) );
			previewURL += ( previewURL.indexOf('?') < 0 ) ? '?' : '&';
			previewURL += 'width=100';
			$('<img class="simpleupload_prv" src="' + previewURL + '">').insertAfter(input);
			removeButton.show();
		}

		removeButton.click( function () {
			inputSpan.find('img.simpleupload_prv').remove();
			fileButton.val('');
			input.val('');
			removeButton.hide();
			uploadButton.val( mw.message( 'pf-simpleupload' ).text() );
		});

		uploadButton.click( function () {
			fileButton.trigger('click');
		});

		fileButton.change( function(event) {
			var fileToUpload = event.target.files[0]; // get (first) File
			var fileName = event.target.files[0].name;

			var formdata = new FormData(); //see https://developer.mozilla.org/en-US/docs/Web/API/FormData/Using_FormData_Objects
			formdata.append("action", "upload");
			formdata.append("format", "json");
			formdata.append("ignorewarnings", "true");
			formdata.append("filename", fileName);
			formdata.append("token", mw.user.tokens.get( 'csrfToken' ) );
			formdata.append("file", fileToUpload);

			uploadButton.hide();
			loadingImage.show();
			// As we now have created the data to send, we send it...
			$.ajax( { //http://stackoverflow.com/questions/6974684/how-to-send-formdata-objects-with-ajax-requests-in-jquery
				url: mw.util.wikiScript( 'api' ), //url to api.php
				contentType:false,
				processData:false,
				type:'POST',
				data: formdata,//the formdata object we created above
				success: function( data ) {
					//do what you like, console logs are just for demonstration :-)
					if ( !data.error ) {
						input.val(fileName);
						input.parent().find('img.simpleupload_prv').remove();
						var imagePreviewURL = mw.config.get('wgArticlePath').replace( '$1', 'Special:Redirect/file/' + encodeURIComponent( input.val() ) );
						imagePreviewURL += ( imagePreviewURL.indexOf('?') === -1 ) ? '?' : '&';
						imagePreviewURL += 'width=100';
						$('<img class="simpleupload_prv" src="' + imagePreviewURL +'">').insertAfter(input);
						uploadButton.show().val( mw.message( 'pf_forminputs_change_file' ).text() );
						loadingImage.hide();
						removeButton.show();
					} else {
						window.alert("Error: " + data.error.info);
						uploadButton.show().val( mw.message( 'pf-simpleupload' ).text() );
						loadingImage.hide();
					}
				},
				error: function( xhr,status, error ) {
					window.alert('Something went wrong! Please check the log for errors');
					uploadButton.show().val( mw.message( 'pf-simpleupload' ).text() );
					loadingImage.hide();
					mw.log(error);
				}
			});
		});

	};

}( jQuery, mediaWiki ) );
