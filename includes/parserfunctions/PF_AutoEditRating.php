<?php

/**
 * '#autoedit_rating' is called as:
 *
 * {{#autoedit_rating:form=|target=|rating field=|value=|star width=|num stars=|allow half stars=}}
 *
 */

use MediaWiki\MediaWikiServices;

class PFAutoEditRating {
	public static function run( Parser $parser ) {
		global $wgPageFormsAutoeditNamespaces;

		$parser->getOutput()->addModules( [ 'ext.pageforms.autoeditrating' ] );

		// Set defaults.
		$formcontent = '';
		$summary = null;
		$minorEdit = false;
		$inQueryArr = [];
		$editTime = null;
		$latestRevId = null;
		$confirmEdit = false;
		$ratingAttrs = [
			'class' => 'pfRating',
			'data-curvalue' => 0,
			'data-starwidth' => '24px',
			'data-numstars' => 5,
			'data-allows-half' => false
		];

		// Parse parameters.
		$params = func_get_args();
		array_shift( $params );

		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();

		foreach ( $params as $param ) {
			$elements = explode( '=', $param, 2 );

			$key = trim( $elements[0] );
			$value = ( count( $elements ) > 1 ) ? trim( $elements[1] ) : '';

			switch ( $key ) {
				case 'rating field':
					$inQueryArr = PFAutoEdit::convertQueryString( $value, $inQueryArr );
					break;
				case 'target':
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
						$targetWikiPage = $wikiPageFactory->newFromTitle( $targetTitle );
						$targetWikiPage->clear();
						$editTime = $targetWikiPage->getTimestamp();
						$latestRevId = $targetWikiPage->getLatest();
					}
					break;
				case 'value':
					$ratingAttrs['data-curvalue'] = $parser->recursiveTagParse( $value );
					break;
				case 'star width':
					$ratingAttrs['data-starwidth'] = $parser->recursiveTagParse( $value ) . 'px';
					break;
				case 'num stars':
					$ratingAttrs['data-numstars'] = $parser->recursiveTagParse( $value );
					break;
				case 'allow half stars':
					$ratingAttrs['data-allows-half'] = true;
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
					$formcontent .= Html::hidden( urldecode( $var_and_val[0] ), urldecode( $var_and_val[1] ), $var_and_val[1] == '' ? [ 'id' => 'ratingInput' ] : [] );
				}
			}
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

		$text = Html::element( 'div', $ratingAttrs );
		$output = Html::rawElement( 'div', [ 'class' => 'autoedit' ],
			$text .
			Html::rawElement( 'span', [ 'class' => "autoedit-result" ], null ) .
			$form
		);

		return [ $output, 'noparse' => true, 'isHTML' => true ];
	}
}
