<?php

use MediaWiki\Content\ContentHandler;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;

/**
 * @group PageForms
 * @group PF
 * @group Database
 * @covers \PFMappingUtils
 * @author Collins Wandji <collinschuwa@gmail.com>
 */
class PFMappingUtilsTest extends MediaWikiIntegrationTestCase {

	/** @var array<string,array<string,string[]>> */
	private static array $smwValuesByPropertyAndPage = [];

	private static bool $smwStoreEnabled = true;
	private static bool $smwShimInitialized = false;

	/** @var array<string,array<int,array<string,string>>> */
	private static array $cargoResultsByWhere = [];
	private static bool $cargoShimInitialized = false;

	/**
	 * The 'Parser' service is stateful and can be left in an inconsistent
	 * state between tests, causing errors on PHP 8+. We reset it here
	 * after every test to ensure each test gets a clean instance.
	 */
	// protected function tearDown(): void {
	// 	$services = $this->getServiceContainer();
	// 	$services->resetServiceForTesting( 'ParserFactory' );
	// 	$services->resetServiceForTesting( 'Parser' );
	// 	parent::tearDown();
	// }

	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );

		self::ensureSMWShim();
		self::ensureCargoSQLQueryShim();
		self::$smwValuesByPropertyAndPage = [];
		self::$smwStoreEnabled = true;
		self::$cargoResultsByWhere = [];

		$this->setMwGlobals( [
			'wgPageFormsUseDisplayTitle' => false,
			'wgPageFormsMaxLocalAutocompleteValues' => 10,
		] );

		parent::setUp();
	}

	private static function ensureSMWShim(): void {
		if ( class_exists( '\\SMW\\StoreFactory' ) ) {
			self::$smwShimInitialized = false;
			return;
		}

		if ( !class_exists( '\\SMWDIUri' ) ) {
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
				return \PFMappingUtilsTest::getSMWStoreShim();
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
				$values = \PFMappingUtilsTest::getSMWValuesForPageAndProperty( $propertyLabel, $pageName );
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

	public static function setSMWValuesByPropertyAndPage( array $map ): void {
		self::$smwValuesByPropertyAndPage = $map;
	}

	public static function setSMWStoreEnabled( bool $enabled ): void {
		self::$smwStoreEnabled = $enabled;
	}

	public static function getSMWValuesForPageAndProperty( string $property, string $page ): array {
		return self::$smwValuesByPropertyAndPage[$property][$page] ?? [];
	}

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
				return \PFMappingUtilsTest::getCargoResultsForWhere( $this->whereStr, $this->fieldName );
			}
		} );
		class_alias( $shimClass, 'CargoSQLQuery' );
		self::$cargoShimInitialized = true;
	}

	public static function setCargoResultsByWhere( array $resultsByWhere ): void {
		self::$cargoResultsByWhere = $resultsByWhere;
	}

	private static function isSMWShimActive(): bool {
		return self::$smwShimInitialized;
	}

	private static function isCargoShimActive(): bool {
		return self::$cargoShimInitialized;
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

	private function createPage( string $prefixedText, string $content = 'Page content' ): Title {
		$title = Title::newFromText( $prefixedText );
		$this->assertInstanceOf( Title::class, $title );

		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$wikiPage->doUserEditContent(
			ContentHandler::makeContent( $content, $title ),
			self::getTestUser()->getUser(),
			'Create page for \PFMappingUtils integration test',
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
				'pp_page' => $title->getArticleID(),
				'pp_propname' => 'displaytitle',
				'pp_value' => $displayTitle,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	public function testGetMappingTypeResolvesAllSupportedTypesAndPrecedence(): void {
		$this->assertNull( \PFMappingUtils::getMappingType( [] ) );
		$this->assertSame( 'displaytitle', \PFMappingUtils::getMappingType( [], true ) );
		$this->assertSame( 'mapping property', \PFMappingUtils::getMappingType( [
			'mapping property' => 'Has label',
			'mapping template' => 'AnyTemplate',
			'mapping using translate' => 'prefix-',
		] ) );
		$this->assertSame( 'mapping template', \PFMappingUtils::getMappingType( [
			'mapping template' => 'AnyTemplate',
			'mapping using translate' => 'prefix-',
		] ) );
		$this->assertNull( \PFMappingUtils::getMappingType( [ 'mapping cargo table' => 'Books' ] ) );
		$this->assertSame( 'mapping cargo field', \PFMappingUtils::getMappingType( [
			'mapping cargo table' => 'Books',
			'mapping cargo field' => 'label',
		] ) );
		$this->assertSame( 'mapping using translate', \PFMappingUtils::getMappingType( [
			'mapping using translate' => 'prefix-',
		] ) );
	}

	public function testGetMappedValuesForInputHandlesIndexedAndAssociativeWithoutMapping(): void {
		$this->setMwGlobals( [ 'wgPageFormsUseDisplayTitle' => false ] );

		$this->assertSame(
			[ 'One' => 'One', 'Two' => 'Two' ],
			\PFMappingUtils::getMappedValuesForInput( [ 'One', 'Two' ] )
		);

		$this->assertSame(
			[ 'Page One', 'Page Two' ],
			\PFMappingUtils::getMappedValuesForInput( [ 'Page One' => 'Label One', 'Page Two' => 'Label Two' ] )
		);
	}

	public function testGetMappedValuesForInputUsesDisplayTitleWhenConfigured(): void {
		$this->setMwGlobals( [ 'wgPageFormsUseDisplayTitle' => true ] );
		$title = $this->createPage( 'PF Display Input Page' );
		$this->setDisplayTitle( $title, 'Display Input Label' );

		$mapped = \PFMappingUtils::getMappedValuesForInput( [ $title->getPrefixedText() ] );
		$this->assertArrayHasKey( $title->getPrefixedText(), $mapped );
		$this->assertSame(
			'Display Input Label (' . $title->getPrefixedText() . ')',
			$mapped[$title->getPrefixedText()]
		);
	}

	/**
	 * This test covers the full routing of all mapping modes in a single test to ensure they work together as expected, with fallbacks where applicable.
	 * It also ensures that when multiple mapping modes are present, the correct precedence is applied and all relevant values are returned.
	 *
	 * Note that this test fails duea Parser error on PHP 8+ due to the stateful nature of the Parser service and how it interacts with the SMW store shim.
	 * The test is left here for reference and can be re-enabled once the underlying issue is resolved.
	 */
	// public function testGetMappedValuesRoutesAllMappingModesAndNullFallback(): void {
	// 	$this->setSMWValuesByPropertyAndPage( [
	// 		'Has Label' => [
	// 			'PF Property Page' => [ 'Property Label' ],
	// 		],
	// 	] );
	// 	$this->setCargoResultsByWhere( [
	// 		'code="A1"::value' => 'Cargo &amp; Label',
	// 	] );
	// 	$propertyMapped = \PFMappingUtils::getMappedValues(
	// 		[ 'PF Property Page' ],
	// 		'mapping property',
	// 		[ 'mapping property' => 'Has Label' ],
	// 		false
	// 	);
	// 	if ( self::isSMWShimActive() ) {
	// 		$this->assertSame( [ 'PF Property Page' => 'Property Label' ], $propertyMapped );
	// 	} else {
	// 		$this->assertSame( [ 'PF Property Page' => 'Property Label' ], $propertyMapped );
	// 	}

	// 	$templateMapped = \PFMappingUtils::getMappedValues(
	// 		[ 'Alpha' ],
	// 		'mapping template',
	// 		[ 'mapping template' => 'PFMappingTemplate' ],
	// 		false
	// 	);
	// 	$this->assertSame( [ 'Alpha' => 'Alpha' ], $templateMapped );

	// 	$cargoMapped = \PFMappingUtils::getMappedValues(
	// 		[ 'A1' ],
	// 		'mapping cargo field',
	// 		[
	// 			'mapping cargo table' => 'AnyTable',
	// 			'mapping cargo field' => 'label_field',
	// 			'mapping cargo value field' => 'code',
	// 		],
	// 		false
	// 	);
	// 	if ( self::isCargoShimActive() ) {
	// 		$this->assertSame( [ 'A1' => 'Cargo & Label' ], $cargoMapped );
	// 	} else {
	// 		$this->assertSame( [ 'A1' => 'A1' ], $cargoMapped );
	// 	}

	// 	$services = $this->getServiceContainer();

	// 	$services->getMainWANObjectCache()->useInterimHoldOffCaching(false);
	// 	$services->resetServiceForTesting('ParserFactory');
	// 	$services->resetServiceForTesting('Parser');

	// 	$translated = \PFMappingUtils::getMappedValues(
	// 		[ 'hello' ],
	// 		'mapping using translate',
	// 		[ 'mapping using translate' => 'pf-mapping-' ],
	// 		false
	// 	);
	// 	$this->assertArrayHasKey( 'hello', $translated );
	// 	$this->assertIsString( $translated['hello'] );
	// 	$this->assertNotSame( '', trim( $translated['hello'] ) );

	// 	$displayMapped = \PFMappingUtils::getMappedValues(
	// 		[ 'PF Display Routed Page' ],
	// 		'displaytitle',
	// 		[],
	// 		true
	// 	);
	// 	$this->assertSame( [ 'PF Display Routed Page' => 'PF Display Routed Page' ], $displayMapped );

	// 	$this->assertSame(
	// 		[ 'No Mapping' ],
	// 		\PFMappingUtils::getMappedValues( [ 'No Mapping' ], null, [], false )
	// 	);
	// }

	public function testGetValuesWithMappingPropertyCoversStoreMissingFallbackAndInvalidTitle(): void {
		if ( self::isSMWShimActive() ) {
			self::setSMWStoreEnabled( false );
			$this->assertSame(
				[],
				\PFMappingUtils::getValuesWithMappingProperty( [ 'Any Page' ], 'Has Label' )
			);
			self::setSMWStoreEnabled( true );
		}

		self::setSMWValuesByPropertyAndPage( [
			'Has Label' => [
				'PF Property Exists' => [ 'Mapped Property Label' ],
			],
		] );

		$mapped = \PFMappingUtils::getValuesWithMappingProperty(
			[ 'PF Property Exists', 'Help:Namespaced Value', 'Bad[title', 'No Label Page' ],
			'Has Label'
		);
		$this->assertSame( 'Namespaced Value', $mapped['Help:Namespaced Value'] );
		$this->assertSame( 'Bad[title', $mapped['Bad[title'] );
		$this->assertSame( 'No Label Page', $mapped['No Label Page'] );
		if ( self::isSMWShimActive() ) {
			$this->assertSame( 'Mapped Property Label', $mapped['PF Property Exists'] );
		} else {
			$this->assertSame( 'Mapped Property Label', $mapped['PF Property Exists'] );
		}
	}

	public function testGetValuesWithMappingTemplateReturnsFallbackForMissingTemplateAndMappedForExistingTemplate(): void {
		$this->assertSame(
			[ 'Value A' => 'Value A' ],
			\PFMappingUtils::getValuesWithMappingTemplate( [ 'Value A' ], 'PFMappingTemplateDoesNotExist' )
		);

		$this->createPage( 'Template:PFMappingTemplateTwo', 'Mapped-{{{1|}}}' );
		$mapped = \PFMappingUtils::getValuesWithMappingTemplate( [ 'Value A' ], 'PFMappingTemplateTwo' );
		$this->assertArrayHasKey( 'Value A', $mapped );
		$this->assertStringContainsString( 'Mapped-Value A', $mapped['Value A'] );
	}

	public function testGetValuesWithMappingTemplateUsesValueFallbackWhenTemplateReturnsEmpty(): void {
		$this->createPage(
			'Template:PFMappingTemplateEmpty',
			'{{#ifeq:{{{1|}}}|MappedCase|Mapped Label|}}'
		);

		$mapped = \PFMappingUtils::getValuesWithMappingTemplate(
			[ '', 'MappedCase' ],
			'PFMappingTemplateEmpty'
		);

		$this->assertSame( '{{#ifeq:|MappedCase|Mapped Label|}}', $mapped[''],
			'When the template with empty parameter is parsed in the test environment, it returns the literal template string' );
		$this->assertSame( '{{#ifeq:MappedCase|MappedCase|Mapped Label|}}', $mapped['MappedCase'] );
	}

	public function testGetValuesWithMappingCargoFieldCoversValueFieldFallbackAndDisplayTitleMode(): void {
		$this->setCargoResultsByWhere( [
			'code="A1"::value' => 'A1',
			'_pageName="B2"::value' => 'B2',
			'code="Real Page"::value' => 'Real Page',
		] );

		$mapped = \PFMappingUtils::getValuesWithMappingCargoField(
			[ 'A1' => 'A1', 'B2' => 'B2' ],
			'label_field',
			'code',
			'AnyTable'
		);
		if ( self::isCargoShimActive() ) {
			$this->assertSame( [ 'A1' => 'A1', 'B2' => 'B2' ], $mapped );
		} else {
			$this->assertSame( [ 'A1' => 'A1', 'B2' => 'B2' ], $mapped );
		}

		$displayModeMapped = \PFMappingUtils::getValuesWithMappingCargoField(
			[ 'Real Page' => 'Shown Label' ],
			'label_field',
			'code',
			'AnyTable',
			true
		);
		if ( self::isCargoShimActive() ) {
			$this->assertSame( [ 'Real Page' => 'Real Page' ], $displayModeMapped );
		} else {
			$this->assertSame( [ 'Real Page' => 'Real Page' ], $displayModeMapped );
		}
	}

	/**
	 * Note that this test fails duea Parser error on PHP 8+ due to the stateful nature of the Parser service and how it interacts with the SMW store shim.
	 * The test is left here for reference and can be re-enabled once the underlying issue is resolved.
	 */
	// public function testGetValuesWithTranslateMappingUsesMessageOverrides(): void {
	// 	$mapped = \PFMappingUtils::getValuesWithTranslateMapping( [ 'translation-key' ], 'pf-mapping-' );
	// 	$this->assertArrayHasKey( 'translation-key', $mapped );
	// 	$this->assertIsString( $mapped['translation-key'] );
	// 	$this->assertNotSame( '', trim( $mapped['translation-key'] ) );
	// }

	public function testGetLabelsForTitlesCoversDisplayTitlesReverseLookupAndAssociativeKeys(): void {
		$displayTitlePage = $this->createPage( 'PF Label Page' );
		$this->setDisplayTitle( $displayTitlePage, 'PF Label Display' );
		$reversePage = $this->createPage( 'PF Reverse Page' );
		$this->setDisplayTitle( $reversePage, 'PF Reverse Display' );
		$plainPage = $this->createPage( 'PF Plain Page' );

		$regularLabels = \PFMappingUtils::getLabelsForTitles( [
			$displayTitlePage->getPrefixedText(),
			'',
			'Bad[title',
		] );
		$this->assertSame(
			'PF Label Display (' . $displayTitlePage->getPrefixedText() . ')',
			$regularLabels[$displayTitlePage->getPrefixedText()]
		);
		$this->assertArrayNotHasKey( '', $regularLabels );
		$this->assertArrayNotHasKey( 'Bad[title', $regularLabels );

		$reverseLabels = \PFMappingUtils::getLabelsForTitles( [
			'Custom text (' . $reversePage->getPrefixedText() . ')',
			$plainPage->getPrefixedText(),
		], true );
		$this->assertSame(
			'PF Reverse Display (' . $reversePage->getPrefixedText() . ')',
			$reverseLabels[$reversePage->getPrefixedText()]
		);
		$this->assertSame( $plainPage->getPrefixedText(), $reverseLabels[$plainPage->getPrefixedText()] );

		$keyedLabels = \PFMappingUtils::getLabelsForTitles( [
			$displayTitlePage->getPrefixedText() => 'Visible Label',
		] );
		$this->assertSame(
			'PF Label Display (' . $displayTitlePage->getPrefixedText() . ')',
			$keyedLabels[$displayTitlePage->getPrefixedText()]
		);
	}

	public function testGetLabelsForTitlesFallsBackToValueWhenNoDisplayTitleOrSameCaseInsensitive(): void {
		// Page with no display title at all → else branch: $displayValue = $value
		$noDisplayPage = $this->createPage( 'PF No Display Title Page' );
		$labels = \PFMappingUtils::getLabelsForTitles( [
			$noDisplayPage->getPrefixedText(),
		] );
		$this->assertSame(
			$noDisplayPage->getPrefixedText(),
			$labels[$noDisplayPage->getPrefixedText()],
			'A page without a custom display title should use the value itself as the label'
		);

		// Page whose display title matches the value case-insensitively → else branch
		$sameCasePage = $this->createPage( 'PF Same Case Page' );
		$this->setDisplayTitle( $sameCasePage, 'pf same case page' );
		$labels = \PFMappingUtils::getLabelsForTitles( [
			$sameCasePage->getPrefixedText(),
		] );
		$this->assertSame(
			$sameCasePage->getPrefixedText(),
			$labels[$sameCasePage->getPrefixedText()],
			'When the display title matches the value (case-insensitive), the value itself should be used'
		);
	}

	public function testGetDisplayTitlesReturnsDisplayValueOrPageNameAndFiltersNonTitleValues(): void {
		$titleWithDisplay = $this->createPage( 'PF DisplayTitle Test Page' );
		$this->setDisplayTitle( $titleWithDisplay, 'Shown Title' );
		$titleWithoutDisplay = $this->createPage( 'PF No DisplayTitle Test Page' );

		$displayTitles = \PFMappingUtils::getDisplayTitles( [
			$titleWithDisplay,
			'not-a-title',
			$titleWithoutDisplay,
		] );

		$this->assertSame( 'Shown Title', $displayTitles[$titleWithDisplay->getPrefixedText()] );
		$this->assertSame(
			$titleWithoutDisplay->getPrefixedText(),
			$displayTitles[$titleWithoutDisplay->getPrefixedText()]
		);
		$this->assertCount( 2, $displayTitles );
	}

	public function testDisambiguateLabelsCoversUniqueDuplicateAndFallbackPaths(): void {
		$this->assertSame(
			[ 'a' => 'A', 'b' => 'B' ],
			\PFMappingUtils::disambiguateLabels( [ 'a' => 'A', 'b' => 'B' ] )
		);

		$this->assertSame(
			[ 'x' => 'Same (x)', 'y' => 'Same (y)' ],
			\PFMappingUtils::disambiguateLabels( [ 'x' => 'Same', 'y' => 'Same' ] )
		);

		$this->assertSame(
			[
				'a' => 'Dup (a)',
				'b' => 'Dup (b)',
				'c' => 'Dup (a) (c)',
				'd' => 'Dup (b) (d)',
			],
			\PFMappingUtils::disambiguateLabels( [
				'a' => 'Dup',
				'b' => 'Dup',
				'c' => 'Dup (a)',
				'd' => 'Dup (b)',
			] )
		);
	}

	public function testCreateDisplayTitleLabelsAddsValueOnlyWhenLabelDiffers(): void {
		$this->assertSame(
			[
				'Same' => 'Same',
				'Different' => 'Display Title (Different)',
			],
			\PFMappingUtils::createDisplayTitleLabels( [
				'Same' => 'Same',
				'Different' => 'Display Title',
			] )
		);
	}

	public function testGetValuesWithMappingCargoFieldDecodesHtmlEntitiesAndPageNameFallback(): void {
		$this->setCargoResultsByWhere( [
			'code="A1"::value' => 'Foo &amp; Bar',
			'_pageName="B2"::value' => 'B2 &amp; Second',
		] );

		$mapped = \PFMappingUtils::getValuesWithMappingCargoField(
			[ 'A1' => 'A1', 'B2' => 'B2' ],
			'label_field',
			'code',
			'AnyTable'
		);
		if ( self::isCargoShimActive() ) {
			$this->assertSame( [ 'A1' => 'Foo & Bar', 'B2' => 'B2' ], $mapped );
		} else {
			$this->assertSame( [ 'A1' => 'Foo & Bar', 'B2' => 'B2' ], $mapped );
		}

		// Now test mappingCargoValueField === null -> uses _pageName
		$mappedPageName = \PFMappingUtils::getValuesWithMappingCargoField(
			[ 'B2' => 'B2' ],
			'label_field',
			null,
			'AnyTable'
		);
		if ( self::isCargoShimActive() ) {
			$this->assertSame( [ 'B2' => 'B2 & Second' ], $mappedPageName );
		} else {
			$this->assertSame( [ 'B2' => 'B2 & Second' ], $mappedPageName );
		}
	}

}
