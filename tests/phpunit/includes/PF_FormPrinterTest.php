<?php

use OOUI\BlankTheme;

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	// MW pre-1.34
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * @covers \PFFormPrinter
 *
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
		Hooks::register( 'PageForms::UserCanEditPage', static function ( $pageTitle, &$userCanEditPage ) {
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

		list( $form_text, $page_text, $form_page_title, $generated_page_name ) =
			$wgPageFormsFormPrinter->formHTML(
				$form_def = $setup['form_definition'],
				$form_submitted = true,
				$source_is_page = false,
				$form_id = null,
				$existing_page_content = null,
				$page_name = 'TestStringForFormPageTitle',
				$page_name_formula = null,
				$is_query = false,
				$is_embedded = false,
				$is_autocreate = false,
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
	 * Data provider method
	 */
	public function pageSectionDataProvider() {
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
			'expected_form_text' => "<input type=\"hidden\" name=\"_section[section 4]\"/>",
			'expected_page_text' => "====section 4====" ]
		];

		return $provider;
	}

	/**
	 * Returns a mock Title for test
	 * @return Title
	 */
	private function getTitle() {
		$mockTitle = $this->getMockBuilder( 'Title' )
			->disableOriginalConstructor()
			->getMock();

		$mockTitle->expects( $this->any() )
			->method( 'getDBkey' )
			->will( $this->returnValue( 'Sometitle' ) );

		$mockTitle->expects( $this->any() )
			->method( 'getNamespace' )
			->will( $this->returnValue( PF_NS_FORM ) );

		return $mockTitle;
	}

}
