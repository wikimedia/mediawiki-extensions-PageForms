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
		$out->enableOOUI();

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
		while ( $row = $res->fetchRow() ) {
			$all_templates[] = str_replace( '_', ' ', $row['page_title'] );
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
						$pos = strpos( $key, '_' . $old_i . '_' . $j );
						if ( $pos !== false ) {
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
					$pos = strpos( $key, '_section_' . $old_i );
					if ( $pos !== false ) {
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
				$text = PFUtils::printRedirectForm( $title, $full_text, "", $save_page, $this->getUser() );
				$out->addHTML( $text );
				return;
			}
		}

		$text = "\t" . '<form action="" method="post">' . "\n";
		if ( $presetFormName === null ) {
			// Set 'title' field, in case there's no URL niceness
			$text .= Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() );
			$formNameItems = [];
			$formNameText = new OOUI\LabelWidget( [
				'label' => $this->msg( 'pf_createform_nameinput' )->escaped() . ' ' . $this->msg( 'pf_createform_nameinputdesc' )->escaped(),
			] );
			$formNameTextInput = new OOUI\TextInputWidget( [
				'name' => 'form_name',
				'id' => 'pfFormName',
				'value' => $form_name
			] );
			array_push( $formNameItems, $formNameText, $formNameTextInput );
			$text .= "\n\t<p><label>";
			if ( !empty( $form_name_error_str ) ) {
				$blankError = new OOUI\MessageWidget( [
					"type" => 'error',
					"inline" => true,
					"label" => $form_name_error_str
				] );
				array_push( $formNameItems, $blankError );
			}
			$formNameHtml = new OOUI\HorizontalLayout( [
				'items' => $formNameItems,
			] );
			$text .= $formNameHtml . "</label></p>\n";
		}

		$text .= $this->formCreationHTML( $form );

		$text .= "<h2> " . $this->msg( 'pf_createform_addelements' )->escaped() . " </h2>";
		$options = [];
		foreach ( $all_templates as $template ) {
			$new_option = [ 'data' => $template, 'label' => $template ];
			array_push( $options, $new_option );
		}
		$items = [];

		// "Add Template" implemented using Label Widget
		$addTemplateText = new OOUI\LabelWidget( [
			'label' => $this->msg( 'pf_createform_addtemplate' )->escaped()
		] );

		$addTemplateDropdown = new OOUI\DropdownInputWidget( [
			'options' => $options,
			'id' => 'pfAddTemplateDropdown',
			'name' => 'new_template',
		] );

		// If a template has already been added, show a dropdown letting
		// the user choose where in the list to add a new dropdown.
		$options = [];
		foreach ( $form_items as $i => $fi ) {
			if ( $fi['type'] == 'template' ) {
				$option_str = $this->msg( 'pf_createform_template' )->escaped();
			} elseif ( $fi['type'] == 'section' ) {
				$option_str = $this->msg( 'pf_createform_pagesection' )->escaped();
			}
			$option_str .= $fi['name'];
			$new_option = [ 'data' => $i, 'label' => $option_str ];
			array_push( $options, $new_option );
		}
		$final_index = count( $form_items );
		$at_end_msg = $this->msg( 'pf_createform_atend' )->escaped();
		$new_option = [ 'label' => $at_end_msg, 'data' => $final_index ];
		array_push( $options, $new_option );

		// "Before" dropdown
		$addTemplateBeforeDropdown = new OOUI\DropdownInputWidget( [
			'options' => $options,
			'name' => 'before_template',
			'id' => 'pfAddTemplateBeforeDropdown',
			'value' => $final_index,
		] );

		$addButton = new OOUI\ButtonInputWidget( [
			'label' => $this->msg( 'pf_createform_add' )->text(),
			'type' => 'submit',
			'icon' => 'add',
			'id' => 'pfAddTemplateButton',
			'name' => 'add_field',
			'flags' => [ 'progressive' ],
		] );

		// Selection for before which item this template should be placed
		if ( count( $form_items ) > 0 ) {
			$addTemplateHtml = new OOUI\HorizontalLayout( [
				'items' => [
					$addTemplateText,
					$addTemplateDropdown,
					new OOUI\LabelWidget( [
						'label' => $this->msg( 'pf_createform_before' )->escaped(),
					] ),
					$addTemplateBeforeDropdown,
					$addButton,
				],
			] );
		} else {
			$addTemplateHtml = new OOUI\HorizontalLayout( [
				'items' => [
					$addTemplateText,
					$addTemplateDropdown,
					$addButton,
				],
			] );
		}

		// Disable 'save' and 'preview' buttons if user has not yet
		// added any templates.
		$text .= "\t" . $addTemplateHtml;

		// The form HTML for page sections
		// "Add Section" implemented with LabelWidget
		$addSectionItems = [];
		$addSectionText = new OOUI\LabelWidget( [
			'label' => $this->msg( 'pf_createform_addsection' )->text() . ":",
		] );

		$addSectionTextInput = new OOUI\TextInputWidget( [
			'name' => 'sectionname',
			'id' => 'pfAddSectionTextInput',
		] );

		array_push( $addSectionItems, $addSectionText, $addSectionTextInput );

		// Selection for before which item this section should be placed
		if ( count( $form_items ) > 0 ) {

			$addSectionBeforeText = new OOUI\LabelWidget( [
				'label' => $this->msg( 'pf_createform_before' )->escaped(),
			] );

			$addSectionBefore = new OOUI\DropdownInputWidget( [
				'options' => $options,
				'name' => 'before_section',
				'id' => 'pfAddSectionBefore',
				'value' => $final_index,
			] );
			array_push( $addSectionItems, $addSectionBeforeText, $addSectionBefore );
		}

		$addSectionButton = new OOUI\ButtonInputWidget( [
			'label' => $this->msg( 'pf_createform_addsection' )->text(),
			'type' => 'submit',
			'icon' => 'add',
			'id' => 'pfAddSectionButton',
			'name' => 'add_section',
			'flags' => [ 'progressive' ],
		] );

		array_push( $addSectionItems, $addSectionButton );

		$addSectionHtml = new OOUI\HorizontalLayout( [
			'items' => $addSectionItems,
		] );

		$text .= "<br/>" . $addSectionHtml;
		$text .= "\n\t" . Html::rawElement( 'div', [ 'id' => 'sectionerror' ] );
		$text .= <<<END
