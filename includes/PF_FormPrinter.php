<?php
/**
 * Handles the creation and running of a user-created form.
 *
 * @author Yaron Koren
 * @author Nils Oppermann
 * @author Jeffrey Stuckman
 * @author Harold Solbrig
 * @author Daniel Hansch
 * @author Stephan Gambke
 * @author LY Meng
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;

class PFFormPrinter {

	public $mSemanticTypeHooks;
	public $mCargoTypeHooks;
	public $mInputTypeHooks;
	public $standardInputsIncluded;
	public $mPageTitle;

	private $mInputTypeClasses;
	private $mDefaultInputForPropType;
	private $mDefaultInputForPropTypeList;
	private $mPossibleInputsForPropType;
	private $mPossibleInputsForPropTypeList;
	private $mDefaultInputForCargoType;
	private $mDefaultInputForCargoTypeList;
	private $mPossibleInputsForCargoType;
	private $mPossibleInputsForCargoTypeList;

	public function __construct() {
		global $wgPageFormsDisableOutsideServices;
		// Initialize variables.
		$this->mSemanticTypeHooks = [];
		$this->mCargoTypeHooks = [];
		$this->mInputTypeHooks = [];
		$this->mInputTypeClasses = [];
		$this->mDefaultInputForPropType = [];
		$this->mDefaultInputForPropTypeList = [];
		$this->mPossibleInputsForPropType = [];
		$this->mPossibleInputsForPropTypeList = [];
		$this->mDefaultInputForCargoType = [];
		$this->mDefaultInputForCargoTypeList = [];
		$this->mPossibleInputsForCargoType = [];
		$this->mPossibleInputsForCargoTypeList = [];

		$this->standardInputsIncluded = false;

		$this->registerInputType( 'PFTextInput' );
		$this->registerInputType( 'PFTextWithAutocompleteInput' );
		$this->registerInputType( 'PFTextAreaInput' );
		$this->registerInputType( 'PFTextAreaWithAutocompleteInput' );
		$this->registerInputType( 'PFDateInput' );
		$this->registerInputType( 'PFStartDateInput' );
		$this->registerInputType( 'PFEndDateInput' );
		$this->registerInputType( 'PFDatePickerInput' );
		$this->registerInputType( 'PFDateTimePicker' );
		$this->registerInputType( 'PFDateTimeInput' );
		$this->registerInputType( 'PFStartDateTimeInput' );
		$this->registerInputType( 'PFEndDateTimeInput' );
		$this->registerInputType( 'PFYearInput' );
		$this->registerInputType( 'PFCheckboxInput' );
		$this->registerInputType( 'PFDropdownInput' );
		$this->registerInputType( 'PFRadioButtonInput' );
		$this->registerInputType( 'PFCheckboxesInput' );
		$this->registerInputType( 'PFListBoxInput' );
		$this->registerInputType( 'PFComboBoxInput' );
		$this->registerInputType( 'PFTreeInput' );
		$this->registerInputType( 'PFTokensInput' );
		$this->registerInputType( 'PFRegExpInput' );
		$this->registerInputType( 'PFRatingInput' );
		// Add this if the Semantic Maps extension is not
		// included, or if it's SM (really Maps) v4.0 or higher.
		if ( !$wgPageFormsDisableOutsideServices ) {
			if ( !defined( 'SM_VERSION' ) || version_compare( SM_VERSION, '4.0', '>=' ) ) {
				$this->registerInputType( 'PFGoogleMapsInput' );
			}
			$this->registerInputType( 'PFOpenLayersInput' );
			$this->registerInputType( 'PFLeafletInput' );
		}

		// All-purpose setup hook.
		// Avoid PHP 7.1 warning from passing $this by reference.
		$formPrinterRef = $this;
		Hooks::run( 'PageForms::FormPrinterSetup', [ &$formPrinterRef ] );
	}

	public function setSemanticTypeHook( $type, $is_list, $class_name, $default_args ) {
		$this->mSemanticTypeHooks[$type][$is_list] = [ $class_name, $default_args ];
	}

	public function setCargoTypeHook( $type, $is_list, $class_name, $default_args ) {
		$this->mCargoTypeHooks[$type][$is_list] = [ $class_name, $default_args ];
	}

	public function setInputTypeHook( $input_type, $class_name, $default_args ) {
		$this->mInputTypeHooks[$input_type] = [ $class_name, $default_args ];
	}

	/**
	 * Register all information about the passed-in form input class.
	 *
	 * @param string $inputTypeClass The full qualified class name representing the new input.
	 * Must be derived from PFFormInput.
	 */
	public function registerInputType( $inputTypeClass ) {
		$inputTypeName = call_user_func( [ $inputTypeClass, 'getName' ] );
		$this->mInputTypeClasses[$inputTypeName] = $inputTypeClass;
		$this->setInputTypeHook( $inputTypeName, $inputTypeClass, [] );

		$defaultProperties = call_user_func( [ $inputTypeClass, 'getDefaultPropTypes' ] );
		foreach ( $defaultProperties as $propertyType => $additionalValues ) {
			$this->setSemanticTypeHook( $propertyType, false, $inputTypeClass, $additionalValues );
			$this->mDefaultInputForPropType[$propertyType] = $inputTypeName;
		}
		$defaultPropertyLists = call_user_func( [ $inputTypeClass, 'getDefaultPropTypeLists' ] );
		foreach ( $defaultPropertyLists as $propertyType => $additionalValues ) {
			$this->setSemanticTypeHook( $propertyType, true, $inputTypeClass, $additionalValues );
			$this->mDefaultInputForPropTypeList[$propertyType] = $inputTypeName;
		}

		$defaultCargoTypes = call_user_func( [ $inputTypeClass, 'getDefaultCargoTypes' ] );
		foreach ( $defaultCargoTypes as $fieldType => $additionalValues ) {
			$this->setCargoTypeHook( $fieldType, false, $inputTypeClass, $additionalValues );
			$this->mDefaultInputForCargoType[$fieldType] = $inputTypeName;
		}
		$defaultCargoTypeLists = call_user_func( [ $inputTypeClass, 'getDefaultCargoTypeLists' ] );
		foreach ( $defaultCargoTypeLists as $fieldType => $additionalValues ) {
			$this->setCargoTypeHook( $fieldType, true, $inputTypeClass, $additionalValues );
			$this->mDefaultInputForCargoTypeList[$fieldType] = $inputTypeName;
		}

		$otherProperties = call_user_func( [ $inputTypeClass, 'getOtherPropTypesHandled' ] );
		foreach ( $otherProperties as $propertyTypeID ) {
			if ( array_key_exists( $propertyTypeID, $this->mPossibleInputsForPropType ) ) {
				$this->mPossibleInputsForPropType[$propertyTypeID][] = $inputTypeName;
			} else {
				$this->mPossibleInputsForPropType[$propertyTypeID] = [ $inputTypeName ];
			}
		}
		$otherPropertyLists = call_user_func( [ $inputTypeClass, 'getOtherPropTypeListsHandled' ] );
		foreach ( $otherPropertyLists as $propertyTypeID ) {
			if ( array_key_exists( $propertyTypeID, $this->mPossibleInputsForPropTypeList ) ) {
				$this->mPossibleInputsForPropTypeList[$propertyTypeID][] = $inputTypeName;
			} else {
				$this->mPossibleInputsForPropTypeList[$propertyTypeID] = [ $inputTypeName ];
			}
		}

		$otherCargoTypes = call_user_func( [ $inputTypeClass, 'getOtherCargoTypesHandled' ] );
		foreach ( $otherCargoTypes as $cargoType ) {
			if ( array_key_exists( $cargoType, $this->mPossibleInputsForCargoType ) ) {
				$this->mPossibleInputsForCargoType[$cargoType][] = $inputTypeName;
			} else {
				$this->mPossibleInputsForCargoType[$cargoType] = [ $inputTypeName ];
			}
		}
		$otherCargoTypeLists = call_user_func( [ $inputTypeClass, 'getOtherCargoTypeListsHandled' ] );
		foreach ( $otherCargoTypeLists as $cargoType ) {
			if ( array_key_exists( $cargoType, $this->mPossibleInputsForCargoTypeList ) ) {
				$this->mPossibleInputsForCargoTypeList[$cargoType][] = $inputTypeName;
			} else {
				$this->mPossibleInputsForCargoTypeList[$cargoType] = [ $inputTypeName ];
			}
		}

		// FIXME: No need to register these functions explicitly. Instead
		// formFieldHTML should call $someInput -> getJsInitFunctionData() and
		// store its return value. formHTML should at some (late) point use the
		// stored data.
		//
		// $initJSFunction = call_user_func( array( $inputTypeClass, 'getJsInitFunctionData' ) );
		// if ( !is_null( $initJSFunction ) ) {
		// 	$wgPageFormsInitJSFunctions[] = $initJSFunction;
		// }
		//
		// $validationJSFunctions = call_user_func( array( $inputTypeClass, 'getJsValidationFunctionData' ) );
		// if ( count( $validationJSFunctions ) > 0 ) {
		// 	$wgPageFormsValidationJSFunctions = array_merge( $wgPageFormsValidationJSFunctions, $initJSFunction );
		// }
	}

	public function getInputType( $inputTypeName ) {
		if ( array_key_exists( $inputTypeName, $this->mInputTypeClasses ) ) {
			return $this->mInputTypeClasses[$inputTypeName];
		} else {
			return null;
		}
	}

	public function getDefaultInputTypeSMW( $isList, $propertyType ) {
		if ( $isList ) {
			if ( array_key_exists( $propertyType, $this->mDefaultInputForPropTypeList ) ) {
				return $this->mDefaultInputForPropTypeList[$propertyType];
			} else {
				return null;
			}
		} else {
			if ( array_key_exists( $propertyType, $this->mDefaultInputForPropType ) ) {
				return $this->mDefaultInputForPropType[$propertyType];
			} else {
				return null;
			}
		}
	}

	public function getDefaultInputTypeCargo( $isList, $fieldType ) {
		if ( $isList ) {
			if ( array_key_exists( $fieldType, $this->mDefaultInputForCargoTypeList ) ) {
				return $this->mDefaultInputForCargoTypeList[$fieldType];
			} else {
				return null;
			}
		} else {
			if ( array_key_exists( $fieldType, $this->mDefaultInputForCargoType ) ) {
				return $this->mDefaultInputForCargoType[$fieldType];
			} else {
				return null;
			}
		}
	}

	public function getPossibleInputTypesSMW( $isList, $propertyType ) {
		if ( $isList ) {
			if ( array_key_exists( $propertyType, $this->mPossibleInputsForPropTypeList ) ) {
				return $this->mPossibleInputsForPropTypeList[$propertyType];
			} else {
				return [];
			}
		} else {
			if ( array_key_exists( $propertyType, $this->mPossibleInputsForPropType ) ) {
				return $this->mPossibleInputsForPropType[$propertyType];
			} else {
				return [];
			}
		}
	}

	public function getPossibleInputTypesCargo( $isList, $fieldType ) {
		if ( $isList ) {
			if ( array_key_exists( $fieldType, $this->mPossibleInputsForCargoTypeList ) ) {
				return $this->mPossibleInputsForCargoTypeList[$fieldType];
			} else {
				return [];
			}
		} else {
			if ( array_key_exists( $fieldType, $this->mPossibleInputsForCargoType ) ) {
				return $this->mPossibleInputsForCargoType[$fieldType];
			} else {
				return [];
			}
		}
	}

	public function getAllInputTypes() {
		return array_keys( $this->mInputTypeClasses );
	}

	/**
	 * Show the set of previous deletions for the page being edited.
	 * @param OutputPage $out
	 * @return true
	 */
	function showDeletionLog( $out ) {
		LogEventsList::showLogExtract( $out, 'delete', $this->mPageTitle->getPrefixedText(),
			'', [ 'lim' => 10,
				'conds' => [ "log_action != 'revision'" ],
				'showIfEmpty' => false,
				'msgKey' => [ 'moveddeleted-notice' ] ]
		);
		return true;
	}

	/**
	 * Like PHP's str_replace(), but only replaces the first found
	 * instance - unfortunately, str_replace() doesn't allow for that.
	 * This code is basically copied directly from
	 * http://www.php.net/manual/en/function.str-replace.php#86177
	 * - this might make sense in the PFUtils class, if it's useful in
	 * other places.
	 * @param string $search
	 * @param string $replace
	 * @param string $subject
	 * @return string
	 */
	function strReplaceFirst( $search, $replace, $subject ) {
		$firstChar = strpos( $subject, $search );
		if ( $firstChar !== false ) {
			$beforeStr = substr( $subject, 0, $firstChar );
			$afterStr = substr( $subject, $firstChar + strlen( $search ) );
			return $beforeStr . $replace . $afterStr;
		} else {
			return $subject;
		}
	}

	static function placeholderFormat( $templateName, $fieldName ) {
		$templateName = str_replace( '_', ' ', $templateName );
		$fieldName = str_replace( '_', ' ', $fieldName );
		return $templateName . '___' . $fieldName;
	}

	static function makePlaceholderInFormHTML( $str ) {
		return '@insert"HTML_' . $str . '@';
	}

	function multipleTemplateStartHTML( $tif ) {
		// If placeholder is set, it means we want to insert a
		// multiple template form's HTML into the main form's HTML.
		// So, the HTML will be stored in $text.
		$text = "\t" . '<div class="multipleTemplateWrapper">' . "\n";
		$attrs = [ 'class' => 'multipleTemplateList' ];
		if ( $tif->getMinInstancesAllowed() !== null ) {
			$attrs['minimumInstances'] = $tif->getMinInstancesAllowed();
		}
		if ( $tif->getMaxInstancesAllowed() !== null ) {
			$attrs['maximumInstances'] = $tif->getMaxInstancesAllowed();
		}
		if ( $tif->getDisplayedFieldsWhenMinimized() != null ) {
			$attrs['data-displayed-fields-when-minimized'] = $tif->getDisplayedFieldsWhenMinimized();
		}
		$text .= "\t" . Html::openElement( 'div', $attrs ) . "\n";
		return $text;
	}

	/**
	 * Creates the HTML for the inner table for every instance of a
	 * multiple-instance template in the form.
	 * @param bool $form_is_disabled
	 * @param string $mainText
	 * @return string
	 */
	function multipleTemplateInstanceTableHTML( $form_is_disabled, $mainText ) {
		if ( $form_is_disabled ) {
			$addAboveButton = $removeButton = '';
		} else {
			$addAboveButton = Html::element( 'a', [ 'class' => "addAboveButton", 'title' => wfMessage( 'pf_formedit_addanotherabove' )->text() ] );
			$removeButton = Html::element( 'a', [ 'class' => "removeButton", 'title' => wfMessage( 'pf_formedit_remove' )->text() ] );
		}

		$text = <<<END
			<table class="multipleTemplateInstanceTable">
			<tr>
			<td class="instanceRearranger"></td>
			<td class="instanceMain">$mainText</td>
			<td class="instanceAddAbove">$addAboveButton</td>
			<td class="instanceRemove">$removeButton</td>
			</tr>
			</table>
END;

		return $text;
	}

	/**
	 * Creates the HTML for a single instance of a multiple-instance
	 * template.
	 * @param PFTemplateInForm $template_in_form
	 * @param bool $form_is_disabled
	 * @param string &$section
	 * @return string
	 */
	function multipleTemplateInstanceHTML( $template_in_form, $form_is_disabled, &$section ) {
		global $wgPageFormsCalendarHTML;

		$wgPageFormsCalendarHTML[$template_in_form->getTemplateName()] = str_replace( '[num]', "[cf]", $section );

		// Add the character "a" onto the instance number of this input
		// in the form, to differentiate the inputs the form starts out
		// with from any inputs added by the Javascript.
		$section = str_replace( '[num]', "[{$template_in_form->getInstanceNum()}a]", $section );
		// @TODO - this replacement should be
		// case- and spacing-insensitive.
		// Also, keeping the "id=" attribute should not be
		// necessary; but currently it is, for "show on select".
		$section = preg_replace_callback(
			'/ id="(.*?)"/',
			static function ( $matches ) {
				$id = htmlspecialchars( $matches[1], ENT_QUOTES );
				return " id=\"$id\" data-origID=\"$id\" ";
			},
			$section
		);

		$text = "\t\t" . Html::rawElement( 'div',
				[
				// The "multipleTemplate" class is there for
				// backwards-compatibility with any custom CSS on people's
				// wikis before PF 2.0.9.
				'class' => "multipleTemplateInstance multipleTemplate"
			],
			$this->multipleTemplateInstanceTableHTML( $form_is_disabled, $section )
		) . "\n";

		return $text;
	}

	/**
	 * Creates the end of the HTML for a multiple-instance template -
	 * including the sections necessary for adding additional instances.
	 * @param PFTemplateInForm $template_in_form
	 * @param bool $form_is_disabled
	 * @param string $section
	 * @return string
	 */
	function multipleTemplateEndHTML( $template_in_form, $form_is_disabled, $section ) {
		global $wgPageFormsTabIndex;

		$text = "\t\t" . Html::rawElement( 'div',
			[
				'class' => "multipleTemplateStarter",
				'style' => "display: none",
			],
			$this->multipleTemplateInstanceTableHTML( $form_is_disabled, $section )
		) . "\n";

		$attributes = [
			'tabIndex' => $wgPageFormsTabIndex,
			'classes' => [ 'multipleTemplateAdder' ],
			'label' => Sanitizer::decodeCharReferences( $template_in_form->getAddButtonText() ),
			'icon' => 'add'
		];
		if ( $form_is_disabled ) {
			$attributes['disabled'] = true;
			$attributes['classes'] = [];
		}
		$button = new OOUI\ButtonWidget( $attributes );
		$text .= <<<END
	</div><!-- multipleTemplateList -->
		<p>$button</p>
		<div class="pfErrorMessages"></div>
	</div><!-- multipleTemplateWrapper -->
</fieldset>
END;
		return $text;
	}

	function tableHTML( $tif, $instanceNum ) {
		global $wgPageFormsFieldNum;

		$allGridValues = $tif->getGridValues();
		if ( array_key_exists( $instanceNum, $allGridValues ) ) {
			$gridValues = $allGridValues[$instanceNum];
		} else {
			$gridValues = null;
		}

		$html = '';
		foreach ( $tif->getFields() as $formField ) {
			$fieldName = $formField->template_field->getFieldName();
			if ( $gridValues == null ) {
				$curValue = null;
			} else {
				$curValue = $gridValues[$fieldName];
			}

			if ( $formField->holdsTemplate() ) {
				$attribs = [];
				if ( $formField->hasFieldArg( 'class' ) ) {
					$attribs['class'] = $formField->getFieldArg( 'class' );
				}
				$html .= '</table>' . "\n";
				$html .= Html::hidden( $formField->getInputName(), $curValue, $attribs );
				$html .= $formField->additionalHTMLForInput( $curValue, $fieldName, $tif->getTemplateName() );
				$html .= '<table class="formtable">' . "\n";
				continue;
			}

			if ( $formField->isHidden() ) {
				$attribs = [];
				if ( $formField->hasFieldArg( 'class' ) ) {
					$attribs['class'] = $formField->getFieldArg( 'class' );
				}
				$html .= Html::hidden( $formField->getInputName(), $curValue, $attribs );
				continue;
			}

			$wgPageFormsFieldNum++;
			if ( $formField->getLabel() !== null ) {
				$labelText = $formField->getLabel();
				// Kind of a @HACK - for a checkbox within
				// display=table, 'label' is used for two
				// purposes: the label column, and the text
				// after the checkbox. Unset the value here so
				// that it's only used for the first purpose,
				// and doesn't show up twice.
				$formField->setFieldArg( 'label', '' );
			} elseif ( $formField->getLabelMsg() !== null ) {
				$labelText = wfMessage( $formField->getLabelMsg() )->parse();
			} elseif ( $formField->template_field->getLabel() !== null ) {
				$labelText = $formField->template_field->getLabel() . ':';
			} else {
				$labelText = $fieldName . ': ';
			}
			$label = Html::element( 'label',
				[ 'for' => "input_$wgPageFormsFieldNum" ],
				$labelText );

			$labelCellAttrs = [];
			if ( $formField->hasFieldArg( 'tooltip' ) ) {
				$labelCellAttrs['data-tooltip'] = $formField->getFieldArg( 'tooltip' );
			}

			$labelCell = Html::rawElement( 'th', $labelCellAttrs, $label );
			$inputHTML = $this->formFieldHTML( $formField, $curValue );
			$inputHTML .= $formField->additionalHTMLForInput( $curValue, $fieldName, $tif->getTemplateName() );
			$inputCell = Html::rawElement( 'td', null, $inputHTML );
			$html .= Html::rawElement( 'tr', null, $labelCell . $inputCell ) . "\n";
		}

		$html = Html::rawElement( 'table', [ 'class' => 'formtable' ], $html );

		return $html;
	}

	function getSpreadsheetAutocompleteAttributes( $formFieldArgs ) {
		if ( array_key_exists( 'values from category', $formFieldArgs ) ) {
			return [ 'category', $formFieldArgs[ 'values from category' ] ];
		} elseif ( array_key_exists( 'cargo table', $formFieldArgs ) ) {
			$cargo_table = $formFieldArgs[ 'cargo table' ];
			$cargo_field = $formFieldArgs[ 'cargo field' ];
			return [ 'cargo field', $cargo_table . '|' . $cargo_field ];
		} elseif ( array_key_exists( 'values from property', $formFieldArgs ) ) {
			return [ 'property', $formFieldArgs['values from property'] ];
		} elseif ( array_key_exists( 'values from concept', $formFieldArgs ) ) {
			return [ 'concept', $formFieldArgs['values from concept'] ];
		} elseif ( array_key_exists( 'values dependent on', $formFieldArgs ) ) {
			return [ 'dep_on', '' ];
		} elseif ( array_key_exists( 'values from external data', $formFieldArgs ) ) {
			return [ 'external data', $formFieldArgs['origName'] ];
		} else {
			return [ '', '' ];
		}
	}

	function spreadsheetHTML( $tif ) {
		global $wgOut, $wgPageFormsGridValues, $wgPageFormsGridParams;
		global $wgPageFormsScriptPath;

		if ( empty( $tif->getFields() ) ) {
			return;
		}

		$wgOut->addModules( 'ext.pageforms.spreadsheet' );

		$gridParams = [];
		foreach ( $tif->getFields() as $formField ) {
			$templateField = $formField->template_field;
			$formFieldArgs = $formField->getFieldArgs();
			$possibleValues = $formField->getPossibleValues();

			$inputType = $formField->getInputType();
			$gridParamValues = [ 'name' => $templateField->getFieldName() ];
			list( $autocompletedatatype, $autocompletesettings ) = $this->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
			if ( $formField->getLabel() !== null ) {
				$gridParamValues['label'] = $formField->getLabel();
			}
			if ( $formField->getDefaultValue() !== null ) {
				$gridParamValues['default'] = $formField->getDefaultValue();
			}
			// currently the spreadsheets in Page Forms doesn't support the tokens input
			// so it's better to take a default jspreadsheet editor for tokens
			if ( $formField->isList() || $inputType == 'tokens' ) {
				$autocompletedatatype = '';
				$autocompletesettings = '';
				$gridParamValues['type'] = 'text';
			} elseif ( !empty( $possibleValues )
				&& $autocompletedatatype != 'category' && $autocompletedatatype != 'cargo field'
				&& $autocompletedatatype != 'concept' && $autocompletedatatype != 'property' ) {
				$gridParamValues['values'] = $possibleValues;
				if ( $formField->isList() ) {
					$gridParamValues['list'] = true;
					$gridParamValues['delimiter'] = $formField->getFieldArg( 'delimiter' );
				}
			} elseif ( $inputType == 'textarea' ) {
				$gridParamValues['type'] = 'textarea';
			} elseif ( $inputType == 'checkbox' ) {
				$gridParamValues['type'] = 'checkbox';
			} elseif ( $inputType == 'date' ) {
				$gridParamValues['type'] = 'date';
			} elseif ( $inputType == 'datetime' ) {
				$gridParamValues['type'] = 'datetime';
			} elseif ( $possibleValues != null ) {
				array_unshift( $possibleValues, '' );
				$completePossibleValues = [];
				foreach ( $possibleValues as $value ) {
					$completePossibleValues[] = [ 'Name' => $value, 'Id' => $value ];
				}
				$gridParamValues['type'] = 'select';
				$gridParamValues['items'] = $completePossibleValues;
				$gridParamValues['valueField'] = 'Id';
				$gridParamValues['textField'] = 'Name';
			} else {
				$gridParamValues['type'] = 'text';
			}
			$gridParamValues['autocompletedatatype'] = $autocompletedatatype;
			$gridParamValues['autocompletesettings'] = $autocompletesettings;
			$gridParamValues['inputType'] = $inputType;
			$gridParams[] = $gridParamValues;
		}

		$templateName = $tif->getTemplateName();
		$templateDivID = str_replace( ' ', '', $templateName ) . "Grid";
		$templateDivAttrs = [
			'class' => 'pfSpreadsheet',
			'id' => $templateDivID,
			'data-template-name' => $templateName
		];
		if ( $tif->getHeight() != null ) {
			$templateDivAttrs['height'] = $tif->getHeight();
		}

		$loadingImage = Html::element( 'img', [ 'src' => "$wgPageFormsScriptPath/skins/loading.gif" ] );
		$loadingImageDiv = '<div class="loadingImage">' . $loadingImage . '</div>';
		$text = Html::rawElement( 'div', $templateDivAttrs, $loadingImageDiv );

		$wgPageFormsGridParams[$templateName] = $gridParams;
		$wgPageFormsGridValues[$templateName] = $tif->getGridValues();

		PFFormUtils::setGlobalVarsForSpreadsheet();

		return $text;
	}

	/**
	 * Get a string representing the current time, for the time zone
	 * specified in the wiki.
	 * @param string $includeTime
	 * @param string $includeTimezone
	 * @return string
	 */
	function getStringForCurrentTime( $includeTime, $includeTimezone ) {
		global $wgLocaltimezone, $wgAmericanDates, $wgPageForms24HourTime;

		if ( isset( $wgLocaltimezone ) ) {
			$serverTimezone = date_default_timezone_get();
			date_default_timezone_set( $wgLocaltimezone );
		}
		$cur_time = time();
		$year = date( "Y", $cur_time );
		$month = date( "n", $cur_time );
		$day = date( "j", $cur_time );
		if ( $wgAmericanDates == true ) {
			$month_names = PFFormUtils::getMonthNames();
			$month_name = $month_names[$month - 1];
			$curTimeString = "$month_name $day, $year";
		} else {
			$curTimeString = "$year-$month-$day";
		}
		if ( isset( $wgLocaltimezone ) ) {
			date_default_timezone_set( $serverTimezone );
		}
		if ( !$includeTime ) {
			return $curTimeString;
		}

		if ( $wgPageForms24HourTime ) {
			$hour = str_pad( intval( substr( date( "G", $cur_time ), 0, 2 ) ), 2, '0', STR_PAD_LEFT );
		} else {
			$hour = str_pad( intval( substr( date( "g", $cur_time ), 0, 2 ) ), 2, '0', STR_PAD_LEFT );
		}
		$minute = str_pad( intval( substr( date( "i", $cur_time ), 0, 2 ) ), 2, '0', STR_PAD_LEFT );
		$second = str_pad( intval( substr( date( "s", $cur_time ), 0, 2 ) ), 2, '0', STR_PAD_LEFT );
		if ( $wgPageForms24HourTime ) {
			$curTimeString .= " $hour:$minute:$second";
		} else {
			$ampm = date( "A", $cur_time );
			$curTimeString .= " $hour:$minute:$second $ampm";
		}

		if ( $includeTimezone ) {
			$timezone = date( "T", $cur_time );
			$curTimeString .= " $timezone";
		}

		return $curTimeString;
	}

	/**
	 * If the value passed in for a certain field, when a form is
	 * submitted, is an array, then it might be from a checkbox
	 * or date input - in that case, convert it into a string.
	 * @param array $value
	 * @param string $delimiter
	 * @return string
	 */
	static function getStringFromPassedInArray( $value, $delimiter ) {
		// If it's just a regular list, concatenate it.
		// This is needed due to some strange behavior
		// in PF, where, if a preload page is passed in
		// in the query string, the form ends up being
		// parsed twice.
		if ( array_key_exists( 'is_list', $value ) ) {
			unset( $value['is_list'] );
			return str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], implode( "$delimiter ", $value ) );
		}

		// if it has 1 or 2 elements, assume it's a checkbox; if it has
		// 3 elements, assume it's a date
		// - this handling will have to get more complex if other
		// possibilities get added
		if ( count( $value ) == 1 ) {
			return PFUtils::getWordForYesOrNo( false );
		} elseif ( count( $value ) == 2 ) {
			return PFUtils::getWordForYesOrNo( true );
		// if it's 3 or greater, assume it's a date or datetime
		} elseif ( count( $value ) >= 3 ) {
			$month = $value['month'];
			$day = $value['day'];
			if ( $day !== '' ) {
				global $wgAmericanDates;
				if ( $wgAmericanDates == false ) {
					// pad out day to always be two digits
					$day = str_pad( $day, 2, "0", STR_PAD_LEFT );
				}
			}
			$year = $value['year'];
			$hour = $minute = $second = $ampm24h = $timezone = null;
			if ( isset( $value['hour'] ) ) {
				$hour = $value['hour'];
			}
			if ( isset( $value['minute'] ) ) {
				$minute = $value['minute'];
			}
			if ( isset( $value['second'] ) ) {
				$second = $value['second'];
			}
			if ( isset( $value['ampm24h'] ) ) {
				$ampm24h = $value['ampm24h'];
			}
			if ( isset( $value['timezone'] ) ) {
				$timezone = $value['timezone'];
			}
			// if ( $month !== '' && $day !== '' && $year !== '' ) {
			// We can accept either year, or year + month, or year + month + day.
			// if ( $month !== '' && $day !== '' && $year !== '' ) {
			if ( $year !== '' ) {
				// special handling for American dates - otherwise, just
				// the standard year/month/day (where month is a number)
				global $wgAmericanDates;

				if ( $month == '' ) {
					return $year;
				} elseif ( $day == '' ) {
					if ( !$wgAmericanDates ) {
						// The month is a number - we
						// need it to be a string, so
						// that the date will be parsed
						// correctly if strtotime() is
						// used.
						$monthNames = PFFormUtils::getMonthNames();
						$month = $monthNames[$month - 1];
					}
					return "$month $year";
				} else {
					if ( $wgAmericanDates == true ) {
						$new_value = "$month $day, $year";
					} else {
						$new_value = "$year/$month/$day";
					}
					// If there's a day, include whatever
					// time information we have.
					if ( $hour !== null ) {
						$new_value .= " " . str_pad( intval( substr( $hour, 0, 2 ) ), 2, '0', STR_PAD_LEFT ) . ":" . str_pad( intval( substr( $minute, 0, 2 ) ), 2, '0', STR_PAD_LEFT );
					}
					if ( $second !== null ) {
						$new_value .= ":" . str_pad( intval( substr( $second, 0, 2 ) ), 2, '0', STR_PAD_LEFT );
					}
					if ( $ampm24h !== null ) {
						$new_value .= " $ampm24h";
					}
					if ( $timezone !== null ) {
						$new_value .= " $timezone";
					}
					return $new_value;
				}
			}
		}
		return '';
	}

	static function displayLoadingImage() {
		global $wgPageFormsScriptPath;

		$text = '<div id="loadingMask"></div>';
		$loadingBGImage = Html::element( 'img', [ 'src' => "$wgPageFormsScriptPath/skins/loadingbg.png" ] );
		$text .= '<div style="position: fixed; left: 50%; top: 50%;">' . $loadingBGImage . '</div>';
		$loadingImage = Html::element( 'img', [ 'src' => "$wgPageFormsScriptPath/skins/loading.gif" ] );
		$text .= '<div style="position: fixed; left: 50%; top: 50%; padding: 48px;">' . $loadingImage . '</div>';

		return Html::rawElement( 'span', [ 'class' => 'loadingImage' ], $text );
	}

	/**
	 * This function is the real heart of the entire Page Forms
	 * extension. It handles two main actions: (1) displaying a form on the
	 * screen, given a form definition and possibly page contents (if an
	 * existing page is being edited); and (2) creating actual page
	 * contents, if the form was already submitted by the user.
	 *
	 * It also does some related tasks, like figuring out the page name (if
	 * only a page formula exists).
	 * @param string $form_def
	 * @param bool $form_submitted
	 * @param bool $source_is_page
	 * @param string|null $form_id
	 * @param string|null $existing_page_content
	 * @param string|null $page_name
	 * @param string|null $page_name_formula
	 * @param bool $is_query
	 * @param bool $is_embedded
	 * @param bool $is_autocreate true when called by #formredlink with "create page"
	 * @param array $autocreate_query query parameters from #formredlink
	 * @param User|null $user
	 * @return array
	 * @throws FatalError
	 * @throws MWException
	 */
	function formHTML(
		$form_def,
		$form_submitted,
		$source_is_page,
		$form_id = null,
		$existing_page_content = null,
		$page_name = null,
		$page_name_formula = null,
		$is_query = false,
		$is_embedded = false,
		$is_autocreate = false,
		$autocreate_query = [],
		$user = null
	) {
		global $wgRequest;
		// used to represent the current tab index in the form
		global $wgPageFormsTabIndex;
		// used for setting various HTML IDs
		global $wgPageFormsFieldNum;
		global $wgPageFormsShowExpandAllLink;

		// Initialize some variables.
		$wiki_page = new PFWikiPage();
		$wgPageFormsTabIndex = 0;
		$wgPageFormsFieldNum = 0;
		$source_page_matches_this_form = false;
		$form_page_title = null;
		$generated_page_name = $page_name_formula;
		$new_text = "";
		$original_page_content = $existing_page_content;

		// Disable all form elements if user doesn't have edit
		// permission - two different checks are needed, because
		// editing permissions can be set in different ways.
		// HACK - sometimes we don't know the page name in advance, but
		// we still need to set a title here for testing permissions.
		if ( $is_embedded ) {
			// If this is an embedded form (probably a 'RunQuery'),
			// just use the name of the actual page we're on.
			global $wgTitle;
			$this->mPageTitle = $wgTitle;
		} elseif ( $is_query ) {
			// We're in Special:RunQuery - just use that as the
			// title.
			global $wgTitle;
			$this->mPageTitle = $wgTitle;
		} elseif ( $page_name === '' || $page_name === null ) {
			$this->mPageTitle = Title::newFromText(
				$wgRequest->getVal( 'namespace' ) . ":Page Forms permissions test" );
		} else {
			$this->mPageTitle = Title::newFromText( $page_name );
		}

		if ( $user === null ) {
			$user = RequestContext::getMain()->getUser();
		}

		global $wgOut;
		// Show previous set of deletions for this page, if it's been
		// deleted before.
		if ( !$form_submitted &&
			( $this->mPageTitle && !$this->mPageTitle->exists() &&
			$page_name_formula === null )
		) {
			$this->showDeletionLog( $wgOut );
		}
		// Unfortunately, we can't just call userCan() or its
		// equivalent here because it seems to ignore the setting
		// "$wgEmailConfirmToEdit = true;". Instead, we'll just get the
		// permission errors from the start, and use those to determine
		// whether the page is editable.
		if ( !$is_query ) {
			if ( class_exists( 'MediaWiki\Permissions\PermissionManager' ) ) {
				// MW 1.33+
				$permissionErrors = MediaWikiServices::getInstance()->getPermissionManager()
					->getPermissionErrors( 'edit', $user, $this->mPageTitle );
			} else {
				$permissionErrors = $this->mPageTitle->getUserPermissionsErrors( 'edit', $user );
			}
			// The handling of $wgReadOnly and $wgReadOnlyFile
			// has to be done separately.
			if ( wfReadOnly() ) {
				$permissionErrors = [ [ 'readonlytext', [ wfReadOnlyReason() ] ] ];
			}
			$userCanEditPage = count( $permissionErrors ) == 0;
			Hooks::run( 'PageForms::UserCanEditPage', [ $this->mPageTitle, &$userCanEditPage ] );
		}

		// Start off with a loading spinner - this will be removed by
		// the JavaScript once everything has finished loading.
		$form_text = self::displayLoadingImage();
		if ( $is_query || $userCanEditPage ) {
			$form_is_disabled = false;
			// Show "Your IP address will be recorded" warning if
			// user is anonymous, and it's not a query.
			if ( $user->isAnon() && !$is_query ) {
				// Based on code in MediaWiki's EditPage.php.
				$anonEditWarning = wfMessage( 'anoneditwarning',
					// Log-in link
					'{{fullurl:Special:UserLogin|returnto={{FULLPAGENAMEE}}}}',
					// Sign-up link
					'{{fullurl:Special:UserLogin/signup|returnto={{FULLPAGENAMEE}}}}' )->parse();
				$form_text .= Html::rawElement( 'div', [ 'id' => 'mw-anon-edit-warning', 'class' => 'warningbox' ], $anonEditWarning );
			}
		} else {
			$form_is_disabled = true;
			if ( $wgOut->getTitle() != null ) {
				$wgOut->setPageTitle( wfMessage( 'badaccess' )->text() );
				$wgOut->addWikiTextAsInterface( $wgOut->formatPermissionsErrorMessage( $permissionErrors, 'edit' ) );
				$wgOut->addHTML( "\n<hr />\n" );
			}
		}

		if ( $wgPageFormsShowExpandAllLink ) {
			$form_text .= Html::rawElement( 'p', [ 'id' => 'pf-expand-all' ],
				// @TODO - add an i18n message for this.
				Html::element( 'a', [ 'href' => '#' ], 'Expand all collapsed parts of the form' ) ) . "\n";
		}

		$parser = PFUtils::getParser()->getFreshParser();
		if ( !$parser->getOptions() ) {
			if ( method_exists( $parser, 'setOptions' ) ) {
				// MW 1.35+
				$parser->setOptions( ParserOptions::newFromUser( $user ) );
			} else {
				$parser->Options( ParserOptions::newFromUser( $user ) );
			}
		}
		if ( !$is_embedded || method_exists( $parser, 'setOptions' ) ) {
			// Once support for MW < 1.35 is removed, this check will no longer be necessary.
			// (It might be unnecessary already.)
			$parser->setTitle( $this->mPageTitle );
		}
		// This is needed in order to make sure $parser->mLinkHolders
		// is set.
		$parser->clearState();

		$form_def = PFFormUtils::getFormDefinition( $parser, $form_def, $form_id );

		// Turn form definition file into an array of sections, one for
		// each template definition (plus the first section).
		$form_def_sections = [];
		$start_position = 0;
		$section_start = 0;
		$free_text_was_included = false;
		$preloaded_free_text = null;
		// @HACK - replace the 'free text' standard input with a
		// field declaration to get it to be handled as a field.
		$form_def = str_replace( 'standard input|free text', 'field|#freetext#', $form_def );
		while ( $brackets_loc = strpos( $form_def, "{{{", $start_position ) ) {
			$brackets_end_loc = strpos( $form_def, "}}}", $brackets_loc );
			$bracketed_string = substr( $form_def, $brackets_loc + 3, $brackets_end_loc - ( $brackets_loc + 3 ) );
			$tag_components = PFUtils::getFormTagComponents( $bracketed_string );
			$tag_title = trim( $tag_components[0] );
			if ( $tag_title == 'for template' || $tag_title == 'end template' ) {
				// Create a section for everything up to here
				$section = substr( $form_def, $section_start, $brackets_loc - $section_start );
				$form_def_sections[] = $section;
				$section_start = $brackets_loc;
			}
			$start_position = $brackets_loc + 1;
		}
		// end while
		$form_def_sections[] = trim( substr( $form_def, $section_start ) );

		// Cycle through the form definition file, and possibly an
		// existing article as well, finding template and field
		// declarations and replacing them with form elements, either
		// blank or pre-populated, as appropriate.
		$template_name = null;
		$template = null;
		$tif = null;
		// This array will keep track of all the replaced @<name>@ strings
		$placeholderFields = [];

		for ( $section_num = 0; $section_num < count( $form_def_sections ); $section_num++ ) {
			$start_position = 0;
			// the append is there to ensure that the original
			// array doesn't get modified; is it necessary?
			$section = " " . $form_def_sections[$section_num];

			while ( $brackets_loc = strpos( $section, '{{{', $start_position ) ) {
				$brackets_end_loc = strpos( $section, "}}}", $brackets_loc );
				// For cases with more than 3 ending brackets,
				// take the last 3 ones as the tag end.
				while ( $section[$brackets_end_loc + 3] == "}" ) {
					$brackets_end_loc++;
				}
				$bracketed_string = substr( $section, $brackets_loc + 3, $brackets_end_loc - ( $brackets_loc + 3 ) );
				$tag_components = PFUtils::getFormTagComponents( $bracketed_string );
				if ( count( $tag_components ) == 0 ) {
					continue;
				}
				$tag_title = trim( $tag_components[0] );
				// Checks for forbidden characters
				if ( $tag_title != 'info' ) {
					foreach ( $tag_components as $tag_component ) {
						// Angled brackets could cause a security leak (and should not be necessary).
						if ( strpos( $tag_component, '<' ) !== false && strpos( $tag_component, '>' ) !== false ) {
							throw new MWException(
								'<div class="error">Error in form definition! The following field tag contains forbidden characters:</div>' .
								"\n<pre>" . htmlspecialchars( $section ) . "</pre>"
							);
						}
					}
				}
				// =====================================================
				// for template processing
				// =====================================================
				if ( $tag_title == 'for template' ) {
					if ( $tif ) {
						$previous_template_name = $tif->getTemplateName();
					} else {
						$previous_template_name = '';
					}
					$template_name = str_replace( '_', ' ', $parser->recursiveTagParse( $tag_components[1] ) );
					$is_new_template = ( $template_name != $previous_template_name );
					if ( $is_new_template ) {
						$template = PFTemplate::newFromName( $template_name );
						$tif = PFTemplateInForm::newFromFormTag( $tag_components );
					}
					// Remove template tag.
					$section = substr_replace( $section, '', $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
					// If we are editing a page, and this
					// template can be found more than
					// once in that page, and multiple
					// values are allowed, repeat this
					// section.
					if ( $source_is_page ) {
						$tif->setPageRelatedInfo( $existing_page_content );
						// Get the first instance of
						// this template on the page
						// being edited, even if there
						// are more.
						if ( $tif->pageCallsThisTemplate() ) {
							$tif->setFieldValuesFromPage( $existing_page_content );
							$existing_template_text = $tif->getFullTextInPage();
							// Now remove this template from the text being edited.
							$existing_page_content = $this->strReplaceFirst( $existing_template_text, '', $existing_page_content );
							// If we've found a match in the source
							// page, there's a good chance that this
							// page was created with this form - note
							// that, so we don't send the user a warning.
							$source_page_matches_this_form = true;
						}
					}

					// We get values from the request,
					// regardless of whether the source is the
					// page or a form submit, because even if
					// the source is a page, values can still
					// come from a query string.
					// (Unless it's called from #formredlink.)
					if ( !$is_autocreate ) {
						$tif->setFieldValuesFromSubmit();
					}

					$tif->checkIfAllInstancesPrinted( $form_submitted, $source_is_page );

					if ( !$tif->allInstancesPrinted() ) {
						$wiki_page->addTemplate( $tif );
					}

				// =====================================================
				// end template processing
				// =====================================================
				} elseif ( $tag_title == 'end template' ) {
					if ( count( $tag_components ) > 1 ) {
						throw new MWException( '<div class="error">Error in form definition: \'end template\' tag cannot contain any additional parameters.</div>' );
					}
					if ( $source_is_page ) {
						// Add any unhandled template fields
						// in the page as hidden variables.
						$form_text .= PFFormUtils::unhandledFieldsHTML( $tif );
					}
					// Remove this tag from the $section variable.
					$section = substr_replace( $section, '', $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
					$template = null;
					$tif = null;
				// =====================================================
				// field processing
				// =====================================================
				} elseif ( $tag_title == 'field' ) {
					// If the template is null, that (hopefully)
					// means we're handling the free text field.
					// Make the template a dummy variable.
					if ( $tif == null ) {
						$template = new PFTemplate( null, [] );
						// Get free text from the query string, if it was set.
						if ( $wgRequest->getCheck( 'free_text' ) ) {
							$standard_input = $wgRequest->getArray( 'standard_input', [] );
							$standard_input['#freetext#'] = $wgRequest->getVal( 'free_text' );
							$wgRequest->setVal( 'standard_input', $standard_input );
						}
						$tif = PFTemplateInForm::create( 'standard_input', null, null, null, [] );
						$tif->setFieldValuesFromSubmit();
					}
					// We get the field name both here
					// and in the PFFormField constructor,
					// because PFFormField isn't equipped
					// to deal with the #freetext# hack,
					// among others.
					$field_name = trim( $tag_components[1] );
					$form_field = PFFormField::newFromFormFieldTag( $tag_components, $template, $tif, $form_is_disabled, $user );
					// For special displays, add in the
					// form fields, so we know the data
					// structure.
					if ( ( $tif->getDisplay() == 'table' && ( !$tif->allowsMultiple() || $tif->getInstanceNum() == 0 ) ) ||
						( $tif->getDisplay() == 'spreadsheet' && $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) || ( $tif->getDisplay() == 'calendar' && $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) ) {
						$tif->addField( $form_field );
					}
					$val_modifier = null;
					if ( $is_autocreate ) {
						$values_from_query = $autocreate_query[$tif->getTemplateName()];
						$cur_value = $form_field->getCurrentValue( $values_from_query, $form_submitted, $source_is_page, $tif->allInstancesPrinted(), $val_modifier );
					} else {
						$cur_value = $form_field->getCurrentValue( $tif->getValuesFromSubmit(), $form_submitted, $source_is_page, $tif->allInstancesPrinted(), $val_modifier );
					}
					$delimiter = $form_field->getFieldArg( 'delimiter' );
					if ( $form_field->holdsTemplate() ) {
						$placeholderFields[] = self::placeholderFormat( $tif->getTemplateName(), $field_name );
					}

					if ( $val_modifier !== null ) {
						$page_value = $tif->getValuesFromPage()[$field_name];
					}
					if ( $val_modifier === '+' ) {
						if ( preg_match( "#(,|\^)\s*$cur_value\s*(,|\$)#", $page_value ) === 0 ) {
							if ( trim( $page_value ) !== '' ) {
								// if page_value is empty, simply don't do anything, because then cur_value
								// is already the value it has to be (no delimiter needed).
								$cur_value = $page_value . $delimiter . $cur_value;
							}
						} else {
							$cur_value = $page_value;
						}
						$tif->changeFieldValues( $field_name, $cur_value, $delimiter );
					} elseif ( $val_modifier === '-' ) {
						// get an array of elements to remove:
						$remove = array_map( 'trim', explode( ",", $cur_value ) );
						// process the current value:
						$val_array = array_map( 'trim', explode( $delimiter, $page_value ) );
						// remove element(s) from list
						foreach ( $remove as $rmv ) {
							// go through each element and remove match(es)
							$key = array_search( $rmv, $val_array );
							if ( $key !== false ) {
								unset( $val_array[$key] );
							}
						}
						// Convert modified array back to a comma-separated string value and modify
						$cur_value = implode( ",", $val_array );
						if ( $cur_value === '' ) {
							// HACK: setting an empty string prevents anything from happening at all.
							// set a dummy string that evaluates to an empty string
							$cur_value = '{{subst:lc: }}';
						}
						$tif->changeFieldValues( $field_name, $cur_value, $delimiter );
					}
					// If the user is editing a page, and that page contains a call to
					// the template being processed, get the current field's value
					// from the template call
					if ( $source_is_page && ( $tif->getFullTextInPage() != '' ) && !$form_submitted ) {
						if ( $tif->hasValueFromPageForField( $field_name ) ) {
							// Get value, and remove it,
							// so that at the end we
							// can have a list of all
							// the fields that weren't
							// handled by the form.
							$cur_value = $tif->getAndRemoveValueFromPageForField( $field_name );

							// If the field is a placeholder, the contents of this template
							// parameter should be treated as elements parsed by an another
							// multiple template form.
							// By putting that at the very end of the parsed string, we'll
							// have it processed as a regular multiple template form.
							if ( $form_field->holdsTemplate() ) {
								$existing_page_content .= $cur_value;
							}
						} elseif ( isset( $cur_value ) && !empty( $cur_value ) ) {
							// Do nothing.
						} else {
							$cur_value = '';
						}
					}

					// Handle the free text field.
					if ( $field_name == '#freetext#' ) {
						// If there was no preloading, this will just be blank.
						$preloaded_free_text = $cur_value;
						// Add placeholders for the free text in both the form and
						// the page, using <free_text> tags - once all the free text
						// is known (at the end), it will get substituted in.
						if ( $form_field->isHidden() ) {
							$new_text = Html::hidden( 'pf_free_text', '!free_text!' );
						} else {
							$wgPageFormsTabIndex++;
							$wgPageFormsFieldNum++;
							if ( $cur_value === '' || $cur_value === null ) {
								$default_value = '!free_text!';
							} else {
								$default_value = $cur_value;
							}
							$freeTextInput = new PFTextAreaInput( $input_number = null, $default_value, 'pf_free_text', ( $form_is_disabled || $form_field->isRestricted() ), $form_field->getFieldArgs() );
							$freeTextInput->addJavaScript();
							$new_text = $freeTextInput->getHtmlText();
							if ( $form_field->hasFieldArg( 'edittools' ) ) {
								// borrowed from EditPage::showEditTools()
								$edittools_text = $parser->recursiveTagParse( wfMessage( 'edittools', [ 'content' ] )->text() );

								$new_text .= <<<END
		<div class="mw-editTools">
		$edittools_text
		</div>

END;
							}
						}
						$free_text_was_included = true;
						$wiki_page->addFreeTextSection();
					}

					if ( $tif->getTemplateName() === '' || $field_name == '#freetext#' ) {
						$section = substr_replace( $section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
					} else {
						if ( is_array( $cur_value ) ) {
							// @TODO - is this code ever called?
							$delimiter = $form_field->getFieldArg( 'is_list' );
							// first, check if it's a list
							if ( array_key_exists( 'is_list', $cur_value ) &&
									$cur_value['is_list'] == true ) {
								$cur_value_in_template = "";
								foreach ( $cur_value as $key => $val ) {
									if ( $key !== "is_list" ) {
										if ( $cur_value_in_template != "" ) {
											$cur_value_in_template .= $delimiter . " ";
										}
										$cur_value_in_template .= $val;
									}
								}
							} else {
								// If it's not a list, it's probably from a checkbox or date input -
								// convert the values into a string.
								$cur_value_in_template = self::getStringFromPassedInArray( $cur_value, $delimiter );
							}
						} elseif ( $form_field->holdsTemplate() ) {
							// If this field holds an embedded template,
							// and the value is not an array, it means
							// there are no instances of the template -
							// set the value to null to avoid getting
							// whatever is currently on the page.
							$cur_value_in_template = null;
						} else {
							// value is not an array.
							$cur_value_in_template = $cur_value;
						}

						// If we're creating the page name from a formula based on
						// form values, see if the current input is part of that formula,
						// and if so, substitute in the actual value.
						if ( $form_submitted && $generated_page_name !== '' ) {
							// This line appears to be unnecessary.
							// $generated_page_name = str_replace('.', '_', $generated_page_name);
							$generated_page_name = str_replace( ' ', '_', $generated_page_name );
							$escaped_input_name = str_replace( ' ', '_', $form_field->getInputName() );
							$generated_page_name = str_ireplace( "<$escaped_input_name>", $cur_value_in_template, $generated_page_name );
							// Once the substitution is done, replace underlines back
							// with spaces.
							$generated_page_name = str_replace( '_', ' ', $generated_page_name );
						}

						if ( $cur_value !== '' &&
							( $form_field->hasFieldArg( 'mapping template' ) ||
							$form_field->hasFieldArg( 'mapping property' ) ||
							( $form_field->hasFieldArg( 'mapping cargo table' ) &&
							$form_field->hasFieldArg( 'mapping cargo field' ) ) ||
							$form_field->getUseDisplayTitle() ) ) {
							// If the input type is "tokens', the value is not
							// an array, but the delimiter still needs to be set.
							if ( !is_array( $cur_value ) ) {
								if ( $form_field->isList() ) {
									$delimiter = $form_field->getFieldArg( 'delimiter' );
								} else {
									$delimiter = null;
								}
							}
							$cur_value = $form_field->valueStringToLabels( $cur_value, $delimiter );
						}

						// Call hooks - unfortunately this has to be split into two
						// separate calls, because of the different variable names in
						// each case.
						// @TODO - should it be $cur_value for both cases? Or should the
						// hook perhaps modify both variables?
						if ( $form_submitted ) {
							Hooks::run( 'PageForms::CreateFormField', [ &$form_field, &$cur_value_in_template, true ] );
						} else {
							$this->createFormFieldTranslateTag( $template, $tif, $form_field, $cur_value );
							Hooks::run( 'PageForms::CreateFormField', [ &$form_field, &$cur_value, false ] );
						}
						// if this is not part of a 'multiple' template, increment the
						// global tab index (used for correct tabbing)
						if ( !$form_field->hasFieldArg( 'part_of_multiple' ) ) {
							$wgPageFormsTabIndex++;
						}
						// increment the global field number regardless
						$wgPageFormsFieldNum++;
						if ( $source_is_page && !$tif->allInstancesPrinted() ) {
							// If the source is a page, don't use the default
							// values - except for newly-added instances of a
							// multiple-instance template.
						// If the field is a date field, and its default value was set
						// to 'now', and it has no current value, set $cur_value to be
						// the current date.
						} elseif ( $form_field->getDefaultValue() == 'now' &&
								// if the date is hidden, cur_value will already be set
								// to the default value
								( $cur_value == '' || $cur_value == 'now' ) ) {
							$input_type = $form_field->getInputType();
							// We don't handle the 'datepicker' and 'datetimepicker'
							// input types here, because they have their own
							// formatting; instead, they handle 'now' themselves.
							if ( $input_type == 'date' || $input_type == 'datetime' ||
									$input_type == 'year' ||
									( $input_type == '' && $form_field->getTemplateField()->getPropertyType() == '_dat' ) ) {
								$cur_value_in_template = self::getStringForCurrentTime( $input_type == 'datetime', $form_field->hasFieldArg( 'include timezone' ) );
							}
						// If the field is a text field, and its default value was set
						// to 'current user', and it has no current value, set $cur_value
						// to be the current user.
						} elseif ( $form_field->getDefaultValue() == 'current user' &&
							// if the input is hidden, cur_value will already be set
							// to the default value
							( $cur_value === '' || $cur_value == 'current user' )
						) {
							if ( method_exists( $user, 'isRegistered' ) ) {
								// MW 1.34+
								$cur_value_in_template = $user->isRegistered() ? $user->getName() : '';
							} else {
								$cur_value_in_template = $user->getName();
							}
							$cur_value = $cur_value_in_template;
						// UUID is the only default value (so far) that can also be set
						// by the JavaScript, for multiple-instance templates - for the
						// other default values, there's no real need to have a
						// different value for each instance.
						} elseif ( $form_field->getDefaultValue() == 'uuid' &&
							( $cur_value == '' || $cur_value == 'uuid' )
						) {
							if ( $tif->allowsMultiple() ) {
								// Will be set by the JS.
								$form_field->setFieldArg( 'class', 'new-uuid' );
							} else {
								$cur_value = $cur_value_in_template = self::generateUUID();
							}
						}

						// If all instances have been
						// printed, that means we're
						// now printing a "starter"
						// div - set the current value
						// to null, unless it's the
						// default value.
						// (Ideally it wouldn't get
						// set at all, but that seems a
						// little harder.)
						if ( $tif->allInstancesPrinted() && $form_field->getDefaultValue() == null ) {
							$cur_value = null;
						}

						$new_text = $this->formFieldHTML( $form_field, $cur_value );
						$new_text .= $form_field->additionalHTMLForInput( $cur_value, $field_name, $tif->getTemplateName() );

						if ( $new_text ) {
							$wiki_page->addTemplateParam( $template_name, $tif->getInstanceNum(), $field_name, $cur_value_in_template );
							$section = substr_replace( $section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
							$start_position = $brackets_loc + strlen( $new_text );
						} else {
							$start_position = $brackets_end_loc;
						}
					}

					if ( $tif->allowsMultiple() && !$tif->allInstancesPrinted() ) {
						$wordForYes = PFUtils::getWordForYesOrNo( true );
						if ( $form_field->getInputType() == 'checkbox' ) {
							if ( strtolower( $cur_value ) == strtolower( $wordForYes ) || strtolower( $cur_value ) == 'yes' || $cur_value == '1' ) {
								$cur_value = true;
							} else {
								$cur_value = false;
							}
						}
					}

					if ( $tif->getDisplay() != null && ( !$tif->allowsMultiple() || !$tif->allInstancesPrinted() ) ) {
						$tif->addGridValue( $field_name, $cur_value );
					}

				// =====================================================
				// standard input processing
				// =====================================================
				} elseif ( $tag_title == 'standard input' ) {
					// handle all the possible values
					$input_name = $tag_components[1];
					$input_label = null;
					$attr = [];

					// if it's a query, ignore all standard inputs except run query
					if ( ( $is_query && $input_name != 'run query' ) || ( !$is_query && $input_name == 'run query' ) ) {
						$new_text = "";
						$section = substr_replace( $section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
						continue;
					}
					// set a flag so that the standard 'form bottom' won't get displayed
					$this->standardInputsIncluded = true;
					// cycle through the other components
					$is_checked = false;
					for ( $i = 2; $i < count( $tag_components ); $i++ ) {
						$component = $tag_components[$i];
						$sub_components = array_map( 'trim', explode( '=', $component ) );
						if ( count( $sub_components ) == 1 ) {
							if ( $sub_components[0] == 'checked' ) {
								$is_checked = true;
							}
						} elseif ( count( $sub_components ) == 2 ) {
							switch ( $sub_components[0] ) {
							case 'label':
								$input_label = $parser->recursiveTagParse( $sub_components[1] );
								break;
							case 'class':
								$attr['class'] = $sub_components[1];
								break;
							case 'style':
								$attr['style'] = Sanitizer::checkCSS( $sub_components[1] );
								break;
							}
						}
					}
					if ( $input_name == 'summary' ) {
						$value = $wgRequest->getVal( 'wpSummary' );
						$new_text = PFFormUtils::summaryInputHTML( $form_is_disabled, $input_label, $attr, $value );
					} elseif ( $input_name == 'minor edit' ) {
						$is_checked = $wgRequest->getCheck( 'wpMinoredit' );
						$new_text = PFFormUtils::minorEditInputHTML( $form_submitted, $form_is_disabled, $is_checked, $input_label, $attr );
					} elseif ( $input_name == 'watch' ) {
						$is_checked = $wgRequest->getCheck( 'wpWatchthis' );
						$new_text = PFFormUtils::watchInputHTML( $form_submitted, $form_is_disabled, $is_checked, $input_label, $attr );
					} elseif ( $input_name == 'save' ) {
						$new_text = PFFormUtils::saveButtonHTML( $form_is_disabled, $input_label, $attr );
					} elseif ( $input_name == 'save and continue' ) {
						// Remove save and continue button in one-step-process
						if ( $this->mPageTitle == $page_name ) {
							$new_text = PFFormUtils::saveAndContinueButtonHTML( $form_is_disabled, $input_label, $attr );
						} else {
							$new_text = '';
						}
					} elseif ( $input_name == 'preview' ) {
						$new_text = PFFormUtils::showPreviewButtonHTML( $form_is_disabled, $input_label, $attr );
					} elseif ( $input_name == 'changes' ) {
						$new_text = PFFormUtils::showChangesButtonHTML( $form_is_disabled, $input_label, $attr );
					} elseif ( $input_name == 'cancel' ) {
						$new_text = PFFormUtils::cancelLinkHTML( $form_is_disabled, $input_label, $attr );
					} elseif ( $input_name == 'run query' ) {
						$new_text = PFFormUtils::runQueryButtonHTML( $form_is_disabled, $input_label, $attr );
					}
					$section = substr_replace( $section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
				// =====================================================
				// for section processing
				// =====================================================
				} elseif ( $tag_title == 'section' ) {
					$wgPageFormsFieldNum++;
					$wgPageFormsTabIndex++;

					$section_name = trim( $tag_components[1] );
					$page_section_in_form = PFPageSection::newFromFormTag( $tag_components, $user );
					$section_text = null;

					// Split the existing page contents into the textareas in the form.
					$default_value = "";
					$section_start_loc = 0;
					if ( $source_is_page && $existing_page_content !== null ) {
						// For the last section of the page, there is no trailing newline in
						// $existing_page_content, but the code below expects it. This code
						// ensures that there is always trailing newline. T72202
						if ( substr( $existing_page_content, -1 ) !== "\n" ) {
							$existing_page_content .= "\n";
						}

						$equalsSigns = str_repeat( '=', $page_section_in_form->getSectionLevel() );
						$searchStr =
							'/^' .
							preg_quote( $equalsSigns, '/' ) .
							'[ ]*?' .
							preg_quote( $section_name, '/' ) .
							'[ ]*?' .
							preg_quote( $equalsSigns, '/' ) .
							'$/m';
						if ( preg_match( $searchStr, $existing_page_content, $matches, PREG_OFFSET_CAPTURE ) ) {
							$section_start_loc = $matches[0][1];
							$header_text = $matches[0][0];
							$existing_page_content = str_replace( $header_text, '', $existing_page_content );
						} else {
							$section_start_loc = 0;
						}
						$section_end_loc = -1;

						// get the position of the next template or section defined in the form which is not empty and hidden if empty
						$previous_brackets_end_loc = $brackets_end_loc;
						$next_section_found = false;
						// loop until the next section is found
						while ( !$next_section_found ) {
							$next_bracket_start_loc = strpos( $section, '{{{', $previous_brackets_end_loc );
							if ( $next_bracket_start_loc == false ) {
								$section_end_loc = strpos( $existing_page_content, '{{', $section_start_loc );
								$next_section_found = true;
							} else {
								$next_bracket_end_loc = strpos( $section, '}}}', $next_bracket_start_loc );
								$bracketed_string_next_section = substr( $section, $next_bracket_start_loc + 3, $next_bracket_end_loc - ( $next_bracket_start_loc + 3 ) );
								$tag_components_next_section = PFUtils::getFormTagComponents( $bracketed_string_next_section );
								$page_next_section_in_form = PFPageSection::newFromFormTag( $tag_components_next_section, $user );
								$tag_title_next_section = trim( $tag_components_next_section[0] );
								if ( $tag_title_next_section == 'section' ) {
									// There is no pattern match for the next section if the section is empty and its hideIfEmpty attribute is set
									if ( preg_match( '/(^={1,6}[ ]*?' . preg_quote( $tag_components_next_section[1], '/' ) . '[ ]*?={1,6}\s*?$)/m', $existing_page_content, $matches, PREG_OFFSET_CAPTURE ) ) {
										$section_end_loc = $matches[0][1];
										$next_section_found = true;
									// Check for the next section if no pattern match
									} elseif ( $page_next_section_in_form->isHideIfEmpty() ) {
										$previous_brackets_end_loc = $next_bracket_end_loc;
									} else {
										// If none of the above conditions is satisfied, exit the loop.
										break;
									}
								} else {
									$next_section_found = true;
								}
							}
						}

						if ( $section_end_loc === -1 || $section_end_loc === null ) {
							$section_text = substr( $existing_page_content, $section_start_loc );
							$existing_page_content = substr( $existing_page_content, 0, $section_start_loc );
						} else {
							$section_text = substr( $existing_page_content, $section_start_loc, $section_end_loc - $section_start_loc );
							$existing_page_content = substr( $existing_page_content, 0, $section_start_loc ) . substr( $existing_page_content, $section_end_loc );
						}
					}

					// If input is from the form.
					if ( ( !$source_is_page ) && $wgRequest ) {
						$text_per_section = $wgRequest->getArray( '_section' );

						if ( is_array( $text_per_section ) && array_key_exists( $section_name, $text_per_section ) ) {
							$section_text = $text_per_section[$section_name];
						} else {
							$section_text = '';
						}
						// $section_options will allow to pass additional options in the future without breaking backword compatibility
						$section_options = [ 'hideIfEmpty' => $page_section_in_form->isHideIfEmpty() ];
						$wiki_page->addSection( $section_name, $page_section_in_form->getSectionLevel(), $section_text, $section_options );
					}

					$section_text = trim( $section_text );

					// Set input name for query string.
					$input_name = '_section' . '[' . $section_name . ']';
					$other_args = $page_section_in_form->getSectionArgs();
					$other_args['isSection'] = true;
					if ( $page_section_in_form->isMandatory() ) {
						$other_args['mandatory'] = true;
					}

					if ( $page_section_in_form->isHidden() ) {
						$form_section_text = Html::hidden( $input_name, $section_text );
					} else {
						$sectionInput = new PFTextAreaInput( $wgPageFormsFieldNum, $section_text, $input_name, ( $form_is_disabled || $page_section_in_form->isRestricted() ), $other_args );
						$sectionInput->addJavaScript();
						$form_section_text = $sectionInput->getHtmlText();
					}

					$section = substr_replace( $section, $form_section_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
				// =====================================================
				// page info processing
				// =====================================================
				} elseif ( $tag_title == 'info' ) {
					// TODO: Generate an error message if this is included more than once
					foreach ( array_slice( $tag_components, 1 ) as $component ) {
						$sub_components = array_map( 'trim', explode( '=', $component, 2 ) );
						// Tag names are case-insensitive
						$tag = strtolower( $sub_components[0] );
						if ( $tag == 'create title' || $tag == 'add title' ) {
							// Handle this only if
							// we're adding a page.
							if ( !$is_query && !$this->mPageTitle->exists() ) {
								$form_page_title = $sub_components[1];
							}
						} elseif ( $tag == 'edit title' ) {
							// Handle this only if
							// we're editing a page.
							if ( !$is_query && $this->mPageTitle->exists() ) {
								$form_page_title = $sub_components[1];
							}
						} elseif ( $tag == 'query title' ) {
							// Handle this only if
							// we're in 'RunQuery'.
							if ( $is_query ) {
								$form_page_title = $sub_components[1];
							}
						} elseif ( $tag == 'includeonly free text' || $tag == 'onlyinclude free text' ) {
							$wiki_page->makeFreeTextOnlyInclude();
						} elseif ( $tag == 'query form at top' ) {
							// TODO - this should be made a field of
							// some non-static class that actually
							// prints the form, instead of requiring
							// a global variable.
							global $wgPageFormsRunQueryFormAtTop;
							$wgPageFormsRunQueryFormAtTop = true;
						}
					}
					// Replace the {{{info}}} tag with a hidden span, instead of a blank, to avoid a
					// potential security issue.
					$section = substr_replace( $section, '<span style="visibility: hidden;"></span>', $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
				// =====================================================
				// default outer level processing
				// =====================================================
				} else {
					// Tag is not one of the allowed values -
					// ignore it, other than to HTML-escape it.
					$form_section_text = htmlspecialchars( substr( $section, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc ) );
					$section = substr_replace( $section, $form_section_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc );
					$start_position = $brackets_end_loc;
				}
				// end if
			}
			// end while

			if ( $tif && ( !$tif->allowsMultiple() || $tif->allInstancesPrinted() ) ) {
				$template_text = $wiki_page->createTemplateCallsForTemplateName( $tif->getTemplateName() );
				// Escape the '$' characters for the preg_replace() call.
				$template_text = str_replace( '$', '\$', $template_text );

				// If there is a placeholder in the text, we
				// know that we are doing a replace.
				if ( $existing_page_content && strpos( $existing_page_content, '{{{insertionpoint}}}', 0 ) !== false ) {
					$existing_page_content = preg_replace( '/\{\{\{insertionpoint\}\}\}(\r?\n?)/',
						preg_replace( '/\}\}/m', '}�',
							preg_replace( '/\{\{/m', '�{', $template_text ) ) .
						"{{{insertionpoint}}}",
						$existing_page_content );
				}
			}

			$multipleTemplateHTML = '';
			if ( $tif ) {
				if ( $tif->getLabel() != null ) {
					$fieldsetStartHTML = "<fieldset>\n" . Html::element( 'legend', null, $tif->getLabel() ) . "\n";
					$fieldsetStartHTML .= $tif->getIntro();
					if ( !$tif->allowsMultiple() ) {
						$form_text .= $fieldsetStartHTML;
					} elseif ( $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) {
						$multipleTemplateHTML .= $fieldsetStartHTML;
					}
				} else {
					if ( !$tif->allowsMultiple() ) {
						$form_text .= $tif->getIntro();
					}
					if ( $tif->allowsMultiple() && $tif->getInstanceNum() == 0 ) {
						$multipleTemplateHTML .= $tif->getIntro();
					}
				}
			}
			if ( $tif && $tif->allowsMultiple() ) {
				if ( $tif->getDisplay() == 'spreadsheet' ) {
					if ( $tif->allInstancesPrinted() ) {
						$multipleTemplateHTML .= $this->spreadsheetHTML( $tif );
						// For spreadsheets, this needs
						// to be specially inserted.
						if ( $tif->getLabel() != null ) {
							$multipleTemplateHTML .= "</fieldset>\n";
						}
					}
				} elseif ( $tif->getDisplay() == 'calendar' ) {
					if ( $tif->allInstancesPrinted() ) {
						global $wgPageFormsCalendarParams, $wgPageFormsCalendarValues;
						global $wgPageFormsScriptPath;
						$text = '';
						$params = [];
						foreach ( $tif->getFields() as $formField ) {
							$templateField = $formField->template_field;
							$inputType = $formField->getInputType();
							$values = [ 'name' => $templateField->getFieldName() ];
							if ( $formField->getLabel() !== null ) {
								$values['title'] = $formField->getLabel();
							}
							$possibleValues = $formField->getPossibleValues();
							if ( $inputType == 'textarea' ) {
								$values['type'] = 'textarea';
							} elseif ( $inputType == 'datetime' ) {
								$values['type'] = 'datetime';
							} elseif ( $inputType == 'checkbox' ) {
								$values['type'] = 'checkbox';
							} elseif ( $inputType == 'checkboxes' ) {
								$values['type'] = 'checkboxes';
							} elseif ( $inputType == 'listbox' ) {
								$values['type'] = 'listbox';
							} elseif ( $inputType == 'date' ) {
								$values['type'] = 'date';
							} elseif ( $inputType == 'rating' ) {
								$values['type'] = 'rating';
							} elseif ( $inputType == 'radiobutton' ) {
								$values['type'] = 'radiobutton';
							} elseif ( $inputType == 'tokens' ) {
								$values['type'] = 'tokens';
							} elseif ( $possibleValues != null ) {
								array_unshift( $possibleValues, '' );
								$completePossibleValues = [];
								foreach ( $possibleValues as $value ) {
									$completePossibleValues[] = [ 'Name' => $value, 'Id' => $value ];
								}
								$values['type'] = 'select';
								$values['items'] = $completePossibleValues;
								$values['valueField'] = 'Id';
								$values['textField'] = 'Name';
							} else {
								$values['type'] = 'text';
							}
							$params[] = $values;
						}
						$templateName = $tif->getTemplateName();
						$templateDivID = str_replace( ' ', '_', $templateName ) . "FullCalendar";
						$templateDivAttrs = [
							'class' => 'pfFullCalendarJS',
							'id' => $templateDivID,
							'template-name' => $templateName,
							'title-field' => $tif->getEventTitleField(),
							'event-date-field' => $tif->getEventDateField(),
							'event-start-date-field' => $tif->getEventStartDateField(),
							'event-end-date-field' => $tif->getEventEndDateField()
						];
						$loadingImage = Html::element( 'img', [ 'src' => "$wgPageFormsScriptPath/skins/loading.gif" ] );
						$text = "<div id='fullCalendarLoading1' style='display: none;'>" . $loadingImage . "</div>";
						$text .= Html::rawElement( 'div', $templateDivAttrs, $text );
						$wgPageFormsCalendarParams[$templateName] = $params;
						$wgPageFormsCalendarValues[$templateName] = $tif->getGridValues();
						$fullForm = $this->multipleTemplateInstanceHTML( $tif, $form_is_disabled, $section );
						$multipleTemplateHTML .= $text;
						$multipleTemplateHTML .= "</fieldset>\n";
						PFFormUtils::setGlobalVarsForSpreadsheet();
					}
				} else {
					if ( $tif->getDisplay() == 'table' ) {
						$section = $this->tableHTML( $tif, $tif->getInstanceNum() );
					}
					if ( $tif->getInstanceNum() == 0 ) {
						$multipleTemplateHTML .= $this->multipleTemplateStartHTML( $tif );
					}
					if ( !$tif->allInstancesPrinted() ) {
						$multipleTemplateHTML .= $this->multipleTemplateInstanceHTML( $tif, $form_is_disabled, $section );
					} else {
						$multipleTemplateHTML .= $this->multipleTemplateEndHTML( $tif, $form_is_disabled, $section );
					}
				}
				$placeholder = $tif->getPlaceholder();
				if ( $placeholder == null ) {
					// The normal process.
					$form_text .= $multipleTemplateHTML;
				} else {
					// The template text won't be appended
					// at the end of the template like for
					// usual multiple template forms.
					// The HTML text will instead be stored in
					// the $multipleTemplateHTML variable,
					// and then added in the right
					// @insertHTML_".$placeHolderField."@"; position
					// Optimization: actually, instead of
					// separating the processes, the usual
					// multiple template forms could also be
					// handled this way if a fitting
					// placeholder tag was added.
					// We replace the HTML into the current
					// placeholder tag, but also add another
					// placeholder tag, to keep track of it.
					$multipleTemplateHTML .= self::makePlaceholderInFormHTML( $placeholder );
					$form_text = str_replace( self::makePlaceholderInFormHTML( $placeholder ), $multipleTemplateHTML, $form_text );
				}
				if ( !$tif->allInstancesPrinted() ) {
					// This will cause the section to be
					// re-parsed on the next go.
					$section_num--;
					$tif->incrementInstanceNum();
				}
			} elseif ( $tif && $tif->getDisplay() == 'table' ) {
				$form_text .= $this->tableHTML( $tif, 0 );
			} elseif ( $tif && !$tif->allowsMultiple() && $tif->getLabel() != null ) {
				$form_text .= $section . "\n</fieldset>";
			} else {
				$form_text .= $section;
			}
		}
		// end for

		// Cleanup - everything has been browsed.
		// Remove all the remaining placeholder
		// tags in the HTML and wiki-text.
		foreach ( $placeholderFields as $stringToReplace ) {
			// Remove the @<insertHTML>@ tags from the generated
			// HTML form.
			$form_text = str_replace( self::makePlaceholderInFormHTML( $stringToReplace ), '', $form_text );
		}

		// If it wasn't included in the form definition, add the
		// 'free text' input as a hidden field at the bottom.
		if ( !$free_text_was_included ) {
			$form_text .= Html::hidden( 'pf_free_text', '!free_text!' );
		}
		// Get free text, and add to page data, as well as retroactively
		// inserting it into the form.

		if ( $source_is_page ) {
			// If the page is the source, free_text will just be
			// whatever in the page hasn't already been inserted
			// into the form.
			$free_text = trim( $existing_page_content );
		// ...or get it from the form submission, if it's not called from #formredlink
		} elseif ( !$is_autocreate && $wgRequest->getCheck( 'pf_free_text' ) ) {
			$free_text = $wgRequest->getVal( 'pf_free_text' );
			if ( !$free_text_was_included ) {
				$wiki_page->addFreeTextSection();
			}
		} elseif ( $preloaded_free_text != null ) {
			$free_text = $preloaded_free_text;
		} else {
			$free_text = null;
		}

		if ( $wiki_page->freeTextOnlyInclude() ) {
			$free_text = str_replace( "<onlyinclude>", '', $free_text );
			$free_text = str_replace( "</onlyinclude>", '', $free_text );
			$free_text = trim( $free_text );
		}

		$page_text = '';

		Hooks::run( 'PageForms::BeforeFreeTextSubst',
			[ &$free_text, $existing_page_content, &$page_text ] );

		// Now that we have the free text, we can create the full page
		// text.
		// The page text needs to be created whether or not the form
		// was submitted, in case this is called from #formredlink.
		$wiki_page->setFreeText( $free_text );
		$page_text = $wiki_page->createPageText();

		// Also substitute the free text into the form.
		$escaped_free_text = Sanitizer::safeEncodeAttribute( $free_text );
		$form_text = str_replace( '!free_text!', $escaped_free_text, $form_text );

		// Add a warning in, if we're editing an existing page and that
		// page appears to not have been created with this form.
		if ( !$is_query && $page_name_formula === null &&
			$this->mPageTitle->exists() && $existing_page_content !== ''
			&& !$source_page_matches_this_form ) {
			$form_text = "\t" . '<div class="warningbox">' .
				// Prepend with a colon in case it's a file or category page.
				wfMessage( 'pf_formedit_formwarning', ':' . $page_name )->parse() .
				"</div>\n<br clear=\"both\" />\n" . $form_text;
		}

		// Add form bottom, if no custom "standard inputs" have been defined.
		if ( !$this->standardInputsIncluded ) {
			if ( $is_query ) {
				$form_text .= PFFormUtils::queryFormBottom();
			} else {
				$form_text .= PFFormUtils::formBottom( $form_submitted, $form_is_disabled );
			}
		}

		if ( !$is_query ) {
			$form_text .= Html::hidden( 'wpStarttime', wfTimestampNow() );
			// This variable is called $mwWikiPage and not
			// something simpler, to avoid confusion with the
			// variable $wiki_page, which is of type PFWikiPage.
			$mwWikiPage = WikiPage::factory( $this->mPageTitle );
			$form_text .= Html::hidden( 'wpEdittime', $mwWikiPage->getTimestamp() );
			$form_text .= Html::hidden( 'editRevId', 0 );
			$form_text .= Html::hidden( 'wpEditToken', $user->getEditToken() );
			$form_text .= Html::hidden( 'wpUnicodeCheck', EditPage::UNICODE_CHECK );
			$form_text .= Html::hidden( 'wpUltimateParam', true );
		}

		$form_text .= "\t</form>\n";
		$parser->replaceLinkHolders( $form_text );
		Hooks::run( 'PageForms::RenderingEnd', [ &$form_text ] );

		// Send the autocomplete values to the browser, along with the
		// mappings of which values should apply to which fields.
		// If doing a replace, the page text is actually the modified
		// original page.
		if ( !$is_embedded ) {
			$form_page_title = $parser->recursiveTagParse( str_replace( "{{!}}", "|", $form_page_title ) );
		} else {
			$form_page_title = null;
		}

		return [ $form_text, $page_text, $form_page_title, $generated_page_name ];
	}

	/**
	 * Create the HTML to display this field within a form.
	 * @param PFFormField $form_field
	 * @param string $cur_value
	 * @return string
	 */
	function formFieldHTML( $form_field, $cur_value ) {
		global $wgPageFormsFieldNum;

		// Also get the actual field, with all the semantic information
		// (type is PFTemplateField, instead of PFFormField)
		$template_field = $form_field->getTemplateField();
		$class_name = null;

		if ( $form_field->isHidden() ) {
			$attribs = [];
			if ( $form_field->hasFieldArg( 'class' ) ) {
				$attribs['class'] = $form_field->getFieldArg( 'class' );
			}
			$text = Html::hidden( $form_field->getInputName(), $cur_value, $attribs );
		} elseif ( $form_field->getInputType() !== '' &&
				array_key_exists( $form_field->getInputType(), $this->mInputTypeHooks ) &&
				$this->mInputTypeHooks[$form_field->getInputType()] != null ) {
			// Last argument to constructor should be a hash,
			// merging the default values for this input type with
			// all other properties set in the form definition, plus
			// some semantic-related arguments.
			$hook_values = $this->mInputTypeHooks[$form_field->getInputType()];
			$class_name = $hook_values[0];
			$other_args = $form_field->getArgumentsForInputCall( $hook_values[1] );
		} else {
			// The input type is not defined in the form.
			$cargo_field_type = $template_field->getFieldType();
			$property_type = $template_field->getPropertyType();
			$is_list = ( $form_field->isList() || $template_field->isList() );
			if ( $cargo_field_type !== '' &&
				array_key_exists( $cargo_field_type, $this->mCargoTypeHooks ) &&
				isset( $this->mCargoTypeHooks[$cargo_field_type][$is_list] ) ) {
				$hook_values = $this->mCargoTypeHooks[$cargo_field_type][$is_list];
				$class_name = $hook_values[0];
				$other_args = $form_field->getArgumentsForInputCall( $hook_values[1] );
			} elseif ( $property_type !== '' &&
				array_key_exists( $property_type, $this->mSemanticTypeHooks ) &&
				isset( $this->mSemanticTypeHooks[$property_type][$is_list] ) ) {
				$hook_values = $this->mSemanticTypeHooks[$property_type][$is_list];
				$class_name = $hook_values[0];
				$other_args = $form_field->getArgumentsForInputCall( $hook_values[1] );
			} else {
				// Anything else.
				$class_name = 'PFTextInput';
				$other_args = $form_field->getArgumentsForInputCall();
				// Set default size for list inputs.
				if ( $form_field->isList() ) {
					if ( !array_key_exists( 'size', $other_args ) ) {
						$other_args['size'] = 100;
					}
				}
			}
		}

		if ( $class_name !== null ) {
			$form_input = new $class_name( $wgPageFormsFieldNum, $cur_value, $form_field->getInputName(), $form_field->isDisabled(), $other_args );

			// If a regex was defined, make this a "regexp" input that wraps
			// around the real one.
			if ( $template_field->getRegex() !== null ) {
				$other_args['regexp'] = $template_field->getRegex();
				$form_input = PFRegExpInput::newFromInput( $form_input );
			}
			$form_input->addJavaScript();
			$text = $form_input->getHtmlText();
		}

		$this->addTranslatableInput( $form_field, $cur_value, $text );
		return $text;
	}

	function isTranslateEnabled() {
		return class_exists( 'SpecialTranslate' );
	}

	/**
	 * for translatable fields, this function add an hidden input containing the translate tags
	 *
	 * @param PFFormField $form_field
	 * @param string $cur_value
	 * @param string &$text
	 */
	private function addTranslatableInput( $form_field, $cur_value, &$text ) {
		if ( !$this->isTranslateEnabled() || !$form_field->hasFieldArg( 'translatable' ) || !$form_field->getFieldArg( 'translatable' ) ) {
			return;
		}

		if ( $form_field->hasFieldArg( 'translate_number_tag' ) ) {
			$inputName = $form_field->getInputName();
			$pattern = '/\[([^\\]\\]]+)\]$/';
			if ( preg_match( $pattern, $inputName, $matches ) ) {
				$inputName = preg_replace( $pattern, '[${1}_translate_number_tag]', $inputName );
			} else {
				$inputName .= '_translate_number_tag';
			}
			$translateTag = $form_field->getFieldArg( 'translate_number_tag' );
			$text .= "<input type='hidden' name='$inputName' value='$translateTag'/>";
		}
	}

	private function createFormFieldTranslateTag( &$template, &$tif, &$form_field, &$cur_value ) {
		if ( !$this->isTranslateEnabled() || !$form_field->hasFieldArg( 'translatable' ) || !$form_field->getFieldArg( 'translatable' ) ) {
			return;
		}

		// If translatable, add translatable tags when saving, or remove them for displaying form.
		if ( preg_match( '#^<translate>(.*)</translate>$#', $cur_value, $matches ) ) {
			$cur_value = $matches[1];
		} elseif ( substr( $cur_value, 0, strlen( '<translate>' ) ) == '<translate>'
				&& substr( $cur_value, -1 * strlen( '</translate>' ) ) == '</translate>' ) {
			// For unknown reasons, the pregmatch regex does not work every time !! :(
			$cur_value = substr( $cur_value, strlen( '<translate>' ), -1 * strlen( '</translate>' ) );
		}

		if ( substr( $cur_value, 0, 6 ) == '<!--T:' ) {
			// hide the tag <!-- T:X --> in another input
			// if field does not use VisualEditor?

			if ( preg_match( "/<!-- *T:([a-zA-Z0-9]+) *-->( |\n)/", $cur_value, $matches ) ) {
				// Remove the tag from this input.
				$cur_value = str_replace( $matches[0], '', $cur_value );
				// Add a field arg, to add a hidden input in form with the tag.
				$form_field->setFieldArg( 'translate_number_tag', $matches[0] );
			}
		}
	}

	private static function generateUUID() {
		// Copied from https://www.php.net/manual/en/function.uniqid.php#94959
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			// 16 bits for "time_mid"
			mt_rand( 0, 0xffff ),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand( 0, 0x0fff ) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand( 0, 0x3fff ) | 0x8000,
			// 48 bits for "node"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

}
