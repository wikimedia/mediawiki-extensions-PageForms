/* global moment */
( function () {

	mw.widgets.PFDateInputWidget = function PFDateInputWidget(config ) {
		let inputFormat;

		if ( config.inputFormat ) {
			inputFormat = config.inputFormat.split( ';' );
			if ( inputFormat.length > 1 ) {
				config.inputFormat = inputFormat.sort( ( a, b ) => a.length - b.length );
			}
		}

		// Parent constructor
		mw.widgets.PFDateInputWidget.parent.call( this, config );
	};

	/* Inheritance */

	OO.inheritClass( mw.widgets.PFDateInputWidget, mw.widgets.DateInputWidget );

	/* Methods */

	/**
	 *  There is a bug in DateInputWidget, it calls this function for internal date
	 *  this.getValue() in this.getValidity() function and entered date
	 *  this.textInput.getValue() in this.onTextInputChange()
	 *  but they can have different formats
	 *
	 * @param date
	 * @param format
	 */
	mw.widgets.PFDateInputWidget.prototype.isValidDate = function ( date, format ) {
		const
			mom = moment( date, format || this.getInternalFormat( true ) ),
			flags = mom.parsingFlags();

		return mom.isValid() && flags.charsLeftOver === 0 && flags.unusedTokens.length === 0;
	};

	mw.widgets.PFDateInputWidget.prototype.onTextInputChange = function () {
		let mom,
			inputFormat = this.getInputFormat(),
			value = this.textInput.getValue(),
			valid = this.isValidDate( value, inputFormat );

		if ( value === '' || !valid || typeof inputFormat === 'string' ) {
			return mw.widgets.PFDateInputWidget.parent.prototype.onTextInputChange.call( this );
		}

		this.inTextInput++;
		// Well-formed date value, parse and set it
		mom = moment( value, inputFormat );
		// Use English locale to avoid number formatting
		this.setValue( mom.locale( 'en' ).format( this.getInternalFormat( mom ) ) );
		this.inTextInput--;
	};

	mw.widgets.PFDateInputWidget.prototype.getInternalFormat = function ( mom ) {
		const internalFormats = [
			'YYYY',
			'YYYY-MM',
			'YYYY-MM-DD',
		];

		if ( !mom ) {
			return mw.widgets.PFDateInputWidget.parent.prototype.getInternalFormat.call( this );
		} else if ( mom === true ) {
			return internalFormats;
		}
		return internalFormats[ mom.parsingFlags().parsedDateParts.length - 1 ];
	};

	mw.widgets.PFDateInputWidget.prototype.updateUI = function () {
		let moment,
			format,
			parsedDatePartsLength,
			inputFormat = this.getInputFormat();

		if ( this.getValue() === '' || typeof inputFormat === 'string' ) {
			return mw.widgets.PFDateInputWidget.parent.prototype.updateUI.call( this );
		}

		moment = this.getMoment();
		parsedDatePartsLength = moment.parsingFlags().parsedDateParts.length;
		if ( parsedDatePartsLength === 1 ) {
			// Minimum length of the input format for a year
			format = inputFormat[0];
		} else {
			// Maximum length of the input format by default
			format = inputFormat[inputFormat.length - 1];
			if ( parsedDatePartsLength === 2 && format.includes('D') ) {
				// Use shorter format when day is not in moment but format has day
				format = inputFormat[inputFormat.length - 2];
			}
		}
		if ( !this.inTextInput ) {
			this.textInput.setValue( moment.format( format ) );
		}
		if ( !this.inCalendar ) {
			this.calendar.setDate( this.getValue() );
		}
		this.innerLabel.setLabel( moment.format( format ) );
		this.$element.removeClass( 'mw-widget-dateInputWidget-empty' );
	};

}() );
