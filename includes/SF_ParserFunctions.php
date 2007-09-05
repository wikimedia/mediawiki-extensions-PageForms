<?php
/**
 * Parser functions for Semantic Forms.
 *
 * Two parser functions are currently defined: 'forminput' and 'arraymap'.
 *
 * 'forminput' is called as:
 *
 * {{#forminput:form_name|size|value|button_text|query_string}}
 *
 * This function returns HTML representing a form to let the user enter the
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
 * 'arraymap' is called as:
 *
 * {{#arraymap:value|delimiter|var|new_value|new_delimiter}}
 *
 * This function applies the same transformation to every section of a
 * delimited string; each such section, as dictated by the 'delimiter'
 * value, is given the same transformation that the 'var' string is
 * given in 'new_value'. Finally, the transformed strings are joined
 * together using the 'new_delimiter' string. Both 'delimiter' and
 * 'new_delimiter' default to commas.
 *
 * Example: to take a semicolon-delimited list, and place the attribute
 * 'Has color' around each element in the list, you could call the
 * following:
 *
 * {#arraymap:blue;red;yellow|;|x|[[Has color:=x]]|;}}
 *
 * @author Yaron Koren
 */


function sfgParserFunctions () {
    global $wgParser;
    $wgParser->setFunctionHook('forminput', 'renderFormInput');
    $wgParser->setFunctionHook('arraymap', 'renderArrayMap');
}

function sfgLanguageGetMagic( &$magicWords, $langCode = "en" ) {
	switch ( $langCode ) {
	default:
		$magicWords['forminput']	= array ( 0, 'forminput' );
		$magicWords['arraymap']		= array ( 0, 'arraymap' );
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

/**
 * {{#arraymap:value|delimiter|var|new_value|new_delimiter}}
 */
function renderArrayMap ( &$parser, $value = '', $delimiter = ',', $var = 'x', $new_value = '', $new_delimiter = ', ' ) {
	$ret = "";
	$values_array = explode($delimiter, $value);
	foreach ($values_array as $i =>$cur_value) {
		if ($i > 0)
			$ret .= $new_delimiter;
		# remove whitespace
		$ret .= str_replace($var, trim($cur_value), $new_value);
	}
	return $ret;
}

?>
