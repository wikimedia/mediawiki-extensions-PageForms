<?php
/**
 * A special page holding a form that allows the user to create a data-entry
 * form.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFSpecialPages
 */
class PFCreateForm extends SpecialPage {

	function __construct() {
		parent::__construct( 'CreateForm' );
	}

	function execute( $query ) {
		$out = $this->getOutput();
		$req = $this->getRequest();

		$this->setHeaders();
		if ( $req->getCheck( 'showinputtypeoptions' ) ) {
			$out->disable();

			// handle Ajax action
			$inputType = $req->getVal( 'showinputtypeoptions' );
			$fieldFormText = $req->getVal( 'formfield' );

			$paramValues = [];
			echo $this->showInputTypeOptions( $inputType, $fieldFormText, $paramValues );
		} else {
			$this->doSpecialCreateForm( $query );
		}
	}

	function doSpecialCreateForm( $query ) {
		$out = $this->getOutput();
		$req = $this->getRequest();
		$db = wfGetDB( DB_REPLICA );

		if ( $query !== null ) {
			$presetFormName = str_replace( '_', ' ', $query );
			$out->setPageTitle( $this->msg( 'pf-createform-with-name', $presetFormName )->text() );
			$form_name = $presetFormName;
		} else {
			$presetFormName = null;
			$form_name = $req->getVal( 'form_name' );
		}

		$section_name_error_str = '<span class="error" id="section_error">' . $this->msg( 'pf_blank_error' )->escaped() . '</span>';

		$out->addModules( [ 'ext.pageforms.collapsible', 'ext.pageforms.PF_CreateForm' ] );

		// Get the names of all templates on this site.
		$all_templates = [];
		$res = $db->select(
			'page',
			'page_title',
			[ 'page_namespace' => NS_TEMPLATE, 'page_is_redirect' => 0 ],
			__METHOD__,
			[ 'ORDER BY' => 'page_title' ]
		);

		if ( $db->numRows( $res ) > 0 ) {
			while ( $row = $db->fetchRow( $res ) ) {
				$template_name = str_replace( '_', ' ', $row[0] );
				$all_templates[] = $template_name;
			}
		}

		$deleted_template_loc = null;
		$deleted_section_loc = null;
		// To keep the templates and sections
		$form_items = [];

		// Handle inputs.
		foreach ( $req->getValues() as $var => $val ) {
			# ignore variables that are not of the right form
			if ( strpos( $var, "_" ) != false ) {
				# get the template declarations and work from there
				list( $action, $id ) = explode( "_", $var, 2 );
				if ( $action == "template" ) {
					// If the button was pressed to remove
					// this template, just don't add it to
					// the array.
					if ( $req->getVal( "del_$id" ) != null ) {
						$deleted_template_loc = $id;
					} else {
						$form_template = PFTemplateInForm::create(
							$val,
							$req->getVal( "label_$id" ),
							$req->getVal( "allow_multiple_$id" )
						);
						$form_items[] = [
							'type' => 'template',
							'name' => $form_template->getTemplateName(),
							'item' => $form_template
						];
					}
				} elseif ( $action == "section" ) {
					if ( $req->getVal( "delsection_$id" ) != null ) {
						$deleted_section_loc = $id;
					} else {
						$form_section = PFPageSection::create( $val );
						$form_items[] = [ 'type' => 'section',
							'name' => $form_section->getSectionName(),
							'item' => $form_section ];
					}
				}
			}
		}
		if ( $req->getVal( 'add_field' ) != null ) {
			$form_template = PFTemplateInForm::create( $req->getVal( 'new_template' ), "", false );
			$template_loc = $req->getVal( 'before_template' );
			$template_count = 0;
			if ( $template_loc === null ) {
				$new_template_loc = 0;
				$template_loc = 0;
			} else {
				// Count the number of templates before the
				// location of the template to be added
				for ( $i = 0; $i < $template_loc; $i++ ) {
					if ( $form_items[$i]['type'] == 'template' ) {
						$template_count++;
					}
				}
				$new_template_loc = $template_count;
			}
			// @HACK - array_splice() doesn't work for objects, so
			// we have to first insert a stub element into the
			// array, then replace that with the actual object.
			array_splice( $form_items, $template_loc, 0, "stub" );
			$form_items[$template_loc] = [ 'type' => 'template', 'name' => $form_template->getTemplateName(), 'item' => $form_template ];
		} else {
			$template_loc = null;
			$new_template_loc = null;
		}

		if ( $req->getVal( 'add_section' ) != null ) {
			$form_section = PFPageSection::create( $req->getVal( 'sectionname' ) );
			$section_loc = $req->getVal( 'before_section' );
			$section_count = 0;
			if ( $section_loc === null ) {
				$new_section_loc = 0;
				$section_loc = 0;
			} else {
				// Count the number of sections before the
				// location of the section to be added
				for ( $i = 0; $i < $section_loc; $i++ ) {
					if ( $form_items[$i]['type'] == 'section' ) {
						$section_count++;
					}
				}
				$new_section_loc = $section_count;
			}
			// The same used hack for templates
			array_splice( $form_items, $section_loc, 0, "stub" );
			$form_items[$section_loc] = [ 'type' => 'section', 'name' => $form_section->getSectionName(), 'item' => $form_section ];
		} else {
			$section_loc = null;
			$new_section_loc = null;
		}

		// Now cycle through the templates and fields, modifying each
		// one per the query variables.
		$templates = 0;
		$sections = 0;
		foreach ( $form_items as $fi ) {
			if ( $fi['type'] == 'template' ) {
				foreach ( $fi['item']->getFields() as $j => $field ) {
					$old_i = PFFormUtils::getChangedIndex( $templates, $new_template_loc, $deleted_template_loc );
					foreach ( $req->getValues() as $key => $value ) {
						if ( ( $pos = strpos( $key, '_' . $old_i . '_' . $j ) ) != false ) {
							$paramName = substr( $key, 0, $pos );
							// Spaces got replaced by
							// underlines in the query.
							$paramName = str_replace( '_', ' ', $paramName );
						} else {
							continue;
						}

						if ( $paramName == 'label' ) {
							$field->template_field->setLabel( $value );
						} elseif ( $paramName == 'input type' ) {
							$input_type = $req->getVal( "input_type_" . $old_i . "_" . $j );
							if ( $input_type == 'hidden' ) {
								$field->setInputType( $input_type );
								$field->setIsHidden( true );
							} elseif ( substr( $input_type, 0, 1 ) == '.' ) {
								// It's the default input type -
								// don't do anything.
							} else {
								$field->setInputType( $input_type );
							}
						} else {
							if ( !empty( $value ) ) {
								if ( $value == 'on' ) {
									$value = true;
								}
								$field->setFieldArg( $paramName, $value );
							}
						}
					}
				}
				$templates++;

			} elseif ( $fi['type'] == 'section' ) {
				$section = $fi['item'];
				$old_i = PFFormUtils::getChangedIndex( $sections, $new_section_loc, $deleted_section_loc );
				foreach ( $req->getValues() as $key => $value ) {
					if ( ( $pos = strpos( $key, '_section_' . $old_i ) ) != false ) {
						$paramName = substr( $key, 0, $pos );
						$paramName = str_replace( '_', ' ', $paramName );
					} else {
						continue;
					}

					if ( !empty( $value ) ) {
						if ( $value == 'on' ) {
							$value = true;
						}
						if ( $paramName == 'level' ) {
							$section->setSectionLevel( $value );
						} elseif ( $paramName == 'hidden' ) {
							$section->setIsHidden( $value );
						} elseif ( $paramName == 'restricted' ) {
							$section->setIsRestricted( $value );
						} elseif ( $paramName == 'mandatory' ) {
							$section->setIsMandatory( $value );
						} else {
							$section->setSectionArgs( $paramName, $value );
						}
					}
				}
				$sections++;
			}

		}

		$form = PFForm::create( $form_name, $form_items );

		// If a submit button was pressed, create the form-definition
		// file, then redirect.
		$save_page = $req->getCheck( 'wpSave' );
		$preview_page = $req->getCheck( 'wpPreview' );
		if ( $save_page || $preview_page ) {
			$validToken = $this->getUser()->matchEditToken( $req->getVal( 'csrf' ), 'CreateForm' );
			if ( !$validToken ) {
				$text = "This appears to be a cross-site request forgery; canceling save.";
				$out->addHTML( $text );
				return;
			}

			// Validate form name.
			if ( $form->getFormName() == "" ) {
				$form_name_error_str = $this->msg( 'pf_blank_error' )->text();
			} else {
				// Redirect to wiki interface.
				$out->setArticleBodyOnly( true );
				$title = Title::makeTitleSafe( PF_NS_FORM, $form->getFormName() );
				$full_text = $form->createMarkup();
				$text = PFUtils::printRedirectForm( $title, $full_text, "", $save_page, $preview_page, false, false, false, null, null );
				$out->addHTML( $text );
				return;
			}
		}

		$text = "\t" . '<form action="" method="post">' . "\n";
		if ( $presetFormName === null ) {
			// Set 'title' field, in case there's no URL niceness
			$text .= Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() );
			$text .= "\n\t<p><label>" . $this->msg( 'pf_createform_nameinput' )->escaped() .
				' ' . $this->msg( 'pf_createform_nameinputdesc' )->escaped() .
				Html::input( 'form_name', $form_name, 'text', [ 'size' => 25 ] );
			if ( !empty( $form_name_error_str ) ) {
				$text .= "\t" . Html::element( 'span', [ 'class' => 'error' ], $form_name_error_str );
			}
			$text .= "</label></p>\n";
		}

