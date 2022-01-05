<?php

/**
 * '#default_form' is called as:
 * {{#default_form:formName}}
 *
 * This function sets the specified form to be the default form for pages
 * in that category, namespace or page. If called without an argument,
 * it specifies that the relevant page(s) should have no form.
 */

class PFDefaultForm {
	public static function run( Parser $parser ) {
		$curTitle = $parser->getTitle();

		$params = func_get_args();
		if ( !isset( $params[1] ) ) {
			return true;
		}
		$defaultForm = $params[1];

		$parserOutput = $parser->getOutput();
		if ( method_exists( $parserOutput, 'setPageProperty' ) ) {
			// MW 1.38+
			$parserOutput->setPageProperty( 'PFDefaultForm', $defaultForm );
		} else {
			$parserOutput->setProperty( 'PFDefaultForm', $defaultForm );
		}

		// Display information on the page, if this is a category.
		if ( $curTitle->getNamespace() == NS_CATEGORY ) {
			$defaultFormPage = Title::makeTitleSafe( PF_NS_FORM, $defaultForm );
			if ( $defaultFormPage == null ) {
				return '<div class="error">Error: No form found with name "' . $defaultForm . '".</div>';
			}
			$defaultFormPageText = $defaultFormPage->getPrefixedText();
			$defaultFormPageLink = "[[$defaultFormPageText|$defaultForm]]";
			$text = wfMessage( 'pf_category_hasdefaultform', $defaultFormPageLink )->text();
			return $text;
		}

		// It's not a category - display nothing.
	}
}
