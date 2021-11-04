<?php

/**
 * @author Stephan Gambke
 * @author Sam Wilson
 * @author Amr El-Absy
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PF
 */

use MediaWiki\Widget\DateInputWidget;

class PFDatePickerInput extends PFFormInput {

	public static function getName(): string {
		return 'datepicker';
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
		if ( $cur_value != '' ) {
			list( $year, $month, $day ) = PFDateInput::parseDate( $cur_value );
			$cur_value = sprintf( '%04d-%02d-%02d', $year, $month, $day );
		}

		parent::__construct( $input_number, $cur_value, $input_name, $disabled, $other_args );
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
		return [ 'ext.pageforms.datepicker' ];
	}

	/**
	 * Returns the set of parameters for this form input.
	 *
	 * TODO: Add missing parameters
	 * @return array[]
	 */
	public static function getParameters() {
		$params = parent::getParameters();
		$params['date format'] = [
			'name' => 'date format',
			'type' => 'string',
			'description' => wfMessage(
				'pf-datepicker-dateformat',
				'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:Page_Forms/Input_types/Datepicker#Parameters'
			)->text()
		];
		$params['first date'] = [
			'name' => 'first date',
			'type' => 'string',
			'description' => wfMessage( 'pf-datepicker-firstdate' )->text()
		];
		$params['last date'] = [
			'name' => 'last date',
			'type' => 'string',
			'description' => wfMessage( 'pf-datepicker-lastdate' )->text()
		];

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
	public function getHtmlText(): string {
		$options = array_merge( $this->getOptions(), [
			'type' => 'date',
			'name' => $this->mInputName,
			'value' => $this->mCurrentValue,
			'id' => 'input_' . $this->mInputNumber,
			'classes' => [ 'pfDatePicker', 'pfPicker' ],
			'infusable' => true
		] );
		$widget = new DateInputWidget( $options );
		$widget->setDisabled( $this->mIsDisabled );
		$text = $widget->toString();

		// We need a wrapper div so that OOUI won't override
		// any classes added by "show on select".
		$wrapperClass = 'pfPickerWrapper';
		if ( isset( $this->mOtherArgs[ 'mandatory' ] ) ) {
			$wrapperClass .= ' mandatory';
		}

		return Html::rawElement( 'div', [ 'class' => $wrapperClass ], $text );
	}

	private function getOptions() {
		$params = $this->mOtherArgs;
		$options = [];
		if ( isset( $params[ 'date format' ] ) ) {
			$options[ 'inputFormat' ] = $this->getConvertedFormat();
			$options[ 'displayFormat' ] = $this->getConvertedFormat();
		}
		if ( isset( $params[ 'first date' ] ) ) {
			$options[ 'mustBeAfter' ] = str_replace( "/", "-", $params[ 'first date' ] );
		}
		if ( isset( $params[ 'last date' ] ) ) {
			$options[ 'mustBeBefore' ] = str_replace( "/", "-", $params[ 'last date' ] );
		}
		if ( isset( $params[ 'hidden' ] ) ) {
			$options[ 'invisibleLabel' ] = true;
		}
		if ( isset( $params[ 'maxlength' ] ) ) {
			$options[ 'maxLength' ] = $params[ 'maxlength' ];
		}
		// It would be nice to set this, since it leads to a useful
		// display (an asterisk), but unfortunately it also causes a
		// JS error that prevents saving.
		//if ( isset( $params[ 'mandatory' ] ) ) {
		//	$options[ 'required' ] = true;
		//}

		return $options;
	}

	private function getConvertedFormat() {
		$oldFormat = $this->mOtherArgs['date format'];
		$j = 0;
		$newFormat = [];
		for ( $i = 0; $i < strlen( $oldFormat ); $i++ ) {
			if ( $oldFormat[$i] === "d" && isset( $oldFormat[$i + 1] ) && $oldFormat[$i + 1] !== "d" ) {
				// If the letter is "d" and next letter is not "d"
				$newFormat[$j] = "D";
				$j++;
			} elseif ( $oldFormat[$i] === "d" && isset( $oldFormat[$i + 1] ) && $oldFormat[$i + 1] === "d" ) {
				// If the letter is "d" and next letter is "d", which means "dd"
				$newFormat[$j] = "DD";
				$j += 2;
				$i++;
			} elseif ( $oldFormat[$i] === "d" && !isset( $oldFormat[$i + 1] ) ) {
				// If the letter is "d" and it is the last letter.
				$newFormat[$j] = "D";
			} elseif ( $oldFormat[$i] === "D" && isset( $oldFormat[$i + 1] ) && $oldFormat[$i + 1] !== "D" ) {
				// If the letter is "D" and next letter is not "D"
				$newFormat[$j] = "dd";
				$j += 2;
			} elseif ( $oldFormat[$i] === "D" && isset( $oldFormat[$i + 1] ) && $oldFormat[$i + 1] === "D" ) {
				// If the letter is "D" and next letter is "D", which means "DD".
				// Until now, we don't know a corresponding format, so, let's let at as it is.
				$newFormat[$j] = "DD";
				$j += 2;
				$i++;
			} elseif ( $oldFormat[$i] === "D" && !isset( $oldFormat[$i + 1] ) ) {
				// If the letter is "D" and it is the last letter.
				$newFormat[$j] = "dd";
			} elseif ( $oldFormat[$i] === "m" && isset( $oldFormat[$i + 1] ) && $oldFormat[$i + 1] !== "m" ) {
				// If the letter is "m" and next letter is not "m"
				$newFormat[$j] = "M";
				$j++;
			} elseif ( $oldFormat[$i] === "m" && isset( $oldFormat[$i + 1] ) && $oldFormat[$i + 1] === "m" ) {
				// If the letter is "m" and next letter is "m", which means "mm".
				$newFormat[$j] = "MM";
				$j += 2;
				$i++;
			} elseif ( $oldFormat[$i] === "m" && !isset( $oldFormat[$i + 1] ) ) {
				// If the letter is "m" and it is the last letter.
				$newFormat[$j] = "M";
			} elseif ( $oldFormat[$i] === "y" && isset( $oldFormat[$i + 1] ) && $oldFormat[$i + 1] !== "y" ) {
				// If the letter is "y" and next letter is not "y"
				$newFormat[$j] = "YY";
				$j += 2;
			} elseif ( $oldFormat[$i] === "y" && isset( $oldFormat[$i + 1] ) && $oldFormat[$i + 1] === "y" ) {
				// If the letter is "y" and next letter is "y", which means "yy".
				$newFormat[$j] = "YYYY";
				$j += 4;
				$i++;
			} elseif ( $oldFormat[$i] === "y" && !isset( $oldFormat[$i + 1] ) ) {
				// If the letter is "y" and it is the last letter.
				$newFormat[$j] = "YY";
			} else {
				// Any another letters, or special characters.
				$newFormat[$j] = $oldFormat[$i];
				$j++;
			}
		}
		$newFormat = implode( $newFormat );
		return $newFormat;
	}

	/**
	 * Returns the set of SMW property types which this input can
	 * handle, but for which it isn't the default input.
	 *
	 * @return string[]
	 */
	public static function getOtherPropTypesHandled() {
		return [ '_dat' ];
	}

	/**
	 * Returns the set of Cargo field types which this input can
	 * handle, but for which it isn't the default input.
	 *
	 * @return string[]
	 */
	public static function getOtherCargoTypesHandled() {
		return [ 'Date' ];
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
	 * @param string|null $inputId (optional)
	 * @param int|null $tabIndex (optional)
	 * @param string $class
	 * @return string the html text of an input element
	 */
	static function genericTextHTML( $currentValue, $inputName, $isDisabled, $otherArgs, $inputId = null, $tabIndex = null, $class = '' ) {
		global $wgPageFormsTabIndex;

		// array of attributes to pass to the input field
		$attribs = [
			'name'  => $inputName,
			'class' => $class,
			'value' => $currentValue,
			'type'  => 'text'
		];

		// set size attrib
		if ( array_key_exists( 'size', $otherArgs ) ) {
			$attribs['size'] = $otherArgs['size'];
		}

		// set maxlength attrib
		if ( array_key_exists( 'maxlength', $otherArgs ) ) {
			$attribs['maxlength'] = $otherArgs['maxlength'];
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

		$html = Html::element( 'input', $attribs );

		return $html;
	}
}
