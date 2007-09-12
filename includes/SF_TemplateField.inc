<?php
/**
 * Defines a class, SFTemplateField, that represents a field in a template,
 * including any possible semantic aspects it may have. Used in both creating
 * templates and displaying user-created forms
 *
 * @author Yaron Koren
 */

class SFTemplateField {
  var $field_name;
  var $label;
  var $semantic_field;
  var $semantic_field_call;
  var $is_list;
  var $is_relation;
  var $field_type;
  var $possible_values;

  function newWithValues($name, $label) {
    $f = new SFTemplateField();
    $f->field_name = str_replace('\\', '', $name);
    $f->label = str_replace('\\', '', $label);
    return $f;
  }

  // setSemanticData() - called when template is parsed during the creation
  // of a form
  function setSemanticData($semantic_field, $is_relation) {
    $this->semantic_field = str_replace('\\', '', $semantic_field);
    $this->is_relation = $is_relation;
    $this->possible_values = array();
    // set field type if it's an attribute
    // HACK - also set type (to 'relation') if it's a relation
    if ($this->is_relation) {
      $this->field_type = 'relation';
    } else {
      global $smwgContLang;
      $fname = 'SFTemplateField::setSemanticData';

      // this whole section of code could be made much simpler if it were
      // to use the SMWTypeHandlerFactory class; but that would mean it
      // would require SMW 0.7, which it might be too early for
      $db = wfGetDB( DB_SLAVE );
      // value has underscores in the database
      $semantic_field_db_str = str_replace(' ', '_', $this->semantic_field);
      $conditions = "subject_namespace = " . SMW_NS_ATTRIBUTE . " AND subject_title = '$semantic_field_db_str' AND property_id = 1";
      $res = $db->select( $db->tableName('smw_specialprops'),
			'value_string',
			$conditions, $fname);
      if ($db->numRows( $res ) > 0) {
        if ($row = $db->fetchObject($res)) {
          $this->field_type = $row->value_string;
          // set possible values if it's an enumeration
          if ($this->field_type == $smwgContLang->smwDatatypeLabels['smw_enum']) {
            $conditions = "subject_namespace = " . SMW_NS_ATTRIBUTE .
              " AND subject_title = '$semantic_field_db_str' AND property_id = " .
              SMW_SP_POSSIBLE_VALUE;
            $res2 = $db->select( $db->tableName('smw_specialprops'),
              'value_string', $conditions, $fname);
            if ($db->numRows( $res2 ) > 0) {
              while ($row = $db->fetchObject($res2)) {
                $this->possible_values[] = $row->value_string;
              }
            }
            $db->freeResult($res2);
          }
        }
      }
      $db->freeResult($res);
    }
  }
}

function createTemplateText($template_name, $template_fields, $category, $template_format) {
  $text = "<noinclude>\n";
  $text .= wfMsgForContent('sf_template_docu', $template_name) . "\n";
  $text .= "<pre>\n";
  $text .= "{{" . $template_name . "\n";
  foreach ($template_fields as $field) {
    $text .= "|" . $field->field_name . "=\n";
  }
  $text .= "}}\n";
  $text .= "</pre>\n";
  $text .= wfMsgForContent('sf_template_docufooter') . "\n";
  $text .=<<<END
</noinclude>

<includeonly>

END;
  // topmost part depends on format
  if ($template_format == 'infobox') {
    // CSS style can't be used, unfortunately, since most MediaWiki setups
    // don't have an 'infobox' or comparable CSS class
    $text .=<<<END
{| style="width: 30em; font-size: 90%; border: 1px solid #aaaaaa; background-color: #f9f9f9; color: black; margin-bottom: 0.5em; margin-left: 1em; padding: 0.2em; float: right; clear: right; text-align:left;"
! style="text-align: center; background-color:#ccccff;" colspan="2" |<big>{{PAGENAME}}</big>
|-

END;
  } else {
    $text .= '{| border="0" cellpadding="4" cellspacing="4"' . "\n";
  }

  foreach ($template_fields as $i => $field) {
    if ($i > 0) {
      $text .= "|-\n";
    }
    $text .= "! " . $field->label . "\n";
    if ($field->semantic_field_call == null || $field->semantic_field_call == '') {
      $text .= "| {{{" . $field->field_name . "|}}}\n";
    // if this field is meant to contain a list, add on an 'arraymap' function,
    // that will call this semantic markup tag on every element in the list
    } elseif ($field->is_list) {
      $text .= "| {{#arraymap:{{{" . $field->field_name . "|}}}|,|x|[[" . $field->semantic_field_call . "x]]}}\n";
    } else {
      $text .= "| [[" . $field->semantic_field_call . "{{{" . $field->field_name . "|}}}]]\n";
    }
  }
  $text .= "|}\n";
  if ($category != '') {
    $text .= "\n[[Category:$category]]\n";
  }
  $text .= "</includeonly>\n";

  return $text;
}

?>
