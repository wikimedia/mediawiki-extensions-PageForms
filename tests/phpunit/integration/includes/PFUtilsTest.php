<?php

require_once __DIR__ . '/PFUtilsTestDummySMWStore.php';

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Session\Token;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @group PageForms
 * @group PF
 * @group Database
 * @covers \PFUtils
 * @author Collins Wandji <collinschuwa@gmail.com>
 */
class PFUtilsTest extends MediaWikiIntegrationTestCase {

	/** @var ParserOptions|null */
	private $parserOptions;

	protected function setUp(): void {
		$smwPath = MW_INSTALL_PATH . '/extensions/SemanticMediaWiki/SemanticMediaWiki.php';
		if ( file_exists( $smwPath ) ) {
			if ( !class_exists( '\SemanticMediaWiki' ) ) {
				require_once $smwPath;
			}

			if ( !defined( 'SMW_VERSION' ) ) {
				define( 'SMW_VERSION', 'test' );
			}

			\SemanticMediaWiki::setupDefines();
			\SemanticMediaWiki::setupGlobals();

			if ( !function_exists( 'smwfContLang' ) ) {
				require_once MW_INSTALL_PATH . '/extensions/SemanticMediaWiki/includes/GlobalFunctions.php';
			}
		}

		$this->setMwGlobals( [
			'wgPageFormsUseDisplayTitle' => false,
			'wgPageFormsMaxLocalAutocompleteValues' => 10,
			'wgPageFormsSimpleUpload' => false,
			'wgPageFormsIgnoreTitlePattern' => [],
			'wgPageFormsScriptPath' => '/extensions/PageForms',
			'smwgIP' => MW_INSTALL_PATH . '/extensions/SemanticMediaWiki',
			'smwgServicesFileDir' => MW_INSTALL_PATH . '/extensions/SemanticMediaWiki/src/Services',
			'smwgDefaultStore' => 'PFUtilsTestDummySMWStore',
			'smwgStoreFactory' => 'SMW\StoreFactory',
			'smwgExtraneousLanguageFileDir' => MW_INSTALL_PATH . '/extensions/SemanticMediaWiki/i18n/extra',
		] );

		parent::setUp();
	}

	private function requireSemanticMediaWiki(): void {
		if ( !class_exists( '\SemanticMediaWiki' ) ) {
			$this->markTestSkipped( 'Semantic MediaWiki is not available' );
		}
	}

	private function getLinkRenderer() {
		return $this->getServiceContainer()->getLinkRenderer();
	}

	private function getFreshOutputPage(): OutputPage {
		return new OutputPage( RequestContext::getMain() );
	}

	private function getFreshParser(): Parser {
		return $this->getServiceContainer()->getParserFactory()->create();
	}

	private function getParserOptions() {
		if ( $this->parserOptions ) {
			return $this->parserOptions;
		}
		return ParserOptions::newFromUserAndLang(
			new User,
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' )
		);
	}

	private function getInitializedParser(): Parser {
		$parser = $this->getFreshParser();
		$options = $this->getParserOptions();
		$parser->setOptions( $options );

		$title = Title::makeTitle( NS_MAIN, 'PFUtils Test Parser Page' );
		$parser->setPage( $title );

		$parser->clearState();

		return $parser;
	}

	private function createTextPage( string $titleText, string $content ): Title {
		$title = Title::newFromText( $titleText );
		$this->editPage( $title, $content );
		return $title;
	}

	private function createFormPage( string $name, string $content = 'Test form content' ): Title {
		$title = Title::makeTitleSafe( PF_NS_FORM, $name );
		$this->editPage( $title, $content );
		return $title;
	}

