<?php
/**
 * Static functions called by various outside hooks, as well as by
 * extension.json.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

class PFHooks {

	// Used for caching by addToCargoTablesLinks().
	private static $mMultiPageEditPage = null;

	public static function registerExtension() {
		if ( defined( 'PF_VERSION' ) ) {
			// Do not load Page Forms more than once.
			return 1;
		}

		define( 'PF_VERSION', '4.4.2' );

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
		$GLOBALS['wgPageFormsPartialPath'] = '/extensions/PageForms';
		$GLOBALS['wgPageFormsScriptPath'] = $GLOBALS['wgScriptPath'] . $GLOBALS['wgPageFormsPartialPath'];

		// We have to have this hook called here, instead of in
		// extension.json, because it's conditional.
		if ( class_exists( 'MediaWiki\Linker\LinkRenderer' ) ) {
			// MW 1.28+
			$GLOBALS['wgHooks']['HtmlPageLinkRendererEnd'][] = 'PFFormLinker::setBrokenLink';
		} else {
			$GLOBALS['wgHooks']['LinkEnd'][] = 'PFFormLinker::setBrokenLinkOld';
		}

		// Admin Links hook needs to be called in a delayed way so that it
		// will always be called after SMW's Admin Links addition; as of
		// SMW 1.9, SMW delays calling all its hook functions.
		$GLOBALS['wgHooks']['AdminLinks'][] = 'PFHooks::addToAdminLinks';

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

		if ( version_compare( $GLOBALS['wgVersion'], '1.26c', '>' ) && ExtensionRegistry::getInstance()->isLoaded( 'OpenLayers' ) ) {
			$resourceLoader->register( array(
				'ext.pageforms.maps' => array(
					'localBasePath' => $pageFormsDir,
					'remoteExtPath' => 'PageForms',
					'scripts' => '/libs/PF_maps.offline.js',
					'dependencies' => array(
						'ext.openlayers.main',
					),
				),
			) );
		} else {
			$resourceLoader->register( array(
				'ext.pageforms.maps' => array(
					'localBasePath' => $pageFormsDir,
					'remoteExtPath' => 'PageForms',
					'scripts' => '/libs/PF_maps.js',
				),
			) );
		}

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

	static function registerFunctions( &$parser ) {
		$parser->setFunctionHook( 'default_form', array( 'PFParserFunctions', 'renderDefaultForm' ) );
		$parser->setFunctionHook( 'forminput', array( 'PFParserFunctions', 'renderFormInput' ) );
		$parser->setFunctionHook( 'formlink', array( 'PFParserFunctions', 'renderFormLink' ) );
		$parser->setFunctionHook( 'formredlink', array( 'PFParserFunctions', 'renderFormRedLink' ) );
		$parser->setFunctionHook( 'queryformlink', array( 'PFParserFunctions', 'renderQueryFormLink' ) );
		$parser->setFunctionHook( 'arraymap', array( 'PFParserFunctions', 'renderArrayMap' ), Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'arraymaptemplate', array( 'PFParserFunctions', 'renderArrayMapTemplate' ), Parser::SFH_OBJECT_ARGS );

		$parser->setFunctionHook( 'autoedit', array( 'PFParserFunctions', 'renderAutoEdit' ) );

		return true;
	}

	static function setGlobalJSVariables( &$vars ) {
		global $wgPageFormsAutocompleteValues, $wgPageFormsAutocompleteOnAllChars;
		global $wgPageFormsFieldProperties, $wgPageFormsCargoFields, $wgPageFormsDependentFields;
		global $wgPageFormsGridValues, $wgPageFormsGridParams;
		global $wgPageFormsContLangYes, $wgPageFormsContLangNo, $wgPageFormsContLangMonths;
		global $wgPageFormsShowOnSelect, $wgPageFormsScriptPath;
		global $edgValues, $wgPageFormsEDSettings;
		global $wgAmericanDates;
		// global $wgPageFormsInitJSFunctions, $wgPageFormsValidationJSFunctions;

		$vars['wgPageFormsAutocompleteValues'] = $wgPageFormsAutocompleteValues;
		$vars['wgPageFormsAutocompleteOnAllChars'] = $wgPageFormsAutocompleteOnAllChars;
		$vars['wgPageFormsFieldProperties'] = $wgPageFormsFieldProperties;
		$vars['wgPageFormsCargoFields'] = $wgPageFormsCargoFields;
		$vars['wgPageFormsDependentFields'] = $wgPageFormsDependentFields;
		$vars['wgPageFormsGridValues'] = $wgPageFormsGridValues;
		$vars['wgPageFormsGridParams'] = $wgPageFormsGridParams;
		$vars['wgPageFormsContLangYes'] = $wgPageFormsContLangYes;
		$vars['wgPageFormsContLangNo'] = $wgPageFormsContLangNo;
		$vars['wgPageFormsContLangMonths'] = $wgPageFormsContLangMonths;
		$vars['wgPageFormsShowOnSelect'] = $wgPageFormsShowOnSelect;
		$vars['wgPageFormsScriptPath'] = $wgPageFormsScriptPath;
		$vars['edgValues'] = $edgValues;
		$vars['wgPageFormsEDSettings'] = $wgPageFormsEDSettings;
		$vars['wgAmericanDates'] = $wgAmericanDates;
		// $vars['wgPageFormsInitJSFunctions'] = $wgPageFormsInitJSFunctions;
		// $vars['wgPageFormsValidationJSFunctions'] = $wgPageFormsValidationJSFunctions;

		return true;
	}

	public static function registerProperty( $id, $typeid, $label ) {
		if ( class_exists( 'SMWDIProperty' ) ) {
			SMWDIProperty::registerProperty( $id, $typeid, $label, true );
		} else {
			SMWPropertyValue::registerProperty( $id, $typeid, $label, true );
		}
	}

	public static function addToAdminLinks( &$admin_links_tree ) {
		$data_structure_label = wfMessage( 'smw_adminlinks_datastructure' )->text();
		$data_structure_section = $admin_links_tree->getSection( $data_structure_label );
		if ( is_null( $data_structure_section ) ) {
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

	/**
	 * Called by the CargoTablesActionLinks hook.
	 *
	 * Adds an "Edit" link to Special:CargoTables, pointing to Special:MultiPageEdit.
	 *
	 * @param array &$actionLinks Action links
	 * @param string $tableName Cargo table name
	 * @param bool $isReplacementTable Whether this table iss a replacement table
	 * @param bool $hasReplacementTable Whether this table has a replacement table
	 * @param string[] $templatesThatDeclareTables An array
	 * @param string[] $templatesThatAttachToTables An array
	 *
	 * @return bool
	 *
	 * @since 4.4
	 */
	public static function addToCargoTablesLinks( &$actionLinks, $tableName, $isReplacementTable, $hasReplacementTable, $templatesThatDeclareTables, $templatesThatAttachToTables ) {
		global $wgUser;

		// If it has a "replacement table", it's read-only and can't
		// be edited (though the replacement table can).
		if ( $hasReplacementTable ) {
			return true;
		}

		// Check permissions.
		if ( !$wgUser->isAllowed( 'multipageedit' ) ) {
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
			self::$mMultiPageEditPage = new SpreadsheetTemplatesPage();
		}
		$formName = self::$mMultiPageEditPage->getFormForTemplate( $templateName );
		if ( $formName == null ) {
			return true;
		}

		$sp = SpecialPageFactory::getPage( 'MultiPageEdit' );
		$editMsg = wfMessage( 'edit' )->text();
		$text = PFUtils::makeLink( $linkRenderer = null, $sp->getTitle(), $editMsg, array(),
			array( "template" => $templateName, "form" => $formName ) );

		$indexOfDrilldown = array_search( 'drilldown', array_keys( $actionLinks ) );
		$pos = false === $indexOfDrilldown ? count( $array ) : $indexOfDrilldown + 1;
		$actionLinks = array_merge( array_slice( $actionLinks, 0, $pos ), array( 'edit' => $text ), array_slice( $actionLinks, $pos ) );
		return true;
	}

	/**
	 * Disable TinyMCE if this is a form definition page, or a form-editable page.
	 *
	 * @param Title $title The page Title object
	 * @return Whether or not to disable TinyMCE
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
		global $wgOut, $wgParser, $wgPageFormsFormPrinter;

		wfDebug( __METHOD__ . ": enter.\n" );

		// Exit if we're not in preview mode.
		if ( !$editpage->preview ) {
			return true;
		}
		// Exit if we aren't in the "Form" namespace.
		if ( $editpage->getArticle()->getTitle()->getNamespace() != PF_NS_FORM ) {
			return true;
		}

		$editpage->previewTextAfterContent .= Html::element( 'h2', null, wfMessage( 'pf-preview-header' )->text() ) . "\n" .
			'<div class="previewnote" style="font-weight: bold">' . $wgOut->parse( wfMessage( 'pf-preview-note' )->text() ) . "</div>\n<hr />\n";

		$form_definition = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $editpage->textbox1 );
		list( $form_text, $data_text, $form_page_title, $generated_page_name ) =
			$wgPageFormsFormPrinter->formHTML( $form_definition, null, false, null, null, "Page Forms form preview dummy title", null );

		$parserOutput = $wgParser->getOutput();
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
	 * Called by the PageContentSaveComplete hook.
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
	public static function setPostEditCookie( &$wikiPage, &$user, $content, $summary, $isMinor, $isWatch, $section, &$flags, $revision, &$status, $baseRevId, $undidRevId = 0 ) {
		if ( $revision == null ) {
			return true;
		}
		// Code based on EditPage::setPostEditCookie().
		$postEditKey = EditPage::POST_EDIT_COOKIE_KEY_PREFIX . $revision->getID();
		$response = RequestContext::getMain()->getRequest()->response();
		$response->setCookie( $postEditKey, 'saved', time() + EditPage::POST_EDIT_COOKIE_DURATION );
		return true;
	}

	/**
	 * Hook to add PHPUnit test cases.
	 * From https://www.mediawiki.org/wiki/Manual:PHP_unit_testing/Writing_unit_tests_for_extensions
	 *
	 * @param string[] &$files
	 * @return bool
	 */
	public static function onUnitTestsList( &$files ) {
		$testDir = dirname( __DIR__ ) . '/tests/phpunit/includes';
		$files = array_merge( $files, glob( "$testDir/*Test.php" ) );
		return true;
	}
}
