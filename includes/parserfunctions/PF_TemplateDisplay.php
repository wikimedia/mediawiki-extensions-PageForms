<?php

use MediaWiki\MediaWikiServices;

/**
 * Defines the #template_display parser function.
 *
 * @author Yaron Koren
 */

class PFTemplateDisplay {

	public static function run( &$parser, $frame, $args ) {
		$title = $parser->getTitle();
		$params = [];
		foreach ( $args as $arg ) {
			$params[] = trim( $frame->expand( $arg ) );
		}

		$templateFields = [];
		$format = 'infobox';
		$tableFieldValues = [];

		$templateTitle = $frame->title;
		$template = PFTemplate::newFromName( $templateTitle->getText() );
		$templateParams = $template->getTemplateParams();
		if ( $templateParams == null ) {
			return '<div class="error">' . 'Error: #template_params must be called in the template "' . $templateTitle->getText() . '".</div>';
		}

		$parser->getOutput()->addModules( [ 'ext.pageforms.templatedisplay' ] );

		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );
			if ( count( $parts ) == 1 ) {
				// No such handled params at the moment.
			} else {
				$key = trim( $parts[0] );
				$value = trim( $parts[1] );
				if ( $key == '_format' ) {
					$format = $value;
				} else {
					$tableFieldValues[$key] = $value;
				}
			}
		}

		// Get all the values in this template call.
		$templateFields = $template->getTemplateFields();
		foreach ( $templateFields as $fieldName => $templateField ) {
			$curFieldValue = $frame->getArgument( $fieldName );
			if ( $curFieldValue == null ) {
				$unescapedFieldName = str_replace( '_', ' ', $fieldName );
				$curFieldValue = $frame->getArgument( $unescapedFieldName );
			}
			$tableFieldValues[$fieldName] = $parser->internalParse( $curFieldValue );
		}

		if ( $format == 'table' ) {
			$text = '<table class="wikitable">' . "\n";
		} elseif ( $format == 'infobox' ) {
			$text = '<table class="infoboxTable">' . "\n";
			$text .= '<tr><th colspan="2" class="infoboxTitle">' . $title->getFullText() . '</th></tr>' . "\n";
		} else {
			$text = '';
		}
		foreach ( $tableFieldValues as $fieldName => $fieldValue ) {
			if ( !array_key_exists( $fieldName, $templateFields ) ) {
				continue;
			}
			$templateField = $templateFields[$fieldName];
			$fieldDisplay = $templateField->getDisplay();
			if ( $fieldDisplay == 'hidden' ) {
				continue;
			}
			if ( $fieldDisplay == 'nonempty' && $fieldValue == '' ) {
				continue;
			}

			$fieldType = $templateField->getFieldType();
			// Ignore stuff like 'Enumeration' - we don't need it.
			$realFieldType = $templateField->getRealFieldType();
			if ( $realFieldType !== null ) {
				$fieldType = $realFieldType;
			}

			$fieldLabel = $templateField->getLabel();
			if ( $fieldLabel == null ) {
				$fieldLabel = $fieldName;
			}

			// If this field holds a template, and it has a value,
			// create a separate fieldset, outside of the table
			// (if this is a table) to display that other set of
			// data.
			// Possibly it would be better to do this not based on
			// whether this field holds a template, but rather on
			// whether the field value contains a <table> tag.
			// However, with the current parser, it may not be
			// possible for this parser function to know that
			// information.
			$holdsTemplate = $templateField->getHoldsTemplate();
			if ( $format !== 'infobox' && $holdsTemplate !== null ) {
				if ( trim( $fieldValue ) !== '' ) {
					if ( $format == 'table' ) {
						$text .= "</table>\n";
					}
					$text .= "<fieldset><legend>$fieldLabel</legend>";
					$text .= $fieldValue;
					$text .= '</fieldset>' . "\n";
					if ( $format == 'table' ) {
						$text .= '<table class="wikitable">' . "\n";
					}
				}
				continue;
			}
			if ( trim( $fieldValue ) == '' ) {
				$formattedFieldValue = '';
			} elseif ( $fieldType == 'Page' ) {
				if ( $templateField->isList() ) {
					$formattedFieldValue = self::pageListText( $fieldValue, $templateField );
				} else {
					$formattedFieldValue = self::pageText( $fieldValue, $templateField );
				}
			} elseif ( $fieldType == 'Coordinates' ) {
				$formattedFieldValue = self::mapText( $fieldValue, $format, $parser );
			} elseif ( $fieldType == 'Rating' ) {
				$formattedFieldValue = self::ratingText( $fieldValue );
			} elseif ( $fieldType == 'File' ) {
				$formattedFieldValue = self::fileText( $fieldValue );
			} elseif ( $templateField->isList() ) {
				$formattedFieldValue = self::stringListText( $fieldValue, $templateField );
			} else {
				$formattedFieldValue = $fieldValue;
			}
			if ( $format == 'table' || $format == 'infobox' ) {
				$text .= "<tr><th>$fieldLabel</th><td>$formattedFieldValue</td></tr>\n";
			} elseif ( $format == 'sections' ) {
				$text .= "<h2>$fieldLabel</h2>\n$formattedFieldValue\n\n";
			} else {
				$text .= "<strong>$fieldLabel:</strong> $formattedFieldValue\n\n";
			}
		}

		if ( $format == 'table' || $format == 'infobox' ) {
			$text .= "</table>\n";
		}

		return [ $text, 'noparse' => true, 'isHTML' => true ];
	}

	private static function mapText( $coordinatesStr, $format, $parser ) {
		if ( $coordinatesStr == '' ) {
			return '';
		}

		$mappingFormat = new CargoMapsFormat( $parser->getOutput() );

		try {
			list( $lat, $lon ) = CargoUtils::parseCoordinatesString( $coordinatesStr );
		} catch ( MWException $e ) {
			return '';
		}
		$valuesTable = [ [ 'Coords  lat' => $lat, 'Coords  lon' => $lon ] ];
		$formattedValuesTable = $valuesTable;
		$coordsDesc = new CargoFieldDescription();
		$coordsDesc->mType = 'Coordinates';
		$fieldDescriptions = [ 'Coords' => $coordsDesc ];
		$displayParams = [];
		if ( $format == 'infobox' ) {
			$displayParams['width'] = '300';
			$displayParams['height'] = '300';
		}

		try {
			$text = $mappingFormat->display( $valuesTable,
				$formattedValuesTable, $fieldDescriptions,
				$displayParams );
		} catch ( MWException $e ) {
			return '';
		}
		return $text;
	}

	private static function pageListText( $value, $templateField ) {
		$text = '';
		$delimiter = $templateField->getDelimiter();
		$fieldValues = explode( $delimiter, $value );
		foreach ( $fieldValues as $i => $fieldValue ) {
			if ( trim( $fieldValue ) == '' ) {
				continue;
			}
			if ( $i > 0 ) {
				$text .= ' <span class="CargoDelimiter">&bull;</span> ';
			}
			$text .= self::pageText( $fieldValue, $templateField );
		}
		return $text;
	}

	private static function pageText( $value, $templateField ) {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$namespace = $templateField->getNamespace();
		$title = Title::makeTitleSafe( $namespace, $value );
		if ( $title->exists() ) {
			return PFUtils::makeLink( $linkRenderer, $title );
		}
		$form = $templateField->getForm();
		if ( $form == null ) {
			return PFUtils::makeLink( $linkRenderer, $title );
		}
		// The page doesn't exist, and a form has been found for this
		// template field - link to this form for this page.
		$formSpecialPage = PFUtils::getSpecialPage( 'FormEdit' );
		$formSpecialPageTitle = $formSpecialPage->getPageTitle();
		$target = $title->getFullText();
		$formURL = $formSpecialPageTitle->getLocalURL() .
			str_replace( ' ', '_', "/$form/$target" );
		return Html::rawElement( 'a', [ 'href' => $formURL, 'class' => 'new' ], $value );
	}

	private static function stringListText( $value, $templateField ) {
		$delimiter = $templateField->getDelimiter();
		$fieldValues = explode( $delimiter, $value );
		return implode( ' <span class="CargoDelimiter">&bull;</span> ', $fieldValues );
	}

	private static function ratingText( $value ) {
		global $wgServer, $wgScriptPath;

		$rate = $value * 20;
		$url = $wgServer . $wgScriptPath . '/' . 'extensions/Cargo/resources/images/star-rating-sprite-1.png';
		$text = '<span style="display: block; width: 65px; height: 13px; background: url(\'' . $url . '\') 0 0;">
			<span style="display: block; width: ' . $rate . '%; height: 13px; background: url(\'' . $url . '\') 0 -13px;"></span>';
		return $text;
	}

	private static function fileText( $value ) {
		$title = Title::newFromText( $value, NS_FILE );
		if ( $title == null || !$title->exists() ) {
			return $value;
		}

		if ( $title->isRedirect() ) {
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );

			$title = $wikiPage->getRedirectTarget();
			if ( !$title->exists() ) {
				return $title->getText();
			}
		}

		$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile( $title );
		return Linker::makeThumbLinkObj(
			$title,
			$file,
			$value,
			'',
			'left'
		);
	}

}
