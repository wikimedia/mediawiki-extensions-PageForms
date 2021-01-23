<?php

use MediaWiki\MediaWikiServices;

/**
 * Parser functions for Page Forms.
 *
 * @file
 * @ingroup PF
 *
 * The following parser functions are defined: #default_form, #forminput,
 * #formlink, #formredlink, #queryformlink, #arraymap, #arraymaptemplate
 * and #autoedit.
 *
 * '#default_form' is called as:
 * {{#default_form:formName}}
 *
 * This function sets the specified form to be the default form for pages
 * in that category, namespace or page. If called without an argument,
 * it specifies that the relevant page(s) should have no form.
 *
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
 *
 *
 * '#formlink' is called as:
 *
 * {{#formlink:form=|link text=|link type=|tooltip=|query string=|target=
 * |popup|reload|...additional query string values...}}
 *
 * This function returns HTML representing a link to a form; given that
 * no page name is entered by the user, the form must be one that
 * creates an automatic page name, or else it will display an error
 * message when the user clicks on the link.
 *
 * The first two arguments are mandatory:
 * 'form' is the name of the PF form, and 'link text' is the text of the link.
 * 'link type' is the type of the link: if set to 'button', the link will be
 * a button; if set to 'post button', the link will be a button that uses the
 * 'POST' method to send other values to the form; if set to anything else or
 * not called, it will be a standard hyperlink.
 * 'tooltip' sets a hovering tooltip text, if it's an actual link.
 * 'query string' is the text to be added to the generated URL's query string
 * (or, in the case of 'post button', to be sent as hidden inputs).
 * 'target' is an optional value, setting the name of the page to be
 * edited by the form.
 * 'reload' is an optional parameter that can be used alongside either
 * 'popup' or 'returnto'; it causes the page that the user ends up on after
 * submitting the form to get reloaded with 'action=purge'.
 *
 * Example: to create a link to add data with a form called
 * 'User' within a namespace also called 'User', and to have the form
 * preload with the page called 'UserStub', you could call the following:
 *
 * {{#formlink:form=User|link text=Add a user
 * |query string=namespace=User&preload=UserStub}}
 *
 *
 * '#formredlink' is called in a very similar way to 'formlink' - the only
 * difference is that it lacks the 'link text', 'link type' and 'tooltip'
 * parameters. Its behavior is quite similar to that of 'formlink' as well;
 * the only difference is that, when the 'target' is an existing page, it
 * creates a link directly to that page, instead of to a form to edit the
 * page.
 *
 *
 * '#queryformlink' links to Special:RunQuery, instead of Special:FormEdit.
 * It is called in the exact same way as 'formlink', though the
 * 'target' parameter should not be specified, and 'link text' is now optional,
 * since it has a default value of 'Run query' (in whatever language the
 * wiki is in).
 *
 *
 * '#arraymap' is called as:
 *
 * {{#arraymap:value|delimiter|var|formula|new_delimiter}}
 *
 * This function applies the same transformation to every section of a
 * delimited string; each such section, as dictated by the 'delimiter'
 * value, is given the same transformation that the 'var' string is
 * given in 'formula'. Finally, the transformed strings are joined
 * together using the 'new_delimiter' string. Both 'delimiter' and
 * 'new_delimiter' default to commas.
 *
 * Example: to take a semicolon-delimited list, and make each element
 * in the list a link, you could call the following:
 *
 * {{#arraymap:blue;red;yellow|;|x|[[x]]|;}}
 *
 *
 * '#arraymaptemplate' is called as:
 *
 * {{#arraymaptemplate:value|template|delimiter|new_delimiter}}
 *
 * This function makes the same template call for every section of a
 * delimited string; each such section, as dictated by the 'delimiter'
 * value, is passed as a first parameter to the template specified.
 * Finally, the transformed strings are joined together using the
 * 'new_delimiter' string. Both 'delimiter' and 'new_delimiter'
 * default to commas.
 *
 * Example: to take a semicolon-delimited list, and call a template
 * named 'Beautify' on each element in the list, you could call the
 * following:
 *
 * {{#arraymaptemplate:blue;red;yellow|Beautify|;|;}}
 *
 *
 * '#autoedit' is called as:
 *
 * {{#autoedit:form=|target=|link text=|link type=|tooltip=|query string=
 * |minor|reload}}
 *
 * This function creates a link or button that, when clicked on,
 * automatically modifies the specified page according to the values in the
 * 'query string' variable.
 *
 * The parameters of #autoedit are called in the same format as those
 * of #formlink. T The two additions are:
 * 'minor' - sets this to be a "minor edit"
 * 'reload' - causes the page to reload after the user clicks the button
 * or link.
 *
 * @author Yaron Koren
 * @author Sergey Chernyshev
 * @author Daniel Friesen
 * @author Barry Welch
 * @author Christoph Burgmer
 * @author Stephan Gambke
 * @author MWJames
 */

