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

SpecialPage::addPage( new SpecialPage('AddData','',true,'doSpecialAddData',false) );

function doSpecialAddData($query = '') {
	global $wgOut, $wgRequest, $sfgScriptPath;

	$form_name = $wgRequest->getVal('form');
        $target_name = $wgRequest->getVal('target');

        // if query string did not contain these variables, try the URL
        if (! $form_name && ! $target_name) {
                $queryparts = explode('/', $query, 2);
                $form_name = $queryparts[0];
                $target_name = $queryparts[1];
        }

	// get contents of template
	$form_title = Title::newFromText($form_name, SF_NS_FORM);
        // get contents of target page
        $target_title = Title::newFromText($target_name);

	// target_title should be null - we shouldn't be adding a page that
	// already exists
	// TODO - handle this contingency
	if ($target_title && $target_title->exists()) {
	} else {
		$page_title = str_replace('_', ' ', $target_name);
	}

	if (! $form_title || ! $form_title->exists() ) {
		if ($form_name == '')
			$text = '<p>' . wfMsg('sf_adddata_badurl') . "</p>\n";
		else
			$text = '<p>' . wfMsg('sf_addpage_noform', sffLinkText(SF_NS_FORM, $form_name)) . ".</p>\n";
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
			$page_contents = SFFormPrinter::getPreloadedText($wgRequest->getVal('preload'));
		} else {
			$page_is_source = false;
			$page_contents = null;
		}
		list ($form_text, $javascript_text, $title, $data_text) =
			SFFormPrinter::formHTML($form_definition, $form_submitted, $page_is_source, $page_contents, $page_title);
		if ($form_submitted) {
			if (! $title) {$title = $target_name;}
			$text = SFFormPrinter::redirectText($title, $data_text);
		} else {
			$text =<<<END
				<form name="createbox" onsubmit="return validate_all()" action="" method="post" class="createbox">

END;
			$text .= $form_text;
                        $target_title = Title::newFromText($title);
			$text .= SFFormPrinter::formBottom($target_title);
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
