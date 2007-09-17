<?php
/**
 * A special page holding a form that allows the user to create a template
 * with semantic fields.
 *
 * @author Yaron Koren
 */

include_once $sfgIP . "/includes/SF_TemplateField.inc";

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once( "$IP/includes/SpecialPage.php" );

SpecialPage::addPage( new SpecialPage('CreateTemplate','',true,'doSpecialCreateTemplate',false) );

// Custom sort function, used in getSemanticProperties()
function cmp($a, $b)
{
    if ($a == $b) {
        return 0;
    } elseif ($a < $b) {
        return -1;
    } else {
        return 1;
    }
}
function getSemanticProperties() {
  $dbr = wfGetDB( DB_SLAVE );
  $all_properties = array();

  $res = $dbr->query("SELECT page_title FROM " . $dbr->tableName('page') .
    " WHERE page_namespace = " . SMW_NS_ATTRIBUTE . " AND page_is_redirect = 0");
  while ($row = $dbr->fetchRow($res)) {
    $attribute_name = str_replace('_', ' ', $row[0]);
    $all_properties[$attribute_name . ":="] = $attribute_name;
  }
  $dbr->freeResult($res);

  $res = $dbr->query("SELECT page_title FROM " . $dbr->tableName('page') .
    " WHERE page_namespace = " . SMW_NS_RELATION . " AND page_is_redirect = 0");
  while ($row = $dbr->fetchRow($res)) {
    $relation_name = str_replace('_', ' ', $row[0]);
    $all_properties[$relation_name . "::"] = $relation_name;
  }
  $dbr->freeResult($res);

  // sort properties list alphabetically - custom sort function is needed
  // because the regular sort function destroys the "keys" of the array
  uasort($all_properties, "cmp");
  return $all_properties;
}

function printPropertiesDropdown($all_properties, $id, $property) {
  $dropdown_str = "<select name=\"semantic_field_call_$id\">\n";
  $dropdown_str .= "<option value=\"\"></option>\n";
  foreach ($all_properties as $prop_id => $prop_name) {
    $selected = ($property == $prop_id) ? "selected" : "";
    $dropdown_str .= "<option value=\"$prop_id\" $selected>$prop_name</option>\n";
  }
  $dropdown_str .= "</select>\n";
  return $dropdown_str;
}

function printFieldEntryBox($id, $f, $all_properties) {
  $dropdown_html = printPropertiesDropdown($all_properties, $id, $f->semantic_field_call);
  $text = '	<div class="field_box">' . "\n";
  $text .= '	<p>' . wfMsg('sf_createtemplate_fieldname') . ' <input size="15" name="name_' . $id . '" value="' . $f->field_name . '">' . "\n";
  $text .= '	' . wfMsg('sf_createtemplate_displaylabel') . ' <input size="15" name="label_' . $id . '" value="' . $f->label . '">' . "\n";
  $text .= '	' . wfMsg('sf_createtemplate_semanticproperty') . ' ' . $dropdown_html . "</p>\n";
  $checked_str = ($f->is_list) ? " checked" : "";
  $text .= '	<p><input type="checkbox" name="is_list_' . $id . '"' . $checked_str . '> ' . wfMsg('sf_createtemplate_fieldislist') . "\n";

  if ($id != "new") {
    $text .= '	&nbsp;&nbsp;<input name="del_' . $id . '" type="submit" value="' . wfMsg('sf_createtemplate_deletefield') . '">' . "\n";
  }
  $text .= <<<END
</p>
</div>

END;
  return $text;
}

