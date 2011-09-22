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
	 * Creates Page Schemas XML for a specific form field.
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
							//handles Parameter name="size">20</Parameter>
							$xml .= '<Parameter name="'.$param_value[0].'">'.$param_value[1].'</Parameter>';
						} else {
							//handles <Parameter name="mandatory" />
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

	public static function getSchemaHTML( $pageSchemaObj, &$text_extensions ) {
		$form_array = array();
		$hasExistingValues = false;
		if ( !is_null( $pageSchemaObj ) ) {
			$obj = $pageSchemaObj->getObject('semanticforms_Form');
			if ( array_key_exists( 'sf', $obj ) ) {
				$form_array = $obj['sf'];
				$hasExistingValues = true;
			}
		}
		if ( array_key_exists( 'name', $form_array ) ) {
			$formName = $form_array['name'];
		} else {
			$formName = '';
		}
		$text = "\t<p>" . 'Name:' . ' ' . Html::input( 'sf_form_name', $formName, 'text', array( 'size' => 15 ) ) . "</p>\n";
		if ( array_key_exists( 'PageNameFormula', $form_array ) ) {
			$pageNameFormula = $form_array['PageNameFormula'];
		} else {
			$pageNameFormula = '';
		}
		$text .= "\t<p>" . wfMsg( 'sf-pageschemas-pagenameformula' ) . ' ' . Html::input( 'sf_page_name_formula', $pageNameFormula, 'text', array( 'size' => 20 ) ) . "</p>\n";
		if ( array_key_exists( 'CreateTitle', $form_array ) ) {
			$createTitle = $form_array['CreateTitle'];
		} else {
			$createTitle = '';
		}
		$text .= "\t<p>" . wfMsg( 'sf-pageschemas-createtitle' ) . ' ' . Html::input( 'sf_create_title', $createTitle, 'text', array( 'size' => 25 ) ) . "</p>\n";
		if ( array_key_exists( 'EditTitle', $form_array ) ) {
			$editTitle = $form_array['EditTitle'];
		} else {
			$editTitle = '';
		}
		$text .= "\t<p>" . wfMsg( 'sf-pageschemas-edittitle' ) . ' ' . Html::input( 'sf_edit_title', $editTitle, 'text', array( 'size' => 25 ) ) . "</p>\n";
		$text_extensions['sf'] = array( 'Form', '#CF9', $text, $hasExistingValues );

		return true;
	}

	/**
	 * Returns the HTML for inputs to define a single form field,
	 * within the Page Schemas 'edit schema' page.
	 */
	public static function getFieldHTML( $field, &$text_extensions ) {
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

		$text .= "\t" . '<p>Enter parameter names and their values as key=value pairs, separated by commas (if a value contains a comma, replace it with "\,") For example: size=20, mandatory</p>' . "\n";
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
		$text_extensions['sf'] = array( 'Form input', '#CF9', $text, $hasExistingValues );

		return true;
	}

	public static function getFormName( $psSchemaObj ) {
		$mainFormInfo = self::getMainFormInfo( $psSchemaObj );
		if ( is_null( $mainFormInfo ) || !array_key_exists( 'name', $mainFormInfo ) ) {
			return null;
		}
		return $mainFormInfo['name']
	}

	public static function getMainFormInfo( $psSchemaObj ) {
		$formData = $psSchemaObj->getObject( 'semanticforms_Form' );
		if ( !array_key_exists( 'sf', $formData ) ) {
			return null;
		}
		return $formData['sf'];
	}

	public static function getFormFieldInfo( $psTemplateObj, $template_fields ) {
		$form_fields = array();
		$fieldsInfo = $psTemplateObj->getFields();
		foreach ( $fieldsInfo as $i => $psFieldObj ) {
			$fieldName = $psFieldObj->getName();
			$fieldFormInfo = $psFieldObj->getObject( 'semanticforms_FormInput' );
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

		$template_all = $psSchemaObj->getTemplates();
		foreach ( $template_all as $template ) {
			$title = Title::makeTitleSafe( NS_TEMPLATE, $template->getName() );
			$genPageList[] = $title;
		}
		$form_name = self::getFormName( $psSchemaObj );
		if ( $form_name == null ) {
			return true;
		}
		//$form = SFForm::create( $form_name, $form_templates );
		$title = Title::makeTitleSafe( SF_NS_FORM, $form_name );
		$genPageList[] = $title;
		return true;
	}

	/**
	 * Returns an array of SFTemplateField objects, representing the fields
	 * of a template, based on the contents of a <PageSchema> tag.
	 */
	public static function getFieldsFromTemplateSchema( $templateFromSchema ) {
		$field_all = $templateFromSchema->getFields();
		$template_fields = array();
		foreach( $field_all as $fieldObj ) {
			$smw_array = $fieldObj->getObject('semanticmediawiki_Property');
			if ( array_key_exists( 'smw', $smw_array ) ) {
				$propertyName = $smw_array['smw']['name'];
			} else {
				$propertName = null;
			}
			if ( $fieldObj->getLabel() == '' ) {
				$fieldLabel = $fieldObj->getName();
			} else {
				$fieldLabel = $fieldObj->getLabel();
			}
			$templateField = SFTemplateField::create(
				$fieldObj->getName(),
				$fieldLabel,
				$propertyName,
				$fieldObj->isList(),
				$fieldObj->getDelimiter()
			);
			$template_fields[] = $templateField;
		}
		return $template_fields;
	}

	/**
	 * Creates a form page, when called from the 'generatepages' page
	 * of Page Schemas.
	 */
	public static function generateForm( $formName, $formTitle, $formTemplates, $formDataFromSchema ) {
		global $wgUser;

		$form = SFForm::create( $formName, $formTemplates );
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

		$templatesFromSchema = $psSchemaObj->getTemplates();
		$form_templates = array();
		$jobs = array();
		foreach ( $templatesFromSchema as $templateFromSchema ) {
			// Generate every specified template
			$templateName = $templateFromSchema->getName();
			$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $templateName );
			$template_fields = array();
			$fullTemplateName = PageSchemas::titleString( $templateTitle );
			$template_fields = self::getFieldsFromTemplateSchema( $templateFromSchema );
			$templateText = SFTemplateField::createTemplateText( $templateName,
				$template_fields, null, $psSchemaObj->categoryName, null, null, null );
			if ( in_array( $fullTemplateName, $toGenPageList ) ) {
				$params = array();
				$params['user_id'] = $wgUser->getId();
				$params['page_text'] = $templateText;
				$jobs[] = new PSCreatePageJob( $templateTitle, $params );
			}

			$form_fields = self::getFormFieldInfo( $templateFromSchema, $template_fields );
			// Create template info for form, for use in generating
			// the form (if it will be generated).
			$form_template = SFTemplateInForm::create(
				$templateName,
				$templateFromSchema->getLabel(),
				$templateFromSchema->isMultiple(),
				null,
				$form_fields
			);
			$form_templates[] = $form_template;
		}
		Job::batchInsert( $jobs );

		// Create form, if it's specified.
		$form_name = self::getFormName( $psSchemaObj );
		if ( !empty( $form_name ) ) {
			$formInfo = self::getMainFormInfo( $psSchemaObj );
			$formTitle = Title::makeTitleSafe( SF_NS_FORM, $form_name );
			$fullFormName = PageSchemas::titleString( $formTitle );
			if ( in_array( $fullFormName, $toGenPageList ) ) {
				self::generateForm( $form_name, $formTitle, $form_templates, $formInfo );
			}
		}
		return true;
	}

	/**
	 * Parses the field elements in the Page Schemas XML.
	 */
	public static function parseFieldElements( $field_xml, &$text_object ) {

		foreach ( $field_xml->children() as $tag => $child ) {
			if ( $tag == "semanticforms_FormInput" ) {
				$text = PageSchemas::tableMessageRowHTML( "paramAttr", wfMsg( 'specialpages-group-sf_group' ), (string)$tag );
				foreach ( $child->children() as $prop ) {
					if ( $prop->getName() == 'InputType' ) {
						$text .= PageSchemas::tableMessageRowHTML("paramAttrMsg", $prop->getName(), $prop );
					} else {
						$prop_name = (string)$prop->attributes()->name;
						$text .= PageSchemas::tableMessageRowHTML("paramAttrMsg", $prop_name, (string)$prop );
					}
				}
				$text_object['sf'] = $text;
				break;
			}
		}
		return true;
	}
}
