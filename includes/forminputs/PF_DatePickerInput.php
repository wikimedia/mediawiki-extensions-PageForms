<?php

/**
 * @author Stephan Gambke
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PF
 */
class PFDatePickerInput extends PFFormInput {

	/**
	 * @param string $input_number The number of the input in the form.
	 * @param string $cur_value The current value of the input field.
	 * @param string $input_name The name of the input.
	 * @param bool $disabled Is this input disabled?
	 * @param array $other_args An associative array of other parameters that were present in the
	 *  input definition.
	 */
	public function __construct( $input_number, $cur_value, $input_name, $disabled, $other_args ) {
		if ( $cur_value == 'now' ) {
			$cur_value = date( 'Y/m/d' );
		}

		parent::__construct( $input_number, $cur_value, $input_name, $disabled, $other_args );

		// call static setup
		self::setup();

		$this->addJsInitFunctionData( 'PF_DP_init', $this->setupJsInitAttribs() );
	}

	/**
	 * Returns the name of the input type this class handles.
	 *
	 * This is the name to be used in the field definition for the
	 * "input type" parameter.
	 *
	 * @return String The name of the input type this class handles.
	 */
	public static function getName() {
		return 'datepicker';
	}

	/**
	 * Returns the names of the resource modules this input type uses.
	 *
	 * Returns the names of the modules as an array or - if there is only
	 * one module - as a string.
	 *
	 * @return null|string|array
	 */
	public function getResourceModuleNames() {
		return array( 'jquery.ui.datepicker', 'ext.pageforms.datepicker' );
	}

	/**
	 * Static setup method for input type "datepicker".
	 * Adds the Javascript config settings used by all datepickers.
	 */
	private static function setup() {
		global $wgOut, $wgLang;

		static $hasRun = false;

		if ( !$hasRun ) {
			$hasRun = true;

			$regional = array(
				'closeText' => wfMessage( 'pf-datepicker-close' )->text(),
				'prevText' => wfMessage( 'pf-datepicker-prev' )->text(),
				'nextText' => wfMessage( 'pf-datepicker-next' )->text(),
				'currentText' => wfMessage( 'pf-datepicker-today' )->text(),
				'monthNames' => array(
					wfMessage( 'january' )->text(),
					wfMessage( 'february' )->text(),
					wfMessage( 'march' )->text(),
					wfMessage( 'april' )->text(),
					wfMessage( 'may_long' )->text(),
					wfMessage( 'june' )->text(),
					wfMessage( 'july' )->text(),
					wfMessage( 'august' )->text(),
					wfMessage( 'september' )->text(),
					wfMessage( 'october' )->text(),
					wfMessage( 'november' )->text(),
					wfMessage( 'december' )->text(),
				),
				'monthNamesShort' => array(
					wfMessage( 'jan' )->text(),
					wfMessage( 'feb' )->text(),
					wfMessage( 'mar' )->text(),
					wfMessage( 'apr' )->text(),
					wfMessage( 'may' )->text(),
					wfMessage( 'jun' )->text(),
					wfMessage( 'jul' )->text(),
					wfMessage( 'aug' )->text(),
					wfMessage( 'sep' )->text(),
					wfMessage( 'oct' )->text(),
					wfMessage( 'nov' )->text(),
					wfMessage( 'dec' )->text(),
				),
				'dayNames' => array(
					wfMessage( 'sunday' )->text(),
					wfMessage( 'monday' )->text(),
					wfMessage( 'tuesday' )->text(),
					wfMessage( 'wednesday' )->text(),
					wfMessage( 'thursday' )->text(),
					wfMessage( 'friday' )->text(),
					wfMessage( 'saturday' )->text(),
				),
				'dayNamesShort' => array(
					wfMessage( 'sun' )->text(),
					wfMessage( 'mon' )->text(),
					wfMessage( 'tue' )->text(),
					wfMessage( 'wed' )->text(),
					wfMessage( 'thu' )->text(),
					wfMessage( 'fri' )->text(),
					wfMessage( 'sat' )->text(),
				),
				'dayNamesMin' => array(
					$wgLang->firstChar( wfMessage( 'sun' )->text() ),
					$wgLang->firstChar( wfMessage( 'mon' )->text() ),
					$wgLang->firstChar( wfMessage( 'tue' )->text() ),
					$wgLang->firstChar( wfMessage( 'wed' )->text() ),
					$wgLang->firstChar( wfMessage( 'thu' )->text() ),
					$wgLang->firstChar( wfMessage( 'fri' )->text() ),
					$wgLang->firstChar( wfMessage( 'sat' )->text() ),
				),
				'weekHeader' => '',
				'dateFormat' => wfMessage( 'pf-datepicker-dateformatshort' )->text(),
				'firstDay' => wfMessage( 'pf-datepicker-firstdayofweek' )->text(),
				'isRTL' => $wgLang->isRTL(),
				'showMonthAfterYear' => false,
				'yearSuffix' => '',
			);

			$wgOut->addJsConfigVars( 'ext.pf.datepicker.regional', $regional );

		}
	}

