<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFTextWithAutocompleteInput extends PFTextInput {
	public static function getName() {
		return 'text with autocomplete';
	}

	public static function getDefaultPropTypes() {
		return [
			'_wpg' => []
		];
	}

	public static function getOtherPropTypesHandled() {
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			return [ '_str' ];
		} else {
			return [ '_txt' ];
		}
	}

	public static function getDefaultPropTypeLists() {
		return [];
	}

	public static function getOtherPropTypeListsHandled() {
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			return [ '_str' ];
		} else {
			return [ '_txt' ];
		}
	}

	public static function getDefaultCargoTypes() {
		return [];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Page', 'String' ];
	}

	public static function getDefaultCargoTypeLists() {
		return [];
	}

	public static function getOtherCargoTypeListsHandled() {
		return [ 'String' ];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;

		$is_list = ( array_key_exists( 'is_list', $other_args ) && $other_args['is_list'] == true );
		list( $autocompleteSettings, $remoteDataType, $delimiter ) = PFValuesUtils::setAutocompleteValues( $other_args, $is_list );

		$className = ( $is_mandatory ) ? 'autocompleteInput mandatoryField' : 'autocompleteInput createboxInput';
		if ( array_key_exists( 'unique', $other_args ) ) {
			$className .= ' uniqueField';
		}
		if ( array_key_exists( 'class', $other_args ) ) {
			$className .= ' ' . $other_args['class'];
		}
		$input_id = 'input_' . $wgPageFormsFieldNum;

		if ( array_key_exists( 'size', $other_args ) ) {
			$size = $other_args['size'];
		} elseif ( $is_list ) {
			$size = '100';
		} else {
			$size = '35';
		}

		$inputAttrs = [
			'id' => $input_id,
			'size' => $size,
			'class' => $className,
			'tabindex' => $wgPageFormsTabIndex,
			'autocompletesettings' => $autocompleteSettings,
		];
		if ( array_key_exists( 'origName', $other_args ) ) {
			$inputAttrs['origName'] = $other_args['origName'];
		}
		if ( !is_null( $remoteDataType ) ) {
			$inputAttrs['autocompletedatatype'] = $remoteDataType;
		}
		if ( $is_disabled ) {
			$inputAttrs['disabled'] = true;
		}
		if ( array_key_exists( 'maxlength', $other_args ) ) {
			$inputAttrs['maxlength'] = $other_args['maxlength'];
		}
		if ( array_key_exists( 'placeholder', $other_args ) ) {
			$inputAttrs['placeholder'] = $other_args['placeholder'];
		}

		// The input value passed in to Html::input() cannot be an array.
		if ( is_array( $cur_value ) ) {
			$curValueStr = implode( $delimiter . ' ', $cur_value );
		} else {
			$curValueStr = $cur_value;
		}
		$text = "\n\t" . Html::input( $input_name, $curValueStr, 'text', $inputAttrs ) . "\n";

		if ( array_key_exists( 'uploadable', $other_args ) && $other_args['uploadable'] == true ) {
			if ( array_key_exists( 'default filename', $other_args ) ) {
				$default_filename = $other_args['default filename'];
			} else {
				$default_filename = '';
			}
			$text .= self::uploadableHTML( $input_id, $delimiter, $default_filename, $cur_value, $other_args );
		}

		$spanClass = 'inputSpan';
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}
		if ( array_key_exists( 'unique', $other_args ) ) {
			$spanClass .= ' uniqueFieldSpan';
		}
		$text = "\n" . Html::rawElement( 'span', [ 'class' => $spanClass ], $text );

		return $text;
	}

	public static function getAutocompletionParameters() {
		$params = PFEnumInput::getValuesParameters();
		$params[] = [
			'name' => 'values from url',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_valuesfromurl' )->text()
		];
		$params[] = [
			'name' => 'list',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_list' )->text()
		];
		$params[] = [
			'name' => 'delimiter',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_delimiter' )->text()
		];
		return $params;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params = array_merge( $params, self::getAutocompletionParameters() );
		return $params;
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
