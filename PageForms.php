<?php

/**
 * Note: When updating this file please also update extension.json with the same changes.
 */

/**
 * Default settings for Page Forms.
 *
 * @file
 * @ingroup PF
 */

/**
 * Forms for creating and editing wiki pages.
 *
 * @defgroup PF Page Forms
 */

/**
 * The module Form Inputs contains form input classes.
 * @defgroup PFFormInput Form Inputs
 * @ingroup PF
 */

/**
 * The module Special Pages contains all Special Pages defined by
 * Page Forms.
 *
 * @defgroup PFSpecialPages Special Pages
 * @ingroup PF
 */

if ( array_key_exists( 'wgWikimediaJenkinsCI', $GLOBALS ) ) {
	if ( file_exists( __DIR__ . '/../../vendor/autoload.php' ) ) {
		require_once __DIR__ . '/../../vendor/autoload.php';
	}
} elseif ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// In some versions of MW 1.25, there's a bug in which global variables
// set in LocalSettings.php do not override the settings in
// extension.json. For simplicity's sake, don't load extensions unless we're
// at version 1.27 or higher.
if ( version_compare( $GLOBALS['wgVersion'], '1.27c', '>' ) ) {
	if ( function_exists( 'wfLoadExtension' ) ) {
		wfLoadExtension( 'PageForms' );
		// Keep i18n globals so mergeMessageFileList.php doesn't break
		$GLOBALS['wgMessagesDirs']['PageForms'] = __DIR__ . '/i18n';
		$GLOBALS['wgExtensionMessagesFiles']['PageFormsAlias'] = __DIR__ . '/languages/PF_Aliases.php';
		$GLOBALS['wgExtensionMessagesFiles']['PageFormsMagic'] = __DIR__ . '/languages/PF_Magic.php';
		$GLOBALS['wgExtensionMessagesFiles']['PageFormsNS'] = __DIR__ . '/languages/PF_Namespaces.php';
		/* wfWarn(
			'Deprecated PHP entry point used for PageForms extension. ' .
			'Please use wfLoadExtension instead, ' .
			'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
		); */
		return;
	}
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( defined( 'PF_VERSION' ) ) {
	// Do not load Page Forms more than once.
	return 1;
}

define( 'PF_VERSION', '4.1.1' );

$GLOBALS['wgExtensionCredits']['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Page Forms',
	'version' => PF_VERSION,
	'author' => array( 'Yaron Koren', 'Stephan Gambke', '...' ),
	'url' => 'https://www.mediawiki.org/wiki/Extension:Page_Forms',
	'descriptionmsg' => 'pageforms-desc',
	'license-name' => 'GPL-2.0+'
);

# ##
# This is the path to your installation of Page Forms as
# seen from the web. Change it if required ($wgScriptPath is the
# path to the base directory of your wiki). No final slash.
# #
$GLOBALS['wgExtensionFunctions'][] = function() {
	$GLOBALS['wgPageFormsPartialPath'] = '/extensions/PageForms';
	$GLOBALS['wgPageFormsScriptPath'] = $GLOBALS['wgScriptPath'] . $GLOBALS['wgPageFormsPartialPath'];
};
# #

# ##
# This is the path to your installation of Page Forms as
# seen on your local filesystem. Used against some PHP file path
# issues.
# #
$GLOBALS['wgPageFormsIP'] = dirname( __FILE__ );
# #

// Sometimes this call needs to be delayed, and sometimes it shouldn't be
// delayed. Is it just the precense of SMW that dictates which one's the case??
if ( defined( 'SMW_VERSION' ) ) {
	$GLOBALS['wgExtensionFunctions'][] = function() {
		// This global variable is needed so that other extensions can
		// hook into it to add their own input types.
		$GLOBALS['wgPageFormsFormPrinter'] = new StubObject( 'wgPageFormsFormPrinter', 'PFFormPrinter' );
	};
} else {
	$GLOBALS['wgPageFormsFormPrinter'] = new StubObject( 'wgPageFormsFormPrinter', 'PFFormPrinter' );

}

$GLOBALS['wgHooks']['LinkEnd'][] = 'PFFormLinker::setBrokenLink';
// 'SkinTemplateNavigation' replaced 'SkinTemplateTabs' in the Vector skin
$GLOBALS['wgHooks']['SkinTemplateTabs'][] = 'PFFormEditAction::displayTab';
$GLOBALS['wgHooks']['SkinTemplateNavigation'][] = 'PFFormEditAction::displayTab2';
$GLOBALS['wgHooks']['SkinTemplateTabs'][] = 'PFHelperFormAction::displayTab';
$GLOBALS['wgHooks']['SkinTemplateNavigation'][] = 'PFHelperFormAction::displayTab2';
$GLOBALS['wgHooks']['ArticlePurge'][] = 'PFFormUtils::purgeCache';
$GLOBALS['wgHooks']['ParserFirstCallInit'][] = 'PFHooks::registerFunctions';
$GLOBALS['wgHooks']['MakeGlobalVariablesScript'][] = 'PFHooks::setGlobalJSVariables';
$GLOBALS['wgHooks']['PageSchemasRegisterHandlers'][] = 'PFPageSchemas::registerClass';
$GLOBALS['wgHooks']['EditPage::importFormData'][] = 'PFHooks::showFormPreview';
$GLOBALS['wgHooks']['CanonicalNamespaces'][] = 'PFHooks::registerNamespaces';
$GLOBALS['wgHooks']['UnitTestsList'][] = 'PFHooks::onUnitTestsList';
$GLOBALS['wgHooks']['ResourceLoaderRegisterModules'][] = 'PFHooks::registerModules';

if ( defined( 'SMW_VERSION' ) ) {
	// Admin Links hook needs to be called in a delayed way so that it
	// will always be called after SMW's Admin Links addition; as of
	// SMW 1.9, SMW delays calling all its hook functions.
	$GLOBALS['wgExtensionFunctions'][] = function() {
		$GLOBALS['wgHooks']['AdminLinks'][] = 'PFHooks::addToAdminLinks';
	};
} else {
	$GLOBALS['wgHooks']['AdminLinks'][] = 'PFHooks::addToAdminLinks';
}

// New "actions"
$GLOBALS['wgActions']['formedit'] = 'PFFormEditAction';
$GLOBALS['wgActions']['formcreate'] = 'PFHelperFormAction';

// API modules
$GLOBALS['wgAPIModules']['pfautocomplete'] = 'PFAutocompleteAPI';
$GLOBALS['wgAPIModules']['pfautoedit'] = 'PFAutoeditAPI';

// register all special pages and other classes
$GLOBALS['wgSpecialPages']['Forms'] = 'PFForms';
$GLOBALS['wgAutoloadClasses']['PFForms'] = __DIR__ . '/specials/PF_Forms.php';
$GLOBALS['wgSpecialPages']['CreateForm'] = 'PFCreateForm';
$GLOBALS['wgAutoloadClasses']['PFCreateForm'] = __DIR__ . '/specials/PF_CreateForm.php';
$GLOBALS['wgSpecialPages']['Templates'] = 'PFTemplates';
$GLOBALS['wgAutoloadClasses']['PFTemplates'] = __DIR__ . '/specials/PF_Templates.php';
$GLOBALS['wgSpecialPages']['CreateTemplate'] = 'PFCreateTemplate';
$GLOBALS['wgAutoloadClasses']['PFCreateTemplate'] = __DIR__ . '/specials/PF_CreateTemplate.php';
if ( defined( 'SMW_VERSION' ) ) {
	$GLOBALS['wgSpecialPages']['CreateProperty'] = 'PFCreateProperty';
	$GLOBALS['wgAutoloadClasses']['PFCreateProperty'] = __DIR__ . '/specials/PF_CreateProperty.php';
}
$GLOBALS['wgSpecialPages']['CreateClass'] = 'PFCreateClass';
$GLOBALS['wgAutoloadClasses']['PFCreateClass'] = __DIR__ . '/specials/PF_CreateClass.php';
$GLOBALS['wgSpecialPages']['CreateCategory'] = 'PFCreateCategory';
$GLOBALS['wgAutoloadClasses']['PFCreateCategory'] = __DIR__ . '/specials/PF_CreateCategory.php';
$GLOBALS['wgSpecialPages']['FormStart'] = 'PFFormStart';
$GLOBALS['wgAutoloadClasses']['PFFormStart'] = __DIR__ . '/specials/PF_FormStart.php';
$GLOBALS['wgSpecialPages']['FormEdit'] = 'PFFormEdit';
$GLOBALS['wgAutoloadClasses']['PFFormEdit'] = __DIR__ . '/specials/PF_FormEdit.php';
$GLOBALS['wgSpecialPages']['RunQuery'] = 'PFRunQuery';
$GLOBALS['wgAutoloadClasses']['PFRunQuery'] = __DIR__ . '/specials/PF_RunQuery.php';
$GLOBALS['wgSpecialPages']['UploadWindow'] = 'PFUploadWindow';
$GLOBALS['wgAutoloadClasses']['PFUploadForm'] = __DIR__ . '/specials/PF_UploadForm.php';
$GLOBALS['wgAutoloadClasses']['PFUploadSourceField'] = __DIR__ . '/specials/PF_UploadSourceField.php';
$GLOBALS['wgAutoloadClasses']['PFUploadWindow'] = __DIR__ . '/specials/PF_UploadWindow.php';
$GLOBALS['wgAutoloadClasses']['PFTemplateField'] = __DIR__ . '/includes/PF_TemplateField.php';
$GLOBALS['wgAutoloadClasses']['TemplatesPage'] = __DIR__ . '/specials/PF_Templates.php';
$GLOBALS['wgAutoloadClasses']['FormsPage'] = __DIR__ . '/specials/PF_Forms.php';
$GLOBALS['wgAutoloadClasses']['PFForm'] = __DIR__ . '/includes/PF_Form.php';
$GLOBALS['wgAutoloadClasses']['PFTemplate'] = __DIR__ . '/includes/PF_Template.php';
$GLOBALS['wgAutoloadClasses']['PFTemplateInForm'] = __DIR__ . '/includes/PF_TemplateInForm.php';
$GLOBALS['wgAutoloadClasses']['PFFormField'] = __DIR__ . '/includes/PF_FormField.php';
$GLOBALS['wgAutoloadClasses']['PFFormPrinter'] = __DIR__ . '/includes/PF_FormPrinter.php';
$GLOBALS['wgAutoloadClasses']['PFFormUtils'] = __DIR__ . '/includes/PF_FormUtils.php';
$GLOBALS['wgAutoloadClasses']['PFUtils'] = __DIR__ . '/includes/PF_Utils.php';
$GLOBALS['wgAutoloadClasses']['PFValuesUtils'] = __DIR__ . '/includes/PF_ValuesUtils.php';
$GLOBALS['wgAutoloadClasses']['PFHooks'] = __DIR__ . '/includes/PF_Hooks.php';
$GLOBALS['wgAutoloadClasses']['PFFormLinker'] = __DIR__ . '/includes/PF_FormLinker.php';
$GLOBALS['wgAutoloadClasses']['PFPageSchemas'] = __DIR__ . '/includes/PF_PageSchemas.php';
$GLOBALS['wgAutoloadClasses']['PFParserFunctions'] = __DIR__ . '/includes/PF_ParserFunctions.php';
$GLOBALS['wgAutoloadClasses']['PFAutocompleteAPI'] = __DIR__ . '/includes/PF_AutocompleteAPI.php';
$GLOBALS['wgAutoloadClasses']['PFAutoeditAPI'] = __DIR__ . '/includes/PF_AutoeditAPI.php';
$GLOBALS['wgAutoloadClasses']['PFFormEditAction'] = __DIR__ . '/includes/PF_FormEditAction.php';
$GLOBALS['wgAutoloadClasses']['PFHelperFormAction'] = __DIR__ . '/includes/PF_HelperFormAction.php';
$GLOBALS['wgAutoloadClasses']['PFPageSection'] = __DIR__ . '/includes/PF_PageSection.php';

// Form inputs
$GLOBALS['wgAutoloadClasses']['PFFormInput'] = __DIR__ . '/includes/forminputs/PF_FormInput.php';
$GLOBALS['wgAutoloadClasses']['PFTextInput'] = __DIR__ . '/includes/forminputs/PF_TextInput.php';
$GLOBALS['wgAutoloadClasses']['PFTextWithAutocompleteInput'] = __DIR__ . '/includes/forminputs/PF_TextWithAutocompleteInput.php';
$GLOBALS['wgAutoloadClasses']['PFTextAreaInput'] = __DIR__ . '/includes/forminputs/PF_TextAreaInput.php';
$GLOBALS['wgAutoloadClasses']['PFTextAreaWithAutocompleteInput'] = __DIR__ . '/includes/forminputs/PF_TextAreaWithAutocompleteInput.php';
$GLOBALS['wgAutoloadClasses']['PFEnumInput'] = __DIR__ . '/includes/forminputs/PF_EnumInput.php';
$GLOBALS['wgAutoloadClasses']['PFMultiEnumInput'] = __DIR__ . '/includes/forminputs/PF_MultiEnumInput.php';
$GLOBALS['wgAutoloadClasses']['PFCheckboxInput'] = __DIR__ . '/includes/forminputs/PF_CheckboxInput.php';
$GLOBALS['wgAutoloadClasses']['PFCheckboxesInput'] = __DIR__ . '/includes/forminputs/PF_CheckboxesInput.php';
$GLOBALS['wgAutoloadClasses']['PFRadioButtonInput'] = __DIR__ . '/includes/forminputs/PF_RadioButtonInput.php';
$GLOBALS['wgAutoloadClasses']['PFDropdownInput'] = __DIR__ . '/includes/forminputs/PF_DropdownInput.php';
$GLOBALS['wgAutoloadClasses']['PFListBoxInput'] = __DIR__ . '/includes/forminputs/PF_ListBoxInput.php';
$GLOBALS['wgAutoloadClasses']['PFComboBoxInput'] = __DIR__ . '/includes/forminputs/PF_ComboBoxInput.php';
$GLOBALS['wgAutoloadClasses']['PFDateInput'] = __DIR__ . '/includes/forminputs/PF_DateInput.php';
$GLOBALS['wgAutoloadClasses']['PFDatePickerInput'] = __DIR__ . '/includes/forminputs/PF_DatePickerInput.php';
$GLOBALS['wgAutoloadClasses']['PFTimePickerInput'] = __DIR__ . '/includes/forminputs/PF_TimePickerInput.php';
$GLOBALS['wgAutoloadClasses']['PFDateTimePicker'] = __DIR__ . '/includes/forminputs/PF_DateTimePicker.php';
$GLOBALS['wgAutoloadClasses']['PFDateTimeInput'] = __DIR__ . '/includes/forminputs/PF_DateTimeInput.php';
$GLOBALS['wgAutoloadClasses']['PFYearInput'] = __DIR__ . '/includes/forminputs/PF_YearInput.php';
$GLOBALS['wgAutoloadClasses']['PFTreeInput'] = __DIR__ . '/includes/forminputs/PF_TreeInput.php';
$GLOBALS['wgAutoloadClasses']['PFTree'] = __DIR__ . '/includes/forminputs/PF_Tree.php';
$GLOBALS['wgAutoloadClasses']['PFTokensInput'] = __DIR__ . '/includes/forminputs/PF_TokensInput.php';
$GLOBALS['wgAutoloadClasses']['PFGoogleMapsInput'] = __DIR__ . '/includes/forminputs/PF_GoogleMapsInput.php';
$GLOBALS['wgAutoloadClasses']['PFOpenLayersInput'] = __DIR__ . '/includes/forminputs/PF_OpenLayersInput.php';
$GLOBALS['wgAutoloadClasses']['PFRegExpInput'] = __DIR__ . '/includes/forminputs/PF_RegExpInput.php';
$GLOBALS['wgAutoloadClasses']['PFRatingInput'] = __DIR__ . '/includes/forminputs/PF_RatingInput.php';

$GLOBALS['wgAutoloadClasses']['PFWikiPage'] = __DIR__ . '/includes/wikipage/PF_WikiPage.php';
$GLOBALS['wgAutoloadClasses']['PFWikiPageTemplate'] = __DIR__ . '/includes/wikipage/PF_WikiPageTemplate.php';
$GLOBALS['wgAutoloadClasses']['PFWikiPageTemplateParam'] = __DIR__ . '/includes/wikipage/PF_WikiPageTemplateParam.php';
$GLOBALS['wgAutoloadClasses']['PFWikiPageSection'] = __DIR__ . '/includes/wikipage/PF_WikiPageSection.php';
$GLOBALS['wgAutoloadClasses']['PFWikiPageFreeText'] = __DIR__ . '/includes/wikipage/PF_WikiPageFreeText.php';

$GLOBALS['wgJobClasses']['createPage'] = 'PFCreatePageJob';
$GLOBALS['wgAutoloadClasses']['PFCreatePageJob'] = __DIR__ . '/includes/PF_CreatePageJob.php';

$GLOBALS['wgMessagesDirs']['PageForms'] = __DIR__ . '/i18n';
$GLOBALS['wgExtensionMessagesFiles']['PageForms'] = __DIR__ . '/languages/PF_Messages.php';
$GLOBALS['wgExtensionMessagesFiles']['PageFormsAlias'] = __DIR__ . '/languages/PF_Aliases.php';
$GLOBALS['wgExtensionMessagesFiles']['PageFormsMagic'] = __DIR__ . '/languages/PF_Magic.php';
$GLOBALS['wgExtensionMessagesFiles']['PageFormsNS'] = __DIR__ . '/languages/PF_Namespaces.php';

// Allow for popup windows for file upload
$GLOBALS['wgEditPageFrameOptions'] = 'SAMEORIGIN';

// Register client-side modules.
$wgPageFormsResourceTemplate = array(
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'PageForms'
);
$GLOBALS['wgResourceModules'] += array(
	'ext.pageforms.main' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/PageForms.js',
			'libs/PF_preview.js'
		),
		'styles' => array(
			'skins/PageForms.css',
			'skins/PF_jquery_ui_overrides.css',
		),
		'dependencies' => array(
			'jquery.ui.core',
			'jquery.ui.autocomplete',
			'jquery.ui.button',
			'jquery.ui.sortable',
			'jquery.ui.widget',
			'ext.pageforms.fancybox',
			'ext.pageforms.autogrow',
			'mediawiki.util',
			'ext.pageforms.select2',
		),
		'messages' => array(
			'pf_formerrors_header',
			'pf_too_few_instances_error',
			'pf_too_many_instances_error',
			'pf_blank_error',
			'pf_not_unique_error',
			'pf_bad_url_error',
			'pf_bad_email_error',
			'pf_bad_number_error',
			'pf_bad_date_error',
			'pf_pipe_error',
		),
	),
	'ext.pageforms.browser' => $wgPageFormsResourceTemplate + array(
		'scripts' => 'libs/jquery.browser.js',
	),
	'ext.pageforms.fancybox' => $wgPageFormsResourceTemplate + array(
		'scripts' => 'libs/jquery.fancybox.js',
		'styles' => 'skins/jquery.fancybox.css',
		'dependencies' => array( 'ext.pageforms.browser' ),
	),
	'ext.pageforms.dynatree' => $wgPageFormsResourceTemplate + array(
		'dependencies' => array( 'jquery.ui.widget' ),
		'scripts' => array(
			'libs/jquery.dynatree.js',
			'libs/PF_dynatree.js',
		),
		'styles' => 'skins/ui.dynatree.css',
	),
	'ext.pageforms.autogrow' => $wgPageFormsResourceTemplate + array(
		'scripts' => 'libs/PF_autogrow.js',
	),
	'ext.pageforms.popupformedit' => $wgPageFormsResourceTemplate + array(
		'scripts' => 'libs/PF_popupform.js',
		'styles' => 'skins/PF_popupform.css',
		'dependencies' => array( 'ext.pageforms.browser' ),
	),
	'ext.pageforms.autoedit' => $wgPageFormsResourceTemplate + array(
		'scripts' => 'libs/PF_autoedit.js',
		'styles' => 'skins/PF_autoedit.css',
		'messages' => array(
			'pf-autoedit-wait',
			'pf_autoedit_anoneditwarning',
		),
	),
	'ext.pageforms.submit' => $wgPageFormsResourceTemplate + array(
		'scripts' => 'libs/PF_submit.js',
		'styles' => 'skins/PF_submit.css',
		'messages' => array(
			'pf_formedit_saveandcontinue_summary',
			'pf_formedit_saveandcontinueediting',
		),
	),
	'ext.pageforms.collapsible' => $wgPageFormsResourceTemplate + array(
		'scripts' => 'libs/PF_collapsible.js',
		'styles' => 'skins/PF_collapsible.css',
	),
	'ext.pageforms.imagepreview' => $wgPageFormsResourceTemplate + array(
		'scripts' => 'libs/PF_imagePreview.js',
	),
	'ext.pageforms.checkboxes' => $wgPageFormsResourceTemplate + array(
		'scripts' => 'libs/PF_checkboxes.js',
		'styles' => 'skins/PF_checkboxes.css',
		'messages' => array(
			'pf_forminputs_checkboxes_select_all',
			'pf_forminputs_checkboxes_select_none',
		),
	),
	'ext.pageforms.datepicker' => $wgPageFormsResourceTemplate + array(
		'scripts'      => 'libs/PF_datepicker.js',
		'dependencies' => array(
			'jquery.ui.datepicker',
			'ext.pageforms.main'
		),
		'position' => 'bottom', // MW 1.26
	),
	'ext.pageforms.timepicker' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/PF_timepicker.js',
		),
		'styles' => 'skins/PF_Timepicker.css',
		'position' => 'bottom', // MW 1.26
	),
	'ext.pageforms.datetimepicker' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/PF_datetimepicker.js',
		),
		'dependencies' => array(
			'ext.pageforms.datepicker',
			'ext.pageforms.timepicker'
		),
		'position' => 'bottom', // MW 1.26
	),
	'ext.pageforms.regexp' => $wgPageFormsResourceTemplate + array(
		'scripts' => 'libs/PF_regexp.js',
		'dependencies' => array(
			'ext.pageforms.main'
		),
	),
	'ext.pageforms.rating' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/jquery.rateyo.js',
			'libs/PF_rating.js'
		),
		'styles' => 'skins/jquery.rateyo.css',
	),
	'ext.pageforms.simpleupload' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/PF_simpleupload.js'
		),
        'messages' => array(
			'pf_forminputs_change_file',
			'upload-dialog-button-upload'
		),
	),
	'ext.pageforms.select2' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/select2.js',
			'libs/ext.pf.select2.base.js',
			'libs/ext.pf.select2.combobox.js',
			'libs/ext.pf.select2.tokens.js',
		),
		'styles' => array(
			'skins/select2/select2.css',
			'skins/select2/select2-bootstrap.css',
			'skins/ext.pf.select2.css',
		),
		'dependencies' => array(
			'ext.pageforms',
			'mediawiki.jqueryMsg',
		),
		'messages' => array(
			'pf-select2-no-matches',
			'pf-select2-searching',
			'pf-select2-input-too-short',
			'pf-select2-selection-too-big',
		),
	),
	'ext.pageforms.jsgrid' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/jsgrid.js',
			'libs/PF_jsGrid.js',
		),
		'styles' => array(
			'skins/jsgrid/jsgrid.css',
			'skins/jsgrid/theme.css',
		),
		'dependencies' => array(
			'jquery.ui.sortable',
		),
		'messages' => array(
			'htmlform-yes',
			'htmlform-no',
		),
	),
	'ext.pageforms.balloon' => $wgPageFormsResourceTemplate + array(
		'styles' => array(
			'skins/balloon.css',
		),
	),
	'ext.pageforms' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/ext.pf.js',
		),
	),
	'ext.pageforms.PF_CreateProperty' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/PF_CreateProperty.js',
		),
	),
	'ext.pageforms.PF_PageSchemas' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/PF_PageSchemas.js',
		),
	),
	'ext.pageforms.PF_CreateTemplate' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/PF_CreateTemplate.js',
		),
		'messages' => array(
			'pf_blank_error',
		),
	),
	'ext.pageforms.PF_CreateClass' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/PF_CreateClass.js',
		),
	),
	'ext.pageforms.PF_CreateForm' => $wgPageFormsResourceTemplate + array(
		'scripts' => array(
			'libs/PF_CreateForm.js',
		),
		'messages' => array(
			'pf_blank_error',
		),
	),
);

