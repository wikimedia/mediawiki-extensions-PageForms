<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFDateInput extends PFFormInput {
	public static function getName() {
		return 'date';
	}

	public static function getDefaultPropTypes() {
		return [ '_dat' => [] ];
	}

	public static function getDefaultCargoTypes() {
		return [
			'Date' => [],
			'Start date' => [],
			'End date' => []
		];
	}

	public static function monthDropdownHTML( $cur_month, $input_name, $is_disabled ) {
		global $wgPageFormsTabIndex, $wgAmericanDates;

		$optionsText = '';
		$month_names = PFFormUtils::getMonthNames();
		// Add a "null" value at the beginning.
		array_unshift( $month_names, null );
		foreach ( $month_names as $i => $name ) {
			if ( $name === null ) {
				$month_value = null;
			} else {
				// Pad out month to always be two digits.
				$month_value = ( $wgAmericanDates == true ) ? $name : str_pad( $i, 2, '0', STR_PAD_LEFT );
			}
			$optionAttrs = [ 'value' => $month_value ];
			if ( $name == $cur_month || $i == $cur_month ) {
				$optionAttrs['selected'] = 'selected';
			}
			$optionsText .= Html::element( 'option', $optionAttrs, $name );
		}
		$selectAttrs = [
			'class' => 'monthInput',
			'name' => $input_name . '[month]',
			'tabindex' => $wgPageFormsTabIndex
		];
		if ( $is_disabled ) {
			$selectAttrs['disabled'] = 'disabled';
		}
		$text = Html::rawElement( 'select', $selectAttrs, $optionsText );
		return $text;
	}

	static function parseDate( $date, $includeTime = false ) {
		global $wgLanguageCode;

		// Special handling for 'default=now'.
		if ( $date == 'now' ) {
			global $wgLocaltimezone;
			if ( isset( $wgLocaltimezone ) ) {
				$serverTimezone = date_default_timezone_get();
				date_default_timezone_set( $wgLocaltimezone );
			}
			$year = date( 'Y' );
			$month = date( 'm' );
			$day = date( 'j' );
			if ( $includeTime ) {
				$time = date( 'H:i:s' );
			}

			if ( isset( $wgLocaltimezone ) ) {
				date_default_timezone_set( $serverTimezone );
			}
			if ( $includeTime ) {
				return [ $year, $month, $day, $time ];
			} else {
				return [ $year, $month, $day ];
			}
		}

		// All other dates.
		if ( ctype_digit( $date ) ) {
			return [ $date, null, null ];
		}

		// Convert any date format to ISO standards.
		$date = str_replace( "/", "-", $date );
		// Special handling for "MM.YYYY" format.
		if ( preg_match( '/^(\d\d)\.(\d\d\d\d)$/', $date, $matches ) ) {
			$date = $matches[2] . '-' . $matches[1];
		}
		// Returns an array with detailed information about the date.
		$date_array = date_parse( $date );

		// If parsing didn't work, it may be because the
		// date contains a month name in a language other than English.
		// (Page Forms only puts in a month name if there's no day
		// value, but the date text could also be coming from an
		// outside source.)
		if ( $date_array['error_count'] > 0 && $wgLanguageCode != 'en' ) {
			$date = strtolower( $date );
			$monthNames = PFFormUtils::getMonthNames();
			$englishMonthNames = [ 'January', 'February',
				'March', 'April', 'May', 'June', 'July',
				'August', 'September', 'October', 'November',
				'December' ];
			foreach ( $monthNames as $i => $monthName ) {
				$monthName = strtolower( $monthName );
				if ( strpos( $date, $monthName ) !== false ) {
					$englishMonthName = $englishMonthNames[$i];
					$date = str_replace( $monthName,
						$englishMonthName, $date );
					break;
				}
			}
			$date_array = date_parse( $date );
		}

		if ( $date_array['error_count'] > 0 ) {
			return null;
		}

		$year = $date_array['year'];
		$month = $date_array['month'];
		$day = $date_array['day'];
		if ( $includeTime ) {
			$time = sprintf( '%02d:%02d:%02d', $date_array['hour'],
				$date_array['minute'], $date_array['second'] );
		}

		// Determine if there's a month but no day. There's no ideal
		// way to do this, so: we'll just look for the total
		// number of spaces and dashes, and if there's
		// exactly one altogether, we'll guess that it's a month only.
		$numSpecialChars = substr_count( $date, ' ' ) + substr_count( $date, '-' );
		if ( $numSpecialChars == 1 ) {
			// For the case of date format Month YYYY
			if ( $date_array['error_count'] > 0 ) {
				// Separating date into its individual components
				$dateParts = explode( " ", $date );
				$month = $dateParts[0];
				$year = $dateParts[1];
			}
			return [ $year, $month, null ];

		}

		if ( $includeTime ) {
			return [ $year, $month, $day, $time ];
		} else {
			return [ $year, $month, $day ];
		}
	}

	public static function getMainHTML( $date, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgAmericanDates;

		$year = $month = $day = null;

		if ( $date ) {
			// Can show up here either as an array or a string,
			// depending on whether it came from user input or a
			// wiki page.
			if ( is_array( $date ) ) {
				$year = $date['year'];
				$month = $date['month'];
				$day = $date['day'];
			} else {
				list( $year, $month, $day ) = self::parseDate( $date );
			}
		} else {
			// Just keep everything at null.
		}
		$text = "";
		$disabled_text = ( $is_disabled ) ? 'disabled' : '';
		$monthInput = self::monthDropdownHTML( $month, $input_name, $is_disabled );
		$dayInput = '	<input tabindex="' . $wgPageFormsTabIndex . '" class="dayInput" name="' . $input_name . '[day]" type="text" value="' . $day . '" size="2" ' . $disabled_text . '/>';
		if ( $wgAmericanDates ) {
			$text .= "$monthInput\n$dayInput\n";
		} else {
			$text .= "$dayInput\n$monthInput\n";
		}
		$text .= '	<input tabindex="' . $wgPageFormsTabIndex . '" class="yearInput" name="' . $input_name . '[year]" type="text" value="' . $year . '" size="4" ' . $disabled_text . '/>' . "\n";
		return $text;
	}

	public static function getHTML( $date, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		$text = self::getMainHTML( $date, $input_name, $is_mandatory, $is_disabled, $other_args );
		$spanClass = 'dateInput';
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}
		$text = Html::rawElement( 'span', [ 'class' => $spanClass ], $text );
		return $text;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText() {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}
}
