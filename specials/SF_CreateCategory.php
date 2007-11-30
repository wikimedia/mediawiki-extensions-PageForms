<?php
/**
 * A special page holding a form that allows the user to create a category
 * page, with SF forms associated with it
 *
 * @author Yaron Koren
 */

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once( "$IP/includes/SpecialPage.php" );

global $sfgSpecialPagesSpecialInit;
if ($sfgSpecialPagesSpecialInit) {
	global $wgSpecialPages;
	$wgSpecialPages['CreateCategory'] = 'SFCreateCategory';

	class SFCreateCategory extends SpecialPage {

		/**
		 * Constructor
		 */
		public function __construct() {
			smwfInitUserMessages();
			parent::__construct('CreateCategory', '', true);
		}

		function execute() {
			doSpecialCreateCategory();
		}
	}
} else {
	SpecialPage::addPage( new SpecialPage('CreateCategory','',true,'doSpecialCreateCategory',false) );
}

function createCategoryText($default_form, $parent_category) {
	global $sfgContLang;

	$namespace_labels = $sfgContLang->getNamespaceArray();
	$form_label = $namespace_labels[SF_NS_FORM];
	$specprops = $sfgContLang->getSpecialPropertiesArray();
	$smw_version = SMW_VERSION;
	$form_tag = "[[" . $specprops[SF_SP_HAS_DEFAULT_FORM] .
		"::$form_label:$default_form|$default_form]]";
	$text = wfMsg('sf_category_hasdefaultform', $form_tag);
	if ($parent_category != '') {
		global $wgContLang;
		$namespace_labels = $wgContLang->getNamespaces();
		$category_namespace = $namespace_labels[NS_CATEGORY];
		$text .= "\n\n[[$category_namespace:$parent_category]]";
	}
	return $text;
}

function doSpecialCreateCategory() {
  global $wgOut, $wgRequest, $wgUser, $sfgScriptPath;

  # cycle through the query values, setting the appropriate local variables
  $category_name = $wgRequest->getVal('category_name');
  $default_form = $wgRequest->getVal('default_form');
  $parent_category = $wgRequest->getVal('parent_category');

  $preview_button_text = wfMsg('preview');
  $category_name_error_str = '';
  if ($wgRequest->getVal('preview') == $preview_button_text) {
    # validate category name
    if ($category_name == '') {
      $category_name_error_str = wfMsg('sf_blank_error');
    } else {
      # redirect to wiki interface
      $namespace = NS_CATEGORY;
      $title = Title::newFromText($category_name, $namespace);
      $submit_url = $title->getLocalURL('action=submit');
      $full_text = createCategoryText($default_form, $parent_category);
      // HTML-encode
      $full_text = str_replace('"', '&quot;', $full_text);
      $text =<<<END
  <form id="editform" name="editform" method="post" action="$submit_url">
    <input type="hidden" name="wpTextbox1" id="wpTextbox1" value="$full_text" />
  </form>
      <script>
      document.editform.submit();
      </script>

END;
      $wgOut->addHTML($text);
      return;
    }
  }

  $all_forms = sffGetAllForms();

  // set 'title' as hidden field, in case there's no URL niceness
  global $wgContLang;
  $mw_namespace_labels = $wgContLang->getNamespaces();
  $special_namespace = $mw_namespace_labels[NS_SPECIAL];
  $name_label = wfMsg('sf_createcategory_name');
  $form_label = wfMsg('sf_createcategory_defaultform');
  $text =<<<END
	<form action="" method="get">
	<input type="hidden" name="title" value="$special_namespace:CreateCategory">
	<p>$name_label <input size="25" name="category_name" value="">
	<span style="color: red;">$category_name_error_str</span>
	$form_label
	<select id="form_dropdown" name="default_form">

END;
  foreach ($all_forms as $form) {
    $text .= "	<option>$form</option>\n";
  }

  $subcategory_label = wfMsg('sf_createcategory_makesubcategory');
  $categories = sffGetCategoriesForArticle();
  $sk = $wgUser->getSkin();
  $cf = SpecialPage::getPage('CreateForm');
  $create_form_link = $sk->makeKnownLinkObj($cf->getTitle(), $cf->getDescription());
  $text .=<<<END
	</select>
	<p>$subcategory_label
	<select id="category_dropdown" name="parent_category">
	<option></option>

END;
  foreach ($categories as $category) {
    $category = str_replace('_', ' ', $category);
    $text .= "	<option>$category</option>\n";
  }
  $text .=<<<END
	</select>
	</p>
	<p><input type="submit" name="preview" value="$preview_button_text"></p>
	<br /><hr /<br />
	<p>$create_form_link.</p>

END;

  $text .= "	</form>\n";

  $wgOut->addLink( array(
    'rel' => 'stylesheet',
    'type' => 'text/css',
    'media' => "screen, projection",
    'href' => $sfgScriptPath . "/skins/SF_main.css"
  ));
  $wgOut->addHTML($text);
}
