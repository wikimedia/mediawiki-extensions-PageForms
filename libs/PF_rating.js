( function( $, mw, pf ) {
        'use strict';

	jQuery.fn.applyRatingInput = function() {
		var starWidth = $(this).attr('data-starwidth');
		if ( starWidth === undefined ) {
			// This is probably because we're in a multple-instance
			// template "starter", but, in any case, just exit.
			return;
		}
		var curValue = $(this).attr('data-curvalue');
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
