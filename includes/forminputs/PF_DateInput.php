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
		return array( '_dat' => array() );
	}

	public static function getDefaultCargoTypes() {
		return array( 'Date' => array() );
	}

	public static function monthDropdownHTML( $cur_month, $input_name, $is_disabled ) {
		global $wgPageFormsTabIndex, $wgAmericanDates;

		$optionsText = '';
		$month_names = PFFormUtils::getMonthNames();
		// Add a "null" value at the beginning.
		array_unshift( $month_names, null );
		foreach ( $month_names as $i => $name ) {
			if ( is_null( $name ) ) {
				$month_value = null;
			} else {
				// Pad out month to always be two digits.
				$month_value = ( $wgAmericanDates == true ) ? $name : str_pad( $i, 2, '0', STR_PAD_LEFT );
			}
			$optionAttrs = array( 'value' => $month_value );
			if ( $name == $cur_month || $i == $cur_month ) {
				$optionAttrs['selected'] = 'selected';
			}
			$optionsText .= Html::element( 'option', $optionAttrs, $name );
		}
		$selectAttrs = array(
			'class' => 'monthInput',
			'name' => $input_name . '[month]',
			'tabindex' => $wgPageFormsTabIndex
		);
		if ( $is_disabled ) {
			$selectAttrs['disabled'] = 'disabled';
		}
		$text = Html::rawElement( 'select', $selectAttrs, $optionsText );
		return $text;
	}

	static function parseDate( $date ) {
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
			if ( isset( $wgLocaltimezone ) ) {
				date_default_timezone_set( $serverTimezone );
			}
			return array( $year, $month, $day );
		}

		// All other dates.
		if ( ctype_digit( $date ) ) {
			return array( $date, null, null );
		}

		$seconds = strtotime( $date );

		// If strtotime() parsing didn't work, it may be because the
		// date contains a month name in a language other than English.
		// (Page Forms only puts in a month name if there's no day
		// value, but the date text could also be coming from an
		// outside source.)
		if ( $seconds == null && $wgLanguageCode != 'en' ) {
			$date = strtolower( $date );
			$monthNames = PFFormUtils::getMonthNames();
			$englishMonthNames = array( 'January', 'February',
				'March', 'April', 'May', 'June', 'July',
				'August', 'September', 'October', 'November',
				'December' );
			foreach ( $monthNames as $i => $monthName ) {
				$monthName = strtolower( $monthName );
				if ( strpos( $date, $monthName ) !== false ) {
					$englishMonthName = $englishMonthNames[$i];
					$date = str_replace( $monthName,
						$englishMonthName, $date );
					break;
				}
			}
			$seconds = strtotime( $date );
		}

		// If we still don't have a date value, exit.
		if ( $seconds == null ) {
			return array( null, null, null );
		}

		$year = date( 'Y', $seconds );
		$month = date( 'm', $seconds );
		// Determine if there's a month but no day. There's no ideal
		// way to do this, so: we'll just look for the total
		// number of spaces, slashes and dashes, and if there's
		// exactly one altogether, we'll guess that it's a month only.
		$numSpecialChars = substr_count( $date, ' ' ) + substr_count( $date, '/' ) + substr_count( $date, '-' );
		if ( $numSpecialChars == 1 ) {
			return array( $year, $month, null );
		}

		$day = date( 'j', $seconds );
		return array( $year, $month, $day );
	}

	public static function getMainHTML( $date, $input_name, $is_mandatory, $is_disabled, $other_args ) {
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

	public static function getHTML( $date, $input_name, $is_mandatory, $is_disabled, $other_args ) {
		$text = self::getMainHTML( $date, $input_name, $is_mandatory, $is_disabled, $other_args );
		$spanClass = 'dateInput';
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}
		$text = Html::rawElement( 'span', array( 'class' => $spanClass ), $text );
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
