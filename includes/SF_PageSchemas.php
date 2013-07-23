<?php
/**
 * Static functions for Semantic Forms, for use by the Page Schemas
 * extension.
 *
 * @author Yaron Koren
 * @author Ankit Garg
 * @file
 * @ingroup SF
 */

class SFPageSchemas extends PSExtensionHandler {
	public static function registerClass() {
		global $wgPageSchemasHandlerClasses;
		$wgPageSchemasHandlerClasses[] = 'SFPageSchemas';
		return true;
	}

	/**
	 * Creates an object to hold form-wide information, based on an XML
	 * object from the Page Schemas extension.
	 */
	public static function createPageSchemasObject( $tagName, $xml ) {
		$sfarray = array();

		if( $tagName == "standardInputs" ) {
			foreach ( $xml->children() as $tag => $child ) {
					foreach ( $child->children() as $tag => $formelem ) {
						if($tag == $tagName) {
							foreach( $formelem->attributes() as $attr => $name) {
								$sfarray[$attr] = (string)$formelem->attributes()->$attr;
							}
						}
					}
					return $sfarray;
			}
		}

		if ( $tagName == "semanticforms_Form" ) {
			foreach ( $xml->children() as $tag => $child ) {
				if ( $tag == $tagName ) {
					$formName = (string)$child->attributes()->name;
					$sfarray['name'] = $formName;
					foreach ( $child->children() as $tag => $formelem ) {
						$sfarray[$tag] = (string)$formelem;
					}
					return $sfarray;
				}
			}
		}
		if ( $tagName == "semanticforms_TemplateDetails" ) {
			foreach ( $xml->children() as $tag => $child ) {
				if ( $tag == $tagName ) {
					foreach ( $child->children() as $tag => $formelem ) {
						$sfarray[$tag] = (string)$formelem;
					}
					return $sfarray;
				}
			}
		}
		if ( $tagName == "semanticforms_FormInput" ) {
			foreach ( $xml->children() as $tag => $child ) {
				if ( $tag == $tagName ) {
					foreach ( $child->children() as $prop ) {
						if ( $prop->getName() == 'InputType' ) {
							$sfarray[$prop->getName()] = (string)$prop;
						} else {
							if ( (string)$prop->attributes()->name == '' ) {
								$sfarray[$prop->getName()] = (string)$prop;
							} else {
							$sfarray[(string)$prop->attributes()->name] = (string)$prop;
						}
					}
					}
					return $sfarray;
				}
			}
		}
		return null;
	}

