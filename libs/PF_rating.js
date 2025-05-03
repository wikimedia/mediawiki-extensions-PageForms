( function( $, mw, pf ) {
	'use strict';

	jQuery.fn.applyRatingInput = function( fromCalendar ) {
		const starWidth = $(this).attr('data-starwidth');
		let curValue = '';
		if ( starWidth === undefined ) {
			// This is probably because we're in a multple-instance
			// template "starter", but, in any case, just exit.
			return;
		}

		if( fromCalendar !== undefined ) {
			curValue = fromCalendar;
		} else {
			curValue = $(this).attr('data-curvalue');
		}
		if ( curValue === '' || curValue === undefined ) {
			curValue = 0;
		}
		const numStars = $(this).attr('data-numstars');
		const allowsHalf = $(this).attr('data-allows-half');
		const disabled = $(this).attr('disabled');
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
		if ( disabled === "disabled" ) {
			ratingsSettings.readOnly = true;
		}

		$(this).rateYo(ratingsSettings)
		.on("rateyo.set", function (e, data) {

			$(this).parent().children(":hidden").attr("value", data.rating);
		});
	};

}( jQuery, mediaWiki, pf ) );
