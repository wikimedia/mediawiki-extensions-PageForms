<?php
/**
 * Global functions and constants for Semantic Forms.
 *
 * @author Yaron Koren
 * @author Harold Solbrig
 * @author Louis Gerbarg
 */

define('SF_VERSION','0.7.9');

// constants for special properties
define('SF_SP_HAS_DEFAULT_FORM', 1);
define('SF_SP_HAS_ALTERNATE_FORM', 2);

$wgExtensionFunctions[] = 'sfgSetupExtension';
$wgExtensionFunctions[] = 'sfgParserFunctions';
$wgHooks['LanguageGetMagic'][] = 'sfgLanguageGetMagic';

require_once($sfgIP . '/includes/SF_ParserFunctions.php');
require_once($sfgIP . '/languages/SF_Language.php');

/**
 *  Do the actual intialisation of the extension. This is just a delayed init that makes sure
 *  MediaWiki is set up properly before we add our stuff.
 */
function sfgSetupExtension() {
	global $sfgVersion, $sfgNamespace, $sfgIP, $wgHooks, $wgExtensionCredits, $wgArticlePath, $wgScriptPath, $wgServer;

	sffInitMessages();

	/**********************************************/
	/***** register specials                  *****/
	/**********************************************/

	require_once($sfgIP . '/specials/SF_Forms.php');
	require_once($sfgIP . '/specials/SF_CreateForm.php');
	require_once($sfgIP . '/specials/SF_Templates.php');
	require_once($sfgIP . '/specials/SF_CreateTemplate.php');
	require_once($sfgIP . '/specials/SF_CreateProperty.php');
	require_once($sfgIP . '/specials/SF_CreateCategory.php');
	require_once($sfgIP . '/specials/SF_AddPage.php');
	require_once($sfgIP . '/specials/SF_AddData.php');
	require_once($sfgIP . '/specials/SF_EditData.php');

	/**********************************************/
	/***** register hooks                     *****/
	/**********************************************/

	require_once($sfgIP . '/includes/SF_FormEditTab.php');

	/**********************************************/
	/***** create globals for outside hooks   *****/
	/**********************************************/

	global $sfgFormPrinter;
	$sfgFormPrinter = new SFFormPrinter();

	/**********************************************/
	/***** credits (see "Special:Version")    *****/
	/**********************************************/
	$wgExtensionCredits['specialpage'][]= array('name'=>'Semantic Forms', 'version'=>SF_VERSION, 'author'=>'Yaron Koren and others',
          'url'=>'http://www.mediawiki.org/wiki/Extension:Semantic_Forms', 'description' => 'Forms for adding and editing semantic data');

	return true;
}

/**********************************************/
/***** namespace settings                 *****/
/**********************************************/

	/**
	 * Init the additional namespaces used by Semantic Forms. The
	 * parameter denotes the least unused even namespace ID that is
	 * greater or equal to 100.
	 */
	function sffInitNamespaces() {
		global $sfgNamespaceIndex, $wgExtraNamespaces, $wgNamespaceAliases, $wgNamespacesWithSubpages, $wgLanguageCode, $sfgContLang;

		if (!isset($sfgNamespaceIndex)) {
			$sfgNamespaceIndex = 106;
		}

		define('SF_NS_FORM',       $sfgNamespaceIndex);
		define('SF_NS_FORM_TALK',  $sfgNamespaceIndex+1);

		sffInitContentLanguage($wgLanguageCode);

		// Register namespace identifiers
		if (!is_array($wgExtraNamespaces)) { $wgExtraNamespaces=array(); }
		$wgExtraNamespaces = $wgExtraNamespaces + $sfgContLang->getNamespaces();
		// this code doesn't work, for some reason - leave it out for now
		//$wgNamespaceAliases = $wgNamespaceAliases + $sfgContLang->getNamespaceAliases();

		// Support subpages only for talk pages by default
		$wgNamespacesWithSubpages = $wgNamespacesWithSubpages + array(
			      SF_NS_FORM_TALK => true
		);
	}

