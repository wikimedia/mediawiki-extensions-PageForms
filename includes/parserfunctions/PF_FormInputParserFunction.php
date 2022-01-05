<?php
/**
 * '#forminput' is called as:
 *
 * {{#forminput:form=|size=|default value=|button text=|query string=
 * |autocomplete on category=|autocomplete on namespace=
 * |popup|reload|...additional query string values...}}
 *
 * This function returns HTML representing a form to let the user enter the
 * name of a page to be added or edited using a Page Forms form. All
 * arguments are optional. 'form' is the name of the PF form to be used;
 * if it is left empty, a dropdown will appear, letting the user chose among
 * all existing forms. 'size' represents the size of the text input (default
 * is 25), and 'default value' is the starting value of the input.
 * 'button text' is the text that will appear on the "submit" button, and
 * 'query string' is the set of values that you want passed in through the
 * query string to the form. (Query string values can also be passed in
 * directly as parameters.) Finally, you can can specify that the user will
 * get autocompletion using the values from a category or namespace of your
 * choice, using 'autocomplete on category' or 'autocomplete on namespace'
 * (you can only use one). To autcomplete on all pages in the main (blank)
 * namespace, specify "autocomplete on namespace=main".
 * 'reload' is an optional parameter that can be used alongside either
 * 'popup' or 'returnto'; it causes the page that the user ends up on after
 * submitting the form to get reloaded with 'action=purge'.
 *
 * Example: to create an input to add or edit a page with a form called
 * 'User' within a namespace also called 'User', and to have the form
 * preload with the page called 'UserStub', you could call the following:
 *
 * {{#forminput:form=User|button text=Add or edit user
 * |query string=namespace=User&preload=UserStub}}
 */

class PFFormInputParserFunction {
	/**
	 * static variable to guarantee that JavaScript for autocompletion
	 * only gets added to the page once.
	 */
	private static $num_autocompletion_inputs = 0;

