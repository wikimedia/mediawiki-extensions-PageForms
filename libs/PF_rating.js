( function( $, mw, pf ) {
        'use strict';

	jQuery.fn.applyRatingInput = function( fromCalendar ) {
		var starWidth = $(this).attr('data-starwidth');
		var curValue = '';
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
		var numStars = $(this).attr('data-numstars');
		var allowsHalf = $(this).attr('data-allows-half');
		var disabled = $(this).attr('disabled');
		var ratingsSettings = {
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
