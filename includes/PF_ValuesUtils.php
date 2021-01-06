<?php
/**
 * Static functions for handling lists of values and labels.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

class PFValuesUtils {

	/**
	 * Helper function to handle getPropertyValues().
	 *
	 * @param Store $store
	 * @param Title $subject
	 * @param string $propID
	 * @param array|null $requestOptions
	 * @return array
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
					$realValue = MWNamespace::getCanonicalName( $value->getNamespace() ) . ":$realValue";
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
		$db = wfGetDB( DB_REPLICA );
		$titlekey = $title->getArticleID();
		if ( $titlekey == 0 ) {
			// Something's wrong - exit
			return $categories;
		}
		$conditions['cl_from'] = $titlekey;
		$res = $db->select(
			'categorylinks',
			'DISTINCT cl_to',
			$conditions,
			__METHOD__
		);
		if ( $db->numRows( $res ) > 0 ) {
			while ( $row = $db->fetchRow( $res ) ) {
				$categories[] = $row['cl_to'];
			}
		}
		$db->freeResult( $res );
		return $categories;
	}

	/**
	 * Helper function - returns names of all the categories.
	 * @return array
	 */
	public static function getAllCategories() {
		$categories = [];
		$db = wfGetDB( DB_REPLICA );
		$res = $db->select(
			'category',
			'cat_title',
			 null,
			__METHOD__
		);
		if ( $db->numRows( $res ) > 0 ) {
			while ( $row = $db->fetchRow( $res ) ) {
				$categories[] = $row['cat_title'];
			}
		}
		$db->freeResult( $res );
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
		global $wgPageFormsMaxAutocompleteValues;

		$store = PFUtils::getSMWStore();
		if ( $store == null ) {
			return [];
		}
		$requestoptions = new SMWRequestOptions();
		$requestoptions->limit = $wgPageFormsMaxAutocompleteValues;
		$values = self::getSMWPropertyValues( $store, null, $property_name, $requestoptions );
		sort( $values );
		return $values;
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
			// Cargo HTML-encodes everything - let's decode double
			// quotes, at least.
			$values[] = str_replace( '&quot;', '"', $row[$fieldAlias] );
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
	 * @return string
	 */
	public static function getAllPagesForCategory( $top_category, $num_levels, $substring = null ) {
		if ( $num_levels == 0 ) {
			return $top_category;
		}
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsUseDisplayTitle;

		$db = wfGetDB( DB_REPLICA );
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
						$conditions[] = '(pp_displaytitle.pp_value IS NULL AND (' .
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
				$res = $db->select( // make the query
					$tables,
					$columns,
					$conditions,
					__METHOD__,
					$options = [
						'ORDER BY' => 'cl_type, cl_sortkey',
						'LIMIT' => $wgPageFormsMaxAutocompleteValues
					],
					$join );
				if ( $res ) {
					while ( $res && $row = $db->fetchRow( $res ) ) {
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
							$cur_value = PFUtils::titleString( $cur_title );
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
					$db->freeResult( $res );
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

	public static function getAllPagesForConcept( $conceptName, $substring = null ) {
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsAutocompleteOnAllChars;

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
		$query->setLimit( $wgPageFormsMaxAutocompleteValues );
		$query_result = $store->getQueryResult( $query );
		$pages = [];
		$sortkeys = [];
		$titles = [];
		while ( $res = $query_result->getNext() ) {
			$page = $res[0]->getNextText( SMW_OUTPUT_WIKI );
			if ( $wgPageFormsUseDisplayTitle && class_exists( 'PageProps' ) ) {
				$title = Title::newFromText( $page );
				if ( $title !== null ) {
					$titles[] = $title;
				}
			} else {
				$pages[$page] = $page;
				$sortkeys[$page] = $page;
			}
		}

		if ( $wgPageFormsUseDisplayTitle && class_exists( 'PageProps' ) ) {
			$properties = PageProps::getInstance()->getProperties( $titles,
				[ 'displaytitle', 'defaultsort' ] );
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

		if ( $wgLanguageCode != 'en' ) {
			$englishLang = Language::factory( 'en' );
			$allEnglishNamespaces = $englishLang->getNamespaces();
		}

		$queriedNamespaces = [];
		$namespaceConditions = [];

		foreach ( $namespaceNames as $namespace_name ) {

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

		$db = wfGetDB( DB_REPLICA );
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
		$res = $db->select( $tables, $columns, $conditions, __METHOD__, $options = [], $join );

		$pages = [];
		$sortkeys = [];
		while ( $row = $db->fetchRow( $res ) ) {
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
		$db->freeResult( $res );

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
			list( $table_name, $field_name ) = explode( '|', $source_name, 2 );
			$names_array = self::getAllValuesForCargoField( $table_name, $field_name );
			// Remove blank/null values from the array.
			$names_array = array_values( array_filter( $names_array ) );
		} elseif ( $source_type == 'property' ) {
			$names_array = self::getAllValuesForProperty( $source_name );
		} elseif ( $source_type == 'category' ) {
			$names_array = self::getAllPagesForCategory( $source_name, 10 );
		} elseif ( $source_type == 'concept' ) {
			$names_array = self::getAllPagesForConcept( $source_name );
		} elseif ( $source_type == 'query' ) {
			$names_array = self::getAllPagesForQuery( $source_name, 10 );
		} else { // i.e., $source_type == 'namespace'
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
		} elseif ( array_key_exists( 'semantic_property', $field_args ) ) {
			$autocompletionSource = $field_args['semantic_property'];
			$autocompleteFieldType = 'property';
		} else {
			$autocompleteFieldType = null;
			$autocompletionSource = null;
		}

		if ( $wgCapitalLinks && $autocompleteFieldType != 'external_url' && $autocompleteFieldType != 'cargo field' ) {
			$autocompletionSource = PFUtils::getContLang()->ucfirst( $autocompletionSource );
		}

		return [ $autocompleteFieldType, $autocompletionSource ];
	}

	public static function getRemoteDataTypeAndPossiblySetAutocompleteValues( $autocompleteFieldType, $autocompletionSource, $field_args, $autocompleteSettings ) {
		global $wgPageFormsMaxLocalAutocompleteValues, $wgPageFormsAutocompleteValues;

		if ( $autocompleteFieldType == 'external_url' ) {
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
			!array_key_exists( 'mapping property', $field_args ) &&
			!( array_key_exists( 'mapping cargo table', $field_args ) &&
			array_key_exists( 'mapping cargo field', $field_args ) )
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
		$page_contents = Http::get( $url );
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
		global $wgDBtype, $wgPageFormsAutocompleteOnAllChars;

		$db = wfGetDB( DB_REPLICA );

		// CONVERT() is also supported in PostgreSQL, but it doesn't
		// seem to work the same way.
		if ( $wgDBtype == 'mysql' ) {
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
			$spaceRepresentation = $replaceSpaces ? '_' : ' ';
			return $column_value . $db->buildLike( $substring, $db->anyString() ) .
				' OR ' . $column_value .
				$db->buildLike( $db->anyString(), $spaceRepresentation . $substring, $db->anyString() );
		}
	}

	/**
	 * Returns an array of the names of pages that are the result of an SMW query.
	 *
	 * @param string $rawQuery the query string like [[Category:Trees]][[age::>1000]]
	 * @return array
	 */
	public static function getAllPagesForQuery( $rawQuery ) {
		$rawQueryArray = [ $rawQuery ];
		SMWQueryProcessor::processFunctionParams( $rawQueryArray, $queryString, $processedParams, $printouts );
		SMWQueryProcessor::addThisPrintout( $printouts, $processedParams );
		$processedParams = SMWQueryProcessor::getProcessedParams( $processedParams, $printouts );
		$queryObj = SMWQueryProcessor::createQuery( $queryString,
			$processedParams,
			SMWQueryProcessor::SPECIAL_PAGE, '', $printouts );
		$res = PFUtils::getSMWStore()->getQueryResult( $queryObj );
		$rows = $res->getResults();
		$pages = [];
		foreach ( $rows as $row ) {
			$pages[] = $row->getDbKey();
		}

		return $pages;
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
	public static function disambiguateLabels( $labels ) {
		asort( $labels );
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