/**********************************************/
/***** language settings                  *****/
/**********************************************/

	/**
	 * Initialise a global language object for content language. This
	 * must happen early on, even before user language is known, to
	 * determine labels for additional namespaces. In contrast, messages
	 * can be initialised much later when they are actually needed.
	 */
	function sffInitContentLanguage($langcode) {
		global $sfgIP, $sfgContLang;

		if (!empty($sfgContLang)) { return; }

		$sfContLangClass = 'SF_Language' . str_replace( '-', '_', ucfirst( $langcode ) );

		if (file_exists($sfgIP . '/languages/'. $sfContLangClass . '.php')) {
			include_once( $sfgIP . '/languages/'. $sfContLangClass . '.php' );
		}

		// fallback if language not supported
		if ( !class_exists($sfContLangClass)) {
			include_once($sfgIP . '/languages/SF_LanguageEn.php');
			$sfContLangClass = 'SF_LanguageEn';
		}

		$sfgContLang = new $sfContLangClass();
	}

	/**
	 * Initialise the global language object for user language. This
	 * must happen after the content language was initialised, since
	 * this language is used as a fallback.
	 */
	function sffInitUserLanguage($langcode) {
		global $sfgIP, $sfgLang;

		if (!empty($sfgLang)) { return; }

		$sfLangClass = 'SF_Language' . str_replace( '-', '_', ucfirst( $langcode ) );

		if (file_exists($sfgIP . '/languages/'. $sfLangClass . '.php')) {
			include_once( $sfgIP . '/languages/'. $sfLangClass . '.php' );
		}

		// fallback if language not supported
		if ( !class_exists($sfLangClass)) {
			global $sfgContLang;
			$sfgLang = $sfgContLang;
		} else {
			$sfgLang = new $sfLangClass();
		}
	}

	/**
	 * Initialise messages. These settings must be applied later on, since
	 * the MessageCache does not exist yet when the settings are loaded in
	 * LocalSettings.php.
	 */
	function sffInitMessages() {
		global $sfgMessagesInPlace; // record whether the function was already called
		if ($sfgMessagesInPlace) { return; }

		global $wgMessageCache, $sfgContLang, $sfgLang, $wgContLang, $wgLang;
		// make sure that language objects exist
		sffInitContentLanguage($wgContLang->getCode());
		sffInitUserLanguage($wgLang->getCode());

		$wgMessageCache->addMessages($sfgContLang->getContentMsgArray(), $wgContLang->getCode());
		$wgMessageCache->addMessages($sfgLang->getUserMsgArray(), $wgLang->getCode());

		$sfgMessagesInPlace = true;
	}

