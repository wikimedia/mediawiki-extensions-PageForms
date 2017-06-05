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

	public static function registerExtension() {
		if ( defined( 'PF_VERSION' ) ) {
			// Do not load Page Forms more than once.
			return 1;
		}

		define( 'PF_VERSION', '4.1.2' );

		$GLOBALS['wgPageFormsIP'] = dirname( __DIR__ ) . '/../';

		/**
		 * This is a delayed init that makes sure that MediaWiki is set
		 * up properly before we add our stuff.
		 */

		// This global variable is needed so that other
		// extensions can hook into it to add their own
		// input types.

		if ( defined( 'SMW_VERSION' ) ) {
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

		if ( class_exists( 'WikiEditorHooks' ) ) {
			$resourceLoader->register( array(
				'ext.pageforms.wikieditor' => array(
					'localBasePath' => $pageFormsDir,
					'remoteExtPath' => 'PageForms',
					'scripts' => '/libs/PF_wikieditor.js',
					'styles' => '/skins/PF_wikieditor.css',
					'dependencies' => array(
						'ext.pageforms.main',
						'jquery.wikiEditor'
					),
				),
			) );
		}

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
	 * @param array $list
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
		global $wgPageFormsShowOnSelect, $wgPageFormsScriptPath;
		global $edgValues, $wgPageFormsEDSettings;
		//global $wgPageFormsInitJSFunctions, $wgPageFormsValidationJSFunctions;

		$vars['wgPageFormsAutocompleteValues'] = $wgPageFormsAutocompleteValues;
		$vars['wgPageFormsAutocompleteOnAllChars'] = $wgPageFormsAutocompleteOnAllChars;
		$vars['wgPageFormsFieldProperties'] = $wgPageFormsFieldProperties;
		$vars['wgPageFormsCargoFields'] = $wgPageFormsCargoFields;
		$vars['wgPageFormsDependentFields'] = $wgPageFormsDependentFields;
		$vars['wgPageFormsGridValues'] = $wgPageFormsGridValues;
		$vars['wgPageFormsGridParams'] = $wgPageFormsGridParams;
		$vars['wgPageFormsShowOnSelect'] = $wgPageFormsShowOnSelect;
		$vars['wgPageFormsScriptPath'] = $wgPageFormsScriptPath;
		$vars['edgValues'] = $edgValues;
		$vars['wgPageFormsEDSettings'] = $wgPageFormsEDSettings;
		//$vars['wgPageFormsInitJSFunctions'] = $wgPageFormsInitJSFunctions;
		//$vars['wgPageFormsValidationJSFunctions'] = $wgPageFormsValidationJSFunctions;

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
			//$smw_docu_row = new ALRow( 'smw_docu' );
			//$data_structure_section->addRow( $smw_docu_row );
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
		list ( $form_text, $data_text, $form_page_title, $generated_page_name ) =
			$wgPageFormsFormPrinter->formHTML( $form_definition, null, false, null, null, "Page Forms form preview dummy title", null );

		$parserOutput = $wgParser->getOutput();
		if( method_exists( $wgOut, 'addParserOutputMetadata' ) ){
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
	 * Hook to add PHPUnit test cases.
	 * From https://www.mediawiki.org/wiki/Manual:PHP_unit_testing/Writing_unit_tests_for_extensions
	 *
	 * @return boolean
	 */
	public static function onUnitTestsList( &$files ) {
		$testDir = dirname( __DIR__ ) . '/tests/phpunit/includes';
		$files = array_merge( $files, glob( "$testDir/*Test.php" ) );
		return true;
	}
}
