<?php
/**
 * Displays a pre-defined form for editing a page's data.
 *
 * @author Yaron Koren
 */
require_once( $sfgIP . "/includes/SF_FormPrinter.inc" );

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once( "$IP/includes/SpecialPage.php" );

SpecialPage::addPage( new SpecialPage('EditData','',true,'doSpecialEditData',false) );

function doSpecialEditData($query = '') {
	global $wgOut, $wgRequest, $sfgScriptPath, $sfgFormPrinter;

	$form_name = $wgRequest->getVal('form');
	$target_name = $wgRequest->getVal('target');

	// if query string did not contain these variables, try the URL
	if (! $form_name && ! $target_name) {
		$queryparts = explode('/', $query, 2);
		$form_name = $queryparts[0];
		$target_name = $queryparts[1];
	}

	// get contents of form definition file
	$form_title = Title::newFromText($form_name, SF_NS_FORM);
	// get contents of target page
	$target_title = Title::newFromText($target_name);

	if (! $form_title || ! $form_title->exists() ) {
		if ($form_name == '')
			$text = '<p>' . wfMsg('sf_editdata_badurl') . "</p>\n";
		else
			$text = "<p>Error: No form page was found at " . sffLinkText(SF_NS_FORM, $form_name) . ".</p>\n";
	} elseif (! $target_title || ! $target_title->exists() ) {
		if ($target_name == '')
			$text = '<p>' . wfMsg('sf_editdata_badurl') . "</p>\n";
		else
			$text = "<p>Error: No page was found at " . sffLinkText(null, $target_name) . ".</p>\n";
	} else {
		$form_article = new Article($form_title);
		$form_definition = $form_article->getContent();
		$submit_url = $form_title->getLocalURL('action=submit');
		$save_page = $wgRequest->getCheck('wpSave');
		$preview_page = $wgRequest->getVal('wpPreview');
		$diff_page = $wgRequest->getVal('wpDiff');
		$summary_text = $wgRequest->getVal('wpSummary');
		$form_submitted = ($save_page || $preview_page || $diff_page);
		$page_title = str_replace('_', ' ', $target_name);
		// if user already made some action, ignore the edited page
		// and just get data from the query string
		if ($wgRequest->getVal('query') == 'true') {
			$edit_content = null;
			$is_text_source = false;
		} else {
			$target_article = new Article($target_title);
			$edit_content = $target_article->getContent();
			$is_text_source = true;
		}
		list ($form_text, $javascript_text, $data_text) =
			$sfgFormPrinter->formHTML($form_definition, $form_submitted, $is_text_source, $edit_content, $page_title);
		if ($form_submitted) {
			$text = $sfgFormPrinter->redirectText($target_name, $data_text);
		} else {
			// set 'title' field, in case there's no URL niceness
			$text =<<<END
	<form name="createbox" onsubmit="return validate_all()" action="" method="post" class="createbox">
	<input type="hidden" name="query" value="true" />

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
	$wgOut->addScript('		<script type="text/javascript">' . "\n" . $javascript_text . '</script>' . "\n");
	$wgOut->addHTML($text);
}