		$text .= $this->formCreationHTML( $form );

		$text .= "<h2> " . $this->msg( 'pf_createform_addelements' )->escaped() . " </h2>";
		$text .= "\t<p><label>" . $this->msg( 'pf_createform_addtemplate' )->escaped() . "\n";

		$select_body = "";
		foreach ( $all_templates as $template ) {
			$select_body .= "	" . Html::element( 'option', [ 'value' => $template ], $template ) . "\n";
		}
		$text .= "\t" . Html::rawElement( 'select', [ 'name' => 'new_template' ], $select_body ) . "\n</label>\n";

		// If a template has already been added, show a dropdown letting
		// the user choose where in the list to add a new dropdown.
		$select_body = "";
		foreach ( $form_items as $i => $fi ) {
			if ( $fi['type'] == 'template' ) {
				$option_str = $this->msg( 'pf_createform_template' )->escaped();
			} elseif ( $fi['type'] == 'section' ) {
				$option_str = $this->msg( 'pf_createform_pagesection' )->escaped();
			}
			$option_str .= $fi['name'];
			$select_body .= "\t" . Html::element( 'option', [ 'value' => $i ], $option_str ) . "\n";
		}
		$final_index = count( $form_items );
		$at_end_msg = $this->msg( 'pf_createform_atend' )->escaped();
		$select_body .= "\t" . Html::element( 'option', [ 'value' => $final_index, 'selected' => 'selected' ], $at_end_msg );

