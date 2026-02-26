<?php

use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;

/**
 * @covers \PFFormLinker
 * @group PageForms
 * @group Database
 * @author Collins Wandji <collinschuwa@gmail.com>
 */
class PFFormLinkerTest extends MediaWikiIntegrationTestCase {
	private static ?ReflectionProperty $formPerNamespaceProperty = null;

	/**
	 * Set up the environment
	 */
	protected function setUp(): void {
		$this->resetNamespaceFormCache();
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

	private function resetNamespaceFormCache(): void {
		$this->getFormPerNamespaceProperty()->setValue( null, [] );
	}

	private function setNamespaceFormCache( array $cache ): void {
		$this->getFormPerNamespaceProperty()->setValue( null, $cache );
	}

	private function getFormPerNamespaceProperty(): ReflectionProperty {
		if ( self::$formPerNamespaceProperty === null ) {
			self::$formPerNamespaceProperty = new ReflectionProperty( \PFFormLinker::class, 'formPerNamespace' );
			self::$formPerNamespaceProperty->setAccessible( true );
		}
		return self::$formPerNamespaceProperty;
	}

	private function setDefaultFormProperty( Title $title, string $formName ): void {
		$this->getExistingTestPage( $title );
		$this->getDb()->newReplaceQueryBuilder()
			->replaceInto( 'page_props' )
			->uniqueIndexFields( [ 'pp_page', 'pp_propname' ] )
			->row( [
				'pp_page' => $title->getArticleID(),
				'pp_propname' => 'PFDefaultForm',
				'pp_value' => $formName,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @covers \PFFormLinker::getDefaultForm
	 */
	public function testGetDefaultFormShouldReturnNullForNullTitle(): void {
		$form = \PFFormLinker::getDefaultForm( null );

		$this->assertNull( $form );
	}

	/**
	 * @covers \PFFormLinker::getDefaultForm
	 */
	public function testGetDefaultFormShouldReturnNullForNonexistentPage(): void {
		$title = $this->getNonexistingTestPage()->getTitle();
		$form = \PFFormLinker::getDefaultForm( $title );

		$this->assertNull( $form );
	}

	/**
	 * @covers \PFFormLinker::getDefaultForm
	 */
	public function testGetDefaultFormShouldReturnNullForExistingPageWithoutDefaultForm(): void {
		$title = $this->getExistingTestPage()->getTitle();
		$form = \PFFormLinker::getDefaultForm( $title );

		$this->assertNull( $form );
	}

	/**
	 * @covers \PFFormLinker::getDefaultForm
	 */
	public function testGetDefaultFormShouldReturnDefaultFormForParserFunction(): void {
		$page = $this->getNonexistingTestPage()->getTitle();
		$this->setDefaultFormProperty( $page, 'TestForm' );

		$form = \PFFormLinker::getDefaultForm( $page );

		$this->assertSame( 'TestForm', $form );
	}

	/**
	 * @covers \PFFormLinker::createPageWithForm
	 */
	public function testCreatePageWithFormQueuesJobWithGeneratedTextAndDefaultUser(): void {
		$targetTitle = $this->getNonexistingTestPage()->getTitle();
		$this->setMwGlobals( [
			'wgPageFormsAutoCreateUser' => null,
			'wgPageFormsFormPrinter' => new class {
				public function formHTML() {
					return [ '', 'Generated page text', null, null ];
				}
			},
		] );

		\PFFormLinker::createPageWithForm( $targetTitle, 'AnyForm', [] );

		$job = $this->getServiceContainer()->getJobQueueGroup()->pop( 'pageFormsCreatePage' );
		$this->assertInstanceOf( \PFCreatePageJob::class, $job );
		$this->assertSame( 1, $job->getParams()['user_id'] );
		$this->assertSame( 'Generated page text', $job->getParams()['page_text'] );
		$this->assertSame( $targetTitle->getPrefixedText(), $job->getTitle()->getPrefixedText() );
	}

	/**
	 * @covers \PFFormLinker::createPageWithForm
	 */
	public function testCreatePageWithFormUsesConfiguredAutoCreateUser(): void {
		$targetTitle = $this->getNonexistingTestPage()->getTitle();
		$autoCreateUser = self::getTestUser()->getUser();
		$this->setMwGlobals( [
			'wgPageFormsAutoCreateUser' => $autoCreateUser->getName(),
			'wgPageFormsFormPrinter' => new class {
				public function formHTML() {
					return [ '', 'Different generated text', null, null ];
				}
			},
		] );

		\PFFormLinker::createPageWithForm( $targetTitle, 'AnyForm', [] );

		$job = $this->getServiceContainer()->getJobQueueGroup()->pop( 'pageFormsCreatePage' );
		$this->assertInstanceOf( \PFCreatePageJob::class, $job );
		$this->assertSame( $autoCreateUser->getId(), $job->getParams()['user_id'] );
		$this->assertSame( 'Different generated text', $job->getParams()['page_text'] );
	}

	/**
	 * @covers \PFFormLinker::setBrokenLink
	 */
	public function testSetBrokenLinkSkipsKnownLinks(): void {
		$attribs = [ 'href' => '/wiki/AlreadyKnown' ];
		$text = 'AlreadyKnown';
		$ret = false;

		$result = \PFFormLinker::setBrokenLink(
			$this->createMock( LinkRenderer::class ),
			Title::newFromText( 'AlreadyKnown' ),
			true,
			$text,
			$attribs,
			$ret
		);

		$this->assertTrue( $result );
		$this->assertSame( '/wiki/AlreadyKnown', $attribs['href'] );
	}

	/**
	 * @covers \PFFormLinker::setBrokenLink
	 */
	public function testSetBrokenLinkSkipsSpecialNamespace(): void {
		$attribs = [ 'href' => '/wiki/Special:Version' ];
		$text = 'Special:Version';
		$ret = false;

		$result = \PFFormLinker::setBrokenLink(
			$this->createMock( LinkRenderer::class ),
			Title::newFromText( 'Version', NS_SPECIAL ),
			false,
			$text,
			$attribs,
			$ret
		);

		$this->assertTrue( $result );
		$this->assertSame( '/wiki/Special:Version', $attribs['href'] );
	}

	/**
	 * @covers \PFFormLinker::setBrokenLink
	 */
	public function testSetBrokenLinkUsesNamespaceDefaultForm(): void {
		$projectPage = Title::makeTitleSafe( NS_PROJECT, 'User' );
		$this->setDefaultFormProperty( $projectPage, 'UserForm' );
		$attribs = [];
		$text = 'User:RedLink';
		$ret = false;

		\PFFormLinker::setBrokenLink(
			$this->createMock( LinkRenderer::class ),
			Title::newFromText( 'RedLink', NS_USER ),
			false,
			$text,
			$attribs,
			$ret
		);

		$this->assertArrayHasKey( 'href', $attribs );
		$this->assertStringContainsString( 'action=formedit', $attribs['href'] );
		$this->assertStringContainsString( 'redlink=1', $attribs['href'] );
	}

	/**
	 * @covers \PFFormLinker::setBrokenLink
	 */
	public function testSetBrokenLinkUsesGlobalToggleForContentNamespace(): void {
		$this->setNamespaceFormCache( [ NS_MAIN => null ] );
		$this->setMwGlobals( [
			'wgPageFormsLinkAllRedLinksToForms' => true,
			'wgContentNamespaces' => [ NS_MAIN ],
		] );
		$attribs = [];
		$text = 'Main red link';
		$ret = false;

		\PFFormLinker::setBrokenLink(
			$this->createMock( LinkRenderer::class ),
			Title::newFromText( 'Main red link' ),
			false,
			$text,
			$attribs,
			$ret
		);

		$this->assertArrayHasKey( 'href', $attribs );
		$this->assertStringContainsString( 'action=formedit', $attribs['href'] );
	}

	/**
	 * @covers \PFFormLinker::setBrokenLink
	 */
	public function testSetBrokenLinkLeavesNonContentNamespaceUntouchedWithoutDefaultForm(): void {
		$this->setNamespaceFormCache( [ NS_USER => null ] );
		$this->setMwGlobals( [
			'wgPageFormsLinkAllRedLinksToForms' => true,
			'wgContentNamespaces' => [ NS_MAIN ],
		] );
		$attribs = [ 'href' => '/wiki/User:Someone' ];
		$text = 'User:Someone';
		$ret = false;

		\PFFormLinker::setBrokenLink(
			$this->createMock( LinkRenderer::class ),
			Title::newFromText( 'Someone', NS_USER ),
			false,
			$text,
			$attribs,
			$ret
		);

		$this->assertSame( '/wiki/User:Someone', $attribs['href'] );
	}

	/**
	 * @covers \PFFormLinker::getDefaultFormsForPage
	 */
	public function testGetDefaultFormsForPageReturnsOwnDefaultForm(): void {
		$page = $this->getNonexistingTestPage()->getTitle();
		$this->setDefaultFormProperty( $page, 'DirectForm' );

		$this->assertSame( [ 'DirectForm' ], \PFFormLinker::getDefaultFormsForPage( $page ) );
	}

	/**
	 * @covers \PFFormLinker::getDefaultFormsForPage
	 */
	public function testGetDefaultFormsForPageReturnsEmptyWhenOwnDefaultFormIsBlank(): void {
		$page = $this->getNonexistingTestPage()->getTitle();
		$this->setDefaultFormProperty( $page, '' );

		$this->assertSame( [], \PFFormLinker::getDefaultFormsForPage( $page ) );
	}

	/**
	 * @covers \PFFormLinker::getDefaultFormsForPage
	 */
	public function testGetDefaultFormsForPageReturnsEmptyWhenCategoryDefaultsCannotBeResolved(): void {
		$this->editPage( Title::newFromText( 'Category:PFCategoryA' ), '{{#default_form:CategoryForm}}' );
		$this->editPage( Title::newFromText( 'Category:PFCategoryB' ), '{{#default_form:CategoryForm}}' );
		$page = $this->getNonexistingTestPage();
		$this->editPage( $page, '[[Category:PFCategoryA]][[Category:PFCategoryB]]' );

		$this->assertSame( [], \PFFormLinker::getDefaultFormsForPage( $page->getTitle() ) );
	}

	/**
	 * @covers \PFFormLinker::getDefaultFormsForPage
	 */
	public function testGetDefaultFormsForPageReturnsEmptyForSubpageWithoutInheritedForms(): void {
		$this->setNamespaceFormCache( [ NS_USER => 'UserNamespaceForm' ] );
		$title = Title::newFromText( 'Parent/Subpage', NS_USER );

		$this->assertSame( [], \PFFormLinker::getDefaultFormsForPage( $title ) );
	}

	/**
	 * @covers \PFFormLinker::getDefaultFormsForPage
	 */
	public function testGetDefaultFormsForPageFallsBackToNamespaceDefaultForm(): void {
		$this->setNamespaceFormCache( [ NS_MAIN => 'MainNamespaceForm' ] );
		$title = Title::newFromText( 'NamespaceFallbackTarget' );

		$this->assertSame( [ 'MainNamespaceForm' ], \PFFormLinker::getDefaultFormsForPage( $title ) );
	}

	/**
	 * @covers \PFFormLinker::getDefaultFormForNamespace
	 */
	public function testGetDefaultFormForNamespaceReturnsNullForInvalidNamespace(): void {
		$this->assertNull( \PFFormLinker::getDefaultFormForNamespace( 999999 ) );
	}

	/**
	 * @covers \PFFormLinker::getDefaultFormForNamespace
	 */
	public function testGetDefaultFormForNamespaceReturnsConfiguredMainNamespaceForm(): void {
		$blankNamespaceLabel = wfMessage( 'pf_blank_namespace' )->inContentLanguage()->text();
		$this->setDefaultFormProperty( Title::makeTitleSafe( NS_PROJECT, $blankNamespaceLabel ), 'MainForm' );

		$this->assertSame( 'MainForm', \PFFormLinker::getDefaultFormForNamespace( NS_MAIN ) );
	}

	/**
	 * @covers \PFFormLinker::getDefaultFormForNamespace
	 */
	public function testGetDefaultFormForNamespaceUsesCacheWhenPresent(): void {
		$this->setNamespaceFormCache( [ NS_MAIN => 'CachedNamespaceForm' ] );

		$this->assertSame( 'CachedNamespaceForm', \PFFormLinker::getDefaultFormForNamespace( NS_MAIN ) );
	}
}
