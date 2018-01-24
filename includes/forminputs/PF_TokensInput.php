<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFTokensInput extends PFFormInput {
	public static function getName() {
		return 'tokens';
	}

	public static function getDefaultPropTypes() {
		return array();
	}

	public static function getOtherPropTypesHandled() {
		$otherPropTypesHandled = array( '_wpg' );
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			$otherPropTypesHandled[] = '_str';
		} else {
			$otherPropTypesHandled[] = '_txt';
		}
		return $otherPropTypesHandled;
	}

	public static function getDefaultPropTypeLists() {
		return array(
			'_wpg' => array( 'is_list' => true, 'size' => 100 )
		);
	}

	public static function getOtherPropTypeListsHandled() {
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			return array( '_str' );
		} else {
			return array( '_txt' );
		}
	}

	public static function getDefaultCargoTypes() {
		return array();
	}

	public static function getOtherCargoTypesHandled() {
		return array( 'Page', 'String' );
	}

	public static function getDefaultCargoTypeLists() {
		return array(
			'Page' => array( 'is_list' => true, 'size' => 100 )
		);
	}

	public static function getOtherCargoTypeListsHandled() {
		return array( 'String' );
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsEDSettings;

		$other_args['is_list'] = true;

		if ( array_key_exists( 'values from external data', $other_args ) ) {
			$autocompleteSettings = 'external data';
			$remoteDataType = null;
			if ( array_key_exists( 'origName', $other_args ) ) {
				$name = $other_args['origName'];
			} else {
				$name = $input_name;
			}
			$wgPageFormsEDSettings[$name] = array();
			if ( $other_args['values from external data'] != null ) {
				$wgPageFormsEDSettings[$name]['title'] = $other_args['values from external data'];
			}
			if ( array_key_exists( 'image', $other_args ) ) {
				$image_param = $other_args['image'];
				$wgPageFormsEDSettings[$name]['image'] = $image_param;
				global $edgValues;
				for ( $i = 0; $i < count( $edgValues[$image_param] ); $i++ ) {
					$image = $edgValues[$image_param][$i];
					if ( strpos( $image, "http" ) !== 0 ) {
						$file = wfFindFile( $image );
						if ( $file ) {
							$url = $file->getFullUrl();
							$edgValues[$image_param][$i] = $url;
						} else {
							$edgValues[$image_param][$i] = "";
						}
					}
				}
			}
			if ( array_key_exists( 'description', $other_args ) ) {
				$wgPageFormsEDSettings[$name]['description'] = $other_args['description'];
			}
		} else {
			list( $autocompleteSettings, $remoteDataType, $delimiter ) = PFTextWithAutocompleteInput::setAutocompleteValues( $other_args );
		}

		if ( is_array( $cur_value ) ) {
			$cur_value = implode( $delimiter, $cur_value );
		}

		$className = 'pfTokens ';
		$className .= ( $is_mandatory ) ? 'mandatoryField' : 'createboxInput';
		if ( array_key_exists( 'class', $other_args ) ) {
			$className .= ' ' . $other_args['class'];
		}
		$input_id = 'input_' . $wgPageFormsFieldNum;

		if ( array_key_exists( 'size', $other_args ) ) {
			$size = $other_args['size'];
		} else {
			$size = '100';
		}

		$inputAttrs = array(
			'id' => $input_id,
			'size' => $size,
			'class' => $className,
			'tabindex' => $wgPageFormsTabIndex,
			'autocompletesettings' => $autocompleteSettings,
		);
		if ( array_key_exists( 'origName', $other_args ) ) {
			$inputAttrs['origName'] = $other_args['origName'];
		}
		if ( array_key_exists( 'existing values only', $other_args ) ) {
			$inputAttrs['existingvaluesonly'] = 'true';
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
		if ( array_key_exists( 'max values', $other_args ) ) {
			$inputAttrs['maxvalues'] = $other_args['max values'];
		}
		if ( array_key_exists( 'namespace', $other_args ) ) {
			$inputAttrs['data-namespace'] = $other_args['namespace'];
		}

		$text = "\n\t" . Html::input( $input_name, $cur_value, 'text', $inputAttrs ) . "\n";

		if ( array_key_exists( 'uploadable', $other_args ) && $other_args['uploadable'] == true ) {
			if ( array_key_exists( 'default filename', $other_args ) ) {
				$default_filename = $other_args['default filename'];
			} else {
				$default_filename = '';
			}

			$text .= PFTextInput::uploadableHTML( $input_id, $delimiter, $default_filename, $cur_value, $other_args );
		}

		$spanClass = 'inputSpan';
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}
		$text = "\n" . Html::rawElement( 'span', array( 'class' => $spanClass ), $text );

		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = array(
			'name' => 'size',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_size' )->text()
		);
		$params[] = array(
			'name' => 'placeholder',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_placeholder' )->text()
		);
		$params[] = array(
			'name' => 'existing values only',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_existingvaluesonly' )->text()
		);
		$params[] = array(
			'name' => 'max values',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_maxvalues' )->text()
		);
		$params = array_merge( $params, PFTextWithAutocompleteInput::getAutocompletionParameters() );
		$params[] = array(
			'name' => 'uploadable',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_uploadable' )->text()
		);
		$params[] = array(
			'name' => 'default filename',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_defaultfilename' )->text()
		);

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
