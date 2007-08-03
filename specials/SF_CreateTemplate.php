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

// beginning of new layout for CreateTemplate
function getSemanticProperties() {
	$dbr =& wfGetDB( DB_SLAVE );
	$attributes = $dbr->tableName( 'smw_attributes' );
	// QueryPage uses the value from this SQL in an ORDER clause,
	// so return attribute title in value, and its type in title.
	$query = "SELECT distinct value_datatype as title,
		  attribute_title as value
		  FROM $attributes
		  GROUP BY attribute_title, value_datatype";
}

function printFieldEntryBox($id, $f) {
  $text = '	<div class="field_box">' . "\n";
  $text .= '	<p>' . wfMsg('sf_createtemplate_fieldname') . ' <input size="15" name="name_' . $id . '" value="' . $f->field_name . '">' . "\n";
  $text .= '	' . wfMsg('sf_createtemplate_displaylabel') . ' <input size="15" name="label_' . $id . '" value="' . $f->label . '"></p>' . "\n";
  $text .= '	<p>' . wfMsg('sf_createtemplate_semanticproperty') . ' <input size="15" name="semantic_field_' . $id . '" value="' . $f->semantic_field . '">' . "\n";

  $text .= "	<input type=\"radio\" name=\"attr_or_rel_$id\" value=\"attribute\"" .
    ($f->attr_or_rel == "attribute" ? " checked" : "") . '> ' .
    wfMsg('sf_createtemplate_attribute') . "\n";
  $text .= "	<input type=\"radio\" name=\"attr_or_rel_$id\" value=\"relation\"" .
    ($f->attr_or_rel == "relation" ? " checked" : "") . '> ' .
    wfMsg('sf_createtemplate_relation') . "\n";
  $text .= "&nbsp;&nbsp;\n\n";

  if ($id != "new") {
    $text .= '	<input name="del_' . $id . '" type="submit" value="' . wfMsg('sf_createtemplate_deletefield') . '">' . "\n";
  }
  $text .= <<<END
</p>
</div>

END;
  return $text;
}

function doSpecialCreateTemplate() {
  global $wgOut, $wgRequest;
  # cycle through the query values, setting the appropriate local variables
  $template_name = $wgRequest->getVal('template_name');
  $type_name = $wgRequest->getVal('type_name');
  $cur_id = 1;
  $fields = array();
  foreach ($wgRequest->getValues() as $var => $val) {
    list ($field_field, $old_id) = explode("_", $var);
    if ($field_field == "name") {
      if ($old_id != "new" || ($old_id == "new" && $val != "")) {
        if ($wgRequest->getVal('del_' . $old_id) != '') {
          # do nothing - this field won't get added to the new list
        } else {
          $field = SFTemplateField::newWithValues($val,
            $wgRequest->getVal('label_' . $old_id),
            $wgRequest->getVal('semantic_field_' . $old_id),
            $wgRequest->getVal('attr_or_rel_' . $old_id));
          $fields[] = $field;
        }
      }
    }
  }

  $preview_button_text = wfMsg('preview');
  if ($wgRequest->getVal('preview') == $preview_button_text) {
    # validate template name
    if ($template_name == '') {
      $template_name_error_str = wfMsg('sf_blank_error');
    } else {
      # redirect to wiki interface
      $title = Title::newFromText($template_name, NS_TEMPLATE);
      $submit_url = $title->getLocalURL('action=submit');
      $full_text = create_template_text($template_name, $fields, $type_name);
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
  $text .= '	<p>' . wfMsg('sf_createtemplate_categorylabel') . ' <input size="25" name="type_name" value="' . $type_name . '"></p>' . "\n";
  $text .= "	<fieldset>\n";
  $text .= '	<legend>' . wfMsg('sf_createtemplate_templatefields') . "</legend>\n";
  $text .= '	<p>' . wfMsg('sf_createtemplate_fieldsdesc') . "</p>\n";

  foreach ($fields as $i => $field) {
    $text .= printFieldEntryBox($i + 1, $field);
  }
  $new_field = new SFTemplateField();
  $text .= printFieldEntryBox("new", $new_field);

  $text .= '	<p><input type="submit" value="' . wfMsg('sf_createtemplate_addfield') . '"></p>' . "\n";
  $text .= "	</fieldset>\n";
  $text .= '	<p><input type="submit" name="preview" value="' . wfMsg('preview') . '"></p>' . "\n";
  $text .= "	</form>\n";

  $wgOut->addLink( array(
    'rel' => 'stylesheet',
    'type' => 'text/css',
    'media' => "screen, projection",
    'href' => "/w/extensions/SemanticForms/skins/SF_main.css"
  ));
  $wgOut->addHTML($text);
}
