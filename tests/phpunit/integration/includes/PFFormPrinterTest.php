<?php

use MediaWiki\Content\ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;

/**
 * @covers \PFFormPrinter
 * @group Database
 * @author Himeshi De Silva
 * @author Collins Wandji <collinschuwa@gmail.com>
 */
class PFFormPrinterTest extends MediaWikiIntegrationTestCase {
	/** @var array<string,array<int,array<string,string>>> */
	private static $cargoResultsByWhere = [];

	/** @var string[] */
	private static $cargoSeenWhere = [];

	/** @var bool */
	private static $cargoShimActive = false;

	private static function ensureCargoSQLQueryShim(): void {
		if ( class_exists( 'CargoSQLQuery' ) ) {
			self::$cargoShimActive = false;
			return;
		}

		$shimClass = get_class( new class {

			/** @var string|null */
			private $whereStr;

			/**
			 * @param string|null $whereStr
			 */
			public function __construct( $whereStr = null ) {
				$this->whereStr = $whereStr;
			}

			/**
			 * @param mixed $tableName
			 * @param mixed $fieldName
			 * @param string|null $whereStr
			 * @param mixed ...$unused
			 * @return self
			 */
			public static function newFromValues( $tableName, $fieldName, $whereStr, ...$unused ) {
				return new self( $whereStr );
			}

			public function run(): array {
				return \PFFormPrinterTest::getCargoResultsForWhere( $this->whereStr );
			}
		} );
		class_alias( $shimClass, 'CargoSQLQuery' );
		self::$cargoShimActive = true;
	}

	private static function isCargoShimActive(): bool {
		return self::$cargoShimActive;
	}

	public static function setCargoResultsByWhere( array $resultsByWhere ): void {
		self::$cargoResultsByWhere = $resultsByWhere;
		self::$cargoSeenWhere = [];
	}

	public static function getCargoResultsForWhere( $whereStr ): array {
		if ( $whereStr === null ) {
			return [];
		}
		self::$cargoSeenWhere[] = $whereStr;
		return self::$cargoResultsByWhere[$whereStr] ?? [];
	}

	public static function getCargoSeenWhere(): array {
		return self::$cargoSeenWhere;
	}

	/**
	 * Set up the environment
	 */
	protected function setUp(): void {
		self::ensureCargoSQLQueryShim();
		\OOUI\Theme::setSingleton( new BlankTheme() );

		// Make sure the form is not in "disabled" state. Unfortunately setting up the global state
		// environment in a proper way to have PFFormPrinter work on a mock title object is very
		// difficult. Therefore we just override the permission check by using a hook.
		$hookContainer = $this->getServiceContainer()->getHookContainer();
		$hookContainer->register( 'PageForms::UserCanEditPage', static function ( $pageTitle, &$userCanEditPage ) {
			$userCanEditPage = true;
			return true;
		} );

		parent::setUp();
	}

	// Tests for page sections in the formHTML() method

	/**
	 * @dataProvider pageSectionDataProvider
	 */
	public function testPageSectionsWithoutExistingPages( $setup, $expected ) {
		global $wgPageFormsFormPrinter, $wgOut;
		static $cachedTestUser = null;

		if ( $cachedTestUser === null ) {
			$cachedTestUser = self::getTestUser()->getUser();
		}

		$wgOut->getContext()->setTitle( $this->getTitle() );

		[ $form_text, $page_text, $form_page_title, $generated_page_name ] =
			$wgPageFormsFormPrinter->formHTML(
				$form_def = $setup['form_definition'],
				$form_submitted = true,
				$source_is_page = false,
				$form_id = null,
				$existing_page_content = null,
				$page_name = 'TestStringForFormPageTitle',
				$page_name_formula = null,
				\PFFormPrinter::CONTEXT_REGULAR,
				$autocreate_query = [],
				$user = $cachedTestUser
			);

		$this->assertStringContainsString(
			$expected['expected_form_text'],
			$form_text,
			'asserts that formHTML() returns the correct HTML text for the form for the given test input'
		);
		$this->assertStringContainsString(
			$expected['expected_page_text'],
			$page_text,
			'assert that formHTML() returns the correct text for the page created by the form'
		);
	}

	/**
	 * @covers \PFFormPrinter::formHTML
	 * @dataProvider formHTMLDataProvider
	 */
	public function testFormHTML( $setup, $expected ) {
		global $wgPageFormsFormPrinter, $wgOut;
		global $wgRequest, $wgPageFormsShowExpandAllLink, $wgTitle;
		static $cachedTestUser = null;

		if ( $cachedTestUser === null ) {
			$cachedTestUser = self::getTestUser()->getUser();
		}

		$wgOut->getContext()->setTitle( $this->getTitle() );
		$wgTitle = Title::newFromText( 'PFFormPrinterTestTitle' );

		$wgPageFormsShowExpandAllLink = $setup['show_expand_all'] ?? false;

		$requestValues = $setup['request_values'] ?? [];
		$request = new FauxRequest( $requestValues, true );
		$wgRequest = $request;
		\RequestContext::getMain()->setRequest( $request );

		if ( array_key_exists( 'context_user', $setup ) ) {
			$contextUser = $setup['context_user'] === 'test_user'
				? $cachedTestUser
				: $setup['context_user'];
			\RequestContext::getMain()->setUser( $contextUser );
		}

		if ( array_key_exists( 'user_can_edit_override', $setup ) ) {
			$overrideValue = (bool)$setup['user_can_edit_override'];
			$this->setTemporaryHook(
				'PageForms::UserCanEditPage',
				static function ( $pageTitle, &$userCanEditPage ) use ( $overrideValue ) {
					$userCanEditPage = $overrideValue;
					return true;
				}
			);
		}

		if ( isset( $setup['read_only'] ) ) {
			$this->overrideConfigValues( [ 'ReadOnly' => $setup['read_only'] ? 'Test read-only' : false ] );
		}

		if ( !empty( $setup['create_page'] ) ) {
			$title = Title::newFromText( $setup['create_page'] );
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
			$content = ContentHandler::makeContent( 'Existing page content', $title );
			$wikiPage->doUserEditContent( $content, self::getTestUser()->getUser(), 'create page for test', 0, false, );
			// $wikiPage->newPageUpdater(self::getTestUser()->getUser(), $content, EDIT_UPDATE)->saveRevision(CommentStoreComment::newUnsavedComment('Creating page for test'));
		}

		if ( !empty( $setup['define_cargo'] ) ) {
			// Cargo-dependent scenarios should only run when Cargo runtime classes are available.
			// Otherwise defining CARGO_VERSION leaks into later datasets and breaks unrelated tests.
			if ( !class_exists( 'CargoUtils' ) ) {
				$this->markTestSkipped( 'Cargo-specific formHTML test requires CargoUtils.' );
			}
			if ( !defined( 'CARGO_VERSION' ) ) {
				define( 'CARGO_VERSION', '3.8.7' );
			}
		}

		if ( isset( $setup['cargo_results'] ) ) {
			self::setCargoResultsByWhere( $setup['cargo_results'] );
		}

		if ( isset( $setup['expected_exception'] ) ) {
			$this->expectException( $setup['expected_exception'] );
			if ( isset( $setup['expected_exception_message'] ) ) {
				$this->expectExceptionMessage( $setup['expected_exception_message'] );
			}
		}

		$form_submitted = $setup['form_submitted'] ?? true;
		$page_exists = $setup['page_exists'] ?? false;
		$form_id = $setup['form_id'] ?? null;
		$existing_page_content = $setup['existing_page_content'] ?? null;
		$page_name = $setup['page_name'] ?? 'TestFormGenerationPage';
		$page_name_formula = $setup['page_name_formula'] ?? null;
		$form_context = $setup['form_context'] ?? \PFFormPrinter::CONTEXT_REGULAR;
		$autocreate_query = $setup['autocreate_query'] ?? [];
		$user = array_key_exists( 'user', $setup ) ? $setup['user'] : $cachedTestUser;

		[ $form_text, $page_text, $form_page_title, $generated_page_name ] =
			$wgPageFormsFormPrinter->formHTML(
				$form_def = $setup['form_definition'],
				$form_submitted,
				$page_exists,
				$form_id,
				$existing_page_content,
				$page_name,
				$page_name_formula,
				$form_context,
				$autocreate_query,
				$user
			);

		if ( isset( $setup['expected_exception'] ) ) {
			return;
		}

		$expectedFormText = $expected['expected_form_text'] ?? null;
		if ( $expectedFormText !== null ) {
			if ( is_array( $expectedFormText ) ) {
				foreach ( $expectedFormText as $expectedFormTextPart ) {
					$this->assertStringContainsString(
						$expectedFormTextPart,
						$form_text,
						'asserts that formHTML() generates the expected HTML form output'
					);
				}
			} else {
				$this->assertStringContainsString(
					$expectedFormText,
					$form_text,
					'asserts that formHTML() generates the expected HTML form output'
				);
			}
		}

		$expectedPageText = $expected['expected_page_text'] ?? null;
		if ( $expectedPageText !== null ) {
			if ( is_array( $expectedPageText ) ) {
				foreach ( $expectedPageText as $expectedPageTextPart ) {
					$this->assertStringContainsString(
						$expectedPageTextPart,
						$page_text,
						'asserts that formHTML() generates the expected page text'
					);
				}
			} else {
				$this->assertStringContainsString(
					$expectedPageText,
					$page_text,
					'asserts that formHTML() generates the expected page text'
				);
			}
		}

		if ( isset( $expected['expected_form_text_regex'] ) ) {
			foreach ( (array)$expected['expected_form_text_regex'] as $regex ) {
				$this->assertMatchesRegularExpression( $regex, $form_text );
			}
		}
		if ( isset( $expected['expected_page_text_regex'] ) ) {
			foreach ( (array)$expected['expected_page_text_regex'] as $regex ) {
				$this->assertMatchesRegularExpression( $regex, $page_text );
			}
		}

		$expectedFormPageTitle = $expected['expected_form_page_title'] ?? '';
		if ( is_array( $expectedFormPageTitle ) ) {
			$this->assertContains(
				$form_page_title,
				$expectedFormPageTitle,
				'asserts that formHTML() generates an acceptable form page title'
			);
		} else {
			$this->assertSame(
				$expectedFormPageTitle,
				$form_page_title,
				'asserts that formHTML() generates the correct form page title'
			);
		}
		$this->assertSame(
			$expected['expected_generated_page_name'] ?? '',
			$generated_page_name,
			'asserts that formHTML() generates the correct generated page name'
		);
	}

	/**
	 * @covers \PFFormPrinter::makePlaceholderInFormHTML
	 */
	public function testMakePlaceholderInFormHTML() {
		$placeholder = 'testPlaceholder';
		$expected = '@insert"HTML_testPlaceholder@';
		$result = \PFFormPrinter::makePlaceholderInFormHTML( $placeholder );
		$this->assertEquals( $expected, $result, 'asserts that makePlaceholderInFormHTML() returns the correct placeholder string' );
	}

	/**
	 * @covers \PFFormPrinter::getAllInputTypes
	 */
	public function testGetAllInputTypes() {
		global $wgPageFormsFormPrinter;

		$inputTypes = $wgPageFormsFormPrinter->getAllInputTypes();
		$expectedInputTypes = [
			'text',
			'text with autocomplete',
			'textarea',
			'textarea with autocomplete',
			'date',
			'start date',
			'end date',
			'datepicker',
			'datetimepicker',
			'datetime',
			'start datetime',
			'end datetime',
			'year',
			'checkbox',
			'dropdown',
			'radiobutton',
			'checkboxes',
			'listbox',
			'combobox',
			'tree',
			'tokens',
			'regexp',
			'rating',
			'googlemaps',
			'openlayers',
			'leaflet'
		];

		$this->assertEquals( $expectedInputTypes, $inputTypes, 'asserts that getAllInputTypes() returns the correct list of input types' );
	}

