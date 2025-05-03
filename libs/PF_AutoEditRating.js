( function( $, mw ) {
	'use strict';

	function sendData( $jtrigger ) {
		const $jautoedit = $jtrigger.closest( '.autoedit' );
		const $jresult = $jautoedit.find( '.autoedit-result' );

		$jresult.attr( 'class', 'autoedit-result autoedit-result-wait' );
		$jresult.text( mw.msg( 'pf-autoedit-wait' ) );

		// data array to be sent to the server
		const data = {
			action: 'pfautoedit',
			format: 'json'
		};

		// add form values to the data
		data.query = $jautoedit.find( 'form.autoedit-data' )
			.serialize();
		$.ajax( {
			type: 'POST', // request type ( GET or POST )
			url: mw.util.wikiScript( 'api' ), // URL to which the request is sent
			data: data, // data to be sent to the server
			dataType: 'json', // type of data expected back from the server
			success: function( result ) {
				$jresult.empty()
					.append( result.responseText );
				if ( result.status === 200 ) {
					$jresult.removeClass( 'autoedit-result-wait' )
						.addClass( 'autoedit-result-ok' );
				} else {
					$jresult.removeClass( 'autoedit-result-wait' )
						.addClass( 'autoedit-result-error' );
				}
			}, // function to be called if the request succeeds
			error: function( jqXHR ) {
					const result = jQuery.parseJSON( jqXHR.responseText );
					let text = result.responseText;

					for ( let i = 0; i < result.errors.length; i++ ) {
						text += ' ' + result.errors[ i ].message;
					}

					$jresult.empty()
						.append( text );
					$jresult.removeClass( 'autoedit-result-wait' )
						.addClass( 'autoedit-result-error' );
				} // function to be called if the request fails
		} );
	}

	function handleAutoEditRating( $jtrigger, value ) {
		if ( mw.config.get( 'wgUserName' ) === null &&
			!confirm( mw.msg( 'pf_autoedit_anoneditwarning' ) ) ) {
			return;
		}
		const $jautoedit = $jtrigger.closest( '.autoedit' );
		const $jeditdata = $jautoedit.find( 'form.autoedit-data' );
		$jeditdata.find( '#ratingInput' )
			.attr( "value", value );;
		const targetpage = $jeditdata.find( 'input[name=target]' )
			.val();
		const confirmEdit = $jeditdata.hasClass( 'confirm-edit' );
		if ( confirmEdit ) {
			OO.ui.confirm( mw.msg( 'pf_autoedit_confirm', targetpage ) )
				.done( (confirmed) => {
					if ( confirmed ) {
						sendData( $jtrigger );
					}
				} )
		} else {
			sendData( $jtrigger );
		}
	};

	jQuery.fn.applyRatingInput = function( fromCalendar ) {
		const starWidth = $( this )
			.attr( 'data-starwidth' );
		let curValue = $( this )
			.attr( 'data-curvalue' );;
		const numStars = $( this )
			.attr( 'data-numstars' );
		const allowsHalf = $( this )
			.attr( 'data-allows-half' );

		if ( curValue === '' || curValue === undefined ) {
			curValue = 0;
		}

		const ratingsSettings = {
			normalFill: '#ddd',
			starWidth: starWidth,
			numStars: numStars,
			maxValue: numStars,
			rating: curValue
		};

		if ( allowsHalf === undefined ) {
			ratingsSettings.fullStar = true;
		} else {
			ratingsSettings.halfStar = true;
		}

		$( this )
			.rateYo( ratingsSettings )
			.on( "rateyo.set", function( e, data ) {
				handleAutoEditRating( $( this )
					.parent(), data.rating );
			} );
	};

	$( function() {
		$( this )
			.find( '.pfRating' )
			.each( function() {
				$( this )
					.applyRatingInput();
			} )
	} );

}( jQuery, mediaWiki ) );
