<?php

use MediaWiki\Content\ContentHandler;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;

/**
 * @covers \PFFormUtils
 * @group PageForms
 * @group PF
 * @group Database
 * @author Collins Wandji <collinschuwa@gmail.com>
 */
class PFFormUtilsTest extends MediaWikiIntegrationTestCase {

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

		$this->setMwGlobals( [
			'wgPageFormsTabIndex'             => 0,
			'wgTitle'                         => null,
			'wgPageFormsCacheFormDefinitions' => false,
			'wgPageFormsShowOnSelect'         => [],
		] );

		parent::setUp();
	}

	private function createPage( string $prefixedText, string $content = 'Page content' ): Title {
		$title = Title::newFromText( $prefixedText );
		$this->assertInstanceOf( Title::class, $title );

		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$wikiPage->doUserEditContent(
			ContentHandler::makeContent( $content, $title ),
			self::getTestUser()->getUser(),
			'Create page for \PFFormUtils integration test',
			0,
			false
		);
		return $title;
	}

	/**
	 * @covers \PFFormUtils::getChangedIndex
	 */
	public function testGetChangedIndexWithNullsReturnsSameIndex(): void {
		$this->assertSame( 5, \PFFormUtils::getChangedIndex( 5, null, null ) );
		$this->assertSame( 0, \PFFormUtils::getChangedIndex( 0, null, null ) );
	}

	/**
	 * @covers \PFFormUtils::getChangedIndex
	 */
	public function testGetChangedIndexWithNewItemAboveCurrentDecrements(): void {
		// inserted at position 2; current index is 4 (> 2) → old_i = 4 - 1 = 3
		$this->assertSame( 3, \PFFormUtils::getChangedIndex( 4, 2, null ) );
	}

	/**
	 * @covers \PFFormUtils::getChangedIndex
	 */
	public function testGetChangedIndexAtExactNewItemLocReturnsMinusOne(): void {
		// current index == new_item_loc → it is the newly inserted item → old_i = -1
		$this->assertSame( -1, \PFFormUtils::getChangedIndex( 3, 3, null ) );
	}

	/**
	 * @covers \PFFormUtils::getChangedIndex
	 */
	public function testGetChangedIndexWithNewItemBelowCurrentUnchanged(): void {
		// inserted at position 5; current index is 3 (< 5) → old_i = 3
		$this->assertSame( 3, \PFFormUtils::getChangedIndex( 3, 5, null ) );
	}

	/**
	 * @covers \PFFormUtils::getChangedIndex
	 */
	public function testGetChangedIndexWithDeletedItemAtOrAboveCurrentIncrements(): void {
		// deleted at position 2; current index is 2 (>= 2) → old_i = 2 + 1 = 3
		$this->assertSame( 3, \PFFormUtils::getChangedIndex( 2, null, 2 ) );
		// deleted at  3; current is 4 → old_i = 5
		$this->assertSame( 5, \PFFormUtils::getChangedIndex( 4, null, 3 ) );
	}

	/**
	 * @covers \PFFormUtils::getChangedIndex
	 */
	public function testGetChangedIndexWithDeletedItemBelowCurrentUnchanged(): void {
		// deleted at position 5; current index is 3 (< 5) → old_i = 3
		$this->assertSame( 3, \PFFormUtils::getChangedIndex( 3, null, 5 ) );
	}

	/**
	 * @covers \PFFormUtils::getMonthNames
	 */
	public function testGetMonthNamesReturnsExactlyTwelveNonEmptyStrings(): void {
		$months = \PFFormUtils::getMonthNames();

		$this->assertCount( 12, $months );
		foreach ( $months as $i => $name ) {
			$this->assertIsString( $name, "Month at index $i should be a string" );
			$this->assertNotSame( '', trim( $name ), "Month at index $i should not be empty" );
		}
	}

	/**
	 * @covers \PFFormUtils::setGlobalVarsForSpreadsheet
	 */
	public function testSetGlobalVarsForSpreadsheetSetsYesNoAndMonths(): void {
		global $wgPageFormsContLangYes, $wgPageFormsContLangNo, $wgPageFormsContLangMonths;

		\PFFormUtils::setGlobalVarsForSpreadsheet();

		$this->assertIsString( $wgPageFormsContLangYes );
		$this->assertNotSame( '', $wgPageFormsContLangYes );

		$this->assertIsString( $wgPageFormsContLangNo );
		$this->assertNotSame( '', $wgPageFormsContLangNo );

		$this->assertIsArray( $wgPageFormsContLangMonths );
		// Index 0 is a blank placeholder; indices 1-12 are month names.
		$this->assertCount( 13, $wgPageFormsContLangMonths );
		$this->assertSame( '', $wgPageFormsContLangMonths[0] );
		for ( $i = 1; $i <= 12; $i++ ) {
			$this->assertNotSame( '', trim( $wgPageFormsContLangMonths[$i] ), "Month $i should not be empty" );
		}
	}

	/**
	 * @covers \PFFormUtils::headerHTML
	 */
	public function testHeaderHTMLDefaultLevelIsH2(): void {
		$result = \PFFormUtils::headerHTML( 'My Section' );

		$this->assertStringContainsString( '<h2>', $result );
		$this->assertStringContainsString( 'My Section', $result );
		$this->assertStringContainsString( '</h2>', $result );
	}

	/**
	 * @covers \PFFormUtils::headerHTML
	 */
	public function testHeaderHTMLRespectLevelParam(): void {
		$this->assertStringContainsString( '<h3>', \PFFormUtils::headerHTML( 'Section', 3 ) );
		$this->assertStringContainsString( '<h4>', \PFFormUtils::headerHTML( 'Section', 4 ) );
	}

	/**
	 * @covers \PFFormUtils::headerHTML
	 */
	public function testHeaderHTMLCapsAtH6(): void {
		// Level > 6 must be clamped to 6
		$result = \PFFormUtils::headerHTML( 'Deep', 99 );
		$this->assertStringContainsString( '<h6>', $result );
		$this->assertStringNotContainsString( '<h7>', $result );
	}

	/**
	 * @covers \PFFormUtils::headerHTML
	 */
	public function testHeaderHTMLNonNumericLevelDefaultsToH2(): void {
		$result = \PFFormUtils::headerHTML( 'Level Test', 'invalid' );
		$this->assertStringContainsString( '<h2>', $result );
	}

	/**
	 * @covers \PFFormUtils::headerHTML
	 */
	public function testHeaderHTMLIncrementsTabIndex(): void {
		global $wgPageFormsTabIndex;
		$before = $wgPageFormsTabIndex;
		\PFFormUtils::headerHTML( 'Tab' );
		$this->assertSame( $before + 1, $wgPageFormsTabIndex );
	}

	/**
	 * @covers \PFFormUtils::saveButtonHTML
	 */
	public function testSaveButtonHTMLIncrementsTabIndexAndReturnsWidget(): void {
		global $wgPageFormsTabIndex;
		$before = $wgPageFormsTabIndex;

		$result = \PFFormUtils::saveButtonHTML( false );

		$this->assertInstanceOf( \OOUI\ButtonInputWidget::class, $result );
		$this->assertSame( $before + 1, $wgPageFormsTabIndex );
	}

	/**
	 * @covers \PFFormUtils::saveButtonHTML
	 */
	public function testSaveButtonHTMLDisabledFlagPropagatesToWidget(): void {
		$result = \PFFormUtils::saveButtonHTML( true );
		// Serialise to HTML and check for the disabled attribute
		$html = (string)$result;
		$this->assertStringContainsString( 'disabled', $html );
	}

	/**
	 * @covers \PFFormUtils::saveButtonHTML
	 */
	public function testSaveButtonHTMLCustomLabelOverridesDefault(): void {
		$result = \PFFormUtils::saveButtonHTML( false, 'Publish Now' );
		$html = (string)$result;
		$this->assertStringContainsString( 'Publish Now', $html );
	}

	/**
	 * @covers \PFFormUtils::saveAndContinueButtonHTML
	 */
	public function testSaveAndContinueButtonHTMLHasCorrectClassWhenDisabled(): void {
		$result = \PFFormUtils::saveAndContinueButtonHTML( true );
		$html = (string)$result;
		$this->assertStringContainsString( 'pf-save_and_continue', $html );
		$this->assertStringContainsString( 'disabled', $html );
	}

	/**
	 * @covers \PFFormUtils::saveAndContinueButtonHTML
	 */
	public function testSaveAndContinueButtonHTMLHasCorrectClassWhenEnabled(): void {
		$result = \PFFormUtils::saveAndContinueButtonHTML( false );
		$html = (string)$result;
		$this->assertStringContainsString( 'pf-save_and_continue', $html );
	}

	/**
	 * @covers \PFFormUtils::showPreviewButtonHTML
	 */
	public function testShowPreviewButtonHTMLIncrementsTabIndexAndReturnsWidget(): void {
		global $wgPageFormsTabIndex;
		$before = $wgPageFormsTabIndex;

		$result = \PFFormUtils::showPreviewButtonHTML( false );

		$this->assertInstanceOf( \OOUI\ButtonInputWidget::class, $result );
		$this->assertSame( $before + 1, $wgPageFormsTabIndex );
	}

	/**
	 * @covers \PFFormUtils::showPreviewButtonHTML
	 */
	public function testShowPreviewButtonHTMLDisabledFlag(): void {
		$html = (string)\PFFormUtils::showPreviewButtonHTML( true );
		$this->assertStringContainsString( 'disabled', $html );
	}

	/**
	 * @covers \PFFormUtils::showChangesButtonHTML
	 */
	public function testShowChangesButtonHTMLIncrementsTabIndexAndReturnsWidget(): void {
		global $wgPageFormsTabIndex;
		$before = $wgPageFormsTabIndex;

		$result = \PFFormUtils::showChangesButtonHTML( false );

		$this->assertInstanceOf( \OOUI\ButtonInputWidget::class, $result );
		$this->assertSame( $before + 1, $wgPageFormsTabIndex );
	}

	/**
	 * @covers \PFFormUtils::showChangesButtonHTML
	 */
	public function testShowChangesButtonHTMLDisabledFlag(): void {
		$html = (string)\PFFormUtils::showChangesButtonHTML( true );
		$this->assertStringContainsString( 'disabled', $html );
	}

	/**
	 * @covers \PFFormUtils::cancelLinkHTML
	 */
	public function testCancelLinkHTMLWithNullTitleUsesPfSendBackClass(): void {
		$this->setMwGlobals( [ 'wgTitle' => null ] );

		$result = \PFFormUtils::cancelLinkHTML( false );

		$this->assertStringContainsString( 'pfSendBack', $result );
	}

	/**
	 * @covers \PFFormUtils::cancelLinkHTML
	 */
	public function testCancelLinkHTMLWithFormEditSpecialPageUsesPfSendBackClass(): void {
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'Special:FormEdit' ) ] );

		$result = \PFFormUtils::cancelLinkHTML( false );

		$this->assertStringContainsString( 'pfSendBack', $result );
	}

	/**
	 * @covers \PFFormUtils::cancelLinkHTML
	 */
	public function testCancelLinkHTMLWithNonFormEditPageIncludesHref(): void {
		$title = Title::newFromText( 'SomePage' );
		$this->setMwGlobals( [ 'wgTitle' => $title ] );

		$result = \PFFormUtils::cancelLinkHTML( false );

		$this->assertStringContainsString( 'href', $result );
	}

	/**
	 * @covers \PFFormUtils::cancelLinkHTML
	 */
	public function testCancelLinkHTMLCustomLabelAppearsInOutput(): void {
		$result = \PFFormUtils::cancelLinkHTML( false, 'Go Back' );
		$this->assertStringContainsString( 'Go Back', $result );
	}

	/**
	 * @covers \PFFormUtils::cancelLinkHTML
	 */
	public function testCancelLinkHTMLCustomClassAppearsInOutput(): void {
		$result = \PFFormUtils::cancelLinkHTML( false, null, [ 'class' => 'my-cancel-class' ] );
		$this->assertStringContainsString( 'my-cancel-class', $result );
	}

	/**
	 * @covers \PFFormUtils::runQueryButtonHTML
	 */
	public function testRunQueryButtonHTMLReturnsFieldLayoutAndIncrementsTabIndex(): void {
		global $wgPageFormsTabIndex;
		$before = $wgPageFormsTabIndex;

		$result = \PFFormUtils::runQueryButtonHTML( false );

		$this->assertInstanceOf( \OOUI\FieldLayout::class, $result );
		$this->assertSame( $before + 1, $wgPageFormsTabIndex );
	}

	/**
	 * @covers \PFFormUtils::queryFormBottom
	 */
	public function testQueryFormBottomReturnsFieldLayoutFromRunQueryButton(): void {
		global $wgPageFormsTabIndex;
		$before = $wgPageFormsTabIndex;

		$result = \PFFormUtils::queryFormBottom();
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'oo-ui-layout', $result );
		$this->assertStringContainsString( 'oo-ui-fieldLayout', $result );
		$this->assertSame( $before + 1, $wgPageFormsTabIndex );
	}

	/**
	 * @covers \PFFormUtils::summaryInputHTML
	 */
	public function testSummaryInputHTMLReturnsFieldLayoutAndIncrementsTabIndex(): void {
		global $wgPageFormsTabIndex;
		$before = $wgPageFormsTabIndex;

		$result = \PFFormUtils::summaryInputHTML( false );

		$this->assertInstanceOf( \OOUI\FieldLayout::class, $result );
		$this->assertSame( $before + 1, $wgPageFormsTabIndex );
	}

	/**
	 * @covers \PFFormUtils::summaryInputHTML
	 */
	public function testSummaryInputHTMLDisabledFlagAppearsInOutput(): void {
		$result = \PFFormUtils::summaryInputHTML( true );
		$html = (string)$result;
		$this->assertStringContainsString( 'disabled', $html );
	}

	/**
	 * @covers \PFFormUtils::summaryInputHTML
	 */
	public function testSummaryInputHTMLCustomLabelAppearsInOutput(): void {
		$result = \PFFormUtils::summaryInputHTML( false, 'Edit summary' );
		$html = (string)$result;
		$this->assertStringContainsString( 'Edit summary', $html );
	}

	/**
	 * @covers \PFFormUtils::summaryInputHTML
	 */
	public function testSummaryInputHTMLValueAppearsInOutput(): void {
		$result = \PFFormUtils::summaryInputHTML( false, null, [], 'previous summary text' );
		$html = (string)$result;
		$this->assertStringContainsString( 'previous summary text', $html );
	}

	/**
	 * @covers \PFFormUtils::summaryInputHTML
	 */
	public function testSummaryInputHTMLCustomClassIsConvertedToOouiClasses(): void {
		$result = \PFFormUtils::summaryInputHTML( false, null, [ 'class' => 'my-summary-class' ] );

		$this->assertStringContainsString( 'my-summary-class', (string)$result );
	}

	/**
	 * @covers \PFFormUtils::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTMLWithNullTemplateReturnsEmptyString(): void {
		$this->assertSame( '', \PFFormUtils::unhandledFieldsHTML( null ) );
	}

	/**
	 * @covers \PFFormUtils::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTMLWithNormalKeyRendersHiddenInput(): void {
		$mockTemplate = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->getMock();
		$mockTemplate->method( 'getTemplateName' )->willReturn( 'My Template' );
		$mockTemplate->method( 'getValuesFromPage' )->willReturn( [ 'City' => 'Berlin' ] );

		$result = \PFFormUtils::unhandledFieldsHTML( $mockTemplate );

		$this->assertStringContainsString( 'type="hidden"', $result );
		$this->assertStringContainsString( '_unhandled_My_Template_City', $result );
		$this->assertStringContainsString( 'Berlin', $result );
	}

	/**
	 * @covers \PFFormUtils::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTMLSkipsNumericKeys(): void {
		$mockTemplate = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->getMock();
		$mockTemplate->method( 'getTemplateName' )->willReturn( 'T' );
		$mockTemplate->method( 'getValuesFromPage' )->willReturn( [
			0      => 'zero-indexed value',
			1      => 'one-indexed value',
			'Name' => 'Alice',
		] );

		$result = \PFFormUtils::unhandledFieldsHTML( $mockTemplate );

		// Named key 'Name' produces a hidden input; numeric keys are skipped.
		$this->assertStringContainsString( 'Alice', $result );
		$this->assertStringNotContainsString( 'zero-indexed value', $result );
		$this->assertStringNotContainsString( 'one-indexed value', $result );
	}

	/**
	 * @covers \PFFormUtils::unhandledFieldsHTML
	 *
	 * PHP silently coerces null array keys to '' (empty string) before the
	 * foreach iterates, so the `$key === null` guard in unhandledFieldsHTML()
	 * is unreachable at runtime. A null key therefore passes through and
	 * generates a hidden input with an empty-string URL-encoded key.
	 * This test documents that actual behaviour.
	 */
	public function testUnhandledFieldsHTMLNullKeyIsCoercedToEmptyStringByPHP(): void {
		$mockTemplate = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->getMock();
		$mockTemplate->method( 'getTemplateName' )->willReturn( 'T' );
		// PHP converts [ null => 'v' ] → [ '' => 'v' ] before iteration
		$mockTemplate->method( 'getValuesFromPage' )->willReturn( [ null => 'coerced-empty-key' ] );

		$result = \PFFormUtils::unhandledFieldsHTML( $mockTemplate );

		// '' is not numeric and not === null so it is NOT skipped;
		// the value is rendered in a hidden input.
		$this->assertStringContainsString( 'coerced-empty-key', $result );
		$this->assertStringContainsString( '_unhandled_T_', $result );
	}

	/**
	 * @covers \PFFormUtils::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTMLSkipsBlankValuesWhenAutoedit(): void {
		$mockTemplate = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->getMock();
		$mockTemplate->method( 'getTemplateName' )->willReturn( 'T' );
		$mockTemplate->method( 'getValuesFromPage' )->willReturn( [
			'City'    => '',
			'Country' => 'Germany',
		] );

		$result = \PFFormUtils::unhandledFieldsHTML( $mockTemplate, true );

		// Blank value skipped when $is_autoedit=true
		$this->assertStringNotContainsString( '_unhandled_T_City', $result );
		$this->assertStringContainsString( '_unhandled_T_Country', $result );
	}

	/**
	 * @covers \PFFormUtils::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTMLDoesNotSkipBlankValuesWhenNotAutoedit(): void {
		$mockTemplate = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->getMock();
		$mockTemplate->method( 'getTemplateName' )->willReturn( 'T' );
		$mockTemplate->method( 'getValuesFromPage' )->willReturn( [ 'City' => '' ] );

		$result = \PFFormUtils::unhandledFieldsHTML( $mockTemplate, false );

		// Blank value is kept when $is_autoedit=false
		$this->assertStringContainsString( '_unhandled_T_City', $result );
	}

	/**
	 * @covers \PFFormUtils::unhandledFieldsHTML
	 */
	public function testUnhandledFieldsHTMLUrlEncodesKeyInInputName(): void {
		$mockTemplate = $this->getMockBuilder( 'PFTemplateInForm' )
			->disableOriginalConstructor()
			->getMock();
		$mockTemplate->method( 'getTemplateName' )->willReturn( 'T' );
		// A key with a space — urlencode() converts space to '+'
		$mockTemplate->method( 'getValuesFromPage' )->willReturn( [ 'My Field' => 'value' ] );

		$result = \PFFormUtils::unhandledFieldsHTML( $mockTemplate );

		// URL-encoded key: space → '+' (or %20 depending on urlencode)
		$this->assertStringContainsString( 'My', $result );
		$this->assertStringNotContainsString( '_unhandled_T_My Field', $result );
	}

	/**
	 * @covers \PFFormUtils::getPreloadedText
	 */
	public function testGetPreloadedTextWithEmptyStringReturnsEmpty(): void {
		$this->assertSame( '', \PFFormUtils::getPreloadedText( '' ) );
	}

	/**
	 * @covers \PFFormUtils::getPreloadedText
	 */
	public function testGetPreloadedTextWithInvalidTitleReturnsEmpty(): void {
		$this->assertSame( '', \PFFormUtils::getPreloadedText( '<invalid>' ) );
	}

	/**
	 * @covers \PFFormUtils::getPreloadedText
	 */
	public function testGetPreloadedTextWithNoReadPermissionReturnsEmpty(): void {
		$this->createPage( 'PFPreloadNoReadPage', '' );

		$user = self::getTestUser()->getUser();
		\RequestContext::getMain()->setUser( $user );
		$this->overrideUserPermissions( $user, [] );

		$this->assertSame( '', \PFFormUtils::getPreloadedText( 'PFPreloadNoReadPage' ) );
	}

	/**
	 * @covers \PFFormUtils::getPreloadedText
	 */
	public function testGetPreloadedTextReturnsPageTextStrippingNoinclude(): void {
		$content = 'Visible content<noinclude>Hidden section</noinclude> after';
		$this->createPage( 'PFPreloadSourcePage', $content );

		$result = \PFFormUtils::getPreloadedText( 'PFPreloadSourcePage' );

		$this->assertStringContainsString( 'Visible content', $result );
		$this->assertStringNotContainsString( 'Hidden section', $result );
		$this->assertStringNotContainsString( '<noinclude>', $result );
	}

	/**
	 * @covers \PFFormUtils::getPreloadedText
	 */
	public function testGetPreloadedTextStripsIncludeonlyTags(): void {
		$content = '<includeonly>wrap start</includeonly>Body<includeonly>wrap end</includeonly>';
		$this->createPage( 'PFPreloadIncludeOnlyPage', $content );

		$result = \PFFormUtils::getPreloadedText( 'PFPreloadIncludeOnlyPage' );

		$this->assertStringContainsString( 'Body', $result );
		$this->assertStringNotContainsString( '<includeonly>', $result );
		$this->assertStringNotContainsString( '</includeonly>', $result );
	}

	/**
	 * @covers \PFFormUtils::getFormCache
	 */
	public function testGetFormCacheReturnsBagOStuff(): void {
		$cache = \PFFormUtils::getFormCache();
		$this->assertInstanceOf( \BagOStuff::class, $cache );
	}

	/**
	 * @covers \PFFormUtils::getCacheKey
	 */
	public function testGetCacheKeyWithoutParserContainsFormId(): void {
		$key = \PFFormUtils::getCacheKey( '42' );

		$this->assertIsString( $key );
		$this->assertStringContainsString( '42', $key );
		$this->assertStringContainsString( 'formdefinition', $key );
	}

	/**
	 * @covers \PFFormUtils::getCacheKey
	 */
	public function testGetCacheKeyWithParserDiffersFromKeylessForm(): void {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->setOptions( \ParserOptions::newFromAnon() );

		$keyWithout = \PFFormUtils::getCacheKey( '7' );
		$keyWith    = \PFFormUtils::getCacheKey( '7', $parser );

		$this->assertNotSame( $keyWithout, $keyWith );
	}

	/**
	 * @covers \PFFormUtils::getFormDefinition
	 */
	public function testGetFormDefinitionReturnsEmptyWhenNoDefAndNoId(): void {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->setOptions( \ParserOptions::newFromAnon() );
		$parser->setTitle( Title::newFromText( 'Dummy' ) );

		$this->assertSame( '', \PFFormUtils::getFormDefinition( $parser, null, null ) );
	}

	/**
	 * @covers \PFFormUtils::getFormDefinition
	 */
	public function testGetFormDefinitionWithProvidedDefStripsNoincludeAndIncludeonly(): void {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$title = Title::newFromText( 'Dummy' );
		$parser->startExternalParse( $title, \ParserOptions::newFromAnon(), \Parser::OT_HTML );

		$formDef = 'Visible<noinclude>Hidden</noinclude><includeonly>Wrap</includeonly>End';
		$result = \PFFormUtils::getFormDefinition( $parser, $formDef );

		$this->assertStringContainsString( 'Visible', $result );
		$this->assertStringNotContainsString( 'Hidden', $result );
		$this->assertStringNotContainsString( '<includeonly>', $result );
		$this->assertStringContainsString( 'Wrap', $result );
		$this->assertStringContainsString( 'End', $result );
	}

	/**
	 * @covers \PFFormUtils::getFormDefinition
	 */
	public function testGetFormDefinitionLoadsFromFormIdWhenNoDefProvided(): void {
		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
		$title = $this->createPage( 'Form:PFTestFormDef', 'Form body text' );
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->startExternalParse( $title, \ParserOptions::newFromAnon(), \Parser::OT_HTML );

		$result = \PFFormUtils::getFormDefinition( $parser, null, $title->getArticleID() );

		$this->assertStringContainsString( 'Form body text', $result );
	}

	/**
	 * @covers \PFFormUtils::getFormDefinition
	 */
	public function testGetFormDefinitionPreservesTripleBraceTags(): void {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$title = Title::newFromText( 'Dummy' );
		$parser->startExternalParse( $title, \ParserOptions::newFromAnon(), \Parser::OT_HTML );

		$formDef = 'Before {{{field|name}}} After';
		$result = \PFFormUtils::getFormDefinition( $parser, $formDef );

		$this->assertStringContainsString( '{{{field|name}}}', $result );
		$this->assertStringContainsString( 'Before', $result );
		$this->assertStringContainsString( 'After', $result );
	}

	/**
	 * @covers \PFFormUtils::getFormDefinitionFromCache
	 */
	public function testGetFormDefinitionFromCacheReturnsNullWhenCachingDisabled(): void {
		$this->setMwGlobals( [ 'wgPageFormsCacheFormDefinitions' => false ] );

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
		$title = $this->createPage( 'Form:PFCacheDisabledForm', 'Cache disabled body' );
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->startExternalParse( $title, \ParserOptions::newFromAnon(), \Parser::OT_HTML );

		$result1 = \PFFormUtils::getFormDefinition( $parser, null, $title->getArticleID() );
		$this->assertStringContainsString( 'Cache disabled body', $result1 );

		$parser2 = $this->getServiceContainer()->getParserFactory()->create();
		$parser2->startExternalParse( $title, \ParserOptions::newFromAnon(), \Parser::OT_HTML );
		$result2 = \PFFormUtils::getFormDefinition( $parser2, null, $title->getArticleID() );
		$this->assertStringContainsString( 'Cache disabled body', $result2 );
	}

	/**
	 * @covers \PFFormUtils::getFormDefinitionFromCache
	 * @covers \PFFormUtils::cacheFormDefinition
	 */
	public function testGetFormDefinitionFromCacheReturnsCachedValueOnSecondCall(): void {
		$this->setMwGlobals( [ 'wgPageFormsCacheFormDefinitions' => true ] );

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
		$title = $this->createPage( 'Form:PFCacheHitForm', 'Cached form body' );
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->startExternalParse( $title, \ParserOptions::newFromAnon(), \Parser::OT_HTML );

		$result1 = \PFFormUtils::getFormDefinition( $parser, null, $title->getArticleID() );
		$this->assertStringContainsString( 'Cached form body', $result1 );

		$parser2 = $this->getServiceContainer()->getParserFactory()->create();
		$parser2->startExternalParse( $title, \ParserOptions::newFromAnon(), \Parser::OT_HTML );
		$result2 = \PFFormUtils::getFormDefinition( $parser2, null, $title->getArticleID() );
		$this->assertStringContainsString( 'Cached form body', $result2 );
		$this->assertSame( $result1, $result2 );
	}

	/**
	 * @covers \PFFormUtils::cacheFormDefinition
	 */
	public function testCacheFormDefinitionDoesNotStoreWhenCachingDisabled(): void {
		$this->setMwGlobals( [ 'wgPageFormsCacheFormDefinitions' => false ] );

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
		$title = $this->createPage( 'Form:PFCacheSkipForm', 'Skip cache body' );
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->startExternalParse( $title, \ParserOptions::newFromAnon(), \Parser::OT_HTML );

		\PFFormUtils::getFormDefinition( $parser, null, $title->getArticleID() );

		$cache = \PFFormUtils::getFormCache();
		$cacheKey = \PFFormUtils::getCacheKey( (string)$title->getArticleID(), $parser );
		$this->assertFalse( $cache->get( $cacheKey ), 'Cache should not store when caching is disabled' );
	}

	/**
	 * @covers \PFFormUtils::cacheFormDefinition
	 */
	public function testCacheFormDefinitionStoresDefinitionAndKeyListWhenEnabled(): void {
		$this->setMwGlobals( [ 'wgPageFormsCacheFormDefinitions' => true ] );

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
		$title = $this->createPage( 'Form:PFCacheStoreForm', 'Stored form body' );
		$formId = (string)$title->getArticleID();
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->startExternalParse( $title, \ParserOptions::newFromAnon(), \Parser::OT_HTML );

		\PFFormUtils::getFormDefinition( $parser, null, $title->getArticleID() );

		$cache = \PFFormUtils::getFormCache();
		$cacheKeyForForm = \PFFormUtils::getCacheKey( $formId, $parser );
		$cacheKeyForList = \PFFormUtils::getCacheKey( $formId );

		$cachedDef = $cache->get( $cacheKeyForForm );
		$this->assertIsString( $cachedDef, 'Cached form definition should be a string' );
		$this->assertStringContainsString( 'Stored form body', $cachedDef );

		$keyList = $cache->get( $cacheKeyForList );
		$this->assertIsArray( $keyList, 'Cache key list should be an array' );
		$this->assertArrayHasKey( $cacheKeyForForm, $keyList );
	}

	/**
	 * @covers \PFFormUtils::getFormDefinition
	 */
	public function testGetFormDefinitionPurgesCacheWhenParserOutputDisablesCaching(): void {
		$this->setMwGlobals( [ 'wgPageFormsCacheFormDefinitions' => true ] );

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
		$title = $this->createPage( 'Form:PFCacheDisabledDuringParse', 'Original body' );
		$formId = (string)$title->getArticleID();

		$cache = \PFFormUtils::getFormCache();
		$cacheKeyForForm = \PFFormUtils::getCacheKey( $formId );
		$cacheKeyForList = \PFFormUtils::getCacheKey( $formId );
		$cache->set( $cacheKeyForForm, 'stale form definition' );
		$cache->set( $cacheKeyForList, [ $cacheKeyForForm => $cacheKeyForForm ] );

		$parserOptions = \ParserOptions::newFromAnon();
		$parserOutput = $this->getMockBuilder( \ParserOutput::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'runOutputPipeline', 'getContentHolderText', 'getCacheTime' ] )
			->getMock();
		$parserOutput->method( 'runOutputPipeline' )->willReturnSelf();
		$parserOutput->method( 'getContentHolderText' )->willReturn( 'Parsed form definition' );
		$parserOutput->method( 'getCacheTime' )->willReturn( -1 );

		$parser = $this->getMockBuilder( \Parser::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getTitle', 'getOptions', 'parse' ] )
			->getMock();
		$parser->method( 'getTitle' )->willReturn( $title );
		$parser->method( 'getOptions' )->willReturn( $parserOptions );
		$parser->method( 'parse' )->willReturn( $parserOutput );

		$result = \PFFormUtils::getFormDefinition( $parser, 'Visible form body', $formId );

		$this->assertSame( 'Parsed form definition', $result );
		$this->assertFalse( $cache->get( $cacheKeyForForm ), 'Cached definition should be purged' );
		$this->assertFalse( $cache->get( $cacheKeyForList ), 'Cache key list should be purged' );
	}

	/**
	 * @covers \PFFormUtils::purgeCache
	 */
	public function testPurgeCacheReturnsTrueForNonFormNamespacePage(): void {
		$title    = $this->createPage( 'PFPurgeCacheTestPage' );
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );

		// Pages not in PF_NS_FORM should return true immediately without touching cache.
		$result = \PFFormUtils::purgeCache( $wikiPage );

		$this->assertTrue( $result );
	}

	/**
	 * @covers \PFFormUtils::purgeCache
	 */
	public function testPurgeCacheReturnsTrueForFormPageWithNoCachedEntries(): void {
		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
		$title = $this->createPage( 'Form:PFPurgeNoCacheForm', 'No cache content' );
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );

		$result = \PFFormUtils::purgeCache( $wikiPage );

		$this->assertTrue( $result );
	}

	/**
	 * @covers \PFFormUtils::purgeCache
	 */
	public function testPurgeCacheDeletesCachedDefinitionAndKeyList(): void {
		$this->setMwGlobals( [ 'wgPageFormsCacheFormDefinitions' => true ] );

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
		$title = $this->createPage( 'Form:PFPurgeCacheForm', 'Purgeable form body' );
		$formId = (string)$title->getArticleID();

		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->startExternalParse( $title, \ParserOptions::newFromAnon(), \Parser::OT_HTML );
		\PFFormUtils::getFormDefinition( $parser, null, $title->getArticleID() );

		$cache = \PFFormUtils::getFormCache();
		$cacheKeyForForm = \PFFormUtils::getCacheKey( $formId, $parser );
		$cacheKeyForList = \PFFormUtils::getCacheKey( $formId );

		// Confirm cache is populated before purge
		$this->assertIsString( $cache->get( $cacheKeyForForm ) );
		$this->assertIsArray( $cache->get( $cacheKeyForList ) );

		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$result = \PFFormUtils::purgeCache( $wikiPage );

		$this->assertTrue( $result );
		$this->assertFalse( $cache->get( $cacheKeyForForm ), 'Cached definition should be deleted' );
		$this->assertFalse( $cache->get( $cacheKeyForList ), 'Key list should be deleted' );
	}

	/**
	 * @covers \PFFormUtils::purgeCacheOnSave
	 */
	public function testPurgeCacheOnSaveReturnsResultOfPurgingResolvedWikiPage(): void {
		$this->setMwGlobals( [ 'wgPageFormsCacheFormDefinitions' => true ] );

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}
		$title = $this->createPage( 'Form:PFPurgeOnSaveForm', 'Body for purge on save' );
		$formId = (string)$title->getArticleID();

		$cache = \PFFormUtils::getFormCache();
		$cacheKeyForForm = \PFFormUtils::getCacheKey( $formId );
		$cacheKeyForList = \PFFormUtils::getCacheKey( $formId );
		$cache->set( $cacheKeyForForm, 'cached form definition' );
		$cache->set( $cacheKeyForList, [ $cacheKeyForForm => $cacheKeyForForm ] );

		$revisionRecord = $this->createMock( \MediaWiki\Revision\RevisionRecord::class );
		$revisionRecord->method( 'getPageId' )->willReturn( (int)$formId );

		$renderedRevision = $this->createMock( \MediaWiki\Revision\RenderedRevision::class );
		$renderedRevision->method( 'getRevision' )->willReturn( $revisionRecord );

		$result = \PFFormUtils::purgeCacheOnSave( $renderedRevision );

		$this->assertTrue( $result );
		$this->assertFalse( $cache->get( $cacheKeyForForm ), 'Cached form definition should be deleted' );
		$this->assertFalse( $cache->get( $cacheKeyForList ), 'Cached key list should be deleted' );
	}

	/**
	 * @covers \PFFormUtils::minorEditInputHTML
	 */
	public function testMinorEditInputHTMLIncrementsTabIndex(): void {
		global $wgPageFormsTabIndex;
		$before = $wgPageFormsTabIndex;

		\PFFormUtils::minorEditInputHTML( true, false, false );

		$this->assertSame( $before + 1, $wgPageFormsTabIndex );
	}

	/**
	 * @covers \PFFormUtils::minorEditInputHTML
	 */
	public function testMinorEditInputHTMLContainsCheckboxAndLabel(): void {
		$html = \PFFormUtils::minorEditInputHTML( true, false, false );

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'wpMinoredit', $html );
		$this->assertStringContainsString( '<label', $html );
		$this->assertStringContainsString( '<div', $html );
	}

	/**
	 * @covers \PFFormUtils::minorEditInputHTML
	 */
	public function testMinorEditInputHTMLCheckedWhenIsCheckedTrue(): void {
		$html = \PFFormUtils::minorEditInputHTML( true, false, true );

		$this->assertStringContainsString( 'checked', $html );
	}

	/**
	 * @covers \PFFormUtils::minorEditInputHTML
	 */
	public function testMinorEditInputHTMLDisabledWhenIsDisabledTrue(): void {
		$html = \PFFormUtils::minorEditInputHTML( true, true, false );

		$this->assertStringContainsString( 'disabled', $html );
	}

	/**
	 * @covers \PFFormUtils::minorEditInputHTML
	 */
	public function testMinorEditInputHTMLCustomLabelAppearsInOutput(): void {
		$html = \PFFormUtils::minorEditInputHTML( true, false, false, 'Mark as minor' );

		$this->assertStringContainsString( 'Mark as minor', $html );
	}

	/**
	 * When the form has NOT been submitted, the method ignores the passed-in
	 * $is_checked and instead reads the user's 'minordefault' preference.
	 *
	 * @covers \PFFormUtils::minorEditInputHTML
	 */
	public function testMinorEditInputHTMLUsesUserMinorDefaultWhenNotSubmitted(): void {
		$user = self::getTestUser()->getUser();
		$this->getServiceContainer()->getUserOptionsManager()
			->setOption( $user, 'minordefault', 1 );
		\RequestContext::getMain()->setUser( $user );

		// Pass $is_checked = false, but $form_submitted = false so the
		// method should read the user preference (minordefault = 1) instead.
		$html = \PFFormUtils::minorEditInputHTML( false, false, false );

		$this->assertStringContainsString( 'checked', $html );
	}

	/**
	 * @covers \PFFormUtils::minorEditInputHTML
	 */
	public function testMinorEditInputHTMLCustomClassConvertedToClasses(): void {
		$html = \PFFormUtils::minorEditInputHTML( true, false, false, null, [ 'class' => 'my-custom-class' ] );

		$this->assertStringContainsString( 'my-custom-class', $html );
	}

	/**
	 * @covers \PFFormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLIncrementsTabIndex(): void {
		global $wgPageFormsTabIndex;
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'SomePage' ) ] );
		$before = $wgPageFormsTabIndex;

		\PFFormUtils::watchInputHTML( true, false, false );

		$this->assertSame( $before + 1, $wgPageFormsTabIndex );
	}

	/**
	 * @covers \PFFormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLContainsCheckboxAndLabel(): void {
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'SomePage' ) ] );

		$html = \PFFormUtils::watchInputHTML( true, false, false );

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'wpWatchthis', $html );
		$this->assertStringContainsString( '<label', $html );
		$this->assertStringContainsString( '<div', $html );
	}

	/**
	 * @covers \PFFormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLCheckedWhenFormSubmittedAndIsCheckedTrue(): void {
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'SomePage' ) ] );

		$html = \PFFormUtils::watchInputHTML( true, false, true );

		$this->assertStringContainsString( 'checked', $html );
	}

	/**
	 * @covers \PFFormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLDisabledWhenIsDisabledTrue(): void {
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'SomePage' ) ] );

		$html = \PFFormUtils::watchInputHTML( true, true, false );

		$this->assertStringContainsString( 'disabled', $html );
	}

	/**
	 * @covers \PFFormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLCustomLabelAppearsInOutput(): void {
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'SomePage' ) ] );

		$html = \PFFormUtils::watchInputHTML( true, false, false, 'Watch this page' );

		$this->assertStringContainsString( 'Watch this page', $html );
	}

	/**
	 * @covers \PFFormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLCustomClassConvertedToClasses(): void {
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'SomePage' ) ] );

		$html = \PFFormUtils::watchInputHTML( true, false, false, null, [ 'class' => 'my-watch-class' ] );

		$this->assertStringContainsString( 'my-watch-class', $html );
	}

	/**
	 * When the form has NOT been submitted and the user has 'watchdefault'
	 * enabled, the checkbox should be checked regardless of the passed-in value.
	 *
	 * @covers \PFFormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLCheckedViaWatchDefaultWhenNotSubmitted(): void {
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'SomePage' ) ] );

		$user = self::getTestUser()->getUser();
		$this->getServiceContainer()->getUserOptionsManager()
			->setOption( $user, 'watchdefault', 1 );
		\RequestContext::getMain()->setUser( $user );

		$html = \PFFormUtils::watchInputHTML( false, false, false );

		$this->assertStringContainsString( 'checked', $html );
	}

	/**
	 * When the form has NOT been submitted, the user has 'watchcreations'
	 * enabled, and $wgTitle does not exist, the checkbox should be checked.
	 *
	 * @covers \PFFormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLCheckedViaWatchCreationsForNonExistentPage(): void {
		// Use a title that definitely does not exist
		$nonExistentTitle = Title::newFromText( 'PFWatchInputTestNonExistent' . mt_rand() );
		$this->setMwGlobals( [ 'wgTitle' => $nonExistentTitle ] );

		$user = self::getTestUser()->getUser();
		$optionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$optionsManager->setOption( $user, 'watchdefault', 0 );
		$optionsManager->setOption( $user, 'watchcreations', 1 );
		\RequestContext::getMain()->setUser( $user );

		$html = \PFFormUtils::watchInputHTML( false, false, false );

		$this->assertStringContainsString( 'checked', $html );
	}

	/**
	 * @covers \PFFormUtils::watchInputHTML
	 */
	public function testWatchInputHTMLCheckedWhenPageIsAlreadyWatched(): void {
		$title = $this->createPage( 'PFWatchedPageForFormUtils' );
		$this->setMwGlobals( [ 'wgTitle' => $title ] );

		$user = self::getTestUser()->getUser();
		$optionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$optionsManager->setOption( $user, 'watchdefault', 0 );
		$optionsManager->setOption( $user, 'watchcreations', 0 );
		\RequestContext::getMain()->setUser( $user );

		$this->getServiceContainer()->getWatchlistManager()->addWatch( $user, $title );

		$html = \PFFormUtils::watchInputHTML( false, false, false );

		$this->assertStringContainsString( 'checked', $html );
	}

	/**
	 * @covers \PFFormUtils::formBottom
	 */
	public function testFormBottomContainsEditOptionsAndEditButtonsWrappers(): void {
		// Ensure RequestContext returns an anonymous user so watchInputHTML
		// is not rendered (avoids WatchlistManager complications).
		$anon = $this->getServiceContainer()->getUserFactory()->newAnonymous();
		\RequestContext::getMain()->setUser( $anon );
		// Use a FauxRequest with no wpSummary so we test the null path.
		$request = new FauxRequest( [], false );
		\RequestContext::getMain()->setRequest( $request );

		$result = \PFFormUtils::formBottom( false, false );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'editOptions', $result );
		$this->assertStringContainsString( 'editButtons', $result );
	}

	/**
	 * @covers \PFFormUtils::formBottom
	 */
	public function testFormBottomWithSummaryValueIncludesItInOutput(): void {
		$anon = $this->getServiceContainer()->getUserFactory()->newAnonymous();
		\RequestContext::getMain()->setUser( $anon );
		$request = new FauxRequest( [ 'wpSummary' => 'My edit summary' ], false );
		\RequestContext::getMain()->setRequest( $request );

		$result = \PFFormUtils::formBottom( false, false );

		$this->assertStringContainsString( 'My edit summary', $result );
	}

	/**
	 * @covers \PFFormUtils::formBottom
	 */
	public function testFormBottomIncludesMinorEditAndWatchForRegisteredUser(): void {
		$user = self::getTestUser()->getUser();
		\RequestContext::getMain()->setUser( $user );
		$request = new FauxRequest( [], false );
		\RequestContext::getMain()->setRequest( $request );
		// watchInputHTML reads $wgTitle
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'SomePage' ) ] );

		$result = \PFFormUtils::formBottom( false, false );

		$this->assertStringContainsString( 'wpMinoredit', $result );
		$this->assertStringContainsString( 'wpWatchthis', $result );
	}

	/**
	 * @covers \PFFormUtils::setShowOnSelect
	 */
	public function testSetShowOnSelectNonCheckboxStoresOptionsAndDivID(): void {
		global $wgPageFormsShowOnSelect;
		$wgPageFormsShowOnSelect = [];

		\PFFormUtils::setShowOnSelect( [ 'divA' => 'optX' ], 'input1', false );

		$this->assertArrayHasKey( 'input1', $wgPageFormsShowOnSelect );
		// Non-checkbox: each entry is [ $options, $divID ]
		$this->assertSame( [ [ 'optX', 'divA' ] ], $wgPageFormsShowOnSelect['input1'] );
	}

	/**
	 * @covers \PFFormUtils::setShowOnSelect
	 */
	public function testSetShowOnSelectCheckboxStoresDivIDDirectly(): void {
		global $wgPageFormsShowOnSelect;
		$wgPageFormsShowOnSelect = [];

		\PFFormUtils::setShowOnSelect( [ 'divB' => 'optY' ], 'input2', true );

		$this->assertArrayHasKey( 'input2', $wgPageFormsShowOnSelect );
		// Checkbox: each entry is just $divID
		$this->assertSame( [ 'divB' ], $wgPageFormsShowOnSelect['input2'] );
	}

	/**
	 * @covers \PFFormUtils::setShowOnSelect
	 */
	public function testSetShowOnSelectAppendsWhenCalledTwiceForSameInputID(): void {
		global $wgPageFormsShowOnSelect;
		$wgPageFormsShowOnSelect = [];

		\PFFormUtils::setShowOnSelect( [ 'divC' => 'opt1' ], 'input3', false );
		\PFFormUtils::setShowOnSelect( [ 'divD' => 'opt2' ], 'input3', false );

		$this->assertCount( 2, $wgPageFormsShowOnSelect['input3'] );
	}

	/**
	 * @covers \PFFormUtils::setShowOnSelect
	 */
	public function testSetShowOnSelectCreatesNewKeyForNewInputID(): void {
		global $wgPageFormsShowOnSelect;
		$wgPageFormsShowOnSelect = [];

		\PFFormUtils::setShowOnSelect( [ 'divE' => 'opt1' ], 'inputA', false );
		\PFFormUtils::setShowOnSelect( [ 'divF' => 'opt2' ], 'inputB', false );

		$this->assertArrayHasKey( 'inputA', $wgPageFormsShowOnSelect );
		$this->assertArrayHasKey( 'inputB', $wgPageFormsShowOnSelect );
		$this->assertCount( 1, $wgPageFormsShowOnSelect['inputA'] );
		$this->assertCount( 1, $wgPageFormsShowOnSelect['inputB'] );
	}
}
