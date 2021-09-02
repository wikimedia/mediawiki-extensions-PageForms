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

		$parser->getOutput()->addModules( 'ext.pageforms.templatedisplay' );

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
			$tableFieldValues[$fieldName] = $curFieldValue;
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
				if ( $templateField->getNamespace() != '' ) {
					$fieldValue = $templateField->getNamespace() . ":$fieldValue";
				}
				$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
				if ( $templateField->isList() ) {
					$formattedFieldValue = self::pageListText( $fieldValue, $templateField );
				} else {
					$fieldValueTitle = Title::newFromText( $fieldValue );
					$formattedFieldValue = PFUtils::makeLink( $linkRenderer, $fieldValueTitle );
				}
			} elseif ( $fieldType == 'Coordinates' ) {
				$formattedFieldValue = self::mapText( $fieldValue, $format, $parser );
			} elseif ( $fieldType == 'Rating' ) {
				$formattedFieldValue = self::ratingText( $fieldValue );
			} elseif ( $fieldType == 'File' ) {
				$formattedFieldValue = self::fileText( $fieldValue );
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
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
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
			$title = Title::newFromText( $fieldValue );
			$text .= PFUtils::makeLink( $linkRenderer, $title );
		}
		return $text;
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
		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile( $title );
		} else {
			$file = wfLocalFile( $title );
		}
		return Linker::makeThumbLinkObj(
			$title,
			$file,
			$value,
			'',
			'left'
		);
	}

}