	/**
	 * Set up JS attributes
	 *
	 * @return String
	 */
	protected function setupJsInitAttribs() {
		global $wgPageFormsDatePickerSettings, $wgExtensionAssetsPath;
		global $wgAmericanDates, $wgLang;

		// store user class(es) for use with buttons
		$userClasses = array_key_exists( 'class', $this->mOtherArgs ) ? $this->mOtherArgs['class'] : '';

		// set up attributes required for both enabled and disabled datepickers
		$jsattribs = array(
			'currValue' => $this->mCurrentValue,
			'disabled' => $this->mIsDisabled,
			'userClasses' => $userClasses
		);

		if ( array_key_exists( 'part of dtp', $this->mOtherArgs ) ) {
			$jsattribs['partOfDTP'] = $this->mOtherArgs['part of dtp'];
		}

		// set date format
		// SHORT and LONG are PF specific acronyms and are translated here
		// into format strings, anything else is passed to the jQuery date picker
		// Americans need special treatment
		if ( $wgAmericanDates && $wgLang->getCode() == "en" ) {
			if ( array_key_exists( 'date format', $this->mOtherArgs ) ) {
				if ( $this->mOtherArgs['date format'] == 'SHORT' ) {
					$jsattribs['dateFormat'] = 'mm/dd/yy';
				} elseif ( $this->mOtherArgs['date format'] == 'LONG' ) {
					$jsattribs['dateFormat'] = 'MM d, yy';
				} else {
					$jsattribs['dateFormat'] = $this->mOtherArgs['date format'];
				}
			} elseif ( $wgPageFormsDatePickerSettings["DateFormat"] ) {
				if ( $wgPageFormsDatePickerSettings["DateFormat"] == 'SHORT' ) {
					$jsattribs['dateFormat'] = 'mm/dd/yy';
				} elseif ( $wgPageFormsDatePickerSettings["DateFormat"] == 'LONG' ) {
					$jsattribs['dateFormat'] = 'MM d, yy';
				} else {
					$jsattribs['dateFormat'] = $wgPageFormsDatePickerSettings["DateFormat"];
				}
			} else {
				$jsattribs['dateFormat'] = 'yy/mm/dd';
			}
		} else {
			if ( array_key_exists( 'date format', $this->mOtherArgs ) ) {
				if ( $this->mOtherArgs['date format'] == 'SHORT' ) {
					$jsattribs['dateFormat'] = wfMessage( 'pf-datepicker-dateformatshort' )->text();
				} elseif ( $this->mOtherArgs['date format'] == 'LONG' ) {
					$jsattribs['dateFormat'] = wfMessage( 'pf-datepicker-dateformatlong' )->text();
				} else {
					$jsattribs['dateFormat'] = $this->mOtherArgs['date format'];
				}
			} elseif ( $wgPageFormsDatePickerSettings["DateFormat"] ) {
				if ( $wgPageFormsDatePickerSettings["DateFormat"] == 'SHORT' ) {
					$jsattribs['dateFormat'] = wfmessage( 'pf-datepicker-dateformatshort' )->text();
				} elseif ( $wgPageFormsDatePickerSettings["DateFormat"] == 'LONG' ) {
					$jsattribs['dateFormat'] = wfMessage( 'pf-datepicker-dateformatlong' )->text();
				} else {
					$jsattribs['dateFormat'] = $wgPageFormsDatePickerSettings["DateFormat"];
				}
			} else {
				$jsattribs['dateFormat'] = 'yy/mm/dd';
			}
		}

		// setup attributes required only for either disabled or enabled datepickers
		if ( $this->mIsDisabled ) {
			$jsattribs['buttonImage'] = $wgExtensionAssetsPath . '/PageForms/images/DatePickerButtonDisabled.gif';
		} else {
			$jsattribs['buttonImage'] = $wgExtensionAssetsPath . '/PageForms/images/DatePickerButton.gif';

			// find min date, max date and disabled dates

			// set first date
			if ( array_key_exists( 'first date', $this->mOtherArgs ) ) {
				$minDate = date_create( $this->mOtherArgs['first date'] );
			} elseif ( $wgPageFormsDatePickerSettings["FirstDate"] ) {
				$minDate = date_create( $wgPageFormsDatePickerSettings["FirstDate"] );
			} else {
				$minDate = null;
			}

			// set last date
			if ( array_key_exists( 'last date', $this->mOtherArgs ) ) {
				$maxDate = date_create( $this->mOtherArgs['last date'] );
			} elseif ( $wgPageFormsDatePickerSettings["LastDate"] ) {
				$maxDate = date_create( $wgPageFormsDatePickerSettings["LastDate"] );
			} else {
				$maxDate = null;
			}

			// find allowed values and invert them to get disabled values
			if ( array_key_exists( 'possible_values', $this->mOtherArgs ) && count( $this->mOtherArgs['possible_values'] ) ) {
				$enabledDates = self::sortAndMergeRanges( self::createRangesArray( $this->mOtherArgs['possible_values'] ) );

				// correct min/max date to the first/last allowed value
				if ( !$minDate || $minDate < $enabledDates[0][0] ) {
					$minDate = $enabledDates[0][0];
				}

				if ( !$maxDate || $maxDate > $enabledDates[count( $enabledDates ) - 1][1] ) {
					$maxDate = $enabledDates[count( $enabledDates ) - 1][1];
				}

				$disabledDates = self::invertRangesArray( $enabledDates );
			} else {
				$disabledDates = array();
			}

			// add user-defined or default disabled values
			if ( array_key_exists( 'disable dates', $this->mOtherArgs ) ) {
				$disabledDates = self::sortAndMergeRanges(
					array_merge( $disabledDates, self::createRangesArray( explode( ',', $this->mOtherArgs['disable dates'] ) ) )
				);
			} elseif ( $wgPageFormsDatePickerSettings["DisabledDates"] ) {
				$disabledDates = self::sortAndMergeRanges(
					array_merge( $disabledDates, self::createRangesArray( explode( ',', $wgPageFormsDatePickerSettings["DisabledDates"] ) ) )
				);
			}

			// If a minDate is set, discard all disabled dates
			// below the min date.
			if ( $minDate ) {
				// Discard all ranges of disabled dates that
				// are entirely below the min date.
				while ( $minDate && count( $disabledDates ) && $disabledDates[0][1] < $minDate ) {
					array_shift( $disabledDates );
				}

				// If min date is in first disabled date range,
				// discard that range and adjust min date.
				if ( count( $disabledDates ) && $disabledDates[0][0] <= $minDate && $disabledDates[0][1] >= $minDate ) {
					$minDate = $disabledDates[0][1];
					array_shift( $disabledDates );
					$minDate->modify( "+1 day" );
				}
			}

			// if a maxDate is set, discard all disabled dates above the max date
			if ( $maxDate ) {
				// Discard all ranges of disabled dates that
				// are entirely above the max date.
				while ( count( $disabledDates ) && $disabledDates[count( $disabledDates ) - 1][0] > $maxDate ) {
					array_pop( $disabledDates );
				}

				// If max date is in last disabled date range,
				// discard that range and adjust max date.
				if ( count( $disabledDates ) && $disabledDates[count( $disabledDates ) - 1][0] <= $maxDate && $disabledDates[count( $disabledDates ) - 1][1] >= $maxDate ) {
					$maxDate = $disabledDates[count( $disabledDates ) - 1][0];
					array_pop( $disabledDates );
					$maxDate->modify( "-1 day" );
				}
			}
			// finished with disabled dates

			// find highlighted dates
			if ( array_key_exists( "highlight dates", $this->mOtherArgs ) ) {
				$highlightedDates = self::sortAndMergeRanges( self::createRangesArray( explode( ',', $this->mOtherArgs["highlight dates"] ) ) );
			} elseif ( $wgPageFormsDatePickerSettings["HighlightedDates"] ) {
				$highlightedDates = self::sortAndMergeRanges( self::createRangesArray( explode( ',', $wgPageFormsDatePickerSettings["HighlightedDates"] ) ) );
			} else {
				$highlightedDates = null;
			}

			// find disabled week days and mark them in an array
			if ( array_key_exists( "disable days of week", $this->mOtherArgs ) ) {
				$disabledDaysString = $this->mOtherArgs['disable days of week'];
			} else {
				$disabledDaysString = $wgPageFormsDatePickerSettings["DisabledDaysOfWeek"];
			}

			if ( $disabledDaysString != null ) {
				$disabledDays = array( false, false, false, false, false, false, false );

				foreach ( explode( ',', $disabledDaysString ) as $day ) {
					if ( is_numeric( $day ) && $day >= 0 && $day <= 6 ) {
						$disabledDays[$day] = true;
					}
				}
			} else {
				$disabledDays = null;
			}

			// find highlighted week days and mark them in an array
			if ( array_key_exists( "highlight days of week", $this->mOtherArgs ) ) {
				$highlightedDaysString = $this->mOtherArgs['highlight days of week'];
			} else {
				$highlightedDaysString = $wgPageFormsDatePickerSettings["HighlightedDaysOfWeek"];
			}

			if ( $highlightedDaysString != null ) {
				$highlightedDays = array( false, false, false, false, false, false, false );

				foreach ( explode( ',', $highlightedDaysString ) as $day ) {
					if ( is_numeric( $day ) && $day >= 0 && $day <= 6 ) {
						$highlightedDays[$day] = true;
					}
				}
			} else {
				$highlightedDays = null;
			}

			// set first day of the week
			if ( array_key_exists( 'week start', $this->mOtherArgs ) ) {
				$jsattribs['firstDay'] = $this->mOtherArgs['week start'];
			} elseif ( $wgPageFormsDatePickerSettings["WeekStart"] != null ) {
				$jsattribs['firstDay'] = $wgPageFormsDatePickerSettings["WeekStart"];
			} else {
				$jsattribs['firstDay'] = wfMessage( 'pf-datepicker-firstdayofweek' )->text();
			}

			// store min date as JS attrib
			if ( $minDate ) {
				$jsattribs['minDate'] = $minDate->format( 'Y/m/d' );
			}

			// store max date as JS attrib
			if ( $maxDate ) {
				$jsattribs['maxDate'] = $maxDate->format( 'Y/m/d' );
			}

			// register disabled dates with datepicker
			if ( count( $disabledDates ) > 0 ) {
				// Convert the PHP array of date ranges into an
				// array of numbers.
				$jsattribs["disabledDates"] = array_map(
					function ( $range ) {
						$y0 = $range[0]->format( "Y" );
						$m0 = $range[0]->format( "m" ) - 1;
						$d0 = $range[0]->format( "d" );

						$y1 = $range[1]->format( "Y" );
						$m1 = $range[1]->format( "m" ) - 1;
						$d1 = $range[1]->format( "d" );

						return array( $y0, $m0, $d0, $y1, $m1, $d1 );
					},
					$disabledDates
				);
			}

			// register highlighted dates with datepicker
			if ( count( $highlightedDates ) > 0 ) {
				// Convert the PHP array of date ranges into an					// array of numbers.
				$jsattribs["highlightedDates"] = array_map(
					function ( $range ) {
						$y0 = $range[0]->format( "Y" );
						$m0 = $range[0]->format( "m" ) - 1;
						$d0 = $range[0]->format( "d" );

						$y1 = $range[1]->format( "Y" );
						$m1 = $range[1]->format( "m" ) - 1;
						$d1 = $range[1]->format( "d" );

						return array( $y0, $m0, $d0, $y1, $m1, $d1 );
					},
					$highlightedDates
				);
			}

			// register disabled days of week with datepicker
			if ( count( $disabledDays ) > 0 ) {
				$jsattribs["disabledDays"] = $disabledDays;
			}

			// register highlighted days of week with datepicker
			if ( count( $highlightedDays ) > 0 ) {
				$jsattribs["highlightedDays"] = $highlightedDays;
			}
		}

		return $jsattribs;
	}

