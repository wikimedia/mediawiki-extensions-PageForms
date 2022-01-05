<?php
use MediaWiki\MediaWikiServices;

/**
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
 */

class PFFormLink {

	public static function run( Parser $parser ) {
		$params = func_get_args();
		// We don't need the parser.
		array_shift( $params );
		$str = self::createFormLink( $parser, $params );
		return [ $str, 'noparse' => true, 'isHTML' => true ];
	}

	protected static function createFormLink( Parser $parser, $params ) {
		// Set defaults.
		$inFormName = $inLinkStr = $inExistingPageLinkStr = $inLinkType =
			$inTooltip = $inTargetName = '';
		$hasPopup = $hasReturnTo = false;
		$className = static::class;
		if ( $className == 'PFQueryFormLink' ) {
			$inLinkStr = wfMessage( 'runquery' )->parse();
		}
		$inCreatePage = false;
		$classStr = '';
		$inQueryArr = [];
		$targetWindow = '_self';

		// Needed for the 'next' icon.
		$parser->getOutput()->addModules( 'oojs-ui.styles.icons-movement' );

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
				$inQueryArr = PFAutoEdit::convertQueryString( $value, $inQueryArr );
			} elseif ( $param_name == 'tooltip' ) {
				$inTooltip = Sanitizer::decodeCharReferences( $value );
			} elseif ( $param_name == 'target' ) {
				$inTargetName = Sanitizer::decodeCharReferences( $value );
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
				$inQueryArr = PFUtils::arrayMergeRecursiveDistinct( $inQueryArr, $arr );
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
		if ( $className == 'PFFormRedLink' ) {
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

		if ( $className == 'PFFormRedLink' && $targetPageExists ) {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			if ( $inExistingPageLinkStr == '' ) {
				return $linkRenderer->makeKnownLink( $targetTitle );
			} else {
				return $linkRenderer->makeKnownLink( $targetTitle, $inExistingPageLinkStr );
			}
		}

		// The page doesn't exist, so if 'create page' was
		// specified, create the page now.
		if ( $className == 'PFFormRedLink' &&
			$inCreatePage && $inTargetName != '' ) {
			$targetTitle = Title::newFromText( $inTargetName );
			PFFormLinker::createPageWithForm( $targetTitle, $inFormName, $inQueryArr );
		}

		if ( $className == 'PFQueryFormLink' ) {
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
			$parser->getOutput()->setEnableOOUI( true );
			OutputPage::setupOOUI();
			$buttonAttrs = [
				'type' => 'submit',
				'label' => $inLinkStr,
				'title' => $inTooltip,
				'flags' => 'progressive',
				'icon' => 'next'
			];
			$buttonHTML = new OOUI\ButtonInputWidget( $buttonAttrs );
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

	public static function loadScriptsForPopupForm( Parser $parser ) {
		$parser->getOutput()->addModules( 'ext.pageforms.popupformedit' );
		return true;
	}
}
