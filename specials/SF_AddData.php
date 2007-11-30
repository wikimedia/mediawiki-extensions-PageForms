<?php
/**
 * Displays a pre-defined form for adding data.
 *
 * @author Yaron Koren
 */
if (!defined('MEDIAWIKI')) die();

global $sfgIP;
require_once( $sfgIP . "/includes/SF_FormPrinter.inc" );

global $IP;
require_once( "$IP/includes/SpecialPage.php" );

global $sfgSpecialPagesSpecialInit;
if ($sfgSpecialPagesSpecialInit) {
	global $wgSpecialPages;
	$wgSpecialPages['AddData'] = 'SFAddData';
 
	class SFAddData extends SpecialPage {

		/**
		 * Constructor
		 */
		public function __construct() {
			smwfInitUserMessages();
			SpecialPage::SpecialPage('AddData','',true,'doSpecialAddData',false);
		}

		function execute($query='') {
			doSpecialAddData($query);
		}
	}
} else {
	SpecialPage::addPage( new SpecialPage('AddData','',true,'doSpecialAddData',false) );
}

function doSpecialAddData($query = '') {
	global $wgRequest;

	$form_name = $wgRequest->getVal('form');
	$target_name = $wgRequest->getVal('target');

	// if query string did not contain these variables, try the URL
	if (! $form_name && ! $target_name) {
		$queryparts = explode('/', $query, 2);
		$form_name = isset($queryparts[0]) ? $queryparts[0] : '';
		$target_name = isset($queryparts[1]) ? $queryparts[1] : '';
	}

	$alt_forms = $wgRequest->getArray('alt_form');

	printAddForm($form_name, $target_name, $alt_forms);
}

function printAltFormsList($alt_forms, $target_name) {
	$text = "";
	$ad = SpecialPage::getPage('AddData');
	$i = 0;
	foreach ($alt_forms as $alt_form) {
		if ($i++ > 0) { $text .= ", "; }
		$text .= '<a href="' . $ad->getTitle()->getFullURL() . "/" . $alt_form . "/" . $target_name . '">' . str_replace('_', ' ', $alt_form) . "</a>";
	}
	return $text;
}

function printAddForm($form_name, $target_name, $alt_forms) {
	global $wgOut, $wgRequest, $sfgScriptPath, $sfgFormPrinter;

	// get contents of form and target page - if there's only one,
	// it might be a target with only alternate forms
	if ($form_name == '') {
		$wgOut->addHTML( "<p class='error'>" . wfMsg('sf_adddata_badurl') . '</p>');
		return;
	} elseif ($target_name == '') {
		if (count($alt_forms) > 0) {
			// if there's just a target and list of alternate
			// forms, but no main form, display just a list of
			// links on the page
			$target_name = $form_name;
			$target_title = Title::newFromText($target_name);
			$s = wfMsg('sf_adddata_title', "", $target_title->getPrefixedText());
			$wgOut->setPageTitle($s);
			$text = '<p>' . wfMsg('sf_adddata_altformsonly') . ' ';
			$text .= printAltFormsList($alt_forms, $target_name);
			$text .= "</p>\n";
			$wgOut->addHTML($text);
			return;
		} else {
			$wgOut->addWikiText( "<p class='error'>" . wfMsg('sf_adddata_badurl') . '</p>');
			return;
		}
	}

	$form_title = Title::newFromText($form_name, SF_NS_FORM);
	$target_title = Title::newFromText($target_name);

	$s = wfMsg('sf_adddata_title', $form_title->getText(), $target_title->getPrefixedText());
	$wgOut->setPageTitle($s);

	// target_title should be null - we shouldn't be adding a page that
	// already exists
	if ($target_title && $target_title->exists()) {
		$wgOut->addWikiText( "<p class='error'>" . wfMsg('articleexists') . '</p>');
		return;
	} else {
		$page_title = str_replace('_', ' ', $target_name);
	}

	if (! $form_title || ! $form_title->exists() ) {
		if ($form_name == '')
			$text = '<p>' . wfMsg('sf_adddata_badurl') . "</p>\n";
		else
			$text = '<p>' . wfMsg('sf_addpage_badform', sffLinkText(SF_NS_FORM, $form_name)) . ".</p>\n";
	} elseif ($target_name == '') {
		$text = '<p>' . wfMsg('sf_adddata_badurl') . "</p>\n";
	} else {
		$formArticle = new Article($form_title);
		$form_definition = $formArticle->getContent();

		$save_page = $wgRequest->getCheck('wpSave');
		$preview_page = $wgRequest->getCheck('wpPreview');
		$diff_page = $wgRequest->getCheck('wpDiff');
		$form_submitted = ($save_page || $preview_page || $diff_page);
		// get 'preload' query value, if it exists
		if (!$form_submitted && $wgRequest->getCheck('preload')) {
			$page_is_source = true;
			$page_contents = $sfgFormPrinter->getPreloadedText($wgRequest->getVal('preload'));
		} else {
			$page_is_source = false;
			$page_contents = null;
		}
		list ($form_text, $javascript_text, $data_text) =
			$sfgFormPrinter->formHTML($form_definition, $form_submitted, $page_is_source, $page_contents, $page_title);
		if ($form_submitted) {
			$text = $sfgFormPrinter->redirectText($target_name, $data_text);
		} else {
			$text = "";
			if (count($alt_forms) > 0) {
				$text .= '<div class="info_message">' . wfMsg('sf_adddata_altforms') . ' ';
				$text .= printAltFormsList($alt_forms, $target_name);
				$text .= "</div>\n";
			}
			$text .=<<<END
				<form name="createbox" onsubmit="return validate_all()" action="" method="post" class="createbox">

END;
			$text .= $form_text;
		}
	}
	$mainCssUrl = $sfgScriptPath . '/skins/SF_main.css';
	$wgOut->addLink( array(
		'rel' => 'stylesheet',
		'type' => 'text/css',
		'media' => "screen, projection",
		'href' => $mainCssUrl
	));
	$scriptaculousCssUrl = $sfgScriptPath . '/skins/scriptaculous.css';
	$wgOut->addLink( array(
		'rel' => 'stylesheet',
		'type' => 'text/css',
		'media' => "screen, projection",
		'href' => $scriptaculousCssUrl
	));
	$wgOut->addScript('<script src="' . $sfgScriptPath . '/libs/scriptaculous-js-1.7.0/lib/prototype.js" type="text/javascript"></script>' . "\n");
	$wgOut->addScript('		<script src="' . $sfgScriptPath . '/libs/scriptaculous-js-1.7.0/src/scriptaculous.js" type="text/javascript"></script>' . "\n");
	if (! empty($javascript_text))
		$wgOut->addScript('		<script type="text/javascript">' . "\n" . $javascript_text . '</script>' . "\n");
	$wgOut->addHTML($text);
}
