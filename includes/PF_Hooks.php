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

		define( 'PF_VERSION', '5.3.4' );

		$GLOBALS['wgPageFormsIP'] = dirname( __DIR__ ) . '/../';

		/**
		 * This is a delayed init that makes sure that MediaWiki is set
		 * up properly before we add our stuff.
		 */

		if ( defined( 'SMW_VERSION' ) || ExtensionRegistry::getInstance()->isLoaded( 'SemanticMediaWiki' ) ) {
			$GLOBALS['wgSpecialPages']['CreateProperty'] = 'PFCreateProperty';
			$GLOBALS['wgAutoloadClasses']['PFCreateProperty'] = __DIR__ . '/../specials/PF_CreateProperty.php';
		}

		// Allow for popup windows for file upload
		$GLOBALS['wgEditPageFrameOptions'] = 'SAMEORIGIN';

		// Necessary setting for SMW 1.9+
		$GLOBALS['smwgEnabledSpecialPage'][] = 'RunQuery';
	}

	public static function initialize() {
		global $wgHooks;

		$GLOBALS['wgPageFormsScriptPath'] = $GLOBALS['wgExtensionAssetsPath'] . '/PageForms';

		if ( class_exists( 'MediaWiki\HookContainer\HookContainer' ) ) {
			// MW 1.35+
			$wgHooks['PageSaveComplete'][] = 'PFHooks::setPostEditCookie';
			$wgHooks['MultiContentSave'][] = 'PFFormUtils::purgeCache2';
		} else {
			$wgHooks['PageContentSaveComplete'][] = 'PFHooks::setPostEditCookieOld';
			$wgHooks['PageContentSave'][] = 'PFFormUtils::purgeCache';
		}
		// Admin Links hook needs to be called in a delayed way so that it
		// will always be called after SMW's Admin Links addition; as of
		// SMW 1.9, SMW delays calling all its hook functions.
		$wgHooks['AdminLinks'][] = 'PFHooks::addToAdminLinks';

		// This global variable is needed so that other
		// extensions can hook into it to add their own
		// input types.
		$GLOBALS['wgPageFormsFormPrinter'] = new StubObject( 'wgPageFormsFormPrinter', 'PFFormPrinter' );
	}

	/**
	 * ResourceLoaderRegisterModules hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 *
	 * @param ResourceLoader &$resourceLoader The ResourceLoader object
	 * @return bool Always true
	 */
	public static function registerModules( ResourceLoader &$resourceLoader ) {
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

		return true;
	}

	/**
	 * Register the namespaces for Page Forms.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CanonicalNamespaces
	 *
	 * @since 2.4.1
	 *
	 * @param array &$list
	 *
	 * @return true
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

		return true;
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
		$parser->setFunctionHook( 'template_params', [ 'PFTemplateParams', 'run' ] );
		$parser->setFunctionHook( 'template_display', [ 'PFTemplateDisplay', 'run' ], Parser::SFH_OBJECT_ARGS );

		return true;
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

		return true;
	}

	public static function addToAdminLinks( &$admin_links_tree ) {
		$data_structure_label = wfMessage( 'smw_adminlinks_datastructure' )->text();
		$data_structure_section = $admin_links_tree->getSection( $data_structure_label );
		if ( $data_structure_section === null ) {
			$data_structure_section = new ALSection( wfMessage( 'pf-adminlinks-datastructure' )->text() );

			// If we are here, it most likely means that SMW is
			// not installed. Still, we'll refer to everything as
			// SMW, to make the rest of the code more
			// straightforward.
			$smw_row = new ALRow( 'smw' );
			$smw_row->addItem( ALItem::newFromSpecialPage( 'Categories' ) );
			$data_structure_section->addRow( $smw_row );
			$smw_admin_row = new ALRow( 'smw_admin' );
			$data_structure_section->addRow( $smw_admin_row );

			// If SMW is not installed, don't bother with a "links
			// to the documentation" row - it would only have one
			// link.
			// $smw_docu_row = new ALRow( 'smw_docu' );
			// $data_structure_section->addRow( $smw_docu_row );
			$admin_links_tree->addSection( $data_structure_section, wfMessage( 'adminlinks_browsesearch' )->text() );
		} else {
			$smw_row = $data_structure_section->getRow( 'smw' );
			$smw_admin_row = $data_structure_section->getRow( 'smw_admin' );
			$smw_docu_row = $data_structure_section->getRow( 'smw_docu' );
		}
		$smw_row->addItem( ALItem::newFromSpecialPage( 'Templates' ), 'Properties' );
		$smw_row->addItem( ALItem::newFromSpecialPage( 'Forms' ), 'SemanticStatistics' );
		$smw_row->addItem( ALItem::newFromSpecialPage( 'MultiPageEdit' ) );
		$smw_admin_row->addItem( ALItem::newFromSpecialPage( 'CreateClass' ), 'SMWAdmin' );
		if ( class_exists( 'PFCreateProperty' ) ) {
			$smw_admin_row->addItem( ALItem::newFromSpecialPage( 'CreateProperty' ), 'SMWAdmin' );
		}
		$smw_admin_row->addItem( ALItem::newFromSpecialPage( 'CreateTemplate' ), 'SMWAdmin' );
		$smw_admin_row->addItem( ALItem::newFromSpecialPage( 'CreateForm' ), 'SMWAdmin' );
		$smw_admin_row->addItem( ALItem::newFromSpecialPage( 'CreateCategory' ), 'SMWAdmin' );
		if ( isset( $smw_docu_row ) ) {
			$pf_name = wfMessage( 'specialpages-group-pf_group' )->text();
			$pf_docu_label = wfMessage( 'adminlinks_documentation', $pf_name )->text();
			$smw_docu_row->addItem( ALItem::newFromExternalLink( "https://www.mediawiki.org/wiki/Extension:Page_Forms", $pf_docu_label ) );
		}

		return true;
	}

	public static function addToCargoTablesColumns( $cargoTablesPage, &$allowedActions ) {
		if ( !$cargoTablesPage->getUser()->isAllowed( 'multipageedit' ) ) {
			return true;
		}

		$cargoTablesPage->getOutput()->addModuleStyles( [ 'oojs-ui.styles.icons-editing-core' ] );

		$editColumn = [ 'edit' => [ 'ooui-icon' => 'edit', 'ooui-title' => 'edit' ] ];
		$indexOfDrilldown = array_search( 'drilldown', array_keys( $allowedActions ) );
		$pos = $indexOfDrilldown === false ? count( $allowedActions ) : $indexOfDrilldown + 1;
		$allowedActions = array_merge( array_slice( $allowedActions, 0, $pos ), $editColumn, array_slice( $allowedActions, $pos ) );

		return true;
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
	 * @return bool
	 *
	 * @since 4.4
	 */
	public static function addToCargoTablesLinks( &$actionLinks, $tableName, $isReplacementTable, $hasReplacementTable, $templatesThatDeclareTables, $templatesThatAttachToTables, $user = null ) {
		// If it has a "replacement table", it's read-only and can't
		// be edited (though the replacement table can).
		if ( $hasReplacementTable ) {
			return true;
		}

		// Check permissions.
		if ( $user == null ) {
			// For Cargo versions < 3.1.
			$user = RequestContext::getMain()->getUser();
		}

		if ( !$user->isAllowed( 'multipageedit' ) ) {
			return true;
		}
		// Only put in an "Edit" link if there's exactly one template
		// for this Cargo table, and one form for that template.
		if ( !array_key_exists( $tableName, $templatesThatDeclareTables ) ) {
			return true;
		}
		if ( array_key_exists( $tableName, $templatesThatAttachToTables ) ) {
			return true;
		}
		$templateIDs = $templatesThatDeclareTables[$tableName];
		if ( count( $templateIDs ) > 1 ) {
			return true;
		}

		$templateTitle = Title::newFromID( $templateIDs[0] );
		$templateName = $templateTitle->getText();
		if ( self::$mMultiPageEditPage == null ) {
			self::$mMultiPageEditPage = new PFMultiPageEdit();
			self::$mMultiPageEditPage->setTemplateList();
		}
		$formName = self::$mMultiPageEditPage->getFormForTemplate( $templateName );
		if ( $formName == null ) {
			return true;
		}

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$sp = PFUtils::getSpecialPage( 'MultiPageEdit' );
		$editMsg = wfMessage( 'edit' )->text();
		$linkParams = [ 'template' => $templateName, 'form' => $formName ];
		$text = $linkRenderer->makeKnownLink( $sp->getPageTitle(), $editMsg, [], $linkParams );

		$indexOfDrilldown = array_search( 'drilldown', array_keys( $actionLinks ) );
		$pos = $indexOfDrilldown === false ? count( $actionLinks ) : $indexOfDrilldown + 1;
		$actionLinks = array_merge( array_slice( $actionLinks, 0, $pos ), [ 'edit' => $text ], array_slice( $actionLinks, $pos ) );
		return true;
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
	 * @return bool
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

		return true;
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
			return true;
		}
		// Exit if we aren't in the "Form" namespace.
		if ( $editpage->getArticle()->getTitle()->getNamespace() != PF_NS_FORM ) {
			return true;
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

		return true;
	}

	/**
	 * Called by the PageContentSaveComplete hook; used for MW < 1.35.
	 *
	 * Set a cookie after the page save so that a "Your edit was saved"
	 * popup will appear after form-based saves, just as it does after
	 * standard saves. This code will be called after all saves, which
	 * means that it will lead to redundant cookie-setting after normal
	 * saves. However, there doesn't appear to be a way to to set the
	 * cookie correctly only after form-based saves, unfortunately.
	 *
	 * @param WikiPage &$wikiPage The page modified
	 * @param User &$user User performing the modification
	 * @param Content $content New content
	 * @param string $summary Edit summary/comment
	 * @param bool $isMinor Whether or not the edit was marked as minor
	 * @param bool $isWatch No longer used
	 * @param bool $section No longer used
	 * @param int[] &$flags Flags passed to WikiPage::doEditContent()
	 * @param Revision $revision Revision object of the saved content (or null)
	 * @param Status &$status Status object about to be returned by doEditContent()
	 * @param int $baseRevId The rev ID (or false) this edit was based on
	 * @param int $undidRevId The rev ID this edit undid (default 0)
	 *
	 * @return bool
	 */
	public static function setPostEditCookieOld( &$wikiPage, &$user, $content, $summary, $isMinor, $isWatch, $section, &$flags, $revision, &$status, $baseRevId, $undidRevId = 0 ) {
		if ( $revision == null ) {
			return true;
		}

		// Have this take effect only if the save came from a form -
		// we need to use a global variable to determine that.
		global $wgPageFormsFormPrinter;
		if ( !property_exists( $wgPageFormsFormPrinter, 'mInputTypeHooks' ) ) {
			return true;
		}

		// Code based on EditPage::setPostEditCookie().
		$postEditKey = EditPage::POST_EDIT_COOKIE_KEY_PREFIX . $revision->getID();
		$response = RequestContext::getMain()->getRequest()->response();
		$response->setCookie( $postEditKey, 'saved', time() + EditPage::POST_EDIT_COOKIE_DURATION );
		return true;
	}

	/**
	 * Called by the PageSaveComplete hook; used for MW >= 1.35.
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
	 * @return bool
	 */
	public static function setPostEditCookie( WikiPage $wikiPage, MediaWiki\User\UserIdentity $user, string $summary, int $flags,
		MediaWiki\Revision\RevisionRecord $revisionRecord, MediaWiki\Storage\EditResult $editResult
	) {
		// Have this take effect only if the save came from a form -
		// we need to use a global variable to determine that.
		global $wgPageFormsFormPrinter;
		if ( !property_exists( $wgPageFormsFormPrinter, 'mInputTypeHooks' ) ) {
			return true;
		}

		// Code based loosely on EditPage::setPostEditCookie().
		$postEditKey = EditPage::POST_EDIT_COOKIE_KEY_PREFIX . $revisionRecord->getID();
		$response = RequestContext::getMain()->getRequest()->response();
		$response->setCookie( $postEditKey, 'saved', time() + EditPage::POST_EDIT_COOKIE_DURATION );
		return true;
	}

}
