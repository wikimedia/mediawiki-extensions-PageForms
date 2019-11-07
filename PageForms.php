<?php
/**
 * Initialization file for Page Forms.
 *
 * @ingroup Page Forms
 * @author Yaron Koren
 */

if ( array_key_exists( 'wgWikimediaJenkinsCI', $GLOBALS ) ) {
	if ( file_exists( __DIR__ . '/../../vendor/autoload.php' ) ) {
		require_once __DIR__ . '/../../vendor/autoload.php';
	}
} elseif ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

wfLoadExtension( 'PageForms' );
// Keep i18n globals so mergeMessageFileList.php doesn't break
$GLOBALS['wgMessagesDirs']['PageForms'] = __DIR__ . '/i18n';
$GLOBALS['wgExtensionMessagesFiles']['PageFormsAlias'] = __DIR__ . '/languages/PF_Aliases.php';
$GLOBALS['wgExtensionMessagesFiles']['PageFormsMagic'] = __DIR__ . '/languages/PF_Magic.php';
$GLOBALS['wgExtensionMessagesFiles']['PageFormsNS'] = __DIR__ . '/languages/PF_Namespaces.php';
/* wfWarn(
	'Deprecated PHP entry point used for Page Forms extension. ' .
	'Please use wfLoadExtension instead, ' .
	'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
); */