/**********************************************/
/***** other global helpers               *****/
/**********************************************/

	/**
	 * Creates HTML linking to a wiki page
	 */
	function sffLinkText($namespace, $name, $text = NULL) {
 		global $wgContLang;

		$inText = $wgContLang->getNsText($namespace) . ':' . $name;
		$title = Title::newFromText( $inText );
		if ($title === NULL) {
			return $inText; // TODO maybe report an error here?
		} 
		if ( NULL === $text ) $text = $title->getText();
		$l = new Linker();
		return $l->makeLinkObj($title, $text);
	}

	/**
	 * Creates the name of the page that appears in the URL;
	 * this method is necessary because Title::getPartialURL(), for
	 * some reason, doesn't include the namespace
	 */
	function sffTitleURLString($title) {
		$namespace = wfUrlencode( $title->getNsText() );
		if ( '' != $namespace ) {
			$namespace .= ':';
		}
		return ($namespace . ucfirst($title->getPartialURL()));
	}

	/**
	 * A very similar function, to get the non-URL-encoded title string
	 */
	function sffTitleString($title) {
		$namespace = $title->getNsText();
		if ( '' != $namespace ) {
			$namespace .= ':';
		}
		return ($namespace . ucfirst($title->getText()));
	}

	/**
	 * Prints the mini-form contained at the bottom of various pages, that
	 * allows pages to spoof a normal edit page, that can preview, save,
	 * etc.
	 */
	function sffPrintRedirectForm($title, $page_contents, $edit_summary, $is_save, $is_preview, $is_diff, $is_minor_edit, $watch_this) {
		$article = new Article($title);
		$new_url = $title->getLocalURL('action=submit');
		$starttime = wfTimestampNow();
		$edittime = $article->getTimestamp();
		global $wgUser;
		if ( $wgUser->isLoggedIn() )
			$token = htmlspecialchars($wgUser->editToken());
		else
			$token = EDIT_TOKEN_SUFFIX;

		if ($is_save)
			$action = "wpSave";
		elseif ($is_preview)
			$action = "wpPreview";
		else // $is_diff
			$action = "wpDiff";

		$text =<<<END
	<form id="editform" name="editform" method="post" action="$new_url">
	<input type="hidden" name="wpTextbox1" id="wpTextbox1" value="$page_contents" />
	<input type="hidden" name="wpSummary" value="$edit_summary" />
	<input type="hidden" name="wpStarttime" value="$starttime" />
	<input type="hidden" name="wpEdittime" value="$edittime" />
	<input type="hidden" name="wpEditToken" value="$token" />
	<input type="hidden" name="$action" />

END;
		if ($is_minor_edit)
			$text .= '    <input type="hidden" name="wpMinoredit">' . "\n";
		if ($watch_this)
			$text .= '    <input type="hidden" name="wpWatchthis">' . "\n";
		$text .=<<<END
	</form>
	<script type="text/javascript">
	document.editform.submit();
	</script>

END;
		return $text;
	}

	/**
	 * Gets the default form specified, if any, for a specific page
	 * (which should be a category, relation, or namespace page)
	 */
	function sffGetDefaultForm($page_title, $page_namespace) {
		$smw_version = SMW_VERSION;
		if ($smw_version{0} == '0') {
			return sffGetDefaultForm_0_7($page_title, $page_namespace);
		} else {
			return sffGetDefaultForm_1_0($page_title, $page_namespace);
		}
	}

	// Version for SMW 0.7 and lower
	function sffGetDefaultForm_0_7($page_title, $page_namespace) {
		global $sfgContLang;
		$db = wfGetDB( DB_SLAVE );
		$sf_props = $sfgContLang->getSpecialPropertiesArray();
		$default_form_relation = str_replace(' ', '_', $sf_props[SF_SP_HAS_DEFAULT_FORM]);
		$sql = "SELECT DISTINCT object_title FROM {$db->tableName('smw_relations')} " .
		  "WHERE subject_title = '" . $db->strencode($page_title) .
		  "' AND subject_namespace = '" . $page_namespace .
		  "' AND (relation_title = '" . $db->strencode($default_form_relation) . "'";
		// try aliases for SF_SP_HAS_DEFAULT_FORM, too
		foreach ($sfgContLang->getSpecialPropertyAliases() as $alias => $property) {
			if ($property == SF_SP_HAS_DEFAULT_FORM) {
				$sql .= " OR relation_title = '" . str_replace(' ', '_', $alias) . "'";
			}
		}
		$sql .= ") AND object_namespace = " . SF_NS_FORM;
		$res = $db->query( $sql );
		if ($db->numRows( $res ) > 0) {
			$row = $db->fetchRow($res);
			$form_name = $row[0];
			return $form_name;
		}
		$db->freeResult($res);
		return null;
	}

	// Version for SMW 1.0 and higher
	function sffGetDefaultForm_1_0($page_title, $page_namespace) {
		if ($page_title == NULL)
			return null;

		global $sfgContLang;
		$store = smwfGetStore();
		$title = Title::newFromText($page_title, $page_namespace);
		$sf_props = $sfgContLang->getSpecialPropertiesArray();
		$default_form_property = str_replace(' ', '_', $sf_props[SF_SP_HAS_DEFAULT_FORM]);
		$property = Title::newFromText($default_form_property, SF_NS_FORM);
		$res = $store->getPropertyValues($title, $property);
		$num = count($res);
		if ($num > 0) {
			$form_name = $res[0]->getTitle()->getText();
			return $form_name;
		}
		// if that didn't work, try any aliases that may exist
		// for SF_SP_HAS_DEFAULT_FORM
		$sf_props_aliases = $sfgContLang->getSpecialPropertyAliases();
		foreach ($sf_props_aliases as $alias => $prop_code) {
			if ($prop_code == SF_SP_HAS_DEFAULT_FORM) {
				$property = Title::newFromText($alias, SF_NS_FORM);
				$res = $store->getPropertyValues($title, $property);
				$num = count($res);
				if ($num > 0) {
					$form_name = $res[0]->getTitle()->getText();
					return $form_name;
				}
			}
		}
		return null;
	}

	/**
	 * Gets the alternate forms specified, if any, for a specific page
	 * (which, for now, should always be a relation)
	 */
	function sffGetAlternateForms($page_title, $page_namespace) {
		$smw_version = SMW_VERSION;
		if ($smw_version{0} == '0') {
			return sffGetAlternateForms_0_7($page_title, $page_namespace);
		} else {
			return sffGetAlternateForms_1_0($page_title, $page_namespace);
		}
	}

	// Version for SMW 0.7 and lower
	function sffGetAlternateForms_0_7($page_title, $page_namespace) {
		global $sfgContLang;
		$db = wfGetDB( DB_SLAVE );
		$sf_props = $sfgContLang->getSpecialPropertiesArray();
		$alternate_form_relation = str_replace(' ', '_', $sf_props[SF_SP_HAS_ALTERNATE_FORM]);
		$sql = "SELECT DISTINCT object_title FROM {$db->tableName('smw_relations')} " .
		  "WHERE subject_title = '" . $db->strencode($page_title) .
		  "' AND subject_namespace = '" . $page_namespace .
		  "' AND (relation_title = '" . $db->strencode($alternate_form_relation);
		// try English version too, if this is in another language
		if ($alternate_form_relation != "Has_alternate_form") {
			$sql .= "' OR relation_title = 'Has_alternate_form";
		}
		$sql .= "') AND object_namespace = " . SF_NS_FORM;
		$res = $db->query( $sql );
		$form_names = array();
		while ($row = $db->fetchRow($res)) {
			$form_names[] = $row[0];
		}
		$db->freeResult($res);
		return $form_names;
	}

	// Version for SMW 1.0 and higher
	function sffGetAlternateForms_1_0($page_title, $page_namespace) {
		if ($page_title == NULL)
			return null;

		global $sfgContLang;
		$store = smwfGetStore();
		$title = Title::newFromText($page_title, $page_namespace);
		$sf_props = $sfgContLang->getSpecialPropertiesArray();
		$alternate_form_property = str_replace(' ', '_', $sf_props[SF_SP_HAS_ALTERNATE_FORM]);
		$property = Title::newFromText($alternate_form_property, SF_NS_FORM);
		$props = $store->getPropertyValues($title, $property);
		$form_names = array();
		foreach ($props as $prop) {
			$form_names[] = str_replace(' ', '_', $prop->getTitle()->getText());
		}
		// try the English version too, if this isn't in English
		if ($alternate_form_property != "Has_alternate_form") {
			$property = Title::newFromText("Has_alternate_form", SF_NS_FORM);
			$props = $store->getPropertyValues($title, $property);
			foreach ($props as $prop) {
				$form_names[] = str_replace(' ', '_', $prop->getTitle()->getText());
			}
		}
		return $form_names;
	}

	/**
	 * Helper function for sffAddDataLink() - gets 'default form' and
	 * 'alternate form' relations/properties, and creates the
	 * corresponding 'add data' link, for a page, if any such
	 * relation/properties are defined
	 */
	function sffGetAddDataLinkForPage($target_page_title, $page_title, $page_namespace) {
		$form_name = sffGetDefaultForm($page_title, $page_namespace);
		$alt_forms = sffGetAlternateForms($page_title, $page_namespace);
		if (! $form_name && count($alt_forms) == 0)
			return null;
		$ad = SpecialPage::getPage('AddData');
		if ($form_name)
			$add_data_url = $ad->getTitle()->getFullURL() . "/" . $form_name . "/" . sffTitleURLString($target_page_title);
		else
			$add_data_url = $ad->getTitle()->getFullURL() . "/" . sffTitleURLString($target_page_title);
		foreach ($alt_forms as $i => $alt_form) {
			$add_data_url .= ($i == 0) ? "?" : "&";
			$add_data_url .= "alt_form[$i]=$alt_form";
		}
		return $add_data_url;
	}

	/**
	 * Gets URL for form-based adding of a nonexistent (red-linked) page
	 */
	function sffAddDataLink($title) {
		$smw_version = SMW_VERSION;
		if ($smw_version{0} == '0') {
			return sffAddDataLink_0_7($title);
		} else {
			return sffAddDataLink_1_0($title);
		}
	}

	// Version for SMW 1.0 and higher
	function sffAddDataLink_1_0($title) {
		// get all properties pointing to this page, and if
		// sffGetAddDataLinkForPage() returns a value with any of
		// them, return that
		$store = smwfGetStore();
		$title_text = sffTitleString($title);
		$value = SMWDataValueFactory::newTypeIDValue('_wpg', $title_text);
		$incoming_properties = $store->getInProperties($value);
		foreach ($incoming_properties as $property) {
			if ($add_data_link = sffGetAddDataLinkForPage($title, $property->getText(), SMW_NS_PROPERTY)) {
				return $add_data_link;
			}
		}

		// if that didn't work, check if this page's namespace
		// has a default form specified
		$namespace = $title->getNsText();
		if ('' === $namespace) {
			// if it's in the main (blank) namespace, check for
			// the file named with the word for "Main" in this
			// language
			$namespace = wfMsgForContent('sf_blank_namespace');
		}
		if ($add_data_link = sffGetAddDataLinkForPage($title, $namespace, NS_PROJECT)) {
			return $add_data_link;
		}
		// if nothing found still, return null
		return null;
	}

	// Version for SMW 0.7 and lower
	function sffAddDataLink_0_7($title) {
		// get all relations that have this page as an object,
		// and see if any of them have a default form specified
		$db = wfGetDB( DB_SLAVE );
		$sql = "SELECT DISTINCT relation_title FROM {$db->tableName('smw_relations')} WHERE object_title = '" . $db->strencode($title->getDBkey()) . "' AND object_namespace = '" . $title->getNamespace() . "'";
		$res = $db->query( $sql );
		if ($db->numRows( $res ) > 0) {
			while ($row = $db->fetchRow($res)) {
				$relation = $row[0];
				if ($add_data_link = sffGetAddDataLinkForPage($title, $relation, SMW_NS_RELATION)) {
					return $add_data_link;
				}
			}
		}
		// if that didn't work, check if this page's namespace
		// has a default form specified
		$namespace = $title->getNsText();
		if ('' === $namespace) {
			// if it's in the main (blank) namespace, check for
			// the file named with the word for "Main" in this
			// language
			$namespace = wfMsgForContent('sf_blank_namespace');
		}
		if ($add_data_link = sffGetAddDataLinkForPage($title, $namespace, NS_PROJECT)) {
			return $add_data_link;
		}
		// if nothing found still, return null
		return null;
	}


