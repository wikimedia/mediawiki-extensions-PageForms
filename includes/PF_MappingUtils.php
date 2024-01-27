<?php
/**
 * Methods for mapping values to labels
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;

class PFMappingUtils {

	/**
	 * @param array $args
	 * @param bool $useDisplayTitle
	 * @return string|null
	 */
	public static function getMappingType( array $args, bool $useDisplayTitle = false ) {
		$mappingType = null;
		if ( array_key_exists( 'mapping property', $args ) ) {
			$mappingType = 'mapping property';
		} elseif ( array_key_exists( 'mapping template', $args ) ) {
			$mappingType = 'mapping template';
		} elseif ( array_key_exists( 'mapping cargo table', $args ) &&
		array_key_exists( 'mapping cargo field', $args ) ) {
			// @todo: or use 'cargo field'?
			$mappingType = 'mapping cargo field';
		} elseif ( array_key_exists( 'mapping using translate', $args ) ) {
			$mappingType = 'mapping using translate';
		} elseif ( $useDisplayTitle ) {
			$mappingType = 'displaytitle';
		}
		return $mappingType;
	}

	/**
	 * Map values if possible and return a named (associative) array
	 * @param array $values
	 * @param array $args
	 * @return array
	 */
	public static function getMappedValuesForInput( array $values, array $args = [] ) {
		global $wgPageFormsUseDisplayTitle;
		$mappingType = self::getMappingType( $args, $wgPageFormsUseDisplayTitle );
		if ( self::isIndexedArray( $values ) == false ) {
			// already named associative
			$pages = array_keys( $values );
			$values = self::getMappedValues( $pages, $mappingType, $args, $wgPageFormsUseDisplayTitle );
			$res = $values;
		} elseif ( $mappingType !== null ) {
			$res = self::getMappedValues( $values, $mappingType, $args, $wgPageFormsUseDisplayTitle );
		} else {
			$res = [];
			foreach ( $values as $key => $value ) {
				$res[$value] = $value;
			}
		}
		return $res;
	}

	/**
	 * Check if array is indexed/sequential (true), else named/associative (false)
	 * @param array $arr
	 * @return string
	 */
	private static function isIndexedArray( $arr ) {
		if ( array_keys( $arr ) == range( 0, count( $arr ) - 1 ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return named array of mapped values
	 * Static version of PF_FormField::setMappedValues
	 * @param array $values
	 * @param string|null $mappingType
	 * @param array $args
	 * @param bool $useDisplayTitle
	 * @return array
	 */
	public static function getMappedValues(
		array $values,
		?string $mappingType,
		array $args,
		bool $useDisplayTitle
	) {
		$mappedValues = null;
		switch ( $mappingType ) {
			case 'mapping property':
				$mappingProperty = $args['mapping property'];
				$mappedValues = self::getValuesWithMappingProperty( $values, $mappingProperty );
				break;
			case 'mapping template':
				$mappingTemplate = $args['mapping template'];
				$mappedValues = self::getValuesWithMappingTemplate( $values, $mappingTemplate );
				break;
			case 'mapping cargo field':
				$mappingCargoField = isset( $args['mapping cargo field'] ) ? $args['mapping cargo field'] : null;
				$mappingCargoValueField = isset( $args['mapping cargo value field'] ) ? $args['mapping cargo value field'] : null;
				$mappingCargoTable = $args['mapping cargo table'];
				$mappedValues = self::getValuesWithMappingCargoField( $values, $mappingCargoField, $mappingCargoValueField, $mappingCargoTable, $useDisplayTitle );
				break;
			case 'mapping using translate':
				$translateMapping = $args[ 'mapping using translate' ];
				$mappedValues = self::getValuesWithTranslateMapping( $values, $translateMapping );
				break;
			case 'displaytitle':
				$isReverseLookup = ( array_key_exists( 'reverselookup', $args ) && ( $args['reverselookup'] == 'true' ) );
				$mappedValues = self::getLabelsFromDisplayTitle( $values, $isReverseLookup );
				// @todo - why just array_values ?
				break;
		}
		$res = ( $mappedValues !== null ) ? self::disambiguateLabels( $mappedValues ) : $values;
		return $res;
	}

	/**
	 * Map a template field value into sequential array of labels.
	 * Used when mapping submitted to possible values.
	 * Works with both local and remote autocompletion.
	 *
	 * @param string|null $valueString
	 * @param string|null $delimiter
	 * @param array $args
	 * @param bool $form_submitted
	 * @return string[]
	 */
	public static function valueStringToLabels(
		?string $valueString,
		?string $delimiter,
		array $args = [],
		bool $form_submitted = false
	) {
		if ( $valueString == null ) {
			return [];
		} else {
			$valueString = trim( $valueString );
			$possibleValues = ( array_key_exists( 'possible_values', $args ) )
			? $args['possible_values']
			: null;
			if ( strlen( $valueString ) === 0 || $possibleValues === null ) {
				return [ $valueString ];
			}
		}
		if ( $delimiter !== null ) {
			$values = array_map( 'trim', explode( $delimiter, $valueString ) );
		} else {
			$values = [ $valueString ];
		}

		$labels = [];
		// Remote autocompletion? Don't try mapping
		// current to possible values
		$valMax = PFValuesUtils::getMaxValuesToRetrieve();
		$mode = ( $form_submitted && count( $possibleValues ) >= $valMax ) ? 'remote' : 'local';
		if ( $mode == 'local' ) {
			foreach ( $values as $value ) {
				if ( $value != '' ) {
					if ( array_key_exists( $value, $possibleValues ) ) {
						$labels[] = $possibleValues[$value];
					} else {
						$labels[] = $value;
					}
				}
			}
		} elseif ( $mode == 'remote' ) {
			$mappedValues = self::getMappedValuesForInput( $values, $args );
			$labels = array_values( $mappedValues );
		}
		return $labels;
		// Always return an array
	}

	/**
	 * Helper function to get a named array of labels from
	 * an indexed array of values given a mapping property.
	 * Originally in PF_FormField
	 * @param array $values
	 * @param string $propertyName
	 * @return array
	 */
	public static function getValuesWithMappingProperty(
		array $values,
		string $propertyName
	): array {
		$store = PFUtils::getSMWStore();
		if ( $store == null || empty( $values ) ) {
			return [];
		}
		$res = [];
		foreach ( $values as $index => $value ) {
			// @todo - does this make sense?
			// if ( $useDisplayTitle ) {
			// $value = $index;
			// }
			$subject = Title::newFromText( $value );
			if ( $subject != null ) {
				$vals = PFValuesUtils::getSMWPropertyValues( $store, $subject, $propertyName );
				if ( count( $vals ) > 0 ) {
					$res[$value] = trim( $vals[0] );
				} else {
					// @todo - make this optional
					$label = self::removeNSPrefixFromLabel( trim( $value ) );
					$res[$value] = $label;
				}
			} else {
				$res[$value] = $value;
			}
		}
		return $res;
	}

	/**
	 * Helper function to get an array of labels from an array of values
	 * given a mapping template.
	 * @todo remove $useDisplayTitle?
	 * @param array $values
	 * @param string $mappingTemplate
	 * @param bool $useDisplayTitle
	 * @return array
	 */
	public static function getValuesWithMappingTemplate(
		array $values,
		string $mappingTemplate,
		bool $useDisplayTitle = false
	): array {
		$title = Title::makeTitleSafe( NS_TEMPLATE, $mappingTemplate );
		$templateExists = $title->exists();
		$res = [];
		foreach ( $values as $index => $value ) {
			// if ( $useDisplayTitle ) {
			// $value = $index;
			// }
			if ( $templateExists ) {
				$label = trim( PFUtils::getParser()->recursiveTagParse( '{{' . $mappingTemplate .
					'|' . $value . '}}' ) );
				if ( $label == '' ) {
					$res[$value] = $value;
				} else {
					$res[$value] = $label;
				}
			} else {
				$res[$value] = $value;
			}
		}
		return $res;
	}

	/**
	 * Helper function to get an array of labels from an array of values
	 * given a mapping Cargo table/field.
	 * Derived from PFFormField::setValuesWithMappingCargoField
	 * @todo does $useDisplayTitle make sense here?
	 * @todo see if check for $mappingCargoValueField works
	 * @param array $values
	 * @param string|null $mappingCargoField
	 * @param string|null $mappingCargoValueField
	 * @param string|null $mappingCargoTable
	 * @param bool $useDisplayTitle
	 * @return array
	 */
	public static function getValuesWithMappingCargoField(
		$values,
		$mappingCargoField,
		$mappingCargoValueField,
		$mappingCargoTable,
		bool $useDisplayTitle = false
	) {
		$labels = [];
		foreach ( $values as $index => $value ) {
			if ( $useDisplayTitle ) {
				$value = $index;
			}
			$labels[$value] = $value;
			// Check if this works
			if ( $mappingCargoValueField !== null ) {
				$valueField = $mappingCargoValueField;
			} else {
				$valueField = '_pageName';
			}
			$vals = PFValuesUtils::getValuesForCargoField(
				$mappingCargoTable,
				$mappingCargoField,
				$valueField . '="' . $value . '"'
			);
			if ( count( $vals ) > 0 ) {
				$labels[$value] = html_entity_decode( trim( $vals[0] ) );
			}
		}
		return $labels;
	}

	/**
	 * Mapping with the Translate extension
	 * @param array $values
	 * @param string $translateMapping
	 * @return array
	 */
	public static function getValuesWithTranslateMapping(
		array $values,
		string $translateMapping
	) {
		$res = [];
		foreach ( $values as $key ) {
			$res[$key] = PFUtils::getParser()->recursiveTagParse( '{{int:' . $translateMapping . $key . '}}' );
		}
		return $res;
	}

	/**
	 * Get a named array of display titles
	 *
	 * @param array $values = pagenames
	 * @param bool $doReverseLookup
	 * @return array
	 */
	public static function getLabelsFromDisplayTitle(
		array $values,
		bool $doReverseLookup = false
	) {
		$labels = [];
		$pageNamesForValues = [];
		$allTitles = [];
		foreach ( $values as $value ) {
			if ( trim( $value ) === "" ) {
				continue;
			}
			if ( $doReverseLookup ) {
				// The regex matches every 'real' page inside the last brackets; for example
				//  'Privacy (doel) (Privacy (doel)concept)',
				//  'Pagina (doel) (Pagina)',
				// will match on (Privacy (doel)concept), (Pagina), ect
				if ( !preg_match_all( '/\((?:[^)(]*(?R)?)*+\)/', $value, $matches ) ) {
					$title = Title::newFromText( $value );
					// @todo : maybe $title instanceof Title && ...?
					if ( $title && $title->exists() ) {
						$labels[ $value ] = $value;
					}
					// If no matches where found, just leave the value as is
					continue;
				} else {
					$firstMatch = reset( $matches );
					// The actual match is always in the last group
					$realPage = end( $firstMatch );
					// The match still contains the first ( and last ) character, remove them
					$realPage = substr( $realPage, 1 );
					// Finally set the actual value
					$value = substr( $realPage, 0, -1 );
				}
			}
			$titleInstance = Title::newFromText( $value );
			// If the title is invalid, just leave the value as is
			if ( $titleInstance === null ) {
				continue;
			}
			$pageNamesForValues[$value] = $titleInstance->getPrefixedText();
			$allTitles[] = $titleInstance;
		}

		$allDisplayTitles = self::getDisplayTitles( $allTitles );
		foreach ( $pageNamesForValues as $value => $pageName ) {
			$labels[$value] = $allDisplayTitles[$pageName] ?? $value;
		}
		return $labels;
	}

	/**
	 * Returns pages each with their display title as the value.
	 * @param array $titlesUnfiltered
	 * @return array
	 */
	public static function getDisplayTitles(
		array $titlesUnfiltered
	) {
		$pages = $titles = [];
		foreach ( $titlesUnfiltered as $k => $title ) {
			if ( $title instanceof Title ) {
				$titles[ $k ] = $title;
			}
		}
		$properties = MediaWikiServices::getInstance()->getPageProps()
			->getProperties( $titles, [ 'displaytitle', 'defaultsort' ] );
		foreach ( $titles as $title ) {
			if ( array_key_exists( $title->getArticleID(), $properties ) ) {
				$titleprops = $properties[$title->getArticleID()];
			} else {
				$titleprops = [];
			}
			$titleText = $title->getPrefixedText();
			if ( array_key_exists( 'displaytitle', $titleprops ) &&
				trim( str_replace( '&#160;', '', strip_tags( $titleprops['displaytitle'] ) ) ) !== '' ) {
				$pages[$titleText] = htmlspecialchars_decode( $titleprops['displaytitle'] );
			} else {
				$pages[$titleText] = $titleText;
			}
		}
		return $pages;
	}

	/**
	 * Remove namespace prefix (if any) from label
	 * @param string $label
	 * @return string
	 */
	private static function removeNSPrefixFromLabel( string $label ) {
		$labelArr = explode( ':',  trim( $label ) );
		if ( count( $labelArr ) > 1 ) {
			$prefix = array_shift( $labelArr );
			$res = implode( ':', $labelArr );
		} else {
			$res = $label;
		}
		return $res;
	}

	/**
	 * Doing "mapping" on values can potentially lead to more than one
	 * value having the same "label". To avoid this, we find duplicate
	 * labels, if there are any, add on the real value, in parentheses,
	 * to all of them.
	 *
	 * @param array $labels
	 * @return array
	 */
	public static function disambiguateLabels( array $labels ) {
		if ( count( $labels ) == count( array_unique( $labels ) ) ) {
			return $labels;
		}
		$fixed_labels = [];
		foreach ( $labels as $value => $label ) {
			$fixed_labels[$value] = $labels[$value];
		}
		$counts = array_count_values( $fixed_labels );
		foreach ( $counts as $current_label => $count ) {
			if ( $count > 1 ) {
				$matching_keys = array_keys( $labels, $current_label );
				foreach ( $matching_keys as $key ) {
					$fixed_labels[$key] .= ' (' . $key . ')';
				}
			}
		}
		if ( count( $fixed_labels ) == count( array_unique( $fixed_labels ) ) ) {
			return $fixed_labels;
		}
		// If that didn't work, just add on " (value)" to *all* the
		// labels. @TODO - is this necessary?
		foreach ( $labels as $value => $label ) {
			$labels[$value] .= ' (' . $value . ')';
		}
		return $labels;
	}
}
