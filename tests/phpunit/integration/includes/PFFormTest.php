<?php

namespace PageForms\Tests\Integration\Includes;

use MediaWikiIntegrationTestCase;

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * @covers \PFForm
 *
 * @author Wandji Collins
 */
class PFFormTest extends MediaWikiIntegrationTestCase {
	private $pfForm;

	/**
	 * Set up environment for testing PFForm, including defining necessary namespaces and creating a PFForm instance.
	 */
	protected function setUp(): void {
		$this->pfForm = new \PFForm();
		parent::setUp();
		$this->setMwGlobals( [
			'wgExtraNamespaces' => [
				PF_NS_FORM => 'Form',
				PF_NS_FORM_TALK => 'Form_talk'
			]
		] );
	}

	/**
	 * @covers \PFForm::getFormName
	 */
	public function testGetFormName() {
		$actual = $this->pfForm->getFormName();
		$this->assertTrue( (bool)equalTo( $actual ) );
		$this->assertEquals( $actual, $this->pfForm->getFormName() );
	}

	/**
	 * @covers \PFForm::getItems
	 */
	public function testGetItems() {
		$actual = $this->pfForm->getItems();
		$this->assertTrue( (bool)equalTo( $actual ) );
		$this->assertEquals( $actual, $this->pfForm->getItems() );
	}

	/**
	 * @covers \PFForm::setPageNameFormula
	 */
	public function testSetPageNameFormula() {
		$actual = $this->pfForm->setPageNameFormula( 'formula' );
		$this->assertTrue( (bool)equalTo( $actual ) );
		$this->assertEquals( $actual, $this->pfForm->setPageNameFormula( 'formula' ) );
	}

	/**
	 * @covers \PFForm::setCreateTitle
	 */
	public function testSetCreateTitle() {
		$actual = $this->pfForm->setCreateTitle( 'create title' );
		$this->assertTrue( (bool)equalTo( $actual ) );
		$this->assertEquals( $actual, $this->pfForm->setCreateTitle( 'create title' ) );
	}

	/**
	 * @covers  \PFForm::setEditTitle
	 */
	public function testSetEditTitle() {
		$editTitle = $this->pfForm->setEditTitle( 'edit title' );
		$this->assertTrue( (bool)equalTo( $editTitle ) );
		$this->assertEquals( $editTitle, $this->pfForm->setEditTitle( 'edit title' ) );
	}

	/**
	 * @covers  \PFForm::setAssociatedCategory
	 */
	public function testSetAssociatedCategory() {
		$associatedCategory = $this->pfForm->setAssociatedCategory( 'mediawiki category' );
		$this->assertTrue( (bool)equalTo( $associatedCategory ) );
		$this->assertEquals( $associatedCategory, $this->pfForm->setAssociatedCategory( 'mediawiki category' ) );
	}

	/**
	 * @covers \PFForm::createMarkup
	 */
	public function testCreateMarkup_basic() {
		$formName = 'TestForm';
		$items = [];

		$form = \PFForm::create( $formName, $items );

		$actualMarkup = $form->createMarkup();

		// We are testing the generated wikitext, not the final HTML.
		$this->assertStringContainsString( '{{#forminput:form=TestForm}}', $actualMarkup, 'Markup should contain a forminput parser function' );

		$fs = \PFUtils::getSpecialPage( 'FormStart' );
		$formStartUrl = \PFUtils::titleURLString( $fs->getPageTitle() ) . "/TestForm";
		$formDescription = wfMessage( 'pf_form_docu', 'TestForm', $formStartUrl )->inContentLanguage()->text();
		$freeTextLabel = wfMessage( 'pf_form_freetextlabel' )->inContentLanguage()->text();

		$expectedMarkup = <<<END
		<noinclude>
		$formDescription

		{{#forminput:form=TestForm}}

		</noinclude><includeonly>
		<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
		'''$freeTextLabel:'''

		{{{standard input|free text|rows=10}}}
		</includeonly>

		END;

		$this->assertEquals( $expectedMarkup, $actualMarkup, "Basic form markup should be generated correctly." );
	}

	/**
	 * @covers \PFForm::createMarkup
	 */
	public function testCreateMarkup_withAllInfoAndNoFreeText() {
		$formName = 'Complex Form';
		$items = [];
		$form = \PFForm::create( $formName, $items );

		$form->setAssociatedCategory( 'TestCategory' );
		$form->setPageNameFormula( 'Test-{{#rand:1,100}}' );
		$form->setCreateTitle( 'Create a new Test' );
		$form->setEditTitle( 'Edit a Test' );

		// Disable free text
		$actualMarkup = $form->createMarkup( false );

		$fs = \PFUtils::getSpecialPage( 'FormStart' );
		$formStartUrl = \PFUtils::titleURLString( $fs->getPageTitle() ) . "/Complex_Form";
		$formDescription = wfMessage( 'pf_form_docu', 'Complex Form', $formStartUrl )->inContentLanguage()->text();

		$expectedMarkup = <<<END
		<noinclude>
		$formDescription

		{{#forminput:form=Complex Form|autocomplete on category=TestCategory}}

		</noinclude><includeonly>
		{{{info|page name=Test-{{#rand:1,100}}|create title=Create a new Test|edit title=Edit a Test}}}
		<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
		</includeonly>

		END;

		$this->assertEquals( $expectedMarkup, $actualMarkup, "Markup with info fields and no free text should be correct." );
	}

	/**
	 * @covers \PFForm::createMarkup
	 */
	public function testCreateMarkup_withItems() {
		$formName = 'AnotherTestFormWithItems';
		$expectedItemMarkup = '{{MyTemplate|field={{{field|}}} }}';

		$templateMock = $this->createMock( \PFTemplateInForm::class );
		$templateMock->method( 'createMarkup' )->willReturn( $expectedItemMarkup );

		$items = [];
		$items[] = [
			'type' => 'template',
			'item' => $templateMock,

		];
		$items[] = [
			'type' => 'section',
			'item' => $templateMock
		];

		$form = \PFForm::create( $formName, $items );
		// Mock a template item
		$actualMarkup = $form->createMarkup();

		// Disable free text
		$actualMarkup = $form->createMarkup( false );

		$this->assertStringContainsString(
			$expectedItemMarkup,
			$actualMarkup,
			"Markup should contain the markup from its items."
		);
	}

	public function testCreate() {
		$formName = 'New Form';
		$expectedItemMarkup = '{{MyTemplate|field={{{field|}}} }}';

		$templateMock = $this->createMock( \PFTemplateInForm::class );
		$templateMock->method( 'createMarkup' )->willReturn( $expectedItemMarkup );

		$items = [];
		$items[] = [
			'type' => 'template',
			'item' => $templateMock
		];
		$items[] = [
			'type' => 'section',
			'item' => $templateMock
		];

		$form = \PFForm::create( $formName, $items );
		// $actualMarkup = $form->getFormName();

		$this->assertEquals( $expectedItemMarkup, $form->getItems()[0]['item']->createMarkup(), true, true, "The form should create items correctly." );
		$this->assertStringContainsString( $formName, $form->getFormName() );
	}

}