/**
 * Helper function - gets names of categories for a page;
 * based on Title::getParentCategories(), but simpler
 * - this function doubles as a function to get all categories on the
 * the site, if no article is specified
 */
function sffGetCategoriesForArticle($article = NULL) {
	$fname = 'sffGetCategoriesForArticle()';
	$categories = array();
	$db = wfGetDB( DB_SLAVE );
	$conditions = null;
	if ($article != '') {
		$titlekey = $article->mTitle->getArticleId();
		$conditions = "cl_from='$titlekey'";
	}
	$res = $db->select( $db->tableName('categorylinks'),
		'distinct cl_to', $conditions, $fname);
	if ($db->numRows( $res ) > 0) {
		while ($row = $db->fetchRow($res)) {
			$categories[] = $row[0];
		}
	}
	$db->freeResult($res);
	return $categories;
}

/**
 * Get the form used to edit this article: either the default form for a
 * category that this article belongs to (if there is one), or the default
 * form for the article's namespace, if there is one
 */
function sffGetFormForArticle($obj) {
	$categories = sffGetCategoriesForArticle($obj);
	foreach ($categories as $category) {
		if ($form_name = sffGetDefaultForm($category, NS_CATEGORY)) {
			return $form_name;
		}
	}
	// if we're still here, just return the default form for the namespace,
	// which may well be null
	return sffGetDefaultForm($obj->mTitle->getNsText(), NS_PROJECT);
}

/**
 * Return an array of all form names on this wiki
 */
function sffGetAllForms() {
	$dbr = wfGetDB( DB_SLAVE );
	$query = "SELECT page_title FROM " . $dbr->tableName( 'page' ) .
		" WHERE page_namespace = " . SF_NS_FORM .
		" AND page_is_redirect = 0" .
		" ORDER BY page_title";
	$res = $dbr->query($query);
	$form_names = array();
	while ($row = $dbr->fetchRow($res)) {
		$form_names[] = str_replace('_', ' ', $row[0]);
	}
	$dbr->freeResult($res);
	return $form_names;
}

	function sffFormDropdownHTML() {
		// create a dropdown of possible form names
		global $sfgContLang;
		$namespace_labels = $sfgContLang->getNamespaces();
		$form_label = $namespace_labels[SF_NS_FORM];
		$str = <<<END
			$form_label:
			<select name="form">

END;
		$form_names = sffGetAllForms();
		foreach ($form_names as $form_name) {
			$str .= "			<option>$form_name</option>\n";
		}
		$str .= "			</select>\n";
		return $str;
	}
?>
