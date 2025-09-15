<?php

use MediaWiki\Title\Title;
use OOUI\BlankTheme;

/**
 * @covers \PFFormPrinter
 * @group Database
 * @author Himeshi De Silva
 */
class PFFormPrinterTest extends MediaWikiIntegrationTestCase {

	/**
	 * Set up the environment
	 */
	protected function setUp(): void {
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
				PFFormPrinter::CONTEXT_REGULAR,
				$autocreate_query = [],
				$user = self::getTestUser()->getUser()
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

		$wgOut->getContext()->setTitle( $this->getTitle() );

		[ $form_text, $page_text, $form_page_title, $generated_page_name ] =
			$wgPageFormsFormPrinter->formHTML(
				$form_def = $setup['form_definition'],
				$form_submitted = true,
				$source_is_page = false,
				$form_id = null,
				$existing_page_content = null,
				$page_name = 'TestFormGenerationPage',
				$page_name_formula = null,
				PFFormPrinter::CONTEXT_REGULAR,
				$autocreate_query = [],
				$user = self::getTestUser()->getUser()
			);

		$this->assertStringContainsString(
			$expected['expected_form_text'],
			$form_text,
			'asserts that formHTML() generates the correct HTML form'
		);
		$this->assertStringContainsString(
			$expected['expected_page_text'],
			$page_text,
			'asserts that formHTML() generates the correct page text'
		);
		$this->assertSame(
			'',
			$form_page_title,
			'asserts that formHTML() generates the correct form page title'
		);
		$this->assertSame(
			'',
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
		$result = PFFormPrinter::makePlaceholderInFormHTML( $placeholder );
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
		$result = PFFormPrinter::placeholderFormat( $templateName, $fieldName );
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
		$pfFormPrinter = new PFFormPrinter();
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
				$this->createMockFormField( 'field1', 'text', 'Field 1' ),
				$this->createMockFormField( 'field2', 'date', 'Field 2' ),
				$this->createMockFormField( 'field3', 'checkbox', 'Field 3' )
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
	 * Data provider method for testFormHTML
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

		// #2 & #3 for template and end template on different lines or the same line.
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

		return $provider;
	}

	/**
	 * @covers \PFFormPrinter::getStringForCurrentTime
	 */
	public function testGetStringForCurrentTime() {
		global $wgPageFormsFormPrinter;

		$includeTime = true;
		$includeTimezone = true;

		$current_time_string = $wgPageFormsFormPrinter->getStringForCurrentTime( $includeTime, $includeTimezone );

		$this->assertMatchesRegularExpression(
			'/\d{4}-\d{1,2}-\d{1,2} \d{2}:\d{2}:\d{2} (AM|PM) [A-Z]{3}/',
			$current_time_string,
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
								 {{{section|section1|level=2}}}" ],
		[
			'expected_form_text' => "<span class=\"inputSpan pageSection\"><textarea tabindex=\"1\" name=\"_section[section1]\" id=\"input_1\" class=\"createboxInput\" rows=\"5\" cols=\"90\" style=\"width: 100%\"></textarea></span>",
			'expected_page_text' => "==section1==" ]
		];

		// #2 'rows' and 'colums' parameters set
		$provider[] = [
		[
			'form_definition' => "=====section 2=====
								 {{{section|section 2|level=5|rows=10|cols=5}}}" ],
		[
			'expected_form_text' => "<span class=\"inputSpan pageSection\"><textarea tabindex=\"1\" name=\"_section[section 2]\" id=\"input_1\" class=\"createboxInput\" rows=\"10\" cols=\"5\" style=\"width: auto\"></textarea></span>",
			'expected_page_text' => "=====section 2=====" ]
		];

		// #3 'mandatory' and 'autogrow' parameters set
		$provider[] = [
		[
			'form_definition' => "==section 3==
								 {{{section|section 3|level=2|mandatory|rows=20|cols=50|autogrow}}}" ],
		[
			'expected_form_text' => "<span class=\"inputSpan pageSection mandatoryFieldSpan\"><textarea tabindex=\"1\" name=\"_section[section 3]\" id=\"input_1\" class=\"mandatoryField autoGrow\" rows=\"20\" cols=\"50\" style=\"width: auto\"></textarea></span>",
			'expected_page_text' => "==section 3==" ]
		];

		// #4 'restricted' parameter set
		$provider[] = [
		[
			'form_definition' => "===Section 5===
								 {{{section|Section 5|level=3|restricted|class=FormTest}}}" ],
		[
			'expected_form_text' => "<span class=\"inputSpan pageSection\"><textarea tabindex=\"1\" name=\"_section[Section 5]\" id=\"input_1\" class=\"createboxInput FormTest\" rows=\"5\" cols=\"90\" style=\"width: 100%\" disabled=\"\"></textarea></span>",
			'expected_page_text' => "===Section 5===" ]
		];

		// #5 'hidden' parameter set
		$provider[] = [
		[
			'form_definition' => "====section 4====
								 {{{section|section 4|level=4|hidden}}}" ],
		[
			'expected_form_text' => "<input type=\"hidden\" name=\"_section[section 4]\">",
			'expected_page_text' => "====section 4====" ]
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

}
