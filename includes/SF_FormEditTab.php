<?php
global $wgHooks;
$wgHooks[ 'SkinTemplateTabs' ][] = 'sffFormEditTab';

/**
 * Adds an action that uses the default form for either a category that this
 * article belongs to (if there is one), or the article's namespace, to
 * edit the article
 */
function sffFormEditTab($obj, $content_actions) {
  $fname = 'SFFormEditTab';
  // make sure that this is not itself a category page, and that the user
  // is allowed to edit it
  if (($obj->mTitle != null) && ($obj->mTitle->getNamespace() != NS_CATEGORY)) {
    $form_name = sffGetFormForArticle($obj);
    if ($form_name) {  
     global $wgRequest;
     $class_name = ($wgRequest->getVal('action') == 'formedit') ? 'selected' : '';

      $content_actions['form_edit'] = array(
        'class' => $class_name,
        'text' => wfMsg('form_edit'),
        'href' => $obj->mTitle->getLocalURL('action=formedit')
      );
      return true;
    }
  }
  return true; // always return true, in order not to stop MW's hook processing!
}

?>