// PHP fails to find relative includes at some level of inclusion:
// $pathfix = $IP . $GLOBALS['wgPageFormsScriptPath'];

# ##
# The number of allowed values per autocomplete - too many might
# slow down the database, and Javascript's completion.
# ##
$GLOBALS['wgPageFormsMaxAutocompleteValues'] = 1000;

# ##
# The number of allowed values for local autocomplete - after which
# it will switch to remote autocompletion.
# ##
$GLOBALS['wgPageFormsMaxLocalAutocompleteValues'] = 100;

# ##
# Whether to autocomplete on all characters in a string, not just the
# beginning of words - this is especially important for Unicode strings,
# since the use of the '\b' regexp character to match on the beginnings
# of words fails for them.
# ##
$GLOBALS['wgPageFormsAutocompleteOnAllChars'] = false;

# ##
# Used for caching of autocompletion values.
# ##
$GLOBALS['wgPageFormsCacheAutocompleteValues'] = false;
$GLOBALS['wgPageFormsAutocompleteCacheTimeout'] = null;

# ##
# Global variables for handling the two edit tabs (for traditional editing
# and for editing with a form):
# $GLOBALS['wgPageFormsRenameEditTabs'] renames the edit-with-form tab to just "Edit", and
#   the traditional-editing tab, if it is visible, to "Edit source", in
#   whatever language is being used.
# $GLOBALS['wgPageFormsRenameMainEditTab'] renames only the traditional editing tab, to
#   "Edit source".
# The wgGroupPermissions 'viewedittab' setting dictates which types of
# visitors will see the "Edit" tab, for pages that are editable by form -
# by default all will see it.
# ##
$GLOBALS['wgPageFormsRenameEditTabs'] = false;
$GLOBALS['wgPageFormsRenameMainEditTab'] = false;
$GLOBALS['wgGroupPermissions']['*']['viewedittab'] = true;
$GLOBALS['wgAvailableRights'][] = 'viewedittab';

