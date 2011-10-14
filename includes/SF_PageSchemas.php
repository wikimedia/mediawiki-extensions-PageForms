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

class SFPageSchemas {

	/**
	 * Creates an object to hold form-wide information, based on an XML
	 * object from the Page Schemas extension.
	 */
	public static function createPageSchemasObject( $objectName, $xmlForField, &$object ) {
		$sfarray = array();
		if ( $objectName == "semanticforms_Form" ) {
			foreach ( $xmlForField->children() as $tag => $child ) {
				if ( $tag == $objectName ) {
					$formName = (string) $child->attributes()->name;
					$sfarray['name'] = $formName;
					foreach ( $child->children() as $tag => $formelem ) {
						$sfarray[(string)$tag] = (string)$formelem;
					}
					$object['sf'] = $sfarray;
					return true;
				}
			}
		}
		if ( $objectName == "semanticforms_TemplateDetails" ) {
			foreach ( $xmlForField->children() as $tag => $child ) {
				if ( $tag == $objectName ) {
					foreach ( $child->children() as $tag => $formelem ) {
						$sfarray[(string)$tag] = (string)$formelem;
					}
					$object['sf'] = $sfarray;
					return true;
				}
			}
		}
		if ( $objectName == "semanticforms_FormInput" ) {
			foreach ( $xmlForField->children() as $tag => $child ) {
				if ( $tag == $objectName ) {
					foreach ( $child->children() as $prop ) {
						if ( $prop->getName() == 'InputType' ) {
							$sfarray[$prop->getName()] = (string)$prop;
						} else {
							$sfarray[(string)$prop->attributes()->name] = (string)$prop;
						}
					}
					$object['sf'] = $sfarray;
					return true;
				}
			}
		}
		return true;
	}

