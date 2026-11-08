<?php

use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Request\FauxRequest;
use MediaWiki\StubObject\StubObject;
use OOUI\BlankTheme;

/**
 * @covers \PFFormUtils
 * @covers \PFFormPrinter
 * @covers \PFFormLink
 * @covers \PFAutoEdit
 * @group Database
 * @group PageForms
 */
class PageFormsOOUITest extends MediaWikiIntegrationTestCase {

	private const CHECKBOX_FORM = <<<'WIKI'
{{{for template|TCheck}}}
{{{field|done|input type=checkbox}}}
{{{end template}}}
WIKI;

	protected function setUp(): void {
		parent::setUp();
		$this->getServiceContainer()->getHookContainer()->register(
			'PageForms::UserCanEditPage',
			static function ( $pageTitle, &$userCanEditPage ) {
				$userCanEditPage = true;
				return true;
			}
		);
	}

	protected function tearDown(): void {
		// Leave PHP OOUI initialized so a later test's BlankTheme is not
		// replaced by the next setupOOUI() call.
		$this->markOOUIInitialized();
		\OOUI\Theme::setSingleton( new BlankTheme() );
		parent::tearDown();
	}

	/**
	 * @covers \PFFormUtils::enableOOUIForParser
	 */
	public function testEnableOOUIForParserMarksOutputAndConfiguresPhpTheme(): void {
		$parser = $this->getServiceContainer()->getParser();
		$output = $parser->parse(
			'PageForms OOUI setup',
			$this->getNonexistingTestPage()->getTitle(),
			ParserOptions::newFromAnon()
		);
		$this->markOOUIUninitialized();

		\PFFormUtils::enableOOUIForParser( $parser );

		$this->assertTrue( $output->getOutputFlag( ParserOutputFlags::ENABLE_OOUI ) );
		$this->assertNotInstanceOf( BlankTheme::class, \OOUI\Theme::singleton() );
	}

	public function testFormLinkButtonEnablesOOUI(): void {
		$output = $this->parse(
			'{{#formlink:form=PageFormsOOUIForm|link text=Edit|link type=button}}'
		);

		$this->assertTrue( $output->getOutputFlag( ParserOutputFlags::ENABLE_OOUI ) );
		$this->assertStringContainsString( 'oo-ui-buttonInputWidget', $output->getContentHolderText() );
	}

	public function testFormLinkTextDoesNotEnableOOUI(): void {
		$output = $this->parse(
			'{{#formlink:form=PageFormsOOUIForm|link text=Edit}}'
		);

		$this->assertFalse( $output->getOutputFlag( ParserOutputFlags::ENABLE_OOUI ) );
		$this->assertStringNotContainsString( 'oo-ui-buttonInputWidget', $output->getContentHolderText() );
	}

	public function testAutoEditButtonEnablesOOUI(): void {
		$output = $this->parse(
			'{{#autoedit:target=PageFormsOOUITarget|link text=Save|link type=button}}'
		);

		$this->assertTrue( $output->getOutputFlag( ParserOutputFlags::ENABLE_OOUI ) );
		$this->assertStringContainsString( 'oo-ui-buttonWidget', $output->getContentHolderText() );
	}

	/**
	 * @covers \PFFormPrinter::formHTML
	 */
	public function testDisplayedFormEnablesOOUIStylesAndRendersCheckbox(): void {
		$context = $this->newFormContext();

		[ $formText ] = $this->formPrinter()->formHTML(
			$context,
			self::CHECKBOX_FORM,
			false,
			false,
			null,
			null,
			'PageFormsOOUIDisplayed',
			null,
			\PFFormPrinter::CONTEXT_REGULAR
		);

		$this->assertContains( 'oojs-ui-core.styles', $context->getOutput()->getModuleStyles() );
		$this->assertStringContainsString( 'oo-ui-checkboxInputWidget', $formText );
	}

	/**
	 * @covers \PFFormPrinter::formHTML
	 */
	public function testAutocreateConfiguresPhpOOUIWithoutAddingStyleModules(): void {
		$this->markOOUIUninitialized();
		$context = $this->newFormContext();

		[ $formText ] = $this->formPrinter()->formHTML(
			$context,
			self::CHECKBOX_FORM,
			false,
			false,
			null,
			null,
			'PageFormsOOUIAutocreate',
			null,
			\PFFormPrinter::CONTEXT_AUTOCREATE,
			[ 'TCheck' => [ 'done' => 'yes' ] ]
		);

		$this->assertNotContains( 'oojs-ui-core.styles', $context->getOutput()->getModuleStyles() );
		$this->assertStringContainsString( 'oo-ui-checkboxInputWidget', $formText );
		$this->assertNotInstanceOf( BlankTheme::class, \OOUI\Theme::singleton() );
	}

	private function parse( string $wikitext ): ParserOutput {
		return $this->getServiceContainer()->getParser()->parse(
			$wikitext,
			$this->getNonexistingTestPage()->getTitle(),
			ParserOptions::newFromAnon()
		);
	}

	private function newFormContext(): RequestContext {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );
		$context->setTitle( $this->getExistingTestPage()->getTitle() );
		$context->setUser( $this->getTestUser()->getUser() );
		$context->setOutput( new OutputPage( $context ) );
		return $context;
	}

	private function formPrinter(): PFFormPrinter {
		global $wgPageFormsFormPrinter;
		if ( !StubObject::isRealObject( $wgPageFormsFormPrinter ) ) {
			$wgPageFormsFormPrinter = $wgPageFormsFormPrinter->_unstub();
		}
		return $wgPageFormsFormPrinter;
	}

	private function markOOUIUninitialized(): void {
		$done = new ReflectionProperty( OutputPage::class, 'oouiSetupDone' );
		$done->setValue( null, false );
		\OOUI\Theme::setSingleton( new BlankTheme() );
	}

	private function markOOUIInitialized(): void {
		$done = new ReflectionProperty( OutputPage::class, 'oouiSetupDone' );
		$done->setValue( null, true );
	}
}
