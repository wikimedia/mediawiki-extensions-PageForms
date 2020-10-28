<?php

/**
 * Displays a spreadsheet interface for editing and adding pages for a
 * particular template. If no template is specified, displays a list of all
 * available templates.
 *
 *
 * @file
 * @ingroup PF
 * @author Yashdeep Thorat
 * @author Yaron Koren
 */

/**
 * @ingroup PFSpecialPages
 */
class PFMultiPageEdit extends QueryPage {

	public $mTemplate;
	public $mForm;
	private $mTemplateInForm = [];
	private $mTemplatesUsed = [];

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

		// If the template and form are both specified, show the
		// editable spreadsheet; otherwise, show the list of templates.
		if ( $this->mTemplate != '' && $this->mForm != '' ) {
			$this->displaySpreadsheet( $this->mTemplate, $this->mForm );
		} else {
			$this->setTemplateList();
			parent::execute( $query );
		}
	}

	/**
	 * Displays the spreadsheet interface for a template, with each
	 * template call/instance as a row.
	 *
	 * @param string $template_name
	 * @param string $form_name
	 */
	private function displaySpreadsheet( $template_name, $form_name ) {
		global $wgPageFormsGridParams, $wgPageFormsScriptPath;
		global $wgPageFormsAutocompleteValues, $wgPageFormsMaxLocalAutocompleteValues;

		$out = $this->getOutput();
		$req = $this->getRequest();

		$out->addModules( 'ext.pageforms.spreadsheet' );
		$text = '';
		$out->setPageTitle( $this->msg( 'pf_multipageedit_with-name', $this->mTemplate )->text() );

		$template = PFTemplate::newFromName( $template_name );
		$templateCalls = [];

		$templateFields = $template->getTemplateFields();

		$gridParams = [];
		$gridParamValues = [ 'name' => 'page', 'title' => 'Page', 'type' => 'text' ];
		$gridParams[] = $gridParamValues;

		foreach ( $templateFields as $templateField ) {
			$gridParamValues = [ 'name' => $templateField->getFieldName() ];
			$gridParamValues['title'] = $templateField->getLabel();
			$gridParamValues['type'] = 'text';
			if ( !empty( $allowedValues = $templateField->getPossibleValues() ) ) {
				$gridParamValues['values'] = $allowedValues;
				if ( $templateField->isList() ) {
					$gridParamValues['list'] = true;
					$gridParamValues['delimiter'] = $templateField->getDelimiter();
				}
			} elseif ( !empty( $fieldType = $templateField->getFieldType() ) ) {
				if ( $fieldType == 'Date' ) {
					$gridParamValues['type'] = 'date';
				} elseif ( $fieldType == 'Datetime' ) {
					$gridParamValues['type'] = 'datetime';
				} elseif ( $fieldType == 'Boolean' ) {
					$gridParamValues['type'] = 'checkbox';
				} elseif ( $fieldType == 'Text' ) {
					$gridParamValues['type'] = 'textarea';
				} elseif ( $fieldType == 'Page' ) {
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
		$templateDivAttrs = [
			'class' => 'pfSpreadsheet',
			'id' => $templateDivID,
			'data-template-name' => $template_name,
			'data-form-name' => $form_name,
			'height' => '500px',
			'editMultiplePages' => true
		];
		$text .= Html::element( 'p', null, wfMessage( 'pf-spreadsheet-addrowinstructions' )->parse() );
		$loadingImage = Html::element( 'img', [ 'src' => "$wgPageFormsScriptPath/skins/loading.gif" ] );
		$loadingImageDiv = '<div class="loadingImage">' . $loadingImage . '</div>';
		$text .= Html::rawElement( 'div', $templateDivAttrs, $loadingImageDiv );

		$wgPageFormsGridParams[$template_name] = $gridParams;

		PFFormUtils::setGlobalVarsForSpreadsheet();

		$text .= "<p><div id='selectLimit'></div></p>";

		$out->addHTML( $text );
	}

	/**
	 * This function is used to find all the non-repeating templates in
	 * all the forms available in the wiki and store them along with the
	 * form names in an array using helper functions.
	 */
	function setTemplateList() {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			[ 'page' ],
			[ 'page_title' ],
			[ 'page_namespace' => PF_NS_FORM, 'page_is_redirect' => 0 ],
			__METHOD__,
			[],
			[]
		);
		while ( $row = $dbr->fetchRow( $res ) ) {
			$this->findTemplatesForForm( $row['page_title'] );
		}
	}

	function isExpensive() {
		return false;
	}

	function isSyndicated() {
		return false;
	}

	function getPageHeader() {
		$header = Html::element( 'p', null, $this->msg( 'pf_multipageedit_docu' )->text() );
		return $header;
	}

	function getPageFooter() {
	}

	function getQueryInfo() {
		return [
			'tables' => [ 'page' ],
			'fields' => [ 'page_title AS title', 'page_title AS value' ],
			'conds' => [
				'page_namespace' => NS_TEMPLATE,
				'page_title' => array_keys( $this->mTemplateInForm )
			]
		];
	}

	function sortDescending() {
		return false;
	}

	function findTemplatesForForm( $formName ) {
		$formTitle = Title::makeTitle( PF_NS_FORM, $formName );
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
					$templateName = str_replace( ' ', '_', $tag_components[1] );
					if ( array_key_exists( $templateName, $this->mTemplatesUsed ) ) {
						unset( $this->mTemplateInForm[$templateName] );
					} else {
						$this->mTemplateInForm[$templateName] = $formTitle->getText();
						$this->mTemplatesUsed[$templateName] = $formTitle->getText();
					}
				}
			}
			$start_position = $brackets_loc + 1;
		}
	}

	public function getFormForTemplate( $templateName ) {
		// This escaping is needed because, when this method is called
		// from Cargo, the template can have a space in its name.
		$templateName = str_replace( ' ', '_', $templateName );
		if ( !array_key_exists( $templateName, $this->mTemplateInForm ) ) {
			return null;
		}
		return $this->mTemplateInForm[$templateName];
	}

	function formatResult( $skin, $result ) {
		$escapedTemplateName = $result->value;
		$escapedFormName = $this->getFormForTemplate( $escapedTemplateName );
		if ( $escapedFormName == null ) {
			return false;
		}
		$templateName = str_replace( '_', ' ', $escapedTemplateName );
		$formName = str_replace( '_', ' ', $escapedFormName );
		$linkRenderer = $this->getLinkRenderer();
		$linkParams = [ 'template' => $templateName, 'form' => $formName ];
		$text = $linkRenderer->makeKnownLink( $this->getPageTitle(), $templateName, [], $linkParams );
		return $text;
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
