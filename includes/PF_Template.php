<?php
/**
 * Defines a class, PFTemplate, that represents a MediaWiki "infobox"
 * template that holds structured data, which may or may not be
 * additionally stored by Cargo and/or Semantic MediaWiki.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;

class PFTemplate {
	private $mTemplateName;
	private $mTemplateText;
	private $mTemplateFields;
	private $mTemplateParams;
	private $mConnectingProperty;
	private $mCategoryName;
	private $mCargoTable;
	private $mAggregatingProperty;
	private $mAggregationLabel;
	private $mTemplateFormat;
	private $mFieldStart;
	private $mFieldEnd;
	private $mTemplateStart;
	private $mTemplateEnd;

	public function __construct( $templateName, $templateFields ) {
		$this->mTemplateName = $templateName;
		$this->mTemplateFields = $templateFields;
	}

	public static function newFromName( $templateName ) {
		$template = new PFTemplate( $templateName, [] );
		$template->loadTemplateParams();
		$template->loadTemplateFields();
		return $template;
	}

	/**
	 * Get (and store in memory) the values from this template's
	 * #template_params call, if it exists.
	 */
	public function loadTemplateParams() {
		$embeddedTemplate = null;
		$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $this->mTemplateName );
		$services = MediaWikiServices::getInstance();
		if ( method_exists( $services, 'getPageProps' ) ) {
			// MW 1.36+
			$pageProps = $services->getPageProps();
		} else {
			$pageProps = PageProps::getInstance();
		}
		$properties = $pageProps->getProperties(
			[ $templateTitle ], [ 'PageFormsTemplateParams' ]
		);
		if ( count( $properties ) == 0 ) {
			return;
		}

		$paramsForPage = reset( $properties );
		$paramsForProperty = reset( $paramsForPage );
		$this->mTemplateParams = unserialize( $paramsForProperty );
	}

	public function getTemplateParams() {
		return $this->mTemplateParams;
	}

	/**
	 * @todo - fix so that this function only gets called once per
	 * template; right now it seems to get called once per field. (!)
	 */
	function loadTemplateFields() {
		$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $this->mTemplateName );
		if ( !isset( $templateTitle ) ) {
			return;
		}

		$templateText = PFUtils::getPageText( $templateTitle );
		// Ignore 'noinclude' sections and 'includeonly' tags.
		$templateText = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $templateText );
		$this->mTemplateText = strtr( $templateText, [ '<includeonly>' => '', '</includeonly>' => '' ] );

		// The Cargo-based function is more specific; it only gets
		// data structure information from the template schema. If
		// there's no Cargo schema for this template, we call
		// loadTemplateFieldsSMWAndOther(), which doesn't require the
		// presence of SMW and can get non-SMW information as well.
		if ( defined( 'CARGO_VERSION' ) ) {
			$this->loadTemplateFieldsCargo( $templateTitle );
			if ( count( $this->mTemplateFields ) > 0 ) {
				return;
			}
		}
		$this->loadTemplateFieldsSMWAndOther();
	}

	/**
	 * Get the fields of the template, along with the semantic property
	 * attached to each one (if any), by parsing the text of the template.
	 */
	function loadTemplateFieldsSMWAndOther() {
		$templateFields = [];
		$fieldNamesArray = [];

		// The way this works is that fields are found and then stored
		// in an array based on their location in the template text, so
		// that they can be returned in the order in which they appear
		// in the template, not the order in which they were found.
		// Some fields can be found more than once (especially if
		// they're part of an "#if" statement), so they're only
		// recorded the first time they're found.

		// Replace all calls to #set within #arraymap with standard
		// SMW tags. This is done so that they will later get
		// parsed correctly.
		// This is "cheating", since it modifies the template text
		// (the rest of the function doesn't do that), but trying to
		// get the #arraymap check regexp to find both kinds of SMW
		// property tags seemed too hard to do.
		$this->mTemplateText = preg_replace( '/#arraymap.*{{\s*#set:\s*([^=]*)=([^}]*)}}/', '[[$1:$2]]', $this->mTemplateText );

		// Look for "arraymap" parser function calls that map a
		// property onto a list.
		$ret = preg_match_all( '/{{#arraymap:{{{([^|}]*:?[^|}]*)[^\[]*\[\[([^:]*:?[^:]*)::/mis', $this->mTemplateText, $matches );
		if ( $ret ) {
			foreach ( $matches[1] as $i => $field_name ) {
				if ( !in_array( $field_name, $fieldNamesArray ) ) {
					$propertyName = $matches[2][$i];
					$this->loadPropertySettingInTemplate( $field_name, $propertyName, true );
					$fieldNamesArray[] = $field_name;
				}
			}
		} elseif ( $ret === false ) {
			// There was an error in the preg_match_all()
			// call - let the user know about it.
			if ( preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR ) {
				print 'Page Forms error: backtrace limit exceeded during parsing! Please increase the value of <a href="http://www.php.net/manual/en/pcre.configuration.php#ini.pcre.backtrack-limit">pcre.backtrack_limit</a> in php.ini or LocalSettings.php.';
			}
		}

		// Look for normal property calls.
		if ( preg_match_all( '/\[\[([^:|\[\]]*:*?[^:|\[\]]*)::{{{([^\]\|}]*).*?\]\]/mis', $this->mTemplateText, $matches ) ) {
			foreach ( $matches[1] as $i => $propertyName ) {
				$field_name = trim( $matches[2][$i] );
				if ( !in_array( $field_name, $fieldNamesArray ) ) {
					$propertyName = trim( $propertyName );
					$this->loadPropertySettingInTemplate( $field_name, $propertyName, false );
					$fieldNamesArray[] = $field_name;
				}
			}
		}

		// Then, get calls to #set, #set_internal and #subobject.
		// (Thankfully, they all have similar syntax).
		if ( preg_match_all( '/#(set|set_internal|subobject):(.*?}}})\s*}}/mis', $this->mTemplateText, $matches ) ) {
			foreach ( $matches[2] as $match ) {
				if ( preg_match_all( '/([^|{]*?)=\s*{{{([^|}]*)/mis', $match, $matches2 ) ) {
					foreach ( $matches2[1] as $i => $propertyName ) {
						$fieldName = trim( $matches2[2][$i] );
						if ( !in_array( $fieldName, $fieldNamesArray ) ) {
							$propertyName = trim( $propertyName );
							$this->loadPropertySettingInTemplate( $fieldName, $propertyName, false );
							$fieldNamesArray[] = $fieldName;
						}
					}
				}
			}
		}

		// Then, get calls to #declare. (This is really rather
		// optional, since no one seems to use #declare.)
		if ( preg_match_all( '/#declare:(.*?)}}/mis', $this->mTemplateText, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				$setValues = explode( '|', $match );
				foreach ( $setValues as $valuePair ) {
					$keyAndVal = explode( '=', $valuePair );
					if ( count( $keyAndVal ) == 2 ) {
						$propertyName = trim( $keyAndVal[0] );
						$fieldName = trim( $keyAndVal[1] );
						if ( !in_array( $fieldName, $fieldNamesArray ) ) {
							$this->loadPropertySettingInTemplate( $fieldName, $propertyName, false );
							$fieldNamesArray[] = $fieldName;
						}
					}
				}
			}
		}

		// Finally, get any non-semantic fields defined.
		if ( preg_match_all( '/{{{([^|}]*)/mis', $this->mTemplateText, $matches ) ) {
			foreach ( $matches[1] as $fieldName ) {
				$fieldName = trim( $fieldName );
				if ( !empty( $fieldName ) && ( !in_array( $fieldName, $fieldNamesArray ) ) ) {
					$cur_pos = stripos( $this->mTemplateText, $fieldName );
					$this->mTemplateFields[$cur_pos] = PFTemplateField::create( $fieldName, PFUtils::getContLang()->ucfirst( $fieldName ) );
					$fieldNamesArray[] = $fieldName;
				}
			}
		}
		ksort( $this->mTemplateFields );
	}

	/**
	 * For a field name and its attached property name located in the
	 * template text, create an PFTemplateField object out of it, and
	 * add it to $this->mTemplateFields.
	 * @param string $fieldName
	 * @param string $propertyName
	 * @param bool $isList
	 */
	function loadPropertySettingInTemplate( $fieldName, $propertyName, $isList ) {
		$templateField = PFTemplateField::create(
			$fieldName, PFUtils::getContLang()->ucfirst( $fieldName ), $propertyName,
			$isList
		);
		$cur_pos = stripos( $this->mTemplateText, $fieldName . '|' );
		$this->mTemplateFields[$cur_pos] = $templateField;
	}

	function loadTemplateFieldsCargo( $templateTitle ) {
		$cargoFieldsOfTemplateParams = [];

		// First, get the table name, and fields, declared for this
		// template, if any.
		list( $tableName, $tableSchema ) = $this->getCargoTableAndSchema( $templateTitle );
		if ( $tableName == null ) {
			$fieldDescriptions = [];
		} else {
			$fieldDescriptions = $tableSchema->mFieldDescriptions;
		}

		// If #template_params was declared for this template, our
		// job is easy - we just go through the declared fields, get
		// the Cargo data for each field if it exists, and populate
		// $mTemplateFields with it.
		if ( $this->mTemplateParams !== null ) {
			foreach ( $this->mTemplateParams as $fieldName => $fieldParams ) {
				$templateField = PFTemplateField::newFromParams( $fieldName, $fieldParams );
				$cargoField = $templateField->getExpectedCargoField();
				if ( array_key_exists( $cargoField, $fieldDescriptions ) ) {
					$fieldDescription = $fieldDescriptions[$cargoField];
					$templateField->setCargoFieldData( $tableName, $cargoField, $fieldDescription );
				}
				$this->mTemplateFields[$fieldName] = $templateField;
			}
			return;
		}

		// If there are no declared template params *or* Cargo fields,
		// exit.
		if ( count( $fieldDescriptions ) == 0 ) {
			return;
		}

		// No #template_params call, so we have to do a more manual
		// process.
		// Match template params to Cargo table fields, by parsing
		// call(s) to #cargo_store.
		// Let's find every #cargo_store tag.
		// Unfortunately, it doesn't seem possible to use a regexp
		// search for this, because it's hard to know which set of
		// double brackets represents the end of such a call. Instead,
		// we'll do some manual parsing.
		$cargoStoreLocations = [];
		$curPos = 0;
		while ( true ) {
			$newPos = strpos( $this->mTemplateText, "#cargo_store:", $curPos );
			if ( $newPos === false ) {
				break;
			}
			$curPos = $newPos + 13;
			$cargoStoreLocations[] = $curPos;
		}

		$cargoStoreCalls = [];
		foreach ( $cargoStoreLocations as $locNum => $startPos ) {
			$numUnclosedBrackets = 2;
			if ( $locNum < count( $cargoStoreLocations ) - 1 ) {
				$lastPos = $cargoStoreLocations[$locNum + 1];
			} else {
				$lastPos = strlen( $this->mTemplateText ) - 1;
			}
			$curCargoStoreCall = '';
			$curPos = $startPos;
			while ( $curPos <= $lastPos ) {
				$curChar = $this->mTemplateText[$curPos];
				$curCargoStoreCall .= $curChar;
				if ( $curChar == '}' ) {
					$numUnclosedBrackets--;
				} elseif ( $curChar == '{' ) {
					$numUnclosedBrackets++;
				}
				if ( $numUnclosedBrackets == 0 ) {
					break;
				}
				$curPos++;
			}
			$cargoStoreCalls[] = $curCargoStoreCall;
		}

		foreach ( $cargoStoreCalls as $cargoStoreCall ) {
			if ( preg_match_all( '/([^|{]*?)=\s*{{{([^|}]*)/mis', $cargoStoreCall, $matches ) ) {
				foreach ( $matches[1] as $i => $cargoFieldName ) {
					$templateParameter = trim( $matches[2][$i] );
					$cargoFieldsOfTemplateParams[$templateParameter] = $cargoFieldName;
				}
			}
		}

		// Now, combine the two sets of information into an array of
		// PFTemplateFields objects.
		// First, go through the #cargo_store parameters, add add them
		// all to the array, matching them with Cargo field descriptions
		// where possible.
		foreach ( $cargoFieldsOfTemplateParams as $templateParameter => $cargoField ) {
			$templateField = PFTemplateField::create( $templateParameter, $templateParameter );
			if ( array_key_exists( $cargoField, $fieldDescriptions ) ) {
				$fieldDescription = $fieldDescriptions[$cargoField];
				$templateField->setCargoFieldData( $tableName, $cargoField, $fieldDescription );
			}
			$this->mTemplateFields[] = $templateField;
		}

		// Now, go through the Cargo field descriptions, and add
		// whichever ones were not in #cargo_store (as of version 3.0,
		// Cargo does not require template parameters to be passed in
		// to #cargo_store).
		foreach ( $fieldDescriptions as $cargoField => $fieldDescription ) {
			$templateParameter = array_search( $cargoField, $cargoFieldsOfTemplateParams );
			if ( $templateParameter !== false ) {
				continue;
			}
			$templateParameter = str_replace( '_', ' ', $cargoField );
			$templateField = PFTemplateField::create( $templateParameter, $templateParameter );
			$templateField->setCargoFieldData( $tableName, $cargoField, $fieldDescription );
			$this->mTemplateFields[] = $templateField;
		}
	}

	function getCargoTableAndSchema( $templateTitle ) {
		$templatePageID = $templateTitle->getArticleID();
		$tableSchemaString = CargoUtils::getPageProp( $templatePageID, 'CargoFields' );
		// See if there even is DB storage for this template - if not,
		// exit.
		if ( $tableSchemaString === null ) {
			// There's no declared table - but see if there's an
			// attached table.
			list( $tableName, $isDeclared ) = CargoUtils::getTableNameForTemplate( $templateTitle );
			if ( $tableName == null ) {
				return [ null, null ];
			}
			$mainTemplatePageID = CargoUtils::getTemplateIDForDBTable( $tableName );
			$tableSchemaString = CargoUtils::getPageProp( $mainTemplatePageID, 'CargoFields' );
		} else {
			$tableName = CargoUtils::getPageProp( $templatePageID, 'CargoTableName' );
		}
		$tableSchema = CargoTableSchema::newFromDBString( $tableSchemaString );
		return [ $tableName, $tableSchema ];
	}

	public function getTemplateFields() {
		return $this->mTemplateFields;
	}

	public function getFieldNamed( $fieldName ) {
		foreach ( $this->mTemplateFields as $curField ) {
			if ( $curField->getFieldName() == $fieldName ) {
				return $curField;
			}
		}
		return null;
	}

	public function setConnectingProperty( $connectingProperty ) {
		$this->mConnectingProperty = $connectingProperty;
	}

	public function setCategoryName( $categoryName ) {
		$this->mCategoryName = $categoryName;
	}

	public function setCargoTable( $cargoTable ) {
		$this->mCargoTable = str_replace( ' ', '_', $cargoTable );
	}

	public function setAggregatingInfo( $aggregatingProperty, $aggregationLabel ) {
		$this->mAggregatingProperty = $aggregatingProperty;
		$this->mAggregationLabel = $aggregationLabel;
	}

	public function setFormat( $templateFormat ) {
		$this->mTemplateFormat = $templateFormat;
	}

	public function createCargoDeclareCall() {
		$text = '{{#cargo_declare:';
		$text .= '_table=' . $this->mCargoTable;
		foreach ( $this->mTemplateFields as $i => $field ) {
			if ( $field->getFieldType() == '' ) {
				continue;
			}

			$text .= '|';
			$text .= str_replace( ' ', '_', $field->getFieldName() ) . '=';
			if ( $field->isList() ) {
				$delimiter = $field->getDelimiter();
				if ( $delimiter == '' ) {
					$delimiter = ',';
				}
				$text .= "List ($delimiter) of ";
			}
			$text .= $field->getFieldType();
			if ( $field->getHierarchyStructure() ) {
				$hierarchyStructureString = $field->getHierarchyStructure();
				$text .= " (hierarchy;allowed values=$hierarchyStructureString)";
			} elseif ( count( $field->getPossibleValues() ) > 0 ) {
				$allowedValuesString = implode( ',', $field->getPossibleValues() );
				$text .= " (allowed values=$allowedValuesString)";
			}
		}
		$text .= '}}';
		return $text;
	}

	public function createCargoStoreCall() {
		$text = '{{#cargo_store:';
		$text .= '_table=' . $this->mCargoTable;
		if ( defined( 'CargoStore::PARAMS_OPTIONAL' ) ) {
			// Cargo 3.0+
			$text .= '}}';
			return $text;
		}

		foreach ( $this->mTemplateFields as $i => $field ) {
			$text .= '|' .
				str_replace( ' ', '_', $field->getFieldName() ) .
				'={{{' . $field->getFieldName() . '|}}}';
		}
		$text .= ' }}';
		return $text;
	}

	/**
	 * Creates the text of a template, when called from
	 * Special:CreateTemplate, Special:CreateClass or the Page Schemas
	 * extension.
	 * @return string
	 */
	public function createText() {
		// Avoid PHP 7.1 warning from passing $this by reference
		$template = $this;
		Hooks::run( 'PageForms::CreateTemplateText', [ &$template ] );
		$text = <<<END
<noinclude>
{{#template_params:
END;
		foreach ( $this->mTemplateFields as $i => $field ) {
			if ( $field->getFieldName() == '' ) {
				continue;
			}
			if ( $i > 0 ) {
				$text .= "|";
			}
			$text .= $field->toWikitext();
		}
		if ( defined( 'CARGO_VERSION' ) && !defined( 'SMW_VERSION' ) && $this->mCargoTable != '' ) {
			$cargoInUse = true;
			$cargoDeclareCall = $this->createCargoDeclareCall() . "\n";
			$cargoStoreCall = $this->createCargoStoreCall();
		} else {
			$cargoInUse = false;
			$cargoDeclareCall = '';
			$cargoStoreCall = '';
		}

		$text .= <<<END
}}
$cargoDeclareCall</noinclude><includeonly>$cargoStoreCall
END;

		if ( !defined( 'SMW_VERSION' ) ) {
			$text .= "\n{{#template_display:";
			if ( $this->mTemplateFormat != null ) {
				$text .= "_format=" . $this->mTemplateFormat;
			}
			$text .= "}}";
			$text .= $this->printCategoryTag();
			$text .= "</includeonly>";
			return $text;
		}

		// Before text
		$text .= $this->mTemplateStart;

		// $internalObjText can be either a call to #set_internal
		// or to #subobject (or null); which one we go with
		// depends on whether Semantic Internal Objects is installed,
		// and on the SMW version.
		// Thankfully, the syntaxes of #set_internal and #subobject
		// are quite similar, so we don't need too much extra logic.
		$internalObjText = null;
		if ( $this->mConnectingProperty ) {
			global $smwgDefaultStore;
			if ( defined( 'SIO_VERSION' ) ) {
				$useSubobject = false;
				$internalObjText = '{{#set_internal:' . $this->mConnectingProperty;
			} elseif ( $smwgDefaultStore == "SMWSQLStore3" ) {
				$useSubobject = true;
				$internalObjText = '{{#subobject:-|' . $this->mConnectingProperty . '={{PAGENAME}}';
			}
		}
		$setText = '';

		// Topmost part of table depends on format.
		if ( !$this->mTemplateFormat ) {
			$this->mTemplateFormat = 'standard';
		}
		if ( $this->mTemplateFormat == 'standard' ) {
			$tableText = '{| class="wikitable"' . "\n";
		} elseif ( $this->mTemplateFormat == 'infobox' ) {
			// A CSS style can't be used, unfortunately, since most
			// MediaWiki setups don't have an 'infobox' or
			// comparable CSS class.
			$tableText = <<<END
{| style="width: 30em; font-size: 90%; border: 1px solid #aaaaaa; background-color: #f9f9f9; color: black; margin-bottom: 0.5em; margin-left: 1em; padding: 0.2em; float: right; clear: right; text-align:left;"
! style="text-align: center; background-color:#ccccff;" colspan="2" |<span style="font-size: larger;">{{PAGENAME}}</span>
|-

END;
		} else {
			$tableText = '';
		}

		foreach ( $this->mTemplateFields as $i => $field ) {
			if ( $field->getFieldName() == '' ) {
				continue;
			}

			$fieldParam = '{{{' . $field->getFieldName() . '|}}}';
			if ( $field->getNamespace() === null ) {
				$fieldString = $fieldParam;
			} else {
				$fieldString = $field->getNamespace() . ':' . $fieldParam;
			}
			$separator = '';

			$fieldLabel = $field->getLabel();
			if ( $fieldLabel == '' ) {
				$fieldLabel = $field->getFieldName();
			}
			$fieldDisplay = $field->getDisplay();
			$fieldProperty = $field->getSemanticProperty();
			$fieldIsList = $field->isList();

			// Header/field label column
			if ( $fieldDisplay === null ) {
				if ( $this->mTemplateFormat == 'standard' || $this->mTemplateFormat == 'infobox' ) {
					if ( $i > 0 ) {
						$tableText .= "|-\n";
					}
					$tableText .= '! ' . $fieldLabel . "\n";
				} elseif ( $this->mTemplateFormat == 'plain' ) {
					$tableText .= "\n'''" . $fieldLabel . ":''' ";
				} elseif ( $this->mTemplateFormat == 'sections' ) {
					$tableText .= "\n==" . $fieldLabel . "==\n";
				}
			} elseif ( $fieldDisplay == 'nonempty' ) {
				if ( $this->mTemplateFormat == 'plain' || $this->mTemplateFormat == 'sections' ) {
					$tableText .= "\n";
				}
				$tableText .= '{{#if:' . $fieldParam . '|';
				if ( $this->mTemplateFormat == 'standard' || $this->mTemplateFormat == 'infobox' ) {
					if ( $i > 0 ) {
						$tableText .= "\n{{!}}-\n";
					}
					$tableText .= '! ' . $fieldLabel . "\n";
					$separator = '{{!}}';
				} elseif ( $this->mTemplateFormat == 'plain' ) {
					$tableText .= "'''" . $fieldLabel . ":''' ";
					$separator = '';
				} elseif ( $this->mTemplateFormat == 'sections' ) {
					$tableText .= '==' . $fieldLabel . "==\n";
					$separator = '';
				}
			} else {
				// If it's 'hidden', do nothing
			}
			// Value column
			if ( $this->mTemplateFormat == 'standard' || $this->mTemplateFormat == 'infobox' ) {
				if ( $fieldDisplay == 'hidden' ) {
				} elseif ( $fieldDisplay == 'nonempty' ) {
					// $tableText .= "{{!}} ";
				} else {
					$tableText .= "| ";
				}
			}

			// If we're using Cargo, fields can simply be displayed
			// normally - no need for any special tags - *unless*
			// the field holds a list of Page values, in which case
			// we need to apply #arraymap.
			$isCargoListOfPages = $cargoInUse && $field->isList() && $field->getFieldType() == 'Page';
			if ( !$fieldProperty && !$isCargoListOfPages ) {
				if ( $separator != '' ) {
					$tableText .= "$separator ";
				}
				$tableText .= $this->createTextForField( $field );
				if ( $fieldDisplay == 'nonempty' ) {
					$tableText .= " }}";
				}
				$tableText .= "\n";
			} elseif ( $internalObjText !== null ) {
				if ( $separator != '' ) {
					$tableText .= "$separator ";
				}
				$tableText .= $this->createTextForField( $field );
				if ( $fieldDisplay == 'nonempty' ) {
					$tableText .= " }}";
				}
				$tableText .= "\n";
				if ( $field->isList() ) {
					if ( $useSubobject ) {
						$internalObjText .= '|' . $fieldProperty . '=' . $fieldString . '|+sep=,';
					} else {
						$internalObjText .= '|' . $fieldProperty . '#list=' . $fieldString;
					}
				} else {
					$internalObjText .= '|' . $fieldProperty . '=' . $fieldString;
				}
			} elseif ( $fieldDisplay == 'hidden' ) {
				if ( $fieldIsList ) {
					$setText .= $fieldProperty . '#list=' . $fieldString . '|';
				} else {
					$setText .= $fieldProperty . '=' . $fieldString . '|';
				}
			} elseif ( $fieldDisplay == 'nonempty' ) {
				if ( $this->mTemplateFormat == 'standard' || $this->mTemplateFormat == 'infobox' ) {
					$tableText .= '{{!}} ';
				}
				$tableText .= $this->createTextForField( $field ) . "\n}}\n";
			} else {
				$tableText .= $this->createTextForField( $field ) . "\n";
			}
		}

		// Add an inline query to the output text, for
		// aggregation, if a property was specified.
		if ( $this->mAggregatingProperty !== null && $this->mAggregatingProperty !== '' ) {
			if ( $this->mTemplateFormat == 'standard' || $this->mTemplateFormat == 'infobox' ) {
				if ( count( $this->mTemplateFields ) > 0 ) {
					$tableText .= "|-\n";
				}
				$tableText .= <<<END
! $this->mAggregationLabel
|
END;
			} elseif ( $this->mTemplateFormat == 'plain' ) {
				$tableText .= "\n'''" . $this->mAggregationLabel . ":''' ";
			} elseif ( $this->mTemplateFormat == 'sections' ) {
				$tableText .= "\n==" . $this->mAggregationLabel . "==\n";
			}
			$tableText .= "{{#ask:[[" . $this->mAggregatingProperty . "::{{SUBJECTPAGENAME}}]]|format=list}}\n";
		}
		if ( $this->mTemplateFormat == 'standard' || $this->mTemplateFormat == 'infobox' ) {
			$tableText .= "|}";
		}
		// Leave out newlines if there's an internal property
		// set here (which would mean that there are meant to be
		// multiple instances of this template.)
		if ( $internalObjText === null ) {
			if ( $this->mTemplateFormat == 'standard' || $this->mTemplateFormat == 'infobox' ) {
				$tableText .= "\n";
			}
		} else {
			$internalObjText .= "}}";
			$text .= $internalObjText;
		}

		// Add a call to #set, if necessary
		if ( $setText !== '' ) {
			$setText = '{{#set:' . $setText . "}}\n";
			$text .= $setText;
		}

		$text .= $tableText;
		$text .= $this->printCategoryTag();

		// After text
		$text .= $this->mTemplateEnd;

		$text .= "</includeonly>\n";

		return $text;
	}

	function createTextForField( $field ) {
		$text = '';
		$fieldStart = $this->mFieldStart;
		Hooks::run( 'PageForms::TemplateFieldStart', [ $field, &$fieldStart ] );
		if ( $fieldStart != '' ) {
			$text .= "$fieldStart ";
		}

		$cargoInUse = defined( 'CARGO_VERSION' ) && !defined( 'SMW_VERSION' ) && $this->mCargoTable != '';
		$text .= $field->createText( $cargoInUse );

		$fieldEnd = $this->mFieldEnd;
		Hooks::run( 'PageForms::TemplateFieldEnd', [ $field, &$fieldEnd ] );
		if ( $fieldEnd != '' ) {
			$text .= " $fieldEnd";
		}

		return $text;
	}

	function printCategoryTag() {
		if ( ( $this->mCategoryName === '' || $this->mCategoryName === null ) ) {
			return '';
		}
		$namespaceLabels = PFUtils::getContLang()->getNamespaces();
		$categoryNamespace = $namespaceLabels[NS_CATEGORY];
		return "\n[[$categoryNamespace:" . $this->mCategoryName . "]]\n";
	}

}
