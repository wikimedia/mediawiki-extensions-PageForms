<?php
/**
 * Static functions called by various outside hooks, as well as by
 * extension.json.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\ResourceLoader;

class PFHooks {

	/**
	 * Used for caching by addToCargoTablesLinks().
	 */
	private static $mMultiPageEditPage = null;

	public static function registerExtension() {
		if ( defined( 'PF_VERSION' ) ) {
			// Do not load Page Forms more than once.
			return 1;
		}

		define( 'PF_VERSION', '5.6.3' );

		$GLOBALS['wgPageFormsIP'] = dirname( __DIR__ ) . '/../';

		/**
		 * This is a delayed init that makes sure that MediaWiki is set
		 * up properly before we add our stuff.
		 */

		if ( defined( 'SMW_VERSION' ) || ExtensionRegistry::getInstance()->isLoaded( 'SemanticMediaWiki' ) ) {
			$GLOBALS['wgSpecialPages']['CreateProperty'] = 'PFCreateProperty';
			$GLOBALS['wgAutoloadClasses']['PFCreateProperty'] = __DIR__ . '/../specials/PF_CreateProperty.php';
			$GLOBALS['smwgEnabledSpecialPage'][] = 'RunQuery';
		}

		// Allow for popup windows for file upload
		$GLOBALS['wgEditPageFrameOptions'] = 'SAMEORIGIN';
	}

	public static function initialize() {
		$GLOBALS['wgPageFormsScriptPath'] = $GLOBALS['wgExtensionAssetsPath'] . '/PageForms';

		// This global variable is needed so that other
		// extensions can hook into it to add their own
		// input types.
		// @phan-suppress-next-line PhanUndeclaredFunctionInCallable
		$GLOBALS['wgPageFormsFormPrinter'] = new StubObject( 'wgPageFormsFormPrinter', 'PFFormPrinter' );
	}

	/**
	 * Called by ResourceLoaderRegisterModules hook.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 *
	 * @param ResourceLoader $resourceLoader The ResourceLoader object
	 */
	public static function registerModules( ResourceLoader $resourceLoader ) {
		// These used to use a value of __DIR__ for 'localBasePath',
		// but apparently in some installations that had a value of
		// /PageForms/libs and in others just /PageForms, so we'll set
		// the value here instead.
		$pageFormsDir = __DIR__ . '/..';

		$mapsModuleAttrs = [
			'localBasePath' => $pageFormsDir,
			'remoteExtPath' => 'PageForms',
			'dependencies' => [ 'oojs-ui.styles.icons-location' ]
		];

		if ( ExtensionRegistry::getInstance()->isLoaded( 'OpenLayers' ) ) {
			$mapsModuleAttrs['scripts'] = '/libs/PF_maps.offline.js';
			$mapsModuleAttrs['dependencies'][] = 'ext.openlayers.main';
		} else {
			$mapsModuleAttrs['scripts'] = '/libs/PF_maps.js';
		}

		$resourceLoader->register( [ 'ext.pageforms.maps' => $mapsModuleAttrs ] );
	}

	/**
	 * Register the namespaces for Page Forms.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CanonicalNamespaces
	 *
	 * @since 2.4.1
	 *
	 * @param array &$list
	 */
	public static function registerNamespaces( array &$list ) {
		global $wgNamespacesWithSubpages;

		if ( !defined( 'PF_NS_FORM' ) ) {
			define( 'PF_NS_FORM', 106 );
			define( 'PF_NS_FORM_TALK', 107 );
		}

		$list[PF_NS_FORM] = 'Form';
		$list[PF_NS_FORM_TALK] = 'Form_talk';

		// Support subpages only for talk pages by default
		$wgNamespacesWithSubpages[PF_NS_FORM_TALK] = true;
	}

	static function registerFunctions( Parser $parser ) {
		$parser->setFunctionHook( 'default_form', [ 'PFDefaultForm', 'run' ] );
		$parser->setFunctionHook( 'forminput', [ 'PFFormInputParserFunction', 'run' ] );
		$parser->setFunctionHook( 'formlink', [ 'PFFormLink', 'run' ] );
		$parser->setFunctionHook( 'formredlink', [ 'PFFormRedLink', 'run' ] );
		$parser->setFunctionHook( 'queryformlink', [ 'PFQueryFormLink', 'run' ] );
		$parser->setFunctionHook( 'arraymap', [ 'PFArrayMap', 'run' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'arraymaptemplate', [ 'PFArrayMapTemplate', 'run' ], Parser::SFH_OBJECT_ARGS );

		$parser->setFunctionHook( 'autoedit', [ 'PFAutoEdit', 'run' ] );
		$parser->setFunctionHook( 'autoedit_rating', [ 'PFAutoEditRating', 'run' ] );
		$parser->setFunctionHook( 'template_params', [ 'PFTemplateParams', 'run' ] );
		$parser->setFunctionHook( 'template_display', [ 'PFTemplateDisplay', 'run' ], Parser::SFH_OBJECT_ARGS );
	}

	static function setGlobalJSVariables( &$vars ) {
		global $wgPageFormsTargetName;
		global $wgPageFormsAutocompleteValues, $wgPageFormsAutocompleteOnAllChars;
		global $wgPageFormsFieldProperties, $wgPageFormsCargoFields, $wgPageFormsDependentFields;
		global $wgPageFormsGridValues, $wgPageFormsGridParams;
		global $wgPageFormsCalendarValues, $wgPageFormsCalendarParams, $wgPageFormsCalendarHTML;
		global $wgPageFormsContLangYes, $wgPageFormsContLangNo, $wgPageFormsContLangMonths;
		global $wgPageFormsHeightForMinimizingInstances;
		global $wgPageFormsShowOnSelect, $wgPageFormsScriptPath;
		global $edgValues, $wgPageFormsEDSettings;
		global $wgAmericanDates;

		$vars['wgPageFormsTargetName'] = $wgPageFormsTargetName;
		$vars['wgPageFormsAutocompleteValues'] = $wgPageFormsAutocompleteValues;
		$vars['wgPageFormsAutocompleteOnAllChars'] = $wgPageFormsAutocompleteOnAllChars;
		$vars['wgPageFormsFieldProperties'] = $wgPageFormsFieldProperties;
		$vars['wgPageFormsCargoFields'] = $wgPageFormsCargoFields;
		$vars['wgPageFormsDependentFields'] = $wgPageFormsDependentFields;
		$vars['wgPageFormsCalendarValues'] = $wgPageFormsCalendarValues;
		$vars['wgPageFormsCalendarParams'] = $wgPageFormsCalendarParams;
		$vars['wgPageFormsCalendarHTML'] = $wgPageFormsCalendarHTML;
		$vars['wgPageFormsGridValues'] = $wgPageFormsGridValues;
		$vars['wgPageFormsGridParams'] = $wgPageFormsGridParams;
		$vars['wgPageFormsContLangYes'] = $wgPageFormsContLangYes;
		$vars['wgPageFormsContLangNo'] = $wgPageFormsContLangNo;
		$vars['wgPageFormsContLangMonths'] = $wgPageFormsContLangMonths;
		$vars['wgPageFormsHeightForMinimizingInstances'] = $wgPageFormsHeightForMinimizingInstances;
		$vars['wgPageFormsShowOnSelect'] = $wgPageFormsShowOnSelect;
		$vars['wgPageFormsScriptPath'] = $wgPageFormsScriptPath;
		if ( method_exists( 'EDParserFunctions', 'getAllValues' ) ) {
			// External Data 2.3+
			$vars['edgValues'] = EDParserFunctions::getAllValues();
		} else {
			$vars['edgValues'] = $edgValues;
		}
		$vars['wgPageFormsEDSettings'] = $wgPageFormsEDSettings;
		$vars['wgAmericanDates'] = $wgAmericanDates;
	}

	public static function registerPageSchemasClass() {
		global $wgPageSchemasHandlerClasses;
		$wgPageSchemasHandlerClasses[] = 'PFPageSchemas';
		return true;
	}

	public static function addToAdminLinks( &$admin_links_tree ) {
		$data_structure_label = wfMessage( 'pf-adminlinks-datastructure' )->escaped();
		$data_structure_section = $admin_links_tree->getSection( $data_structure_label );
		if ( $data_structure_section === null ) {
			$data_structure_section = new ALSection( wfMessage( 'pf-adminlinks-datastructure' )->escaped() );
		}

		$pf_row = new ALRow( 'pageforms' );
		$pf_row->addItem( ALItem::newFromSpecialPage( 'Categories' ) );
		$data_structure_section->addRow( $pf_row );
		$pf_admin_row = new ALRow( 'pageforms_admin' );
		$data_structure_section->addRow( $pf_admin_row );

		$admin_links_tree->addSection( $data_structure_section, wfMessage( 'adminlinks_browsesearch' )->escaped() );

		$pf_row->addItem( ALItem::newFromSpecialPage( 'Templates' ), 'Properties' );
		$pf_row->addItem( ALItem::newFromSpecialPage( 'Forms' ), 'SemanticStatistics' );
		$pf_row->addItem( ALItem::newFromSpecialPage( 'MultiPageEdit' ) );
		$pf_admin_row->addItem( ALItem::newFromSpecialPage( 'CreateClass' ), 'SMWAdmin' );
		if ( class_exists( 'PFCreateProperty' ) ) {
			$pf_admin_row->addItem( ALItem::newFromSpecialPage( 'CreateProperty' ), 'SMWAdmin' );
		}
		$pf_admin_row->addItem( ALItem::newFromSpecialPage( 'CreateTemplate' ), 'SMWAdmin' );
		$pf_admin_row->addItem( ALItem::newFromSpecialPage( 'CreateForm' ), 'SMWAdmin' );
		$pf_admin_row->addItem( ALItem::newFromSpecialPage( 'CreateCategory' ), 'SMWAdmin' );
	}

	public static function addToCargoTablesColumns( $cargoTablesPage, &$allowedActions ) {
		if ( !$cargoTablesPage->getUser()->isAllowed( 'multipageedit' ) ) {
			return;
		}

		$cargoTablesPage->getOutput()->addModuleStyles( [ 'oojs-ui.styles.icons-editing-core' ] );

		$editColumn = [ 'edit' => [ 'ooui-icon' => 'edit', 'ooui-title' => 'edit' ] ];
		$indexOfDrilldown = array_search( 'drilldown', array_keys( $allowedActions ) );
		$pos = $indexOfDrilldown === false ? count( $allowedActions ) : $indexOfDrilldown + 1;
		$allowedActions = array_merge( array_slice( $allowedActions, 0, $pos ), $editColumn, array_slice( $allowedActions, $pos ) );
	}

	/**
	 * Called by the CargoTablesActionLinks hook.
	 *
	 * Adds an "Edit" link to Special:CargoTables, pointing to Special:MultiPageEdit.
	 *
	 * @param array &$actionLinks Action links
	 * @param string $tableName Cargo table name
	 * @param bool $isReplacementTable Whether this table is a replacement table
	 * @param bool $hasReplacementTable Whether this table has a replacement table
	 * @param int[][] $templatesThatDeclareTables
	 * @param string[] $templatesThatAttachToTables
	 * @param User|null $user The current user
	 *
	 * @since 4.4
	 */
	public static function addToCargoTablesLinks( &$actionLinks, $tableName, $isReplacementTable, $hasReplacementTable, $templatesThatDeclareTables, $templatesThatAttachToTables, $user = null ) {
		// If it has a "replacement table", it's read-only and can't
		// be edited (though the replacement table can).
		if ( $hasReplacementTable ) {
			return;
		}

		// Check permissions.
		if ( $user == null ) {
			// For Cargo versions < 3.1.
			$user = RequestContext::getMain()->getUser();
		}

		if ( !$user->isAllowed( 'multipageedit' ) ) {
			return;
		}
		// Only put in an "Edit" link if there's exactly one template
		// for this Cargo table, and one form for that template.
		if ( !array_key_exists( $tableName, $templatesThatDeclareTables ) ) {
			return;
		}
		if ( array_key_exists( $tableName, $templatesThatAttachToTables ) ) {
			return;
		}
		$templateIDs = $templatesThatDeclareTables[$tableName];
		if ( count( $templateIDs ) > 1 ) {
			return;
		}

		$templateTitle = Title::newFromID( $templateIDs[0] );
		$templateName = $templateTitle->getText();
		if ( self::$mMultiPageEditPage == null ) {
			self::$mMultiPageEditPage = new PFMultiPageEdit();
			self::$mMultiPageEditPage->setTemplateList();
		}
		$formName = self::$mMultiPageEditPage->getFormForTemplate( $templateName );
		if ( $formName == null ) {
			return;
		}

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$sp = PFUtils::getSpecialPage( 'MultiPageEdit' );
		$editMsg = wfMessage( 'edit' )->text();
		$linkParams = [ 'template' => $templateName, 'form' => $formName ];
		$text = $linkRenderer->makeKnownLink( $sp->getPageTitle(), $editMsg, [], $linkParams );

		$indexOfDrilldown = array_search( 'drilldown', array_keys( $actionLinks ) );
		$pos = $indexOfDrilldown === false ? count( $actionLinks ) : $indexOfDrilldown + 1;
		$actionLinks = array_merge( array_slice( $actionLinks, 0, $pos ), [ 'edit' => $text ], array_slice( $actionLinks, $pos ) );
	}

	/**
	 * Called by the CargoTablesSetActionLinks hook.
	 *
	 * Adds an "Edit" link to Special:CargoTables, pointing to Special:MultiPageEdit.
	 *
	 * @param SpecialPage $cargoTablesPage
	 * @param array &$actionLinks Action links
	 * @param string $tableName Cargo table name
	 * @param bool $isReplacementTable Whether this table iss a replacement table
	 * @param bool $hasReplacementTable Whether this table has a replacement table
	 * @param int[][] $templatesThatDeclareTables
	 * @param string[] $templatesThatAttachToTables
	 * @param string[] $actionList
	 * @param User|null $user The current user
	 *
	 * @since 4.8.1
	 */
	public static function addToCargoTablesRow( $cargoTablesPage, &$actionLinks, $tableName, $isReplacementTable, $hasReplacementTable, $templatesThatDeclareTables, $templatesThatAttachToTables, $actionList, $user = null ) {
		$cargoTablesPage->getOutput()->addModuleStyles( [ 'oojs-ui.styles.icons-editing-core' ] );

		// For the sake of simplicity, this function basically just
		// wraps around the previous hook function, for Cargo <= 2.4.
		// That's why there's this awkward behavior of parsing links
		// to get their URL. Hopefully this won't cause problems.
		self::addToCargoTablesLinks( $actionLinks, $tableName, $isReplacementTable, $hasReplacementTable, $templatesThatDeclareTables, $templatesThatAttachToTables, $user );

		if ( array_key_exists( 'edit', $actionLinks ) ) {
			preg_match( '/href="(.*?)"/', $actionLinks['edit'], $matches );
			$mpeURL = html_entity_decode( $matches[1] );
			$actionLinks['edit'] = $cargoTablesPage->getActionButton( 'edit', $mpeURL );
		}
	}

	/**
	 * Disable TinyMCE if this is a form definition page, or a form-editable page.
	 *
	 * @param Title $title The page Title object
	 * @return bool Whether or not to disable TinyMCE
	 */
	public static function disableTinyMCE( $title ) {
		if ( $title->getNamespace() == PF_NS_FORM ) {
			return false;
		}

		$defaultForms = PFFormLinker::getDefaultFormsForPage( $title );
		if ( count( $defaultForms ) > 0 ) {
			return false;
		}

		return true;
	}

	public static function showFormPreview( EditPage $editpage, WebRequest $request ) {
		global $wgOut, $wgPageFormsFormPrinter;

		wfDebug( __METHOD__ . ": enter.\n" );

		// Exit if we're not in preview mode.
		if ( !$editpage->preview ) {
			return;
		}
		// Exit if we aren't in the "Form" namespace.
		if ( $editpage->getArticle()->getTitle()->getNamespace() != PF_NS_FORM ) {
			return;
		}

		// Needed in case there are any OOUI-based input types in the form.
		$wgOut->enableOOUI();

		$previewNote = $wgOut->parseAsInterface( wfMessage( 'pf-preview-note' )->text() );
		// The "pfForm" ID is there so the form JS will be activated.
		$editpage->previewTextAfterContent .= Html::element( 'h2', null, wfMessage( 'pf-preview-header' )->text() ) . "\n" .
			'<div id="pfForm" class="previewnote" style="font-weight: bold">' . $previewNote . "</div>\n<hr />\n";

		$form_definition = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $editpage->textbox1 );
		list( $form_text, $data_text, $form_page_title, $generated_page_name ) =
			$wgPageFormsFormPrinter->formHTML( $form_definition, null, false, null, null, "Page Forms form preview dummy title", null );

		$parserOutput = PFUtils::getParser()->getOutput();
		if ( method_exists( $wgOut, 'addParserOutputMetadata' ) ) {
			$wgOut->addParserOutputMetadata( $parserOutput );
		} else {
			$wgOut->addParserOutputNoText( $parserOutput );
		}

		PFUtils::addFormRLModules();
		$editpage->previewTextAfterContent .=
			'<div style="margin-top: 15px">' . $form_text . "</div>";
	}

	/**
	 * Called by the PageSaveComplete hook.
	 *
	 * Set a cookie after the page save so that a "Your edit was saved"
	 * popup will appear after form-based saves, just as it does after
	 * standard saves. This code will be called after all saves, which
	 * means that it will lead to redundant cookie-setting after normal
	 * saves. However, there doesn't appear to be a way to to set the
	 * cookie correctly only after form-based saves, unfortunately.
	 *
	 * @param WikiPage $wikiPage
	 * @param MediaWiki\User\UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param MediaWiki\Revision\RevisionRecord $revisionRecord
	 * @param MediaWiki\Storage\EditResult $editResult
	 */
	public static function setPostEditCookie( WikiPage $wikiPage, MediaWiki\User\UserIdentity $user, string $summary, int $flags,
		MediaWiki\Revision\RevisionRecord $revisionRecord, MediaWiki\Storage\EditResult $editResult
	) {
		// Have this take effect only if the save came from a form -
		// we need to use a global variable to determine that.
		global $wgPageFormsFormPrinter;
		if ( !property_exists( $wgPageFormsFormPrinter, 'mInputTypeHooks' ) ) {
			return;
		}

		// Code based loosely on EditPage::setPostEditCookie().
		$postEditKey = EditPage::POST_EDIT_COOKIE_KEY_PREFIX . $revisionRecord->getID();
		$response = RequestContext::getMain()->getRequest()->response();
		$response->setCookie( $postEditKey, 'saved', time() + EditPage::POST_EDIT_COOKIE_DURATION );
	}

}