		// Selection for before which item this template should be placed
		if ( count( $form_items ) > 0 ) {
			$text .= '<label>' . $this->msg( 'pf_createform_before' )->escaped() .
				Html::rawElement( 'select', [ 'name' => 'before_template' ], $select_body ) .
				"\n</label>\n";
		}

		// Disable 'save' and 'preview' buttons if user has not yet
		// added any templates.
		$add_button_text = $this->msg( 'pf_createform_add' )->text();
		$text .= "\t" . Html::input( 'add_field', $add_button_text, 'submit' ) . "\n";

		// The form HTML for page sections
		$text .= "<br/></br/>" . Html::element( 'span', null, $this->msg( 'pf_createform_addsection' )->text() . ":" ) . "\n";
		$text .= Html::input( 'sectionname', '', 'text', [ 'size' => '30', 'placeholder' => $this->msg( 'pf_createform_sectionname' )->text(), 'id' => 'sectionname' ] ) . "\n";

		// Selection for before which item this section should be placed
		if ( count( $form_items ) > 0 ) {
			$text .= $this->msg( 'pf_createform_before' )->escaped();
			$text .= Html::rawElement( 'select', [ 'name' => 'before_section' ], $select_body ) . "\n";
		}

		$add_section_text = $this->msg( 'pf_createform_addsection' )->text();
		$text .= "\t" . Html::input( 'add_section', $add_section_text, 'submit', [ 'id' => 'addsection' ] );
		$text .= "\n\t" . Html::rawElement( 'div', [ 'id' => 'sectionerror' ] );
		$text .= <<<END
</p>
	<br />

END;