# ##
# Permission to edit form fields defined as 'restricted'
# ##
$GLOBALS['wgGroupPermissions']['sysop']['editrestrictedfields'] = true;
$GLOBALS['wgAvailableRights'][] = 'editrestrictedfields';

# ##
# Permission to view, and create pages with, Special:CreateClass
# ##
$GLOBALS['wgGroupPermissions']['user']['createclass'] = true;
$GLOBALS['wgAvailableRights'][] = 'createclass';

# ##
# List separator character
# ##
$GLOBALS['wgPageFormsListSeparator'] = ",";

# ##
# Use 24-hour time format in forms, e.g. 15:30 instead of 3:30 PM
# ##
$GLOBALS['wgPageForms24HourTime'] = false;

# ##
# Cache parsed form definitions in the page_props table, to improve loading
# speed
# ##
$GLOBALS['wgPageFormsCacheFormDefinitions'] = false;

/**
 * The cache type for storing form definitions. This cache is similar in
 * function to the parser cache. Is is used to store form data which is
 * expensive to regenerate, and benefits from having plenty of storage space.
 *
 * If this setting remains at null the setting for the $wgParserCacheType will
 * be used.
 *
 * For available types see $wgMainCacheType.
 */
$GLOBALS['wgPageFormsFormCacheType'] = null;

