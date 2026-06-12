<?php
/**
 * @file
 * @ingroup PF
 */

use MediaWiki\Html\Html;

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
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;

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
		// $cur_values are the raw internal values (e.g. page names); the
		// <option> value is the internal value and only the (possibly mapped)
		// label is shown to the user. (T427758)
		$cur_values = PFValuesUtils::getValuesArray( $this->mCurrentValue, $delimiter );

		$value_label_map = PFValuesUtils::getValueLabelMap(
			$this->mOtherArgs['possible_values'] ?? [],
			( array_key_exists( 'value_labels', $this->mOtherArgs ) && is_array( $this->mOtherArgs['value_labels'] ) )
				? $this->mOtherArgs['value_labels'] : null
		);
		$optionsText = '';
		foreach ( $value_label_map as $value => $optionLabel ) {
			$optionAttrs = [ 'value' => $value ];
			if ( in_array( $value, $cur_values ) ) {
				$optionAttrs['selected'] = 'selected';
			}
			$optionsText .= Html::element( 'option', $optionAttrs, $optionLabel );
		}

		if ( array_key_exists( 'show on select', $this->mOtherArgs ) ) {
			$className .= ' pfShowIfSelected';
			PFFormUtils::setShowOnSelect( $this->mOtherArgs['show on select'], $input_id );
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

		return $text;
	}
}
