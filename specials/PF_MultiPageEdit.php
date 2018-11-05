<?php
/**
 * Displays a spreadsheet interface for editing and adding pages for a particular
 * template. If no template is specified, displays a list of all available templates.
 *
 *
 * @file
 * @ingroup PF
 * @author Yashdeep Thorat
 */

/**
 * @ingroup PFSpecialPages
 */
class PFMultiPageEdit extends SpecialPage {

	public $mTemplate;
	public $mForm;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'MultiPageEdit', 'multipageedit' );
	}

	function execute( $query ) {
		$this->setHeaders();

		// Check permissions.
		if ( !$this->getUser()->isAllowed( 'multipageedit' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->mTemplate = $this->getRequest()->getText( 'template' );
		$this->mForm = $this->getRequest()->getText( 'form' );
		// If a template is not specified, list all the available templates.
		if ( empty( $this->mTemplate ) ) {
			list( $limit, $offset ) = $this->getRequest()->getLimitOffset();
			$rep = new SpreadsheetTemplatesPage();
			$rep->execute( $query );
		} else {
			if ( empty( $this->mForm ) ) {
				list( $limit, $offset ) = $this->getRequest()->getLimitOffset();
				$rep = new SpreadsheetTemplatesPage();
				$rep->execute( $query );
			} else {
				$this->createSpreadsheet( $this->mTemplate, $this->mForm );
			}
		}
	}

	/**
	 * Creates the spreadsheet Interface for a template and dislpays all the
	 * template calls( instances ) as rows.
	 * @param string $template_name
	 */
	private function createSpreadsheet( $template_name, $form_name ) {
		global $wgPageFormsGridParams, $wgPageFormsScriptPath;
		global $wgPageFormsAutocompleteValues, $wgPageFormsMaxLocalAutocompleteValues;

		$out = $this->getOutput();
		$req = $this->getRequest();

		$out->addModules( 'ext.pageforms.jsgrid' );
		$text = '';
		$out->setPageTitle( wfMessage( 'pf_multipageedit_with-name', $this->mTemplate )->text() );

		$template = PFTemplate::newFromName( $template_name );
		$templateCalls = array();

		$templateFields = $template->getTemplateFields();

		$gridParams = array();
		$gridParamValues = array( 'name' => 'page', 'title' => 'Page', 'type' => 'text' );
		$gridParams[] = $gridParamValues;

		foreach ( $templateFields as $templateField ) {
			$gridParamValues = array( 'name' => $templateField->getFieldName() );
			$gridParamValues['title'] = $templateField->getLabel();
			$gridParamValues['type'] = 'text';
			if ( !empty( $fieldType = $templateField->getFieldType() ) ) {
				if ( $fieldType == 'Date' ) {
					$gridParamValues['type'] = 'date';
				} elseif ( $fieldType == 'Boolean' ) {
					$gridParamValues['type'] = 'checkbox';
				} elseif ( $fieldType == 'Text' ) {
					$gridParamValues['type'] = 'textarea';
				} elseif ( $fieldType == 'Page' ) {
					$gridParamValues['type'] = 'select';
					if ( $templateField->isList() ) {
						$gridParamValues['type'] = 'tokens';
						$gridParamValues['delimiter'] = $templateField->getDelimiter();
					} else {
						$gridParamValues['type'] = 'combobox';
					}
					$fullCargoField = $templateField->getFullCargoField();
					$autocompleteValues = PFValuesUtils::getAutocompleteValues( $fullCargoField, 'cargo field' );
					$gridParamValues['autocompletesettings'] = $fullCargoField;
					if ( count( $autocompleteValues ) > $wgPageFormsMaxLocalAutocompleteValues ) {
						$gridParamValues['autocompletedatatype'] = 'cargo field';
					} else {
						$wgPageFormsAutocompleteValues[$fullCargoField] = $autocompleteValues;
					}
				}
			} elseif ( !empty( $propertyType = $templateField->getPropertyType() ) ) {
				if ( $propertyType == '_dat' ) {
					$gridParamValues['type'] = 'date';
				} elseif ( $propertyType == '_boo' ) {
					$gridParamValues['type'] = 'checkbox';
				} elseif ( $propertyType == '_txt' || $propertyType == '_cod' ) {
					$gridParamValues['type'] = 'textarea';
				} elseif ( $propertyType == '_wpg' ) {
					if ( $templateField->isList() ) {
						$gridParamValues['type'] = 'tokens';
						$gridParamValues['delimiter'] = $templateField->getDelimiter();
					} else {
						$gridParamValues['type'] = 'combobox';
					}
					$property = $templateField->getSemanticProperty();
					$autocompleteValues = PFValuesUtils::getAutocompleteValues( $property, 'property' );
					$gridParamValues['autocompletesettings'] = $property;
					if ( count( $autocompleteValues ) > $wgPageFormsMaxLocalAutocompleteValues ) {
						$gridParamValues['autocompletedatatype'] = 'property';
					} else {
						$wgPageFormsAutocompleteValues[$property] = $autocompleteValues;
					}

				}
			}
			$gridParams[] = $gridParamValues;
		}
		$templateDivID = str_replace( ' ', '', $template_name ) . "Grid";
		$templateDivAttrs = array(
			'class' => 'pfJSGrid',
			'id' => $templateDivID,
			'data-template-name' => $template_name,
			'data-form-name' => $form_name,
			'height' => '500px',
			'editMultiplePages' => true
		);
		$loadingImage = Html::element( 'img', array( 'src' => "$wgPageFormsScriptPath/skins/loading.gif" ) );

		$text .= "<div id='loadingImage' style='display: none;'>" . $loadingImage . "</div>";

		$text .= Html::rawElement( 'div', $templateDivAttrs, $loadingImage );
		$wgPageFormsGridParams[$template_name] = $gridParams;

		PFFormUtils::setGlobalVarsForSpreadsheet();

		$text .= "<p><div id='selectLimit'></div></p>";

		$out->addHTML( $text );
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}

/**
 * @ingroup PFSpecialPages
 */
class SpreadsheetTemplatesPage extends QueryPage {

	private $templateInForm = array();
	private $templatesUsed = array();

	/**
	 * This function is used to find all the non-repeating templates in all the
	 * forms available in the wiki and store them along with the form names
	 * in an array using helper functions.
	 * @param string $name
	 */
	public function __construct( $name = 'MultiPageEdit' ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array( 'page' ),
			array( 'page_title' ),
			array( 'page_namespace' => PF_NS_FORM, 'page_is_redirect' => 0 ),
			__METHOD__,
			array(),
			array()
		);
		while ( $row = $dbr->fetchRow( $res ) ) {
			$formTitle = Title::makeTitle( PF_NS_FORM, $row['page_title'] );
			$this->findTemplates( $formTitle );
		}
		parent::__construct( $name );
	}

	function getName() {
		return "MultiPageEdit";
	}

	function isExpensive() {
		return false;
	}

	function isSyndicated() {
		return false;
	}

	function getPageHeader() {
		$header = Html::element( 'p', null, wfMessage( 'pf_multipageedit_docu' )->text() );
		return $header;
	}

	function getPageFooter() {
	}

	function getQueryInfo() {
		return array(
			'tables' => array( 'page' ),
			'fields' => array( 'page_title AS title', 'page_title AS value' ),
			'conds' => array( 'page_namespace' => NS_TEMPLATE )
		);
	}

	function sortDescending() {
		return false;
	}

	function findTemplates( $formTitle ) {
		$formWikiPage = WikiPage::factory( $formTitle );
		$formContent = $formWikiPage->getContent( Revision::RAW )->getNativeData();
		$start_position = 0;
		while ( $brackets_loc = strpos( $formContent, '{{{', $start_position ) ) {
			$brackets_end_loc = strpos( $formContent, "}}}", $brackets_loc );
			$bracketed_string = substr( $formContent, $brackets_loc + 3, $brackets_end_loc - ( $brackets_loc + 3 ) );
			$tag_components = PFUtils::getFormTagComponents( $bracketed_string );
			$tag_title = trim( $tag_components[0] );
			if ( $tag_title == 'for template' ) {
				if ( count( $tag_components ) > 1 ) {
					$templateName = $tag_components[1];
					if ( array_key_exists( $templateName, $this->templatesUsed ) ) {
						unset( $this->templateInForm[$templateName] );
					} else {
						$this->templateInForm[$templateName] = $formTitle->getText();
						$this->templatesUsed[$templateName] = $formTitle->getText();
					}
				}
			}
			$start_position = $brackets_loc + 1;
		}
	}

	function getFormForTemplate( $templateName ) {
		if ( !array_key_exists( $templateName, $this->templateInForm ) ) {
			return null;
		}
		return $this->templateInForm[$templateName];
	}

	function formatResult( $skin, $result ) {
		$templateName = $result->value;
		$formName = $this->getFormForTemplate( $templateName );
		if ( $formName == null ) {
			return false;
		}
		$templateTitle = Title::makeTitle( NS_TEMPLATE, $templateName );
		if ( method_exists( $this, 'getLinkRenderer' ) ) {
			$linkRenderer = $this->getLinkRenderer();
		} else {
			$linkRenderer = null;
		}
		$sp = SpecialPageFactory::getPage( 'MultiPageEdit' );
		$text = PFUtils::makeLink( $linkRenderer, $sp->getTitle(), $templateTitle->getText(), array(), array( "template" => $templateTitle->getText(), "form" => $formName ) );
		return $text;
	}
}
