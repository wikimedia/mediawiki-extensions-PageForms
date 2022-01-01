<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFListBoxInput extends PFMultiEnumInput {

	public static function getName(): string {
		return 'listbox';
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = [
			'name' => 'size',
			'type' => 'int',
			'description' => wfMessage( 'Pf_forminputs_listboxsize' )->text()
		];
		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText(): string {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsShowOnSelect;

		$className = ( $this->mIsMandatory ) ? 'mandatoryField' : 'createboxInput';
		if ( array_key_exists( 'class', $this->mOtherArgs ) ) {
			$className .= ' ' . $this->mOtherArgs['class'];
		}
		$input_id = "input_$wgPageFormsFieldNum";
		// get list delimiter - default is comma
		if ( array_key_exists( 'delimiter', $this->mOtherArgs ) ) {
			$delimiter = $this->mOtherArgs['delimiter'];
		} else {
			$delimiter = ',';
		}
		$cur_values = PFValuesUtils::getValuesArray( $this->mCurrentValue, $delimiter );
		$className .= ' pfShowIfSelected';

		$possible_values = $this->mOtherArgs['possible_values'];
		if ( $possible_values == null ) {
			$possible_values = [];
		}
		$optionsText = '';
		foreach ( $possible_values as $possible_value ) {
			if (
				array_key_exists( 'value_labels', $this->mOtherArgs ) &&
				is_array( $this->mOtherArgs['value_labels'] ) &&
				array_key_exists( $possible_value, $this->mOtherArgs['value_labels'] )
			) {
				$optionLabel = $this->mOtherArgs['value_labels'][$possible_value];
			} else {
				$optionLabel = $possible_value;
			}
			$optionAttrs = [ 'value' => $possible_value ];
			if ( in_array( $possible_value, $cur_values ) ) {
				$optionAttrs['selected'] = 'selected';
			}
			$optionsText .= Html::element( 'option', $optionAttrs, $optionLabel );
		}
		$selectAttrs = [
			'id' => $input_id,
			'tabindex' => $wgPageFormsTabIndex,
			'name' => $this->mInputName . '[]',
			'class' => $className,
			'multiple' => 'multiple'
		];
		if ( array_key_exists( 'size', $this->mOtherArgs ) ) {
			$selectAttrs['size'] = $this->mOtherArgs['size'];
		}
		if ( $this->mIsDisabled ) {
			$selectAttrs['disabled'] = 'disabled';
		}
		$text = Html::rawElement( 'select', $selectAttrs, $optionsText );
		$text .= Html::hidden( $this->mInputName . '[is_list]', 1 );
		if ( $this->mIsMandatory ) {
			$text = Html::rawElement( 'span', [ 'class' => 'inputSpan mandatoryFieldSpan' ], $text );
		}

		if ( array_key_exists( 'show on select', $this->mOtherArgs ) ) {
			foreach ( $this->mOtherArgs['show on select'] as $div_id => $options ) {
				if ( array_key_exists( $input_id, $wgPageFormsShowOnSelect ) ) {
					$wgPageFormsShowOnSelect[$input_id][] = [ $options, $div_id ];
				} else {
					$wgPageFormsShowOnSelect[$input_id] = [ [ $options, $div_id ] ];
				}
			}
		}

		return $text;
	}
}