# ##
# Point all red links to "action=formedit", instead of "action=edit", so
# that users can choose which form to use to create each new page.
# ##
$GLOBALS['wgPageFormsLinkAllRedLinksToForms'] = false;

# ##
# Show the "create with form" tab for uncreated templates and categories.
# ##
$GLOBALS['wgPageFormsShowTabPForAllHelperForms'] = true;

# ##
# Displays the form above, instead of below, the results, in the
# Special:RunQuery page.
# (This is actually an undocumented variable, used by the code.)
# ##
$GLOBALS['wgPageFormsRunQueryFormAtTop'] = false;

$GLOBALS['wgPageFormsGoogleMapsKey'] = null;

// Include default settings for form inputs
require_once 'includes/PF_DatePickerSettings.php';

# ##
# Display displaytitle page property instead of page title for Page type fields
# ##
$GLOBALS['wgPageFormsUseDisplayTitle'] = false;

// Other variables
$GLOBALS['wgPageFormsSimpleUpload'] = false;
$GLOBALS['wgPageFormsDisableOutsideServices'] = false;

# ##
# Global variables for Javascript
# ##
$GLOBALS['wgPageFormsShowOnSelect'] = array();
$GLOBALS['wgPageFormsAutocompleteValues'] = array();
$GLOBALS['wgPageFormsGridValues'] = array();
$GLOBALS['wgPageFormsGridParams'] = array();
// SMW
$GLOBALS['wgPageFormsFieldProperties'] = array();
// Cargo
$GLOBALS['wgPageFormsCargoFields'] = array();
$GLOBALS['wgPageFormsDependentFields'] = array();

/**
 * Minimum number of values in a checkboxes field to show the 'Select all'/'Select none' switches
 */
$GLOBALS['wgPageFormsCheckboxesSelectAllMinimum'] = 10;

// Necessary setting for SMW 1.9+
$GLOBALS['smwgEnabledSpecialPage'][] = 'RunQuery';
