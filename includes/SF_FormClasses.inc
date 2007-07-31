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
    $form->form_name = $form_name;
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
    $add_data_url = sffTitleURLString($ad->getTitle()) . "/" . sffTitleURLString($title);
    $form_description = wfMsgForContent('sf_form_docu', $this->form_name, $add_data_url);
    $text =<<<END
<noinclude>
$form_description
</noinclude>
<includeonly>

END;
    $text .= "<h2>" . $this->form_name . ": {{{field|page title}}}</h2>\n";
    foreach ($this->templates as $template) {
      $text .= "\n" . $template->createMarkup();
    }
    $free_text_label = wfMsg('sf_editdata_freetextlabel');
    $text .=<<<END

   <fieldset><legend>$free_text_label</legend>
   {{{field|free text}}}
   </fieldset>
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

    if (preg_match_all('/\[\[([\w- ]*)(:[:=]){{{([\w- ]*).*?\]\]/mis', $template_text, $matches)) {
      // this a two-dimensional array; we need the last three of the four
      // sub-arrays; we also have to remove redundant values
      foreach ($matches[1] as $i => $semantic_field_name) {
        $attr_or_rel = ($matches[2][$i] == ":=") ? "attribute" : "relation";
        $field_name = $matches[3][$i];
        if (! in_array($field_name, $field_names_array)) {
          $field_names_array[] = $field_name;
          $template_fields[] = SFTemplateField::newWithValues($field_name, ucfirst(strtolower($field_name)), $semantic_field_name, $attr_or_rel);
        }
      }
    }
    // get any non-semantic fields defined as well
    if (preg_match_all('/{{{([^|}]*)/mis', $template_text, $matches)) {
      foreach ($matches[1] as $field_name) {
        if (! in_array($field_name, $field_names_array)) {
          $field_names_array[] = $field_name;
          $template_fields[] = SFTemplateField::newWithValues($field_name, ucfirst(strtolower($field_name)), null, null);
        }
      }
    }
    return $template_fields;
  }

  function create($name, $label, $allow_multiple, $max_allowed = null) {
    $tif = new SFTemplateInForm();
    $tif->template_name = $name;
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

class SFFormTemplateField {
  var $num;
  var $template_field;
  var $is_mandatory;
  var $is_hidden;
  var $is_restricted;
  // the following fields are not set by the form-creation page
  // (though they could be)
  var $input_type;
  var $size;
  var $num_rows;
  var $num_cols;
  var $autocomplete_category;
  var $no_autocomplete;
  var $part_of_multiple;
  // somewhat of a hack - this is an HTML-based value for a specific
  // representation of a form
  var $input_name;

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
    if ($template_field->attr_or_rel == "attribute") {
      $attr_link_text = sffLinkText(SMW_NS_ATTRIBUTE, $template_field->semantic_field);
      if ($template_field->attribute_type == "")
        $text .= '<p>' . wfMsg('sf_createform_fieldattrunknowntype', $attr_link_text,  sffLinkText(SMW_NS_TYPE, 'String')) . "</p>\n";
      else
        $text .= '<p>' . wfMsg('sf_createform_fieldattr', $attr_link_text,
          sffLinkText(SMW_NS_TYPE, $template_field->attribute_type)) . "</p>\n";

    } elseif ($template_field->attr_or_rel == "relation") {
      $rel_link_text = sffLinkText(SMW_NS_RELATION, $template_field->semantic_field);
      $text .= '<p>' . wfMsg('sf_createform_fieldrel', $rel_link_text) . "</p>\n";
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