		$text .= "\t" . Html::hidden( 'csrf', $this->getUser()->getEditToken( 'CreateForm' ) ) . "\n";

		$saveAttrs = [ 'id' => 'wpSave' ];
		if ( count( $form_items ) == 0 ) {
			$saveAttrs['disabled'] = true;
		}
		$editButtonsText = "\t" . Html::input( 'wpSave', $this->msg( 'savearticle' )->text(), 'submit', $saveAttrs ) . "\n";
		$previewAttrs = [ 'id' => 'wpPreview' ];
		if ( count( $form_items ) == 0 ) {
			$previewAttrs['disabled'] = true;
		}
		$editButtonsText .= "\t" . Html::input( 'wpPreview', $this->msg( 'preview' )->text(), 'submit', $previewAttrs ) . "\n";
		$text .= "\t" . Html::rawElement( 'div', [ 'class' => 'editButtons' ],
			Html::rawElement( 'p', [], $editButtonsText ) . "\n" ) . "\n";
		// Explanatory message if buttons are disabled because no
		// templates have been added.
		if ( count( $form_items ) == 0 ) {
			$text .= "\t" . Html::element( 'p', null, "(" . $this->msg( 'pf_createform_additembeforesave' )->text() . ")" );
		}
		$text .= <<<END
	</form>

END;

