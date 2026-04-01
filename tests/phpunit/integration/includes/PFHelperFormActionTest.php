<?php

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Page\Article;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use OOUI\BlankTheme;

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * @covers \PFHelperFormAction
 * @group PageForms
 * @group PF
 */
class PFHelperFormActionTest extends MediaWikiIntegrationTestCase {

	private \PFHelperFormAction $pfHelperFormAction;
	private DerivativeContext $context;
	private Article $article;

	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );
		parent::setUp();

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
		}

		$title = Title::newFromText( 'PFHelperFormActionTestPage' );
		$this->assertInstanceOf( Title::class, $title );

		$wikiPage = $this->getServiceContainer()
			->getWikiPageFactory()
			->newFromTitle( $title );

		$this->context = new DerivativeContext( RequestContext::getMain() );
		$this->context->setRequest( new FauxRequest( [ 'action' => 'formcreate' ] ) );
		$this->context->setTitle( $title );
		$this->context->setWikiPage( $wikiPage );

		$this->article = Article::newFromWikiPage( $wikiPage, $this->context );

		$this->pfHelperFormAction = new \PFHelperFormAction( $this->article, $this->context );
	}

	/**
	 * @covers \PFHelperFormAction::getName
	 */
	public function testGetName(): void {
		$this->assertSame( 'formcreate', $this->pfHelperFormAction->getName() );
	}

	/**
	 * @covers \PFHelperFormAction::execute
	 */
	public function testExecuteReturnsTrue(): void {
		$this->assertNotFalse( $this->pfHelperFormAction->execute() );

		$result = $this->pfHelperFormAction->execute();
		$this->assertTrue( $result );
	}

	/**
	 * @covers \PFHelperFormAction::show
	 */
	public function testShowReturnsFalseForUnsupportedNamespace(): void {
		$this->assertFalse( $this->pfHelperFormAction->show() );
	}

	/**
	 * @covers \PFHelperFormAction::displayTab
	 */
	public function testDisplayTabSkipsUnsupportedNamespace(): void {
		$title = $this->newTitleStub( NS_MAIN, false, '/wiki/PFHelperMainNamespacePage' );
		$links = $this->getDefaultLinks();

		\PFHelperFormAction::displayTab(
			$this->newSkinTemplateMock( $title, $this->newUserStub( true ), 'view' ),
			$links
		);

		$this->assertSame( $this->getDefaultLinks(), $links );
	}

	/**
	 * @covers \PFHelperFormAction::displayTab
	 */
	public function testDisplayTabSkipsExistingSupportedPage(): void {
		$title = $this->newTitleStub( NS_TEMPLATE, true, '/wiki/Template:PFExistingHelperTemplate' );
		$links = $this->getDefaultLinks();

		\PFHelperFormAction::displayTab(
			$this->newSkinTemplateMock( $title, $this->newUserStub( true ), 'view' ),
			$links
		);

		$this->assertSame( $this->getDefaultLinks(), $links );
	}

	/**
	 * @covers \PFHelperFormAction::displayTab
	 */
	public function testDisplayTabSkipsTemplateWhenGlobalDisabled(): void {
		$this->setMwGlobals( [ 'wgPageFormsShowTabsForAllHelperForms' => false ] );
		$title = $this->newTitleStub( NS_TEMPLATE, false, '/wiki/Template:PFHelperTemplateDraft' );
		$links = $this->getDefaultLinks();

		\PFHelperFormAction::displayTab(
			$this->newSkinTemplateMock( $title, $this->newUserStub( true ), 'view' ),
			$links
		);

		$this->assertSame( $this->getDefaultLinks(), $links );
	}

	/**
	 * @covers \PFHelperFormAction::displayTab
	 */
	public function testDisplayTabAddsSelectedFormeditTabBeforeEdit(): void {
		$this->setService( 'PermissionManager', $this->newPermissionManagerStub( true ) );
		$title = $this->newTitleStub( PF_NS_FORM, false, '/wiki/Form:PFHelperDraftForm?action=formcreate' );
		$links = $this->getDefaultLinks();

		\PFHelperFormAction::displayTab(
			$this->newSkinTemplateMock( $title, $this->newUserStub( true ), 'formcreate' ),
			$links
		);

		$this->assertSame( [ 'view', 'formedit', 'edit', 'history' ], array_keys( $links['views'] ) );
		$this->assertSame( 'selected', $links['views']['formedit']['class'] );
		$this->assertSame( wfMessage( 'pf_formcreate' )->text(), $links['views']['formedit']['text'] );
		$this->assertSame( '/wiki/Form:PFHelperDraftForm?action=formcreate', $links['views']['formedit']['href'] );
		$this->assertSame( 'edit', $links['views']['formedit']['icon'] );
	}

	/**
	 * @covers \PFHelperFormAction::displayTab
	 */
	public function testDisplayTabUsesViewSourceFallbackAndRemovesViewsourceWithoutViewedittabRight(): void {
		$this->setService( 'PermissionManager', $this->newPermissionManagerStub( false ) );
		$title = $this->newTitleStub( PF_NS_FORM, false, '/wiki/Form:PFHelperViewSourceForm?action=formcreate' );
		$links = [
			'views' => [
				'view' => [ 'text' => 'Read' ],
				'viewsource' => [ 'text' => 'View source' ],
				'history' => [ 'text' => 'History' ],
			],
		];

		\PFHelperFormAction::displayTab(
			$this->newSkinTemplateMock( $title, $this->newUserStub( false ), 'view' ),
			$links
		);

		$this->assertSame( [ 'view', 'formedit', 'history' ], array_keys( $links['views'] ) );
		$this->assertSame( '', $links['views']['formedit']['class'] );
		$this->assertSame( wfMessage( 'pf_viewform' )->text(), $links['views']['formedit']['text'] );
		$this->assertArrayNotHasKey( 'viewsource', $links['views'] );
	}

	private function getDefaultLinks(): array {
		return [
			'views' => [
				'view' => [ 'text' => 'Read' ],
				'edit' => [ 'text' => 'Edit' ],
				'history' => [ 'text' => 'History' ],
			],
		];
	}

	private function newPermissionManagerStub( bool $userCanEdit ): PermissionManager {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userCan' )->willReturn( $userCanEdit );
		return $permissionManager;
	}

	private function newUserStub( bool $canViewEditTab ): User {
		$user = $this->createMock( User::class );
		$user->method( 'isAllowed' )
			->willReturnCallback( static function ( string $right ) use ( $canViewEditTab ) {
				return $right === 'viewedittab' ? $canViewEditTab : true;
			} );
		return $user;
	}

	private function newSkinTemplateMock( Title $title, User $user, string $action ): SkinTemplate {
		$request = new FauxRequest( [ 'action' => $action ] );

		$skinTemplate = $this->createMock( SkinTemplate::class );
		$skinTemplate->method( 'getTitle' )->willReturn( $title );
		$skinTemplate->method( 'getUser' )->willReturn( $user );
		$skinTemplate->method( 'getRequest' )->willReturn( $request );

		return $skinTemplate;
	}

	private function newTitleStub( int $namespace, bool $exists, string $localUrl ): Title {
		$title = $this->createMock( Title::class );
		$title->method( 'getNamespace' )->willReturn( $namespace );
		$title->method( 'exists' )->willReturn( $exists );
		$title->method( 'getLocalURL' )->with( 'action=formcreate' )->willReturn( $localUrl );

		return $title;
	}
}