class PFParserFunctions {

	// static variable to guarantee that Javascript for autocompletion
	// only gets added to the page once
	private static $num_autocompletion_inputs = 0;

	public static function renderDefaultForm( Parser $parser ) {
		$curTitle = $parser->getTitle();

		$params = func_get_args();
		if ( !isset( $params[1] ) ) {
			return true;
		}
		$defaultForm = $params[1];

		$parserOutput = $parser->getOutput();
		$parserOutput->setProperty( 'PFDefaultForm', $defaultForm );

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

	public static function renderFormLink( Parser $parser ) {
		$params = func_get_args();
		array_shift( $params ); // We don't need the parser.
		$str = self::createFormLink( $parser, $params, 'formlink' );
		return [ $str, 'noparse' => true, 'isHTML' => true ];
	}

	public static function renderFormRedLink( Parser $parser ) {
		$params = func_get_args();
		array_shift( $params ); // We don't need the parser.
		$str = self::createFormLink( $parser, $params, 'formredlink' );
		return [ $str, 'noparse' => true, 'isHTML' => true ];
	}

	public static function renderQueryFormLink( Parser $parser ) {
		$params = func_get_args();
		array_shift( $params ); // We don't need the parser.
		$str = self::createFormLink( $parser, $params, 'queryformlink' );
		return [ $str, 'noparse' => true, 'isHTML' => true ];
	}

	private static function convertQueryString( $queryString, $inQueryArr ) {
		// Change HTML-encoded ampersands directly to URL-encoded
		// ampersands, so that the string doesn't get split up on the '&'.
		$queryString = str_replace( '&amp;', '%26', $queryString );
		// "Decode" any other HTML tags.
		$queryString = html_entity_decode( $queryString, ENT_QUOTES );
		// next, replace  Foo[Bar] += Baz  with  Foo[Bar+] = Baz
		// and do the same for -=
		// This way, parse_str won't strip out the += and -=
		$queryString = preg_replace( "/\[([^\]]+)\]\s*(\+|-)=/", "[$1$2]=", $queryString );
		$queryString = str_replace( '+', '%2B', $queryString ); // prevent decoding + to space character

		parse_str( $queryString, $arr );

		return PFUtils::array_merge_recursive_distinct( $inQueryArr, $arr );
	}

	public static function renderFormInput( Parser $parser ) {
		global $wgCapitalLinks;

		$params = func_get_args();
		array_shift( $params ); // don't need the parser

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
				$inQueryArr = self::convertQueryString( $value, $inQueryArr );
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
				self::loadScriptsForPopupForm( $parser );
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
				$inQueryArr = PFUtils::array_merge_recursive_distinct( $inQueryArr, $arr );
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
		if ( ( $pos = strpos( $fsURL, "title=" ) ) > -1 ) {
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
			$formInputAttrs['data-possible-forms'] = $listOfForms;
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

	/**
	 * {{#arraymap:value|delimiter|var|formula|new_delimiter}}
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @return string
	 */
	public static function renderArrayMap( Parser $parser, $frame, $args ) {
		// Set variables.
		$value = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$delimiter = isset( $args[1] ) ? trim( $frame->expand( $args[1] ) ) : ',';
		$var = isset( $args[2] ) ? trim( $frame->expand( $args[2], PPFrame::NO_ARGS | PPFrame::NO_TEMPLATES ) ) : 'x';
		$formula = isset( $args[3] ) ? $args[3] : 'x';
		$new_delimiter = isset( $args[4] ) ? trim( $frame->expand( $args[4] ) ) : ', ';
		$conjunction = isset( $args[5] ) ? trim( $frame->expand( $args[5] ) ) : $new_delimiter;
		# Unstrip some
		$delimiter = $parser->mStripState->unstripNoWiki( $delimiter );
		# Let '\n' represent newlines, and '\s' represent spaces.
		$delimiter = str_replace( [ '\n', '\s' ], [ "\n", ' ' ], $delimiter );
		$new_delimiter = str_replace( [ '\n', '\s' ], [ "\n", ' ' ], $new_delimiter );
		$conjunction = str_replace( [ '\n', '\s' ], [ "\n", ' ' ], $conjunction );

		if ( $delimiter == '' ) {
			$values_array = preg_split( '/(.)/u', $value, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
		} else {
			$values_array = explode( $delimiter, $value );
		}

		$results_array = [];
		// Add results to the results array only if the old value was
		// non-null, and the new, mapped value is non-null as well.
		foreach ( $values_array as $old_value ) {
			$old_value = trim( $old_value );
			if ( $old_value == '' ) {
				continue;
			}
			$result_value = $frame->expand( $formula, PPFrame::NO_ARGS | PPFrame::NO_TEMPLATES );
			$result_value = str_replace( $var, $old_value, $result_value );
			$result_value = $parser->preprocessToDom( $result_value, $frame->isTemplate() ? Parser::PTD_FOR_INCLUSION : 0 );
			$result_value = trim( $frame->expand( $result_value ) );
			if ( $result_value == '' ) {
				continue;
			}
			$results_array[] = $result_value;
		}
		if ( $conjunction != $new_delimiter ) {
			$conjunction = " " . trim( $conjunction ) . " ";
		}

		$result_text = "";
		$num_values = count( $results_array );
		for ( $i = 0; $i < $num_values; $i++ ) {
			if ( $i == 0 ) {
				$result_text .= $results_array[$i];
			} elseif ( $i == $num_values - 1 ) {
				$result_text .= $conjunction . $results_array[$i];
			} else {
				$result_text .= $new_delimiter . $results_array[$i];
			}
		}
		return $result_text;
	}

	/**
	 * {{#arraymaptemplate:value|template|delimiter|new_delimiter}}
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @return string
	 */
	public static function renderArrayMapTemplate( Parser $parser, $frame, $args ) {
		// Set variables.
		$value = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$template = isset( $args[1] ) ? trim( $frame->expand( $args[1] ) ) : '';
		$delimiter = isset( $args[2] ) ? trim( $frame->expand( $args[2] ) ) : ',';
		$new_delimiter = isset( $args[3] ) ? trim( $frame->expand( $args[3] ) ) : ', ';
		# Unstrip some
		$delimiter = $parser->mStripState->unstripNoWiki( $delimiter );
		# let '\n' represent newlines
		$delimiter = str_replace( '\n', "\n", $delimiter );
		$new_delimiter = str_replace( '\n', "\n", $new_delimiter );

		if ( $delimiter == '' ) {
			$values_array = preg_split( '/(.)/u', $value, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
		} else {
			$values_array = explode( $delimiter, $value );
		}

		$results_array = [];
		foreach ( $values_array as $old_value ) {
			$old_value = trim( $old_value );
			if ( $old_value == '' ) {
				continue;
			}
			$bracketed_value = $frame->virtualBracketedImplode( '{{', '|', '}}',
				$template, '1=' . $old_value );
			// Special handling if preprocessor class is set to
			// 'Preprocessor_Hash'.
			if ( $bracketed_value instanceof PPNode_Hash_Array ) {
				$bracketed_value = $bracketed_value->value;
			}
			$results_array[] = $parser->replaceVariables(
				implode( '', $bracketed_value ), $frame );
		}
		return implode( $new_delimiter, $results_array );
	}

	public static function renderAutoEdit( Parser $parser ) {
		global $wgPageFormsAutoeditNamespaces;

		$parser->getOutput()->addModules( 'ext.pageforms.autoedit' );
		$parser->getOutput()->preventClickjacking( true );

		// Set defaults.
		$formcontent = '';
		$linkString = null;
		$linkType = 'span';
		$summary = null;
		$minorEdit = false;
		$classString = 'autoedit-trigger';
		$inTooltip = null;
		$inQueryArr = [];
		$editTime = null;

		// Parse parameters.
		$params = func_get_args();
		array_shift( $params ); // don't need the parser

		foreach ( $params as $param ) {
			$elements = explode( '=', $param, 2 );

			$key = trim( $elements[ 0 ] );
			$value = ( count( $elements ) > 1 ) ? trim( $elements[ 1 ] ) : '';

			switch ( $key ) {
				case 'link text':
					$linkString = $parser->recursiveTagParse( $value );
					break;
				case 'link type':
					$linkType = $parser->recursiveTagParse( $value );
					break;
				case 'reload':
					$classString .= ' reload';
					break;
				case 'summary':
					$summary = $parser->recursiveTagParse( $value );
					break;
				case 'minor':
					$minorEdit = true;
					break;
				case 'query string' :
					$inQueryArr = self::convertQueryString( $value, $inQueryArr );
					break;

				case 'ok text':
				case 'error text':
					// do not parse ok text or error text yet. Will be parsed on api call
					$arr = [ $key => $value ];
					$inQueryArr = PFUtils::array_merge_recursive_distinct( $inQueryArr, $arr );
					break;
				case 'tooltip':
					$inTooltip = Sanitizer::decodeCharReferences( $value );
					break;

				case 'target':
				case 'title':
					$value = $parser->recursiveTagParse( $value );
					$arr = [ $key => $value ];
					$inQueryArr = PFUtils::array_merge_recursive_distinct( $inQueryArr, $arr );

					$targetTitle = Title::newFromText( $value );

					if ( $targetTitle !== null ) {
						$allowedNamespaces = array_merge(
							$wgPageFormsAutoeditNamespaces,
							[ NS_CATEGORY ]
						);
						if ( !in_array( $targetTitle->getNamespace(), $allowedNamespaces ) ) {
							$errorMsg = wfMessage( 'pf-autoedit-invalidnamespace', $targetTitle->getNsText() )->parse();
							return Html::element( 'div', [ 'class' => 'error' ], $errorMsg );
						}
						$targetWikiPage = WikiPage::factory( $targetTitle );
						$targetWikiPage->clear();
						$editTime = $targetWikiPage->getTimestamp();
					}

				default:
					$value = $parser->recursiveTagParse( $value );
					$arr = [ $key => $value ];
					$inQueryArr = PFUtils::array_merge_recursive_distinct( $inQueryArr, $arr );
			}
		}

		// query string has to be turned into hidden inputs.
		if ( !empty( $inQueryArr ) ) {
			$query_components = explode( '&', http_build_query( $inQueryArr, '', '&' ) );

			foreach ( $query_components as $query_component ) {
				$var_and_val = explode( '=', $query_component, 2 );
				if ( count( $var_and_val ) == 2 ) {
					$formcontent .= Html::hidden( urldecode( $var_and_val[0] ), urldecode( $var_and_val[1] ) );
				}
			}
		}

		if ( $linkString == null ) {
			return null;
		}

		if ( $linkType == 'button' ) {
			$attrs = [
				'flags' => 'progressive',
				'label' => $linkString,
				'classes' => [ $classString ]
			];
			if ( $inTooltip != null ) {
				$attrs['title'] = $inTooltip;
			}
			$parser->getOutput()->setEnableOOUI( true );
			OutputPage::setupOOUI();
			$linkElement = new OOUI\ButtonWidget( $attrs );
		} elseif ( $linkType == 'link' ) {
			$attrs = [ 'class' => $classString, 'href' => "#" ];
			if ( $inTooltip != null ) {
				$attrs['title'] = $inTooltip;
			}
			$linkElement = Html::rawElement( 'a', $attrs, $linkString );
		} else {
			$linkElement = Html::rawElement( 'span', [ 'class' => $classString ], $linkString );
		}

		if ( $summary == null ) {
			$summary = wfMessage( 'pf_autoedit_summary', "[[{$parser->getTitle()}]]" )->text();
		}

		$formcontent .= Html::hidden( 'wpSummary', $summary );

		if ( $minorEdit ) {
			$formcontent .= Html::hidden( 'wpMinoredit', true );
		}

		if ( $editTime !== null ) {
			$formcontent .= Html::hidden( 'wpEdittime', $editTime );
		}

		$form = Html::rawElement( 'form', [ 'class' => 'autoedit-data' ], $formcontent );

		$output = Html::rawElement( 'div', [ 'class' => 'autoedit' ],
				$linkElement .
				Html::rawElement( 'span', [ 'class' => "autoedit-result" ], null ) .
				$form
		);

		// Return output HTML.
		return [ $output, 'noparse' => true, 'isHTML' => true ];
	}

	private static function createFormLink( Parser $parser, $params, $parserFunctionName ) {
		// Set defaults.
		$inFormName = $inLinkStr = $inExistingPageLinkStr = $inLinkType =
			$inTooltip = $inTargetName = '';
		$hasPopup = $hasReturnTo = false;
		if ( $parserFunctionName == 'queryformlink' ) {
			$inLinkStr = wfMessage( 'runquery' )->parse();
		}
		$inCreatePage = false;
		$classStr = '';
		$inQueryArr = [];
		$targetWindow = '_self';

		// assign params
		// - support unlabelled params, for backwards compatibility
		// - parse and sanitize all parameter values
		foreach ( $params as $i => $param ) {
			$elements = explode( '=', $param, 2 );

			// set param_name and value
			if ( count( $elements ) > 1 ) {
				$param_name = trim( $elements[0] );

				// parse (and sanitize) parameter values
				$value = trim( $parser->recursiveTagParse( $elements[1] ) );
			} else {
				$param_name = null;

				// parse (and sanitize) parameter values
				$value = trim( $parser->recursiveTagParse( $param ) );
			}

			if ( $param_name == 'form' ) {
				$inFormName = $value;
			} elseif ( $param_name == 'link text' ) {
				$inLinkStr = $value;
			} elseif ( $param_name == 'existing page link text' ) {
				$inExistingPageLinkStr = $value;
			} elseif ( $param_name == 'link type' ) {
				$inLinkType = $value;
			} elseif ( $param_name == 'query string' ) {
				$inQueryArr = self::convertQueryString( $value, $inQueryArr );
			} elseif ( $param_name == 'tooltip' ) {
				$inTooltip = Sanitizer::decodeCharReferences( $value );
			} elseif ( $param_name == 'target' ) {
				$inTargetName = $value;
			} elseif ( $param_name == null && $value == 'popup' ) {
				self::loadScriptsForPopupForm( $parser );
				$classStr = 'popupformlink';
				$hasPopup = true;
			} elseif ( $param_name == null && $value == 'reload' ) {
				$classStr .= ' reload';
				$inQueryArr['reload'] = '1';
			} elseif ( $param_name == null && $value == 'new window' ) {
				$targetWindow = '_blank';
			} elseif ( $param_name == null && $value == 'create page' ) {
				$inCreatePage = true;
			} elseif ( $param_name !== null ) {
				$value = urlencode( $value );
				parse_str( "$param_name=$value", $arr );
				$inQueryArr = PFUtils::array_merge_recursive_distinct( $inQueryArr, $arr );
				if ( $param_name == 'returnto' ) {
					$hasReturnTo = true;
				}
			}
		}

		if ( $hasPopup && $hasReturnTo ) {
			return '<div class="error">Error: \'popup\' and \'returnto\' cannot be set in the same function.</div>';
		}

		// Not the most graceful way to do this, but it is the
		// easiest - if this is the #formredlink function, just
		// ignore whatever values were passed in for these params.
		if ( $parserFunctionName == 'formredlink' ) {
			$inLinkType = $inTooltip = null;
		}

		// If "red link only" was specified, and a target page was
		// specified, and it exists, just link to the page.
		if ( $inTargetName != '' ) {
			// Call urldecode() on it, in case the target was
			// set via {{PAGENAMEE}}, and the page name contains
			// an apostrophe or other unusual character.
			$targetTitle = Title::newFromText( urldecode( $inTargetName ) );
			$targetPageExists = ( $targetTitle != '' && $targetTitle->exists() );
		} else {
			$targetPageExists = false;
		}

		if ( $parserFunctionName == 'formredlink' && $targetPageExists ) {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			if ( $inExistingPageLinkStr == '' ) {
				return $linkRenderer->makeKnownLink( $targetTitle );
			} else {
				return $linkRenderer->makeKnownLink( $targetTitle, $inExistingPageLinkStr );
			}
		}

		// The page doesn't exist, so if 'create page' was
		// specified, create the page now.
		if ( $parserFunctionName == 'formredlink' &&
			$inCreatePage && $inTargetName != '' ) {
			$targetTitle = Title::newFromText( $inTargetName );
			PFFormLinker::createPageWithForm( $targetTitle, $inFormName );
		}

		if ( $parserFunctionName == 'queryformlink' ) {
			$formSpecialPage = PFUtils::getSpecialPage( 'RunQuery' );
		} else {
			$formSpecialPage = PFUtils::getSpecialPage( 'FormEdit' );
		}
		$formSpecialPageTitle = $formSpecialPage->getPageTitle();

		if ( $inFormName == '' ) {
			$query = [ 'target' => $inTargetName ];
			$link_url = $formSpecialPageTitle->getLocalURL( $query );
		} elseif ( strpos( $inFormName, '/' ) == true ) {
			$query = [ 'form' => $inFormName, 'target' => $inTargetName ];
			$link_url = $formSpecialPageTitle->getLocalURL( $query );
		} else {
			$link_url = $formSpecialPageTitle->getLocalURL() . "/$inFormName";
			if ( !empty( $inTargetName ) ) {
				$link_url .= "/$inTargetName";
			}
			$link_url = str_replace( ' ', '_', $link_url );
		}
		$hidden_inputs = "";
		if ( !empty( $inQueryArr ) ) {
			// Special handling for the buttons - query string
			// has to be turned into hidden inputs.
			if ( $inLinkType == 'button' || $inLinkType == 'post button' ) {
				$query_components = explode( '&', http_build_query( $inQueryArr, '', '&' ) );

				foreach ( $query_components as $query_component ) {
					$var_and_val = explode( '=', $query_component, 2 );
					if ( count( $var_and_val ) == 2 ) {
						$hidden_inputs .= Html::hidden( urldecode( $var_and_val[0] ), urldecode( $var_and_val[1] ) );
					}
				}
			} else {
				$link_url .= ( strstr( $link_url, '?' ) ) ? '&' : '?';
				$link_url .= str_replace( '+', '%20', http_build_query( $inQueryArr, '', '&' ) );
			}
		}
		if ( $inLinkType == 'button' || $inLinkType == 'post button' ) {
			$buttonAttrs = [
				'type' => 'submit',
				'value' => $inLinkStr,
				'title' => $inTooltip
			];
			$buttonHTML = Html::rawElement( 'button', $buttonAttrs, $inLinkStr );
			$formAttrs = [
				'action' => $link_url,
				'method' => ( $inLinkType == 'button' ) ? 'get' : 'post',
				'class' => $classStr,
				'target' => $targetWindow
			];
			$str = Html::rawElement( 'form', $formAttrs, $buttonHTML . $hidden_inputs );
		} else {
			// If a target page has been specified but it doesn't
			// exist, make it a red link.
			if ( !empty( $inTargetName ) ) {
				if ( !$targetPageExists ) {
					$classStr .= " new";
				}
				// If no link string was specified, make it
				// the name of the page.
				if ( $inLinkStr == '' ) {
					$inLinkStr = $inTargetName;
				}
			}
			$str = Html::rawElement( 'a', [ 'href' => $link_url, 'class' => $classStr, 'title' => $inTooltip, 'target' => $targetWindow ], $inLinkStr );
		}

		return $str;
	}

	private static function loadScriptsForPopupForm( Parser $parser ) {
		$parser->getOutput()->addModules( 'ext.pageforms.popupformedit' );
		return true;
	}

}