	/**
	 * Creates Page Schemas XML for form-wide information.
	 */
	public static function getSchemaXML( $request, &$xmlArray ) {
		$xml = '';
		foreach ( $request->getValues() as $var => $val ) {
			if ( $var == 'sf_form_name' ) {
				$xml = '<semanticforms_Form name="' . $val . '" >';
			} elseif ( $var == 'sf_page_name_formula' ) {
				if ( !empty( $val ) ) {
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
				$xml .= '</semanticforms_Form>';
			}
		}
		$xmlArray['sf'] = $xml;
		return true;
	}

	/**
	 * Creates Page Schemas XML for form information on templates.
	 */
	public static function getTemplateXML( $request, &$xmlArray ) {
		$xmlPerTemplate = array();
		$templateNum = -1;
		foreach ( $request->getValues() as $var => $val ) {
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
		$xmlArray['sf'] = $xmlPerTemplate;
		return true;
	}

	/**
	 * Creates Page Schemas XML for form fields.
	 */
	public static function getFieldXML( $request, &$xmlArray ) {
		$xmlPerField = array();
		$fieldNum = -1;
		foreach ( $request->getValues() as $var => $val ) {
			if ( substr( $var, 0, 14 ) == 'sf_input_type_' ) {
				$fieldNum = substr( $var, 14 );
				$xml = '<semanticforms_FormInput>';
				if ( !empty( $val ) ) {
					$xml .= '<InputType>' . $val . '</InputType>';
				}
			} elseif ( substr( $var, 0, 14 ) == 'sf_key_values_' ) {
				if ( $val != '' ) {
					// replace the comma substitution character that has no chance of
					// being included in the values list - namely, the ASCII beep
					$listSeparator = ',';
					$key_values_str = str_replace( "\\$listSeparator", "\a", $val );
					$key_values_array = explode( $listSeparator, $key_values_str );
					foreach ( $key_values_array as $i => $value ) {
						// replace beep back with comma, trim
						$value = str_replace( "\a", $listSeparator, trim( $value ) );
						$param_value = explode( "=", $value );
						if ( $param_value[1] != null ) {
							// Handles <Parameter name="size">20</Parameter>
							$xml .= '<Parameter name="'.$param_value[0].'">'.$param_value[1].'</Parameter>';
						} else {
							// Handles <Parameter name="mandatory" />
							$xml .= '<Parameter name="'.$param_value[0].'"/>';
						}
					}
				}
				$xml .= '</semanticforms_FormInput>';
				$xmlPerField[$fieldNum] = $xml;
			}
		}
		$xmlArray['sf'] = $xmlPerField;
		return true;
	}

	public static function getSchemaHTML( $pageSchemaObj, &$extensionsHTML ) {
		$form_array = array();
		$hasExistingValues = false;
		if ( !is_null( $pageSchemaObj ) ) {
			$obj = $pageSchemaObj->getObject('semanticforms_Form');
			if ( array_key_exists( 'sf', $obj ) ) {
				$form_array = $obj['sf'];
				$hasExistingValues = true;
			}
		}

		// Get all the values from the page schema.
		if ( array_key_exists( 'name', $form_array ) ) {
			$formName = $form_array['name'];
		} else {
			$formName = '';
		}
		if ( array_key_exists( 'PageNameFormula', $form_array ) ) {
			$pageNameFormula = $form_array['PageNameFormula'];
		} else {
			$pageNameFormula = '';
		}
		if ( array_key_exists( 'CreateTitle', $form_array ) ) {
			$createTitle = $form_array['CreateTitle'];
		} else {
			$createTitle = '';
		}
		if ( array_key_exists( 'EditTitle', $form_array ) ) {
			$editTitle = $form_array['EditTitle'];
		} else {
			$editTitle = '';
		}

		$text = "\t<p>" . wfMsg( 'ps-namelabel' ) . ' ' . Html::input( 'sf_form_name', $formName, 'text', array( 'size' => 15 ) ) . "</p>\n";
		// The checkbox isn't actually a field in the page schema -
		// we set it based on whether or not a page formula has been
		// specified.
		$twoStepProcessAttrs = array( 'id' => 'sf-two-step-process' );
		if ( $pageNameFormula == '' ) {
			$twoStepProcessAttrs['checked'] = true;
		}
		$text .= '<p>' . Html::input( 'sf_two_step_process', null, 'checkbox', $twoStepProcessAttrs );
		$text .= ' Users must enter the page name before getting to the form (default)';
		$text .= "</p>\n";
		$text .= "\t<p id=\"sf-page-name-formula\">" . wfMsg( 'sf-pageschemas-pagenameformula' ) . ' ' . Html::input( 'sf_page_name_formula', $pageNameFormula, 'text', array( 'size' => 30 ) ) . "</p>\n";
		$text .= "\t<p>" . wfMsg( 'sf-pageschemas-createtitle' ) . ' ' . Html::input( 'sf_create_title', $createTitle, 'text', array( 'size' => 25 ) ) . "</p>\n";
		$text .= "\t<p id=\"sf-edit-title\">" . wfMsg( 'sf-pageschemas-edittitle' ) . ' ' . Html::input( 'sf_edit_title', $editTitle, 'text', array( 'size' => 25 ) ) . "</p>\n";

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

		$extensionsHTML['sf'] = array( 'Form', '#CF9', $text, $hasExistingValues );

		return true;
	}

	public static function getTemplateHTML( $psTemplate, &$extensionsHTML ) {
		$form_array = array();
		$hasExistingValues = false;
		$templateLabel = null;
		$addAnotherText = null;
		if ( !is_null( $psTemplate ) ) {
			$obj = $psTemplate->getObject( 'semanticforms_TemplateDetails' );
			if ( array_key_exists( 'sf', $obj ) ) {
				$form_array = $obj['sf'];
				$hasExistingValues = true;
			}
			if ( array_key_exists( 'Label', $form_array ) ) {
				$templateLabel = $form_array['Label'];
			}
			if ( array_key_exists( 'AddAnotherText', $form_array ) ) {
				$addAnotherText = $form_array['AddAnotherText'];
			}
		}

		$text = "\t<p>" . "The following fields are useful if there can be multiple instances of this template." . "</p>\n";
		$text .= "\t<p>" . 'Label:' . ' ' . Html::input( 'sf_template_label_num', $templateLabel, 'text', array( 'size' => 15 ) ) . "</p>\n";
		$text .= "\t<p>" . 'Text of button to add another instance (default is "Add another"):' . ' ' . Html::input( 'sf_template_addanother_num', $addAnotherText, 'text', array( 'size' => 25 ) ) . "</p>\n";

		$extensionsHTML['sf'] = array( 'Details for template in form', '#CF9', $text, $hasExistingValues );

		return true;
	}

	/**
	 * Returns the HTML for inputs to define a single form field,
	 * within the Page Schemas 'edit schema' page.
	 */
	public static function getFieldHTML( $field, &$extensionsHTML ) {
		$hasExistingValues = false;
		$fieldValues = array();
		if ( !is_null( $field ) ) {
			$sf_array = $field->getObject('semanticforms_FormInput');
			if ( array_key_exists( 'sf', $sf_array ) ) {
				$fieldValues = $sf_array['sf'];
				$hasExistingValues = true;
			}
		}

		if ( array_key_exists( 'InputType', $fieldValues ) ) {
			$inputType = $fieldValues['InputType'];
		} else {
			$inputType = '';
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
		$text = '<p>' . wfMsg( 'sf-pageschemas-inputtype' ) . ' ' . $inputTypeDropdown . '</p>';

		$text .= "\t" . '<p>Enter parameter names and their values as key=value pairs, separated by commas (if a value contains a comma, replace it with "\,"). For example: size=20, mandatory</p>' . "\n";
		$paramValues = array();
		foreach ( $fieldValues as $param => $value ) {
			if ( !empty( $param ) && $param != 'InputType' ) {
				if ( !empty( $value ) ) {
					$paramValues[] = $param . '=' . $value;
				} else {
					$paramValues[] = $param;
				}
			}
		}
		$param_value_str = implode( ', ', $paramValues );
		$inputParamsAttrs = array( 'size' => 80 );
		$inputParamsInput = Html::input( 'sf_key_values_num', $param_value_str, 'text', $inputParamsAttrs );
		$text .= "\t<p>$inputParamsInput</p>\n";
		$extensionsHTML['sf'] = array( 'Form input', '#CF9', $text, $hasExistingValues );

		return true;
	}

	public static function getFormName( $psSchemaObj ) {
		$mainFormInfo = self::getMainFormInfo( $psSchemaObj );
		if ( is_null( $mainFormInfo ) || !array_key_exists( 'name', $mainFormInfo ) ) {
			return null;
		}
		return $mainFormInfo['name'];
	}

	public static function getMainFormInfo( $psSchemaObj ) {
		$formData = $psSchemaObj->getObject( 'semanticforms_Form' );
		if ( !array_key_exists( 'sf', $formData ) ) {
			return null;
		}
		return $formData['sf'];
	}

	public static function getFormFieldInfo( $psTemplate, $template_fields ) {
		$form_fields = array();
		$fieldsInfo = $psTemplate->getFields();
		foreach ( $fieldsInfo as $i => $psField ) {
			$fieldName = $psField->getName();
			$fieldFormInfo = $psField->getObject( 'semanticforms_FormInput' );
			if ( !is_null( $fieldFormInfo ) && array_key_exists( 'sf', $fieldFormInfo ) ) {
				$formField = SFFormField::create( $i, $template_fields[$i] );
				$fieldFormArray = $fieldFormInfo['sf'];
				foreach ($fieldFormArray as $var => $val ) {
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
	public static function getPageList( $psSchemaObj, &$genPageList ) {
		global $wgOut, $wgUser;

		$psTemplates = $psSchemaObj->getTemplates();
		foreach ( $psTemplates as $psTemplate ) {
			$title = Title::makeTitleSafe( NS_TEMPLATE, $psTemplate->getName() );
			$genPageList[] = $title;
		}
		$form_name = self::getFormName( $psSchemaObj );
		if ( $form_name == null ) {
			return true;
		}
		$title = Title::makeTitleSafe( SF_NS_FORM, $form_name );
		$genPageList[] = $title;
		return true;
	}

	/**
	 * Returns an array of SFTemplateField objects, representing the fields
	 * of a template, based on the contents of a <PageSchema> tag.
	 */
	public static function getFieldsFromTemplateSchema( $psTemplate ) {
		$psFields = $psTemplate->getFields();
		$templateFields = array();
		foreach( $psFields as $psField ) {
			$smw_array = $psField->getObject('semanticmediawiki_Property');
			if ( array_key_exists( 'smw', $smw_array ) ) {
				$propertyName = $smw_array['smw']['name'];
			} else {
				$propertyName = null;
			}
			if ( $psField->getLabel() == '' ) {
				$fieldLabel = $psField->getName();
			} else {
				$fieldLabel = $psField->getLabel();
			}
			$templateField = SFTemplateField::create(
				$psField->getName(),
				$fieldLabel,
				$propertyName,
				$psField->isList(),
				$psField->getDelimiter()
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

		$form = SFForm::create( $formName, $formTemplates );
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
		$formContents = $form->createMarkup();
		$params = array();
		$params['user_id'] = $wgUser->getId();
		$params['page_text'] = $formContents;
		$job = new PSCreatePageJob( $formTitle, $params );
		Job::batchInsert( array( $job ) );
	}

	/**
	 * Generate pages (form and templates) specified in the list.
	 */
	public static function generatePages( $psSchemaObj, $toGenPageList ) {
		global $wgOut, $wgUser;

		$psTemplates = $psSchemaObj->getTemplates();

		$form_templates = array();
		$jobs = array();
		foreach ( $psTemplates as $psTemplate ) {
			// Generate every specified template
			$templateName = $psTemplate->getName();
			$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $templateName );
			$fullTemplateName = PageSchemas::titleString( $templateTitle );
			$template_fields = self::getFieldsFromTemplateSchema( $psTemplate );
			if ( class_exists( 'SIOPageSchemas' ) ) {
				$internalObjProperty = SIOPageSchemas::getInternalObjectPropertyName( $psTemplate );
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
				$categoryName = $psSchemaObj->getCategoryName();
			}
			$templateText = SFTemplateField::createTemplateText( $templateName,
				$template_fields, $internalObjProperty, $categoryName, null, null, null );
			if ( in_array( $fullTemplateName, $toGenPageList ) ) {
				$params = array();
				$params['user_id'] = $wgUser->getId();
				$params['page_text'] = $templateText;
				$jobs[] = new PSCreatePageJob( $templateTitle, $params );
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
		Job::batchInsert( $jobs );

		// Create form, if it's specified.
		$formName = self::getFormName( $psSchemaObj );
		if ( !empty( $formName ) ) {
			$formInfo = self::getMainFormInfo( $psSchemaObj );
			$formTitle = Title::makeTitleSafe( SF_NS_FORM, $formName );
			$fullFormName = PageSchemas::titleString( $formTitle );
			if ( in_array( $fullFormName, $toGenPageList ) ) {
				self::generateForm( $formName, $formTitle,
					$form_templates, $formInfo, $categoryName );
			}
		}
		return true;
	}

	public static function getFormDisplayInfo( $schemaXML, &$text_object ) {
		foreach ( $schemaXML->children() as $tag => $child ) {
			if ( $tag == "semanticforms_Form" ) {
				$formName = $child->attributes()->name;
				$values = array();
				foreach ( $child->children() as $tagName => $prop ) {
					$values[$tagName] = (string)$prop;
				}
				$text_object['sf'] = array( 'Form', $formName, '#CF9', $values );
				break;
			}
		}
		return true;
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

	/**
	 * Displays form details for one template in the Page Schemas XML.
	 */
	public static function getTemplateDisplayInfo( $templateXML, &$text_object ) {
		$templateValues = self::getTemplateValues( $templateXML );
		if ( count( $templateValues ) == 0 ) {
			return true;
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
		$text_object['sf'] = array( 'Details for template in form', null, '#CF9', $displayValues );
		return true;
	}

	/**
	 * Displays data on a single form input in the Page Schemas XML.
	 */
	public static function getFormInputDisplayInfo( $fieldXML, &$text_object ) {
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
				$text_object['sf'] = array( 'Form input', $inputName, '#CF9', $values );
				break;
			}
		}
		return true;
	}
}
