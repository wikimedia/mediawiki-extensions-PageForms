<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFCheckboxesInput extends PFMultiEnumInput {

	public static function getName() {
		return 'checkboxes';
	}

	public static function getDefaultPropTypeLists() {
		return [
			'enumeration' => []
		];
	}

	public static function getOtherPropTypeListsHandled() {
		return [];
	}

	public static function getDefaultCargoTypeLists() {
		return [
			'Enumeration' => []
		];
	}

	public static function getOtherCargoTypeListsHandled() {
		return [];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsShowOnSelect;

		$checkboxClass = ( $is_mandatory ) ? 'mandatoryField' : 'createboxInput';
		$labelClass = 'checkboxLabel';
		if ( array_key_exists( 'class', $other_args ) ) {
			$labelClass .= ' ' . $other_args['class'];
		}
		$input_id = "input_$wgPageFormsFieldNum";
		// get list delimiter - default is comma
		if ( array_key_exists( 'delimiter', $other_args ) ) {
			$delimiter = $other_args['delimiter'];
		} else {
			$delimiter = ',';
		}
		$cur_values = PFValuesUtils::getValuesArray( $cur_value, $delimiter );

		if ( ( $possible_values = $other_args['possible_values'] ) == null ) {
			$possible_values = [];
		}
		$text = '';
		foreach ( $possible_values as $key => $possible_value ) {
			$cur_input_name = $input_name . '[' . $key . ']';

			if (
				array_key_exists( 'value_labels', $other_args ) &&
				is_array( $other_args['value_labels'] ) &&
				array_key_exists( $possible_value, $other_args['value_labels'] )
			) {
				$label = $other_args['value_labels'][$possible_value];
			} else {
				$label = $possible_value;
			}

			$checkbox_attrs = [
				'id' => $input_id,
				'tabindex' => $wgPageFormsTabIndex,
				'class' => $checkboxClass,
			];
			if ( in_array( $possible_value, $cur_values ) ) {
				$checkbox_attrs['checked'] = 'checked';
			}
			if ( $is_disabled ) {
				$checkbox_attrs['disabled'] = 'disabled';
			}
			$checkbox_input = Html::input( $cur_input_name, $possible_value, 'checkbox', $checkbox_attrs );

			// Put a <label> tag around each checkbox, for CSS
			// purposes as well as to clarify this element.
			$text .= "\t" . Html::rawElement( 'label',
				[ 'class' => $labelClass ],
				$checkbox_input . '&nbsp;' . $label
			) . " ";
			$wgPageFormsTabIndex++;
			$wgPageFormsFieldNum++;
		}

		$outerSpanID = "span_$wgPageFormsFieldNum";
		$outerSpanClass = 'checkboxesSpan';
		if ( $is_mandatory ) {
			$outerSpanClass .= ' mandatoryFieldSpan';
		}

		// @HACK! The current "select all/none" JS code doesn't work
		// when this input is part of a multiple-instance template, so
		// if that happens, just don't display those links.
		// Unfortunately, there's no easy way to know if we're in a
		// multiple-instance template, so look at the input name - if
		// it contains "[num][", we can assume that we are.
		// @TODO - get the JS working in multiple-instance templates -
		// this will probably require rewriting the checkboxes JS
		// to some extent, so the relevant part can be called each
		// time an instance is added.
		if ( strpos( $input_name, '[num][' ) !== false ) {
			// Multiple-instance template; do nothing.
		} elseif ( array_key_exists( 'show select all', $other_args ) ||
			( count( $possible_values ) >= $GLOBALS[ 'wgPageFormsCheckboxesSelectAllMinimum' ] && !array_key_exists( 'hide select all', $other_args ) ) ) {
			$outerSpanClass .= ' select-all';
		}

		if ( array_key_exists( 'show on select', $other_args ) ) {
			$outerSpanClass .= ' pfShowIfChecked';
			foreach ( $other_args['show on select'] as $div_id => $options ) {
				if ( array_key_exists( $outerSpanID, $wgPageFormsShowOnSelect ) ) {
					$wgPageFormsShowOnSelect[$outerSpanID][] = [ $options, $div_id ];
				} else {
					$wgPageFormsShowOnSelect[$outerSpanID] = [ [ $options, $div_id ] ];
				}
			}
		}

		$text .= Html::hidden( $input_name . '[is_list]', 1 );
		$outerSpanAttrs = [ 'id' => $outerSpanID, 'class' => $outerSpanClass ];
		$text = "\t" . Html::rawElement( 'span', $outerSpanAttrs, $text ) . "\n";

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
