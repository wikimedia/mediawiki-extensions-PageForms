/**
 * @author Nischay Nahata
 */

( function( $, mw, pf ) {
	$( ".simpleupload_btn" ).each(function(){
		var _this = $(this);
		var input = _this.parent().find('#' + _this.data('id'));
		input.hide();
		if ( input.val() !== '' ) {
			_this.val( mw.message( 'pf_forminputs_change_file' ).text() );
			$('<img class="simpleupload_prv" src="' +
				mw.config.get('wgArticlePath').replace('$1', 'Special:Redirect/file/' + encodeURIComponent( input.val() ) + '?width=100') +'">').insertAfter(input);
			_this.parent().find('.simpleupload_rmv_btn').show();
		}
	});

	$( ".simpleupload_rmv_btn" ).click(function () {
		var _this = $(this);
		var input = _this.parent().find('#' + _this.data('id'));
		_this.parent().find('img.simpleupload_prv').remove();
		_this.parent().find( "input[type='file']" ).val('');
		input.val('');
		_this.hide();
		_this.parent().find('.simpleupload_btn').val( mw.message( 'pf-simpleupload' ).text() );
	});

	$( ".simpleupload_btn" ).click(function () {
		$(this).parent().find("input[type='file']").trigger('click');
	});

	$( "input[type='file'].simpleupload" ).change(function(event) {
		var _this = $(this);
		var input = _this.parent().find('#' + _this.data('id'));
		var fileToUpload = event.target.files[0]; // get (first) File
		var fileName = event.target.files[0].name;

		var formdata = new FormData(); //see https://developer.mozilla.org/en-US/docs/Web/API/FormData/Using_FormData_Objects
		formdata.append("action", "upload");
		formdata.append("format", "json");
		formdata.append("ignorewarnings", "true");
		formdata.append("filename", fileName);
		formdata.append("token", mw.user.tokens.get( 'editToken' ) );
		formdata.append("file", fileToUpload);

		_this.parent().find('.simpleupload_btn').hide();
		_this.parent().find('img.loading').show();
		// As we now have created the data to send, we send it...
		$.ajax( { //http://stackoverflow.com/questions/6974684/how-to-send-formdata-objects-with-ajax-requests-in-jquery
			url: mw.util.wikiScript( 'api' ), //url to api.php
			contentType:false,
			processData:false,
			type:'POST',
			data: formdata,//the formdata object we created above
			success:function( data ){
				//do what you like, console logs are just for demonstration :-)
				if ( !data.error ) {
					input.val(fileName);
					input.parent().find('img.simpleupload_prv').remove();
					var imagePreviewURL = mw.config.get('wgArticlePath').replace( '$1', 'Special:Redirect/file/' + encodeURIComponent( input.val() ) );
					imagePreviewURL += ( imagePreviewURL.indexOf('?') === -1 ) ? '?' : '&';
					imagePreviewURL += 'width=100';
					$('<img class="simpleupload_prv" src="' + imagePreviewURL +'">').insertAfter(input);
					_this.parent().find('.simpleupload_btn').show().val( mw.message( 'pf_forminputs_change_file' ).text() );
					_this.parent().find('img.loading').hide();
					_this.parent().find('.simpleupload_rmv_btn').show();
				} else {
					window.alert("Error: " + data.error.info);
					_this.parent().find('.simpleupload_btn').show().val( mw.message( 'pf-simpleupload' ).text() );
					_this.parent().find('img.loading').hide();
				}
			},
			error:function( xhr,status, error ){
				window.alert('Something went wrong! Please check the log for errors');
				_this.parent().find('.simpleupload_btn').show().val( mw.message( 'pf-simpleupload' ).text() );
				_this.parent().find('img.loading').hide();
				mw.log(error);
			}
		});
	});
}( jQuery, mediaWiki, pf ) );
