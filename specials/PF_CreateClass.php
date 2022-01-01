<?php
/**
 * A special page holding a form that allows the user to create a semantic
 * property.
 *
 * @author Yaron Koren
 * @author Sanyam Goyal
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFSpecialPages
 */
class PFCreateClass extends SpecialPage {

	public function __construct() {
		parent::__construct( 'CreateClass', 'createclass' );
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $query ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$out->enableOOUI();
		// Check permissions.
		if ( !$this->getUser()->isAllowed( 'createclass' ) ) {
			$this->displayRestrictionError();
		}
		$this->printCreateClassForm( $query );
	}

	private function createAllPages() {
		$out = $this->getOutput();
		$req = $this->getRequest();
		$user = $this->getUser();

		$template_name = trim( $req->getVal( "template_name" ) );
		$template_multiple = $req->getBool( "template_multiple" );
		$use_cargo = trim( $req->getBool( "use_cargo" ) );
		$cargo_table = trim( $req->getVal( "cargo_table" ) );
		// If this is a multiple-instance template, there
		// shouldn't be a corresponding form or category.
		if ( $template_multiple ) {
			$form_name = null;
			$category_name = null;
		} else {
			$form_name = trim( $req->getVal( "form_name" ) );
			$category_name = trim( $req->getVal( "category_name" ) );
		}
		if ( $template_name === '' || ( !$template_multiple && $form_name === '' ) ||
			( $use_cargo && ( $cargo_table === '' ) ) ) {
			$out->addWikiMsg( 'pf_createclass_missingvalues' );
			return;
		}
		$fields = [];
		$jobs = [];
		$allowedValuesForFields = [];
		// Cycle through all the rows passed in.
		for ( $i = 1; $req->getVal( "name_$i" ) != ''; $i++ ) {
			// Go through the query values, setting the appropriate
			// local variables.
			$field_name = trim( $req->getVal( "name_$i" ) );
			$display_label = trim( $req->getVal( "label_$i" ) );
			$display_label = $display_label ?: $field_name;
			$property_name = trim( $req->getVal( "property_name_$i" ) );
			$property_type = $req->getVal( "field_type_$i" );
			$allowed_values = $req->getVal( "allowed_values_$i" );
			$is_list = $req->getCheck( "is_list_$i" );
			$is_hierarchy = $req->getCheck( "is_hierarchy_$i" );
			// Create an PFTemplateField object based on these
			// values, and add it to the $fields array.
			$field = PFTemplateField::create( $field_name, $display_label, $property_name, $is_list );

			if ( defined( 'CARGO_VERSION' ) ) {
				// Hopefully it's safe to use a Cargo
				// utility method here.
				$possibleValues = CargoUtils::smartSplit( ',', $allowed_values );
				if ( $is_hierarchy ) {
					$field->setHierarchyStructure( $req->getVal( 'hierarchy_structure_' . $i ) );
				} else {
					$field->setPossibleValues( $possibleValues );
				}
				if ( $use_cargo ) {
					$cargo_field = str_replace( ' ', '_', $field_name );
					$field->setCargoFieldData( $cargo_table, $cargo_field );
					$field->setFieldType( $property_type );
					$field->setPossibleValues( $possibleValues );
				} else {
					if ( $allowed_values != '' ) {
						$allowedValuesForFields[$field_name] = $allowed_values;
					}
				}

			}

			$fields[] = $field;

			// Create the property, and make a job for it.
			if ( defined( 'SMW_VERSION' ) && !empty( $property_name ) ) {
				$property_title = Title::makeTitleSafe( SMW_NS_PROPERTY, $property_name );
				$full_text = PFCreateProperty::createPropertyText( $property_type, $allowed_values );
				$params = [
					'user_id' => $user->getId(),
					'page_text' => $full_text,
					'edit_summary' => $this->msg( 'pf_createproperty_editsummary', $property_type )->inContentLanguage()->text()
				];
				$jobs[] = new PFCreatePageJob( $property_title, $params );
			}
		}

		// Also create the "connecting property", if there is one.
		$connectingProperty = trim( $req->getVal( 'connecting_property' ) );
		if ( defined( 'SMW_VERSION' ) && $connectingProperty != '' ) {
			$property_title = Title::makeTitleSafe( SMW_NS_PROPERTY, $connectingProperty );
			$datatypeLabels = PFUtils::getSMWContLang()->getDatatypeLabels();
			$property_type = $datatypeLabels['_wpg'];
			$full_text = PFCreateProperty::createPropertyText( $property_type, $allowed_values );
			$params = [
				'user_id' => $user->getId(),
				'page_text' => $full_text,
				'edit_summary' => $this->msg( 'pf_createproperty_editsummary', $property_type )->inContentLanguage()->text()
			];
			$jobs[] = new PFCreatePageJob( $property_title, $params );
		}

		// Create the template, and save it (might as well save
		// one page, instead of just creating jobs for all of them).
		$template_format = $req->getVal( "template_format" );
		$pfTemplate = new PFTemplate( $template_name, $fields );
		if ( defined( 'CARGO_VERSION' ) && $use_cargo ) {
			$pfTemplate->setCargoTable( $cargo_table );
		}
		if ( defined( 'SMW_VERSION' ) && $template_multiple ) {
			$pfTemplate->setConnectingProperty( $connectingProperty );
		} else {
			$pfTemplate->setCategoryName( $category_name );
		}
		$pfTemplate->setFormat( $template_format );
		$full_text = $pfTemplate->createText();

		$template_title = Title::makeTitleSafe( NS_TEMPLATE, $template_name );
		$template_page = WikiPage::factory( $template_title );
		$edit_summary = '';
		PFCreatePageJob::createOrModifyPage( $template_page, $full_text, $edit_summary, $user );

		// Create the form, and make a job for it.
		if ( $form_name != '' ) {
			$formFields = [];
			foreach ( $fields as $field ) {
				$formField = PFFormField::create( $field );
				$fieldName = $field->getFieldName();
				if ( array_key_exists( $fieldName, $allowedValuesForFields ) ) {
					$formField->setInputType( 'dropdown' );
					$formField->setFieldArg( 'values', $allowedValuesForFields[$fieldName] );
				}
				$formFields[] = $formField;
			}
			$form_template = PFTemplateInForm::create( $template_name, '', false, false, $formFields );
			$form_items = [];
			$form_items[] = [
				'type' => 'template',
				'name' => $form_template->getTemplateName(),
				'item' => $form_template
			];

			$form_title = Title::makeTitleSafe( PF_NS_FORM, $form_name );
			$form = PFForm::create( $form_name, $form_items );
			if ( $category_name != '' ) {
				$form->setAssociatedCategory( $category_name );
			}
			$full_text = $form->createMarkup();
			$params = [
				'user_id' => $user->getId(),
				'page_text' => $full_text
			];
			$jobs[] = new PFCreatePageJob( $form_title, $params );
		}

		// Create the category, and make a job for it.
		if ( $category_name != '' ) {
			$full_text = PFCreateCategory::createCategoryText( $form_name, $category_name, '' );
			$category_title = Title::makeTitleSafe( NS_CATEGORY, $category_name );
			$params = [
				'user_id' => $user->getId(),
				'page_text' => $full_text
			];
			$jobs[] = new PFCreatePageJob( $category_title, $params );
		}

		JobQueueGroup::singleton()->push( $jobs );

		$out->addWikiMsg( 'pf_createclass_success' );
	}

