<?php
global $wgHooks;
$wgHooks[ 'SkinTemplateTabs' ][] = 'sffFormEditTab';

/**
 * Adds an "action" (i.e., a tab) to edit the current article with a form
 */
function sffFormEditTab($obj, $content_actions) {
  $fname = 'SFFormEditTab';
  // make sure that this is not itself a category page, and that the user
  // is allowed to edit it
  if (($obj->mTitle != null) && ($obj->mTitle->getNamespace() != NS_CATEGORY)) {
    $form_name = sffGetFormForArticle($obj);
    if ($form_name) {  
      global $wgRequest;
      global $sfgHideMainEditTab, $sfgSwitchEditTabLocations, $sfgRenameEditTabs;

      // create the form edit tab, and apply whatever changes are specified
      // by the three edit-tab global variables
      $edit_tab_hidden = false;
      if ($sfgHideMainEditTab) {
        global $wgUser;
        // TODO - there should be a better way to determine if the user is a sysop
        if (! $wgUser->isAllowed('delete')) {
          $edit_tab_hidden = true;
        }
      }

      if ($sfgRenameEditTabs) {
        $form_edit_tab_text = wfMsg('edit');
        $content_actions['edit']['text'] = wfMsg('edit_source');
      } else {
        $form_edit_tab_text = wfMsg('form_edit');
      }

      $class_name = ($wgRequest->getVal('action') == 'formedit') ? 'selected' : '';
      $form_edit_tab = array(
        'class' => $class_name,
        'text' => $form_edit_tab_text,
        'href' => $obj->mTitle->getLocalURL('action=formedit')
      );

      if ($sfgSwitchEditTabLocations) {
        if (! $edit_tab_hidden) {
          $content_actions['form_edit'] = $content_actions['edit'];
        }
        $content_actions['edit'] = $form_edit_tab;
      } else {
        if ($edit_tab_hidden) {
          unset($content_actions['edit']);
        }
        $content_actions['form_edit'] = $form_edit_tab;
      }

      return true;
    }
  }
  return true; // always return true, in order not to stop MW's hook processing!
}

?>
