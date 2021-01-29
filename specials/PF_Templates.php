<?php
/**
 * Shows list of all templates on the site.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFSpecialPages
 */
class PFTemplates extends QueryPage {

	public function __construct( $name = 'Templates' ) {
		parent::__construct( $name );
	}

	function isExpensive() {
		return false;
	}

	function isSyndicated() {
		return false;
	}

	function getPageHeader() {
		$header = Html::element( 'p', null, $this->msg( 'pf_templates_docu' )->text() );
		return $header;
	}

	function getPageFooter() {
	}

	function getQueryInfo() {
		return [
			'tables' => [ 'page' ],
			'fields' => [ 'page_title AS title', 'page_title AS value' ],
			'conds' => [ 'page_namespace' => NS_TEMPLATE ]
		];
	}

	function sortDescending() {
		return false;
	}

	function getCategoryDefinedByTemplate( $templateTitle ) {
		$templateText = PFUtils::getPageText( $templateTitle );
		$cat_ns_name = PFUtils::getContLang()->getNsText( NS_CATEGORY );
		// Ignore categories inside <noinclude> tags.
		$templateText = preg_replace( '/<noinclude>.*<\/noinclude>/isU', '', $templateText );
		if ( preg_match_all( "/\[\[(Category|$cat_ns_name):([^\]]*)\]\]/", $templateText, $matches ) ) {
			// Get the last match - if there's more than one
			// category tag, there's a good chance that the last
			// one will be the relevant one - the others are
			// probably part of inline queries.
			$categoryName = trim( end( $matches[2] ) );
			// If there's a pipe, remove it and anything after it.
			$locationOfPipe = strpos( $categoryName, '|' );
			if ( $locationOfPipe !== false ) {
				$categoryName = substr( $categoryName, 0, $locationOfPipe );
			}
			return $categoryName;
		}
		return "";
	}

	function formatResult( $skin, $result ) {
		$title = Title::makeTitle( NS_TEMPLATE, $result->value );
		$linkRenderer = $this->getLinkRenderer();
		$text = $linkRenderer->makeKnownLink( $title, htmlspecialchars( $title->getText() ) );
		$category = $this->getCategoryDefinedByTemplate( $title );
		if ( $category !== '' ) {
			$text .= ' ' . $this->msg(
				'pf_templates_definescat',
				PFUtils::linkText( NS_CATEGORY, $category )
			)->parse();
		}
		return $text;
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