	/**
	 * Sort and merge time ranges in an array.
	 *
	 * Expects an array of arrays -
	 * the inner arrays must contain two dates representing the start and
	 * end date of a time range.
	 *
	 * Returns an array of arrays with the date ranges sorted and
	 * overlapping ranges merged.
	 *
	 * @param array[] $ranges array of arrays of DateTimes
	 * @return array[] array of arrays of DateTimes
	*/
	private static function sortAndMergeRanges( $ranges ) {
		// sort ranges, earliest date first
		sort( $ranges );

		// stores the start of the current date range
		$currmin = false;

		// stores the date the next ranges start date has to top to not overlap
		$nextmin = false;

		// result array
		$mergedRanges = array();

		foreach ( $ranges as $range ) {
			// ignore empty date ranges
			if ( !$range ) {
				continue;
			}

			if ( !$currmin ) { // found first valid range
				$currmin = $range[0];
				$nextmin = $range[1];
				$nextmin->modify( '+1 day' );
			} elseif ( $range[0] <= $nextmin ) { // overlap detected
				$currmin = min( $currmin, $range[0] );

				$range[1]->modify( '+1 day' );
				$nextmin = max( $nextmin, $range[1] );
			} else {
				// No overlap - store current range and
				// continue with next.
				$nextmin->modify( '-1 day' );
				$mergedRanges[] = array( $currmin, $nextmin );

				$currmin = $range[0];
				$nextmin = $range[1];
				$nextmin->modify( '+1 day' );
			}
		}

		// store last range
		if ( $currmin ) {
			$nextmin->modify( '-1 day' );
			$mergedRanges[] = array( $currmin, $nextmin );
		}

		return $mergedRanges;
	}

