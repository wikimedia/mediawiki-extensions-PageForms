<?php
/**
 * Static functions for Page Forms, for use by the Page Schemas
 * extension.
 *
 * @author Yaron Koren
 * @author Ankit Garg
 * @file
 * @ingroup PF
 */

class PFPageSchemas extends PSExtensionHandler {
	public static function registerClass() {
		global $wgPageSchemasHandlerClasses;
		$wgPageSchemasHandlerClasses[] = 'PFPageSchemas';
		return true;
	}

	/**
	 * Creates an object to hold form-wide information, based on an XML
	 * object from the Page Schemas extension.
	 * @param string $tagName
	 * @param SimpleXMLElement $xml
	 * @return string[]|null
	 */
	public static function createPageSchemasObject( $tagName, $xml ) {
		$pfarray = [];

		if ( $tagName == "standardInputs" ) {
			foreach ( $xml->children() as $_ => $child ) {
				foreach ( $child->children() as $tag => $formelem ) {
					if ( $tag == $tagName ) {
						foreach ( $formelem->attributes() as $attr => $name ) {
							$pfarray[$attr] = (string)$formelem->attributes()->$attr;
						}
					}
				}
				return $pfarray;
			}
		}

		if ( $tagName == "pageforms_Form" ) {
			foreach ( $xml->children() as $tag => $child ) {
				if ( $tag == $tagName ) {
					$formName = (string)$child->attributes()->name;
					$pfarray['name'] = $formName;
					foreach ( $child->children() as $childTag => $formelem ) {
						$pfarray[$childTag] = (string)$formelem;
					}
					return $pfarray;
				}
			}
		}
		if ( $tagName == "pageforms_TemplateDetails" ) {
			foreach ( $xml->children() as $tag => $child ) {
				if ( $tag == $tagName ) {
					foreach ( $child->children() as $childTag => $formelem ) {
						$pfarray[$childTag] = (string)$formelem;
					}
					return $pfarray;
				}
			}
		}
		if ( $tagName == "pageforms_FormInput" || $tagName == "pageforms_PageSection" ) {
			foreach ( $xml->children() as $tag => $child ) {
				if ( $tag == $tagName ) {
					foreach ( $child->children() as $prop ) {
						if ( $prop->getName() == 'InputType' ) {
							$pfarray[$prop->getName()] = (string)$prop;
						} else {
							if ( (string)$prop->attributes()->name == '' ) {
								$pfarray[$prop->getName()] = (string)$prop;
							} else {
								$pfarray[(string)$prop->attributes()->name] = (string)$prop;
							}
						}
					}
					return $pfarray;
				}
			}
		}
		return null;
	}

