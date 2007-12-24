<?php
/**
 * Shows list of all forms on the site.
 *
 * @author Yaron Koren
 */

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once( "$IP/includes/SpecialPage.php" );

SpecialPage::addPage( new SpecialPage('Forms','',true,'doSpecialForms',false) );

class FormsPage extends QueryPage {
	function getName() {
		return "Forms";
	}

	function isExpensive() { return false; }

	function isSyndicated() { return false; }

	function getPageHeader() {
		global $wgUser;
		$sk = $wgUser->getSkin();
		$cf = SpecialPage::getPage('CreateForm');
		$create_form_link = $sk->makeKnownLinkObj($cf->getTitle(), $cf->getDescription());
		$header = "<p>" . $create_form_link . ".</p>\n";
		$header .= '<p>' . wfMsg('sf_forms_docu') . "</p><br />\n";
		return $header;
	}

	function getPageFooter() {
	}

	function getSQL() {
		$NSform = SF_NS_FORM;
		$dbr = wfGetDB( DB_SLAVE );
		$page = $dbr->tableName( 'page' );
		// QueryPage uses the value from this SQL in an ORDER clause,
		// so return page_title as title.
		return "SELECT 'Form' AS type,
			page_title AS title,
			page_title AS value
			FROM $page
			WHERE page_namespace = {$NSform}
			AND page_is_redirect = 0";
	}

	function sortDescending() {
		return false;
	}

	function formatResult($skin, $result) {
		$title = Title::makeTitle( SF_NS_FORM, $result->value );
		$text = $skin->makeLinkObj( $title, $title->getText() );
		$ad = SpecialPage::getPage('AddPage');
		$add_data_url = $ad->getTitle()->getFullURL() . "/" . $title->getText();
		$text .= ' (<a href="' . $add_data_url . '">' . wfMsg('sf_forms_adddata') . '</a>)';

		return $text;
	}
}

function doSpecialForms() {
	list( $limit, $offset ) = wfCheckLimits();
	$rep = new FormsPage();
	return $rep->doQuery( $offset, $limit );
}