function doSpecialCreateTemplate() {
  global $wgOut, $wgRequest, $wgUser, $wgRequest;

  $all_properties = getSemanticProperties();

  # cycle through the query values, setting the appropriate local variables
  $template_name = $wgRequest->getVal('template_name');
  $category = $wgRequest->getVal('category');
  $cur_id = 1;
  $fields = array();
  foreach ($wgRequest->getValues() as $var => $val) {
    list ($field_field, $old_id) = explode("_", $var);
    if ($field_field == "name") {
      if ($old_id != "new" || ($old_id == "new" && $val != "")) {
        if ($wgRequest->getVal('del_' . $old_id) != '') {
          # do nothing - this field won't get added to the new list
        } else {
          $field = SFTemplateField::newWithValues($val, $wgRequest->getVal('label_' . $old_id));
          $field->semantic_field_call = $wgRequest->getVal('semantic_field_call_' . $old_id);
          $field->is_list = $wgRequest->getCheck('is_list_' . $old_id);
          $fields[] = $field;
        }
      }
    }
  }
  $template_format = $wgRequest->getVal('template_format');

  $preview_button_text = wfMsg('preview');
  if ($wgRequest->getVal('preview') == $preview_button_text) {
    # validate template name
    if ($template_name == '') {
      $template_name_error_str = wfMsg('sf_blank_error');
    } else {
      # redirect to wiki interface
      $title = Title::newFromText($template_name, NS_TEMPLATE);
      $submit_url = $title->getLocalURL('action=submit');
      $full_text = createTemplateText($template_name, $fields, $category, $template_format);
      // HTML-encode
      $full_text = str_replace('"', '&quot;', $full_text);
      $text .= <<<END
  <form id="editform" name="editform" method="post" action="$submit_url">
    <input type="hidden" name="wpTextbox1" id="wpTextbox1" value="$full_text" />
  </form>
      <script>
      document.editform.submit();
      </script>

END;
    }
  }

  $text .= '	<form action="" method="get">' . "\n";
  // set 'title' field, in case there's no URL niceness
  $text .= '    <input type="hidden" name="title" value="Special:CreateTemplate">' . "\n";
  $text .= '	<p>' . wfMsg('sf_createtemplate_namelabel') . ' <input size="25" name="template_name" value="' . $template_name . '"> <font color="red">' . $template_name_error_str . '</font></p>' . "\n";
  $text .= '	<p>' . wfMsg('sf_createtemplate_categorylabel') . ' <input size="25" name="category" value="' . $category . '"></p>' . "\n";
  $text .= "	<fieldset>\n";
  $text .= '	<legend>' . wfMsg('sf_createtemplate_templatefields') . "</legend>\n";
  $text .= '	<p>' . wfMsg('sf_createtemplate_fieldsdesc') . "</p>\n";

  foreach ($fields as $i => $field) {
    $text .= printFieldEntryBox($i + 1, $field, $all_properties);
  }
  $new_field = new SFTemplateField();
  $text .= printFieldEntryBox("new", $new_field, $all_properties);

  $text .= '	<p><input type="submit" value="' . wfMsg('sf_createtemplate_addfield') . '"></p>' . "\n";
  $text .= "	</fieldset>\n";
  $text .= '	<p>' . wfMsg('sf_createtemplate_outputformat') . "\n";
  $text .= '	<input type="radio" name="template_format" checked value="standard">' . wfMsg('sf_createtemplate_standardformat') . "\n";
  $text .= '	<input type="radio" name="template_format" value="infobox">' . wfMsg('sf_createtemplate_infoboxformat') . "</p>\n";
  $text .= '	<p><input type="submit" name="preview" value="' . wfMsg('preview') . '"></p>' . "\n";
  $text .= "	</form>\n";
  $sk = $wgUser->getSkin();
  $cp = SpecialPage::getPage('CreateProperty');
  $create_property_link = $sk->makeKnownLinkObj($cp->getTitle(), $cp->getDescription());
  $text .= "	<br /><hr /><br />\n";
  $text .= "	<p>$create_property_link.</p>\n";

  $wgOut->addLink( array(
    'rel' => 'stylesheet',
    'type' => 'text/css',
    'media' => "screen, projection",
    'href' => $sfgScriptPath . "/skins/SF_main.css"
  ));
  $wgOut->addHTML($text);
}
