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
		\PFHelperFormAction::setHelperPageFactory( null );

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

	protected function tearDown(): void {
		\PFHelperFormAction::setHelperPageFactory( null );
		parent::tearDown();
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
		$this->assertTrue( $this->pfHelperFormAction->execute() );
	}

	/**
	 * @covers \PFHelperFormAction::show
	 */
	public function testShowReturnsFalseForUnsupportedNamespace(): void {
		$this->assertFalse( $this->pfHelperFormAction->show() );
	}

	/**
	 * @covers \PFHelperFormAction::show
	 */
	public function testShowDisplaysPropertyHelperFormForPropertyNamespace(): void {
		if ( !defined( 'SMW_NS_PROPERTY' ) ) {
			define( 'SMW_NS_PROPERTY', 102 );
		}
		$executions = new ArrayObject();
		$action = $this->newHelperFormActionForNamespace( SMW_NS_PROPERTY, 'PFHelperPropertyPage', $executions );
		$this->assertFalse( $action->show() );
		$this->assertSame( [ [ PFCreateProperty::class, 'PFHelperPropertyPage' ] ], $executions->getArrayCopy() );
	}

	/**
	 * @covers \PFHelperFormAction::show
	 */
	public function testShowDisplaysTemplateHelperFormForTemplateNamespace(): void {
		$executions = new ArrayObject();
		$action = $this->newHelperFormActionForNamespace( NS_TEMPLATE, 'PFHelperTemplatePage', $executions );
		$this->assertFalse( $action->show() );
		$this->assertSame( [ [ PFCreateTemplate::class, 'PFHelperTemplatePage' ] ], $executions->getArrayCopy() );
	}

	/**
	 * @covers \PFHelperFormAction::show
	 */
	public function testShowDisplaysFormHelperFormForFormNamespace(): void {
		$executions = new ArrayObject();
		$action = $this->newHelperFormActionForNamespace( PF_NS_FORM, 'PFHelperFormPage', $executions );
		$this->assertFalse( $action->show() );
		$this->assertSame( [ [ PFCreateForm::class, 'PFHelperFormPage' ] ], $executions->getArrayCopy() );
	}

	/**
	 * @covers \PFHelperFormAction::show
	 */
	public function testShowDisplaysCategoryHelperFormForCategoryNamespace(): void {
		$executions = new ArrayObject();
		$action = $this->newHelperFormActionForNamespace( NS_CATEGORY, 'PFHelperCategoryPage', $executions );
		$this->assertFalse( $action->show() );
		$this->assertSame( [ [ PFCreateCategory::class, 'PFHelperCategoryPage' ] ], $executions->getArrayCopy() );
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
	public function testDisplayTabAddsTabForPropertyNamespaceWhenDefined(): void {
		if ( !defined( 'SMW_NS_PROPERTY' ) ) {
			define( 'SMW_NS_PROPERTY', 102 );
		}

		$this->setService( 'PermissionManager', $this->newPermissionManagerStub( true ) );
		$title = $this->newTitleStub( SMW_NS_PROPERTY, false, '/wiki/Property:PFHelperPropertyDraft?action=formcreate' );
		$links = $this->getDefaultLinks();

		\PFHelperFormAction::displayTab(
			$this->newSkinTemplateMock( $title, $this->newUserStub( true ), 'view' ),
			$links
		);

		$this->assertSame( [ 'view', 'formedit', 'edit', 'history' ], array_keys( $links['views'] ) );
		$this->assertSame( '', $links['views']['formedit']['class'] );
		$this->assertSame( wfMessage( 'pf_formcreate' )->text(), $links['views']['formedit']['text'] );
		$this->assertSame( '/wiki/Property:PFHelperPropertyDraft?action=formcreate', $links['views']['formedit']['href'] );
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

	/**
	 * @covers \PFHelperFormAction::displayTab
	 */
	public function testDisplayTabAppendsNearEndWhenEditAndViewsourceAreMissing(): void {
		$this->setService( 'PermissionManager', $this->newPermissionManagerStub( true ) );
		$title = $this->newTitleStub( PF_NS_FORM, false, '/wiki/Form:PFHelperNoEditTabForm?action=formcreate' );
		$links = [
			'views' => [
				'view' => [ 'text' => 'Read' ],
				'history' => [ 'text' => 'History' ],
				'delete' => [ 'text' => 'Delete' ],
			],
		];

		\PFHelperFormAction::displayTab(
			$this->newSkinTemplateMock( $title, $this->newUserStub( true ), 'view' ),
			$links
		);

		$this->assertSame( [ 'view', 'history', 'formedit', 'delete' ], array_keys( $links['views'] ) );
		$this->assertSame( wfMessage( 'pf_formcreate' )->text(), $links['views']['formedit']['text'] );
		$this->assertSame( '/wiki/Form:PFHelperNoEditTabForm?action=formcreate', $links['views']['formedit']['href'] );
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

	private function newHelperFormActionForNamespace(
		int $namespace,
		string $dbKey,
		ArrayObject $executions
	): \PFHelperFormAction {
		RequestContext::resetMain();
		$mainContext = RequestContext::getMain();
		$mainContext->setRequest( new FauxRequest( [ 'action' => 'formcreate' ] ) );
		$mainContext->getOutput()->clearHTML();

		$context = new DerivativeContext( $mainContext );
		$context->setRequest( new FauxRequest( [ 'action' => 'formcreate' ] ) );

		$title = $this->createMock( Title::class );
		$title->method( 'getNamespace' )->willReturn( $namespace );
		$title->method( 'getText' )->willReturn( $dbKey );
		$context->setTitle( $title );

		$article = $this->createMock( Article::class );
		$article->method( 'getTitle' )->willReturn( $title );
		$executions->exchangeArray( [] );

		\PFHelperFormAction::setHelperPageFactory(
			static function ( string $className ) use ( $executions ) {
				return new class( $className, $executions ) {
					private string $className;
					private ArrayObject $executionLog;

					public function __construct( string $className, ArrayObject $executionLog ) {
						$this->className = $className;
						$this->executionLog = $executionLog;
					}

					public function execute( $query ): void {
						$this->executionLog->append( [ $this->className, $query ] );
					}
				};
			}
		);

		return new \PFHelperFormAction( $article, $context );
	}

	private function newTitleStub( int $namespace, bool $exists, string $localUrl ): Title {
		$title = $this->createMock( Title::class );
		$title->method( 'getNamespace' )->willReturn( $namespace );
		$title->method( 'exists' )->willReturn( $exists );
		$title->method( 'getLocalURL' )->with( 'action=formcreate' )->willReturn( $localUrl );

		return $title;
	}
}