</p>
	<br />

END;

		$text .= "\t" . Html::hidden( 'csrf', $this->getUser()->getEditToken( 'CreateForm' ) ) . "\n";

		$saveAttrs = [
			'id' => 'wpSave',
			'name' => 'wpSave',
			'type' => 'submit',
			'label' => $this->msg( 'savearticle' )->text(),
			'useInputTag' => true,
			'flags' => [ 'primary', 'progressive' ]
		];
		if ( count( $form_items ) == 0 ) {
			$saveAttrs['disabled'] = true;
		}
		$saveButton = new OOUI\ButtonInputWidget( $saveAttrs );

		$editButtonsText = "\t" . $saveButton . "\n";

		$previewAttrs = [
			'id' => 'wpPreview',
			'name' => 'wpPreview',
			'type' => 'submit',
			'label' => $this->msg( 'preview' )->text(),
			'useInputTag' => true,
			'flags' => [ 'progressive' ]
		];
		if ( count( $form_items ) == 0 ) {
			$previewAttrs['disabled'] = true;
		}
		$previewButton = new OOUI\ButtonInputWidget( $previewAttrs );

		$editButtonsText .= "\t" . $previewButton . "\n";
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
			$pos = strpos( $key, '_section_' . $section_count );
			if ( $pos !== false ) {
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
		$removeSectionButton = new OOUI\ButtonInputWidget( [
			'label' => $this->msg( 'pf_createform_removesection' )->text(),
			'type' => 'submit',
			'icon' => 'subtract',
			'id' => 'pfRemoveSectionButton',
			'name' => 'delsection_' . $section_count,
			'flags' => [ 'destructive' ],
		] ) . "\n";
		$text .= "<br />" . $removeSectionButton . "\n";
		$text .= "	</div>\n";

		return $text;
	}

	function templateCreationHTML( $tif, $template_num ) {
		$template_str = $this->msg( 'pf_createform_template' )->escaped();
		$template_label_input = $this->msg( 'pf_createform_templatelabelinput' )->escaped();
		$allow_multiple_text = $this->msg( 'pf_createform_allowmultiple' )->escaped();

		$text = Html::hidden( "template_$template_num", $tif->getTemplateName() );
		$text .= '<div class="templateForm">';
		$text .= Html::element( 'h2', [], "$template_str '{$tif->getTemplateName()}'" );
		$text .= new OOUI\HorizontalLayout( [
			'items' => [
				new OOUI\LabelWidget( [
					'label' => $template_label_input
				] ),
				new OOUI\TextInputWidget( [
					'name' => "label_$template_num",
					'value' => $tif->getLabel(),
					'classes' => [ 'pfTemplateLabel' ]
				] )
			]
		] );
		$text .= new OOUI\HorizontalLayout( [
			'items' => [
				new OOUI\CheckboxInputWidget( [
					'name' => "allow_multiple_$template_num",
					'selected' => ( $tif->allowsMultiple() ) ? true : false,
					'value' => ( $tif->allowsMultiple() ) ? 'on' : ''
				] ),
				new OOUI\LabelWidget( [
					'label' => $allow_multiple_text
				] )
			]
		] );
		$text .= '<hr />';

		foreach ( $tif->getFields() as $field_num => $field ) {
			$text .= $this->fieldCreationHTML( $field, $field_num, $template_num );
		}
		$removeTemplateButton = new OOUI\ButtonInputWidget( [
			'label' => $this->msg( 'pf_createform_removetemplate' )->text(),
			'type' => 'submit',
			'icon' => 'subtract',
			'id' => 'pfRemoveTemplateButton',
			'name' => 'del_' . $template_num,
			'flags' => [ 'destructive' ],
		] );
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
		$input_type_text = $this->msg( 'pf_createform_inputtype' )->escaped();

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

		if ( $field->getInputType() !== null ) {
			$cur_input_type = $field->getInputType();
		} elseif ( $default_input_type !== null ) {
			$cur_input_type = $default_input_type;
		} else {
			$cur_input_type = $possible_input_types[0];
		}

		$formFieldRow = new OOUI\HorizontalLayout( [
			'items' => [
				new OOUI\LabelWidget( [
					'label' => $form_label_text
				] ),
				new OOUI\TextInputWidget( [
					'name' => "label_$field_form_text",
					'value' => $template_field->getLabel(),
					'classes' => [ 'pfFormLabel' ]
				] ),
				new OOUI\LabelWidget( [
					'label' => $input_type_text
				] ),
				$this->inputTypeDropdownHTML( $field_form_text, $default_input_type, $possible_input_types, $field->getInputType() )
			]
		] );

		$text .= <<<END
	<div class="formField">
	$formFieldRow

END;
		$paramValues = [];
		foreach ( $this->getRequest()->getValues() as $key => $value ) {
			$pos = strpos( $key, '_' . $field_form_text );
			if ( $pos !== false ) {
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
		$dropdownAttrs = [];
		foreach ( $possible_input_types as $i => $input_type ) {
			if ( $i == 0 ) {
				array_push( $dropdownAttrs, [ 'data' => $input_type, 'label' => $input_type . ' ' . $this->msg( 'pf_createform_inputtypedefault' )->escaped() ] );
			} else {
				$value = ( $cur_input_type == $input_type ) ? $input_type : "";
				array_push( $dropdownAttrs, [ 'data' => $input_type, 'label' => $input_type ] );
			}
		}
		array_push( $dropdownAttrs, [ 'data' => 'hidden', 'label' => $this->msg( 'pf_createform_hidden' )->escaped() ] );
		$value = ( $cur_input_type == 'hidden' ) ? 'hidden' : "";
		$text = new OOUI\DropdownInputWidget( [
			'classes' => [ 'inputTypeSelector' ],
			'name' => 'input_type_' . $field_form_text,
			'id' => $field_form_text,
			'options' => $dropdownAttrs
		] );
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
			return new OOUI\TextInputWidget( [
				'name' => $paramName . '_' . $fieldFormText,
				'value' => $cur_value,
				'classes' => [ 'pfTextFieldForInt' ]
			] );
		} elseif ( $type == 'string' ) {
			return new OOUI\TextInputWidget( [
				'name' => $paramName . '_' . $fieldFormText,
				'value' => $cur_value,
				'classes' => [ 'pfTextFieldForString' ]
			] );
		} elseif ( $type == 'text' ) {
			return new OOUI\MultilineTextInputWidget( [
				'name' => $paramName . '_' . $fieldFormText,
				'rows' => 4,
				'value' => $cur_value
			] );
		} elseif ( $type == 'enumeration' ) {
			$val = '';
			foreach ( $param['values'] as $value ) {
				$optionAttrs = [ 'value' => $value ];
				if ( $cur_value == $value ) {
					$val = $value;
				}
			}
			return new OOUI\DropdownInputWidget( [
				'name' => 'p[' . $paramName . ']',
				'options' => $optionAttrs,
				'value' => $val
			] );
		} elseif ( $type == 'enum-list' ) {
			$cur_values = explode( ',', $cur_value );
			$text = '';
			foreach ( $param['values'] as $val ) {
				$checkboxHTML = new OOUI\CheckboxInputWidget( [
					'name' => 'p[' . $paramName . '][' . $val . ']',
					'selected' => in_array( $val, $cur_values ) ? true : false,
					'value' => in_array( $val, $cur_values ) ? 'on' : ''
				] );
				$text .= Html::rawElement( 'span', [
						'style' => 'white-space: nowrap; padding-right: 5px; font-family: monospace;'
					], $checkboxHTML );
			}
			return $text;
		} elseif ( $type == 'boolean' ) {
			return new OOUI\CheckboxInputWidget( [
				'name' => $paramName . '_' . $fieldFormText,
				'selected' => $cur_value ? true : false,
				'value' => $cur_value ? 'on' : ''
			] );
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
