<?php
/**
 * Defines a class, SFTemplateField, used in both creating templates and
 * displaying user-created forms
 *
 * @author Yaron Koren
 */

class SFTemplateField {
  var $field_name;
  var $label;
  var $semantic_field;
  var $attr_or_rel;
  var $attribute_type;
  var $possible_values;

  function newWithValues($name, $label, $semantic_field, $attr_or_rel) {
    global $smwgContLang;

    $f = new SFTemplateField();
    $f->field_name = str_replace('\\', '', $name);
    $f->label = str_replace('\\', '', $label);
    $f->semantic_field = str_replace('\\', '', $semantic_field);
    $f->attr_or_rel = str_replace('\\', '', $attr_or_rel);
    $f->possible_values = array();
    $fname = 'SFTemplateField::newWithValues';
    // set type if it's an attribute
    if ($f->attr_or_rel == "attribute") {
      // this whole section of code could be made much simpler if it were
      // to use the SMWTypeHandlerFactory class; but that would mean it
      // would require SMW 0.7, which it might be too early for
      $db =& wfGetDB( DB_SLAVE );
      // value has underscores in the database
      $semantic_field_db_str = str_replace(' ', '_', $f->semantic_field);
      $conditions = "subject_namespace = " . SMW_NS_ATTRIBUTE . " AND subject_title = '$semantic_field_db_str' AND property_id = 1";
      $res = $db->select( $db->tableName('smw_specialprops'),
			'value_string',
			$conditions, $fname);
      if ($db->numRows( $res ) > 0) {
	if ($row = $db->fetchObject($res)) {
	  $f->attribute_type = $row->value_string;
	  // set possible values if it's an enumeration
	  if ($f->attribute_type == $smwgContLang->smwDatatypeLabels['smw_enum']) {
	    $conditions = "subject_namespace = " . SMW_NS_ATTRIBUTE .
	      " AND subject_title = '$semantic_field_db_str' AND property_id = " .
	      SMW_SP_POSSIBLE_VALUE;
	    $res2 = $db->select( $db->tableName('smw_specialprops'),
				'value_string',
				$conditions, $fname);
	    if ($db->numRows( $res2 ) > 0) {
	      while ($row = $db->fetchObject($res2)) {
		$f->possible_values[] = $row->value_string;
	      }
	    }
	    $db->freeResult($res2);
	  }
	}
      }
      $db->freeResult($res);
    }
    return $f;
  }
}

function create_template_text($template_name, $template_fields, $category_name) {
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
  if ($category_name != '') {
    $text .= wfMsgForContent('sf_template_pagetype', "[[:Category:$category_name]]") . "\n";
  }
  $text .= "{| border=0 cellpadding=4 cellspacing=4\n";

  foreach ($template_fields as $i => $field) {
    if ($i > 0) {
      $text .= "|-\n";
    }
    $text .= "! " . $field->label . "\n";
    if ($field->semantic_field == null || $field->semantic_field == '') {
      $text .= "| {{{" . $field->field_name . "|unknown}}}\n";
    } else {
      if ($field->attr_or_rel == "attribute") {
        $text .= "| [[" . $field->semantic_field . ":={{{" . $field->field_name . "|unknown}}}]]\n";
      } else {
        $text .= "| [[" . $field->semantic_field . "::{{{" . $field->field_name . "|unknown}}}]]\n";
      }
    }
  }
  $text .= "|}\n";
  if ($category_name != '') {
    $text .= "\n[[Category:$category_name]]\n";
  }
  $text .= "</includeonly>\n";

  return $text;
}

?>
