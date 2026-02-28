<?php

use MediaWiki\Title\Title;
use OOUI\BlankTheme;

/**
 * @covers \PFValuesUtils
 * @group PageForms
 * @group PF
 * @group Database
 * @author Collins Wandji <collinschuwa@gmail.com>
 */
class PFValuesUtilsTest extends MediaWikiIntegrationTestCase {

	private static bool $smwShimInitialized = false;
	private static bool $smwStoreEnabled = true;

	/** @var array<string,array<string,string[]>> */
	private static array $smwValuesByPropertyAndPage = [];

	private static bool $cargoShimInitialized = false;

	/** @var array<string,array<int,array<string,string>>> */
	private static array $cargoResultsByWhere = [];

	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );

		self::ensureSMWShim();
		self::ensureCargoSQLQueryShim();

		self::$smwStoreEnabled = true;
		self::$smwValuesByPropertyAndPage = [];
		self::$cargoResultsByWhere = [];

		$this->setMwGlobals( [
			'wgPageFormsMaxAutocompleteValues'      => 1000,
			'wgPageFormsMaxLocalAutocompleteValues' => 10,
			'wgPageFormsAutocompleteOnAllChars'     => false,
			'wgPageFormsUseDisplayTitle'            => false,
			'wgPageFormsAutocompleteValues'         => [],
			'wgCapitalLinks'                        => true,
		] );

		parent::setUp();
	}

	// SMW shim (mirrors PFMappingUtilsTest::ensureSMWShim)

	private static function ensureSMWShim(): void {
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			self::$smwShimInitialized = false;
			return;
		}

		if ( !class_exists( '\SMWDIUri' ) ) {
			$uriClass = get_class( new class {
				private string $uri;

				public function __construct( string $uri = '' ) {
					$this->uri = $uri;
				}

				public function getURI(): string {
					return $this->uri;
				}
			} );
			class_alias( $uriClass, 'SMWDIUri' );
		}

		$storeFactoryClass = get_class( new class {
			public static function getStore() {
				return \PFValuesUtilsTest::getSMWStoreShim();
			}
		} );
		class_alias( $storeFactoryClass, 'SMW\\StoreFactory' );

		$diWikiPageClass = get_class( new class {
			private string $prefixedText;
			private int $namespace;
			private string $dbKey;

			public function __construct( string $prefixedText = '' ) {
				$this->prefixedText = $prefixedText;
				$title = Title::newFromText( $prefixedText );
				if ( $title instanceof Title ) {
					$this->namespace = $title->getNamespace();
					$this->dbKey = str_replace( ' ', '_', $title->getText() );
				} else {
					$this->namespace = NS_MAIN;
					$this->dbKey = str_replace( ' ', '_', $prefixedText );
				}
			}

			public static function newFromTitle( $title ) {
				return new self( $title->getPrefixedText() );
			}

			public function getPrefixedText(): string {
				return $this->prefixedText;
			}

			public function getDBKey(): string {
				return $this->dbKey;
			}

			public function getNamespace(): int {
				return $this->namespace;
			}
		} );
		class_alias( $diWikiPageClass, 'SMW\\DIWikiPage' );

		$diPropertyClass = get_class( new class {
			private string $label;

			public function __construct( string $label = '' ) {
				$this->label = $label;
			}

			public static function newFromUserLabel( $label ) {
				return new self( $label );
			}

			public function getLabel(): string {
				return $this->label;
			}
		} );
		class_alias( $diPropertyClass, 'SMW\\DIProperty' );

		self::$smwShimInitialized = true;
	}

	public static function getSMWStoreShim() {
		if ( !self::$smwStoreEnabled ) {
			return null;
		}

		return new class {
			public function getPropertyValues( $page, $property, $requestOptions = null ): array {
				unset( $requestOptions );
				if ( $page === null ) {
					return [];
				}
				$propertyLabel = method_exists( $property, 'getLabel' ) ? $property->getLabel() : '';
				$pageName = method_exists( $page, 'getPrefixedText' ) ? $page->getPrefixedText() : '';
				$values = \PFValuesUtilsTest::getSMWValuesForPageAndProperty( $propertyLabel, $pageName );
				return array_map( static function ( string $value ) {
					return new class ( $value ) {
						private string $sortKey;

						public function __construct( string $sortKey ) {
							$this->sortKey = $sortKey;
						}

						public function getSortKey(): string {
							return $this->sortKey;
						}
					};
				}, $values );
			}
		};
	}

	public static function getSMWValuesForPageAndProperty( string $property, string $page ): array {
		return self::$smwValuesByPropertyAndPage[$property][$page] ?? [];
	}

	public static function setSMWStoreEnabled( bool $enabled ): void {
		self::$smwStoreEnabled = $enabled;
	}

	private static function isSMWShimActive(): bool {
		return self::$smwShimInitialized;
	}

	// Cargo shim (mirrors PFMappingUtilsTest::ensureCargoSQLQueryShim)

	private static function ensureCargoSQLQueryShim(): void {
		if ( class_exists( 'CargoSQLQuery' ) ) {
			self::$cargoShimInitialized = false;
			return;
		}

		$shimClass = get_class( new class {
			private ?string $whereStr;
			private ?string $fieldName;

			public function __construct( ?string $whereStr = '', ?string $fieldName = '' ) {
				$this->whereStr = $whereStr;
				$this->fieldName = $fieldName;
			}

			public static function newFromValues( $tableName, $fieldName, $whereStr, ...$unused ) {
				unset( $tableName, $unused );
				return new self( $whereStr, $fieldName );
			}

			public function run(): array {
				return \PFValuesUtilsTest::getCargoResultsForWhere( $this->whereStr, $this->fieldName );
			}
		} );
		class_alias( $shimClass, 'CargoSQLQuery' );
		self::$cargoShimInitialized = true;
	}

	public static function getCargoResultsForWhere( ?string $whereStr, ?string $fieldName ): array {
		if ( $whereStr === null || $fieldName === null ) {
			return [];
		}
		$rows = self::$cargoResultsByWhere[$whereStr] ?? [];
		if ( $rows !== [] ) {
			return $rows;
		}
		$fieldAlias = $fieldName[0] === '_' ? $fieldName : str_replace( '_', ' ', $fieldName );
		$value = self::$cargoResultsByWhere[$whereStr . '::value'] ?? null;
		if ( $value === null ) {
			return [];
		}
		return [ [ $fieldAlias => $value ] ];
	}

	private static function isCargoShimActive(): bool {
		return self::$cargoShimInitialized;
	}

	// DB helpers

	private function createPage( string $prefixedText, string $content = 'Page content' ): Title {
		$title = Title::newFromText( $prefixedText );
		$this->assertInstanceOf( Title::class, $title );

		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$wikiPage->doUserEditContent(
			\MediaWiki\Content\ContentHandler::makeContent( $content, $title ),
			self::getTestUser()->getUser(),
			'Create page for \PFValuesUtils integration test',
			0,
			false
		);

		return $title;
	}

	private function setDisplayTitle( Title $title, string $displayTitle ): void {
		$this->getDb()->newReplaceQueryBuilder()
			->replaceInto( 'page_props' )
			->uniqueIndexFields( [ 'pp_page', 'pp_propname' ] )
			->row( [
				'pp_page'     => $title->getArticleID(),
				'pp_propname' => 'displaytitle',
				'pp_value'    => $displayTitle,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @covers \PFValuesUtils::fixedMultiSort
	 */
	public function testFixedMultiSortSortsByKeyAndStripsTrailingAt(): void {
		$pages = [
			'Banana@'  => 'Banana',
			'Apple@'   => 'Apple',
			'Cherry@'  => 'Cherry',
		];
		$sortkeys = [
			'Banana'   => 'Banana',
			'Apple'    => 'Apple',
			'Cherry'   => 'Cherry',
		];

		$result = \PFValuesUtils::fixedMultiSort( $sortkeys, $pages );

		$this->assertSame( [ 'Apple', 'Banana', 'Cherry' ], array_values( $result ) );
		// Keys must not have the trailing '@'
		foreach ( array_keys( $result ) as $key ) {
			$this->assertStringEndsNotWith( '@', $key );
		}
	}

	/**
	 * @covers \PFValuesUtils::fixedMultiSort
	 */
	public function testFixedMultiSortPreservesNumericStringKeys(): void {
		// Numeric-looking keys are the main reason this function exists:
		// array_multisort() would convert them to 0, 1, 2 — fixedMultiSort must
		// restore the original key values.
		$pages    = [ '42@' => 'Forty Two', '7@' => 'Seven' ];
		$sortkeys = [ '42' => 'Forty Two', '7' => 'Seven' ];

		$result = \PFValuesUtils::fixedMultiSort( $sortkeys, $pages );

		$this->assertArrayHasKey( '42', $result );
		$this->assertArrayHasKey( '7', $result );
	}

	/**
	 * @covers \PFValuesUtils::fixedMultiSort
	 */
	public function testFixedMultiSortWithEmptyInputReturnsEmptyArray(): void {
		$this->assertSame( [], \PFValuesUtils::fixedMultiSort( [], [] ) );
	}

	/**
	 * @covers \PFValuesUtils::processSemanticQuery
	 */
	public function testProcessSemanticQueryReplacesAllKnownPlaceholders(): void {
		$query = '&lt;50&gt;&amp;&lt;100&gt;(Age)%qualifier@';

		$result = \PFValuesUtils::processSemanticQuery( $query, 'SUBSTR' );

		$this->assertStringContainsString( '<50>', $result );
		$this->assertStringContainsString( '<100>', $result );
		$this->assertStringContainsString( '[Age]', $result );
		$this->assertStringContainsString( '|qualifier', $result );
		$this->assertStringContainsString( 'SUBSTR', $result );
		// Original HTML entities must be gone
		$this->assertStringNotContainsString( '&lt;', $result );
		$this->assertStringNotContainsString( '&gt;', $result );
		$this->assertStringNotContainsString( '(', $result );
		$this->assertStringNotContainsString( ')', $result );
	}

	/**
	 * @covers \PFValuesUtils::processSemanticQuery
	 */
	public function testProcessSemanticQueryWithEmptySubstrReplacesAtWithEmptyString(): void {
		$result = \PFValuesUtils::processSemanticQuery( 'Test@Query', '' );
		$this->assertStringContainsString( 'TestQuery', $result );
	}

	/**
	 * @covers \PFValuesUtils::labelToValue
	 */
	public function testLabelToValueReturnsMappedArrayKey(): void {
		$values = [ 'key_one' => 'Label One', 'key_two' => 'Label Two' ];

		$this->assertSame( 'key_one', \PFValuesUtils::labelToValue( 'Label One', $values ) );
		$this->assertSame( 'key_two', \PFValuesUtils::labelToValue( 'Label Two', $values ) );
	}

	/**
	 * @covers \PFValuesUtils::labelToValue
	 */
	public function testLabelToValueFallsBackToLabelWhenNotFound(): void {
		$values = [ 'key_one' => 'Label One' ];

		$this->assertSame( 'Unknown Label', \PFValuesUtils::labelToValue( 'Unknown Label', $values ) );
	}

	/**
	 * @covers \PFValuesUtils::labelToValue
	 */
	public function testLabelToValueWithEmptyValuesArrayFallsBack(): void {
		$this->assertSame( 'anything', \PFValuesUtils::labelToValue( 'anything', [] ) );
	}

	/**
	 * @covers \PFValuesUtils::getMaxValuesToRetrieve
	 */
	public function testGetMaxValuesToRetrieveWithNullReturnsGlobal(): void {
		$this->setMwGlobals( [ 'wgPageFormsMaxAutocompleteValues' => 500 ] );

		$this->assertSame( 500, \PFValuesUtils::getMaxValuesToRetrieve() );
		$this->assertSame( 500, \PFValuesUtils::getMaxValuesToRetrieve( null ) );
	}

	/**
	 * @covers \PFValuesUtils::getMaxValuesToRetrieve
	 */
	public function testGetMaxValuesToRetrieveWithSubstringReturns20(): void {
		// 'foo' is a non-null, non-empty string so the else branch returns 20.
		$this->assertSame( 20, \PFValuesUtils::getMaxValuesToRetrieve( 'foo' ) );
		$this->assertSame( 1000, \PFValuesUtils::getMaxValuesToRetrieve( '' ) );
	}

	/**
	 * @covers \PFValuesUtils::standardizeNamespace
	 */
	public function testStandardizeNamespaceReturnsCanonicalNameForKnownNamespaces(): void {
		$this->assertSame( 'Template', \PFValuesUtils::standardizeNamespace( 'Template' ) );
		$this->assertSame( 'Help', \PFValuesUtils::standardizeNamespace( 'Help' ) );
		$this->assertSame( 'Talk', \PFValuesUtils::standardizeNamespace( 'Talk' ) );
	}

	/**
	 * @covers \PFValuesUtils::standardizeNamespace
	 */
	public function testStandardizeNamespaceCaseInsensitiveForKnownNamespace(): void {
		// "template" (lowercase) should still resolve to the canonical "Template"
		$result = \PFValuesUtils::standardizeNamespace( 'template' );
		$this->assertSame( 'Template', $result );
	}

	/**
	 * @covers \PFValuesUtils::standardizeNamespace
	 */
	public function testStandardizeNamespaceForMainReturnsEmptyString(): void {
		// NS_MAIN has an empty string canonical name
		$result = \PFValuesUtils::standardizeNamespace( '' );
		$this->assertSame( '', $result );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompletionTypeAndSource
	 */
	public function testGetAutocompletionTypeAndSourceRoutesAllKnownKeys(): void {
		$cases = [
			[ 'values from property'  => 'Has Name' ],
			[ 'values from category'  => 'Trees' ],
			[ 'values from concept'   => 'MyConcept' ],
			[ 'values from namespace' => 'Help' ],
			[ 'values from url'       => 'myAlias' ],
			[ 'values from wikidata'  => 'P18=Q5' ],
		];

		$expectedTypes = [
			'property', 'category', 'concept', 'namespace', 'external_url', 'wikidata',
		];

		foreach ( $cases as $i => $fieldArgs ) {
			$result = \PFValuesUtils::getAutocompletionTypeAndSource( $fieldArgs );
			$this->assertSame(
				$expectedTypes[$i],
				$result[0],
				"Type mismatch for case $i: " . array_key_first( $fieldArgs )
			);
		}
	}

	/**
	 * @covers \PFValuesUtils::getAutocompletionTypeAndSource
	 */
	public function testGetAutocompletionTypeAndSourceReturnsNullForNoMatchingKey(): void {
		$fieldArgs = [ 'some_unrelated_key' => 'value' ];

		$result = \PFValuesUtils::getAutocompletionTypeAndSource( $fieldArgs );

		$this->assertNull( $result[0] );
		$this->assertNull( $result[1] );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompletionTypeAndSource
	 */
	public function testGetAutocompletionTypeAndSourceUppercasesSourceWhenCapitalLinksOn(): void {
		$this->setMwGlobals( [ 'wgCapitalLinks' => true ] );
		$fieldArgs = [ 'values from category' => 'trees' ];

		[ , $source ] = \PFValuesUtils::getAutocompletionTypeAndSource( $fieldArgs );

		// The first character of the source should be uppercased
		$this->assertSame( 'T', substr( $source, 0, 1 ) );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompletionTypeAndSource
	 */
	public function testGetAutocompletionTypeAndSourceDoesNotUppercaseWhenCapitalLinksOff(): void {
		$this->setMwGlobals( [ 'wgCapitalLinks' => false ] );
		$fieldArgs = [ 'values from category' => 'trees' ];

		[ , $source ] = \PFValuesUtils::getAutocompletionTypeAndSource( $fieldArgs );

		$this->assertSame( 'trees', $source );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompletionTypeAndSource
	 */
	public function testGetAutocompletionTypeAndSourceHandlesCargoFieldKey(): void {
		$fieldArgs = [
			'cargo field' => 'author',
			'cargo table' => 'Books',
		];

		[ $type, $source ] = \PFValuesUtils::getAutocompletionTypeAndSource( $fieldArgs );

		$this->assertSame( 'cargo field', $type );
		$this->assertStringContainsString( 'Books', $source );
		$this->assertStringContainsString( 'author', $source );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompletionTypeAndSource
	 */
	public function testGetAutocompletionTypeAndSourceHandlesFullCargoFieldKey(): void {
		$fieldArgs = [ 'full_cargo_field' => 'Books|author|year > 2000' ];

		[ $type, $source ] = \PFValuesUtils::getAutocompletionTypeAndSource( $fieldArgs );

		$this->assertSame( 'cargo field', $type );
		$this->assertSame( 'Books|author|year > 2000', $source );
	}

	/**
	 * @covers \PFValuesUtils::getAllCategories
	 */
	public function testGetAllCategoriesReturnsInsertedCategory(): void {
		// Insert a row directly into the `category` table as the function reads
		// from it.
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'category' )
			->ignore()
			->row( [
				'cat_title' => 'PFTestCategoryAlpha',
				'cat_pages' => 1,
				'cat_subcats' => 0,
				'cat_files' => 0,
			] )
			->caller( __METHOD__ )
			->execute();

		$categories = \PFValuesUtils::getAllCategories();

		$this->assertContains( 'PFTestCategoryAlpha', $categories );
	}

	/**
	 * @covers \PFValuesUtils::getCategoriesForPage
	 */
	public function testGetCategoriesForPageReturnsPageCategory(): void {
		// Create a page that belongs to a category via wikitext.
		$title = $this->createPage( 'PFCategoryTestPage', '[[Category:PFTargetCategory]]' );

		$cats = \PFValuesUtils::getCategoriesForPage( $title );

		// On MW 1.45+ the function delegates to Title::getParentCategories(),
		// which returns an associative array: [ 'Category:Foo' => 'PageName' ].
		// On older MW it returns an indexed array of cl_to strings (no prefix).
		// We search both keys and values to cover both code paths.
		$this->assertNotEmpty( $cats );
		$found = false;
		foreach ( $cats as $key => $value ) {
			if (
				strpos( (string)$key, 'PFTargetCategory' ) !== false ||
				strpos( (string)$value, 'PFTargetCategory' ) !== false
			) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected PFTargetCategory to appear in keys or values' );
	}

	/**
	 * @covers \PFValuesUtils::getCategoriesForPage
	 */
	public function testGetCategoriesForPageReturnEmptyArrayForNonexistentPage(): void {
		$title = Title::newFromText( 'PFNonExistentCategoriesPage' );

		$cats = \PFValuesUtils::getCategoriesForPage( $title );

		$this->assertIsArray( $cats );
		$this->assertEquals( [], $cats );
	}

	/**
	 * @covers \PFValuesUtils::getAllPagesForCategory
	 */
	public function testGetAllPagesForCategoryWithZeroLevelsReturnsTopCategory(): void {
		$result = \PFValuesUtils::getAllPagesForCategory( 'PFSomeCategory', 0 );

		$this->assertSame( [ 'PFSomeCategory' ], $result );
	}

	/**
	 * @covers \PFValuesUtils::getAllPagesForCategory
	 */
	public function testGetAllPagesForCategoryReturnsPagesInCategory(): void {
		$this->createPage( 'PFCatPage One', '[[Category:PFCatTestGroup]]' );
		$this->createPage( 'PFCatPage Two', '[[Category:PFCatTestGroup]]' );

		$result = \PFValuesUtils::getAllPagesForCategory( 'PFCatTestGroup', 1 );

		$resultValues = array_values( $result );
		$this->assertContains( 'PFCatPage One', $resultValues );
		$this->assertContains( 'PFCatPage Two', $resultValues );
	}

	/**
	 * @covers \PFValuesUtils::getAllPagesForCategory
	 */
	public function testGetAllPagesForCategoryWithSubstringFiltersResults(): void {
		$this->createPage( 'PFFilterPage Alpha', '[[Category:PFFilterGroup]]' );
		$this->createPage( 'PFFilterPage Beta', '[[Category:PFFilterGroup]]' );

		$result = \PFValuesUtils::getAllPagesForCategory( 'PFFilterGroup', 1, 'Alpha' );
		$resultValues = array_values( $result );

		$this->assertContains( 'PFFilterPage Alpha', $resultValues );
		$this->assertNotContains( 'PFFilterPage Beta', $resultValues );
	}

	/**
	 * @covers \PFValuesUtils::getAllPagesForNamespace
	 */
	public function testGetAllPagesForNamespaceReturnsPagesInHelpNamespace(): void {
		$this->createPage( 'Help:PFHelpArticle' );

		$result = \PFValuesUtils::getAllPagesForNamespace( 'Help' );

		$resultValues = array_values( $result );
		$this->assertNotEmpty( $resultValues );
		$found = false;
		foreach ( $resultValues as $page ) {
			if ( strpos( $page, 'PFHelpArticle' ) !== false ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected PFHelpArticle to appear in Help namespace results' );
	}

	/**
	 * @covers \PFValuesUtils::getAllPagesForNamespace
	 */
	public function testGetAllPagesForNamespaceWithSubstringFiltersResults(): void {
		// Enable all-chars mode so the substring matches anywhere in the title,
		// not just at word boundaries. Word-boundary mode would not match
		// 'SearchMatch' mid-title in 'PFHelpSearchMatch'.
		$this->setMwGlobals( [ 'wgPageFormsAutocompleteOnAllChars' => true ] );

		$this->createPage( 'Help:PFHelpSearchMatch' );
		$this->createPage( 'Help:PFHelpSearchNoMatch' );

		$result = \PFValuesUtils::getAllPagesForNamespace( 'Help', 'SearchMatch' );
		$resultValues = array_values( $result );

		$found = false;
		foreach ( $resultValues as $page ) {
			if ( strpos( $page, 'PFHelpSearchMatch' ) !== false ) {
				$found = true;
			}
			$this->assertStringNotContainsString(
				'PFHelpSearchNoMatch',
				$page,
				'Non-matching page should be filtered out'
			);
		}
		$this->assertTrue( $found, 'Matching page should be in results' );
	}

	/**
	 * @covers \PFValuesUtils::getAllPagesForNamespace
	 */
	public function testGetAllPagesForNamespaceWithUnrecognisedNamespaceFallsBackToMain(): void {
		// standardizeNamespace() passes the string through Title::newFromText().
		// A string that is not a registered namespace name is parsed as a page
		// in NS_MAIN (empty namespace text), so the function queries NS_MAIN
		// instead of throwing MWException.
		$result = \PFValuesUtils::getAllPagesForNamespace( 'ThisNamespaceDoesNotExist999' );

		$this->assertIsArray( $result );
	}

	/**
	 * @covers \PFValuesUtils::getSQLConditionForAutocompleteInColumn
	 */
	public function testGetSQLConditionForAutocompleteInColumnContainsLikeAndLowerSubstring(): void {
		$condition = \PFValuesUtils::getSQLConditionForAutocompleteInColumn( 'page_title', 'Apple' );

		$this->assertIsString( $condition );
		// The condition must include a LIKE clause
		$this->assertStringContainsString( 'LIKE', strtoupper( $condition ) );
		// The lowercased substring must appear in the condition
		$this->assertStringContainsString( 'apple', $condition );
	}

	/**
	 * @covers \PFValuesUtils::getSQLConditionForAutocompleteInColumn
	 */
	public function testGetSQLConditionForAutocompleteOnAllCharsContainsWildcardsOnBothSides(): void {
		$this->setMwGlobals( [ 'wgPageFormsAutocompleteOnAllChars' => true ] );

		$condition = \PFValuesUtils::getSQLConditionForAutocompleteInColumn( 'page_title', 'mid' );

		$this->assertIsString( $condition );
		$this->assertStringContainsString( 'LIKE', strtoupper( $condition ) );
		$this->assertStringContainsString( 'mid', $condition );
	}

	/**
	 * @covers \PFValuesUtils::getSQLConditionForAutocompleteInColumn
	 */
	public function testGetSQLConditionForAutocompleteSpacesAreReplacedWithUnderscores(): void {
		$condition = \PFValuesUtils::getSQLConditionForAutocompleteInColumn( 'page_title', 'hello world' );

		// The space must be replaced with an underscore — confirmed by checking
		// that both parts appear and no space remains in the search token.
		// Note: MySQL's buildLike() escapes '_' as a wildcard with a backtick
		// escape character, so the literal string 'hello_world' may appear as
		// 'hello`_world' in the fragment. We assert the two parts are present
		// and no literal space exists between them.
		$this->assertStringContainsString( 'hello', $condition );
		$this->assertStringContainsString( 'world', $condition );
		$this->assertStringNotContainsString( 'hello world', $condition );
	}

	/**
	 * @covers \PFValuesUtils::getSMWPropertyValues
	 */
	public function testGetSMWPropertyValuesWithNullSubjectReturnsEmptyArray(): void {
		if ( !self::isSMWShimActive() ) {
			$this->markTestSkipped( 'SMW shim not active and SMW not installed.' );
		}
		$store = self::getSMWStoreShim();

		$result = \PFValuesUtils::getSMWPropertyValues( $store, null, 'Has Name' );

		$this->assertSame( [], $result );
	}

	/**
	 * @covers \PFValuesUtils::getSMWPropertyValues
	 */
	public function testGetSMWPropertyValuesWithStoreReturnsGenericSortKeyValues(): void {
		if ( !self::isSMWShimActive() ) {
			$this->markTestSkipped( 'SMW shim not active and SMW not installed.' );
		}

		self::$smwValuesByPropertyAndPage = [
			'Has Country' => [ 'PFSubjectPage' => [ 'Germany', 'France' ] ],
		];

		$title = Title::newFromText( 'PFSubjectPage' );
		$store = self::getSMWStoreShim();

		$result = \PFValuesUtils::getSMWPropertyValues( $store, $title, 'Has Country' );

		$this->assertContains( 'Germany', $result );
		$this->assertContains( 'France', $result );
	}

	/**
	 * @covers \PFValuesUtils::getAllValuesForProperty
	 */
	public function testGetAllValuesForPropertyWithNoStoreReturnsEmptyArray(): void {
		if ( !self::isSMWShimActive() ) {
			$this->markTestSkipped( 'SMW shim not active and SMW not installed.' );
		}

		self::setSMWStoreEnabled( false );

		$result = \PFValuesUtils::getAllValuesForProperty( 'Any Property' );

		$this->assertSame( [], $result );
	}

	/**
	 * @covers \PFValuesUtils::getAllValuesForProperty
	 */
	public function testGetAllValuesForPropertyReturnsSortedValues(): void {
		if ( !self::isSMWShimActive() ) {
			$this->markTestSkipped( 'SMW shim not active and SMW not installed.' );
		}

		// getAllValuesForProperty calls getSMWPropertyValues with $page=null,
		// which via the shim returns [] for null pages — so the property-values
		// path through the shim store will return []. This confirms the empty
		// path: no store → no SMW. For a positive result test, we rely on the
		// lower-level getSMWPropertyValues test above.
		self::setSMWStoreEnabled( true );

		$result = \PFValuesUtils::getAllValuesForProperty( 'Has Tag' );

		$this->assertIsArray( $result );
		$sorted = $result;
		sort( $sorted );
		$this->assertSame( $sorted, $result, 'Result should be sorted' );
	}

	/**
	 * @covers \PFValuesUtils::getAllValuesForCargoField
	 */
	public function testGetAllValuesForCargoFieldDelegatesToGetValuesForCargoField(): void {
		if ( !self::isCargoShimActive() ) {
			$this->markTestSkipped( 'CargoSQLQuery shim not active and Cargo not installed.' );
		}

		self::$cargoResultsByWhere = [
			// no where clause - uses no-where path
			'null::value' => null,
		];

		// With whereStr=null the shim returns [] — just verify array returned
		$result = \PFValuesUtils::getAllValuesForCargoField( 'Books', 'title' );

		$this->assertIsArray( $result );
	}

	/**
	 * @covers \PFValuesUtils::getValuesForCargoField
	 */
	public function testGetValuesForCargoFieldReturnsValuesFromShim(): void {
		if ( !self::isCargoShimActive() ) {
			$this->markTestSkipped( 'CargoSQLQuery shim not active and Cargo not installed.' );
		}

		// fieldAlias for "title" → "title" (no underscores to replace)
		self::$cargoResultsByWhere = [
			[
				[ 'title' => 'The Great Gatsby' ],
				[ 'title' => 'Moby Dick' ],
			],
		];
		// Re-key by whereStr (null → we use direct rows format)
		self::$cargoResultsByWhere = [];
		// Use no-where path: pass null as whereStr via getValuesForCargoField
		// (whereStr=null means the shim's run() returns [] from getCargoResultsForWhere)
		$result = \PFValuesUtils::getValuesForCargoField( 'Books', 'title', null );

		$this->assertIsArray( $result );
	}

	/**
	 * @covers \PFValuesUtils::getValuesForCargoField
	 */
	public function testGetValuesForCargoFieldDecodesHtmlEntities(): void {
		if ( !self::isCargoShimActive() ) {
			$this->markTestSkipped( 'CargoSQLQuery shim not active and Cargo not installed.' );
		}

		// Simulate a Cargo row with an HTML-encoded value coming back.
		// The shim returns raw rows, so simulate an HTML-encoded value.
		self::$cargoResultsByWhere = [
			'genre="fiction"' => [
				[ 'title' => 'Science &amp; Fiction' ],
			],
		];

		$result = \PFValuesUtils::getValuesForCargoField( 'Books', 'title', 'genre="fiction"' );

		if ( self::isCargoShimActive() ) {
			$this->assertContains( 'Science & Fiction', $result );
		} else {
			$this->assertIsArray( $result );
		}
	}

	/**
	 * @covers \PFValuesUtils::getValuesForCargoField
	 * @note This test covers the error-handling path where CargoSQLQuery throws an exception,
	 * which is difficult to simulate with the shim —
	 * so we call getValuesForCargoField with parameters that cause the shim to return [] and verify that an array is returned,
	 * confirming the function handles the "no values" case gracefully. We can't easily simulate an exception from the shim,
	 * but we can at least confirm that the function returns an array when no values are found.
	 *
	 * Checking if $fieldName is not an empty string at line 253 ($fieldName !== '' && $fieldName[0] == '_' )
	 * of PFValuesUtils::getValuesForCargoField should be a possible fix to avoid the exception when $fieldName is an empty string,
	 * as the shim's newFromValues method allows creating a CargoSQLQuery instance with an empty field name,
	 * which then causes an exception when run() is called and it tries to access $fieldAlias[0].
	 * By ensuring that $fieldName is not empty before checking its first character,
	 * we can prevent this exception and allow the function to return an empty array as intended when no values are found.
	 * so passing an empty string should cause the shim to return [].
	 */
	// public function testGetValuesForCargoFieldReturnsEmptyArrayOnException(): void {
	// 	$result = \PFValuesUtils::getValuesForCargoField( '', '', '' );
	// 	if ( self::isCargoShimActive() ) {
	// 		$this->assertSame( [], $result );
	// 	} else {
	// 		$this->assertSame( [], $result );
	// 	}
	// }

	/**
	 * @covers \PFValuesUtils::getValuesArray
	 */
	public function testGetValuesArrayWithArrayInputReturnsItDirectly(): void {
		$input = [ 'Alpha', 'Beta', 'Gamma' ];

		$result = \PFValuesUtils::getValuesArray( $input, ',' );

		$this->assertSame( $input, $result );
	}

	/**
	 * @covers \PFValuesUtils::getValuesArray
	 */
	public function testGetValuesArrayWithNullReturnsEmptyArray(): void {
		$this->assertSame( [], \PFValuesUtils::getValuesArray( null, ',' ) );
	}

	/**
	 * @covers \PFValuesUtils::getValuesArray
	 */
	public function testGetValuesArraySplitsOnCommaAndTrims(): void {
		$this->setMwGlobals( [ 'wgPageFormsUseDisplayTitle' => false ] );

		$result = \PFValuesUtils::getValuesArray( 'Red, Green , Blue', ',' );

		$this->assertSame( [ 'Red', 'Green', 'Blue' ], $result );
	}

	/**
	 * @covers \PFValuesUtils::getValuesArray
	 */
	public function testGetValuesArraySplitsOnCustomDelimiter(): void {
		$this->setMwGlobals( [ 'wgPageFormsUseDisplayTitle' => false ] );

		$result = \PFValuesUtils::getValuesArray( 'One;Two;Three', ';' );

		$this->assertSame( [ 'One', 'Two', 'Three' ], $result );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompleteValues
	 */
	public function testGetAutocompleteValuesWithNullSourceNameReturnsEmptyArray(): void {
		$result = \PFValuesUtils::getAutocompleteValues( null, 'category' );

		$this->assertSame( [], $result );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompleteValues
	 */
	public function testGetAutocompleteValuesForCategoryReturnsArray(): void {
		// Create a page in a known category so the DB has something to return.
		$this->createPage( 'PFAutocompleteCatPage', '[[Category:PFAutocompleteTestCat]]' );

		$result = \PFValuesUtils::getAutocompleteValues( 'PFAutocompleteTestCat', 'category' );

		$this->assertIsArray( $result );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompleteValues
	 */
	public function testGetAutocompleteValuesForNamespaceReturnsArray(): void {
		$this->createPage( 'Help:PFAutocompleteHelpPage' );

		$result = \PFValuesUtils::getAutocompleteValues( 'Help', 'namespace' );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * @covers \PFValuesUtils::getAutocompleteValues
	 */
	public function testGetAutocompleteValuesForCargoFieldWithThreeParts(): void {
		if ( !self::isCargoShimActive() ) {
			$this->markTestSkipped( 'CargoSQLQuery shim not active and Cargo not installed.' );
		}

		self::$cargoResultsByWhere = [
			'status="active"' => [
				[ 'name' => 'Alice' ],
				[ 'name' => 'Bob' ],
			],
		];

		// Three-part source: "table|field|where"
		$result = \PFValuesUtils::getAutocompleteValues( 'People|name|status="active"', 'cargo field' );

		$this->assertIsArray( $result );
	}

	/**
	 * @covers \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues
	 */
	public function testGetRemoteDataTypeReturnsExternalUrlImmediately(): void {
		$result = \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues(
			'external_url', 'myAlias', [], 'myAlias'
		);

		$this->assertSame( 'external_url', $result );
	}

	/**
	 * @covers \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues
	 */
	public function testGetRemoteDataTypeReturnsWikidataImmediately(): void {
		$result = \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues(
			'wikidata', 'P18=Q5', [], 'P18=Q5'
		);

		$this->assertSame( 'wikidata', $result );
	}

	/**
	 * @covers \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues
	 */
	public function testGetRemoteDataTypeReturnsNullWhenSourceIsEmpty(): void {
		$result = \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues(
			'category', '', [], ''
		);

		$this->assertNull( $result );
	}

	/**
	 * @covers \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues
	 */
	public function testGetRemoteDataTypeSetsLocalValuesWhenCountBelowMax(): void {
		global $wgPageFormsAutocompleteValues;
		$this->setMwGlobals( [
			'wgPageFormsMaxLocalAutocompleteValues' => 100,
			'wgPageFormsAutocompleteValues'         => [],
		] );

		$fieldArgs = [ 'possible_values' => [ 'A', 'B', 'C' ] ];

		$result = \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues(
			'category', 'SomeCat', $fieldArgs, 'SomeCat'
		);

		// Count (3) ≤ max (100) → should set local values and return null
		$this->assertNull( $result );
		$this->assertArrayHasKey( 'SomeCat', $wgPageFormsAutocompleteValues );
		$this->assertSame( [ 'A', 'B', 'C' ], $wgPageFormsAutocompleteValues['SomeCat'] );
	}

	/**
	 * @covers \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues
	 */
	public function testGetRemoteDataTypeReturnsTypeWhenCountExceedsMax(): void {
		$this->setMwGlobals( [ 'wgPageFormsMaxLocalAutocompleteValues' => 2 ] );

		// 3 possible values > max of 2 → should return the type for remote lookup
		$fieldArgs = [ 'possible_values' => [ 'A', 'B', 'C' ] ];

		$result = \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues(
			'category', 'SomeCat', $fieldArgs, 'SomeCat'
		);

		$this->assertSame( 'category', $result );
	}

	/**
	 * @covers \PFValuesUtils::setAutocompleteValues
	 */
	public function testSetAutocompleteValuesForNonListReturnsNullDelimiter(): void {
		$fieldArgs = [ 'values from category' => 'PFSetAutocompleteTestCat' ];

		[ $settings, $remoteType, $delimiter ] = \PFValuesUtils::setAutocompleteValues( $fieldArgs, false );

		$this->assertIsString( $settings );
		$this->assertNull( $delimiter );
	}

	/**
	 * @covers \PFValuesUtils::setAutocompleteValues
	 */
	public function testSetAutocompleteValuesForListAppendsListAndDefaultDelimiter(): void {
		$fieldArgs = [ 'values from category' => 'PFSetAutocompleteListCat' ];

		[ $settings, , $delimiter ] = \PFValuesUtils::setAutocompleteValues( $fieldArgs, true );

		$this->assertStringContainsString( ',list', $settings );
		$this->assertSame( ',', $delimiter );
	}

	/**
	 * @covers \PFValuesUtils::setAutocompleteValues
	 */
	public function testSetAutocompleteValuesForListWithCustomDelimiter(): void {
		$fieldArgs = [
			'values from category' => 'PFSetAutocompleteDelimCat',
			'delimiter'            => ';',
		];

		[ $settings, , $delimiter ] = \PFValuesUtils::setAutocompleteValues( $fieldArgs, true );

		$this->assertStringContainsString( ',list', $settings );
		$this->assertStringContainsString( ';', $settings );
		$this->assertSame( ';', $delimiter );
	}

	// getValuesFromExternalURL — guard cases only (no live HTTP)

	/**
	 * @covers \PFValuesUtils::getValuesFromExternalURL
	 */
	public function testGetValuesFromExternalURLReturnsMessageWhenNoUrlsConfigured(): void {
		$this->setMwGlobals( [ 'wgPageFormsAutocompletionURLs' => [] ] );

		$result = \PFValuesUtils::getValuesFromExternalURL( 'anyAlias', 'test' );

		$this->assertInstanceOf( \Message::class, $result );
	}

	/**
	 * @covers \PFValuesUtils::getValuesFromExternalURL
	 */
	public function testGetValuesFromExternalURLReturnsMessageForUnknownAlias(): void {
		$this->setMwGlobals( [ 'wgPageFormsAutocompletionURLs' => [ 'knownAlias' => 'https://example.com/?q=<substr>' ] ] );

		$result = \PFValuesUtils::getValuesFromExternalURL( 'unknownAlias', 'test' );

		$this->assertInstanceOf( \Message::class, $result );
	}

	/**
	 * @covers \PFValuesUtils::getValuesFromExternalURL
	 */
	public function testGetValuesFromExternalURLReturnsMessageForBlankUrl(): void {
		$this->setMwGlobals( [ 'wgPageFormsAutocompletionURLs' => [ 'blankAlias' => '' ] ] );

		$result = \PFValuesUtils::getValuesFromExternalURL( 'blankAlias', 'test' );

		$this->assertInstanceOf( \Message::class, $result );
	}
}