	/**
	 * @covers \PFFormPrinter::getInputType
	 */
	public function testGetInputType() {
		global $wgPageFormsFormPrinter;

		$inputTypeClass = 'PFTextInput';
		$wgPageFormsFormPrinter->registerInputType( $inputTypeClass );

		$result = $wgPageFormsFormPrinter->getInputType( 'text' );
		$this->assertEquals( $inputTypeClass, $result, 'asserts that getInputType() returns the correct input type class for "text"' );

		$result = $wgPageFormsFormPrinter->getInputType( 'nonexistent' );
		$this->assertNull( $result, 'asserts that getInputType() returns null for a nonexistent input type' );
	}

	/**
	 * @covers \PFFormPrinter::placeholderFormat
	 */
	public function testPlaceholderFormat() {
		$templateName = 'TemplateName';
		$fieldName = 'FieldName';
		$expected = 'TemplateName___FieldName';
		$result = \PFFormPrinter::placeholderFormat( $templateName, $fieldName );
		$this->assertEquals( $expected, $result, 'asserts that placeholderFormat() returns the correct formatted placeholder string' );
	}

	/**
	 * @covers \PFFormPrinter::strReplaceFirst
	 */
	public function testStrReplaceFirst() {
		$search = 'foo';
		$replace = 'bar';
		$subject = 'foo foo foo';
		$expected = 'bar foo foo';
		$pfFormPrinter = new \PFFormPrinter();
		$result = $pfFormPrinter->strReplaceFirst( $search, $replace, $subject );
		$this->assertEquals( $expected, $result, 'asserts that strReplaceFirst() replaces the first occurrence of the search string' );

		$search = 'baz';
		$replace = 'qux';
		$subject = 'foo foo foo';
		$expected = 'foo foo foo';
		$result = $pfFormPrinter->strReplaceFirst( $search, $replace, $subject );
		$this->assertEquals( $expected, $result, 'asserts that strReplaceFirst() does nothing if the search string is not found' );
	}

	/**
	 * This function unfortunately leads to a validation error where an internal call to $this->getRequest() in IndexPager.php
	 * returns null. This may be an actual bug in Page Forms (due to the use of FauxRequest?) or just a bug in this test
	 * function, but regardless, this function can't be included until it is fixed.
	 */
	/*
	public function testShowDeletionLog() {
		$pfFormPrinter = new PFFormPrinter();
		$mockOutputPage = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->getMock();

		$mockTitle = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getPrefixedText', 'getText' ] )
			->getMock();
		$mockTitle->method( 'getPrefixedText' )
			->willReturn( 'TestFormGenerationPage' );
		$mockTitle->method( 'getText' )
			->willReturn( 'TestFormGenerationPage' );

		$pfFormPrinter->mPageTitle = $mockTitle;

		$mockContext = $this->getMockBuilder( RequestContext::class )
			->disableOriginalConstructor()
			->getMock();
		$mockContext->method( 'getTitle' )
			->willReturn( $mockTitle );

		$mockOutputPage->method( 'getContext' )
			->willReturn( $mockContext );

		$mockOutputPage->expects( $this->once() )
			->method( 'addHTML' )
			->with( $this->stringContains( 'moveddeleted-notice' ) );

		$result = $pfFormPrinter->showDeletionLog( $mockOutputPage );
		$this->assertTrue( $result, 'asserts that showDeletionLog() returns true' );
	}
	*/

	/**
	 * @covers \PFFormPrinter::multipleTemplateStartHTML
	 */
	public function testMultipleTemplateStartHTML() {
		$pfFormPrinter = new PFFormPrinter();
		$mockTemplateInForm = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->getMock();

		$mockTemplateInForm->method( 'getMinInstancesAllowed' )
			->willReturn( 1 );

		$mockTemplateInForm->method( 'getMaxInstancesAllowed' )
			->willReturn( 5 );

		$mockTemplateInForm->method( 'getDisplayedFieldsWhenMinimized' )
			->willReturn( 'field1, field2' );

		$result = $pfFormPrinter->multipleTemplateStartHTML( $mockTemplateInForm );

		$this->assertStringContainsString( 'multipleTemplateWrapper', $result, 'asserts that the correct HTML structure is returned' );
		$this->assertStringContainsString( 'multipleTemplateList', $result, 'asserts that the correct HTML structure is returned' );
		$this->assertStringContainsString( 'minimuminstances="1"', $result, 'asserts the minimum instances attribute' );
		$this->assertStringContainsString( 'maximuminstances="5"', $result, 'asserts the maximum instances attribute' );
		$this->assertStringContainsString( 'data-displayed-fields-when-minimized="field1, field2"', $result, 'asserts the displayed fields when minimized attribute' );
	}

	/**
	 * @covers \PFFormPrinter::formFieldHTML
	 */
	public function testFormFieldHTML() {
		$pfFormPrinter = new PFFormPrinter();
		$mockTemplate = $this->getMockBuilder( 'PFTemplate' )
			->disableOriginalConstructor()
			->getMock();

		// Explicit input type provided, using the input-type hook path.
		$mockTemplateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->getMock();
		$mockTemplateField->method( 'getFieldName' )
			->willReturn( 'testField' );
		$mockTemplateField->method( 'getPropertyType' )
			->willReturn( '_txt' );

		$mockFormField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->getMock();
		$mockFormField->method( 'getTemplateField' )
			->willReturn( $mockTemplateField );
		$mockFormField->method( 'getInputType' )
			->willReturn( 'text' );
		$mockFormField->method( 'getInputName' )
			->willReturn( 'testField' );
		$mockFormField->method( 'isHidden' )
			->willReturn( false );
		$mockFormField->method( 'isDisabled' )
			->willReturn( false );
		$mockFormField->method( 'getArgumentsForInputCall' )
			->willReturn( [] );

		$cur_value = 'testValue';
		$result = $pfFormPrinter->formFieldHTML( $mockFormField, $cur_value );

		$this->assertStringContainsString( '<input', $result, 'asserts that formFieldHTML() returns the correct HTML for a text input' );
		$this->assertStringContainsString( 'name="testField"', $result, 'asserts that formFieldHTML() includes the correct input name' );
		$this->assertStringContainsString( 'value="testValue"', $result, 'asserts that formFieldHTML() includes the correct input value' );

		$hiddenTemplateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->getMock();
		$hiddenFormField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->getMock();
		$hiddenFormField->method( 'getTemplateField' )
			->willReturn( $hiddenTemplateField );
		$hiddenFormField->method( 'isHidden' )
			->willReturn( true );
		$hiddenFormField->method( 'getInputName' )
			->willReturn( 'hiddenField' );
		$hiddenFormField->method( 'hasFieldArg' )
			->willReturnCallback( static function ( $name ) {
				return $name === 'class';
			} );
		$hiddenFormField->method( 'getFieldArg' )
			->willReturnCallback( static function ( $name ) {
				if ( $name === 'class' ) {
					return 'hidden-class';
				}
				return null;
			} );

		$hiddenValue = 'hiddenValue';
		$hiddenResult = $pfFormPrinter->formFieldHTML( $hiddenFormField, $hiddenValue );

		$this->assertStringContainsString( 'type="hidden"', $hiddenResult, 'asserts that formFieldHTML() returns a hidden input when the field is hidden' );
		$this->assertStringContainsString( 'name="hiddenField"', $hiddenResult, 'asserts that formFieldHTML() uses the correct name for hidden inputs' );
		$this->assertStringContainsString( 'value="hiddenValue"', $hiddenResult, 'asserts that formFieldHTML() uses the correct value for hidden inputs' );
		$this->assertStringContainsString( 'class="hidden-class"', $hiddenResult, 'asserts that formFieldHTML() passes the class attribute through to hidden inputs' );

		$cargoTemplateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->getMock();
		$cargoTemplateField->method( 'getFieldType' )
			->willReturn( 'TestCargoType' );
		$cargoTemplateField->method( 'getPropertyType' )
			->willReturn( '' );

		$cargoFormField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->getMock();
		$cargoFormField->method( 'getTemplateField' )
			->willReturn( $cargoTemplateField );
		$cargoFormField->method( 'getInputType' )
			->willReturn( '' );
		$cargoFormField->method( 'getInputName' )
			->willReturn( 'cargoField' );
		$cargoFormField->method( 'isHidden' )
			->willReturn( false );
		$cargoFormField->method( 'isDisabled' )
			->willReturn( false );
		$cargoFormField->method( 'isList' )
			->willReturn( false );
		$cargoFormField->method( 'getArgumentsForInputCall' )
			->willReturn( [] );

		$pfFormPrinter->setCargoTypeHook( 'TestCargoType', false, 'PFTextInput', [] );

		$cargoValue = 'cargoValue';
		$cargoResult = $pfFormPrinter->formFieldHTML( $cargoFormField, $cargoValue );

		$this->assertStringContainsString( '<input', $cargoResult, 'asserts that formFieldHTML() returns HTML when using a cargo-based mapping' );
		$this->assertStringContainsString( 'name="cargoField"', $cargoResult, 'asserts that formFieldHTML() uses the correct name for cargo-based mappings' );

		$semanticTemplateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->getMock();
		$semanticTemplateField->method( 'getFieldType' )
			->willReturn( '' );
		$semanticTemplateField->method( 'getPropertyType' )
			->willReturn( 'TestPropertyType' );

		$semanticFormField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->getMock();
		$semanticFormField->method( 'getTemplateField' )
			->willReturn( $semanticTemplateField );
		$semanticFormField->method( 'getInputType' )
			->willReturn( '' );
		$semanticFormField->method( 'getInputName' )
			->willReturn( 'semanticField' );
		$semanticFormField->method( 'isHidden' )
			->willReturn( false );
		$semanticFormField->method( 'isDisabled' )
			->willReturn( false );
		$semanticFormField->method( 'isList' )
			->willReturn( false );
		$semanticFormField->method( 'getArgumentsForInputCall' )
			->willReturn( [] );

		$pfFormPrinter->setSemanticTypeHook( 'TestPropertyType', false, 'PFTextInput', [] );

		$semanticValue = 'semanticValue';
		$semanticResult = $pfFormPrinter->formFieldHTML( $semanticFormField, $semanticValue );

		$this->assertStringContainsString( '<input', $semanticResult, 'asserts that formFieldHTML() returns HTML when using a semantic-type-based mapping' );
		$this->assertStringContainsString( 'name="semanticField"', $semanticResult, 'asserts that formFieldHTML() uses the correct name for semantic-type-based mappings' );

		$listTemplateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->getMock();
		$listTemplateField->method( 'getFieldType' )
			->willReturn( '' );
		$listTemplateField->method( 'getPropertyType' )
			->willReturn( '__NON_EXISTENT_PROP__' );
		$listTemplateField->method( 'getRegex' )
			->willReturn( '/[A-Z]+/' );

		$listFormField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->getMock();
		$listFormField->method( 'getTemplateField' )
			->willReturn( $listTemplateField );
		$listFormField->method( 'getInputType' )
			->willReturn( '' );
		$listFormField->method( 'getInputName' )
			->willReturn( 'listField' );
		$listFormField->method( 'isHidden' )
			->willReturn( false );
		$listFormField->method( 'isDisabled' )
			->willReturn( false );
		$listFormField->method( 'isList' )
			->willReturn( true );
		$listFormField->method( 'getArgumentsForInputCall' )
			->willReturn( [] );

		$listValue = 'listValue';
		$listResult = $pfFormPrinter->formFieldHTML( $listFormField, $listValue );

		$this->assertStringContainsString( '<input', $listResult, 'asserts that formFieldHTML() returns HTML for list inputs using the default text input' );
		$this->assertStringContainsString( 'name="listField"', $listResult, 'asserts that formFieldHTML() uses the correct name for list inputs' );
		$this->assertStringContainsString( 'size="100"', $listResult, 'asserts that formFieldHTML() sets the default size for list inputs when none is provided' );
	}

