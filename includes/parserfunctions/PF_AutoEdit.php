<?php

/**
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
 */

class PFAutoEdit {
	public static function run( Parser $parser ) {
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
		$latestRevId = null;
		$confirmEdit = false;

		// Parse parameters.
		$params = func_get_args();
		// We don't need the parser.
		array_shift( $params );

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
				case 'confirm':
					$confirmEdit = true;
					break;
				case 'query string':
					$inQueryArr = self::convertQueryString( $value, $inQueryArr );
					break;

				case 'ok text':
				case 'error text':
					// do not parse ok text or error text yet. Will be parsed on api call
					$arr = [ $key => $value ];
					$inQueryArr = PFUtils::arrayMergeRecursiveDistinct( $inQueryArr, $arr );
					break;
				case 'tooltip':
					$inTooltip = Sanitizer::decodeCharReferences( $value );
					break;

				case 'target':
				case 'title':
					$value = $parser->recursiveTagParse( $value );
					$arr = [ $key => $value ];
					$inQueryArr = PFUtils::arrayMergeRecursiveDistinct( $inQueryArr, $arr );

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
						$latestRevId = $targetWikiPage->getLatest();
					}
					break;

				default:
					$value = $parser->recursiveTagParse( $value );
					$arr = [ $key => $value ];
					$inQueryArr = PFUtils::arrayMergeRecursiveDistinct( $inQueryArr, $arr );
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
		if ( $latestRevId !== null ) {
			$formcontent .= Html::hidden( 'editRevId', $latestRevId );
		}

		if ( $confirmEdit ) {
			$formAttrs = [ 'class' => [ 'autoedit-data', 'confirm-edit' ] ];
		} else {
			$formAttrs = [ 'class' => 'autoedit-data' ];
		}

		$form = Html::rawElement( 'form', $formAttrs, $formcontent );

		$output = Html::rawElement( 'div', [ 'class' => 'autoedit' ],
				$linkElement .
				Html::rawElement( 'span', [ 'class' => "autoedit-result" ], null ) .
				$form
		);

		// Return output HTML.
		return [ $output, 'noparse' => true, 'isHTML' => true ];
	}

	public static function convertQueryString( $queryString, $inQueryArr ) {
		// Change HTML-encoded ampersands directly to URL-encoded
		// ampersands, so that the string doesn't get split up on the '&'.
		$queryString = str_replace( '&amp;', '%26', $queryString );
		// "Decode" any other HTML tags.
		$queryString = html_entity_decode( $queryString, ENT_QUOTES );
		// next, replace  Foo[Bar] += Baz  with  Foo[Bar+] = Baz
		// and do the same for -=
		// This way, parse_str won't strip out the += and -=
		$queryString = preg_replace( "/\[([^\]]+)\]\s*(\+|-)=/", "[$1$2]=", $queryString );
		// Prevent "decoding" + into a space character
		$queryString = str_replace( '+', '%2B', $queryString );

		parse_str( $queryString, $arr );

		return PFUtils::arrayMergeRecursiveDistinct( $inQueryArr, $arr );
	}
}
