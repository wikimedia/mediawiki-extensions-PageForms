<?php
/**
 * Static functions for handling lists of values and labels.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;

class PFValuesUtils {

	/**
	 * Helper function to handle getPropertyValues().
	 *
	 * @param Store $store
	 * @param Title $subject
	 * @param string $propID
	 * @param SMWRequestOptions|null $requestOptions
	 * @return array
	 * @suppress PhanUndeclaredTypeParameter For Store
	 */
	public static function getSMWPropertyValues( $store, $subject, $propID, $requestOptions = null ) {
		// If SMW is not installed, exit out.
		if ( !class_exists( 'SMWDIWikiPage' ) ) {
			return [];
		}
		if ( $subject === null ) {
			$page = null;
		} else {
			$page = SMWDIWikiPage::newFromTitle( $subject );
		}
		$property = SMWDIProperty::newFromUserLabel( $propID );
		$res = $store->getPropertyValues( $page, $property, $requestOptions );
		$values = [];
		foreach ( $res as $value ) {
			if ( $value instanceof SMWDIUri ) {
				$values[] = $value->getURI();
			} elseif ( $value instanceof SMWDIWikiPage ) {
				$realValue = str_replace( '_', ' ', $value->getDBKey() );
				if ( $value->getNamespace() != 0 ) {
					$realValue = PFUtils::getCanonicalName( $value->getNamespace() ) . ":$realValue";
				}
				$values[] = $realValue;
			} else {
				// getSortKey() seems to return the correct
				// value for all the other data types.
				$values[] = str_replace( '_', ' ', $value->getSortKey() );
			}
		}
		return $values;
	}

	/**
	 * Helper function - gets names of categories for a page;
	 * based on Title::getParentCategories(), but simpler.
	 *
	 * @param Title $title
	 * @return array
	 */
	public static function getCategoriesForPage( $title ) {
		$categories = [];
		$db = PFUtils::getReadDB();
		$titlekey = $title->getArticleID();
		if ( $titlekey == 0 ) {
			// Something's wrong - exit
			return $categories;
		}
		$conditions = [ 'cl_from' => $titlekey ];
		$res = $db->select(
			'categorylinks',
			'DISTINCT cl_to',
			$conditions,
			__METHOD__
		);
		while ( $row = $res->fetchRow() ) {
			$categories[] = $row['cl_to'];
		}
		$res->free();
		return $categories;
	}

	/**
	 * Helper function - returns names of all the categories.
	 * @return array
	 */
	public static function getAllCategories() {
		$categories = [];
		$db = PFUtils::getReadDB();
		$res = $db->select(
			'category',
			'cat_title',
			 null,
			__METHOD__
		);
		while ( $row = $res->fetchRow() ) {
			$categories[] = $row['cat_title'];
		}
		$res->free();
		return $categories;
	}

	/**
	 * This function, unlike the others, doesn't take in a substring
	 * because it uses the SMW data store, which can't perform
	 * case-insensitive queries; for queries with a substring, the
	 * function PFAutocompleteAPI::getAllValuesForProperty() exists.
	 *
	 * @param string $property_name
	 * @return array
	 */
	public static function getAllValuesForProperty( $property_name ) {
		$store = PFUtils::getSMWStore();
		if ( $store == null ) {
			return [];
		}
		$requestoptions = new SMWRequestOptions();
		$requestoptions->limit = self::getMaxValuesToRetrieve();
		$values = self::getSMWPropertyValues( $store, null, $property_name, $requestoptions );
		sort( $values );
		return $values;
	}

	/**
	 * This function is used for fetching the values from wikidata based on the provided
	 * annotations. For queries with substring, the function returns all the values which
	 * have the substring in it.
	 *
	 * @param string $query
	 * @param string|null $substring
	 * @return array
	 */
	public static function getAllValuesFromWikidata( $query, $substring = null ) {
		$endpointUrl = "https://query.wikidata.org/sparql";
		global $wgLanguageCode;

		$query = urldecode( $query );

		$filter_strings = explode( '&', $query );
		$filters = [];

		foreach ( $filter_strings as $filter ) {
			$temp = explode( "=", $filter );
			$filters[ $temp[ 0 ] ] = $temp[ 1 ];
		}

		$attributesQuery = "";
		$count = 0;
		foreach ( $filters as $key => $val ) {
			$attributesQuery .= "wdt:" . $key;
			if ( is_numeric( str_replace( "Q", "", $val ) ) ) {
				$attributesQuery .= " wd:" . $val . ";";
			} else {
				$attributesQuery .= "?customLabel" . $count . " .
				?customLabel" . $count . " rdfs:label \"" . $val . "\"@" . $wgLanguageCode . " . ";
				$count++;
				$attributesQuery .= "?value ";
			}
		}
		unset( $count );
		$attributesQuery = rtrim( $attributesQuery, ";" );
		$attributesQuery = rtrim( $attributesQuery, ". ?value " );

		$sparqlQueryString = "
SELECT DISTINCT ?valueLabel WHERE {
{
SELECT ?value  WHERE {
?value " . $attributesQuery . " .
?value rdfs:label ?valueLabel .
FILTER(LANG(?valueLabel) = \"" . $wgLanguageCode . "\") .
FILTER(REGEX(LCASE(?valueLabel), \"\\\\b" . strtolower( $substring ) . "\"))
} ";
		$maxValues = self::getMaxValuesToRetrieve( $substring );
		$sparqlQueryString .= "LIMIT " . ( $maxValues + 10 );
		$sparqlQueryString .= "}
SERVICE wikibase:label { bd:serviceParam wikibase:language \"" . $wgLanguageCode . "\". }
}";
		$sparqlQueryString .= "LIMIT " . $maxValues;
		$opts = [
			'http' => [
				'method' => 'GET',
				'header' => [
					'Accept: application/sparql-results+json',
					'User-Agent: PageForms_API PHP/8.0'
				],
			],
		];
		$context = stream_context_create( $opts );

		$url = $endpointUrl . '?query=' . urlencode( $sparqlQueryString );
		$response = file_get_contents( $url, false, $context );
		$apiResults = json_decode( $response, true );
		$results = [];
		if ( $apiResults != null ) {
			$apiResults = $apiResults[ 'results' ][ 'bindings' ];
			foreach ( $apiResults as $result ) {
				foreach ( $result as $key => $val ) {
					array_push( $results, $val[ 'value' ] );
				}
			}
		}
		return $results;
	}

	/**
	 * Used with the Cargo extension.
	 * @param string $tableName
	 * @param string $fieldName
	 * @return array
	 */
	public static function getAllValuesForCargoField( $tableName, $fieldName ) {
		return self::getValuesForCargoField( $tableName, $fieldName );
	}

	/**
	 * Used with the Cargo extension.
	 * @param string $tableName
	 * @param string $fieldName
	 * @param string|null $whereStr
	 * @return array
	 */
	public static function getValuesForCargoField( $tableName, $fieldName, $whereStr = null ) {
		global $wgPageFormsMaxLocalAutocompleteValues;

		// The limit should be greater than the maximum number of local
		// autocomplete values, so that form inputs also know whether
		// to switch to remote autocompletion.
		// (We increment by 10, to be on the safe side, since some values
		// can be null, etc.)
		$limitStr = max( 100, $wgPageFormsMaxLocalAutocompleteValues + 10 );

		try {
			$sqlQuery = CargoSQLQuery::newFromValues( $tableName, $fieldName, $whereStr, $joinOnStr = null, $fieldName, $havingStr = null, $fieldName, $limitStr, $offsetStr = 0 );
		} catch ( Exception $e ) {
			return [];
		}

		$queryResults = $sqlQuery->run();
		$values = [];
		// Field names starting with a '_' are special fields -
		// all other fields will have had their underscores
		// replaced with spaces in $queryResults.
		if ( $fieldName[0] == '_' ) {
			$fieldAlias = $fieldName;
		} else {
			$fieldAlias = str_replace( '_', ' ', $fieldName );
		}
		foreach ( $queryResults as $row ) {
			if ( !isset( $row[$fieldAlias] ) ) {
				continue;
			}
			// Cargo HTML-encodes everything - decode the quotes and
			// angular brackets.
			$values[] = html_entity_decode( $row[$fieldAlias] );
		}
		return $values;
	}

	/**
	 * Get all the pages that belong to a category and all its
	 * subcategories, down a certain number of levels - heavily based on
	 * SMW's SMWInlineQuery::includeSubcategories().
	 *
	 * @param string $top_category
	 * @param int $num_levels
	 * @param string|null $substring
	 * @return string[]
	 */
	public static function getAllPagesForCategory( $top_category, $num_levels, $substring = null ) {
		if ( $num_levels == 0 ) {
			return [ $top_category ];
		}
		global $wgPageFormsUseDisplayTitle;

		$db = PFUtils::getReadDB();
		$top_category = str_replace( ' ', '_', $top_category );
		$categories = [ $top_category ];
		$checkcategories = [ $top_category ];
		$pages = [];
		$sortkeys = [];
		for ( $level = $num_levels; $level > 0; $level-- ) {
			$newcategories = [];
			foreach ( $checkcategories as $category ) {
				$tables = [ 'categorylinks', 'page' ];
				$columns = [ 'page_title', 'page_namespace' ];
				$conditions = [];
				$conditions[] = 'cl_from = page_id';
				$conditions['cl_to'] = $category;
				if ( $wgPageFormsUseDisplayTitle ) {
					$tables['pp_displaytitle'] = 'page_props';
					$tables['pp_defaultsort'] = 'page_props';
					$columns['pp_displaytitle_value'] = 'pp_displaytitle.pp_value';
					$columns['pp_defaultsort_value'] = 'pp_defaultsort.pp_value';
					$join = [
						'pp_displaytitle' => [
							'LEFT JOIN', [
								'pp_displaytitle.pp_page = page_id',
								'pp_displaytitle.pp_propname = \'displaytitle\''
							]
						],
						'pp_defaultsort' => [
							'LEFT JOIN', [
								'pp_defaultsort.pp_page = page_id',
								'pp_defaultsort.pp_propname = \'defaultsort\''
							]
						]
					];
					if ( $substring != null ) {
						$conditions[] = '((pp_displaytitle.pp_value IS NULL OR pp_displaytitle.pp_value = \'\') AND (' .
							self::getSQLConditionForAutocompleteInColumn( 'page_title', $substring ) .
							')) OR ' .
							self::getSQLConditionForAutocompleteInColumn( 'pp_displaytitle.pp_value', $substring ) .
							' OR page_namespace = ' . NS_CATEGORY;
					}
				} else {
					$join = [];
					if ( $substring != null ) {
						$conditions[] = self::getSQLConditionForAutocompleteInColumn( 'page_title', $substring ) . ' OR page_namespace = ' . NS_CATEGORY;
					}
				}
				// Make the query.
				$res = $db->select(
					$tables,
					$columns,
					$conditions,
					__METHOD__,
					$options = [
						'ORDER BY' => 'cl_type, cl_sortkey',
						'LIMIT' => self::getMaxValuesToRetrieve( $substring )
					],
					$join );
				if ( $res ) {
					while ( $res && $row = $res->fetchRow() ) {
						if ( !array_key_exists( 'page_title', $row ) ) {
							continue;
						}
						$page_namespace = $row['page_namespace'];
						$page_name = $row[ 'page_title' ];
						if ( $page_namespace == NS_CATEGORY ) {
							if ( !in_array( $page_name, $categories ) ) {
								$newcategories[] = $page_name;
							}
						} else {
							$cur_title = Title::makeTitleSafe( $page_namespace, $page_name );
							if ( $cur_title === null ) {
								// This can happen if it's
								// a "phantom" page, in a
								// namespace that no longer exists.
								continue;
							}
							$cur_value = $cur_title->getPrefixedText();
							if ( !in_array( $cur_value, $pages ) ) {
								if ( array_key_exists( 'pp_displaytitle_value', $row ) &&
									( $row[ 'pp_displaytitle_value' ] ) !== null &&
									trim( str_replace( '&#160;', '', strip_tags( $row[ 'pp_displaytitle_value' ] ) ) ) !== '' ) {
									$pages[ $cur_value . '@' ] = htmlspecialchars_decode( $row[ 'pp_displaytitle_value'] );
								} else {
									$pages[ $cur_value . '@' ] = $cur_value;
								}
								if ( array_key_exists( 'pp_defaultsort_value', $row ) &&
									( $row[ 'pp_defaultsort_value' ] ) !== null ) {
									$sortkeys[ $cur_value ] = $row[ 'pp_defaultsort_value'];
								} else {
									$sortkeys[ $cur_value ] = $cur_value;
								}
							}
						}
					}
					$res->free();
				}
			}
			if ( count( $newcategories ) == 0 ) {
				return self::fixedMultiSort( $sortkeys, $pages );
			} else {
				$categories = array_merge( $categories, $newcategories );
			}
			$checkcategories = array_diff( $newcategories, [] );
		}
		return self::fixedMultiSort( $sortkeys, $pages );
	}

	/**
	 * array_multisort() unfortunately messes up array keys that are
	 * numeric - they get converted to 0, 1, etc. There are a few ways to
	 * get around this, but I (Yaron) couldn't get those working, so
	 * instead we're going with this hack, where all key values get
	 * appended with a '@' before sorting, which is then removed after
	 * sorting. It's inefficient, but it's probably good enough.
	 *
	 * @param string[] $sortkeys
	 * @param string[] $pages
	 * @return string[] a sorted version of $pages, sorted via $sortkeys
	 */
	static function fixedMultiSort( $sortkeys, $pages ) {
		array_multisort( $sortkeys, $pages );
		$newPages = [];
		foreach ( $pages as $key => $value ) {
			$fixedKey = rtrim( $key, '@' );
			$newPages[$fixedKey] = $value;
		}
		return $newPages;
	}

	/**
	 * @param string $conceptName
	 * @param string|null $substring
	 * @return string[]
	 */
	public static function getAllPagesForConcept( $conceptName, $substring = null ) {
		global $wgPageFormsAutocompleteOnAllChars;

		$store = PFUtils::getSMWStore();
		if ( $store == null ) {
			return [];
		}

		$conceptTitle = Title::makeTitleSafe( SMW_NS_CONCEPT, $conceptName );

		if ( $substring !== null ) {
			$substring = strtolower( $substring );
		}

		// Escape if there's no such concept.
		if ( $conceptTitle == null || !$conceptTitle->exists() ) {
			throw new MWException( wfMessage( 'pf-missingconcept', wfEscapeWikiText( $conceptName ) ) );
		}

		global $wgPageFormsUseDisplayTitle;
		$conceptDI = SMWDIWikiPage::newFromTitle( $conceptTitle );
		$desc = new SMWConceptDescription( $conceptDI );
		$printout = new SMWPrintRequest( SMWPrintRequest::PRINT_THIS, "" );
		$desc->addPrintRequest( $printout );
		$query = new SMWQuery( $desc );
		$query->setLimit( self::getMaxValuesToRetrieve( $substring ) );
		$query_result = $store->getQueryResult( $query );
		$pages = [];
		$sortkeys = [];
		$titles = [];
		while ( $res = $query_result->getNext() ) {
			$page = $res[0]->getNextText( SMW_OUTPUT_WIKI );
			if ( $wgPageFormsUseDisplayTitle ) {
				$title = Title::newFromText( $page );
				if ( $title !== null ) {
					$titles[] = $title;
				}
			} else {
				$pages[$page] = $page;
				$sortkeys[$page] = $page;
			}
		}

		if ( $wgPageFormsUseDisplayTitle ) {
			$properties = MediaWikiServices::getInstance()->getPageProps()->getProperties(
				$titles, [ 'displaytitle', 'defaultsort' ]
			);
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
				if ( array_key_exists( 'defaultsort', $titleprops ) ) {
					$sortkeys[$titleText] = $titleprops['defaultsort'];
				} else {
					$sortkeys[$titleText] = $titleText;
				}
			}
		}

		if ( $substring !== null ) {
			$filtered_pages = [];
			$filtered_sortkeys = [];
			foreach ( $pages as $index => $pageName ) {
				// Filter on the substring manually. It would
				// be better to do this filtering in the
				// original SMW query, but that doesn't seem
				// possible yet.
				// @TODO - this will miss a lot of results for
				// concepts with > 1000 pages. Instead, this
				// code should loop through all the pages,
				// using "offset".
				$lowercasePageName = strtolower( $pageName );
				$position = strpos( $lowercasePageName, $substring );
				if ( $position !== false ) {
					if ( $wgPageFormsAutocompleteOnAllChars ) {
						if ( $position >= 0 ) {
							$filtered_pages[$index] = $pageName;
							$filtered_sortkeys[$index] = $sortkeys[$index];
						}
					} else {
						if ( $position === 0 ||
							strpos( $lowercasePageName, ' ' . $substring ) > 0 ) {
							$filtered_pages[$index] = $pageName;
							$filtered_sortkeys[$index] = $sortkeys[$index];
						}
					}
				}
			}
			$pages = $filtered_pages;
			$sortkeys = $filtered_sortkeys;
		}
		array_multisort( $sortkeys, $pages );
		return $pages;
	}

	public static function getAllPagesForNamespace( $namespaceStr, $substring = null ) {
		global $wgLanguageCode, $wgPageFormsUseDisplayTitle;

		$namespaceNames = explode( ',', $namespaceStr );

		$allNamespaces = PFUtils::getContLang()->getNamespaces();

		if ( $wgLanguageCode !== 'en' ) {
			$englishLang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
			$allEnglishNamespaces = $englishLang->getNamespaces();
		}

		$queriedNamespaces = [];
		$namespaceConditions = [];

		foreach ( $namespaceNames as $namespace_name ) {
			$namespace_name = self::standardizeNamespace( $namespace_name );
			// Cycle through all the namespace names for this language, and
			// if one matches the namespace specified in the form, get the
			// names of all the pages in that namespace.

			// Switch to blank for the string 'Main'.
			if ( $namespace_name == 'Main' || $namespace_name == 'main' ) {
				$namespace_name = '';
			}
			$matchingNamespaceCode = null;
			foreach ( $allNamespaces as $curNSCode => $curNSName ) {
				if ( $curNSName == $namespace_name ) {
					$matchingNamespaceCode = $curNSCode;
				}
			}

			// If that didn't find anything, and we're in a language
			// other than English, check English as well.
			if ( $matchingNamespaceCode === null && $wgLanguageCode != 'en' ) {
				foreach ( $allEnglishNamespaces as $curNSCode => $curNSName ) {
					if ( $curNSName == $namespace_name ) {
						$matchingNamespaceCode = $curNSCode;
					}
				}
			}

			if ( $matchingNamespaceCode === null ) {
				throw new MWException( wfMessage( 'pf-missingnamespace', wfEscapeWikiText( $namespace_name ) ) );
			}

			$queriedNamespaces[] = $matchingNamespaceCode;
			$namespaceConditions[] = "page_namespace = $matchingNamespaceCode";
		}

		$db = PFUtils::getReadDB();
		$conditions = [];
		$conditions[] = implode( ' OR ', $namespaceConditions );
		$tables = [ 'page' ];
		$columns = [ 'page_title' ];
		if ( count( $namespaceNames ) > 1 ) {
			$columns[] = 'page_namespace';
		}
		if ( $wgPageFormsUseDisplayTitle ) {
			$tables['pp_displaytitle'] = 'page_props';
			$tables['pp_defaultsort'] = 'page_props';
			$columns['pp_displaytitle_value'] = 'pp_displaytitle.pp_value';
			$columns['pp_defaultsort_value'] = 'pp_defaultsort.pp_value';
			$join = [
				'pp_displaytitle' => [
					'LEFT JOIN', [
						'pp_displaytitle.pp_page = page_id',
						'pp_displaytitle.pp_propname = \'displaytitle\''
					]
				],
				'pp_defaultsort' => [
					'LEFT JOIN', [
						'pp_defaultsort.pp_page = page_id',
						'pp_defaultsort.pp_propname = \'defaultsort\''
					]
				]
			];
			if ( $substring != null ) {
				$substringCondition = '(pp_displaytitle.pp_value IS NULL AND (' .
					self::getSQLConditionForAutocompleteInColumn( 'page_title', $substring ) .
					')) OR ' .
					self::getSQLConditionForAutocompleteInColumn( 'pp_displaytitle.pp_value', $substring, false );
				if ( !in_array( NS_CATEGORY, $queriedNamespaces ) ) {
					$substringCondition .= ' OR page_namespace = ' . NS_CATEGORY;
				}
				$conditions[] = $substringCondition;
			}
		} else {
			$join = [];
			if ( $substring != null ) {
				$conditions[] = self::getSQLConditionForAutocompleteInColumn( 'page_title', $substring );
			}
		}
		$options = [
			'LIMIT' => self::getMaxValuesToRetrieve( $substring )
		];
		$res = $db->select( $tables, $columns, $conditions, __METHOD__, $options, $join );

		$pages = [];
		$sortkeys = [];
		while ( $row = $res->fetchRow() ) {
			// If there's more than one namespace, include the
			// namespace prefix in the results - otherwise, don't.
			if ( array_key_exists( 'page_namespace', $row ) ) {
				$actualTitle = Title::newFromText( $row['page_title'], $row['page_namespace'] );
				$title = $actualTitle->getPrefixedText();
			} else {
				$title = str_replace( '_', ' ', $row['page_title'] );
			}
			if ( array_key_exists( 'pp_displaytitle_value', $row ) &&
				( $row[ 'pp_displaytitle_value' ] ) !== null &&
				trim( str_replace( '&#160;', '', strip_tags( $row[ 'pp_displaytitle_value' ] ) ) ) !== '' ) {
				$pages[ $title ] = htmlspecialchars_decode( $row[ 'pp_displaytitle_value'], ENT_QUOTES );
			} else {
				$pages[ $title ] = $title;
			}
			if ( array_key_exists( 'pp_defaultsort_value', $row ) &&
				( $row[ 'pp_defaultsort_value' ] ) !== null ) {
				$sortkeys[ $title ] = $row[ 'pp_defaultsort_value'];
			} else {
				$sortkeys[ $title ] = $title;
			}
		}
		$res->free();

		array_multisort( $sortkeys, $pages );
		return $pages;
	}

	/**
	 * Creates an array of values that match the specified source name and
	 * type, for use by both Javascript autocompletion and comboboxes.
	 *
	 * @param string|null $source_name
	 * @param string $source_type
	 * @return string[]
	 */
	public static function getAutocompleteValues( $source_name, $source_type ) {
		if ( $source_name === null ) {
			return [];
		}

		// The query depends on whether this is a Cargo field, SMW
		// property, category, SMW concept or namespace.
		if ( $source_type == 'cargo field' ) {
			$arr = explode( '|', $source_name );
			if ( count( $arr ) == 3 ) {
				$names_array = self::getValuesForCargoField( $arr[0], $arr[1], $arr[2] );
			} else {
				list( $table_name, $field_name ) = explode( '|', $source_name, 2 );
				$names_array = self::getAllValuesForCargoField( $table_name, $field_name );
			}
			// Remove blank/null values from the array.
			$names_array = array_values( array_filter( $names_array ) );
		} elseif ( $source_type == 'property' ) {
			$names_array = self::getAllValuesForProperty( $source_name );
		} elseif ( $source_type == 'category' ) {
			$names_array = self::getAllPagesForCategory( $source_name, 10 );
		} elseif ( $source_type == 'concept' ) {
			$names_array = self::getAllPagesForConcept( $source_name );
		} elseif ( $source_type == 'query' ) {
			// Get rid of the "@", which is a placeholder for the substring,
			// since there is no substring here.
			// May not cover all possible use cases.
			$baseQuery = str_replace(
				[ "~*@*", "~@*", "~*@", "~@", "like:*@*", "like:@*", "like:*@", "like:@", "&lt;@", "&gt;@", "@" ],
				"+",
				$source_name
			);
			$smwQuery = self::processSemanticQuery( $baseQuery );
			$names_array = self::getAllPagesForQuery( $smwQuery );
		} elseif ( $source_type == 'wikidata' ) {
			$names_array = self::getAllValuesFromWikidata( $source_name );
			sort( $names_array );
		} else {
			// i.e., $source_type == 'namespace'
			$names_array = self::getAllPagesForNamespace( $source_name );
		}
		return $names_array;
	}

	public static function getAutocompletionTypeAndSource( &$field_args ) {
		global $wgCapitalLinks;

		if ( array_key_exists( 'values from property', $field_args ) ) {
			$autocompletionSource = $field_args['values from property'];
			$autocompleteFieldType = 'property';
		} elseif ( array_key_exists( 'values from category', $field_args ) ) {
			$autocompleteFieldType = 'category';
			$autocompletionSource = $field_args['values from category'];
		} elseif ( array_key_exists( 'values from concept', $field_args ) ) {
			$autocompleteFieldType = 'concept';
			$autocompletionSource = $field_args['values from concept'];
		} elseif ( array_key_exists( 'values from namespace', $field_args ) ) {
			$autocompleteFieldType = 'namespace';
			$autocompletionSource = $field_args['values from namespace'];
		} elseif ( array_key_exists( 'values from url', $field_args ) ) {
			$autocompleteFieldType = 'external_url';
			$autocompletionSource = $field_args['values from url'];
		} elseif ( array_key_exists( 'values from wikidata', $field_args ) ) {
			$autocompleteFieldType = 'wikidata';
			$autocompletionSource = $field_args['values from wikidata'];
		} elseif ( array_key_exists( 'values from query', $field_args ) ) {
			$autocompletionSource = $field_args['values from query'];
			$autocompleteFieldType = 'semantic_query';
		} elseif ( array_key_exists( 'values', $field_args ) ) {
			global $wgPageFormsFieldNum;
			$autocompleteFieldType = 'values';
			$autocompletionSource = "values-$wgPageFormsFieldNum";
		} elseif ( array_key_exists( 'autocomplete field type', $field_args ) ) {
			$autocompleteFieldType = $field_args['autocomplete field type'];
			$autocompletionSource = $field_args['autocompletion source'];
		} elseif ( array_key_exists( 'full_cargo_field', $field_args ) ) {
			$autocompletionSource = $field_args['full_cargo_field'];
			$autocompleteFieldType = 'cargo field';
		} elseif ( array_key_exists( 'cargo field', $field_args ) ) {
			$fieldName = $field_args['cargo field'];
			$tableName = $field_args['cargo table'];
			$autocompletionSource = "$tableName|$fieldName";
			$autocompleteFieldType = 'cargo field';
			if ( array_key_exists( 'cargo where', $field_args ) ) {
				$whereStr = $field_args['cargo where'];
				$autocompletionSource .= "|$whereStr";
			}
		} elseif ( array_key_exists( 'semantic_property', $field_args ) ) {
			$autocompletionSource = $field_args['semantic_property'];
			$autocompleteFieldType = 'property';
		} else {
			$autocompleteFieldType = null;
			$autocompletionSource = null;
		}

		if ( $wgCapitalLinks && $autocompleteFieldType != 'external_url' && $autocompleteFieldType != 'cargo field' && $autocompleteFieldType != 'semantic_query' ) {
			$autocompletionSource = PFUtils::getContLang()->ucfirst( $autocompletionSource );
		}

		return [ $autocompleteFieldType, $autocompletionSource ];
	}

	public static function getRemoteDataTypeAndPossiblySetAutocompleteValues( $autocompleteFieldType, $autocompletionSource, $field_args, $autocompleteSettings ) {
		global $wgPageFormsMaxLocalAutocompleteValues, $wgPageFormsAutocompleteValues;

		if ( $autocompleteFieldType == 'external_url' || $autocompleteFieldType == 'wikidata' ) {
			// Autocompletion from URL is always done remotely.
			return $autocompleteFieldType;
		}
		if ( $autocompletionSource == '' ) {
			// No autocompletion.
			return null;
		}
		// @TODO - that empty() check shouldn't be necessary.
		if ( array_key_exists( 'possible_values', $field_args ) &&
		!empty( $field_args['possible_values'] ) ) {
			$autocompleteValues = $field_args['possible_values'];
		} elseif ( $autocompleteFieldType == 'values' ) {
			$autocompleteValues = explode( ',', $field_args['values'] );
		} else {
			$autocompleteValues = self::getAutocompleteValues( $autocompletionSource, $autocompleteFieldType );
		}

		if ( count( $autocompleteValues ) > $wgPageFormsMaxLocalAutocompleteValues &&
			$autocompleteFieldType != 'values' &&
			!array_key_exists( 'values dependent on', $field_args ) &&
			!array_key_exists( 'mapping template', $field_args ) &&
			!array_key_exists( 'mapping property', $field_args )
		) {
			return $autocompleteFieldType;
		} else {
			$wgPageFormsAutocompleteValues[$autocompleteSettings] = $autocompleteValues;
			return null;
		}
	}

	/**
	 * Get all autocomplete-related values, plus delimiter value
	 * (it's needed also for the 'uploadable' link, if there is one).
	 *
	 * @param array $field_args
	 * @param bool $is_list
	 * @return string[]
	 */
	public static function setAutocompleteValues( $field_args, $is_list ) {
		list( $autocompleteFieldType, $autocompletionSource ) =
			self::getAutocompletionTypeAndSource( $field_args );
		$autocompleteSettings = $autocompletionSource;
		if ( $is_list ) {
			$autocompleteSettings .= ',list';
			if ( array_key_exists( 'delimiter', $field_args ) ) {
				$delimiter = $field_args['delimiter'];
				$autocompleteSettings .= ',' . $delimiter;
			} else {
				$delimiter = ',';
			}
		} else {
			$delimiter = null;
		}

		$remoteDataType = self::getRemoteDataTypeAndPossiblySetAutocompleteValues( $autocompleteFieldType, $autocompletionSource, $field_args, $autocompleteSettings );
		return [ $autocompleteSettings, $remoteDataType, $delimiter ];
	}

	/**
	 * Helper function to get an array of values out of what may be either
	 * an array or a delimited string.
	 *
	 * @param string[]|string $value
	 * @param string $delimiter
	 * @return string[]
	 */
	public static function getValuesArray( $value, $delimiter ) {
		if ( is_array( $value ) ) {
			return $value;
		} elseif ( $value == null ) {
			return [];
		} else {
			// Remove extra spaces.
			return array_map( 'trim', explode( $delimiter, $value ) );
		}
	}

	public static function getValuesFromExternalURL( $external_url_alias, $substring ) {
		global $wgPageFormsAutocompletionURLs;
		if ( empty( $wgPageFormsAutocompletionURLs ) ) {
			return wfMessage( 'pf-nocompletionurls' );
		}
		if ( !array_key_exists( $external_url_alias, $wgPageFormsAutocompletionURLs ) ) {
			return wfMessage( 'pf-invalidexturl' );
		}
		$url = $wgPageFormsAutocompletionURLs[$external_url_alias];
		if ( empty( $url ) ) {
			return wfMessage( 'pf-blankexturl' );
		}
		$url = str_replace( '<substr>', urlencode( $substring ), $url );
		$page_contents = MediaWikiServices::getInstance()->getHttpRequestFactory()->get( $url );
		if ( empty( $page_contents ) ) {
			return wfMessage( 'pf-externalpageempty' );
		}
		$data = json_decode( $page_contents );
		if ( empty( $data ) ) {
			return wfMessage( 'pf-externalpagebadjson' );
		}
		$return_values = [];
		foreach ( $data->pfautocomplete as $val ) {
			$return_values[] = (array)$val;
		}
		return $return_values;
	}

	/**
	 * Returns a SQL condition for autocompletion substring value in a column.
	 *
	 * @param string $column Value column name
	 * @param string $substring Substring to look for
	 * @param bool $replaceSpaces
	 * @return string SQL condition for use in WHERE clause
	 */
	public static function getSQLConditionForAutocompleteInColumn( $column, $substring, $replaceSpaces = true ) {
		global $wgPageFormsAutocompleteOnAllChars;

		$db = PFUtils::getReadDB();

		// CONVERT() is also supported in PostgreSQL, but it doesn't
		// seem to work the same way.
		if ( $db->getType() == 'mysql' ) {
			$column_value = "LOWER(CONVERT($column USING utf8))";
		} else {
			$column_value = "LOWER($column)";
		}

		$substring = strtolower( $substring );
		if ( $replaceSpaces ) {
			$substring = str_replace( ' ', '_', $substring );
		}

		if ( $wgPageFormsAutocompleteOnAllChars ) {
			return $column_value . $db->buildLike( $db->anyString(), $substring, $db->anyString() );
		} else {
			$sqlCond = $column_value . $db->buildLike( $substring, $db->anyString() );
			$spaceRepresentation = $replaceSpaces ? '_' : ' ';
			$wordSeparators = [ $spaceRepresentation, '/', '(', ')', '-', '\'', '\"' ];
			foreach ( $wordSeparators as $wordSeparator ) {
				$sqlCond .= ' OR ' . $column_value .
					$db->buildLike( $db->anyString(), $wordSeparator . $substring, $db->anyString() );
			}
			return $sqlCond;
		}
	}

	/**
	 * Returns an array of the names of pages that are the result of an SMW query.
	 *
	 * @param string $rawQuery the query string like [[Category:Trees]][[age::>1000]]
	 * @return array
	 */
	public static function getAllPagesForQuery( $rawQuery ) {
		global $wgPageFormsMaxAutocompleteValues;
		global $wgPageFormsUseDisplayTitle;

		$rawQuery = $rawQuery . "|named args=yes|link=none|limit=$wgPageFormsMaxAutocompleteValues|searchlabel=";
		$rawQueryArray = explode( "|", $rawQuery );
		list( $queryString, $processedParams, $printouts ) = SMWQueryProcessor::getComponentsFromFunctionParams( $rawQueryArray, false );
		SMWQueryProcessor::addThisPrintout( $printouts, $processedParams );
		$processedParams = SMWQueryProcessor::getProcessedParams( $processedParams, $printouts );

		// Run query and get results.
		$queryObj = SMWQueryProcessor::createQuery( $queryString,
			$processedParams,
			SMWQueryProcessor::SPECIAL_PAGE, '', $printouts );
		$res = PFUtils::getSMWStore()->getQueryResult( $queryObj );
		$rows = $res->getResults();
		$titles = [];
		$pages = [];

		foreach ( $rows as $diWikiPage ) {
			$pages[] = $diWikiPage->getDbKey();
			$titles[] = $diWikiPage->getTitle();
		}

		if ( $wgPageFormsUseDisplayTitle ) {
			$pages = PFMappingUtils::getDisplayTitles( $titles );
		}

		return $pages;
	}

	public static function processSemanticQuery( $query, $substr = '' ) {
		$query = str_replace(
			[ "&lt;", "&gt;", "(", ")", '%', '@' ],
			[ "<", ">", "[", "]", '|', $substr ],
			$query
		);
		return $query;
	}

	public static function getMaxValuesToRetrieve( $substring = null ) {
		// $wgPageFormsMaxAutocompleteValues is currently misnamed,
		// or mis-used - it's actually used for those cases where
		// autocomplete *isn't* used, i.e. to populate a radiobutton
		// input, where it makes sense to have a very large limit
		// (current value: 1,000). For actual autocompletion, though,
		// with a substring, a limit like 20 makes more sense. It
		// would be good use the variable for this purpose instead,
		// with a default like 20, and then create a new global
		// variable, like $wgPageFormsMaxNonAutocompleteValues, to
		// hold the much larger number.
		if ( $substring == null ) {
			global $wgPageFormsMaxAutocompleteValues;
			return $wgPageFormsMaxAutocompleteValues;
		} else {
			return 20;
		}
	}

	/**
	 * Get the exact canonical namespace string, given a user-created string
	 *
	 * @param string $namespaceStr
	 * @return string
	 */
	public static function standardizeNamespace( $namespaceStr ) {
		$dummyTitle = Title::newFromText( "$namespaceStr:ABC" );
		return $dummyTitle ? $dummyTitle->getNsText() : $namespaceStr;
	}

	/**
	 * Map a label back to a value.
	 * @param string $label
	 * @param array $values
	 * @return string
	 */
	public static function labelToValue( $label, $values ) {
		$value = array_search( $label, $values );
		if ( $value === false ) {
			return $label;
		} else {
			return $value;
		}
	}
}
