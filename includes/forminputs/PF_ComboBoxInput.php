<?php
/**
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;

/**
 * @ingroup PFFormInput
 */
class PFComboBoxInput extends PFFormInput {

	public static function getName(): string {
		return 'combobox';
	}

	public static function getOtherPropTypesHandled() {
		return [ '_wpg', '_str' ];
	}

	public static function getDefaultCargoTypes() {
		return [ 'Page' => [] ];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'String' ];
	}

	/**
	 * @param string|string[] $cur_value
	 * @param string $input_name
	 * @param bool $is_mandatory
	 * @param bool $is_disabled
	 * @param array $other_args
	 * @return string
	 */
	public static function getHTML( $cur_value, string $input_name, bool $is_mandatory, bool $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsEDSettings;
		// $cur_value may be a simple string or an array,
		// possibly even a mapped value-label array.
		if ( is_array( $cur_value ) ) {
			$cur_label = reset( $cur_value );
			$cur_val_keys = array_keys( $cur_value );
			$cur_value = reset( $cur_val_keys );
		} else {
			$cur_label = $cur_value;
		}

		$className = 'pfComboBox';
		if ( array_key_exists( 'class', $other_args ) ) {
			$className .= ' ' . $other_args['class'];
		}

		if ( array_key_exists( 'size', $other_args ) ) {
			$size = intval( $other_args['size'] );
			if ( $size == 0 ) {
				$size = 35;
			}
		} else {
			$size = 35;
		}
		if ( array_key_exists( 'values from external data', $other_args ) ) {
			$autocompleteSettings = 'external data';
			$remoteDataType = null;
			if ( array_key_exists( 'origName', $other_args ) ) {
				$name = $other_args['origName'];
			} else {
				$name = $input_name;
			}
			$wgPageFormsEDSettings[$name] = [];
			if ( $other_args['values from external data'] != null ) {
				$wgPageFormsEDSettings[$name]['title'] = $other_args['values from external data'];
			}
			if ( array_key_exists( 'image', $other_args ) ) {
				$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();
				$image_param = $other_args['image'];
				$wgPageFormsEDSettings[$name]['image'] = $image_param;
				global $edgValues;
				for ( $i = 0; $i < count( $edgValues[$image_param] ); $i++ ) {
					$image = $edgValues[$image_param][$i];
					if ( strpos( $image, "http" ) !== 0 ) {
						$file = $repoGroup->findFile( $image );
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
				if ( !array_key_exists( 'size', $other_args ) ) {
					// Set larger default size if description is also there
					$size = '80';
				}
			}
		} else {
			list( $autocompleteSettings, $remoteDataType, $delimiter ) = PFValuesUtils::setAutocompleteValues( $other_args, false );
			$autocompleteSettings = str_replace( "'", "\'", $autocompleteSettings );
		}

		$input_id = 'input_' . $wgPageFormsFieldNum;

		$inputAttrs = [
			'id' => $input_id,
			'name' => $input_name,
			'class' => $className,
			'tabindex' => $wgPageFormsTabIndex,
			'autocompletesettings' => $autocompleteSettings,
			'value' => $cur_value,
			'data-size' => $size * 6,
			'style' => 'width:' . $size * 6 . 'px',
			'disabled' => $is_disabled,
			'data-value' => $cur_value,
			'data-label' => $cur_label
		];
		if ( array_key_exists( 'origName', $other_args ) ) {
			$inputAttrs['origname'] = $other_args['origName'];
		}
		if ( array_key_exists( 'existing values only', $other_args ) ) {
			$inputAttrs['existingvaluesonly'] = 'true';
		}
		if ( array_key_exists( 'placeholder', $other_args ) ) {
			$inputAttrs['placeholder'] = $other_args['placeholder'];
		}
		if ( $remoteDataType !== null ) {
			$inputAttrs['autocompletedatatype'] = $remoteDataType;
		}
		if ( array_key_exists( 'mapping property', $other_args ) ) {
			$inputAttrs['mappingproperty'] = $other_args['mapping property'];
		}
		if ( array_key_exists( 'mapping template', $other_args ) ) {
			$inputAttrs['mappingtemplate'] = $other_args['mapping template'];
		}

		$innerDropdown = '';

		if ( !$is_mandatory || $cur_value === '' ) {
			$innerDropdown .= "	<option value=\"\"></option>\n";
		}
		$possible_values = $other_args['possible_values'];
		if ( $possible_values == null ) {
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

		// Make sure that the current value always shows up when the
		// form is first displayed.
		$innerDropdown .= Html::element(
			'option',
			[ 'selected' => true, 'value' => $cur_value ],
			$cur_label
		);

		$inputText = Html::rawElement( 'select', $inputAttrs, $innerDropdown );

		if ( array_key_exists( 'uploadable', $other_args ) && $other_args['uploadable'] == true ) {
			if ( array_key_exists( 'default filename', $other_args ) ) {
				$default_filename = $other_args['default filename'];
			} else {
				$default_filename = '';
			}

			$inputText .= PFTextInput::uploadableHTML( $input_id, $delimiter = null, $default_filename, $cur_value, $other_args );
		}

		$spanID = 'span_' . $wgPageFormsFieldNum;
		$spanClass = 'comboboxSpan';
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}

		if ( array_key_exists( 'show on select', $other_args ) ) {
			$spanClass .= ' pfShowIfSelected';
			PFFormUtils::setShowOnSelect( $other_args['show on select'], $spanID );
		}

		$spanAttrs = [
			'id' => $spanID,
			'class' => $spanClass,
			'data-input-type' => 'combobox'
		];
		$text = Html::rawElement( 'span', $spanAttrs, $inputText );
		if ( array_key_exists( 'feeds to map', $other_args ) ) {
			global $wgPageFormsMapsWithFeeders;
			$targetMapName = $other_args['feeds to map'];
			if ( array_key_exists( 'part_of_multiple', $other_args ) ) {
				$targetMapName = str_replace( '[', '[num][', $targetMapName );
			}
			$mapField = Html::rawElement( 'input', [
				'class' => 'combobox_map_feed',
				'data-feeds-to-map' => $targetMapName,
				'style' => 'display:none;'
			] );
			$wgPageFormsMapsWithFeeders[$targetMapName] = true;
			$text .= $mapField;
		}
		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = [
			'name' => 'size',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_size' )->text()
		];
		$params = array_merge( $params, PFEnumInput::getValuesParameters() );
		$params[] = [
			'name' => 'existing values only',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_existingvaluesonly' )->text()
		];
		$params[] = [
			'name' => 'uploadable',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_uploadable' )->text()
		];
		$params[] = [
			'name' => 'default filename',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_defaultfilename' )->text()
		];
		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText(): string {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}

	public function getResourceModuleNames() {
		return [ 'ext.pageforms.ooui.combobox' ];
	}
}
