<?php

/**
 * @author Stephan Gambke
 * @file
 */

class PFTimePickerInput extends PFFormInput {

	public static function getName(): string {
		return 'timepicker';
	}

	/**
	 * @param string $input_number The number of the input in the form.
	 * @param string $cur_value The current value of the input field.
	 * @param string $input_name The name of the input.
	 * @param bool $disabled Is this input disabled?
	 * @param array $other_args An associative array of other parameters that were present in the
	 *  input definition.
	 */
	public function __construct( $input_number, $cur_value, $input_name, $disabled, array $other_args ) {
		if ( $cur_value == 'now' ) {
			// Hours and minutes.
			$cur_value = date( 'H:i' );
		}
		parent::__construct( $input_number, $cur_value, $input_name, $disabled, $other_args );

		$this->addJsInitFunctionData( 'PF_TP_init', $this->setupJsInitAttribs() );
	}

	/**
	 * Set up JS attributes
	 *
	 * @return string[]
	 */
	protected function setupJsInitAttribs() {
		global $wgExtensionAssetsPath;

		// store user class(es) for use with buttons
		$userClasses = array_key_exists( 'class', $this->mOtherArgs ) ? $this->mOtherArgs['class'] : '';

		// set min time if valid, else use default
		if ( array_key_exists( 'mintime', $this->mOtherArgs )
			&& ( preg_match( '/^\d+:\d\d$/', trim( $this->mOtherArgs['mintime'] ) ) == 1 ) ) {
			$minTime = trim( $this->mOtherArgs['mintime'] );
		} else {
			$minTime = '00:00';
		}

		// set max time if valid, else use default
		if ( array_key_exists( 'maxtime', $this->mOtherArgs )
			&& ( preg_match( '/^\d+:\d\d$/', trim( $this->mOtherArgs['maxtime'] ) ) == 1 ) ) {
			$maxTime = trim( $this->mOtherArgs['maxtime'] );
		} else {
			$maxTime = '23:59';
		}

		// set interval if valid, else use default
		if ( array_key_exists( 'interval', $this->mOtherArgs )
			&& preg_match( '/^\d+$/', trim( $this->mOtherArgs['interval'] ) ) == 1 ) {
			$interval = trim( $this->mOtherArgs['interval'] );
		} else {
			$interval = '15';
		}

		// build JS code from attributes array
		$jsattribs = [
			'minTime'   => $minTime,
			'maxTime'   => $maxTime,
			'interval'  => $interval,
			'format'    => 'hh:mm',
			'currValue' => $this->mCurrentValue,
			'disabled'  => $this->mIsDisabled,
			'userClasses' => $userClasses
		];

		if ( array_key_exists( 'part of dtp', $this->mOtherArgs ) ) {
			$jsattribs['partOfDTP'] = $this->mOtherArgs['part of dtp'];
		}

		// setup attributes required only for either disabled or enabled timepickers
		if ( $this->mIsDisabled ) {
			$jsattribs['buttonImage'] = $wgExtensionAssetsPath . '/PageForms/images/TimePickerButtonDisabled.gif';

		} else {
			$jsattribs['buttonImage'] = $wgExtensionAssetsPath . '/PageForms/images/TimePickerButton.gif';
		}

		return $jsattribs;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 *
	 * Ideally this HTML code should provide a basic functionality even if the
	 * browser is not Javascript capable. I.e. even without Javascript the user
	 * should be able to input values.
	 * @return string
	 */
	public function getHtmlText(): string {
		// create visible input field (for display) and invisible field (for data)
		$html = PFDatePickerInput::genericTextHTML( $this->mCurrentValue, $this->mInputName, $this->mIsDisabled, $this->mOtherArgs, 'input_' . $this->mInputNumber );

		// wrap in span (e.g. used for mandatory inputs)
		if ( !array_key_exists( 'part of dtp', $this->mOtherArgs ) ) {
			$html = '<span class="inputSpan' . ( array_key_exists( 'mandatory', $this->mOtherArgs ) ? ' mandatoryFieldSpan' : '' ) . '">' . $html . '</span>';
		}

		return $html;
	}

	/**
	 * Returns the set of SMW property types which this input can
	 * handle, but for which it isn't the default input.
	 * @return string[]
	 */
	public static function getOtherPropTypesHandled() {
		return [ '_str', '_dat' ];
	}

	/**
	 * Returns the set of parameters for this form input.
	 *
	 * TODO: Specify parameters specific for menuselect.
	 * @return array[]
	 */
	public static function getParameters() {
		$params = parent::getParameters();
		$params['mintime'] = [
			'name' => 'mintime',
			'type' => 'string',
			'description' => wfMessage( 'semanticformsinputs-timepicker-mintime' )->text(),
		];
		$params['maxtime'] = [
			'name' => 'maxtime',
			'type' => 'string',
			'description' => wfMessage( 'semanticformsinputs-timepicker-maxtime' )->text(),
		];
		$params['interval'] = [
			'name' => 'interval',
			'type' => 'int',
			'description' => wfMessage( 'semanticformsinputs-timepicker-interval' )->text(),
		];

		return $params;
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
		return 'ext.semanticformsinputs.timepicker';
	}
}