		$out->addHTML( $text );
	}

	function formCreationHTML( $form ) {
		$text = "";
		$template_count = 0;
		$section_count = 0;
		foreach ( $form->getItems() as $item ) {
			if ( $item['type'] == 'template' ) {
				$template = $item['item'];
				$text .= $this->templateCreationHTML( $template, $template_count );
				$template_count++;
			} elseif ( $item['type'] == 'section' ) {
				$section = $item['item'];
				$text .= $this->sectionCreationHTML( $section, $section_count );
				$section_count++;
			}
		}

		return $text;
	}

	function sectionCreationHTML( $section, $section_count ) {
		$paramValues = [];
		$section_name = $section->getSectionName();
		$section_level = $section->getSectionLevel();

		$section_str = $this->msg( 'pf_createform_pagesection' )->text() . " '" . $section_name . "'";
		$text = Html::hidden( "section_$section_count", $section_name );
		$text .= '<div class="sectionForm">';
		$text .= Html::element( 'h2', [], $section_str );

		foreach ( $this->getRequest()->getValues() as $key => $value ) {
			if ( ( $pos = strpos( $key, '_section_' . $section_count ) ) != false ) {
				$paramName = substr( $key, 0, $pos );
				$paramName = str_replace( '_', ' ', $paramName );
				$paramValues[$paramName] = $value;
			}
		}

		$header_options = '';
		$text .= '<label>' . $this->msg( 'pf_createform_sectionlevel' )->text() . "\n";
		for ( $i = 1; $i < 7; $i++ ) {
			if ( $section_level == $i ) {
				$header_options .= " " . Html::element( 'option', [ 'value' => $i, 'selected' ], $i ) . "\n";
			} else {
				$header_options .= " " . Html::element( 'option', [ 'value' => $i ], $i ) . "\n";
			}
		}
		$text .= Html::rawElement( 'select', [ 'name' => "level_section_" . $section_count ], $header_options ) . "</label>\n";
		$other_param_text = $this->msg( 'pf_createform_otherparameters' )->escaped();
		$text .= "<fieldset class=\"pfCollapsibleFieldset\"><legend>$other_param_text</legend>\n";
		$text .= Html::rawElement( 'div', [],
			$this->showSectionParameters( $section_count, $paramValues ) ) . "\n";
		$text .= "</fieldset>\n";
		$removeSectionButton = Html::input( 'delsection_' . $section_count, $this->msg( 'pf_createform_removesection' )->text(), 'submit' ) . "\n";
		$text .= "<br />" . Html::rawElement( 'p', null, $removeSectionButton ) . "\n";
		$text .= "	</div>\n";

		return $text;
	}

	function templateCreationHTML( $tif, $template_num ) {
		$checked_attribs = ( $tif->allowsMultiple() ) ? [ 'checked' => 'checked' ] : [];
		$template_str = $this->msg( 'pf_createform_template' )->escaped();
		$template_label_input = $this->msg( 'pf_createform_templatelabelinput' )->escaped();
		$allow_multiple_text = $this->msg( 'pf_createform_allowmultiple' )->escaped();

		$text = Html::hidden( "template_$template_num", $tif->getTemplateName() );
		$text .= '<div class="templateForm">';
		$text .= Html::element( 'h2', [], "$template_str '{$tif->getTemplateName()}'" );
		$text .= '<p><label>' . $template_label_input .
			Html::input( "label_$template_num", $tif->getLabel(), 'text', [ 'size' => 25 ] ) .
			"</label></p>\n";
		$text .= '<p><label>' .
			Html::input( "allow_multiple_$template_num", '', 'checkbox', $checked_attribs ) .
			$allow_multiple_text . "</label></p>\n";
		$text .= '<hr />';

		foreach ( $tif->getFields() as $field_num => $field ) {
			$text .= $this->fieldCreationHTML( $field, $field_num, $template_num );
		}
		$removeTemplateButton = Html::input(
			'del_' . $template_num,
			$this->msg( 'pf_createform_removetemplate' )->text(),
			'submit'
		);
		$text .= "\t" . Html::rawElement( 'p', null, $removeTemplateButton ) . "\n";
		$text .= "	</div>\n";
		return $text;
	}

	function fieldCreationHTML( $field, $field_num, $template_num ) {
		$field_form_text = $template_num . "_" . $field_num;
		$template_field = $field->template_field;
		$text = Html::element( 'h3', null, $this->msg( 'pf_createform_field' )->text() . " " . $template_field->getFieldName() ) . "\n";

		if ( !defined( 'SMW_VERSION' ) || $template_field->getSemanticProperty() == "" ) {
			// Print nothing if there's no semantic property.
		} elseif ( $template_field->getPropertyType() == "" ) {
			$prop_link_text = PFUtils::linkText( SMW_NS_PROPERTY, $template_field->getSemanticProperty() );
			$text .= $this->msg( 'pf_createform_fieldpropunknowntype', $prop_link_text )->parseAsBlock() . "\n";
		} else {
			if ( $template_field->isList() ) {
				$propDisplayMsg = 'pf_createform_fieldproplist';
			} else {
				$propDisplayMsg = 'pf_createform_fieldprop';
			}
			$prop_link_text = PFUtils::linkText( SMW_NS_PROPERTY, $template_field->getSemanticProperty() );

			// Get the display label for this property type.
			$propertyTypeStr = '';
			$smwContLang = PFUtils::getSMWContLang();
			if ( $smwContLang != null ) {
				$datatypeLabels = $smwContLang->getDatatypeLabels();
				$datatypeLabels['enumeration'] = 'enumeration';

				$propTypeID = $template_field->getPropertyType();

				// Special handling for SMW 1.9
				if ( $propTypeID == '_str' && !array_key_exists( '_str', $datatypeLabels ) ) {
					$propTypeID = '_txt';
				}
				$propertyTypeStr = $datatypeLabels[$propTypeID];
			}
			$text .= Html::rawElement( 'p', null, $this->msg( $propDisplayMsg, $prop_link_text, $propertyTypeStr )->parse() ) . "\n";
		}
		// If it's not a semantic field - don't add any text.
		$form_label_text = $this->msg( 'pf_createform_formlabel' )->escaped();
		$form_label_input = Html::input(
			'label_' . $field_form_text,
			$template_field->getLabel(),
			'text',
			[ 'size' => 20 ]
		);
		$input_type_text = $this->msg( 'pf_createform_inputtype' )->escaped();
		$text .= <<<END
	<div class="formField">
	<p><label>$form_label_text $form_label_input</label>
	&#160; <label>$input_type_text

END;
		global $wgPageFormsFormPrinter;
		if ( $template_field->getPropertyType() !== null ) {
			$default_input_type = $wgPageFormsFormPrinter->getDefaultInputTypeSMW( $template_field->isList(), $template_field->getPropertyType() );
			$possible_input_types = $wgPageFormsFormPrinter->getPossibleInputTypesSMW( $template_field->isList(), $template_field->getPropertyType() );
		} elseif ( $template_field->getFieldType() !== null ) {
			$default_input_type = $wgPageFormsFormPrinter->getDefaultInputTypeCargo( $template_field->isList(), $template_field->getFieldType() );
			$possible_input_types = $wgPageFormsFormPrinter->getPossibleInputTypesCargo( $template_field->isList(), $template_field->getFieldType() );
		} else {
			// Most likely, template uses neither SMW nor Cargo.
			$default_input_type = null;
			$possible_input_types = [];
		}

		if ( $default_input_type == null && count( $possible_input_types ) == 0 ) {
			$default_input_type = null;
			$possible_input_types = $wgPageFormsFormPrinter->getAllInputTypes();
		}
		$text .= $this->inputTypeDropdownHTML( $field_form_text, $default_input_type, $possible_input_types, $field->getInputType() ) . "</label>\n";

		if ( $field->getInputType() !== null ) {
			$cur_input_type = $field->getInputType();
		} elseif ( $default_input_type !== null ) {
			$cur_input_type = $default_input_type;
		} else {
			$cur_input_type = $possible_input_types[0];
		}

		$paramValues = [];
		foreach ( $this->getRequest()->getValues() as $key => $value ) {
			if ( ( $pos = strpos( $key, '_' . $field_form_text ) ) != false ) {
				$paramName = substr( $key, 0, $pos );
				// Spaces got replaced by underlines in the
				// query.
				$paramName = str_replace( '_', ' ', $paramName );
				$paramValues[$paramName] = $value;
			}
		}

		$other_param_text = $this->msg( 'pf_createform_otherparameters' )->escaped();
		$text .= "<fieldset class=\"pfCollapsibleFieldset\"><legend>$other_param_text</legend>\n";
		$text .= Html::rawElement( 'div', [ 'class' => 'otherInputParams' ],
			$this->showInputTypeOptions( $cur_input_type, $field_form_text, $paramValues ) ) . "\n";
		$text .= "</fieldset>\n";
		$text .= <<<END
	</p>
	</div>
	<hr>

END;
		return $text;
	}

	function inputTypeDropdownHTML( $field_form_text, $default_input_type, $possible_input_types, $cur_input_type ) {
		if ( $default_input_type !== null ) {
			array_unshift( $possible_input_types, $default_input_type );
		}
		// create the dropdown HTML for a list of possible input types
		$dropdownHTML = "";
		foreach ( $possible_input_types as $i => $input_type ) {
			if ( $i == 0 ) {
				$dropdownHTML .= "	<option value=\".$input_type\">$input_type " .
					$this->msg( 'pf_createform_inputtypedefault' )->escaped() . "</option>\n";
			} else {
				$selected_str = ( $cur_input_type == $input_type ) ? "selected" : "";
				$dropdownHTML .= "	<option value=\"$input_type\" $selected_str>$input_type</option>\n";
			}
		}
		$hidden_text = $this->msg( 'pf_createform_hidden' )->escaped();
		$selected_str = ( $cur_input_type == 'hidden' ) ? "selected" : "";
		// @todo FIXME: Contains hard coded parentheses.
		$dropdownHTML .= "	<option value=\"hidden\" $selected_str>($hidden_text)</option>\n";
		$text = "\t" . Html::rawElement( 'select',
			[
				'class' => 'inputTypeSelector',
				'name' => 'input_type_' . $field_form_text,
				'formfieldid' => $field_form_text
			], $dropdownHTML ) . "\n";
		return $text;
	}

	/**
	 * Prints an input for a form-field parameter.
	 * Code borrowed from Semantic MediaWiki's
	 * SMWAskPage::addOptionInput().
	 * @param string $type
	 * @param string $paramName
	 * @param string $cur_value
	 * @param array $param
	 * @param array $paramValues
	 * @param string $fieldFormText
	 * @return string
	 */
	public static function inputTypeParamInput( $type, $paramName, $cur_value, array $param, array $paramValues, $fieldFormText ) {
		if ( $type == 'int' ) {
			return Html::input(
				$paramName . '_' . $fieldFormText,
				$cur_value,
				'text',
				[ 'size' => 6 ]
			);
		} elseif ( $type == 'string' ) {
			return Html::input(
				$paramName . '_' . $fieldFormText,
				$cur_value,
				'text',
				[ 'size' => 32 ]
			);
		} elseif ( $type == 'text' ) {
			return Html::element( 'textarea', [
				'name' => $paramName . '_' . $fieldFormText,
				'rows' => 4
			], $cur_value );
		} elseif ( $type == 'enumeration' ) {
			$selectBody = Html::element( 'option' ) . "\n";
			foreach ( $param['values'] as $value ) {
				$optionAttrs = [ 'value' => $value ];
				if ( $cur_value == $value ) {
					$optionAttrs['selected'] = true;
				}
				$selectBody .= Html::element( 'option', $optionAttrs, $value ) . "\n";
			}

			return Html::rawElement( 'select', [ 'name' => 'p[' . $paramName . ']' ], $selectBody );
		} elseif ( $type == 'enum-list' ) {
			$cur_values = explode( ',', $cur_value );
			foreach ( $param['values'] as $val ) {
				$checkboxName = 'p[' . $paramName . '][' . $val . ']';
				$checkboxAttrs = [];
				if ( in_array( $val, $cur_values ) ) {
					$checkboxAttrs['checked'] = true;
				}
				$checkboxHTML = Html::input( $checkboxName, 'true', 'checkbox', $checkboxAttrs );
				$text .= Html::rawElement( 'span', [
						'style' => 'white-space: nowrap; padding-right: 5px; font-family: monospace;'
					], $checkboxHTML );
			}
			return $text;
		} elseif ( $type == 'boolean' ) {
			$checkboxAttrs = [];
			if ( $cur_value ) {
				$checkboxAttrs['checked'] = true;
			}
			return Html::input( $paramName . '_' . $fieldFormText, null, 'checkbox', $checkboxAttrs );
		}
	}

	/**
	 * Display a form section showing the options for a given format,
	 * based on the getParameters() value for that format's query printer.
	 *
	 * @param string $inputType
	 * @param string $fieldFormText
	 * @param array $paramValues
	 *
	 * @return string
	 */
	public function showInputTypeOptions( $inputType, $fieldFormText, $paramValues ) {
		global $wgPageFormsFormPrinter;

		$text = '';

		// Handle default types, which start with a '.' to differentiate
		// them.
		if ( substr( $inputType, 0, 1 ) == '.' ) {
			$inputType = substr( $inputType, 1 );
		}

		$inputTypeClass = $wgPageFormsFormPrinter->getInputType( $inputType );

		$params = method_exists( $inputTypeClass, 'getParameters' ) ? call_user_func( [ $inputTypeClass, 'getParameters' ] ) : [];

		$i = 0;
		foreach ( $params as $param ) {
			$paramName = $param['name'];
			$type = $param['type'];
			$desc = PFUtils::getParser()->parse( $param['description'], $this->getPageTitle(), ParserOptions::newFromUser( $this->getUser() ) )->getText();

			if ( array_key_exists( $paramName, $paramValues ) ) {
				$cur_value = $paramValues[$paramName];
			} elseif ( array_key_exists( 'default', $param ) ) {
				$cur_value = $param['default'];
			} else {
				$cur_value = '';
			}

			// 3 values per row, with alternating colors for rows
			if ( $i % 3 == 0 ) {
				$bgcolor = ( $i % 6 ) == 0 ? '#eee' : 'white';
				$text .= "<div style=\"background: $bgcolor;\">";
			}

			$text .= "<div style=\"width: 30%; padding: 5px; float: left;\">\n<label>$paramName:\n";

			$text .= self::inputTypeParamInput( $type, $paramName, $cur_value, $param, [], $fieldFormText );
			$text .= "\n</label>\n<br />" . Html::rawElement( 'em', null, $desc ) . "\n</div>\n";

			if ( $i % 3 == 2 || $i == count( $params ) - 1 ) {
				$text .= "<div style=\"clear: both;\"></div></div>\n";
			}
			++$i;
		}
		return $text;
	}

	/**
	 * Display other parameters for a page section
	 *
	 * @param int $section_count
	 * @param array $paramValues
	 * @return string
	 */
	function showSectionParameters( $section_count, $paramValues ) {
		$text = '';
		$section_text = 'section_' . $section_count;

		$params = PFPageSection::getParameters();
		$i = 0;
		foreach ( $params as $param ) {
			$paramName = $param['name'];
			$type = $param['type'];
			$desc = PFUtils::getParser()->parse( $param['description'], $this->getPageTitle(), ParserOptions::newFromUser( $this->getUser() ) )->getText();

			if ( array_key_exists( $paramName, $paramValues ) ) {
				$cur_value = $paramValues[$paramName];
			} elseif ( array_key_exists( 'default', $param ) ) {
				$cur_value = $param['default'];
			} else {
				$cur_value = '';
			}

			// 3 values per row, with alternating colors for rows
			if ( $i % 3 == 0 ) {
				$bgcolor = ( $i % 6 ) == 0 ? '#ecf0f6' : 'white';
				$text .= "<div style=\"background: $bgcolor;\">";
			}

			$text .= "<div style=\"width: 30%; padding: 5px; float: left;\">\n<label>$paramName:\n";

			$text .= self::inputTypeParamInput( $type, $paramName, $cur_value, $param, [], $section_text );
			$text .= "\n</label>\n<br />" . Html::rawElement( 'em', null, $desc ) . "\n</div>\n";
			if ( $i % 3 == 2 || $i == count( $params ) - 1 ) {
				$text .= "<div style=\"clear: both\";></div></div>\n";
			}
			++$i;
		}
		return $text;
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
