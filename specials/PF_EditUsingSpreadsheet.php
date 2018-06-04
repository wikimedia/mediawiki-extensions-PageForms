<?php
/**
 * Displays a spreadsheet interface for editing and adding pages for a particular
 * template. If no template is specified, displays a list of all available templates.
 *
 *
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFSpecialPages
 */
class PFEditUsingSpreadsheet extends SpecialPage {

	public $mTemplate;
	public $mForm;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'EditUsingSpreadsheet' );
	}

	function execute( $query ) {
		$this->setHeaders();
		$this->mTemplate = $this->getRequest()->getText( 'template' );
		$this->mForm = $this->getRequest()->getText( 'form' );
		// If a template is not specified, list all the available templates.
		if ( empty( $this->mTemplate ) ) {
			list( $limit, $offset ) = $this->getRequest()->getLimitOffset();
			$rep = new SpreadsheetTemplatesPage();
			$rep->execute( $query );
		} else {
			if ( empty( $this->mForm ) ) {
				$out = $this->getOutput();
				$text = Html::element( 'p', array( 'class' => 'error' ), "You must specify a form name along with the template in the url." ) . "\n";
				$out->addHTML( $text );
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
		global $wgPageFormsGridValues, $wgPageFormsGridParams;
		global $wgPageFormsScriptPath;
		$out = $this->getOutput();
		$req = $this->getRequest();

		$out->addModules( 'ext.pageforms.jsgrid' );
		$text = '';
		$pageTitle = "Edit pages using spreadsheet for template: $this->mTemplate";
		$out->setPageTitle( $pageTitle );
		// Use inner join to get all the pages which contain the template.
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array( 'templatelinks', 'page' ),
			array( 'page_title' ),
			array(
				'tl_title' => $template_name,
				'tl_from_namespace' => '0'
			),
			__METHOD__,
			array(),
			array( 'templatelinks' => array( 'INNER JOIN', array(
				'tl_from=page_id' ) ) )
		);
		// $pages contains the title and contents of all the pages queried.
		$pages = array();
		$pageContents = array();
		while ( $row = $dbr->fetchRow( $res ) ) {
			$pageName = str_replace( '_', ' ', $row[0] );
			$pageContents['page_title'] = $pageName;
			$PageTitle = Title::makeTitle( NS_MAIN, $pageName );
			$wikiPage = WikiPage::factory( $PageTitle );
			$pageContents['page_content'] = $wikiPage->getContent( Revision::RAW )->getNativeData();
			$pages[] = $pageContents;
		}

		$template = PFTemplate::newFromName( $template_name );
		$templateCalls = array();

		foreach ( $pages as $page ) {
			$currentPageTemplateCalls = $this->getTemplateCalls( $page, $template_name );
			$templateCalls = array_merge( $templateCalls, $currentPageTemplateCalls );
		}

		$templateFields = $template->getTemplateFields();
		$gridValues = array();
		foreach ( $templateCalls as $templateCall ) {
			$gridValues[] = $this->getGridValues( $templateCall );
		}

		$gridParams = array();
		$gridParamValues = array( 'name' => 'page', 'title' => 'Page' );
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
				}
			} elseif ( !empty( $propertyType = $templateField->getPropertyType() ) ) {
				if ( $propertyType == '_dat' ) {
					$gridParamValues['type'] = 'date';
				} elseif ( $propertyType == '_boo' ) {
					$gridParamValues['type'] = 'checkbox';
				} elseif ( $propertyType == '_txt' || $propertyType == '_cod' ) {
					$gridParamValues['type'] = 'textarea';
				}
			}
			$gridParams[] = $gridParamValues;
		}
		$templateDivID = str_replace( ' ', '', $template_name ) . "Grid";
		$templateDivAttrs = array(
			'class' => 'pfJSGrid',
			'id' => $templateDivID,
			'data-template-name' => $template_name,
			'height' => '200px'
		);

		$loadingImage = Html::element( 'img', array( 'src' => "$wgPageFormsScriptPath/skins/loading.gif" ) );
		$text = Html::rawElement( 'div', $templateDivAttrs, $loadingImage );
		$wgPageFormsGridParams[$template_name] = $gridParams;
		$wgPageFormsGridValues[$template_name] = $gridValues;

		$out->addHTML( $text );
	}

	/**
	 * Retruns an array of template calls found in the page in form of an array
	 * of strings.Takes care of multiple template calls in a single page.
	 * This code is copied from
	 * https://stackoverflow.com/questions/27078259/get-string-between-find-all-occurrences-php/27078384#27078384
	 * @param array $page
	 * @param string $template_name
	 * @return array
	 */
	private function getTemplateCalls( $page, $template_name ) {
		$str = $page['page_content'];
		$startDelimiter = '{{' . $template_name;
		$endDelimiter = '}}';
		$contents = array();
		$startDelimiterLength = strlen( $startDelimiter );
		$endDelimiterLength = strlen( $endDelimiter );
		$startFrom = $contentStart = $contentEnd = 0;
		while ( false !== ( $contentStart = strpos( $str, $startDelimiter, $startFrom ) ) ) {
			$contentStart += $startDelimiterLength;
			$contentEnd = strpos( $str, $endDelimiter, $contentStart );
			if ( false === $contentEnd ) {
				break;
			}
			$contents[] = 'page=' . $page['page_title'] . substr( $str, $contentStart, $contentEnd - $contentStart );
			$startFrom = $contentEnd + $endDelimiterLength;
		}
		return $contents;
	}

	/**
	 * This function is used to get an array of field names and field values
	 * from each template call to display in the spreadsheet.
	 * @param array $templateCall
	 * @return array
	 */
	private function getGridValues( $templateCall ) {
		$fieldArray = explode( "|", $templateCall );
		$fieldValueArray = array();
		foreach ( $fieldArray as $field ) {
			$equalPos = strpos( $field, '=' );
			$fieldLabel = substr( $field, 0, $equalPos );
			$fieldValue = substr( $field, $equalPos + 1, strlen( $field ) - ( $equalPos + 2 ) );
			$fieldValueArray[$fieldLabel] = $fieldValue;
		}
		return $fieldValueArray;
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

	/**
	 * This function is used to find all the non-repeating templates in all the
	 * forms available in the wiki and store them along with the form names
	 * in an array using helper functions.
	 * @param string $name
	 */
	public function __construct( $name = 'EditUsingSpreadsheet' ) {
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
		return "EditUsingSpreadsheet";
	}

	function isExpensive() {
		return false;
	}

	function isSyndicated() {
		return false;
	}

	function getPageHeader() {
		$header = Html::element( 'p', null, wfMessage( 'pf_templates_docu' )->text() );
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
				if ( count( $tag_components ) > 1 && !array_key_exists( $templateName = $tag_components[1], $this->templateInForm ) ) {
					$this->templateInForm[$templateName] = $formTitle->getText();
				}
			}
			$start_position = $brackets_loc + 1;
		}
	}

	function formatResult( $skin, $result ) {
		if ( !array_key_exists( $result->value, $this->templateInForm ) ) {
			return false;
		}
		$formName = $this->templateInForm[$result->value];
		$templateTitle = Title::makeTitle( NS_TEMPLATE, $result->value );
		if ( method_exists( $this, 'getLinkRenderer' ) ) {
			$linkRenderer = $this->getLinkRenderer();
		} else {
			$linkRenderer = null;
		}
		$sp = SpecialPageFactory::getPage( 'EditUsingSpreadsheet' );
		$link = Title::makeTitle( NS_SPECIAL, $sp->mName );
		$text = PFUtils::makeLink( $linkRenderer, $link, htmlspecialchars( $templateTitle->getText() ), array(), array( "template" => htmlspecialchars( $templateTitle->getText() ), "form" => $formName ) );
		return $text;
	}
}
