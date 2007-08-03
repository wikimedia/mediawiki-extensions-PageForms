<?php
/**
 * Displays a form for entering the title of a page, which then redirects
 * to either the form for adding the page, or a form for editing it,
 * depending on whether the page already exists.
 *
 * @author Yaron Koren
 * @author Jeffrey Stuckman
 */
if (!defined('MEDIAWIKI')) die();

global $IP;
require_once( "$IP/includes/SpecialPage.php" );

SpecialPage::addPage( new SpecialPage('AddPage','',true,'doSpecialAddPage',false) );

function doSpecialAddPage($query = '') {
	global $wgOut, $wgRequest, $sfgScriptPath;

	$form_name = $wgRequest->getVal('form');
	$target_namespace = $wgRequest->getVal('namespace');

	// if query string did not contain form name, try the URL
	if (! $form_name) {
		$form_name = $query;
		// get namespace from  the URL, if it's there
		if ($namespace_label_loc = strpos($form_name, "/Namespace:")) {
			$target_namespace = substr($form_name, $namespace_label_loc + 11);
			$form_name = substr($form_name, 0, $namespace_label_loc);
		}
	}

	// get title of form
	$form_title = Title::newFromText($form_name, SF_NS_FORM);

	// handle submission
	$form_submitted = $wgRequest->getCheck('page_name');
	if ($form_submitted) {
		$page_name = $wgRequest->getVal('page_name');
		if ('' != $page_name) {
			// Append the namespace prefix to the page name,
			// if a namespace was not already entered.
			if (strpos($page_name,":") === false)
				$page_name = $target_namespace . ":" . $page_name;
			// find out whether this page already exists,
			// and send user to the appropriate form
			$page_title = Title::newFromText($page_name);
			if ($page_title && $page_title->exists()) {
				// it exists - see if page is a redirect; if
				// it is, edit the target page instead
				$article = new Article($page_title);
				$article->loadContent();
				$redirect_title = Title::newFromRedirect($article->fetchContent());
				if ($redirect_title != NULL) {
					$page_title = $redirect_title;
				}
				$ed = SpecialPage::getPage('EditData');
				$redirect_url = $ed->getTitle()->getFullURL() . "/" . $form_name . "/" . sffTitleURLString($page_title);
			} else {
				$ad = SpecialPage::getPage('AddData');
				$redirect_url = $ad->getTitle()->getFullURL() . "/" . $form_name . "/" . sffTitleURLString($page_title);
			}
			$text =<<<END
        <script type="text/javascript">
        window.location="$redirect_url";
        </script>

END;
			$wgOut->addHTML($text);
			return;
		}
	}

	if (! $form_title || ! $form_title->exists() ) {
		if ($form_name == '')
			$text = '<p>' . wfMsg('sf_addpage_badurl') . "</p>\n";
		else
			$text = '<p>' . wfMsg('sf_addpage_noform', sffLinkText(SF_NS_FORM, $form_name)) . ".</p>\n";
	} else {
		$description = wfMsg('sf_addpage_docu', $form_name);
		$button_text = wfMsg('addoreditdata');
		$text =<<<END
	<form action="" method="post">
	<p>$description</p>
	<p><input type="text" size="40" name="page_name"></p>
	<input type="hidden" name="namespace" value="$target_namespace">
	<input type="Submit" value="$button_text">
	</form>

END;
	}
	$scriptaculousCssUrl = $sfgScriptPath . '/skins/scriptaculous.css';
	$wgOut->addLink( array(
		'rel' => 'stylesheet',
		'type' => 'text/css',
		'media' => "screen, projection",
		'href' => $scriptaculousCssUrl
	));
	$wgOut->addScript('<script src="' . $sfgScriptPath . '/libs/scriptaculous-js-1.7.0/lib/prototype.js" type="text/javascript"></script>');
	$wgOut->addScript('<script src="' . $sfgScriptPath . '/libs/scriptaculous-js-1.7.0/src/scriptaculous.js" type="text/javascript"></script>');
	$wgOut->addHTML($text);
}
