/**
 * Javascript handler for the autoedit parser function
 *
 * @author Stephan Gambke
 * @author Pim Bax
 */

/*global confirm */

( function ( $, mw ) {

	'use strict';

	function autoEditAPIError( jqXHR, textStatus, errorThrown ) {
		var $trigger = $(this);
		var $result = $trigger.closest('.autoedit').find('.autoedit-result');
		if (jqXHR) {
			var result = $.parseJSON(jqXHR.responseText);
			var text = result.responseText;

			for ( var i = 0; i < result.errors.length; i++ ) {
				text += ' ' + result.errors[i].message;
			}
		} else {
			var text = textStatus;
		}
		$result.empty().append( text );
		$result.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-error' );
		$trigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-error' );
	}

	function skipWhiteSpace( string, index ) {
		//keep looping until we find a non-whitespace character
		while (' \n\t'.indexOf(string.charAt(index)) != -1)
			index++;
		return index;
	}

	function autoEditHandler() {

		if ( mw.config.get( 'wgUserName' ) === null &&
			! confirm( mw.msg( 'pf_autoedit_anoneditwarning' ) ) ) {

			return;
		}

		var jtrigger = $( this );
		var jautoedit = jtrigger.closest( '.autoedit' );
		var jresult = jautoedit.find( '.autoedit-result' );

		var reload = jtrigger.hasClass( 'reload' );

		jtrigger.attr( 'class', 'autoedit-trigger autoedit-trigger-wait' );
		jresult.attr( 'class', 'autoedit-result autoedit-result-wait' );

		jresult.text( mw.msg( 'pf-autoedit-wait' ) );


		// data array to be sent to the server
		var data = {
			action: 'pfautoedit',
			format: 'json'
		};

		// add form values to the data
		data.query =  jautoedit.find( 'form.autoedit-data' ).serialize();

		$.ajax( {

			type:     'POST', // request type ( GET or POST )
			url:      mw.util.wikiScript( 'api' ), // URL to which the request is sent
			data:     data, // data to be sent to the server
			dataType: 'json', // type of data expected back from the server
			success:  function ( result ) {
				jresult.empty().append( result.responseText );

				if ( result.status === 200 ) {

					if ( reload ) {
						window.location.reload();
					}

					jresult.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-ok' );
					jtrigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-ok' );
				} else {
					jresult.removeClass( 'autoedit-result-wait' ).addClass( 'autoedit-result-error' );
					jtrigger.removeClass( 'autoedit-trigger-wait' ).addClass( 'autoedit-trigger-error' );
				}
			}, // function to be called if the request succeeds
			error: autoEditAPIError.bind(this), // function to be called if the request fails
		} );
	};

	function autoEditToggleHandler() {
		var $trigger = $( this );
		var $autoedit = $trigger.closest( '.autoedit' );
		var $result = $autoedit.find( '.autoedit-result' );
		var $data = $autoedit.find( 'form.autoedit-data' );

		$trigger.attr( 'class', 'autoedit-trigger autoedit-trigger-wait' );
		$result.attr( 'class', 'autoedit-result autoedit-result-wait' );

		$result.text( mw.msg( 'pf-autoedit-wait' ) );
		//first retrieve the current value stored in the `prop` on the target page.

		var inputs = {
			target: $data.find( '[name="target"]' ).val(),
			form: 	$data.find( '[name="form"]' ).val(),
			prop: 	$data.find( '[name="prop"]' ).val(),
			toggle: $data.find( '[name="toggle"]' ).val(),
			sep: 	$data.find( '[name="separator"]' ).val() || ',', // default separator is comma
			action: $data.find( '[name="action"]' ).val(),
		}

		var data = {
			action: 	'query',
			format: 	'json',
			prop: 		'revisions',
			titles: 	inputs.target,
			rvprop: 	'content',
		}

		$.ajax( {
			type: 		'POST',
			url: 		mw.util.wikiScript('api'),
			data: 		data,
			dataType: 	'json',
			success: 	function (result) {
				// get the first (and only) value in the returned object, without having to
				// retrieve the object's keys. For JavaScript5+ you could use:
				// var page = result.query.pages[Object.keys(result.query.pages)[0]]
				for (var page in result.query.pages)
					break; //first value has been read.
				if (page == -1) {
					autoEditAPIError.call($trigger, '', 'Page does not exist', 404);
					return;
				}
				var content = result.query.pages[page].revisions[0]['*'];
				//go to the occurrence of the form
				var form = inputs.form,
					start = (new RegExp('\\{\\{\\s*'+form)).exec(content).index,
					lvl = 0,
					end = start,
					found = false; // false as long as the param has not been found yet
				do { // Find property `prop` in outer level (ie. the form template itself)
					 // code efficiency: use `let` instead of `var` as soon as it is universally supported.
					 // see http://stackoverflow.com/q/21467642/1256925
					var c = content.charAt(end);
					if (found && c == '}' && lvl == 2)
						break; // end of parameter reached.
					if (c == '{')
						lvl++;
					else if (c == '}')
						lvl--;
					else if (lvl == 2 && c == '|') {
						if (!found) {
							// check if the current template parameter is the property to have a value toggled
							// skip current character and then space, newline and tab character after a |
							end = skipWhiteSpace(content, end+1);
							if (content.substr(end, inputs.prop.length) == inputs.prop) {
								// current parameter is the property in which to toggle something.
								// First skip parameter name length and whitespace:
								end = skipWhiteSpace(content, end + inputs.prop.length);
								// c is now the first character *after* the parameter we found.
								c = content.charAt(end);
								if (c != '=') // if this is not an =, we didn't find the correct parameter
									continue; // so: simply start looking further for the right parameter.
								start = end = skipWhiteSpace(content, end + 1); //Start of parameter contents.
								found = true; // mark parameter as found; treat next | differently.
								continue; // now simply look for the end of the template
							}
						} else {
							break; // we found the end of the parameter's contents.
						}
					}
					end++;
				} while (lvl > 0 && end < content.length);
				var re_split = new RegExp('\\s*' + inputs.sep + '\\s*');
				var values = $.trim(content.substring(start, end)).split(re_split);
				console.log(values);
				var idx = values.indexOf(inputs.toggle)
				if (idx == -1 && (inputs.action == 'add' || inputs.action == 'toggle'))
					values.push(inputs.toggle);
				else if (idx != -1 && (inputs.action == 'remove' || inputs.action == 'toggle'))
					values.splice(idx, 1);
				// with the new value found, start submitting the new value
				$('<input/>', {
					type: 'hidden',
					name: inputs.form + '[' + inputs.prop + ']',
					value: values.join(inputs.separator),
				}).appendTo($data); // add input to be submitted to the data form

				$data.find('[name="prop"], [name="toggle"], [name="separator"], [name="action"]').remove();
				window.autoEditHandler = autoEditHandler;
				autoEditHandler.call($trigger); // move on to submit the autoEdit with the generated  contents.
			},
			error: 	autoEditAPIError.bind($trigger),
		} );
	}

	$( function ( ) { // shorthand for $(document).ready(..)
		$( '.autoedit-trigger' ).click( autoEditHandler );
		$( '.autoedit-toggle' ).click( autoEditToggleHandler );
	} );

}( jQuery, mediaWiki ) );
