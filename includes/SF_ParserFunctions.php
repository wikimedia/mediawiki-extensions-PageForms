<?php
/**
 * Parser functions for Semantic Forms.
 * Currently only one parser function is defined: forminput. It is
 * called as:
 *
 * {{#forminput:form_name|size|value|button_text|query_string}}
 *
 * This functions returns HTML representing a form to let the user enter the
 * name of a page to be added or edited using a Semantic Forms form. All
 * arguments are optional. form_name is the name of the SF form to be used;
 * if it is left empty, a dropdown will appear, letting the user chose among
 * all existing forms. size represents the size of the text input (default
 * is 25), and value is the starting value of the input (default is blank).
 * button_text is the text that will appear on the "submit" button, and
 * query_string is the set of values that you want passed in through the
 * query string to the form.
 *
 * Example: to create an input to add or edit a page with a form called
 * 'User' within a namespace also called 'User', and to have the form
 * preload with the page called 'UserStub', you could call the following:
 *
 * {{#forminput:User|||Add or edit user|namespace=User&preload=UserStub}}
 *
 * @author Yaron Koren
 */


function sfgParserFunctions () {
    global $wgParser;
    $wgParser->setFunctionHook('forminput', 'renderFormInput');
}

function sfgLanguageGetMagic( &$magicWords, $langCode = "en" ) {
	switch ( $langCode ) {
	default:
		$magicWords['forminput']	= array ( 0, 'forminput' );
	}
	return true;
}

function renderFormInput (&$parser, $inFormName = '', $inSize = '25', $inValue = '', $inButtonStr = '', $inQueryStr = '') {
	$ap = SpecialPage::getPage('AddPage');
	$ap_url = $ap->getTitle()->getLocalURL();
	$str = <<<END
			<form action="$ap_url" method="get">
			<p><input type="text" name="page_name" size="$inSize" value="$inValue">

END;
	// if the add page URL looks like "index.php?title=Special:AddPage"
	// (i.e., it's in the default URL style), add in the title as a
	// hidden value
	if (($pos = strpos($ap_url, "title=")) > -1) {
		$str .= '			<input type="hidden" name="title" value="' . substr($ap_url, $pos + 6) . '">' . "\n";
	}
	if ($inFormName == '') {
		$str .= sffFormDropdownHTML();
	} else {
		$str .= '			<input type="hidden" name="form" value="' . $inFormName . '">' . "\n";
	}
	// recreate the passed-in query string as a set of hidden variables
	$query_components = explode('&', $inQueryStr);
	foreach ($query_components as $component) {
		$subcomponents = explode('=', $component, 2);
		$key = (isset($subcomponents[0])) ? $subcomponents[0] : '';
		$val = (isset($subcomponents[1])) ? $subcomponents[1] : '';
		$str .= '			<input type="hidden" name="' . $key . '" value="' . $val . '">' . "\n";
	}
	$button_str = ($inButtonStr != '') ? $inButtonStr : wfMsg('addoreditdata');
	$str .= <<<END
			<input type="submit" value="$button_str"></p>
			</form>
END;
	return array($str, 'noparse' => 'true');
}

?>
