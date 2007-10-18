<?php

###
# This is the path to your installation of Semantic Forms as
# seen from the web. Change it if required ($wgScriptPath is the
# path to the base directory of your wiki). No final slash.
##
$sfgScriptPath = $wgScriptPath . '/extensions/SemanticForms';
##

###
# This is the path to your installation of Semantic Forms as
# seen on your local filesystem. Used against some PHP file path
# issues.
##
$sfgIP = $IP . '/extensions/SemanticForms';
##


// PHP fails to find relative includes at some level of inclusion:
//$pathfix = $IP . $sfgScriptPath;

// load global functions
require_once('SF_GlobalFunctions.php');

###
# If you already have custom namespaces on your site, insert
# $sfgNamespaceIndex = ???;
# into your LocalSettings.php *before* including this file.
# The number ??? must be the smallest even namespace number
# that is not in use yet. However, it must not be smaller
# than 150.
##
if (!isset($sfgNamespaceIndex)) {
        sffInitNamespaces(150);
} else {
        sffInitNamespaces();
}

###
# The number of allowed values per autocomplete - too many might
# slow down the database, and Javascript's completion
###
$sfgMaxAutocompleteValues = 1000;

// A temporary global variable, until we determine the issue with
// initialization of special pages
$sfgSpecialPagesSpecialInit = false;

?>