	/**
	 * @covers \PFFormPrinter::addTranslatableInput
	 */
	public function testAddTranslatableInputAddsHiddenInputWithExpectedName() {
		if ( !\ExtensionRegistry::getInstance()->isLoaded( 'Translate' ) ) {
			$this->markTestSkipped( 'Translate extension is required for translatable-input rendering checks.' );
		}

		$pfFormPrinter = new PFFormPrinter();

		$bracketedFormField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->getMock();
		$bracketedFormField->method( 'getInputName' )->willReturn( 'MyTemplate[MyField]' );
		$bracketedFormField->method( 'hasFieldArg' )
			->willReturnCallback( static function ( $arg ) {
				return in_array( $arg, [ 'translatable', 'translate_number_tag' ], true );
			} );
		$bracketedFormField->method( 'getFieldArg' )
			->willReturnCallback( static function ( $arg ) {
				if ( $arg === 'translatable' ) {
					return true;
				}
				if ( $arg === 'translate_number_tag' ) {
					return "<!--T:7 -->\n";
				}
				return null;
			} );

		$method = new \ReflectionMethod( PFFormPrinter::class, 'addTranslatableInput' );
		$method->setAccessible( true );

		$text = '<input type="text" name="MyTemplate[MyField]" value="value">';
		$method->invokeArgs( $pfFormPrinter, [ &$bracketedFormField, &$text ] );

		$this->assertStringContainsString( "name='MyTemplate[MyField_translate_number_tag]'", $text );
		$this->assertStringContainsString( "value='<!--T:7 -->", $text );

		$plainFormField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->getMock();
		$plainFormField->method( 'getInputName' )->willReturn( 'StandaloneField' );
		$plainFormField->method( 'hasFieldArg' )
			->willReturnCallback( static function ( $arg ) {
				return in_array( $arg, [ 'translatable', 'translate_number_tag' ], true );
			} );
		$plainFormField->method( 'getFieldArg' )
			->willReturnCallback( static function ( $arg ) {
				if ( $arg === 'translatable' ) {
					return true;
				}
				if ( $arg === 'translate_number_tag' ) {
					return "<!--T:8 -->\n";
				}
				return null;
			} );

		$text = '<input type="text" name="StandaloneField" value="value">';
		$method->invokeArgs( $pfFormPrinter, [ &$plainFormField, &$text ] );

		$this->assertStringContainsString( "name='StandaloneField_translate_number_tag'", $text );
		$this->assertStringContainsString( "value='<!--T:8 -->", $text );
	}

	/**
	 * @covers \PFFormPrinter::createFormFieldTranslateTag
	 */
	public function testCreateFormFieldTranslateTagHandlesNullAndExtractsTranslateNumberTag() {
		if ( !\ExtensionRegistry::getInstance()->isLoaded( 'Translate' ) ) {
			$this->markTestSkipped( 'Translate extension is required for createFormFieldTranslateTag checks.' );
		}

		$pfFormPrinter = new PFFormPrinter();
		$template = $this->getMockBuilder( 'PFTemplate' )->disableOriginalConstructor()->getMock();
		$tif = $this->getMockBuilder( 'PFTemplateInForm' )->disableOriginalConstructor()->getMock();

		$formField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->getMock();
		$formField->method( 'hasFieldArg' )
			->willReturnCallback( static function ( $arg ) {
				return $arg === 'translatable';
			} );
		$formField->method( 'getFieldArg' )
			->willReturnCallback( static function ( $arg ) {
				return $arg === 'translatable' ? true : null;
			} );
		$formField->expects( $this->once() )
			->method( 'setFieldArg' )
			->with( 'translate_number_tag', "<!--T:42 -->\n" );

		$method = new \ReflectionMethod( PFFormPrinter::class, 'createFormFieldTranslateTag' );
		$method->setAccessible( true );

		$curValue = null;
		$method->invokeArgs( $pfFormPrinter, [ &$template, &$tif, &$formField, &$curValue ] );
		$this->assertNull( $curValue );

		$curValue = "<translate><!--T:42 -->\nTranslated text</translate>";
		$method->invokeArgs( $pfFormPrinter, [ &$template, &$tif, &$formField, &$curValue ] );

		$this->assertSame( 'Translated text', $curValue );
	}

