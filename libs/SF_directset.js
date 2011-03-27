/**
 * Javascript handler for the directset parser function
 *
 * @author Stephan Gambke
 */

jQuery(function($){

	$('.directset-trigger').click(function(){

		if ( wgUserName == null ) {
			if ( confirm( sfgAnonEditWarning ) ) {
				handleDirectSet( this );
			}
		} else {
			handleDirectSet( this );
		}

		return false;
	});

	function handleDirectSet( trigger ){
		var jtrigger = jQuery( trigger );
		var jdirectset = jtrigger.closest( '.directset' );
		var jresult = jdirectset.find('.directset-result');

		var reload = jtrigger.hasClass( 'reload' );

		var data = new Array();
		data.push( jdirectset.find('form.directset-data').serialize() );

		jtrigger.attr('class', 'directset-trigger directset-trigger-wait');
		jresult.attr('class', 'directset-result directset-result-wait');

		jresult[0].innerHTML="Wait..."; // TODO: replace by localized message

		sajax_request_type = 'POST';

		sajax_do_call( 'SFDirectSetAjaxHandler::handleDirectSet', data, function( ajaxHeader ){
			jresult.empty().append( ajaxHeader.responseText );

			if ( ajaxHeader.status == 200 ) {

				if ( reload ) window.location.reload();

				jresult.removeClass('directset-result-wait').addClass('directset-result-ok');
				jtrigger.removeClass('directset-trigger-wait').addClass('directset-trigger-ok');
			} else {
				jresult.removeClass('directset-result-wait').addClass('directset-result-error');
				jtrigger.removeClass('directset-trigger-wait').addClass('directset-trigger-error');
			}
		} );
	}

})