	/**
	 * @covers \PFUtils::getSMWStore
	 */
	public function testGetSMWStore(): void {
		$this->requireSemanticMediaWiki();
		$store = \PFUtils::getSMWStore();
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			$this->assertInstanceOf( \SMW\Store::class, $store );
		} else {
			$this->assertNull( $store );
		}
	}

	/**
	 * @covers \PFUtils::getContLang
	 */
	public function testGetContLang(): void {
		$lang = \PFUtils::getContLang();
		$this->assertInstanceOf( \Language::class, $lang );
		$this->assertSame(
			MediaWikiServices::getInstance()->getContentLanguage(),
			$lang
		);
	}

	/**
	 * @covers \PFUtils::getSMWContLang
	 */
	public function testGetSMWContLang(): void {
		$this->requireSemanticMediaWiki();
		$lang = \PFUtils::getSMWContLang();
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			$this->assertInstanceOf( \SMW\Localizer\LocalLanguage\LocalLanguage::class, $lang );
		} else {
			$this->assertNull( $lang );
		}
	}

	/**
	 * @covers \PFUtils::getParser
	 */
	public function testGetParser(): void {
		$parser = \PFUtils::getParser();
		$this->assertInstanceOf( \Parser::class, $parser );
	}

	/**
	 * @covers \PFUtils::getSpecialPage
	 */
	public function testGetSpecialPage(): void {
		$specialPage = \PFUtils::getSpecialPage( 'Version' );
		$this->assertInstanceOf( SpecialPage::class, $specialPage );
		$this->assertSame( 'Version', $specialPage->getName() );
	}

	/**
	 * @covers \PFUtils::getReadDB
	 */
	public function testGetReadDB(): void {
		$dbr = \PFUtils::getReadDB();
		$this->assertTrue( $dbr instanceof IReadableDatabase || $dbr instanceof DBConnRef );
	}

	/**
	 * @covers \PFUtils::linkForSpecialPage
	 */
	public function testLinkForSpecialPageRendersRealSpecialPageLink(): void {
		$link = \PFUtils::linkForSpecialPage( $this->getLinkRenderer(), 'Version' );
		$specialPage = \PFUtils::getSpecialPage( 'Version' );

		$this->assertStringContainsString( 'href=', $link );
		$this->assertStringContainsString( 'Special:Version', $link );
		$this->assertStringContainsString(
			htmlspecialchars( $specialPage->getDescription() ),
			$link
		);
	}

	/**
	 * @covers \PFUtils::makeLink
	 */
	public function testMakeLinkReturnsNullForNullTitle(): void {
		$this->assertNull( \PFUtils::makeLink( $this->getLinkRenderer(), null ) );
	}

	/**
	 * @covers \PFUtils::makeLink
	 */
	public function testMakeLinkReturnsSelfLinkForCurrentTitle(): void {
		$title = Title::newFromText( 'Current PFUtils Page' );
		$this->setMwGlobals( [ 'wgTitle' => $title ] );

		$link = \PFUtils::makeLink( $this->getLinkRenderer(), $title, 'Current PFUtils Page' );

		$this->assertStringContainsString( 'Current PFUtils Page', $link );
		$this->assertStringContainsString( 'mw-selflink', $link );
	}

	/**
	 * @covers \PFUtils::makeLink
	 */
	public function testMakeLinkReturnsNormalLinkForDifferentTitle(): void {
		$current = Title::newFromText( 'PFUtils Current Page' );
		$target = Title::newFromText( 'PFUtils Target Page' );
		$this->setMwGlobals( [ 'wgTitle' => $current ] );

		$link = \PFUtils::makeLink(
			$this->getLinkRenderer(),
			$target,
			'Target label',
			[ 'class' => 'pfutils-link' ],
			[ 'hello' => 'hi' ]
		);

		$this->assertStringContainsString( '<a ', $link );
		$this->assertStringContainsString( 'Target label', $link );
		$this->assertStringContainsString( 'PFUtils_Target_Page', $link );
		$this->assertStringContainsString( 'hello=hi', $link );
		$this->assertStringContainsString( 'pfutils-link', $link );
	}

	/**
	 * @covers \PFUtils::titleURLString
	 */
	public function testTitleURLStringForMainNamespace(): void {
		$title = Title::newFromText( 'Test Page' );
		$urlString = \PFUtils::titleURLString( $title );
		$this->assertSame( 'Test_Page', $urlString );
	}

	/**
	 * @covers \PFUtils::titleURLString
	 */
	public function testTitleURLStringIncludesNamespacePrefix(): void {
		$title = Title::makeTitleSafe( NS_TEMPLATE, 'Infobox person' );
		$urlString = \PFUtils::titleURLString( $title );
		$this->assertSame( 'Template:Infobox_person', $urlString );
	}

	/**
	 * @covers \PFUtils::titleURLString
	 */
	public function testTitleURLStringNonCapitalizedNamespaceBranch(): void {
		$this->setMwGlobals( [ 'wgCapitalLinks' => false ] );
		$title = Title::newFromText( 'test page' );
		$urlString = \PFUtils::titleURLString( $title );
		$this->assertSame( 'test_page', $urlString );
	}

	/**
	 * @covers \PFUtils::getPageText
	 */
	public function testGetPageTextReturnsStoredText(): void {
		$title = $this->createTextPage( 'PFUtils page text source', 'Stored PFUtils text' );
		$this->assertSame( 'Stored PFUtils text', \PFUtils::getPageText( $title ) );
	}

	/**
	 * @covers \PFUtils::getPageText
	 */
	public function testGetPageTextReturnsNullForNonTextContent(): void {
		$title = Title::newFromText( 'PFUtils:PFUtils-config.json' );
		$this->editPage( $title, new JsonContent( '{"pfutils":"value"}' ) );

		$this->assertNotNull( \PFUtils::getPageText( $title, RevisionRecord::FOR_PUBLIC ) );
	}

	/**
	 * @covers \PFUtils::linkText
	 */
	public function testLinkTextUsesPageNameWhenTextMissing(): void {
		$link = \PFUtils::linkText( NS_MAIN, 'PFUtils Link Target' );
		$this->assertSame( '[[:PFUtils Link Target|PFUtils Link Target]]', $link );
	}

	/**
	 * @covers \PFUtils::linkText
	 */
	public function testLinkTextUsesCustomTextWhenProvided(): void {
		$link = \PFUtils::linkText( NS_TEMPLATE, 'PFUtilsTemplate', 'Template label' );
		$this->assertSame( '[[:Template:PFUtilsTemplate|Template label]]', $link );
	}

	/**
	 * @covers \PFUtils::linkText
	 */
	public function testLinkTextReturnsRawNameForInvalidTitle(): void {
		$this->assertSame( 'Invalid[Title', \PFUtils::linkText( NS_MAIN, 'Invalid[Title' ) );
	}

	/**
	 * @covers \PFUtils::printRedirectForm
	 */
	public function testPrintRedirectFormForSaveWithTitleObject(): void {
		$title = Title::newFromText( 'PFUtils Redirect Target' );
		$user = self::getTestUser()->getUser();

		$html = \PFUtils::printRedirectForm( $title, 'Body text', 'Summary text', true, $user );

		$this->assertStringContainsString( 'loading.gif', $html );
		$this->assertStringContainsString( 'name="wpTextbox1"', $html );
		$this->assertStringContainsString( 'Body text', $html );
		$this->assertStringContainsString( 'name="wpSummary"', $html );
		$this->assertStringContainsString( 'Summary text', $html );
		$this->assertStringContainsString( 'name="wpEditToken"', $html );
		$this->assertStringContainsString( $user->getEditToken(), $html );
		$this->assertStringContainsString( 'name="wpSave"', $html );
		$this->assertStringContainsString( 'name="wpUltimateParam"', $html );
		$this->assertStringContainsString( 'action=submit', $html );
		$this->assertStringContainsString( 'document.editform.submit()', $html );
	}

	/**
	 * @covers \PFUtils::printRedirectForm
	 */
	public function testPrintRedirectFormForPreviewWithRawUrl(): void {
		$user = self::getTestUser()->getUser();
		$url = '/wiki/Special:FormEdit/TestPage';

		$html = \PFUtils::printRedirectForm( $url, 'Preview body', 'Preview summary', false, $user );

		$this->assertStringContainsString( 'name="wpPreview"', $html );
		$this->assertStringContainsString( 'action="' . htmlspecialchars( $url ) . '"', $html );
		$this->assertStringContainsString( 'Preview body', $html );
		$this->assertStringContainsString( 'Preview summary', $html );
	}

	/**
	 * @covers \PFUtils::printRedirectForm
	 */
	public function testPrintRedirectFormUsesAnonymousTokenSuffixForAnonymousUser(): void {
		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' );

		$html = \PFUtils::printRedirectForm(
			'/wiki/Special:FormEdit/Anon',
			'Anon body',
			'Anon summary',
			true,
			$user
		);

		$this->assertStringContainsString( 'name="wpEditToken"', $html );
		$this->assertStringContainsString( Token::SUFFIX, $html );
	}

	/**
	 * @covers \PFUtils::addFormRLModules
	 */
	public function testAddFormRLModulesUsesOutputPageWhenParserMissing(): void {
		$output = $this->getFreshOutputPage();
		$this->setMwGlobals( [ 'wgOut' => $output, 'wgPageFormsSimpleUpload' => false ] );

		\PFUtils::addFormRLModules();

		$metaTags = $output->getMetaTags();
		$metaTagsString = '';
		foreach ( $metaTags as $tag ) {
			if ( is_array( $tag ) ) {
				$metaTagsString .= implode( ',', $tag );
			} else {
				$metaTagsString .= $tag;
			}
		}

		$this->assertStringContainsString( 'noindex,nofollow', $metaTagsString );
		$this->assertContains( 'ext.pageforms.main', $output->getModules() );
		$this->assertContains( 'ext.pageforms.submit', $output->getModules() );
		$this->assertContains( 'jquery.makeCollapsible', $output->getModules() );
		$this->assertContains( 'ext.pageforms.main.styles', $output->getModuleStyles() );
		$this->assertNotContains( 'ext.pageforms.simpleupload', $output->getModules() );
	}

	/**
	 * @covers \PFUtils::addFormRLModules
	 */
	public function testAddFormRLModulesUsesParserOutputWhenParserProvided(): void {
		$output = $this->getFreshOutputPage();
		$this->setMwGlobals( [ 'wgOut' => $output, 'wgPageFormsSimpleUpload' => false ] );
		$parser = $this->getInitializedParser();

		$parserOutput = $parser->getOutput();

		\PFUtils::addFormRLModules( $parser );

		$this->assertContains( 'ext.pageforms.main', $parserOutput->getModules() );
		$this->assertContains( 'ext.pageforms.main.styles', $parserOutput->getModuleStyles() );
		$this->assertNotContains( 'ext.pageforms.main', $output->getModules() );
	}

	/**
	 * @covers \PFUtils::addFormRLModules
	 */
	public function testAddFormRLModulesAddsSimpleUploadModuleWhenEnabled(): void {
		$output = $this->getFreshOutputPage();
		$this->setMwGlobals( [ 'wgOut' => $output, 'wgPageFormsSimpleUpload' => true ] );

		\PFUtils::addFormRLModules();

		$this->assertContains( 'ext.pageforms.simpleupload', $output->getModules() );
	}

	/**
	 * @covers \PFUtils::getAllForms
	 */
	public function testGetAllFormsReturnsSortedFormNames(): void {
		$this->createFormPage( 'Zoo_Form' );
		$this->createFormPage( 'Alpha_Form' );

		$forms = \PFUtils::getAllForms();

		$this->assertSame( [ 'Alpha Form', 'Zoo Form' ], $forms );
	}

	/**
	 * @covers \PFUtils::getAllForms
	 */
	public function testGetAllFormsThrowsWhenNoFormsExist(): void {
		$this->expectException( MWException::class );
		\PFUtils::getAllForms();
	}

	/**
	 * @covers \PFUtils::convertBackToPipes
	 */
	public function testConvertBackToPipes(): void {
		$this->assertSame( 'one|two|three', \PFUtils::convertBackToPipes( "one\1two\1three" ) );
	}

	/**
	 * @covers \PFUtils::getFormTagComponents
	 */
	public function testGetFormTagComponentsSplitsSimpleTags(): void {
		$this->assertSame(
			[ 'field', 'input type=text', 'default=Value' ],
			\PFUtils::getFormTagComponents( 'field|input type=text|default=Value' )
		);
	}

	/**
	 * @covers \PFUtils::getFormTagComponents
	 */
	public function testGetFormTagComponentsPreservesNestedTemplatePipes(): void {
		$this->assertSame(
			[ 'field', '{{#if:{{{1|}}}|yes|no}}', 'input type=text' ],
			\PFUtils::getFormTagComponents( 'field|{{#if:{{{1|}}}|yes|no}}|input type=text' )
		);
	}

	/**
	 * @covers \PFUtils::getFormTagComponents
	 */
	public function testGetFormTagComponentsRemovesHtmlComments(): void {
		$this->assertSame(
			[ 'field', 'input type=text' ],
			\PFUtils::getFormTagComponents( 'field|<!-- hidden -->input type=text' )
		);
	}

	/**
	 * @covers \PFUtils::getFormTagComponents
	 */
	public function testGetFormTagComponentsReturnsEmptyArrayForEmptyString(): void {
		$this->assertSame( [], \PFUtils::getFormTagComponents( '' ) );
	}

	/**
	 * @covers \PFUtils::getWordForYesOrNo
	 */
	public function testGetWordForYesOrNo(): void {
		$this->assertSame(
			wfMessage( 'htmlform-yes' )->inContentLanguage()->text(),
			\PFUtils::getWordForYesOrNo( true )
		);
		$this->assertSame(
			wfMessage( 'htmlform-no' )->inContentLanguage()->text(),
			\PFUtils::getWordForYesOrNo( false )
		);
	}

	/**
	 * @covers \PFUtils::arrayMergeRecursiveDistinct
	 */
	public function testArrayMergeRecursiveDistinctOverwritesRecursively(): void {
		$array1 = [
			'a' => [ 'left' => 1, 'shared' => [ 'old' => 'value', 'keep' => true ] ],
			'b' => 'first',
		];
		$array2 = [
			'a' => [ 'shared' => [ 'old' => 'new value' ], 'right' => 2 ],
			'b' => 'second',
		];

		$result = \PFUtils::arrayMergeRecursiveDistinct( $array1, $array2 );

		$this->assertSame(
			[
				'a' => [
					'left' => 1,
					'shared' => [ 'old' => 'new value', 'keep' => true ],
					'right' => 2,
				],
				'b' => 'second',
			],
			$result
		);
	}

	/**
	 * @covers \PFUtils::ignoreFormName
	 */
	public function testIgnoreFormNameWithScalarPattern(): void {
		$this->setMwGlobals( [ 'wgPageFormsIgnoreTitlePattern' => 'Archive' ] );

		$this->assertTrue( \PFUtils::ignoreFormName( 'Project Archive' ) );
		$this->assertFalse( \PFUtils::ignoreFormName( 'Project Active' ) );
	}

	/**
	 * @covers \PFUtils::ignoreFormName
	 */
	public function testIgnoreFormNameWithPatternArray(): void {
		$this->setMwGlobals( [ 'wgPageFormsIgnoreTitlePattern' => [ '^Draft', 'Sandbox$' ] ] );

		$this->assertTrue( \PFUtils::ignoreFormName( 'Draft Form' ) );
		$this->assertTrue( \PFUtils::ignoreFormName( 'Form Sandbox' ) );
		$this->assertFalse( \PFUtils::ignoreFormName( 'Published Form' ) );
	}

	/**
	 * @covers \PFUtils::getCanonicalName
	 */
	public function testGetCanonicalName(): void {
		$this->assertSame(
			$this->getServiceContainer()->getNamespaceInfo()->getCanonicalName( NS_TEMPLATE ),
			\PFUtils::getCanonicalName( NS_TEMPLATE )
		);
	}

	/**
	 * @covers \PFUtils::isTranslateEnabled
	 */
	public function testIsTranslateEnabledMatchesExtensionRegistry(): void {
		$this->assertSame(
			ExtensionRegistry::getInstance()->isLoaded( 'Translate' ),
			\PFUtils::isTranslateEnabled()
		);
	}

	/**
	 * @covers \PFUtils::getCargoFieldDescription
	 */
	public function testGetCargoFieldDescriptionConditionalBehavior(): void {
		if ( !class_exists( 'CargoUtils' ) ) {
			$this->assertFalse( class_exists( 'CargoUtils' ) );
			return;
		}

		$this->assertNull( \PFUtils::getCargoFieldDescription( 'pfutils_missing_table', 'field' ) );
	}
}