	/**
	 * Creates Page Schemas XML for form-wide information.
	 */
	public static function createSchemaXMLFromForm() {
		global $wgRequest;

		$formName = null;
		$xml = '';
		$isStandardInputsOpen = false;
		foreach ( $wgRequest->getValues() as $var => $val ) {
			$val = str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $val );
			if ( $var == 'sf_form_name' ) {
				$formName = $val;
			} elseif ( $var == 'sf_page_name_formula' ) {
				if ( !empty( $val ) ) {
					$val = Xml::escapeTagsOnly( $val );
					$xml .= '<PageNameFormula>' . $val . '</PageNameFormula>';
				}
			} elseif ( $var == 'sf_create_title' ) {
				if ( !empty( $val ) ) {
					$xml .= '<CreateTitle>' . $val . '</CreateTitle>';
				}
			} elseif ( $var == 'sf_edit_title' ) {
				if ( !empty( $val ) ) {
					$xml .= '<EditTitle>' . $val . '</EditTitle>';
				}
			} elseif ( $var == 'sf_fi_free_text_label' ) {
				$isStandardInputsOpen = true;
				$xml .= '<standardInputs ';
				if ( !empty( $val ) ) {
					$xml .= 'freeTextLabel="' . Xml::escapeTagsOnly( $val ) . '" ';
				}
			} /* Options */ elseif ( $var == 'sf_fi_free_text' ) {
				if ( !empty( $val ) ) {
					$xml .= 'inputFreeText="' . $val . '" ';
				}
			} elseif ( $var == 'sf_fi_summary' ) {
				if ( !empty( $val ) ) {
					$xml .= 'inputSummary="' . $val . '" ';
				}
			} elseif ( $var == 'sf_fi_minor_edit' ) {
				if ( !empty( $val ) ) {
					$xml .= 'inputMinorEdit="' . $val . '" ';
				}
			} elseif ( $var == 'sf_fi_watch' ) {
				if ( !empty( $val ) ) {
					$xml .= 'inputWatch="' . $val . '" ';
				}
			} elseif ( $var == 'sf_fi_save' ) {
				if ( !empty( $val ) ) {
					$xml .= 'inputSave="' . $val . '" ';
				}
			} elseif ( $var == 'sf_fi_preview' ) {
				if ( !empty( $val ) ) {
					$xml .= 'inputPreview="' . $val . '" ';
				}
			} elseif ( $var == 'sf_fi_changes' ) {
				if ( !empty( $val ) ) {
					$xml .= 'inputChanges="' . $val . '" ';
				}
			} elseif ( $var == 'sf_fi_cancel' ) {
				if ( !empty( $val ) ) {
					$xml .= 'inputCancel="' . $val . '"';
				}
			}
		}
		if($isStandardInputsOpen) {
			$isStandardInputsOpen = false;
			$xml .= ' />';
		}
		$xml = '<semanticforms_Form name="' . $formName . '" >' . $xml;
		$xml .= '</semanticforms_Form>';
		return $xml;
	}

	/**
	 * Creates Page Schemas XML from form information on templates.
	 */
	public static function createTemplateXMLFromForm() {
		global $wgRequest;

		$xmlPerTemplate = array();
		$templateNum = -1;
		foreach ( $wgRequest->getValues() as $var => $val ) {
			$val = str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $val );
			if ( substr( $var, 0, 18 ) == 'sf_template_label_' ) {
				$templateNum = substr( $var, 18 );
				$xml = '<semanticforms_TemplateDetails>';
				if ( !empty( $val ) ) {
					$xml .= "<Label>$val</Label>";
				}
			} elseif ( substr( $var, 0, 23 ) == 'sf_template_addanother_' ) {
				if ( !empty( $val ) ) {
					$xml .= "<AddAnotherText>$val</AddAnotherText>";
				}
				$xml .= '</semanticforms_TemplateDetails>';
				$xmlPerTemplate[$templateNum] = $xml;
			}
		}
		return $xmlPerTemplate;
	}

	/**
	 * Creates Page Schemas XML for form fields.
	 */
	public static function createFieldXMLFromForm() {
		global $wgRequest;

		$xmlPerField = array();
		$fieldNum = -1;
		foreach ( $wgRequest->getValues() as $var => $val ) {
			$val = str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $val );
			if ( substr( $var, 0, 14 ) == 'sf_input_type_' ) {
				$fieldNum = substr( $var, 14 );
				$xml = '<semanticforms_FormInput>';
				if ( !empty( $val ) ) {
					$xml .= '<InputType>' . $val . '</InputType>';
				}
			} elseif ( substr( $var, 0, 14 ) == 'sf_input_desc_' ) {

				if ( $val !== '' ) {
					$xml .= '<Description>' . $val . '</Description>';
				}

			} elseif ( substr( $var, 0, 18 ) == 'sf_input_desctool_' ) {

				if ( $val !== '' ) {
					$xml .= '<DescriptionTooltipMode>' . $val . '</DescriptionTooltipMode>';
				}

			} elseif ( substr( $var, 0, 14 ) == 'sf_input_befo_' ) {

				if ( $val !== '' ) {
					$xml .= '<TextBeforeField>' . $val . '</TextBeforeField>';
				}

			} elseif ( substr( $var, 0, 14 ) == 'sf_key_values_' ) {
				if ( $val !== '' ) {
					// replace the comma substitution character that has no chance of
					// being included in the values list - namely, the ASCII beep
					$listSeparator = ',';
					$key_values_str = str_replace( "\\$listSeparator", "\a", $val );
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
				$xml .= '</semanticforms_FormInput>';
				$xmlPerField[$fieldNum] = $xml;
			}
		}
		return $xmlPerField;
	}

	public static function getDisplayColor() {
		return '#CF9';
	}

	public static function getSchemaDisplayString() {
		return 'Form';
	}

	public static function getSchemaEditingHTML( $pageSchemaObj ) {
		$form_array = array();
		$hasExistingValues = false;
		if ( !is_null( $pageSchemaObj ) ) {
			$form_array = $pageSchemaObj->getObject( 'semanticforms_Form' );
			if ( !is_null( $form_array ) ) {
				$hasExistingValues = true;
			}
		}

		// Get all the values from the page schema.
		$formName = PageSchemas::getValueFromObject( $form_array, 'name' );
		$pageNameFormula = PageSchemas::getValueFromObject( $form_array, 'PageNameFormula' );
		$createTitle = PageSchemas::getValueFromObject( $form_array, 'CreateTitle' );
		$editTitle = PageSchemas::getValueFromObject( $form_array, 'EditTitle' );

		//Inputs
		if ( !is_null( $pageSchemaObj ) ) {
			$standardInputs = $pageSchemaObj->getObject( 'standardInputs' );
		} else {
			$standardInputs = array();
		}

		$freeTextLabel = html_entity_decode( PageSchemas::getValueFromObject( $form_array, 'freeTextLabel' ) );

		$text = "\t<p>" . wfMessage( 'ps-namelabel' )->escaped() . ' ' . Html::input( 'sf_form_name', $formName, 'text', array( 'size' => 15 ) ) . "</p>\n";
		// The checkbox isn't actually a field in the page schema -
		// we set it based on whether or not a page formula has been
		// specified.
		$twoStepProcessAttrs = array( 'id' => 'sf-two-step-process' );
		if ( is_null( $pageNameFormula ) ) {
			$twoStepProcessAttrs['checked'] = true;
		}
		$text .= '<p>' . Html::input( 'sf_two_step_process', null, 'checkbox', $twoStepProcessAttrs );
		$text .= ' Users must enter the page name before getting to the form (default)';
		$text .= "</p>\n";
		$text .= "\t<p id=\"sf-page-name-formula\">" . wfMessage( 'sf-pageschemas-pagenameformula' )->escaped() . ' ' . Html::input( 'sf_page_name_formula', $pageNameFormula, 'text', array( 'size' => 30 ) ) . "</p>\n";
		$text .= "\t<p>" . wfMessage( 'sf-pageschemas-createtitle' )->escaped() . ' ' . Html::input( 'sf_create_title', $createTitle, 'text', array( 'size' => 25 ) ) . "</p>\n";
		$text .= "\t<p id=\"sf-edit-title\">" . wfMessage( 'sf-pageschemas-edittitle' )->escaped() . ' ' . Html::input( 'sf_edit_title', $editTitle, 'text', array( 'size' => 25 ) ) . "</p>\n";

		//Inputs
		$text .= "<p>Define form buttons and inputs (will be enabled all if not selected any): &nbsp;</p><p>";

		$text .= "Free text label: " . Html::input( 'sf_fi_free_text_label', ( ( empty( $freeTextLabel ) ) ? wfMsgForContent( 'sf_form_freetextlabel' ) : $freeTextLabel ), 'text' ) . "</p><p>";

		// Free text
		$text .= '<span>';
		$text .= Html::input( 'sf_fi_free_text', '1', 'checkbox', array( 'id' => 'sf_fi_free_text', 'checked' => (isset($standardInputs['inputFreeText'])) ? $standardInputs['inputFreeText'] : null ) );
		$text .= Html::rawElement( 'label', array( 'for' => 'sf_fi_free_text' ), 'Free text input' );
		$text .= "&nbsp;</span>";
		// Summary
		$text .= '<span>';
		$text .= Html::input( 'sf_fi_summary', '1', 'checkbox', array( 'id' => 'sf_fi_summary', 'checked' => (isset($standardInputs['inputSummary'])) ? $standardInputs['inputSummary'] : null ) );
		$text .= Html::rawElement( 'label', array( 'for' => 'sf_fi_summary' ), 'Summary input' );
		$text .= "&nbsp;</span>";
		// Minor edit
		$text .= '<span>';
		$text .= Html::input( 'sf_fi_minor_edit', '1', 'checkbox', array( 'id' => 'sf_fi_minor_edit', 'checked' => (isset($standardInputs['inputMinorEdit'])) ? $standardInputs['inputMinorEdit'] : null ) );
		$text .= Html::rawElement( 'label', array( 'for' => 'sf_fi_minor_edit' ), 'Minor edit input' );
		$text .= "&nbsp;</span>";
		// Watch
		$text .= '<span>';
		$text .= Html::input( 'sf_fi_watch', '1', 'checkbox', array( 'id' => 'sf_fi_watch', 'checked' => (isset($standardInputs['inputWatch'])) ? $standardInputs['inputWatch'] : null ) );
		$text .= Html::rawElement( 'label', array( 'for' => 'sf_fi_watch' ), 'Watch input' );
		$text .= "&nbsp;</span>";
		// Save
		$text .= '<span>';
		$text .= Html::input( 'sf_fi_save', '1', 'checkbox', array( 'id' => 'sf_fi_save', 'checked' => (isset($standardInputs['inputSave'])) ? $standardInputs['inputSave'] : null ) );
		$text .= Html::rawElement( 'label', array( 'for' => 'sf_fi_save' ), 'Save input' );
		$text .= "&nbsp;</span>";
		// Preview
		$text .= '<span>';
		$text .= Html::input( 'sf_fi_preview', '1', 'checkbox', array( 'id' => 'sf_fi_preview', 'checked' => (isset($standardInputs['inputPreview'])) ? $standardInputs['inputPreview'] : null ) );
		$text .= Html::rawElement( 'label', array( 'for' => 'sf_fi_preview' ), 'Preview input' );
		$text .= "&nbsp;</span>";
		// Changes
		$text .= '<span>';
		$text .= Html::input( 'sf_fi_changes', '1', 'checkbox', array( 'id' => 'sf_fi_changes', 'checked' => (isset($standardInputs['inputChanges'])) ? $standardInputs['inputChanges'] : null ) );
		$text .= Html::rawElement( 'label', array( 'for' => 'sf_fi_changes' ), 'Changes input' );
		$text .= "&nbsp;</span>";
		// Cancel
		$text .= '<span>';
		$text .= Html::input( 'sf_fi_cancel', '1', 'checkbox', array( 'id' => 'sf_fi_cancel', 'checked' => (isset($standardInputs['inputCancel'])) ? $standardInputs['inputCancel'] : null ) );
		$text .= Html::rawElement( 'label', array( 'for' => 'sf_fi_cancel' ), 'Cancel input' );
		$text .= "&nbsp;</span>";

		$text .= "</p>";

		// Separately, add Javascript for getting the checkbox to
		// hide certain fields.
		$jsText = <<<END
<script type="text/javascript">
jQuery.fn.toggleFormDataDisplay = function() {
	if (jQuery(this).is(":checked")) {
		jQuery('#sf-page-name-formula').css('display', 'none');
		jQuery('#sf-edit-title').css('display', 'block');
	} else {
		jQuery('#sf-page-name-formula').css('display', 'block');
		jQuery('#sf-edit-title').css('display', 'none');
	}
}
jQuery('#sf-two-step-process').toggleFormDataDisplay();
jQuery('#sf-two-step-process').click( function() {
	jQuery(this).toggleFormDataDisplay();
} );
</script>

END;
		global $wgOut;
		$wgOut->addScript( $jsText );

		return array( $text, $hasExistingValues );
	}

	public static function getTemplateEditingHTML( $psTemplate ) {
		$hasExistingValues = false;
		$templateLabel = null;
		$addAnotherText = null;
		if ( !is_null( $psTemplate ) ) {
			$form_array = $psTemplate->getObject( 'semanticforms_TemplateDetails' );
			if ( !is_null( $form_array ) ) {
				$hasExistingValues = true;
				$templateLabel = PageSchemas::getValueFromObject( $form_array, 'Label' );
				$addAnotherText = PageSchemas::getValueFromObject( $form_array, 'AddAnotherText' );
			}
		}

		$text = "\t<p>" . "The following fields are useful if there can be multiple instances of this template." . "</p>\n";
		$text .= "\t<p>" . 'Label:' . ' ' . Html::input( 'sf_template_label_num', $templateLabel, 'text', array( 'size' => 15 ) ) . "</p>\n";
		$text .= "\t<p>" . 'Text of button to add another instance (default is "Add another"):' . ' ' . Html::input( 'sf_template_addanother_num', $addAnotherText, 'text', array( 'size' => 25 ) ) . "</p>\n";

		return array( $text, $hasExistingValues );
	}

	/**
	 * Returns the HTML for inputs to define a single form field,
	 * within the Page Schemas 'edit schema' page.
	 */
	public static function getFieldEditingHTML( $psField ) {
		$fieldValues = array();
		$hasExistingValues = false;
		$inputType = null;
		$inputDesc = null;
		$inputDescTooltipMode = null;
		$inputBeforeText = null;
		if ( !is_null( $psField ) ) {
			$fieldValues = $psField->getObject( 'semanticforms_FormInput' );
			if ( !is_null( $fieldValues ) ) {
				$hasExistingValues = true;
				$inputType = PageSchemas::getValueFromObject( $fieldValues, 'InputType' );
				$inputDesc = PageSchemas::getValueFromObject( $fieldValues, 'Description' );
				$inputDescTooltipMode = PageSchemas::getValueFromObject( $fieldValues, 'DescriptionTooltipMode' );
				$inputBeforeText = PageSchemas::getValueFromObject( $fieldValues, 'TextBeforeField' );
			} else {
				$fieldValues = array();
			}
		}

		global $sfgFormPrinter;
		$possibleInputTypes = $sfgFormPrinter->getAllInputTypes();
		$inputTypeDropdownHTML = Html::element( 'option', null, null );
		foreach ( $possibleInputTypes as $possibleInputType ) {
			$inputTypeOptionAttrs = array();
			if ( $possibleInputType == $inputType ) {
				$inputTypeOptionAttrs['selected'] = true;
			}
			$inputTypeDropdownHTML .= Html::element( 'option', $inputTypeOptionAttrs, $possibleInputType ) . "\n";
		}
		$inputTypeDropdown = Html::rawElement( 'select', array( 'name' => 'sf_input_type_num' ), $inputTypeDropdownHTML );
		$text = '<p>' . wfMessage( 'sf-pageschemas-inputtype' )->escaped() . ' ' . $inputTypeDropdown . '</p>';

		$inputBeforeTextPrint = Html::input( 'sf_input_befo_num', $inputBeforeText, 'text', array( 'size' => 80 ) );
		$text .= "\t<p>Enter the text that will be printed before the field:</p>\t<p>$inputBeforeTextPrint</p>\n";

		$inputDescription = Html::input( 'sf_input_desc_num', $inputDesc, 'text', array( 'size' => 80 ) );
		$inputDescriptionTooltipMode = Html::input( 'sf_input_desctool_num', $inputDescTooltipMode, 'checkbox', array( 'checked' => ($inputDescTooltipMode) ? 'checked' : null ) );
		$text .= "\t<p>Enter field <b>description</b>:</p>\t<p>$inputDescription<br>$inputDescriptionTooltipMode Show description as pop-up tooltip</p>\n";

		// @todo FIXME: i18n issue: Hard coded text.
		$text .= "\t" . '<p>Enter parameter names and their values as key=value pairs, separated by commas (if a value contains a comma, replace it with "\,"). For example: size=20, mandatory</p>' . "\n";
		$paramValues = array();
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
		$inputParamsAttrs = array( 'size' => 80 );
		$inputParamsInput = Html::input( 'sf_key_values_num', $param_value_str, 'text', $inputParamsAttrs );
		$text .= "\t<p>$inputParamsInput</p>\n";
		return array( $text, $hasExistingValues );
	}

	public static function getFormName( $pageSchemaObj ) {
		$mainFormInfo = self::getMainFormInfo( $pageSchemaObj );
		if ( is_null( $mainFormInfo ) || !array_key_exists( 'name', $mainFormInfo ) ) {
			return null;
		}
		return $mainFormInfo['name'];
	}

	public static function getMainFormInfo( $pageSchemaObj ) {
		// return $pageSchemaObj->getObject( 'semanticforms_Form' );
		// We don't just call getObject() here, because sometimes, for
		// some reason, this gets called before SF registers itself
		// with Page Schemas, which means that getObject() would return
		// null. Instead, we directly call the code that would have
		// been called.
		$xml = $pageSchemaObj->getXML();
		foreach ( $xml->children() as $tag => $child ) {
			if ( $tag == "semanticforms_Form" ) {
				$sfarray = array();
				$formName = (string)$child->attributes()->name;
				$sfarray['name'] = $formName;
				foreach ( $child->children() as $tag => $formelem ) {
					if($tag == "standardInputs") {
						foreach ( $formelem->attributes() as $attr => $value ) {
							$sfarray[$attr] = (string)$formelem->attributes()->$attr;
						}
					}else{
						$sfarray[$tag] = (string)$formelem;
					}
				}
				return $sfarray;
			}
		}
		return array();
	}

	public static function getFormFieldInfo( $psTemplate, $template_fields ) {
		$form_fields = array();
		$fieldsInfo = $psTemplate->getFields();
		foreach ( $fieldsInfo as $i => $psField ) {
			$fieldFormArray = $psField->getObject( 'semanticforms_FormInput' );
			if ( !is_null( $fieldFormArray ) ) {
				$formField = SFFormField::create( $i, $template_fields[$i] );
				foreach ( $fieldFormArray as $var => $val ) {
					if ( $var == 'InputType' ) {
						$formField->setInputType( $val );
					} elseif ( $var == 'mandatory' ) {
						$formField->setIsMandatory( true );
					} elseif ( $var == 'hidden' ) {
						$formField->setIsHidden( true );
					} elseif ( $var == 'restricted' ) {
						$formField->setIsRestricted( true );
					} else {
						$formField->setFieldArg( $var, $val );
					}
				}
				$form_fields[] = $formField;
			}
		}
		return $form_fields;
	}

	/**
	 * Return the list of pages that Semantic Forms could generate from
	 * the current Page Schemas schema.
	 */
	public static function getPagesToGenerate( $pageSchemaObj ) {
		$psTemplates = $pageSchemaObj->getTemplates();
		foreach ( $psTemplates as $psTemplate ) {
			$title = Title::makeTitleSafe( NS_TEMPLATE, $psTemplate->getName() );
			$genPageList[] = $title;
		}
		$form_name = self::getFormName( $pageSchemaObj );
		if ( $form_name == null ) {
			return array();
		}
		$title = Title::makeTitleSafe( SF_NS_FORM, $form_name );
		$genPageList[] = $title;
		return $genPageList;
	}

	/**
	 * Returns an array of SFTemplateField objects, representing the fields
	 * of a template, based on the contents of a <PageSchema> tag.
	 */
	public static function getFieldsFromTemplateSchema( $psTemplate ) {
		$psFields = $psTemplate->getFields();
		$templateFields = array();
		foreach ( $psFields as $psField ) {
			$prop_array = $psField->getObject( 'semanticmediawiki_Property' );
			$field_options = array();
			wfRunHooks( 'SfFieldsFromTemplateSchema', array( $psField, &$field_options ));
			$propertyName = PageSchemas::getValueFromObject( $prop_array, 'name' );
			if ( !is_null( $prop_array ) && empty( $propertyName ) ) {
				$propertyName = $psField->getName();
			}
			if ( $psField->getLabel() === '' ) {
				$fieldLabel = $psField->getName();
			} else {
				$fieldLabel = $psField->getLabel();
			}
			$templateField = SFTemplateField::create(
				$psField->getName(),
				$fieldLabel,
				$propertyName,
				$psField->isList(),
				$psField->getDelimiter(),
				$psField->getDisplay(),
				$field_options
			);
			$templateFields[] = $templateField;
		}
		return $templateFields;
	}

	/**
	 * Creates a form page, when called from the 'generatepages' page
	 * of Page Schemas.
	 */
	public static function generateForm( $formName, $formTitle,
		$formTemplates, $formDataFromSchema, $categoryName ) {
		global $wgUser;

		$input = array();
		if ( array_key_exists( 'inputFreeText', $formDataFromSchema ) )
			$input['free text'] = '{{{standard input|free text|rows=10}}}';
		if ( array_key_exists( 'inputSummary', $formDataFromSchema ) )
			$input['summary'] = '{{{standard input|summary}}}';
		if ( array_key_exists( 'inputMinorEdit', $formDataFromSchema ) )
			$input['minor edit'] = '{{{standard input|minor edit}}}';
		if ( array_key_exists( 'inputWatch', $formDataFromSchema ) )
			$input['watch'] = '{{{standard input|watch}}}';
		if ( array_key_exists( 'inputSave', $formDataFromSchema ) )
			$input['save'] = '{{{standard input|save}}}';
		if ( array_key_exists( 'inputPreview', $formDataFromSchema ) )
			$input['preview'] = '{{{standard input|preview}}}';
		if ( array_key_exists( 'inputChanges', $formDataFromSchema ) )
			$input['changes'] = '{{{standard input|changes}}}';
		if ( array_key_exists( 'inputCancel', $formDataFromSchema ) )
			$input['cancel'] = '{{{standard input|cancel}}}';

		$freeTextLabel = null;
		if ( array_key_exists( 'freeTextLabel', $formDataFromSchema ) )
			$freeTextLabel = $formDataFromSchema['freeTextLabel'];

		$formItems = array();
		foreach ( $formTemplates as $template ) {
			$formItems[] = array( 'type' => 'template',
							'name' => $template->getTemplateName(),
							'item' => $template );
		}
		$form = SFForm::create( $formName, $formItems );
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
		$formContents = $form->createMarkup( $input, $freeTextLabel );
		$params = array();
		$params['user_id'] = $wgUser->getId();
		$params['page_text'] = $formContents;
		$job = new PSCreatePageJob( $formTitle, $params );
		Job::batchInsert( array( $job ) );
	}

	/**
	 * Generate pages (form and templates) specified in the list.
	 */
	public static function generatePages( $pageSchemaObj, $selectedPages ) {
		global $wgUser;

		$psTemplates = $pageSchemaObj->getTemplates();

		$form_templates = array();
		$jobs = array();
		$templateHackUsed = false;
		$isCategoryNameSet = false;

		// Generate every specified template
		foreach ( $psTemplates as $psTemplate ) {
			$templateName = $psTemplate->getName();
			$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $templateName );
			$fullTemplateName = PageSchemas::titleString( $templateTitle );
			$template_fields = self::getFieldsFromTemplateSchema( $psTemplate );
			if ( class_exists( 'SIOPageSchemas' ) ) {
				$internalObjProperty = SIOPageSchemas::getInternalObjectPropertyName( $psTemplate );
			} else {
				$internalObjProperty = null;
			}
			$template_options = array();
			wfRunHooks('SfTemplateOptions', array( $psTemplate, &$template_options ) );
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
			$templateText = SFTemplateField::createTemplateText( $templateName,
				$template_fields, $internalObjProperty, $categoryName,
				null, null, $templateFormat, $template_options );
			if ( in_array( $fullTemplateName, $selectedPages ) ) {
				$params = array();
				$params['user_id'] = $wgUser->getId();
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
			$form_template = SFTemplateInForm::create(
				$templateName,
				$templateLabel,
				$psTemplate->isMultiple(),
				null,
				$form_fields
			);
			$form_templates[] = $form_template;
		}

		// Create the "!" hack template, if it's necessary
		if ( $templateHackUsed ) {
			$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, '!' );
			if ( ! $templateTitle->exists() ) {
				$params = array();
				$params['user_id'] = $wgUser->getId();
				$params['page_text'] = '|';
				$jobs[] = new PSCreatePageJob( $templateTitle, $params );
			}
		}

		Job::batchInsert( $jobs );

		// Create form, if it's specified.
		$formName = self::getFormName( $pageSchemaObj );
		$categoryName = $pageSchemaObj->getCategoryName();
		if ( !empty( $formName ) ) {
			$formInfo = self::getMainFormInfo( $pageSchemaObj );
			$formTitle = Title::makeTitleSafe( SF_NS_FORM, $formName );
			$fullFormName = PageSchemas::titleString( $formTitle );
			if ( in_array( $fullFormName, $selectedPages ) ) {
				self::generateForm( $formName, $formTitle,
					$form_templates, $formInfo, $categoryName );
			}
		}
	}

	public static function getSchemaDisplayValues( $schemaXML ) {
		foreach ( $schemaXML->children() as $tag => $child ) {
			if ( $tag == "semanticforms_Form" ) {
				$formName = $child->attributes()->name;
				$values = array();
				foreach ( $child->children() as $tagName => $prop ) {
					$values[$tagName] = (string)$prop;
				}
				return array( $formName, $values );
			}
		}
		return null;
	}

	public static function getTemplateValues( $psTemplate ) {
		// TODO - fix this.
		$values = array();
		if ( $psTemplate instanceof PSTemplate ) {
			$psTemplate = $psTemplate->getXML();
		}
		foreach ( $psTemplate->children() as $tag => $child ) {
			if ( $tag == "semanticforms_TemplateDetails" ) {
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
	 */
	public static function getTemplateDisplayValues( $templateXML ) {
		$templateValues = self::getTemplateValues( $templateXML );
		if ( count( $templateValues ) == 0 ) {
			return null;
		}

		$displayValues = array();
		foreach ( $templateValues as $key => $value ) {
			if ( $key == 'Label' ) {
				$propName = 'Label';
			} elseif ( $key == 'AddAnotherText' ) {
				$propName = "'Add another' button";
			}
			$displayValues[$propName] = $value;
		}
		return array( null, $displayValues );
	}

	public static function getFieldDisplayString() {
		return 'Form input';
	}

	/**
	 * Displays data on a single form input in the Page Schemas XML.
	 */
	public static function getFieldDisplayValues( $fieldXML ) {
		foreach ( $fieldXML->children() as $tag => $child ) {
			if ( $tag == "semanticforms_FormInput" ) {
				$inputName = $child->attributes()->name;
				$values = array();
				foreach ( $child->children() as $prop ) {
					if ( $prop->getName() == 'InputType' ) {
						$propName = 'Input type';
					} else {
						$propName = (string)$prop->attributes()->name;
					}
					$values[$propName] = (string)$prop;
				}
				return array( $inputName, $values );
			}
		}
		return null;
	}
}
