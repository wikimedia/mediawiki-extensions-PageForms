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

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'EditUsingSpreadsheet' );
	}

	function execute( $query ) {
		$this->setHeaders();
		$this->mTemplate = $this->getRequest()->getText( 'template' );
		// If a template is not specified, list all the available templates.
		if ( empty( $this->mTemplate ) ) {
			list( $limit, $offset ) = $this->getRequest()->getLimitOffset();
			$rep = new SpreadsheetTemplatesPage();
			$rep->execute( $query );
		} else {
			$this->createSpreadsheet( $this->mTemplate );
		}
	}

	/**
	 * Creates the spreadsheet Interface for a template.
	 * @param string $template_name
	 */
	private function createSpreadsheet( $template_name ) {
		$out = $this->getOutput();
		$text = '';
		$pageTitle = "Edit pages using spreadsheet for template: $this->mTemplate";
		$out->setPageTitle( $pageTitle );
		return;
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}

/**
 * @ingroup PFSpecialPages
 */
class SpreadsheetTemplatesPage extends QueryPage {

	public function __construct( $name = 'EditUsingSpreadsheet' ) {
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

	function formatResult( $skin, $result ) {
		$templateTitle = Title::makeTitle( NS_TEMPLATE, $result->value );
		if ( method_exists( $this, 'getLinkRenderer' ) ) {
			$linkRenderer = $this->getLinkRenderer();
		} else {
			$linkRenderer = null;
		}

		$sp = SpecialPageFactory::getPage( 'EditUsingSpreadsheet' );
		$link = Title::makeTitle( NS_SPECIAL, $sp->mName );
		$text = PFUtils::makeLink( $linkRenderer, $link, htmlspecialchars( $templateTitle->getText() ), array(), array( "template" => htmlspecialchars( $templateTitle->getText() ) ) );
		return $text;
	}
}