	/**
	 * Creates Page Schemas XML for form-wide information.
	 * @return string
	 */
	public static function createSchemaXMLFromForm() {
		global $wgRequest;

		// Quick check: if the "form name" field hasn't been sent,
		// it means the main "Form" checkbox wasn't selected; don't
		// create any XML if so.
		if ( !$wgRequest->getCheck( 'pf_form_name' ) ) {
			return '';
		}

		$formName = null;
		$xml = '';
		$includeFreeText = false;
		foreach ( $wgRequest->getValues() as $var => $val ) {
			$val = str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $val );
			if ( $var == 'pf_form_name' ) {
				$formName = $val;
			} elseif ( $var == 'pf_page_name_formula' ) {
				if ( !empty( $val ) ) {
					$val = Xml::escapeTagsOnly( $val );
					$xml .= '<PageNameFormula>' . $val . '</PageNameFormula>';
				}
			} elseif ( $var == 'pf_create_title' ) {
				if ( !empty( $val ) ) {
					$xml .= '<CreateTitle>' . $val . '</CreateTitle>';
				}
			} elseif ( $var == 'pf_edit_title' ) {
				if ( !empty( $val ) ) {
					$xml .= '<EditTitle>' . $val . '</EditTitle>';
				}
			} elseif ( $var == 'pf_fi_free_text' && !empty( $val ) ) {
				$includeFreeText = true;
				$xml .= '<standardInputs inputFreeText="1" ';
			} elseif ( $includeFreeText && $var == 'pf_fi_free_text_label' ) {
				if ( !empty( $val ) ) {
					$xml .= 'freeTextLabel="' . Xml::escapeTagsOnly( $val ) . '" ';
				}
			}
		}
		if ( $includeFreeText ) {
			$xml .= ' />';
		}
		$xml = '<pageforms_Form name="' . $formName . '" >' . $xml;
		$xml .= '</pageforms_Form>';
		return $xml;
	}

	/**
	 * Creates Page Schemas XML from form information on templates.
	 * @return string[]
	 */
	public static function createTemplateXMLFromForm() {
		global $wgRequest;

		$xmlPerTemplate = [];
		$templateNum = -1;
		$xml = '';
		foreach ( $wgRequest->getValues() as $var => $val ) {
			$val = str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $val );
			if ( substr( $var, 0, 18 ) === 'pf_template_label_' ) {
				$templateNum = substr( $var, 18 );
				$xml = '<pageforms_TemplateDetails>';
				if ( !empty( $val ) ) {
					$xml .= "<Label>$val</Label>";
				}
			} elseif ( substr( $var, 0, 23 ) === 'pf_template_addanother_' ) {
				if ( !empty( $val ) ) {
					$xml .= "<AddAnotherText>$val</AddAnotherText>";
				}
				$xml .= '</pageforms_TemplateDetails>';
				$xmlPerTemplate[$templateNum] = $xml;
			}
		}
		return $xmlPerTemplate;
	}

	/**
	 * Creates Page Schemas XML for form fields.
	 * @return string[]
	 */
	public static function createFieldXMLFromForm() {
		global $wgRequest;

		$xmlPerField = [];
		$fieldNum = -1;
		$xml = '';
		foreach ( $wgRequest->getValues() as $var => $val ) {
			$val = str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $val );
			if ( substr( $var, 0, 14 ) === 'pf_input_type_' ) {
				$fieldNum = substr( $var, 14 );
				$xml = '<pageforms_FormInput>';
				if ( !empty( $val ) ) {
					$xml .= '<InputType>' . $val . '</InputType>';
				}
			} elseif ( substr( $var, 0, 14 ) === 'pf_key_values_' ) {
				$xml .= self::createFormInputXMLFromForm( $val );
			} elseif ( substr( $var, 0, 14 ) === 'pf_input_befo_' ) {
				if ( $val !== '' ) {
					$xml .= '<TextBeforeField>' . $val . '</TextBeforeField>';
				}
			} elseif ( substr( $var, 0, 14 ) === 'pf_input_desc_' ) {
				if ( $val !== '' ) {
					$xml .= '<Description>' . $val . '</Description>';
				}
			} elseif ( substr( $var, 0, 18 ) === 'pf_input_desctool_' ) {
				if ( $val !== '' ) {
					$xml .= '<DescriptionTooltipMode>' . $val . '</DescriptionTooltipMode>';
				}
			} elseif ( substr( $var, 0, 16 ) === 'pf_input_finish_' ) {
				// This is a hack.
				$xml .= '</pageforms_FormInput>';
				$xmlPerField[$fieldNum] = $xml;
			}
		}
		return $xmlPerField;
	}

	/**
	 * Creates Page Schemas XML for page sections
	 * @return string[]
	 */
	public static function createPageSectionXMLFromForm() {
		global $wgRequest;
		$xmlPerPageSection = [];
		$pageSectionNum = -1;

		foreach ( $wgRequest->getValues() as $var => $val ) {
			$val = str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $val );
			if ( substr( $var, 0, 26 ) == 'pf_pagesection_key_values_' ) {
				$pageSectionNum = substr( $var, 26 );
				$xml = "";
				if ( $val != '' ) {
					$xml = '<pageforms_PageSection>';
					$xml .= self::createFormInputXMLFromForm( $val );
					$xml .= '</pageforms_PageSection>';
				}
				$xmlPerPageSection[$pageSectionNum] = $xml;
			}
		}
		return $xmlPerPageSection;
	}

	static function createFormInputXMLFromForm( $valueFromForm ) {
		$xml = '';
		if ( $valueFromForm !== '' ) {
			// replace the comma substitution character that has no chance of
			// being included in the values list - namely, the ASCII beep
			$listSeparator = ',';
			$key_values_str = str_replace( "\\$listSeparator", "\a", $valueFromForm );
			$key_values_array = explode( $listSeparator, $key_values_str );
			foreach ( $key_values_array as $value ) {
			// replace beep back with comma, trim
				$value = str_replace( "\a", $listSeparator, trim( $value ) );
				$param_value = explode( "=", $value, 2 );
				if ( count( $param_value ) == 2 && $param_value[1] != null ) {
					// Handles <Parameter name="size">20</Parameter>
					$xml .= '<Parameter name="' . $param_value[0] . '">' . $param_value[1] . '</Parameter>';
				} else {
					// Handles <Parameter name="mandatory" />
					$xml .= '<Parameter name="' . $param_value[0] . '"/>';
				}
			}
		}
		return $xml;
	}

	public static function getDisplayColor() {
		return '#CF9';
	}

	public static function getSchemaDisplayString() {
		return 'Form';
	}

	public static function getSchemaEditingHTML( $pageSchemaObj ) {
		$form_array = [];
		$hasExistingValues = false;
		if ( $pageSchemaObj !== null ) {
			$form_array = $pageSchemaObj->getObject( 'pageforms_Form' );
			if ( $form_array !== null ) {
				$hasExistingValues = true;
			}
		}

		// Get all the values from the page schema.
		$formName = PageSchemas::getValueFromObject( $form_array, 'name' );
		$pageNameFormula = PageSchemas::getValueFromObject( $form_array, 'PageNameFormula' );
		$createTitle = PageSchemas::getValueFromObject( $form_array, 'CreateTitle' );
		$editTitle = PageSchemas::getValueFromObject( $form_array, 'EditTitle' );

		// Inputs
		if ( $pageSchemaObj !== null ) {
			$standardInputs = $pageSchemaObj->getObject( 'standardInputs' );
			$includeFreeText = isset( $standardInputs['inputFreeText'] ) ? $standardInputs['inputFreeText'] : false;
		} else {
			$includeFreeText = true;
		}

		$freeTextLabel = html_entity_decode( PageSchemas::getValueFromObject( $form_array, 'freeTextLabel' ) );

		$text = "\t<p>" . wfMessage( 'ps-namelabel' )->escaped() . ' ' . Html::input( 'pf_form_name', $formName, 'text', [ 'size' => 15 ] ) . "</p>\n";
		// The checkbox isn't actually a field in the page schema -
		// we set it based on whether or not a page formula has been
		// specified.
		$twoStepProcessAttrs = [ 'id' => 'pf-two-step-process' ];
		if ( $pageNameFormula === null ) {
			$twoStepProcessAttrs['checked'] = true;
		}
		$text .= '<p>' . Html::input( 'pf_two_step_process', null, 'checkbox', $twoStepProcessAttrs );
		$text .= ' Users must enter the page name before getting to the form (default)';
		$text .= "</p>\n";
		$text .= '<div class="editSchemaMinorFields">';
		$text .= "\t<p id=\"pf-page-name-formula\">" . wfMessage( 'pf-pageschemas-pagenameformula' )->escaped() . ' ' . Html::input( 'pf_page_name_formula', $pageNameFormula, 'text', [ 'size' => 30 ] ) . "</p>\n";
		$text .= "\t<p>" . wfMessage( 'pf-pageschemas-createtitle' )->escaped() . ' ' . Html::input( 'pf_create_title', $createTitle, 'text', [ 'size' => 25 ] ) . "</p>\n";
		$text .= "\t<p id=\"pf-edit-title\">" . wfMessage( 'pf-pageschemas-edittitle' )->escaped() . ' ' . Html::input( 'pf_edit_title', $editTitle, 'text', [ 'size' => 25 ] ) . "</p>\n";

		// This checkbox went from a default of false to true in PF 5.2.
		$text .= '<p>';
		$text .= Html::input( 'pf_fi_free_text', '1', 'checkbox', [ 'id' => 'pf_fi_free_text', 'checked' => $includeFreeText ] );
		$text .= Html::rawElement( 'label', [ 'for' => 'pf_fi_free_text' ], 'Include free text input' );
		$text .= "</p>";

		$text .= "Free text label: " . Html::input( 'pf_fi_free_text_label', ( ( empty( $freeTextLabel ) ) ? wfMessage( 'pf_form_freetextlabel' )->inContentLanguage()->text() : $freeTextLabel ), 'text' ) . "</p><p>";

		$text .= "</div>\n";

		global $wgOut;
		// Separately, add Javascript for getting the checkbox to
		// hide certain fields.
		$wgOut->addModules( [ 'ext.pageforms.PF_PageSchemas' ] );

		return [ $text, $hasExistingValues ];
	}

	public static function getTemplateEditingHTML( $psTemplate ) {
		$hasExistingValues = false;
		$templateLabel = null;
		$addAnotherText = null;
		if ( $psTemplate !== null ) {
			$form_array = $psTemplate->getObject( 'pageforms_TemplateDetails' );
			if ( $form_array !== null ) {
				$hasExistingValues = true;
				$templateLabel = PageSchemas::getValueFromObject( $form_array, 'Label' );
				$addAnotherText = PageSchemas::getValueFromObject( $form_array, 'AddAnotherText' );
			}
		}

		$text = "\t<p>" . "The following fields are useful if there can be multiple instances of this template." . "</p>\n";
		$text .= "\t<p>" . wfMessage( 'exif-label' )->escaped() . ': ' . Html::input( 'pf_template_label_num', $templateLabel, 'text', [ 'size' => 15 ] ) . "</p>\n";
		$text .= "\t<p>" . 'Text of button to add another instance (default is "Add another"):' . ' ' . Html::input( 'pf_template_addanother_num', $addAnotherText, 'text', [ 'size' => 25 ] ) . "</p>\n";

		return [ $text, $hasExistingValues ];
	}

	/**
	 * Returns the HTML for inputs to define a single form field,
	 * within the Page Schemas 'edit schema' page.
	 * @param PSTemplateField $psField
	 * @return array
	 */
	public static function getFieldEditingHTML( $psField ) {
		$fieldValues = [];
		$hasExistingValues = false;
		$inputType = null;
		$inputDesc = null;
		$inputDescTooltipMode = null;
		$inputBeforeText = null;
		if ( $psField !== null ) {
			$fieldValues = $psField->getObject( 'pageforms_FormInput' );
			if ( $fieldValues !== null ) {
				$hasExistingValues = true;
				$inputType = PageSchemas::getValueFromObject( $fieldValues, 'InputType' );
				$inputDesc = PageSchemas::getValueFromObject( $fieldValues, 'Description' );
				$inputDescTooltipMode = PageSchemas::getValueFromObject( $fieldValues, 'DescriptionTooltipMode' );
				$inputBeforeText = PageSchemas::getValueFromObject( $fieldValues, 'TextBeforeField' );
			} else {
				$fieldValues = [];
			}
		}

		global $wgPageFormsFormPrinter;
		$possibleInputTypes = $wgPageFormsFormPrinter->getAllInputTypes();
		$inputTypeDropdownHTML = Html::element( 'option', null, null );
		foreach ( $possibleInputTypes as $possibleInputType ) {
			$inputTypeOptionAttrs = [];
			if ( $possibleInputType == $inputType ) {
				$inputTypeOptionAttrs['selected'] = true;
			}
			$inputTypeDropdownHTML .= Html::element( 'option', $inputTypeOptionAttrs, $possibleInputType ) . "\n";
		}
		$inputTypeDropdown = Html::rawElement( 'select', [ 'name' => 'pf_input_type_num' ], $inputTypeDropdownHTML );
		$text = '<p>' . wfMessage( 'pf-pageschemas-inputtype' )->escaped() . ' ' . $inputTypeDropdown . '</p>';

		$text .= "\t" . '<p>' . wfMessage( 'pf-pageschemas-otherparams', 'size=20, mandatory' )->escaped() . '</p>' . "\n";
		$paramValues = [];
		foreach ( $fieldValues as $param => $value ) {
			if ( !empty( $param ) && $param != 'InputType' && $param != 'Description' && $param != 'DescriptionTooltipMode' && $param != 'TextBeforeField' ) {
				if ( !empty( $value ) ) {
					$paramValues[] = $param . '=' . $value;
				} else {
					$paramValues[] = $param;
				}
			}
		}
		foreach ( $paramValues as $i => $paramAndVal ) {
			$paramValues[$i] = str_replace( ',', '\,', $paramAndVal );
		}
		$param_value_str = implode( ', ', $paramValues );
		$inputParamsAttrs = [ 'size' => 80 ];
		$inputParamsInput = Html::input( 'pf_key_values_num', $param_value_str, 'text', $inputParamsAttrs );
		$text .= "\t<p>$inputParamsInput</p>\n";

		$text .= '<div class="editSchemaMinorFields">' . "\n";
		$inputBeforeTextPrint = Html::input( 'pf_input_befo_num', $inputBeforeText, 'text', [ 'size' => 80 ] );
		$text .= "\t<p>Text that will be printed before the field: $inputBeforeTextPrint</p>\n";

		$inputDescriptionLabel = wfMessage( 'pf-pageschemas-inputdescription' )->parse();
		$inputDescription = Html::input( 'pf_input_desc_num', $inputDesc, 'text', [ 'size' => 80 ] );
		$inputDescriptionTooltipMode = Html::input( 'pf_input_desctool_num', $inputDescTooltipMode, 'checkbox', [ 'checked' => ( $inputDescTooltipMode ) ? 'checked' : null ] );
		$text .= "\t<p>$inputDescriptionLabel $inputDescription<br>$inputDescriptionTooltipMode Show description as pop-up tooltip</p>\n";

		// @HACK to make input parsing easier.
		$text .= Html::hidden( 'pf_input_finish_num', 1 );

		$text .= "</div>\n";

		return [ $text, $hasExistingValues ];
	}

	public static function getPageSectionEditingHTML( $psPageSection ) {
		$otherParams = [];

		if ( $psPageSection !== null ) {
			$otherParams = $psPageSection->getObject( 'pageforms_PageSection' );
		}
		$paramValues = [];
		if ( $otherParams !== null ) {
			foreach ( $otherParams as $param => $value ) {
				if ( !empty( $param ) ) {
					if ( !empty( $value ) ) {
						$paramValues[] = $param . '=' . $value;
					} else {
						$paramValues[] = $param;
					}
				}
			}
		}

		foreach ( $paramValues as $i => $paramAndVal ) {
			$paramValues[$i] = str_replace( ',', '\,', $paramAndVal );
		}
		$param_value_str = implode( ', ', $paramValues );
		$text = "\t" . '<p>' . wfMessage( 'pf-pageschemas-otherparams', 'rows=10, mandatory' )->escaped() . '</p>' . "\n";
		$inputParamsInput = Html::input( 'pf_pagesection_key_values_num', $param_value_str, 'text', [ 'size' => 80 ] );
		$text .= "\t<p>$inputParamsInput</p>\n";

		return $text;
	}

	public static function getFormName( $pageSchemaObj ) {
		$mainFormInfo = self::getMainFormInfo( $pageSchemaObj );
		if ( $mainFormInfo === null || !array_key_exists( 'name', $mainFormInfo ) ) {
			return null;
		}
		return $mainFormInfo['name'];
	}

	public static function getMainFormInfo( $pageSchemaObj ) {
		// return $pageSchemaObj->getObject( 'pageforms_Form' );
		// We don't just call getObject() here, because sometimes, for
		// some reason, this gets called before PF registers itself
		// with Page Schemas, which means that getObject() would return
		// null. Instead, we directly call the code that would have
		// been called.
		$xml = $pageSchemaObj->getXML();
		foreach ( $xml->children() as $tag => $child ) {
			if ( $tag == "pageforms_Form" ) {
				$pfarray = [];
				$formName = (string)$child->attributes()->name;
				$pfarray['name'] = $formName;
				foreach ( $child->children() as $childTag => $formelem ) {
					if ( $childTag == "standardInputs" ) {
						foreach ( $formelem->attributes() as $attr => $value ) {
							$pfarray[$attr] = (string)$formelem->attributes()->$attr;
						}
					} else {
						$pfarray[$childTag] = (string)$formelem;
					}
				}
				return $pfarray;
			}
		}
		return [];
	}

	public static function getFormFieldInfo( $psTemplate, $template_fields ) {
		$form_fields = [];
		$fieldsInfo = $psTemplate->getFields();
		foreach ( $fieldsInfo as $i => $psField ) {
			$fieldFormArray = $psField->getObject( 'pageforms_FormInput' );
			if ( $fieldFormArray === null ) {
				continue;
			}
			$formField = PFFormField::create( $template_fields[$i] );
			foreach ( $fieldFormArray as $var => $val ) {
				if ( $var == 'InputType' ) {
					$formField->setInputType( $val );
				} elseif ( $var == 'mandatory' ) {
					$formField->setIsMandatory( true );
				} elseif ( $var == 'hidden' ) {
					$formField->setIsHidden( true );
				} elseif ( $var == 'restricted' ) {
					$formField->setIsRestricted( true );
				} elseif ( in_array( $var, [ 'Description', 'DescriptionTooltipMode', 'TextBeforeField' ] ) ) {
					$formField->setDescriptionArg( $var, $val );
				} else {
					$formField->setFieldArg( $var, $val );
				}
			}
			$form_fields[] = $formField;
		}
		return $form_fields;
	}

	public static function getPageSection( $psPageSection ) {
		$pageSection = PFPageSection::create( $psPageSection->getSectionName() );
		$pageSectionArray = $psPageSection->getObject( 'pageforms_PageSection' );
		if ( $pageSectionArray == null ) {
			return null;
		}

		foreach ( $pageSectionArray as $var => $val ) {
			if ( $var == 'mandatory' ) {
				$pageSection->setIsMandatory( true );
			} elseif ( $var == 'hidden' ) {
				$pageSection->setIsHidden( true );
			} elseif ( $var == 'restricted' ) {
				$pageSection->setIsRestricted( true );
			} else {
				$pageSection->setSectionArgs( $var, $val );
			}
		}

		return $pageSection;
	}

	/**
	 * Return the list of pages that Page Forms could generate from
	 * the current Page Schemas schema.
	 * @param PFPageSchemas $pageSchemaObj
	 * @return Title[]
	 */
	public static function getPagesToGenerate( $pageSchemaObj ) {
		$genPageList = [];
		$psTemplates = $pageSchemaObj->getTemplates();
		foreach ( $psTemplates as $psTemplate ) {
			$title = Title::makeTitleSafe( NS_TEMPLATE, $psTemplate->getName() );
			$genPageList[] = $title;
		}
		$form_name = self::getFormName( $pageSchemaObj );
		if ( $form_name != null ) {
			$title = Title::makeTitleSafe( PF_NS_FORM, $form_name );
			$genPageList[] = $title;
		}

		return $genPageList;
	}

	/**
	 * Returns an array of PFTemplateField objects, representing the fields
	 * of a template, based on the contents of a <PageSchema> tag.
	 * @param PFTemplate $psTemplate
	 * @return PFTemplateField[]
	 */
	public static function getFieldsFromTemplateSchema( $psTemplate ) {
		$psFields = $psTemplate->getFields();
		$templateFields = [];
		foreach ( $psFields as $psField ) {
			if ( defined( 'SMW_VERSION' ) ) {
				$prop_array = $psField->getObject( 'semanticmediawiki_Property' );
				$propertyName = PageSchemas::getValueFromObject( $prop_array, 'name' );
				if ( $prop_array !== null && empty( $propertyName ) ) {
					$propertyName = $psField->getName();
				}
			} else {
				$propertyName = null;
			}

			if ( $psField->getLabel() === '' ) {
				$fieldLabel = $psField->getName();
			} else {
				$fieldLabel = $psField->getLabel();
			}
			$templateField = PFTemplateField::create(
				$psField->getName(),
				$fieldLabel,
				$propertyName,
				$psField->isList(),
				$psField->getDelimiter(),
				$psField->getDisplay()
			);
			$templateField->setNamespace( $psField->getNamespace() );
			if ( defined( 'CARGO_VERSION' ) ) {
				$cargoFieldArray = $psField->getObject( 'cargo_Field' );
				$fieldType = PageSchemas::getValueFromObject( $cargoFieldArray, 'Type' );
				$allowedValues = PageSchemas::getValueFromObject( $cargoFieldArray, 'AllowedValues' );
				if ( $fieldType != '' ) {
					$templateField->setFieldType( $fieldType );
					$templateField->setPossibleValues( $allowedValues );
				}
			}

			$templateFields[] = $templateField;
		}
		return $templateFields;
	}

	/**
	 * Creates a form page, when called from the 'generatepages' page
	 * of Page Schemas.
	 * @param string $formName
	 * @param Title $formTitle
	 * @param array $formItems
	 * @param array $formDataFromSchema
	 * @param string $categoryName
	 */
	public static function generateForm( $formName, $formTitle,
		$formItems, $formDataFromSchema, $categoryName ) {
		$includeFreeText = array_key_exists( 'inputFreeText', $formDataFromSchema );
		$freeTextLabel = null;
		if ( $includeFreeText && array_key_exists( 'freeTextLabel', $formDataFromSchema ) ) {
			$freeTextLabel = $formDataFromSchema['freeTextLabel'];
		}

		$form = PFForm::create( $formName, $formItems );
		$form->setAssociatedCategory( $categoryName );
		if ( array_key_exists( 'PageNameFormula', $formDataFromSchema ) ) {
			$form->setPageNameFormula( $formDataFromSchema['PageNameFormula'] );
		}
		if ( array_key_exists( 'CreateTitle', $formDataFromSchema ) ) {
			$form->setCreateTitle( $formDataFromSchema['CreateTitle'] );
		}
		if ( array_key_exists( 'EditTitle', $formDataFromSchema ) ) {
			$form->setEditTitle( $formDataFromSchema['EditTitle'] );
		}

		$user = RequestContext::getMain()->getUser();

		$formContents = $form->createMarkup( $includeFreeText, $freeTextLabel );
		$params = [];
		$params['user_id'] = $user->getId();
		$params['page_text'] = $formContents;
		$job = new PSCreatePageJob( $formTitle, $params );

		$jobs = [ $job ];
		JobQueueGroup::singleton()->push( $jobs );
	}

	/**
	 * Generate pages (form and templates) specified in the list.
	 * @param PageSchemas $pageSchemaObj
	 * @param array $selectedPages
	 */
	public static function generatePages( $pageSchemaObj, $selectedPages ) {
		if ( $selectedPages == null ) {
			return;
		}

		$user = RequestContext::getMain()->getUser();

		$psFormItems = $pageSchemaObj->getFormItemsList();
		$form_items = [];
		$jobs = [];
		$templateHackUsed = false;
		$isCategoryNameSet = false;

		// Generate every specified template
		foreach ( $psFormItems as $psFormItem ) {
			if ( $psFormItem['type'] == 'Template' ) {
				$psTemplate = $psFormItem['item'];
				$templateName = $psTemplate->getName();
				$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $templateName );
				$fullTemplateName = PageSchemas::titleString( $templateTitle );
				$template_fields = self::getFieldsFromTemplateSchema( $psTemplate );
				// Get property for use in either #set_internal
				// or #subobject, defined by either SIO's or
				// SMW's Page Schemas portion. We don't need
				// to record which one it came from, because
				// PF's code to generate the template runs its
				// own, similar check.
				// @TODO - $internalObjProperty should probably
				// have a more generic name.
				if ( class_exists( 'SIOPageSchemas' ) ) {
					$internalObjProperty = SIOPageSchemas::getInternalObjectPropertyName( $psTemplate );
				} elseif ( method_exists( 'SMWPageSchemas', 'getConnectingPropertyName' ) ) {
					$internalObjProperty = SMWPageSchemas::getConnectingPropertyName( $psTemplate );
				} else {
					$internalObjProperty = null;
				}
				// TODO - actually, the category-setting should be
				// smarter than this: if there's more than one
				// template in the schema, it should probably be only
				// the first non-multiple template that includes the
				// category tag.
				if ( $psTemplate->isMultiple() ) {
					$categoryName = null;
				} else {
					if ( $isCategoryNameSet == false ) {
						$categoryName = $pageSchemaObj->getCategoryName();
						$isCategoryNameSet = true;
					} else {
						$categoryName = null;
					}

				}
				if ( method_exists( $psTemplate, 'getFormat' ) ) {
					$templateFormat = $psTemplate->getFormat();
				} else {
					$templateFormat = null;
				}

				$pfTemplate = new PFTemplate( $templateName, $template_fields );
				$pfTemplate->setConnectingProperty( $internalObjProperty );
				$pfTemplate->setCategoryName( $categoryName );
				$pfTemplate->setFormat( $templateFormat );

				// Set Cargo table, if one was set in the schema.
				$cargoArray = $psTemplate->getObject( 'cargo_TemplateDetails' );
				if ( $cargoArray !== null ) {
					$cargoTable = PageSchemas::getValueFromObject( $cargoArray, 'Table' );
					$pfTemplate->setCargoTable( $cargoTable );
				}

				$templateText = $pfTemplate->createText();

				if ( in_array( $fullTemplateName, $selectedPages ) ) {
					$params = [];
					$params['user_id'] = $user->getId();
					$params['page_text'] = $templateText;
					$jobs[] = new PSCreatePageJob( $templateTitle, $params );
					if ( strpos( $templateText, '{{!}}' ) > 0 ) {
						$templateHackUsed = true;
					}
				}

				$templateValues = self::getTemplateValues( $psTemplate );
				if ( array_key_exists( 'Label', $templateValues ) ) {
					$templateLabel = $templateValues['Label'];
				} else {
					$templateLabel = null;
				}
				$form_fields = self::getFormFieldInfo( $psTemplate, $template_fields );
				// Create template info for form, for use in generating
				// the form (if it will be generated).
				$form_template = PFTemplateInForm::create(
					$templateName,
					$templateLabel,
					$psTemplate->isMultiple(),
					null,
					$form_fields
				);
				$form_items[] = [ 'type' => 'template', 'name' => $form_template->getTemplateName(), 'item' => $form_template ];
			} elseif ( $psFormItem['type'] == 'Section' ) {
				$psPageSection = $psFormItem['item'];
				$form_section = self::getPageSection( $psPageSection );
				if ( $form_section !== null ) {
					$form_section->setSectionLevel( $psPageSection->getSectionLevel() );
					$form_items[] = [ 'type' => 'section', 'name' => $form_section->getSectionName(), 'item' => $form_section ];
				}
			}

		}

		// Create the "!" hack template, if it's necessary
		if ( $templateHackUsed ) {
			$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, '!' );
			if ( !$templateTitle->exists() ) {
				$params = [];
				$params['user_id'] = $user->getId();
				$params['page_text'] = '|';
				$jobs[] = new PSCreatePageJob( $templateTitle, $params );
			}
		}

		JobQueueGroup::singleton()->push( $jobs );

		// Create form, if it's specified.
		$formName = self::getFormName( $pageSchemaObj );
		$categoryName = $pageSchemaObj->getCategoryName();
		if ( !empty( $formName ) ) {
			$formInfo = self::getMainFormInfo( $pageSchemaObj );
			$formTitle = Title::makeTitleSafe( PF_NS_FORM, $formName );
			$fullFormName = PageSchemas::titleString( $formTitle );
			if ( in_array( $fullFormName, $selectedPages ) ) {
				self::generateForm( $formName, $formTitle,
					$form_items, $formInfo, $categoryName );
			}
		}
	}

	public static function getSchemaDisplayValues( $schemaXML ) {
		foreach ( $schemaXML->children() as $tag => $child ) {
			if ( $tag == "pageforms_Form" ) {
				$formName = $child->attributes()->name;
				$values = [];
				foreach ( $child->children() as $tagName => $prop ) {
					$values[$tagName] = (string)$prop;
				}
				return [ $formName, $values ];
			}
		}
		return null;
	}

	public static function getTemplateValues( $psTemplate ) {
		// TODO - fix this.
		$values = [];
		if ( $psTemplate instanceof PSTemplate ) {
			$psTemplate = $psTemplate->getXML();
		}
		foreach ( $psTemplate->children() as $tag => $child ) {
			if ( $tag == "pageforms_TemplateDetails" ) {
				foreach ( $child->children() as $prop ) {
					$values[$prop->getName()] = (string)$prop;
				}
			}
		}
		return $values;
	}

	public static function getTemplateDisplayString() {
		return 'Details for template in form';
	}

	/**
	 * Displays form details for one template in the Page Schemas XML.
	 * @param string $templateXML
	 * @return null|array
	 */
	public static function getTemplateDisplayValues( $templateXML ) {
		$templateValues = self::getTemplateValues( $templateXML );
		if ( count( $templateValues ) == 0 ) {
			return null;
		}

		$displayValues = [];
		foreach ( $templateValues as $key => $value ) {
			if ( $key == 'Label' ) {
				$propName = wfMessage( 'exif-label' )->escaped();
			} elseif ( $key == 'AddAnotherText' ) {
				$propName = "'Add another' button";
			}
			$displayValues[$propName] = $value;
		}
		return [ null, $displayValues ];
	}

	public static function getFieldDisplayString() {
		return wfMessage( 'pf-pageschemas-forminput' )->parse();
	}

	public static function getPageSectionDisplayString() {
		return wfMessage( 'ps-otherparams' )->text();
	}

	/**
	 * Displays data on a single form input in the Page Schemas XML.
	 * @param Node $fieldXML
	 * @return array|null
	 */
	public static function getFieldDisplayValues( $fieldXML ) {
		foreach ( $fieldXML->children() as $tag => $child ) {
			if ( $tag == "pageforms_FormInput" ) {
				$inputName = $child->attributes()->name;
				$values = [];
				foreach ( $child->children() as $prop ) {
					if ( $prop->getName() == 'InputType' ) {
						$propName = 'Input type';
					} else {
						$propName = (string)$prop->attributes()->name;
					}
					$values[$propName] = (string)$prop;
				}
				return [ $inputName, $values ];
			}
		}
		return null;
	}

	public static function getPageSectionDisplayValues( $pageSectionXML ) {
		foreach ( $pageSectionXML->children() as $tag => $child ) {
			if ( $tag == "pageforms_PageSection" ) {
				$inputName = $child->attributes()->name;
				$values = [];
				foreach ( $child->children() as $prop ) {
					$propName = (string)$prop->attributes()->name;
					$values[$propName] = (string)$prop;
				}
				return [ $inputName, $values ];
			}
		}
		return null;
	}
}
