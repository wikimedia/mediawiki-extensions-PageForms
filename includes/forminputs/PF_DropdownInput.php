<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFDropdownInput extends PFEnumInput {
	public static function getName() {
		return 'dropdown';
	}

	public static function getDefaultPropTypes() {
		return [
			'enumeration' => []
		];
	}

	public static function getOtherPropTypesHandled() {
		return [ '_boo' ];
	}

	public static function getDefaultCargoTypes() {
		return [
			'Enumeration' => []
		];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Boolean' ];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsShowOnSelect;

		// Standardize $cur_value
		if ( $cur_value === null ) {
			$cur_value = '';
		}

		$className = ( $is_mandatory ) ? 'mandatoryField' : 'createboxInput';
		if ( array_key_exists( 'class', $other_args ) ) {
			$className .= ' ' . $other_args['class'];
		}
		$input_id = "input_$wgPageFormsFieldNum";
		if ( array_key_exists( 'show on select', $other_args ) ) {
			$className .= ' pfShowIfSelected';
			foreach ( $other_args['show on select'] as $div_id => $options ) {
				if ( array_key_exists( $input_id, $wgPageFormsShowOnSelect ) ) {
					$wgPageFormsShowOnSelect[$input_id][] = [ $options, $div_id ];
				} else {
					$wgPageFormsShowOnSelect[$input_id] = [ [ $options, $div_id ] ];
				}
			}
		}
		$innerDropdown = '';
		// Add a blank value at the beginning, unless this is a
		// mandatory field and there's a current value in place
		// (either through a default value or because we're editing
		// an existing page).
		if ( !$is_mandatory || $cur_value === '' ) {
			$innerDropdown .= "	<option value=\"\"></option>\n";
		}
		if ( ( $possible_values = $other_args['possible_values'] ) == null ) {
			// If it's a Boolean property, display 'Yes' and 'No'
			// as the values.
			if ( array_key_exists( 'property_type', $other_args ) && $other_args['property_type'] == '_boo' ) {
				$possible_values = [
					PFUtils::getWordForYesOrNo( true ),
					PFUtils::getWordForYesOrNo( false ),
				];
			} else {
				$possible_values = [];
			}
		}
		foreach ( $possible_values as $possible_value ) {
			$optionAttrs = [ 'value' => $possible_value ];
			if ( $possible_value == $cur_value ) {
				$optionAttrs['selected'] = "selected";
			}
			if (
				array_key_exists( 'value_labels', $other_args ) &&
				is_array( $other_args['value_labels'] ) &&
				array_key_exists( $possible_value, $other_args['value_labels'] )
			) {
				$label = $other_args['value_labels'][$possible_value];
			} else {
				$label = $possible_value;
			}
			$innerDropdown .= Html::element( 'option', $optionAttrs, $label );
		}
		$selectAttrs = [
			'id' => $input_id,
			'tabindex' => $wgPageFormsTabIndex,
			'name' => $input_name,
			'class' => $className
		];
		if ( $is_disabled ) {
			$selectAttrs['disabled'] = 'disabled';
		}
		if ( array_key_exists( 'origName', $other_args ) ) {
			$selectAttrs['origname'] = $other_args['origName'];
		}
		$text = Html::rawElement( 'select', $selectAttrs, $innerDropdown );
		$spanClass = 'inputSpan';
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
