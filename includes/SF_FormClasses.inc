<?php
/**
 * Three classes - SFForm, SFTemplateInForm, and SFFormTemplateField - that
 * represent the components of a user-defined form, and handle the creation
 * of a form definition file.
 *
 * @author Yaron Koren
 */

class SFForm {
  var $form_name;
  var $templates;

  function create($form_name, $templates) {
    $form = new SFForm();
    $form->form_name = ucfirst(str_replace('_', ' ', $form_name));
    $form->templates = $templates;
    return $form;
  }

  function creationHTML() {
    $text = "";
    foreach ($this->templates as $i => $ft) {
      $text .= $ft->creationHTML($i);
    }
    return $text;
  }

  function createMarkup() {
    $title = Title::makeTitle( SF_NS_FORM, $this->form_name );
    $ad = SpecialPage::getPage('AddPage');
    $add_data_url = sffTitleURLString($ad->getTitle()) . "/" . $title->getPartialURL();
    $form_description = wfMsgForContent('sf_form_docu', $this->form_name, $add_data_url);
    $form_input = "{{#forminput:" . $this->form_name . "}}\n";
    $text =<<<END
<noinclude>
$form_description

$form_input
</noinclude>
<includeonly>

END;
    foreach ($this->templates as $template) {
      $text .= $template->createMarkup() . "\n";
    }
    $free_text_label = wfMsg('sf_editdata_freetextlabel');
    $text .=<<<END
   <fieldset><legend>$free_text_label</legend>
   {{{field|free text}}}
   </fieldset>

<p>{{{standard input|summary}}}</p>
<p>{{{standard input|minor edit}}} {{{standard input|watch}}}</p>
<p>{{{standard input|save}}} {{{standard input|preview}}} {{{standard input|changes}}} {{{standard input|cancel}}}</p>
</includeonly>

END;

    return $text;
  }

}

class SFTemplateInForm {
  var $template_name;
  var $label;
  var $allow_multiple;
  var $max_allowed;
  var $fields;

  function getAllFields() {
    $template_fields = array();
    $field_names_array = array();

    // get fields of template, both semantic and otherwise, by parsing text
    // of template
    $template_title = Title::newFromText($this->template_name, NS_TEMPLATE);
    $template_article = new Article($template_title);
    $template_text = $template_article->getContent();
    // ignore 'noinclude' sections and 'includeonly' tags
    $template_text = StringUtils::delimiterReplace('<noinclude>', '</noinclude>', '', $template_text);
    $template_text = strtr($template_text, array('<includeonly>' => '', '</includeonly>' => ''));

    // first look for normal relation/attribute calls
    if (preg_match_all('/\[\[([^:=]*)(:[:=]){{{([^\]\|}]*).*?\]\]/mis', $template_text, $matches)) {
      // this is a two-dimensional array; we need the last three of the four
      // sub-arrays; we also have to remove redundant values
      foreach ($matches[1] as $i => $semantic_field_name) {
        $is_relation = ($matches[2][$i] == "::");
        $field_name = $matches[3][$i];
        if (! in_array($field_name, $field_names_array)) {
          $field_names_array[] = $field_name;
          $template_field = SFTemplateField::newWithValues($field_name, ucfirst(strtolower($field_name)));
          $template_field->setSemanticData($semantic_field_name, $is_relation, false);
          $template_fields[] = $template_field;
        }
      }
    }
    // second, look for "arraymap" parser function calls that map a
    // relation or attribute onto a list
    if (preg_match_all('/{{#arraymap:{{{([^|}]*)[^\[]*\[\[([^:=]*)(:[:=])/mis', $template_text, $matches)) {
      // this is a two-dimensional array; we need the last three of the four
      // sub-arrays; we also have to remove redundant values
      foreach ($matches[1] as $i => $field_name) {
        $is_relation = ($matches[3][$i] == "::");
        $semantic_field_name = $matches[2][$i];
        if (! in_array($field_name, $field_names_array)) {
          $field_names_array[] = $field_name;
          $template_field = SFTemplateField::newWithValues($field_name, ucfirst(strtolower($field_name)));
          $template_field->setSemanticData($semantic_field_name, $is_relation, true);
          $template_fields[] = $template_field;
        }
      }
    }
    
    // get any non-semantic fields defined as well
    if (preg_match_all('/{{{([^|}]*)/mis', $template_text, $matches)) {
      foreach ($matches[1] as $field_name) {
        if (! in_array($field_name, $field_names_array)) {
          $field_names_array[] = $field_name;
          $template_fields[] = SFTemplateField::newWithValues($field_name, ucfirst(strtolower($field_name)));
        }
      }
    }
    return $template_fields;
  }

  function create($name, $label, $allow_multiple, $max_allowed = null) {
    $tif = new SFTemplateInForm();
    $tif->template_name = str_replace('_', ' ', $name);
    $tif->fields = array();
    $fields = $tif->getAllFields();
    foreach ($fields as $i => $field) {
      $tif->fields[] = SFFormTemplateField::create($i, $field);
    }
    $tif->label = $label;
    $tif->allow_multiple = $allow_multiple;
    $tif->max_allowed = $max_allowed;
    return $tif;
  }