	/**
	 * Creates an array of arrays of dates from an array of strings
	 *
	 * Expects an array of strings containing dates or date ranges in the
	 * format "yyyy/mm/dd" or "yyyy/mm/dd-yyyy/mm/dd"
	 *
	 * Returns an array of arrays, each of the latter consisting of two
	 * dates representing the start and end date of the range
	 *
	 * The result array will contain null values for unparseable date
	 * strings.
	 *
	 * @param string[] $rangesAsStrings dates and date ranges
	 * @return array[] array of arrays of DateTimes
	 */
	private static function createRangesArray( $rangesAsStrings ) {
		return array_map(
			function ( $range ) {
				if ( strpos( $range, "-" ) === false ) { // single date
					$date = date_create( $range );
					return ( $date ) ? array( $date, clone $date ) : null;
				} else { // date range
					$dates = array_map( "date_create", explode( "-", $range ) );
					return ( $dates[0] && $dates[1] ) ? $dates : null;
				}
			},
			$rangesAsStrings
		);
	}

	/**
	 * Takes an array of date ranges and returns an array containing the gaps
	 *
	 * The very first and the very last date of the original string are
	 * lost in the process, of course, as they do not delimit a gap. This
	 * means, after repeated inversions the result will eventually be empty.
	 *
	 * @param DateTime[] $ranges
	 * @return array[] array of arrays of DateTimes
	 */
	private static function invertRangesArray( $ranges ) {
		// the result (initially empty)
		$invRanges = null;

		// the minimum of the current gap (initially none)
		$min = null;

		foreach ( $ranges as $range ) {
			if ( $min ) {
				// if min date of current gap is known store gap
				$min->modify( "+1day " );
				$range[0]->modify( "-1day " );
				$invRanges[] = array( $min, $range[0] );
			}

			$min = $range[1]; // store min date of next gap
		}

		return $invRanges;
	}

