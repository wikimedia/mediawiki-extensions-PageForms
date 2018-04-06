<?php

/**
 * @author Stephan Gambke
 * @file
 * @ingroup PageForms
 */

/**
 * @ingroup PageForms
 */
class PFDateTimePicker extends PFFormInput {

	protected $mDatePicker;
	protected $mTimePicker;

	/**
	 * @param string $input_number The number of the input in the form.
	 * @param string $cur_value The current value of the input field.
	 * @param string $input_name The name of the input.
	 * @param bool $disabled Is this input disabled?
	 * @param array $other_args An associative array of other parameters that were present in the
	 *  input definition.
	 */
	public function __construct( $input_number, $cur_value, $input_name, $disabled, $other_args ) {
		parent::__construct( $input_number, $cur_value, $input_name, $disabled, $other_args );

		// prepare sub-inputs

		$this->mOtherArgs["part of dtp"] = true;

		// find allowed values and keep only the date portion
		if ( array_key_exists( 'possible_values', $this->mOtherArgs ) &&
			count( $this->mOtherArgs[ 'possible_values' ] )
		) {
			$this->mOtherArgs[ 'possible_values' ] = preg_replace(
				'/^\s*(\d{4}\/\d{2}\/\d{2}).*/',
				'$1',
				$this->mOtherArgs[ 'possible_values' ]
			);
		}

		$dateTimeString = trim( $this->mCurrentValue );
		$dateString = '';
		$timeString = '';

		$separatorPos = strpos( $dateTimeString, " " );

		if ( $dateTimeString == 'now' ) {
			$dateString = $timeString = 'now';

		// does it have a separating whitespace? assume it's a date & time
		} elseif ( $separatorPos ) {
			$dateString = substr( $dateTimeString, 0, $separatorPos );
			$timeString = substr( $dateTimeString, $separatorPos + 1 );

		// does it start with a time of some kind?
		} elseif ( preg_match( '/^\d?\d:\d\d/', $dateTimeString ) ) {
			$timeString = $dateTimeString;

		// if all else fails assume it's a date
		} else {
			$dateString = $dateTimeString;
		}

		$this->mDatePicker = new PFDatePickerInput( $this->mInputNumber . '_dp', $dateString, $this->mInputName, $this->mIsDisabled, $this->mOtherArgs );
		$this->mTimePicker = new PFTimePickerInput( $this->mInputNumber . '_tp', $timeString, $this->mInputName, $this->mIsDisabled, $this->mOtherArgs );

		// add JS data
		$this->addJsInitFunctionData( 'PF_DTP_init', $this->setupJsInitAttribs() );
	}

	/**
	 * Returns the name of the input type this class handles: menuselect.
	 *
	 * This is the name to be used in the field definition for the "input
	 * type" parameter.
	 *
	 * @return String The name of the input type this class handles.
	 */
	public static function getName() {
		return 'datetimepicker';
	}

	protected function setupJsInitAttribs() {
		$jsattribs = array();

		$jsattribs['disabled'] = $this->mIsDisabled;

		if ( array_key_exists( 'class', $this->mOtherArgs ) ) {
			$jsattribs['userClasses'] = $this->mOtherArgs['class'];
		} else {
			$jsattribs['userClasses'] = '';
		}

		$jsattribs['subinputs'] =
			$this->mDatePicker->getHtmlText() . " " .
			$this->mTimePicker->getHtmlText();

		$jsattribs['subinputsInitData'] = array(
			'input_' . $this->mInputNumber . '_dp' => $this->mDatePicker->getJsInitFunctionData(),
			'input_' . $this->mInputNumber . '_tp' => $this->mTimePicker->getJsInitFunctionData()
		);

		// build JS code from attributes array
		return $jsattribs;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 *
	 * Ideally this HTML code should provide a basic functionality even if the
	 * browser is not JavaScript capable. I.e. even without JavaScript the user
	 * should be able to input values.
	 * @return string
	 */
	public function getHtmlText() {
		$html = '<span class="inputSpan' . ( array_key_exists( 'mandatory', $this->mOtherArgs ) ? ' mandatoryFieldSpan' : '' ) . '">' .
			PFDatePickerInput::genericTextHTML( $this->mCurrentValue, $this->mInputName, $this->mIsDisabled, $this->mOtherArgs, 'input_' . $this->mInputNumber ) .
			'</span>';

		return $html;
	}

	/**
	 * Returns the set of SMW property types which this input can
	 * handle, but for which it isn't the default input.
	 * @return string[]
	 */
	public static function getOtherPropTypesHandled() {
		return array( '_str', '_dat' );
	}

	/**
	 * Returns the set of parameters for this form input.
	 * @return array[]
	 */
	public static function getParameters() {
		$params = array_merge(
			parent::getParameters(),
			PFDatePickerInput::getParameters()
		);

		// Copied from PFTimePickerInput, which was not moved
		// over to Page Forms.
		$params['mintime'] = array(
			'name' => 'mintime',
			'type' => 'string',
			'description' => wfMessage( 'pageforms-timepicker-mintime' )->text(),
		);
		$params['maxtime'] = array(
			'name' => 'maxtime',
			'type' => 'string',
			'description' => wfMessage( 'pageforms-timepicker-maxtime' )->text(),
		);
		$params['interval'] = array(
			'name' => 'interval',
			'type' => 'int',
			'description' => wfMessage( 'pageforms-timepicker-interval' )->text(),
		);

		return $params;
	}

	/**
	 * Returns the name and parameters for the validation JavaScript
	 * functions for this input type, if any.
	 * @return array
	 */
	public function getJsValidationFunctionData() {
		return array_merge(
			$this->mJsValidationFunctionData,
			$this->mDatePicker->getJsValidationFunctionData()
		);
	}

	/**
	 * Returns the names of the resource modules this input type uses.
	 *
	 * Returns the names of the modules as an array or - if there is only one
	 * module - as a string.
	 *
	 * @return null|string|array
	 */
	public function getResourceModuleNames() {
		return array( 'ext.pageforms.timepicker', 'ext.pageforms.datetimepicker' );
	}

}