  function creationHTML($template_num) {
    $checked_str = ($this->allow_multiple) ? "checked" : "";
    $template_str = wfMsg('sf_createform_template');
    $template_label_input = wfMsg('sf_createform_templatelabelinput');
    $allow_multiple_text = wfMsg('sf_createform_allowmultiple');
    $text =<<<END
	<input type="hidden" name="template_$template_num" value="$this->template_name">
	<div class="template_form">
	<h2>$template_str '$this->template_name'</h2>
	<p>$template_label_input <input size=25 name="label_$template_num" value="$this->label"></p>
	<p><input type="checkbox" name="allow_multiple_$template_num" $checked_str> $allow_multiple_text</p>
	<hr>

END;
    foreach ($this->fields as $field) {
      $text .= $field->creationHTML($template_num);
    }
    $text .= '	<p><input type="submit" name="del_' . $template_num .
      '" value="' . wfMsg('sf_createform_removetemplate') . '"></p>' . "\n";
    $text .= "	</div>\n";
    return $text;
  }

  function createMarkup() {
    $text = "";
    $text .= "{{{for template|" . $this->template_name;
    if ($this->allow_multiple) 
      $text .= "|multiple";
    if ($this->label != '')
      $text .= "|label=" . $this->label;
    $text .= "}}}\n";
    foreach ($this->fields as $field) {
      $text .= $field->createMarkup();
    }
    $text .= "{{{end template}}}\n";
    return $text;
  }
}

/*
 * This class is distinct from SFTemplateField in that it represents a
 * template field defined in a form - it contains a simple SFTemplateField
 * object (the $template_field variable), along with the other properties
 * for that field that are set within the form
 */
class SFFormTemplateField {
  var $num;
  var $template_field;
  var $is_mandatory;
  var $is_hidden;
  var $is_restricted;
  // the following fields are not set by the form-creation page
  // (though they could be)
  var $input_type;
  var $field_args;
  var $autocomplete_category;
  var $no_autocomplete;
  var $part_of_multiple;
  // somewhat of a hack - these two fields are for a field in a specific
  // representation of a form, not the form definition; ideally these
  // should be contained in a third 'field' class, called something like
  // SFFormInstanceField, that holds these fields plus an instance of
  // SFFormTemplateField.
  var $input_name;
  var $is_disabled;

  function create($num, $template_field) {
    $f = new SFFormTemplateField();
    $f->num = $num;
    $f->template_field = $template_field;
    $f->is_mandatory = false;
    $f->is_hidden = false;
    $f->is_restricted = false;
    return $f;
  }

  function creationHTML($template_num) {
    $field_form_text = $template_num . "_" . $this->num;
    $template_field = $this->template_field;
    $text = '<h3>' . wfMsg('sf_createform_field') . " '" . $template_field->field_name . "'</h3>\n";
    if ($template_field->is_relation) {
      $rel_link_text = sffLinkText(SMW_NS_RELATION, $template_field->semantic_field);
      $text .= '<p>' . wfMsg('sf_createform_fieldrel', $rel_link_text) . "</p>\n";
    } elseif ($template_field->field_type != null) {
      $attr_link_text = sffLinkText(SMW_NS_ATTRIBUTE, $template_field->semantic_field);
      // TODO - remove this probably-unnecessary check?
      if ($template_field->field_type == "")
        $text .= '<p>' . wfMsg('sf_createform_fieldattrunknowntype', $attr_link_text,  sffLinkText(SMW_NS_TYPE, 'String')) . "</p>\n";
      else
        $text .= '<p>' . wfMsg('sf_createform_fieldattr', $attr_link_text,
          sffLinkText(SMW_NS_TYPE, $template_field->field_type)) . "</p>\n";

    } else {
      // not a semantic field - don't add any text
    }
    $form_label_text = wfMsg('sf_createform_formlabel');
    $field_label = $template_field->label;
    $hidden_checked_str = ($this->is_hidden) ? "checked" : "";
    $hidden_text = wfMsg('sf_createform_hidden');
    $restricted_checked_str = ($this->is_restricted) ? "checked" : "";
    $restricted_text = wfMsg('sf_createform_restricted');
    $mandatory_checked_str = ($this->is_mandatory) ? "checked" : "";
    $mandatory_text = wfMsg('sf_createform_mandatory');
    $text .=<<<END
	<p>$form_label_text <input type="text" name="label_$field_form_text" size=20 value="$field_label" />
	&nbsp; <input type="checkbox" name="mandatory_$field_form_text" value="mandatory" $mandatory_checked_str /> $mandatory_text
	<input type="checkbox" name="hidden_$field_form_text" value="hidden" $hidden_checked_str /> $hidden_text
	<input type="checkbox" name="restricted_$field_form_text" value="restricted" $restricted_checked_str /> $restricted_text</p>
	<hr>

END;
    return $text;
  }

  function createMarkup() {
    $text = "   <p>";
    if ($this->template_field->label != "")
      $text .= "<strong>" . $this->template_field->label .  ":</strong> ";
    $text .= "{{{field|" . $this->template_field->field_name;
    if ($this->is_mandatory) {
      $text .= "|mandatory";
    } elseif ($this->is_hidden) {
      $text .= "|hidden";
    } elseif ($this->is_restricted) {
      $text .= "|restricted";
    }
    $text .= "}}}</p>\n";
    return $text;
  }
}

?>