	private function printCreateClassForm( $query ) {
		$lang = $this->getLanguage();
		$out = $this->getOutput();
		$req = $this->getRequest();

		$out->addModules( [ 'ext.pageforms.PF_CreateClass', 'ext.pageforms.main', 'ext.pageforms.PF_CreateTemplate' ] );
		$createAll = $req->getCheck( 'createAll' );
		if ( $createAll ) {
			// Guard against cross-site request forgeries (CSRF).
			$validToken = $this->getUser()->matchEditToken( $req->getVal( 'csrf' ), 'CreateClass' );
			if ( !$validToken ) {
				$text = "This appears to be a cross-site request forgery; canceling save.";
				$out->addHTML( $text );
				return;
			}
			$this->createAllPages();
			return;
		}

		if ( defined( 'SMW_VERSION' ) ) {
			$possibleTypes = PFUtils::getSMWContLang()->getDatatypeLabels();
		} elseif ( defined( 'CARGO_VERSION' ) ) {
			global $wgCargoFieldTypes;
			$possibleTypes = $wgCargoFieldTypes;
			$specialBGColor = '';
		} else {
			$possibleTypes = [];
		}

		// Make links to all the other 'Create...' pages, in order to
		// link to them at the top of the page.
		$creation_links = [];
		$linkRenderer = $this->getLinkRenderer();
		if ( defined( 'SMW_VERSION' ) ) {
			$creation_links[] = PFUtils::linkForSpecialPage( $linkRenderer, 'CreateProperty' );
		}
		$creation_links[] = PFUtils::linkForSpecialPage( $linkRenderer, 'CreateTemplate' );
		$creation_links[] = PFUtils::linkForSpecialPage( $linkRenderer, 'CreateForm' );
		$creation_links[] = PFUtils::linkForSpecialPage( $linkRenderer, 'CreateCategory' );

		$text = '<form id="createClassForm" action="" method="post">' . "\n";
		$text .= "\t" . Html::rawElement( 'p', null,
				$this->msg( 'pf_createclass_docu' )
					->rawParams( $lang->listToText( $creation_links ) )
					->escaped() ) . "\n";
		$templateNameLabel = $this->msg( 'pf_createtemplate_namelabel' )->escaped();
		$templateNameInput = Html::input( 'template_name', null, 'text', [ 'size' => 30 ] );
		$text .= "\t" . Html::rawElement( 'p', null, $templateNameLabel . ' ' . $templateNameInput ) . "\n";

		$templateInfo = '';
		if ( defined( 'CARGO_VERSION' ) && !defined( 'SMW_VERSION' ) ) {
			$templateInfo .= "\t<p><label id='cargo_toggle'>" .
				Html::hidden( 'use_cargo', true ) .
				$this->msg( 'pf_createtemplate_usecargo' )->escaped() .
				"</label></p>\n";
			$cargo_table_label = $this->msg( 'pf_createtemplate_cargotablelabel' )->escaped();
			$templateInfo .= "\t" . Html::rawElement( 'p', [ 'id' => 'cargo_table_input' ],
				Html::element( 'label', [ 'for' => 'cargo_table' ], $cargo_table_label ) . ' ' .
				Html::element( 'input', [ 'size' => '30', 'name' => 'cargo_table', 'id' => 'cargo_table' ], null )
			) . "\n";
		}
		$createTemplatePage = new PFCreateTemplate();
		$templateInfo .= $createTemplatePage->printTemplateStyleInput( 'template_format' );
		$templateInfo .= Html::rawElement( 'p', [ 'id' => 'template_multiple_p' ],
			Html::hidden( 'multiple_template', false ) . $this->msg( 'pf_createtemplate_multipleinstance' )->escaped() ) . "\n";
		// Either #set_internal or #subobject will be added to the
		// template, depending on whether Semantic Internal Objects is
		// installed.
		global $smwgDefaultStore;
		if ( defined( 'SIO_VERSION' ) || $smwgDefaultStore == "SMWSQLStore3" ) {
			$templateInfo .= Html::rawElement( 'div',
				[
					'id' => 'connecting_property_div',
					'style' => 'display: none;',
				],
				$this->msg( 'pf_createtemplate_connectingproperty' )->escaped() . "\n" .
				Html::element( 'input', [
					'type' => 'text',
					'name' => 'connecting_property',
				] ) ) . "\n";
		}
		$text .= Html::rawElement( 'blockquote', null, $templateInfo );

		$form_name_input = Html::element( 'input', [ 'size' => '30', 'name' => 'form_name', 'id' => 'form_name' ], null );
		$text .= "\t<p><label>" . $this->msg( 'pf_createclass_nameinput' )->escaped() . " $form_name_input</label></p>\n";
		$category_name_input = Html::element( 'input', [ 'size' => '30', 'name' => 'category_name', 'id' => 'category_name' ], null );
		$text .= "\t<p><label>" . $this->msg( 'pf_createcategory_name' )->escaped() . " $category_name_input</label></p>\n";

		$text .= "\t<fieldset>\n";
		$text .= "\t" . Html::element( 'legend', null, $this->msg( 'pf_createtemplate_templatefields' )->text() ) . "\n";
		$text .= "\t" . Html::element( 'p', null, $this->msg( 'pf_createtemplate_fieldsdesc' )->text() ) . "\n";

		if ( defined( 'SMW_VERSION' ) ) {
			$all_properties = PFCreateTemplate::getAllPropertyNames();
		} else {
			$all_properties = [];
		}
		$text .= '<div id="fieldsList">' . "\n";
		$text .= $createTemplatePage->printFieldEntryBox( "1", $all_properties );
		$text .= $createTemplatePage->printFieldEntryBox( "starter", $all_properties, false );
		$text .= "</div>\n";

		$add_field_button = new OOUI\ButtonWidget( [
			'label' => $this->msg( 'pf_createtemplate_addfield' )->text(),
			'classes' => [ 'createTemplateAddField' ],
			'icon' => 'add'
		] );
		$text .= new OOUI\FieldLayout( $add_field_button ) . "\n";
		$text .= "\t</fieldset>\n";

		if ( defined( 'SMW_VERSION' ) ) {
			$text .= "\t<fieldset>\n";
			$text .= "\t" . Html::element( 'legend', null, $this->msg( 'pf_createtemplate_aggregation' )->text() ) . "\n";
			$text .= "\t" . Html::element( 'p', null, $this->msg( 'pf_createtemplate_aggregationdesc' )->text() ) . "\n";
			$text .= "\t<p>" . $this->msg( 'pf_createtemplate_semanticproperty' )->escaped() . ' ' .
				PFCreateTemplate::printPropertiesComboBox( $all_properties, "aggregation" ) . "</p>\n";
			$text .= "\t<p>" . $this->msg( 'pf_createtemplate_aggregationlabel' )->escaped() . ' ' .
				Html::input( 'aggregation_label', null, 'text',
					[ 'size' => '25' ] ) .
				"</p>\n";
			$text .= "\t</fieldset>\n";
		}

		$text .= "\t" . Html::hidden( 'csrf', $this->getUser()->getEditToken( 'CreateClass' ) ) . "\n";

		$attrs = [
			'type' => 'submit',
			'flags' => [ 'primary', 'progressive' ],
			'name' => 'createAll',
			'label' => $this->msg( 'pf_createclass_create' )->text()
		];
		$createButton = new OOUI\ButtonInputWidget( $attrs );
		$text .= new OOUI\FieldLayout( $createButton );

		$text .= "</form>\n";
		$out->addHTML( $text );
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
