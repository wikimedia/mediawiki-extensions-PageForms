<?php
global $wgHooks;
$wgHooks[ 'SkinTemplateTabs' ][] = 'sffFormEditTab';

/**
 * Gets names of categories for this page;
 * based on Title::getParentCategories(), but simpler
 */
function getCategoriesForArticle($article) {
  $fname = 'SFFormEditTab::getCategoriesForArticle()';
  $categories = array();
  $titlekey = $article->mTitle->getArticleId();
  $db =& wfGetDB( DB_SLAVE );
  $conditions = "cl_from='$titlekey'";
  $res = $db->select( $db->tableName('categorylinks'),
                      'cl_to',
                      $conditions, $fname);
  if ($db->numRows( $res ) > 0) {
    while ($row = $db->fetchRow($res)) {
      $categories[] = $row[0];
    }
  }
  $db->freeResult($res);
  return $categories;
}

/**
 * Adds an action that uses the default form for either a category that this
 * article belongs to (if there is one), or the article's namespace, to
 * edit the article
 */
function sffFormEditTab($obj, $content_actions) {
  $fname = 'SFFormEditTab';
  $db =& wfGetDB( DB_SLAVE );
  // make sure that this is not itself a category page, and that the user
  // is allowed to edit it
  if (($obj->mTitle != null) && ($obj->mTitle->getNamespace() != NS_CATEGORY)) {
    $categories = getCategoriesForArticle($obj);
    $default_form_relation = str_replace(' ', '_', wfMsgForContent('sf_form_relation'));
    foreach ($categories as $category) {
      if ($form_name = sffGetDefaultForm($db, $category, NS_CATEGORY)) {
        break;
      }
    }
    if (! $form_name) {
      $form_name = sffGetDefaultForm($db, $obj->mTitle->getNsText(), NS_PROJECT);
    }
    if ($form_name) {  
      $page = SpecialPage::getPage('EditData');
      $target_name = sffTitleURLString($obj->mTitle);

      $content_actions['form_edit'] = array(
        'class' => false,
        'text' => wfMsg('form_edit'),
        'href' => $page->getTitle()->getFullURL() . "/$form_name/$target_name"
      );
      return true;
    }
  }
  return true; // always return true, in order not to stop MW's hook processing!
}
?>
