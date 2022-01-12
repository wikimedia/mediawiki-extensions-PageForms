<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * Adds and handles the 'pfautocomplete' action to the MediaWiki API.
 *
 * @ingroup PF
 *
 * @author Sergey Chernyshev
 * @author Yaron Koren
 */
class PFAutocompleteAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$substr = $params['substr'];
		$namespace = $params['namespace'];
		$property = $params['property'];
		$category = $params['category'];
		$concept = $params['concept'];
		$cargo_table = $params['cargo_table'];
		$cargo_field = $params['cargo_field'];
		$external_url = $params['external_url'];
		$baseprop = $params['baseprop'];
		$base_cargo_table = $params['base_cargo_table'];
		$base_cargo_field = $params['base_cargo_field'];
		$basevalue = $params['basevalue'];
		// $limit = $params['limit'];

		if ( $baseprop === null && $base_cargo_table === null && strlen( $substr ) == 0 ) {
			$this->dieWithError( [ 'apierror-missingparam', 'substr' ], 'param_substr' );
		}

		global $wgPageFormsUseDisplayTitle;
		$map = false;
		if ( $baseprop !== null ) {
			if ( $property !== null ) {
				$data = $this->getAllValuesForProperty( $property, null, $baseprop, $basevalue );
			}
		} elseif ( $property !== null ) {
			$data = $this->getAllValuesForProperty( $property, $substr );
		} elseif ( $category !== null ) {
			$data = PFValuesUtils::getAllPagesForCategory( $category, 3, $substr );
			$map = $wgPageFormsUseDisplayTitle;
			if ( $map ) {
				$data = PFValuesUtils::disambiguateLabels( $data );
			}
		} elseif ( $concept !== null ) {
			$data = PFValuesUtils::getAllPagesForConcept( $concept, $substr );
			$map = $wgPageFormsUseDisplayTitle;
			if ( $map ) {
				$data = PFValuesUtils::disambiguateLabels( $data );
			}
		} elseif ( $cargo_table !== null && $cargo_field !== null ) {
			$data = self::getAllValuesForCargoField( $cargo_table, $cargo_field, $substr, $base_cargo_table, $base_cargo_field, $basevalue );
		} elseif ( $namespace !== null ) {
			$data = PFValuesUtils::getAllPagesForNamespace( $namespace, $substr );
			$map = $wgPageFormsUseDisplayTitle;
		} elseif ( $external_url !== null ) {
			$data = PFValuesUtils::getValuesFromExternalURL( $external_url, $substr );
		} else {
			$data = [];
		}

		// If we got back an error message, exit with that message.
		if ( !is_array( $data ) ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				if ( !$data instanceof Message ) {
					$data = ApiMessage::create( new RawMessage( '$1', [ $data ] ), 'unknownerror' );
				}
				$this->dieWithError( $data );
			} else {
				$code = 'unknownerror';
				if ( $data instanceof Message ) {
					$code = $data instanceof IApiMessage ? $data->getApiCode() : $data->getKey();
					$data = $data->inLanguage( 'en' )->useDatabase( false )->text();
				}
				$this->dieWithError( $data, $code );
			}
		}

		// to prevent JS parsing problems, display should be the same
		// even if there are no results
		/*
		if ( count( $data ) <= 0 ) {
			return;
		}
		*/

		// Format data as the API requires it - this is not needed
		// for "values from url", where the data is already formatted
		// correctly.
		if ( $external_url === null ) {
			$formattedData = [];
			foreach ( $data as $index => $value ) {
				if ( $map ) {
					$formattedData[] = [ 'title' => $index, 'displaytitle' => $value ];
				} else {
					$formattedData[] = [ 'title' => $value ];
				}
			}
		} else {
			$formattedData = $data;
		}

		// Set top-level elements.
		$result = $this->getResult();
		$result->setIndexedTagName( $formattedData, 'p' );
		$result->addValue( null, $this->getModuleName(), $formattedData );
	}

	protected function getAllowedParams() {
		return [
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'substr' => null,
			'property' => null,
			'category' => null,
			'concept' => null,
			'cargo_table' => null,
			'cargo_field' => null,
			'namespace' => null,
			'external_url' => null,
			'baseprop' => null,
			'base_cargo_table' => null,
			'base_cargo_field' => null,
			'basevalue' => null,
		];
	}

	protected function getParamDescription() {
		return [
			'substr' => 'Search substring',
			'property' => 'Semantic property for which to search values',
			'category' => 'Category for which to search values',
			'concept' => 'Concept for which to search values',
			'namespace' => 'Namespace for which to search values',
			'external_url' => 'Alias for external URL from which to get values',
			'baseprop' => 'A previous property in the form to check against',
			'basevalue' => 'The value to check for the previous property',
			// 'limit' => 'Limit how many entries to return',
		];
	}

	protected function getDescription() {
		return 'Autocompletion call used by the Page Forms extension (https://www.mediawiki.org/Extension:Page_Forms)';
	}

	protected function getExamples() {
		return [
			'api.php?action=pfautocomplete&substr=te',
			'api.php?action=pfautocomplete&substr=te&property=Has_author',
			'api.php?action=pfautocomplete&substr=te&category=Authors',
		];
	}

	private function getAllValuesForProperty(
		$property_name,
		$substring,
		$basePropertyName = null,
		$baseValue = null
	) {
		global $wgPageFormsCacheAutocompleteValues, $wgPageFormsAutocompleteCacheTimeout;
		global $smwgDefaultStore;

		if ( $smwgDefaultStore == null ) {
			$this->dieWithError( 'Semantic MediaWiki must be installed to query on "property"', 'param_property' );
		}

		$property_name = str_replace( ' ', '_', $property_name );

		// Use cache if allowed
		if ( !$wgPageFormsCacheAutocompleteValues ) {
			return $this->computeAllValuesForProperty( $property_name, $substring, $basePropertyName, $baseValue );
		}

		$cache = PFFormUtils::getFormCache();
		// Remove trailing whitespace to avoid unnecessary database selects
		$cacheKeyString = $property_name . '::' . rtrim( $substring );
		if ( $basePropertyName !== null ) {
			$cacheKeyString .= ',' . $basePropertyName . ',' . $baseValue;
		}
		$cacheKey = $cache->makeKey( 'pf-autocomplete', md5( $cacheKeyString ) );
		return $cache->getWithSetCallback(
			$cacheKey,
			$wgPageFormsAutocompleteCacheTimeout,
			function () use ( $property_name, $substring, $basePropertyName, $baseValue ) {
				return $this->computeAllValuesForProperty( $property_name, $substring, $basePropertyName, $baseValue );
			}
		);
	}

	/**
	 * @param string $property_name
	 * @param string $substring
	 * @param string|null $basePropertyName
	 * @param mixed $baseValue
	 * @return array
	 */
	private function computeAllValuesForProperty(
		$property_name,
		$substring,
		$basePropertyName = null,
		$baseValue = null
	) {
		global $wgPageFormsMaxAutocompleteValues;
		global $smwgDefaultStore;

		$db = wfGetDB( DB_REPLICA );
		$sqlOptions = [];
		$sqlOptions['LIMIT'] = $wgPageFormsMaxAutocompleteValues;

		if ( method_exists( 'SMW\DataValueFactory', 'newPropertyValueByLabel' ) ) {
			// SMW 3.0+
			$property = SMW\DataValueFactory::getInstance()->newPropertyValueByLabel( $property_name );
		} else {
			$property = SMWPropertyValue::makeUserProperty( $property_name );
		}

		$propertyHasTypePage = ( $property->getPropertyTypeID() == '_wpg' );
		$conditions = [ 'p_ids.smw_title' => $property_name ];
		if ( $propertyHasTypePage ) {
			$valueField = 'o_ids.smw_title';
			if ( $smwgDefaultStore === 'SMWSQLStore2' ) {
				$idsTable = $db->tableName( 'smw_ids' );
				$propsTable = $db->tableName( 'smw_rels2' );
			} else {
				// SMWSQLStore3 - also the backup for SMWSPARQLStore
				$idsTable = $db->tableName( 'smw_object_ids' );
				$propsTable = $db->tableName( 'smw_di_wikipage' );
			}
			$fromClause = "$propsTable p JOIN $idsTable p_ids ON p.p_id = p_ids.smw_id JOIN $idsTable o_ids ON p.o_id = o_ids.smw_id";
		} else {
			if ( $smwgDefaultStore === 'SMWSQLStore2' ) {
				$valueField = 'p.value_xsd';
				$idsTable = $db->tableName( 'smw_ids' );
				$propsTable = $db->tableName( 'smw_atts2' );
			} else {
				// SMWSQLStore3 - also the backup for SMWSPARQLStore
				$valueField = 'p.o_hash';
				$idsTable = $db->tableName( 'smw_object_ids' );
				$propsTable = $db->tableName( 'smw_di_blob' );
			}
			$fromClause = "$propsTable p JOIN $idsTable p_ids ON p.p_id = p_ids.smw_id";
		}

		if ( $basePropertyName !== null ) {
			if ( method_exists( 'SMW\DataValueFactory', 'newPropertyValueByLabel' ) ) {
				$baseProperty = SMW\DataValueFactory::getInstance()->newPropertyValueByLabel( $basePropertyName );
			} else {
				// SMW 3.0+
				$baseProperty = SMWPropertyValue::makeUserProperty( $basePropertyName );
			}
			$basePropertyHasTypePage = ( $baseProperty->getPropertyTypeID() == '_wpg' );

			$basePropertyName = str_replace( ' ', '_', $basePropertyName );
			$conditions['base_p_ids.smw_title'] = $basePropertyName;
			if ( $basePropertyHasTypePage ) {
				if ( $smwgDefaultStore === 'SMWSQLStore2' ) {
					$idsTable = $db->tableName( 'smw_ids' );
					$propsTable = $db->tableName( 'smw_rels2' );
				} else {
					$idsTable = $db->tableName( 'smw_object_ids' );
					$propsTable = $db->tableName( 'smw_di_wikipage' );
				}
				$fromClause .= " JOIN $propsTable p_base ON p.s_id = p_base.s_id";
				$fromClause .= " JOIN $idsTable base_p_ids ON p_base.p_id = base_p_ids.smw_id JOIN $idsTable base_o_ids ON p_base.o_id = base_o_ids.smw_id";
				$baseValue = str_replace( ' ', '_', $baseValue );
				$conditions['base_o_ids.smw_title'] = $baseValue;
			} else {
				if ( $smwgDefaultStore === 'SMWSQLStore2' ) {
					$baseValueField = 'p_base.value_xsd';
					$idsTable = $db->tableName( 'smw_ids' );
					$propsTable = $db->tableName( 'smw_atts2' );
				} else {
					$baseValueField = 'p_base.o_hash';
					$idsTable = $db->tableName( 'smw_object_ids' );
					$propsTable = $db->tableName( 'smw_di_blob' );
				}
				$fromClause .= " JOIN $propsTable p_base ON p.s_id = p_base.s_id";
				$fromClause .= " JOIN $idsTable base_p_ids ON p_base.p_id = base_p_ids.smw_id";
				$conditions[$baseValueField] = $baseValue;
			}
		}

		if ( $substring !== null ) {
			// "Page" type property valeus are stored differently
			// in the DB, i.e. underlines instead of spaces.
			$conditions[] = PFValuesUtils::getSQLConditionForAutocompleteInColumn( $valueField, $substring, $propertyHasTypePage );
		}

		$sqlOptions['ORDER BY'] = $valueField;
		$res = $db->select( $fromClause, "DISTINCT $valueField",
			$conditions, __METHOD__, $sqlOptions );

		$values = [];
		while ( $row = $res->fetchRow() ) {
			$values[] = str_replace( '_', ' ', $row[0] );
		}
		$res->free();
		return $values;
	}

	private static function getAllValuesForCargoField( $cargoTable, $cargoField, $substring, $baseCargoTable = null, $baseCargoField = null, $baseValue = null ) {
		global $wgPageFormsCacheAutocompleteValues, $wgPageFormsAutocompleteCacheTimeout;

		if ( !$wgPageFormsCacheAutocompleteValues ) {
			return self::computeAllValuesForCargoField(
				$cargoTable, $cargoField, $substring, $baseCargoTable, $baseCargoField, $baseValue );
		}

		$cache = PFFormUtils::getFormCache();
		// Remove trailing whitespace to avoid unnecessary database selects
		$cacheKeyString = $cargoTable . '|' . $cargoField . '|' . rtrim( $substring );
		if ( $baseCargoTable !== null ) {
			$cacheKeyString .= '|' . $baseCargoTable . '|' . $baseCargoField . '|' . $baseValue;
		}
		$cacheKey = $cache->makeKey( 'pf-autocomplete', md5( $cacheKeyString ) );
		return $cache->getWithSetCallback(
			$cacheKey,
			$wgPageFormsAutocompleteCacheTimeout,
			function () use ( $cargoTable, $cargoField, $substring, $baseCargoTable, $baseCargoField, $baseValue ) {
				return self::computeAllValuesForCargoField(
					$cargoTable, $cargoField, $substring, $baseCargoTable, $baseCargoField, $baseValue );
			}
		);
	}

	private static function computeAllValuesForCargoField(
		$cargoTable,
		$cargoField,
		$substring,
		$baseCargoTable,
		$baseCargoField,
		$baseValue
	) {
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsAutocompleteOnAllChars;

		$tablesStr = $cargoTable;
		$fieldsStr = $cargoField;
		$joinOnStr = '';
		$whereStr = '';

		if ( $baseCargoTable !== null && $baseCargoField !== null ) {
			if ( $baseCargoTable != $cargoTable ) {
				$tablesStr .= ", $baseCargoTable";
				$joinOnStr = "$cargoTable._pageName = $baseCargoTable._pageName";
			}
			$whereStr = "$baseCargoTable.$baseCargoField = \"$baseValue\"";
		}

		if ( $substring !== null ) {
			if ( $whereStr != '' ) {
				$whereStr .= " AND ";
			}
			$fieldIsList = self::cargoFieldIsList( $cargoTable, $cargoField );

			if ( $fieldIsList ) {
				// If it's a list field, we query directly on
				// the "helper table" for that field. We could
				// instead use "HOLDS LIKE", but this would
				// return false positives - other values that
				// have been listed alongside the values we're
				// looking for - at least for Cargo >= 2.6.
				$fieldTableName = $cargoTable . '__' . $cargoField;
				// Because of the way Cargo querying works, the
				// field table has to be listed first for only
				// the right values to show up.
				$tablesStr = $fieldTableName . ', ' . $tablesStr;
				if ( $joinOnStr != '' ) {
					$joinOnStr = ', ' . $joinOnStr;
				}
				$joinOnStr = $fieldTableName . '._rowID=' .
					$cargoTable . '._ID' . $joinOnStr;

				$fieldsStr = $cargoField = '_value';
			}

			if ( $wgPageFormsAutocompleteOnAllChars ) {
				$whereStr .= "($cargoField LIKE \"%$substring%\")";
			} else {
				$whereStr .= "($cargoField LIKE \"$substring%\" OR $cargoField LIKE \"% $substring%\")";
			}
		}

		$sqlQuery = CargoSQLQuery::newFromValues(
			$tablesStr,
			$fieldsStr,
			$whereStr,
			$joinOnStr,
			$cargoField,
			$havingStr = null,
			$cargoField,
			$wgPageFormsMaxAutocompleteValues,
			$offsetStr = 0
		);
		$queryResults = $sqlQuery->run();

		if ( $cargoField[0] != '_' ) {
			$cargoFieldAlias = str_replace( '_', ' ', $cargoField );
		} else {
			$cargoFieldAlias = $cargoField;
		}

		$values = [];
		foreach ( $queryResults as $row ) {
			$value = $row[$cargoFieldAlias];
			// @TODO - this check should not be necessary.
			if ( $value == '' ) {
				continue;
			}
			// Cargo HTML-encodes everything - let's decode double
			// quotes, at least.
			$values[] = str_replace( '&quot;', '"', $value );
		}
		return $values;
	}

	static function cargoFieldIsList( $cargoTable, $cargoField ) {
		// @TODO - this is duplicate work; the schema is retrieved
		// again when the CargoSQLQuery object is created. There should
		// be some way of avoiding that duplicate retrieval.
		try {
			$tableSchemas = CargoUtils::getTableSchemas( [ $cargoTable ] );
		} catch ( MWException $e ) {
			return false;
		}
		if ( !array_key_exists( $cargoTable, $tableSchemas ) ) {
			return false;
		}
		$tableSchema = $tableSchemas[$cargoTable];
		if ( !array_key_exists( $cargoField, $tableSchema->mFieldDescriptions ) ) {
			return false;
		}
		$fieldDesc = $tableSchema->mFieldDescriptions[$cargoField];
		return $fieldDesc->mIsList;
	}

}