	/**
	 * Returns the set of parameters for this form input.
	 *
	 * TODO: Add missing parameters
	 * @return array[]
	 */
	public static function getParameters() {
		$params = parent::getParameters();
		$params['date format'] = array(
			'name' => 'date format',
			'type' => 'string',
			'description' => wfMessage( 'pf-datepicker-dateformat' )->text()
		);
		$params['week start'] = array(
			'name' => 'week start',
			'type' => 'int',
			'description' => wfMessage( 'pf-datepicker-weekstart' )->text()
		);
		$params['first date'] = array(
			'name' => 'first date',
			'type' => 'string',
			'description' => wfMessage( 'pf-datepicker-firstdate' )->text()
		);
		$params['last date'] = array(
			'name' => 'last date',
			'type' => 'string',
			'description' => wfMessage( 'pf-datepicker-lastdate' )->text()
		);
		$params['disable days of week'] = array(
			'name' => 'disable days of week',
			'type' => 'string',
			'description' => wfMessage( 'pf-datepicker-disabledaysofweek' )->text()
		);
		$params['highlight days of week'] = array(
			'name' => 'highlight days of week',
			'type' => 'string',
			'description' => wfMessage( 'pf-datepicker-highlightdaysofweek' )->text()
		);
		$params['disable dates'] = array(
			'name' => 'disable dates',
			'type' => 'string',
			'description' => wfMessage( 'pf-datepicker-disabledates' )->text()
		);
		$params['highlight days of week'] = array(
			'name' => 'highlight days of week',
			'type' => 'string',
			'description' => wfMessage( 'pf-datepicker-highlightdates' )->text()
		);

		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 *
	 * Ideally this HTML code should provide a basic functionality even if
	 * the browser is not JavaScript capable, i.e. even without JavaScript
	 * the user should be able to input values.
	 * @return string
	 */
	public function getHtmlText() {
		// Assemble HTML code.
		$html = self::genericTextHTML( $this->mCurrentValue, $this->mInputName, $this->mIsDisabled, $this->mOtherArgs, 'input_' . $this->mInputNumber );

		if ( ! array_key_exists( 'part of dtp', $this->mOtherArgs ) ) {
			// wrap in span (e.g. used for mandatory inputs)
			$class = array_key_exists( 'mandatory', $this->mOtherArgs ) ? 'inputSpan mandatoryFieldSpan' : 'inputSpan';
			$html = Xml::tags( 'span', array( 'class' => $class ), $html );
		}

		return $html;
	}

	/**
	 * Returns the set of SMW property types which this input can
	 * handle, but for which it isn't the default input.
	 *
	 * @return string[]
	 */
	public static function getOtherPropTypesHandled() {
		return array( '_str', '_dat' );
	}

	/**
	 * Creates the HTML text for an input.
	 *
	 * Common attributes for input types are set according to the parameters.
	 * The parameters are the standard parameters set by Page Forms'
	 * InputTypeHook plus some optional.
	 *
	 * @param string $currentValue
	 * @param string $inputName
	 * @param bool $isDisabled
	 * @param array $otherArgs
	 * @param string $inputId (optional)
	 * @param int $tabIndex (optional)
	 * @param string $class
	 * @return string the html text of an input element
	 */
	static function genericTextHTML( $currentValue, $inputName, $isDisabled, $otherArgs, $inputId = null, $tabIndex = null, $class = '' ) {
		global $wgPageFormsTabIndex;

		// array of attributes to pass to the input field
		$attribs = array(
			'name'  => $inputName,
			'class' => $class,
			'value' => $currentValue,
			'type'  => 'text'
		);

		// set size attrib
		if ( array_key_exists( 'size', $otherArgs ) ) {
			$attribs['size'] = $otherArgs['size'];
		}

		// set maxlength attrib
		if ( array_key_exists( 'maxlength', $otherArgs ) ) {
			$attribs['maxlength'] = $otherArgs['maxlength'];
		}

		// modify class attribute for mandatory form fields
		if ( array_key_exists( 'mandatory', $otherArgs ) ) {
			$attribs['class'] .= ' mandatoryField';
		}

		// add user class(es) to class attribute of input field
		if ( array_key_exists( 'class', $otherArgs ) ) {
			$attribs['class'] .= ' ' . $otherArgs['class'];
		}

		// set readonly attrib
		if ( $isDisabled ) {
			$attribs['disabled'] = true;
		}

		// if no special input id is specified set the Page Forms standard
		if ( $inputId !== null ) {
			$attribs['id'] = $inputId;
		}

		if ( $tabIndex == null ) {
			$attribs['tabindex'] = $wgPageFormsTabIndex;
		} else {
			$attribs['tabindex'] = $tabIndex;
		}

		$html = Xml::element( 'input', $attribs );

		return $html;
	}
}
