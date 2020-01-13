<?php
/**
 * Shows list of all forms on the site.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFSpecialPages
 */
class PFForms extends SpecialPage {

	function __construct() {
		parent::__construct( 'Forms' );
	}

	function execute( $query ) {
		$this->setHeaders();
		list( $limit, $offset ) = $this->getRequest()->getLimitOffset();
		$rep = new FormsPage();
		return $rep->execute( $query );
	}

	protected function getGroupName() {
		return 'pages';
	}
}

/**
 * @ingroup PFSpecialPages
 */
class FormsPage extends QueryPage {
	public function __construct( $name = 'Forms' ) {
		parent::__construct( $name );
	}

	function getName() {
		return "Forms";
	}

	function isExpensive() {
		return false;
	}

	function isSyndicated() {
		return false;
	}

	function getPageHeader() {
		$header = Html::element( 'p', null, wfMessage( 'pf_forms_docu' )->text() );
		return $header;
	}

	function getPageFooter() {
	}

	function getQueryInfo() {
		return [
			'tables' => [ 'page' ],
			'fields' => [ 'page_title AS title', 'page_title AS value' ],
			'conds' => [ 'page_namespace' => PF_NS_FORM, 'page_is_redirect' => 0 ]
		];
	}

	function sortDescending() {
		return false;
	}

	function formatResult( $skin, $result ) {
		$title = Title::makeTitle( PF_NS_FORM, $result->value );
		return $this->getLinkRenderer()->makeKnownLink( $title, htmlspecialchars( $title->getText() ) );
	}
}