	/**
	 * @covers \PFFormPrinter::generateUUID
	 */
	public function testGenerateUUID() {
		$method = new \ReflectionMethod( PFFormPrinter::class, 'generateUUID' );
		$method->setAccessible( true );

		$first = $method->invoke( null );
		$second = $method->invoke( null );

		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$first
		);
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$second
		);
		$this->assertNotSame( $first, $second );
	}

	/**
	 * @covers \PFFormPrinter::getCargoBasedMapping
	 * @dataProvider cargoBasedMappingDataProvider
	 */
	public function testGetCargoBasedMapping( $setup, $expected ) {
		if ( !self::isCargoShimActive() ) {
			$this->markTestSkipped( 'CargoSQLQuery shim is not active in this environment.' );
		}

		self::setCargoResultsByWhere( $setup['results_by_where'] );

		$pfFormPrinter = new PFFormPrinter();
		$mockFormField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->getMock();
		$mockFormField->method( 'getFieldArg' )
			->willReturnMap( [
				[ 'delimiter', $setup['delimiter'] ]
			] );
		$mockFormField->method( 'isList' )
			->willReturn( $setup['is_list'] );

		$reflectionMethod = new ReflectionMethod( PFFormPrinter::class, 'getCargoBasedMapping' );
		$reflectionMethod->setAccessible( true );
		$result = $reflectionMethod->invoke(
			$pfFormPrinter,
			$setup['current_value'],
			$setup['mapping_cargo_table'],
			$setup['mapping_cargo_field'],
			$setup['mapping_cargo_value_field'],
			$mockFormField
		);

		$this->assertSame(
			$expected['mapped_value'],
			$result,
			'asserts that getCargoBasedMapping() returns expected mapped output for the given form field setup'
		);
		$this->assertSame(
			$expected['where_calls'],
			self::getCargoSeenWhere(),
			'asserts that getCargoBasedMapping() creates Cargo lookups for each split value, with expected trimming'
		);
	}

	public function cargoBasedMappingDataProvider() {
		return [
			'list values: mapped and partially unmapped' => [
				[
					'current_value' => 'A1, C3, B2',
					'mapping_cargo_table' => 'TestTable',
					'mapping_cargo_field' => 'Label',
					'mapping_cargo_value_field' => 'Code',
					'delimiter' => ',',
					'is_list' => true,
					'results_by_where' => [
						'Code="A1"' => [ [ 'Label' => 'Alpha' ] ],
						'Code="B2"' => [ [ 'Label' => 'Beta' ] ],
					],
				],
				[
					'mapped_value' => 'Alpha, C3,Beta',
					'where_calls' => [ 'Code="A1"', 'Code="C3"', 'Code="B2"' ],
				]
			],
			'list values: trimming with semicolon delimiter' => [
				[
					'current_value' => '  A1 ;B2  ',
					'mapping_cargo_table' => 'TestTable',
					'mapping_cargo_field' => 'Label',
					'mapping_cargo_value_field' => 'Code',
					'delimiter' => ';',
					'is_list' => true,
					'results_by_where' => [
						'Code="A1"' => [ [ 'Label' => 'Alpha' ] ],
						'Code="B2"' => [ [ 'Label' => 'Beta' ] ],
					],
				],
				[
					'mapped_value' => 'Alpha;Beta',
					'where_calls' => [ 'Code="A1"', 'Code="B2"' ],
				]
			],
			'single non-list value: mapped' => [
				[
					'current_value' => 'B2',
					'mapping_cargo_table' => 'TestTable',
					'mapping_cargo_field' => 'Label',
					'mapping_cargo_value_field' => 'Code',
					'delimiter' => ',',
					'is_list' => false,
					'results_by_where' => [
						'Code="B2"' => [ [ 'Label' => 'Beta' ] ],
					],
				],
				[
					'mapped_value' => 'Beta',
					'where_calls' => [ 'Code="B2"' ],
				]
			],
			'single non-list value: unmapped falls back to input' => [
				[
					'current_value' => 'Z9',
					'mapping_cargo_table' => 'TestTable',
					'mapping_cargo_field' => 'Label',
					'mapping_cargo_value_field' => 'Code',
					'delimiter' => ',',
					'is_list' => false,
					'results_by_where' => [],
				],
				[
					'mapped_value' => 'Z9',
					'where_calls' => [ 'Code="Z9"' ],
				]
			],
			'list values: quoted value is preserved when unmapped' => [
				[
					'current_value' => 'A"1, B2',
					'mapping_cargo_table' => 'TestTable',
					'mapping_cargo_field' => 'Label',
					'mapping_cargo_value_field' => 'Code',
					'delimiter' => ',',
					'is_list' => true,
					'results_by_where' => [
						'Code="B2"' => [ [ 'Label' => 'Beta' ] ],
					],
				],
				[
					'mapped_value' => 'A"1,Beta',
					'where_calls' => [ 'Code="A"1"', 'Code="B2"' ],
				]
			],
		];
	}

	/**
	 * @covers \PFFormPrinter::getStringFromPassedInArray
	 */
	public function testGetStringFromPassedInArray() {
		$pfFormPrinter = new PFFormPrinter();

		// Test for a regular list
		$value = [ 'is_list' => true, 'item1', 'item2', 'item3' ];
		$delimiter = ', ';
		$expected = 'item1,  item2,  item3';
		$result = $pfFormPrinter::getStringFromPassedInArray( $value, $delimiter );
		$this->assertEquals( $expected, $result, 'asserts that getStringFromPassedInArray() returns the correct string for a regular list' );

		// Test for a checkbox with one element
		$value = [ 'item1' ];
		$expected = 'No';
		$result = $pfFormPrinter::getStringFromPassedInArray( $value, $delimiter );
		$this->assertEquals( $expected, $result, 'asserts that getStringFromPassedInArray() returns "No" for a checkbox with one element' );

		// Test for a checkbox with two elements
		$value = [ 'item1', 'item2' ];
		$expected = 'Yes';
		$result = $pfFormPrinter::getStringFromPassedInArray( $value, $delimiter );
		$this->assertEquals( $expected, $result, 'asserts that getStringFromPassedInArray() returns "Yes" for a checkbox with two elements' );

		// Test for a date with three elements
		$value = [ 'year' => '2023', 'month' => '10', 'day' => '15' ];
		$expected = '2023-10-15';
		$result = $pfFormPrinter::getStringFromPassedInArray( $value, $delimiter );
		$this->assertEquals( $expected, $result, 'asserts that getStringFromPassedInArray() returns the correct date string for a date with three elements' );

		// Test for a date with six elements (datetime)
		$value = [ 'year' => '2023', 'month' => '10', 'day' => '15', 'hour' => '10', 'minute' => '30', 'second' => '45' ];
		$expected = '2023-10-15 10:30:45';
		$result = $pfFormPrinter::getStringFromPassedInArray( $value, $delimiter );
		$this->assertEquals( $expected, $result, 'asserts that getStringFromPassedInArray() returns the correct datetime string for a date with six elements' );

		// Test year-only date
		$value = [ 'year' => '2024', 'month' => '', 'day' => '' ];
		$expected = '2024';
		$result = $pfFormPrinter::getStringFromPassedInArray( $value, $delimiter );
		$this->assertEquals( $expected, $result, 'asserts that getStringFromPassedInArray() returns only the year when month is empty' );

		// Test month + year date in non-American format (month converted from number to name)
		$this->setMwGlobals( [ 'wgAmericanDates' => false ] );
		$value = [ 'year' => '2024', 'month' => '2', 'day' => '' ];
		$expected = 'February 2024';
		$result = $pfFormPrinter::getStringFromPassedInArray( $value, $delimiter );
		$this->assertEquals( $expected, $result, 'asserts that getStringFromPassedInArray() converts numeric month to month name when day is empty' );

		// Test American date with time, AM/PM marker and timezone
		$this->setMwGlobals( [ 'wgAmericanDates' => true ] );
		$value = [
			'year' => '2024',
			'month' => 'March',
			'day' => '7',
			'hour' => '3',
			'minute' => '5',
			'second' => '9',
			'ampm24h' => 'PM',
			'timezone' => 'UTC'
		];
		$expected = 'March 7, 2024 03:05:09 PM UTC';
		$result = $pfFormPrinter::getStringFromPassedInArray( $value, $delimiter );
		$this->assertEquals( $expected, $result, 'asserts that getStringFromPassedInArray() appends AM/PM and timezone when provided' );

		// Test invalid date payload with empty year.
		$value = [ 'year' => '', 'month' => '10', 'day' => '15' ];
		$expected = '';
		$result = $pfFormPrinter::getStringFromPassedInArray( $value, $delimiter );
		$this->assertEquals( $expected, $result, 'asserts that getStringFromPassedInArray() returns an empty string when year is missing' );
	}

	/**
	 * @covers \PFFormPrinter::spreadsheetHTML
	 */
	public function testSpreadsheetHTML() {
		$pfFormPrinter = new PFFormPrinter();
		$mockTemplateInForm = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->getMock();

		$mockTemplateInForm->method( 'getFields' )
			->willReturn( [
				// Basic fields
				$this->createMockFormField( 'field1', 'text', 'Field 1' ),
				$this->createMockFormField( 'field2', 'date', 'Field 2' ),
				$this->createMockFormField( 'field3', 'checkbox', 'Field 3' ),
				// Field that uses the "tokens or list" branch
				$this->createAdvancedMockFormField(
					'tokensField',
					'tokens',
					'Tokens Field',
					[],
					[],
					null,
					false,
					null
				),
				// Field that uses the "values" + list + delimiter branch, and sets a default
				$this->createAdvancedMockFormField(
					'valuesListField',
					'text',
					'Values List Field',
					[ 'A', 'B' ],
					[],
					'DefaultValue',
					true,
					'|'
				),
				// Field that uses the textarea type branch
				$this->createAdvancedMockFormField(
					'textareaField',
					'textarea',
					'Textarea Field',
					[],
					[],
					null,
					false,
					null
				),
				// Field that uses the datetime type branch
				$this->createAdvancedMockFormField(
					'datetimeField',
					'datetime',
					'Datetime Field',
					[],
					[],
					null,
					false,
					null
				),
				// Field that uses the select/lookup branch
				$this->createAdvancedMockFormField(
					'selectField',
					'text',
					'Select Field',
					[ 'X', 'Y' ],
					[ 'values from category' => 'SomeCategory' ],
					null,
					false,
					null
				),
			] );

		$mockTemplateInForm->method( 'getTemplateName' )
			->willReturn( 'TestTemplate' );

		$mockTemplateInForm->method( 'getHeight' )
			->willReturn( '400px' );

		$mockTemplateInForm->method( 'getGridValues' )
			->willReturn( [
				[ 'field1' => 'value1', 'field2' => '2023-10-15', 'field3' => 'Yes' ],
				[ 'field1' => 'value2', 'field2' => '2023-10-16', 'field3' => 'No' ]
			] );

		$result = $pfFormPrinter->spreadsheetHTML( $mockTemplateInForm );

		$this->assertStringContainsString( 'class="pfSpreadsheet"', $result, 'asserts that the correct HTML structure is returned' );
		$this->assertStringContainsString( 'id="TestTemplateGrid"', $result, 'asserts that the correct HTML structure is returned' );
		$this->assertStringContainsString( 'height="400px"', $result, 'asserts that the correct height attribute is included' );
		$this->assertStringContainsString( 'loading.gif', $result, 'asserts that the loading image is included' );

		global $wgPageFormsGridParams;
		$gridParams = $wgPageFormsGridParams['TestTemplate'];

		// Assert "tokens or list" branch for tokensField
		$tokensParams = $gridParams[3];
		$this->assertSame( 'tokensField', $tokensParams['name'], 'asserts that the tokens field name is set correctly' );
		$this->assertSame( 'text', $tokensParams['type'], 'asserts that tokens input falls back to text type in spreadsheets' );
		$this->assertSame( '', $tokensParams['autocompletedatatype'], 'asserts that autocompletedatatype is cleared for tokens inputs' );
		$this->assertSame( '', $tokensParams['autocompletesettings'], 'asserts that autocompletesettings is cleared for tokens inputs' );

		// Assert "values" + list + delimiter branch and default for valuesListField
		$valuesListParams = $gridParams[4];
		$this->assertSame( 'valuesListField', $valuesListParams['name'], 'asserts that the values list field name is set correctly' );
		$this->assertSame( [ 'A', 'B' ], $valuesListParams['values'], 'asserts that the possible values are passed through to the spreadsheet config' );
		$this->assertTrue( $valuesListParams['list'], 'asserts that list inputs are flagged correctly in the spreadsheet config' );
		$this->assertSame( '|', $valuesListParams['delimiter'], 'asserts that the delimiter is taken from the form field args' );
		$this->assertSame( 'DefaultValue', $valuesListParams['default'], 'asserts that the default value is included in the spreadsheet config' );

		// Assert textarea type branch for textareaField
		$textareaParams = $gridParams[5];
		$this->assertSame( 'textareaField', $textareaParams['name'], 'asserts that the textarea field name is set correctly' );
		$this->assertSame( 'textarea', $textareaParams['type'], 'asserts that textarea inputs map to the textarea spreadsheet type' );

		// Assert datetime type branch for datetimeField
		$datetimeParams = $gridParams[6];
		$this->assertSame( 'datetimeField', $datetimeParams['name'], 'asserts that the datetime field name is set correctly' );
		$this->assertSame( 'datetime', $datetimeParams['type'], 'asserts that datetime inputs map to the datetime spreadsheet type' );

		// Assert select/lookup branch for selectField
		$selectParams = $gridParams[7];
		$this->assertSame( 'selectField', $selectParams['name'], 'asserts that the select field name is set correctly' );
		$this->assertSame( 'select', $selectParams['type'], 'asserts that fields with predefined values map to the select spreadsheet type' );
		$this->assertArrayHasKey( 'items', $selectParams, 'asserts that select items are included in the spreadsheet config' );
		$this->assertSame( 'Id', $selectParams['valueField'], 'asserts that the value field key is set correctly' );
		$this->assertSame( 'Name', $selectParams['textField'], 'asserts that the text field key is set correctly' );
	}

	/**
	 * @covers \PFFormPrinter::tableHTML
	 */
	public function testTableHTML() {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 1;

		$pfFormPrinter = $this->getMockBuilder( PFFormPrinter::class )
			->onlyMethods( [ 'formFieldHTML' ] )
			->getMock();

		// Mock template in form
		$mockTemplateInForm = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->getMock();

		// Grid values for instance 0
		$mockTemplateInForm->method( 'getGridValues' )
			->willReturn( [
				0 => [
					'templateField' => 'templateValue',
					'hiddenField' => 'hiddenValue',
					'visibleField' => 'visibleValue'
				]
			] );

		$mockTemplateInForm->method( 'getTemplateName' )
			->willReturn( 'TestTemplate' );

		// Fields: holdsTemplate, hidden, visible-with-label-and-tooltip
		$templateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFieldName', 'getLabel' ] )
			->getMock();
		$templateField->method( 'getFieldName' )
			->willReturn( 'visibleField' );
		$templateField->method( 'getLabel' )
			->willReturn( 'Template Label' );

		$holdsTemplateField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->onlyMethods( [
				'getTemplateField',
				'holdsTemplate',
				'hasFieldArg',
				'getFieldArg',
				'getInputName',
				'additionalHTMLForInput'
			] )
			->getMock();
		$holdsTemplateField->method( 'getTemplateField' )
			->willReturn( $templateField );
		$holdsTemplateField->method( 'holdsTemplate' )
			->willReturn( true );
		$holdsTemplateField->method( 'hasFieldArg' )
			->willReturnCallback( static function ( $name ) {
				return $name === 'class';
			} );
		$holdsTemplateField->method( 'getFieldArg' )
			->willReturnCallback( static function ( $name ) {
				if ( $name === 'class' ) {
					return 'template-class';
				}
				return null;
			} );
		$holdsTemplateField->method( 'getInputName' )
			->willReturn( 'templateField' );
		$holdsTemplateField->method( 'additionalHTMLForInput' )
			->willReturn( '<span class="additional-template">Additional</span>' );

		$hiddenField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->onlyMethods( [
				'getTemplateField',
				'holdsTemplate',
				'isHidden',
				'hasFieldArg',
				'getFieldArg',
				'getInputName'
			] )
			->getMock();
		$hiddenField->method( 'getTemplateField' )
			->willReturn( $templateField );
		$hiddenField->method( 'holdsTemplate' )
			->willReturn( false );
		$hiddenField->method( 'isHidden' )
			->willReturn( true );
		$hiddenField->method( 'hasFieldArg' )
			->willReturnCallback( static function ( $name ) {
				return $name === 'class';
			} );
		$hiddenField->method( 'getFieldArg' )
			->willReturnCallback( static function ( $name ) {
				if ( $name === 'class' ) {
					return 'hidden-class';
				}
				return null;
			} );
		$hiddenField->method( 'getInputName' )
			->willReturn( 'hiddenField' );

		$visibleField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->onlyMethods( [
				'getTemplateField',
				'holdsTemplate',
				'isHidden',
				'getLabel',
				'getLabelMsg',
				'setFieldArg',
				'getInputName',
				'hasFieldArg',
				'getFieldArg',
				'additionalHTMLForInput'
			] )
			->getMock();
		$visibleField->method( 'getTemplateField' )
			->willReturn( $templateField );
		$visibleField->method( 'holdsTemplate' )
			->willReturn( false );
		$visibleField->method( 'isHidden' )
			->willReturn( false );
		$visibleField->method( 'getLabel' )
			->willReturn( 'Field Label' );
		$visibleField->method( 'getLabelMsg' )
			->willReturn( null );
		$visibleField->method( 'setFieldArg' )
			->willReturn( null );
		$visibleField->method( 'getInputName' )
			->willReturn( 'visibleField' );
		$visibleField->method( 'hasFieldArg' )
			->willReturnCallback( static function ( $name ) {
				return $name === 'tooltip';
			} );
		$visibleField->method( 'getFieldArg' )
			->willReturnCallback( static function ( $name ) {
				if ( $name === 'tooltip' ) {
					return 'Tooltip text';
				}
				return null;
			} );
		$visibleField->method( 'additionalHTMLForInput' )
			->willReturn( '<span class="additional-visible">Extra</span>' );

		$mockTemplateInForm->method( 'getFields' )
			->willReturn( [ $holdsTemplateField, $hiddenField, $visibleField ] );

		// Stub formFieldHTML so that we can easily assert on its output in the table
		$pfFormPrinter->method( 'formFieldHTML' )
			->willReturnCallback( static function ( $formField, $curValue ) {
				return '<input name="' . $formField->getInputName() . '" value="' . $curValue . '">';
			} );

		$result = $pfFormPrinter->tableHTML( $mockTemplateInForm, 0 );

		// Outer table wrapper
		$this->assertStringContainsString( '<table class="formtable">', $result, 'asserts that tableHTML() wraps rows in a table' );

		// Holds-template field closes and reopens the table, and includes a hidden input with class
		$this->assertStringContainsString( '</table>', $result, 'asserts that tableHTML() closes the table before a template-holding field' );
		$this->assertStringContainsString( 'name="templateField"', $result, 'asserts that tableHTML() uses the correct name for template-holding hidden inputs' );
		$this->assertStringContainsString( 'class="template-class"', $result, 'asserts that tableHTML() passes the class attribute for template-holding fields' );
		$this->assertStringContainsString( 'additional-template', $result, 'asserts that tableHTML() appends additional HTML for template-holding fields' );

		// Hidden field generates a hidden input with its own class
		$this->assertStringContainsString( 'name="hiddenField"', $result, 'asserts that tableHTML() uses the correct name for hidden fields' );
		$this->assertStringContainsString( 'class="hidden-class"', $result, 'asserts that tableHTML() passes the class attribute for hidden fields' );

		// Visible field: label, tooltip, and input with value from grid values
		$this->assertStringContainsString( 'Field Label', $result, 'asserts that tableHTML() includes the field label for visible fields' );
		$this->assertStringContainsString( 'data-tooltip="Tooltip text"', $result, 'asserts that tableHTML() sets the tooltip attribute when provided' );
		$this->assertStringContainsString( 'name="visibleField"', $result, 'asserts that tableHTML() uses the correct input name for visible fields' );
		$this->assertStringContainsString( 'value="visibleValue"', $result, 'asserts that tableHTML() passes the correct current value to formFieldHTML()' );
		$this->assertStringContainsString( 'additional-visible', $result, 'asserts that tableHTML() appends additional HTML for visible fields' );
	}

	/**
	 * @covers \PFFormPrinter::tableHTML
	 */
	public function testTableHTMLMissingGridValuesAndLabelFallbacks() {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 1;

		$pfFormPrinter = $this->getMockBuilder( PFFormPrinter::class )
			->onlyMethods( [ 'formFieldHTML' ] )
			->getMock();

		$templateInForm = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->getMock();
		$templateInForm->method( 'getGridValues' )->willReturn( [] );
		$templateInForm->method( 'getTemplateName' )->willReturn( 'LabelFallbackTemplate' );

		$msgTemplateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFieldName', 'getLabel' ] )
			->getMock();
		$msgTemplateField->method( 'getFieldName' )->willReturn( 'msgField' );
		$msgTemplateField->method( 'getLabel' )->willReturn( null );

		$msgLabelField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->onlyMethods( [
				'getTemplateField',
				'holdsTemplate',
				'isHidden',
				'getLabel',
				'getLabelMsg',
				'getInputName',
				'hasFieldArg',
				'additionalHTMLForInput'
			] )
			->getMock();
		$msgLabelField->method( 'getTemplateField' )->willReturn( $msgTemplateField );
		$msgLabelField->method( 'holdsTemplate' )->willReturn( false );
		$msgLabelField->method( 'isHidden' )->willReturn( false );
		$msgLabelField->method( 'getLabel' )->willReturn( null );
		$msgLabelField->method( 'getLabelMsg' )->willReturn( 'mainpage' );
		$msgLabelField->method( 'getInputName' )->willReturn( 'msgField' );
		$msgLabelField->method( 'hasFieldArg' )->willReturn( false );
		$msgLabelField->method( 'additionalHTMLForInput' )->willReturn( '' );

		$templateLabelTemplateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFieldName', 'getLabel' ] )
			->getMock();
		$templateLabelTemplateField->method( 'getFieldName' )->willReturn( 'templateLabelField' );
		$templateLabelTemplateField->method( 'getLabel' )->willReturn( 'Template Label' );

		$templateLabelField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->onlyMethods( [
				'getTemplateField',
				'holdsTemplate',
				'isHidden',
				'getLabel',
				'getLabelMsg',
				'getInputName',
				'hasFieldArg',
				'additionalHTMLForInput'
			] )
			->getMock();
		$templateLabelField->method( 'getTemplateField' )->willReturn( $templateLabelTemplateField );
		$templateLabelField->method( 'holdsTemplate' )->willReturn( false );
		$templateLabelField->method( 'isHidden' )->willReturn( false );
		$templateLabelField->method( 'getLabel' )->willReturn( null );
		$templateLabelField->method( 'getLabelMsg' )->willReturn( null );
		$templateLabelField->method( 'getInputName' )->willReturn( 'templateLabelField' );
		$templateLabelField->method( 'hasFieldArg' )->willReturn( false );
		$templateLabelField->method( 'additionalHTMLForInput' )->willReturn( '' );

		$fallbackTemplateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFieldName', 'getLabel' ] )
			->getMock();
		$fallbackTemplateField->method( 'getFieldName' )->willReturn( 'fallbackField' );
		$fallbackTemplateField->method( 'getLabel' )->willReturn( null );

		$fallbackField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->onlyMethods( [
				'getTemplateField',
				'holdsTemplate',
				'isHidden',
				'getLabel',
				'getLabelMsg',
				'getInputName',
				'hasFieldArg',
				'additionalHTMLForInput'
			] )
			->getMock();
		$fallbackField->method( 'getTemplateField' )->willReturn( $fallbackTemplateField );
		$fallbackField->method( 'holdsTemplate' )->willReturn( false );
		$fallbackField->method( 'isHidden' )->willReturn( false );
		$fallbackField->method( 'getLabel' )->willReturn( null );
		$fallbackField->method( 'getLabelMsg' )->willReturn( null );
		$fallbackField->method( 'getInputName' )->willReturn( 'fallbackField' );
		$fallbackField->method( 'hasFieldArg' )->willReturn( false );
		$fallbackField->method( 'additionalHTMLForInput' )->willReturn( '' );

		$templateInForm->method( 'getFields' )->willReturn( [ $msgLabelField, $templateLabelField, $fallbackField ] );

		$pfFormPrinter->method( 'formFieldHTML' )
			->willReturnCallback( static function ( $formField, $curValue ) {
				return '<input name="' . $formField->getInputName() . '" value="' . $curValue . '">';
			} );

		$result = $pfFormPrinter->tableHTML( $templateInForm, 42 );

		$this->assertStringContainsString( 'Template Label:', $result, 'asserts that tableHTML() uses the template field label when field label and label message are absent' );
		$this->assertStringContainsString( 'fallbackField: ', $result, 'asserts that tableHTML() falls back to field name when no label sources are available' );
		$this->assertStringContainsString( 'name="msgField" value=""', $result, 'asserts that tableHTML() passes null current values when grid values for the instance do not exist' );
	}

	/**
	 * @covers \PFFormPrinter::spreadsheetHTML
	 */
	public function testSpreadsheetHTMLReturnsNullWhenNoFields() {
		$pfFormPrinter = new PFFormPrinter();
		$templateInForm = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFields' ] )
			->getMock();
		$templateInForm->method( 'getFields' )->willReturn( [] );

		$result = $pfFormPrinter->spreadsheetHTML( $templateInForm );
		$this->assertNull( $result, 'asserts that spreadsheetHTML() returns null when no fields are defined' );
	}

	/**
	 * @covers \PFFormPrinter::multipleTemplateInstanceTableHTML
	 */
	public function testMultipleTemplateInstanceTableHTMLEnabledAndDisabled() {
		$pfFormPrinter = new PFFormPrinter();

		$mainText = 'InnerContent';

		// Enabled form: should have add/remove buttons.
		$enabledHtml = $pfFormPrinter->multipleTemplateInstanceTableHTML( false, $mainText );
		$this->assertStringContainsString( 'class="multipleTemplateInstanceTable"', $enabledHtml, 'asserts that the correct table class is used' );
		$this->assertStringContainsString( 'class="instanceMain">InnerContent', $enabledHtml, 'asserts that the main text is placed in the instanceMain cell' );
		$this->assertStringContainsString( 'class="instanceAddAbove"', $enabledHtml, 'asserts that the add-above cell is present when the form is enabled' );
		$this->assertStringContainsString( 'class="addAboveButton"', $enabledHtml, 'asserts that the addAboveButton anchor is rendered when the form is enabled' );
		$this->assertStringContainsString( 'class="instanceRemove"', $enabledHtml, 'asserts that the remove cell is present when the form is enabled' );
		$this->assertStringContainsString( 'class="removeButton"', $enabledHtml, 'asserts that the removeButton anchor is rendered when the form is enabled' );

		// Disabled form: add/remove buttons should be empty strings.
		$disabledHtml = $pfFormPrinter->multipleTemplateInstanceTableHTML( true, $mainText );
		$this->assertStringContainsString( 'class="multipleTemplateInstanceTable"', $disabledHtml, 'asserts that the correct table class is used when disabled' );
		$this->assertStringContainsString( 'class="instanceMain">InnerContent', $disabledHtml, 'asserts that the main text is placed in the instanceMain cell when disabled' );
		$this->assertStringContainsString( 'class="instanceAddAbove">', $disabledHtml, 'asserts that the add-above cell is present when disabled' );
		$this->assertStringNotContainsString( 'addAboveButton', $disabledHtml, 'asserts that no addAboveButton anchor is rendered when the form is disabled' );
		$this->assertStringContainsString( 'class="instanceRemove">', $disabledHtml, 'asserts that the remove cell is present when disabled' );
		$this->assertStringNotContainsString( 'removeButton', $disabledHtml, 'asserts that no removeButton anchor is rendered when the form is disabled' );
	}

	/**
	 * @covers \PFFormPrinter::multipleTemplateInstanceHTML
	 */
	public function testMultipleTemplateInstanceHTML() {
		global $wgPageFormsCalendarHTML;

		$pfFormPrinter = $this->getMockBuilder( PFFormPrinter::class )
			->onlyMethods( [ 'multipleTemplateInstanceTableHTML' ] )
			->getMock();

		// Stub multipleTemplateInstanceTableHTML so we can see that its output is embedded.
		$pfFormPrinter->method( 'multipleTemplateInstanceTableHTML' )
			->willReturn( '<table class="stubbedInstanceTable"></table>' );

		$mockTemplateInForm = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getTemplateName', 'getInstanceNum' ] )
			->getMock();
		$mockTemplateInForm->method( 'getTemplateName' )
			->willReturn( 'TestTemplate' );
		$mockTemplateInForm->method( 'getInstanceNum' )
			->willReturn( 3 );

		$section = '<div id="field_[num]">Content [num]</div>';

		$result = $pfFormPrinter->multipleTemplateInstanceHTML( $mockTemplateInForm, false, $section );

		// Calendar HTML should store the original section with [num] replaced by [cf]
		$this->assertArrayHasKey( 'TestTemplate', $wgPageFormsCalendarHTML, 'asserts that multipleTemplateInstanceHTML() populates wgPageFormsCalendarHTML for the template' );
		$this->assertSame(
			'<div id="field_[cf]">Content [cf]</div>',
			$wgPageFormsCalendarHTML['TestTemplate'],
			'asserts that [num] is replaced by [cf] in the calendar HTML'
		);

		// Section passed by reference should now have [num] replaced by the instance number plus "a"
		$this->assertStringContainsString( 'id="field_[3a]"', $section, 'asserts that [num] is replaced by the instance number and suffixed with "a" in IDs' );
		$this->assertStringContainsString( 'Content [3a]', $section, 'asserts that [num] is replaced by the instance number and suffixed with "a" in content' );

		// The original id is preserved in data-origID
		$this->assertStringContainsString( 'data-origID="field_[3a]"', $section, 'asserts that the original id is stored in data-origID' );

		// The returned HTML should wrap the stubbed table in the expected div with classes.
		$this->assertStringContainsString( 'class="multipleTemplateInstance multipleTemplate"', $result, 'asserts that multipleTemplateInstanceHTML() wraps content in the correct container div' );
		$this->assertStringContainsString( 'class="stubbedInstanceTable"', $result, 'asserts that the inner table HTML from multipleTemplateInstanceTableHTML() is embedded' );
	}

	/**
	 * @covers \PFFormPrinter::multipleTemplateEndHTML
	 */
	public function testMultipleTemplateEndHTMLEnabledAndDisabled() {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 5;

		$pfFormPrinter = $this->getMockBuilder( PFFormPrinter::class )
			->onlyMethods( [ 'multipleTemplateInstanceTableHTML' ] )
			->getMock();
		$pfFormPrinter->method( 'multipleTemplateInstanceTableHTML' )
			->willReturn( '<table class="stubbedInstanceTable"></table>' );

		$mockTemplateInForm = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getAddButtonText' ] )
			->getMock();
		$mockTemplateInForm->method( 'getAddButtonText' )
			->willReturn( 'Add another' );

		// Enabled form: button should not be disabled and should have the multipleTemplateAdder class.
		$enabledHtml = $pfFormPrinter->multipleTemplateEndHTML( $mockTemplateInForm, false, $section = '' );
		$this->assertStringContainsString( 'multipleTemplateStarter', $enabledHtml, 'asserts that multipleTemplateEndHTML() includes the starter div' );
		$this->assertStringContainsString( 'stubbedInstanceTable', $enabledHtml, 'asserts that the inner instance table HTML is included' );
		$this->assertStringContainsString( 'multipleTemplateAdder', $enabledHtml, 'asserts that the add button has the multipleTemplateAdder class when enabled' );
		$this->assertStringContainsString( 'Add another', $enabledHtml, 'asserts that the add button label is taken from getAddButtonText()' );

		// Disabled form: button should be disabled and have no multipleTemplateAdder class.
		$disabledHtml = $pfFormPrinter->multipleTemplateEndHTML( $mockTemplateInForm, true, $section = '' );
		$this->assertStringContainsString( 'multipleTemplateStarter', $disabledHtml, 'asserts that multipleTemplateEndHTML() includes the starter div when disabled' );
		$this->assertStringContainsString( 'stubbedInstanceTable', $disabledHtml, 'asserts that the inner instance table HTML is included when disabled' );
		$this->assertStringNotContainsString( 'multipleTemplateAdder', $disabledHtml, 'asserts that the multipleTemplateAdder class is removed when the form is disabled' );
	}

	private function createMockFormField( $fieldName, $inputType, $label ) {
		$mockTemplateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFieldName' ] )
			->getMock();
		$mockTemplateField->method( 'getFieldName' )
			->willReturn( $fieldName );

		$mockFormField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getTemplateField', 'getInputType', 'getLabel', 'getPossibleValues', 'getFieldArgs' ] )
			->getMock();
		$mockFormField->method( 'getTemplateField' )
			->willReturn( $mockTemplateField );
		$mockFormField->method( 'getInputType' )
			->willReturn( $inputType );
		$mockFormField->method( 'getLabel' )
			->willReturn( $label );
		$mockFormField->method( 'getPossibleValues' )
			->willReturn( [] );
		$mockFormField->method( 'getFieldArgs' )
			->willReturn( [] );

		return $mockFormField;
	}

	private function createAdvancedMockFormField(
		$fieldName,
		$inputType,
		$label,
		$possibleValues,
		$formFieldArgs,
		$defaultValue,
		$isList,
		$delimiter
	) {
		$mockTemplateField = $this->getMockBuilder( 'PFTemplateField' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFieldName' ] )
			->getMock();
		$mockTemplateField->method( 'getFieldName' )
			->willReturn( $fieldName );

		$methods = [
			'getTemplateField',
			'getInputType',
			'getLabel',
			'getPossibleValues',
			'getFieldArgs',
			'getDefaultValue',
			'isList',
			'getFieldArg'
		];

		$mockFormField = $this->getMockBuilder( 'PFFormField' )
			->disableOriginalConstructor()
			->onlyMethods( $methods )
			->getMock();
		$mockFormField->method( 'getTemplateField' )
			->willReturn( $mockTemplateField );
		$mockFormField->method( 'getInputType' )
			->willReturn( $inputType );
		$mockFormField->method( 'getLabel' )
			->willReturn( $label );
		$mockFormField->method( 'getPossibleValues' )
			->willReturn( $possibleValues );
		$mockFormField->method( 'getFieldArgs' )
			->willReturn( $formFieldArgs );
		$mockFormField->method( 'getDefaultValue' )
			->willReturn( $defaultValue );

		if ( $fieldName === 'valuesListField' ) {
			// For this field we want the outer isList() check (L581) to be false,
			// but the inner check inside the "values" branch (L589) to be true,
			// so that we reach L590-L591.
			$mockFormField->method( 'isList' )
				->willReturnOnConsecutiveCalls( false, true );
		} else {
			$mockFormField->method( 'isList' )
				->willReturn( $isList );
		}

		$mockFormField->method( 'getFieldArg' )
			->willReturnCallback( static function ( $name ) use ( $delimiter ) {
				if ( $name === 'delimiter' ) {
					return $delimiter;
				}
				return null;
			} );

		return $mockFormField;
	}

	/**
	 * @covers \PFFormPrinter::getSpreadsheetAutocompleteAttributes
	 */
	public function testGetSpreadsheetAutocompleteAttributes() {
		$pfFormPrinter = new PFFormPrinter();

		// Test for 'values from category'
		$formFieldArgs = [ 'values from category' => 'CategoryName' ];
		$expected = [ 'category', 'CategoryName' ];
		$result = $pfFormPrinter->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
		$this->assertEquals( $expected, $result, 'asserts that getSpreadsheetAutocompleteAttributes() returns the correct attributes for "values from category"' );

		// Test for 'cargo table'
		$formFieldArgs = [ 'cargo table' => 'TableName', 'cargo field' => 'FieldName' ];
		$expected = [ 'cargo field', 'TableName|FieldName' ];
		$result = $pfFormPrinter->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
		$this->assertEquals( $expected, $result, 'asserts that getSpreadsheetAutocompleteAttributes() returns the correct attributes for "cargo table"' );

		// Test for 'values from property'
		$formFieldArgs = [ 'values from property' => 'PropertyName' ];
		$expected = [ 'property', 'PropertyName' ];
		$result = $pfFormPrinter->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
		$this->assertEquals( $expected, $result, 'asserts that getSpreadsheetAutocompleteAttributes() returns the correct attributes for "values from property"' );

		// Test for 'values from concept'
		$formFieldArgs = [ 'values from concept' => 'ConceptName' ];
		$expected = [ 'concept', 'ConceptName' ];
		$result = $pfFormPrinter->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
		$this->assertEquals( $expected, $result, 'asserts that getSpreadsheetAutocompleteAttributes() returns the correct attributes for "values from concept"' );

		// Test for 'values dependent on'
		$formFieldArgs = [ 'values dependent on' => 'DependentField' ];
		$expected = [ 'dep_on', '' ];
		$result = $pfFormPrinter->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
		$this->assertEquals( $expected, $result, 'asserts that getSpreadsheetAutocompleteAttributes() returns the correct attributes for "values dependent on"' );

		// Test for 'values from external data'
		$formFieldArgs = [ 'values from external data' => 'ExternalData', 'origName' => 'OriginalName' ];
		$expected = [ 'external data', 'OriginalName' ];
		$result = $pfFormPrinter->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
		$this->assertEquals( $expected, $result, 'asserts that getSpreadsheetAutocompleteAttributes() returns the correct attributes for "values from external data"' );

		// Test for 'values from wikidata'
		$formFieldArgs = [ 'values from wikidata' => 'WikidataValue' ];
		$expected = [ 'wikidata', 'WikidataValue' ];
		$result = $pfFormPrinter->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
		$this->assertEquals( $expected, $result, 'asserts that getSpreadsheetAutocompleteAttributes() returns the correct attributes for "values from wikidata"' );

		// Test for no matching attribute
		$formFieldArgs = [];
		$expected = [ '', '' ];
		$result = $pfFormPrinter->getSpreadsheetAutocompleteAttributes( $formFieldArgs );
		$this->assertEquals( $expected, $result, 'asserts that getSpreadsheetAutocompleteAttributes() returns the correct attributes for no matching attribute' );
	}

	/**
	 * Data provider method for testFormHTML, providing various form definitions and expected outputs to test different branches of formHTML().
	 */
	public static function formHTMLDataProvider() {
		$provider = [];

		// #1 form definition with two fields
		$provider[] = [
			[
				'form_definition' => "==section1==\n{{{for template|template1}}}\n{{{field|field1}}}\n{{{field|field2}}}\n{{{end template}}}"
			],
			[
				'expected_form_text' => '<input id="input_1"',
				'expected_page_text' => '{{template1}}'
			]
		];

		// #2, #3, & #4 for template and end template on different lines or the same line. T377307.
		$provider[] = [
			[
				'form_definition' => "{{{for template|lorem}}}\n{{{end template}}}"
			],
			[
				'expected_form_text' => '',
				'expected_page_text' => '{{lorem}}'
			]
		];
		$provider[] = [
			[
				'form_definition' => "{{{for template|lorem}}}{{{end template}}}"
			],
			[
				'expected_form_text' => '',
				'expected_page_text' => '{{lorem}}'
			]
		];
		$provider[] = [
			[
				'form_definition' => "Foo{{{for template|lorem}}}{{{field|baz}}}{{{end template}}}Bar"
			],
			[
				'expected_form_text' => '<input id="input_1" tabindex="1" class="createboxInput" size="35" name="lorem[baz]">',
				'expected_page_text' => '{{lorem}}'
			]
		];

		// #5 page name empty, anon warning, and expand-all link.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TAnon}}}\n{{{field|field1}}}\n{{{end template}}}",
				'page_name' => '',
				'user' => MediaWikiServices::getInstance()->getWikiPageFactory()->newFromId( 0 ),
				'show_expand_all' => true
			],
			[
				'expected_form_text' => [ 'mw-anon-edit-warning', 'pf-expand-all' ],
				'expected_page_text' => '{{TAnon}}'
			]
		];

		// #5b extra closing braces and empty tag components.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TExtra}}}\n" .
					"{{{field|file|default filename=FileName}}}}}\n{{{end template}}}"
			],
			[
				'expected_form_text' => 'name="TExtra[file]"',
				'expected_page_text' => '{{TExtra}}'
			]
		];

		// #5c nested for template to exercise previous template tracking.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TOuter}}}{{{for template|TInner}}}{{{field|field1}}}{{{end template}}}{{{end template}}}"
			],
			[
				'expected_form_text' => 'name="TInner[field1]"',
				'expected_page_text' => '{{TInner}}'
			]
		];

		// #6 call showDeletionLog() when form is not submitted.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TDelete}}}\n{{{field|field1}}}\n{{{end template}}}",
				'form_submitted' => false,
				'page_name' => 'NewPageForDeletionLog'
			],
			[
				'expected_form_text' => '</form>',
				'expected_page_text' => '{{TDelete}}'
			]
		];

		// #7 use current user, defaults, page name formula, and mapping property.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TGen}}}\n" .
					"{{{field|field1}}}\n" .
					"{{{field|datefield|input type=date|default=now}}}\n" .
					"{{{field|userfield|default=current user}}}\n" .
					"{{{field|uuidfield|default=uuid}}}\n" .
					"{{{field|mapped|mapping property=SomeProperty}}}\n" .
					"{{{end template}}}",
				'page_name_formula' => '<TGen[field1]>',
				'request_values' => [
					'TGen' => [
						'field1' => 'GeneratedTitle',
						'mapped' => 'SomeValue'
					]
				],
				'user' => null,
				'context_user' => 'test_user'
			],
			[
				'expected_form_text' => 'name="TGen[field1]"',
				'expected_page_text' => '{{TGen',
				'expected_generated_page_name' => 'GeneratedTitle',
				'expected_page_text_regex' => '/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}/'
			]
		];

		// #8 cargo-based mapping when form is submitted.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TCargo}}}\n" .
					"{{{field|field1|mapping cargo table=Table1|mapping cargo field=Source|mapping cargo value field=Target}}}\n" .
					"{{{end template}}}",
				'define_cargo' => true,
				'cargo_results' => [
					'Target="Value A"' => [ [ 'Target' => 'Mapped A' ] ]
				],
				'request_values' => [
					'TCargo' => [
						'field1' => 'Value A'
					]
				]
			],
			[
				'expected_form_text' => 'name="TCargo[field1]"',
				'expected_page_text_regex' => '/\\{\\{TCargo\\s*\\|field1=Value A\\s*\\}\\}/'
			]
		];

		// #9 cargo-based mapping when form is not submitted.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TCargoView}}}\n" .
					"{{{field|field1|mapping cargo table=Table1|mapping cargo field=Source|mapping cargo value field=Target}}}\n" .
					"{{{end template}}}",
				'define_cargo' => true,
				'cargo_results' => [
					'Target="View A"' => [ [ 'Target' => 'Mapped View' ] ]
				],
				'form_submitted' => false,
				'request_values' => [
					'TCargoView' => [
						'field1' => 'View A'
					]
				]
			],
			[
				'expected_form_text' => 'name="TCargoView[field1]"',
				'expected_page_text_regex' => '/\\{\\{TCargoView\\s*\\|field1=View A\\s*\\}\\}/'
			]
		];

		// #11 read-only mode with permission status.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TReadOnly}}}\n{{{field|field1}}}\n{{{end template}}}",
				'read_only' => true,
				'user_can_edit_override' => false
			],
			[
				'expected_form_text' => '</form>',
				'expected_page_text' => '{{TReadOnly}}'
			]
		];

		// #12 info tag handling and free text input with edittools.
		$provider[] = [
			[
				'form_definition' => "{{{info|create title=Form{{!}}Title|includeonly free text}}}\n" .
					"{{{field|#freetext#|edittools}}}\n" .
					"{{{unknown|value}}}\n" .
					"{{{standard input|summary|label=Summary|class=SumClass|style=width: 10px;}}}\n" .
					"{{{standard input|minor edit|label=Minor|checked}}}\n" .
					"{{{standard input|watch|label=Watch}}}\n" .
					"{{{standard input|save|label=Save}}}\n" .
					"{{{standard input|save and continue|label=Continue}}}\n" .
					"{{{standard input|preview}}}\n" .
					"{{{standard input|changes}}}\n" .
					"{{{standard input|cancel}}}\n" .
					"{{{standard input|run query}}}\n",
				'request_values' => [
					'standard_input' => [
						'#freetext#' => '<onlyinclude>FreeText</onlyinclude>'
					],
					'wpSummary' => 'Summary text',
					'wpMinoredit' => '1',
					'wpWatchthis' => '1'
				]
			],
			[
				'expected_form_text' => [ 'mw-editTools', 'SumClass', 'Summary text' ],
				'expected_page_text' => 'FreeText',
				'expected_form_page_title' => 'Form|Title'
			]
		];

		// #13 query context without standard inputs to cover query form bottom.
		$provider[] = [
			[
				'form_definition' => "{{{info|query form at top}}}\n{{{for template|TQuery}}}\n{{{field|field1}}}\n{{{end template}}}",
				'form_context' => \PFFormPrinter::CONTEXT_QUERY
			],
			[
				'expected_form_text' => 'name="TQuery[field1]"',
				'expected_page_text' => '{{TQuery}}'
			]
		];

		// #14 embedded query context sets form page title to null.
		$provider[] = [
			[
				'form_definition' => "{{{info|query title=EmbeddedTitle}}}\n{{{for template|TEmbed}}}\n{{{field|field1}}}\n{{{end template}}}",
				'form_context' => \PFFormPrinter::CONTEXT_EMBEDDED_QUERY
			],
			[
				'expected_form_text' => 'name="TEmbed[field1]"',
				'expected_page_text' => '{{TEmbed}}',
				'expected_form_page_title' => [ null, '' ]
			]
		];

		// #15 forbidden characters in tag components should throw.
		$provider[] = [
			[
				'form_definition' => "{{{for template|Bad}}}\n{{{field|field1|label=<bad>}}}\n{{{end template}}}",
				'expected_exception' => \MWException::class,
				'expected_exception_message' => 'forbidden characters'
			],
			[]
		];

		// #16 missing template name should throw.
		$provider[] = [
			[
				'form_definition' => "{{{for template}}}\n{{{end template}}}",
				'expected_exception' => \MWException::class,
				'expected_exception_message' => 'template name must be specified'
			],
			[]
		];

		// #17 end template with extra parameters should throw.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TBadEnd}}}\n{{{end template|extra}}}",
				'expected_exception' => \MWException::class,
				'expected_exception_message' => 'end template'
			],
			[]
		];

		// #18 source page with insertionpoint replacement and + modifier.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TSource}}}\n{{{field|field1|list|delimiter=,}}}\n{{{end template}}}",
				'page_exists' => true,
				'existing_page_content' => "Start\n{{TSource|field1=Alpha, Beta}}\n{{{insertionpoint}}}\nEnd",
				'request_values' => [
					'TSource' => [
						'field1+' => 'Gamma'
					]
				]
			],
			[
				'expected_form_text' => 'name="TSource[field1]"',
				'expected_page_text_regex' => '/Alpha,\\s*Beta,\\s*Gamma/'
			]
		];

		// #19 source page with - modifier resulting in empty list.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TMinus}}}\n{{{field|field1|list|delimiter=,}}}\n{{{end template}}}",
				'page_exists' => true,
				'existing_page_content' => "{{TMinus|field1=RemoveMe}}",
				'request_values' => [
					'TMinus' => [
						'field1-' => 'RemoveMe'
					]
				]
			],
			[
				'expected_form_text' => 'name="TMinus[field1]"',
				'expected_page_text' => '{{subst:lc: }}'
			]
		];

		// #20 get value from existing page when form is not submitted and holds template.
		$provider[] = [
			[
				'form_definition' => "{{{for template|THold}}}\n{{{field|embed|holds template}}}\n{{{end template}}}",
				'page_exists' => true,
				'form_submitted' => false,
				'existing_page_content' => "{{THold|embed={{Inner|val=1}}}}"
			],
			[
				'expected_form_text' => 'name="THold[embed]"',
				'expected_page_text' => '{{THold'
			]
		];

		// #21 page sections with existing page content and hideIfEmpty handling.
		$provider[] = [
			[
				'form_definition' => "==Section A==\n{{{section|Section A|level=2}}}\n" .
					"==Section B==\n{{{section|Section B|level=2|hideIfEmpty}}}",
				'page_exists' => true,
				'existing_page_content' => "==Section A==\nContent A\n==Section B==\nContent B"
			],
			[
				'expected_form_text' => 'name="_section[Section A]"',
				'expected_page_text' => null
			]
		];

		// #22 multiple template displays and placeholder replacement.
		$provider[] = [
			[
				'form_definition' =>
					"{{{for template|Outer}}}\n" .
					"{{{field|inner|holds template}}}\n" .
					"{{{end template}}}\n" .
					"{{{for template|Inner|multiple|display=table|label=Inner Label}}}\n" .
					"{{{field|name}}}\n" .
					"{{{field|uuidfield|default=uuid}}}\n" .
					"{{{end template}}}\n" .
					"{{{for template|Sheet|multiple|display=spreadsheet|label=Sheet Label}}}\n" .
					"{{{field|sheetfield}}}\n" .
					"{{{end template}}}\n" .
					"{{{for template|Cal|multiple|display=calendar|label=Cal Label|event title field=title|event date field=date}}}\n" .
					"{{{field|title}}}\n" .
					"{{{field|date|input type=date}}}\n" .
					"{{{end template}}}\n" .
					"{{{for template|TableSingle|display=table|label=Table Label}}}\n" .
					"{{{field|tablefield}}}\n" .
					"{{{end template}}}\n" .
					"{{{for template|LabelOnly|label=Label Only}}}\n" .
					"{{{field|field1}}}\n" .
					"{{{end template}}}",
				'form_submitted' => true
			],
			[
				'expected_form_text' => [ 'multipleTemplateWrapper', 'pfFullCalendarJS', 'Table Label' ],
				'expected_page_text' => '{{Outer}}'
			]
		];

		// #23 multiple checkbox with source page to exercise checkbox coercion.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TMulti|multiple}}}\n{{{field|flag|input type=checkbox}}}\n{{{end template}}}",
				'page_exists' => true,
				'form_submitted' => false,
				'existing_page_content' => "{{TMulti|flag=yes}}"
			],
			[
				'expected_form_text_regex' => '/TMulti\\[[^\\]]+\\]\\[flag\\]/',
				'expected_page_text' => '{{TMulti'
			]
		];

		// #24 free text from request when no free-text input is defined.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TFree}}}\n{{{field|field1}}}\n{{{end template}}}",
				'request_values' => [
					'pf_free_text' => 'Submitted free text'
				]
			],
			[
				'expected_form_text' => 'pf_free_text',
				'expected_page_text' => 'Submitted free text'
			]
		];

		// #25 warning when existing page does not match form.
		$provider[] = [
			[
				'form_definition' => "{{{info|edit title=EditTitle}}}\n{{{for template|TWarn}}}\n{{{field|field1}}}\n{{{end template}}}",
				'page_exists' => true,
				'create_page' => 'FormWarningPage',
				'page_name' => 'FormWarningPage',
				'existing_page_content' => "Existing content not matching template"
			],
			[
				'expected_form_text' => 'cdx-message--warning',
				'expected_page_text' => '{{TWarn}}',
				'expected_form_page_title' => 'EditTitle'
			]
		];

		// #26 autocreate context uses values from autocreate query.
		$provider[] = [
			[
				'form_definition' => "{{{for template|TAuto}}}\n{{{field|field1}}}\n{{{end template}}}",
				'form_context' => \PFFormPrinter::CONTEXT_AUTOCREATE,
				'autocreate_query' => [
					'TAuto' => [
						'field1' => 'AutoValue'
					]
				]
			],
			[
				'expected_form_text' => 'AutoValue',
				'expected_page_text_regex' => '/\\{\\{TAuto\\s*\\|field1=AutoValue\\s*\\}\\}/'
			]
		];

		// #27 section values from request when not using source page.
		$provider[] = [
			[
				'form_definition' => "==Section X==\n{{{section|Section X|level=2}}}",
				'request_values' => [
					'_section' => [ 'Section X' => 'Section content' ]
				]
			],
			[
				'expected_form_text' => 'Section content',
				'expected_page_text' => '==Section X=='
			]
		];

		// #28 run query button when standard input is used in query context.
		$provider[] = [
			[
				'form_definition' => "{{{standard input|run query|label=Run}}}\n{{{for template|TQueryRun}}}\n{{{field|field1}}}\n{{{end template}}}",
				'form_context' => \PFFormPrinter::CONTEXT_QUERY
			],
			[
				'expected_form_text' => 'wpRunQuery',
				'expected_page_text' => '{{TQueryRun}}'
			]
		];

		return $provider;
	}

	/**
	 * @covers \PFFormPrinter::getStringForCurrentTime
	 */
	public function testGetStringForCurrentTime() {
		global $wgPageFormsFormPrinter;

		$this->setMwGlobals( [
			'wgAmericanDates' => false,
			'wgPageForms24HourTime' => false
		] );
		$currentDateOnly = $wgPageFormsFormPrinter->getStringForCurrentTime(
			$includeTime = false,
			$includeTimezone = false
		);
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{1,2}-\d{1,2}$/',
			$currentDateOnly,
			'asserts that getStringForCurrentTime() returns only the date when time is excluded'
		);

		$this->setMwGlobals( [ 'wgAmericanDates' => true ] );
		$currentAmericanDateOnly = $wgPageFormsFormPrinter->getStringForCurrentTime(
			$includeTime = false,
			$includeTimezone = false
		);
		$monthNamesPattern = implode(
			'|',
			array_map( static fn ( $monthName ) => preg_quote( $monthName, '/' ), \PFFormUtils::getMonthNames() )
		);
		$this->assertMatchesRegularExpression(
			'/^(' . $monthNamesPattern . ') \d{1,2}, \d{4}$/',
			$currentAmericanDateOnly,
			'asserts that getStringForCurrentTime() returns an American-style date when configured'
		);

		$this->setMwGlobals( [
			'wgAmericanDates' => false,
			'wgPageForms24HourTime' => true
		] );
		$current24HourTime = $wgPageFormsFormPrinter->getStringForCurrentTime(
			$includeTime = true,
			$includeTimezone = false
		);
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{1,2}-\d{1,2} \d{2}:\d{2}:\d{2}$/',
			$current24HourTime,
			'asserts that getStringForCurrentTime() returns the time in 24-hour format when configured'
		);

		$this->setMwGlobals( [ 'wgPageForms24HourTime' => false ] );
		$currentTimeWithTimezone = $wgPageFormsFormPrinter->getStringForCurrentTime(
			$includeTime = true,
			$includeTimezone = true
		);
		$this->assertMatchesRegularExpression(
			'/\d{4}-\d{1,2}-\d{1,2} \d{2}:\d{2}:\d{2} (AM|PM) [A-Z]{3}/',
			$currentTimeWithTimezone,
			'asserts that getStringForCurrentTime() returns the correct time string with timezone'
		);
	}

	/**
	 * Data provider method
	 */
	public static function pageSectionDataProvider() {
		$provider = [];

		// #1 form definition without other parameters
		$provider[] = [
			[
				'form_definition' => "==section1==
								 {{{section|section1|level=2}}}"
			],
			[
				'expected_form_text' => "<span class=\"inputSpan pageSection\"><textarea tabindex=\"1\" name=\"_section[section1]\" id=\"input_1\" class=\"createboxInput\" rows=\"5\" cols=\"90\" style=\"width: 100%\"></textarea></span>",
				'expected_page_text' => "==section1=="
			]
		];

		// #2 'rows' and 'colums' parameters set
		$provider[] = [
			[
				'form_definition' => "=====section 2=====
								 {{{section|section 2|level=5|rows=10|cols=5}}}"
			],
			[
				'expected_form_text' => "<span class=\"inputSpan pageSection\"><textarea tabindex=\"1\" name=\"_section[section 2]\" id=\"input_1\" class=\"createboxInput\" rows=\"10\" cols=\"5\" style=\"width: auto\"></textarea></span>",
				'expected_page_text' => "=====section 2====="
			]
		];

		// #3 'mandatory' and 'autogrow' parameters set
		$provider[] = [
			[
				'form_definition' => "==section 3==
								 {{{section|section 3|level=2|mandatory|rows=20|cols=50|autogrow}}}"
			],
			[
				'expected_form_text' => "<span class=\"inputSpan pageSection mandatoryFieldSpan\"><textarea tabindex=\"1\" name=\"_section[section 3]\" id=\"input_1\" class=\"mandatoryField autoGrow\" rows=\"20\" cols=\"50\" style=\"width: auto\"></textarea></span>",
				'expected_page_text' => "==section 3=="
			]
		];

		// #4 'restricted' parameter set
		$provider[] = [
			[
				'form_definition' => "===Section 5===
								 {{{section|Section 5|level=3|restricted|class=FormTest}}}"
			],
			[
				'expected_form_text' => "<span class=\"inputSpan pageSection\"><textarea tabindex=\"1\" name=\"_section[Section 5]\" id=\"input_1\" class=\"createboxInput FormTest\" rows=\"5\" cols=\"90\" style=\"width: 100%\" disabled=\"\"></textarea></span>",
				'expected_page_text' => "===Section 5==="
			]
		];

		// #5 'hidden' parameter set
		$provider[] = [
			[
				'form_definition' => "====section 4====
								 {{{section|section 4|level=4|hidden}}}"
			],
			[
				'expected_form_text' => "<input type=\"hidden\" name=\"_section[section 4]\">",
				'expected_page_text' => "====section 4===="
			]
		];

		return $provider;
	}

	/**
	 * Returns a mock Title for test
	 * @return Title
	 */
	private function getTitle() {
		$mockTitle = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->getMock();

		$mockTitle->expects( $this->any() )
			->method( 'getDBkey' )
			->willReturn( 'Sometitle' );

		$mockTitle->expects( $this->any() )
			->method( 'getNamespace' )
			->willReturn( PF_NS_FORM );

		return $mockTitle;
	}

	/**
	 * Data provider method
	 */
	public function getDefaultInputTypeSMWDataProvider(): array {
		return [
			'single_found' => [ false, 'Text', 'textbox' ],
			'single_not_found' => [ false, 'NonExistentType', null ],
			'list_found' => [ true, 'Text', 'textarea' ],
			'list_not_found' => [ true, 'NonExistentType', null ],
		];
	}

	/**
	 * @dataProvider getDefaultInputTypeSMWDataProvider
	 */
	public function testGetDefaultInputTypeSMW( bool $isList, string $propertyType, ?string $expected ) {
		$pfFormPrinter = new \PFFormPrinter();
		$this->setPrivateProperty( $pfFormPrinter, 'mDefaultInputForPropType', [
			'Text' => 'textbox',
			'Number' => 'number',
		] );
		$this->setPrivateProperty( $pfFormPrinter, 'mDefaultInputForPropTypeList', [
			'Text' => 'textarea',
		] );

		$this->assertEquals( $expected, $pfFormPrinter->getDefaultInputTypeSMW( $isList, $propertyType ) );
	}

	/**
	 * Data provider method
	 */
	public function getDefaultInputTypeCargoDataProvider(): array {
		return [
			'single_found' => [ false, 'String', 'text' ],
			'single_not_found' => [ false, 'NonExistentType', null ],
			'list_found' => [ true, 'String', 'text' ],
			'list_not_found' => [ true, 'NonExistentType', null ],
		];
	}

	/**
	 * @dataProvider getDefaultInputTypeCargoDataProvider
	 */
	public function testGetDefaultInputTypeCargo( bool $isList, string $fieldType, ?string $expected ) {
		$pfFormPrinter = new \PFFormPrinter();

		$this->setPrivateProperty( $pfFormPrinter, 'mDefaultInputForCargoType', [
			'String' => 'text',
			'Integer' => 'int',
		] );
		$this->setPrivateProperty( $pfFormPrinter, 'mDefaultInputForCargoTypeList', [
			'String' => 'text',
		] );

		$this->assertEquals( $expected, $pfFormPrinter->getDefaultInputTypeCargo( $isList, $fieldType ) );
	}

	/**
	 * Data provider method
	 */
	public function getPossibleInputTypesSMWDataProvider(): array {
		return [
			'single_found' => [ false, 'Text', [ 'textbox', 'textarea', 'text' ] ],
			'single_not_found' => [ false, 'NonExistentType', [] ],
			'list_found' => [ true, 'Text', [ 'textarea', 'text', 'textbox' ] ],
			'list_not_found' => [ true, 'NonExistentType', [] ],
		];
	}

	/**
	 * @dataProvider getPossibleInputTypesSMWDataProvider
	 */
	public function testGetPossibleInputTypesSMW( bool $isList, string $propertyType, array $expected ) {
		$pfFormPrinter = new \PFFormPrinter();

		$this->setPrivateProperty( $pfFormPrinter, 'mPossibleInputsForPropType', [
			'Text' => [ 'textbox', 'textarea', 'text' ],
			'Number' => [ 'number', 'spinner' ],
		] );
		$this->setPrivateProperty( $pfFormPrinter, 'mPossibleInputsForPropTypeList', [
			'Text' => [ 'textarea', 'text', 'textbox' ],
		] );

		$this->assertEquals( $expected, $pfFormPrinter->getPossibleInputTypesSMW( $isList, $propertyType ) );
	}

	/**
	 * Data provider method
	 */
	public function getPossibleInputTypesCargoDataProvider(): array {
		return [
			'single_found' => [ false, 'String', [ 'text with autocomplete', 'textarea with autocomplete', 'combobox', 'tree', 'tokens' ] ],
			'single_not_found' => [ false, 'NonExistentType', [] ],
			'list_found' => [ true, 'String', [ 'tree', 'tokens' ] ],
			'list_not_found' => [ true, 'NonExistentType', [] ],
		];
	}

	/**
	 * @dataProvider getPossibleInputTypesCargoDataProvider
	 */
	public function testGetPossibleInputTypesCargo( bool $isList, string $fieldType, array $expected ) {
		$pfFormPrinter = new \PFFormPrinter();

		$this->setPrivateProperty( $pfFormPrinter, 'mPossibleInputsForCargoType', [
			'String' => [ 'text with autocomplete', 'textarea with autocomplete', 'combobox', 'tree', 'tokens' ],
			'Integer' => [ 'int', 'number' ],
		] );
		$this->setPrivateProperty( $pfFormPrinter, 'mPossibleInputsForCargoTypeList', [
			'String' => [ 'tree', 'tokens' ],
		] );

		$this->assertEquals( $expected, $pfFormPrinter->getPossibleInputTypesCargo( $isList, $fieldType ) );
	}

	/**
	 * @param \PFFormPrinter $pfFormPrinter
	 * @param string $propertyName
	 * @param mixed $value
	 * @return void
	 */
	private function setPrivateProperty( \PFFormPrinter $pfFormPrinter, string $propertyName, $value ): void {
		$reflection = new \ReflectionClass( get_class( $pfFormPrinter ) );
		$property = $reflection->getProperty( $propertyName );
		$property->setAccessible( true );
		$property->setValue( $pfFormPrinter, $value );
	}
}
// phpcs:enable Generic.Files.OneObjectStructurePerFile.MultipleFound