	public static function run( Parser $parser ) {
		global $wgCapitalLinks;

		$params = func_get_args();
		// We don't need the parser.
		array_shift( $params );

		$parser->getOutput()->addModules( 'ext.pageforms.forminput' );

		// Set defaults.
		$inFormName = $inValue = $inButtonStr = '';
		$inQueryArr = [];
		$inAutocompletionSource = '';
		$inSize = 25;
		$classStr = "pfFormInput";
		$inNamespaceSelector = null;
		$inPlaceholder = null;
		$inAutofocus = true;
		$hasPopup = $hasReturnTo = false;

		// Assign params.
		foreach ( $params as $i => $param ) {
			$elements = explode( '=', $param, 2 );

			// Set param name and value.
			if ( count( $elements ) > 1 ) {
				$paramName = trim( $elements[0] );
				// Parse (and sanitize) parameter values.
				// We call recursivePreprocess() and not
				// recursiveTagParse() so that URL values will
				// not be turned into links.
				$value = trim( $parser->recursivePreprocess( html_entity_decode( $elements[1], ENT_QUOTES ) ) );
			} else {
				$paramName = trim( $param );
				$value = null;
			}

			if ( $paramName == 'form' ) {
				$inFormName = $value;
			} elseif ( $paramName == 'size' ) {
				$inSize = $value;
			} elseif ( $paramName == 'default value' ) {
				$inValue = $value;
			} elseif ( $paramName == 'button text' ) {
				$inButtonStr = $value;
			} elseif ( $paramName == 'query string' ) {
				$inQueryArr = PFAutoEdit::convertQueryString( $value, $inQueryArr );
			} elseif ( $paramName == 'autocomplete on category' ) {
				$inAutocompletionSource = $value;
				$autocompletionType = 'category';
			} elseif ( $paramName == 'autocomplete on namespace' ) {
				$inAutocompletionSource = $value;
				$autocompletionType = 'namespace';
			} elseif ( $paramName == 'namespace selector' ) {
				$inNamespaceSelector = explode( ',', $value );
			} elseif ( $paramName == 'placeholder' ) {
				$inPlaceholder = $value;
			} elseif ( $paramName == 'popup' ) {
				PFFormLink::loadScriptsForPopupForm( $parser );
				$classStr .= ' popupforminput';
				$hasPopup = true;
			} elseif ( $paramName == 'reload' ) {
				$classStr .= ' reload';
				$inQueryArr['reload'] = '1';
			} elseif ( $paramName == 'no autofocus' ) {
				$inAutofocus = false;
			} else {
				$value = urlencode( $value );
				parse_str( "$paramName=$value", $arr );
				$inQueryArr = PFUtils::arrayMergeRecursiveDistinct( $inQueryArr, $arr );
				if ( $paramName == 'returnto' ) {
					$hasReturnTo = true;
				}
			}
		}

		if ( $hasPopup && $hasReturnTo ) {
			return '<div class="error">Error: \'popup\' and \'returnto\' cannot be set in the same function.</div>';
		}

		$formInputAttrs = [
			'class' => 'pfFormInputWrapper',
			'data-size' => $inSize
		];

		$formContents = '';

		if ( $inValue != null ) {
			$formInputAttrs['data-default-value'] = $inValue;
		}

		if ( $inNamespaceSelector !== null ) {
			foreach ( $inNamespaceSelector as &$nsName ) {
				$nsName = htmlspecialchars( trim( $nsName ) );
			}
			$possibleNamespacesStr = implode( '|', $inNamespaceSelector );
			$formInputAttrs['data-possible-namespaces'] = $possibleNamespacesStr;
		}

		if ( $inPlaceholder != null ) {
			$formInputAttrs['data-placeholder'] = $inPlaceholder;
		}
		if ( $inAutofocus ) {
			$formInputAttrs['data-autofocus'] = true;
		}
		if ( !$wgCapitalLinks ) {
			$formInputAttrs['data-autocapitalize'] = 'off';
		}

		// Now apply the necessary settings and JavaScript, depending
		// on whether or not there's autocompletion (and whether the
		// autocompletion is local or remote).
		$input_num = 1;
		if ( !empty( $inAutocompletionSource ) ) {
			self::$num_autocompletion_inputs++;
			$input_num = self::$num_autocompletion_inputs;
			$inputID = 'input_' . $input_num;
			$formInputAttrs['id'] = $inputID;
			// This code formerly only used remote autocompletion
			// when the number of autocompletion values was above
			// a certain limit - as happens in regular forms -
			// but local autocompletion didn't always work,
			// apparently due to page caching.
			$formInputAttrs['data-autocomplete-settings'] = $inAutocompletionSource;
			$formInputAttrs['data-autocomplete-data-type'] = $autocompletionType;
		}

		// If the form start URL looks like "index.php?title=Special:FormStart"
		// (i.e., it's in the default URL style), add in the title as a
		// hidden value
		$fs = PFUtils::getSpecialPage( 'FormStart' );
		$fsURL = $fs->getPageTitle()->getLocalURL();
		$pos = strpos( $fsURL, "title=" );
		if ( $pos > -1 ) {
			$formContents .= Html::hidden( "title", urldecode( substr( $fsURL, $pos + 6 ) ) );
		}
		$listOfForms = preg_split( '~(?<!\\\)' . preg_quote( ',', '~' ) . '~', $inFormName );
		foreach ( $listOfForms as & $formName ) {
			$formName = str_replace( "\,", ",", $formName );
		}
		unset( $formName );
		if ( $inFormName == '' ) {
			try {
				$allForms = PFUtils::getAllForms();
			} catch ( MWException $e ) {
				return Html::element( 'div', [ 'class' => 'error' ], $e->getMessage() );
			}
			$formInputAttrs['data-possible-forms'] = implode( '|', $allForms );
			$formInputAttrs['data-form-label'] = PFUtils::getFormDropdownLabel();
		} elseif ( count( $listOfForms ) == 1 ) {
			$inFormName = str_replace( '\,', ',', $inFormName );
			$formContents .= Html::hidden( "form", $inFormName );
		} else {
			$formInputAttrs['data-possible-forms'] = implode( '|', $listOfForms );
			$formInputAttrs['data-form-label'] = PFUtils::getFormDropdownLabel();
		}

		// Recreate the passed-in query string as a set of hidden
		// variables.
		if ( !empty( $inQueryArr ) ) {
			// Query string has to be turned into hidden inputs.
			$query_components = explode( '&', http_build_query( $inQueryArr, '', '&' ) );

			foreach ( $query_components as $query_component ) {
				$var_and_val = explode( '=', $query_component, 2 );
				if ( count( $var_and_val ) == 2 ) {
					$formContents .= Html::hidden( urldecode( $var_and_val[0] ), urldecode( $var_and_val[1] ) );
				}
			}
		}

		$formInputAttrs['data-button-label'] = ( $inButtonStr != '' ) ? $inButtonStr : wfMessage( 'pf_formstart_createoredit' )->escaped();
		$formContents .= Html::element( 'div', $formInputAttrs, null );

		Hooks::run( 'PageForms::FormInputEnd', [ $params, &$formContents ] );

		$str = "\t" . Html::rawElement( 'form', [
				'name' => 'createbox',
				'action' => $fsURL,
				'method' => 'get',
				'class' => $classStr
			], '<p>' . $formContents . '</p>'
		) . "\n";

		return [ $str, 'noparse' => true, 'isHTML' => true ];
	}
}
