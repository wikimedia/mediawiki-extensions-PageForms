<?php
/**
 * Handles the display and running of a user-created form.
 *
 * @author Yaron Koren
 * @author Nils Oppermann
 */

class SFFormPrinter {


  function formHTML($form_def, $form_submitted, $source_is_page, $existing_page_content = null, $page_title = null) {
    global $wgRequest, $wgUser;
    global $gTabIndex; // used to represent the current tab index in the form
    global $gDisabledText; // disables all form elements if user doesn't have edit permission

    $gTabIndex = 0;
    if ( $wgUser->isAllowed('edit') ) {
      $gDisabledText = "";
      $form_text = "";
    } else {
      $gDisabledText = "disabled";
      // display a message to the user explaining why they can't edit the
      // page - borrowed heavily from EditPage.php
      if ( $wgUser->isAnon() ) {
        $skin = $wgUser->getSkin();
        $loginTitle = SpecialPage::getTitleFor( 'Userlogin' );
        $loginLink = $skin->makeKnownLinkObj( $loginTitle, wfMsgHtml( 'loginreqlink' ) );
        $form_text = wfMsgWikiHtml( 'whitelistedittext', $loginLink );
      } else {
        $form_text = wfMsg( 'protectedpagetext' );
      }
    }

    // Remove <noinclude> sections and <includeonly> tags from form definition
    $form_def = StringUtils::delimiterReplace('<noinclude>', '</noinclude>', '', $form_def);
    $form_def = strtr($form_def, array('<includeonly>' => '', '</includeonly>' => ''));

    // turn form definition file into an array of sections, one for each
    // template definition (plus the first section)
    $form_def_sections = array();
    $start_position = 0;
    $section_start = 0;
    $free_text_was_included = false;
    while ($brackets_loc = strpos($form_def, "{{{", $start_position)) {
      $brackets_end_loc = strpos($form_def, "}}}", $brackets_loc);
      $bracketed_string = substr($form_def, $brackets_loc + 3, $brackets_end_loc - ($brackets_loc + 3));
      $tag_components = explode('|', $bracketed_string);
      $tag_title = trim($tag_components[0]);
      if ($tag_title == 'for template' || $tag_title == 'end template') {
        // create a section for everything up to here
        $section = substr($form_def, $section_start, $brackets_loc - $section_start);
        $form_def_sections[] = $section;
        $section_start = $brackets_loc;
      }
      $start_position = $brackets_loc + 1;
    }
    $form_def_sections[] = trim(substr($form_def, $section_start));

    // cycle through form definition file (and possibly an existing article
    // as well), finding template and field declarations and replacing them
    // with form elements, either blank or pre-populated, as appropriate
    $all_fields = array();
    $passed_validation = true;
    $data_text = "";
    $template_name = "";
    $instance_was_deleted = false;
    $allow_multiple = false;
    $strict_parsing = false;
    for ($section_num = 0; $section_num < count($form_def_sections); $section_num++) {
      $tif = new SFTemplateInForm();
      $start_position = 0;
      $template_text = "";
      $instance_added = false;
      // the append is there to ensure that the original array doesn't get
      // modified; is it necessary?
      $section = " " . $form_def_sections[$section_num];

      while ($brackets_loc = strpos($section, '{{{', $start_position)) {
        $brackets_end_loc = strpos($section, "}}}", $brackets_loc);
        $bracketed_string = substr($section, $brackets_loc + 3, $brackets_end_loc - ($brackets_loc + 3));
        $tag_components = explode('|', $bracketed_string);
        $tag_title = trim($tag_components[0]);
        if ($tag_title == 'for template') {
          $old_template_name = $template_name;
          $template_name = trim($tag_components[1]);
          $tif->template_name = $template_name;
          $query_template_name = str_replace(' ', '_', $template_name);
          // cycle through the other components
          for ($i = 2; $i < count($tag_components); $i++) {
            $component = $tag_components[$i];
            if ($component == 'multiple') $allow_multiple = true;
            if ($component == 'strict') $strict_parsing = true;
            $sub_components = explode('=', $component);
            if (count($sub_components) == 2) {
              if ($sub_components[0] == 'label') {
                $template_label = $sub_components[1];
              }
            }
          }
          // increment instance_num (used for query string) if it's
          // a repeated template, or if user has hit 'add' button
          // - this logic might not need to be this complicated
          if ($allow_multiple) {
            if ($old_template_name == $template_name || $repeat_section) {
              $instance_num++;
            } else {
              $instance_num = 1;
            }
            $repeat_section = false;
            $full_template_name = $query_template_name . '_' . $instance_num;
          } else {
            $instance_num = 1;
            $full_template_name = $query_template_name;
          }
          // if this is the first instance, add the label in the form
          if ($instance_num == 1 && $template_label != null) {
            $form_text .= "<fieldset>\n";
            $form_text .= "<legend>$template_label</legend>\n";
          }
          $template_text .= "{{" . $tif->template_name;
          $all_fields = $tif->getAllFields();
          // remove template tag
          $section = substr_replace($section, '', $brackets_loc, $brackets_end_loc + 3 - $brackets_loc);
          // if this is an 'allowed multiple' template, and user requested a
          // delete of this instance, increment the instance_num; that will
          // skip over all the fields of this template in the query
          $delete_cur_instance = false;
          $full_template_values = $wgRequest->getArray($full_template_name);
          if ($allow_multiple && $full_template_values['delete'] != null) {
            $delete_cur_instance = true;
            $instance_was_deleted = true;
          }
          // if we are editing a page, and this template can be found more than
          // once in that page, and multiple values are allowed, repeat this
          // section
          $existing_template_text = null;
          if ($source_is_page) {
            if ($allow_multiple) {
              // find the number of instances of this template in the page -
              // if it's more than one, re-parse this section of the
              // definition form for the subsequent template instances in
              // this page; if there's none, don't include fields at all.
              // there has to be a more efficient way to handle multiple
              // instances of templates, one that doesn't involve re-parsing
              // the same tags, but I don't know what it is.
              if (preg_match_all('/\{\{' . $tif->template_name . '(.*?)\}\}/mis', $existing_page_content, $matches)) {
                if (count($matches[0]) > 1) {
                  $repeat_section = true;
                }
              } else {
                $delete_cur_instance = true;
              }
            }
            // get the first instance of this template on the page being edited,
            // even if there are more
            if (preg_match('/\{\{' . $tif->template_name . '(.*?)\}\}/mis', $existing_page_content, $matches)) {
              $existing_template_text = $matches[1];
              // create array of contents of this template
              // somewhat of a hack - this array starts out with one element,
              // so that adding fields with no corresponding key will give them
              // an index starting with 1, not 0, to match MediaWiki's counting
              // system
              $template_contents = array(null);
              $template_fields = explode('|', $existing_template_text);
              foreach ($template_fields as $field) {
                if ($field != null) {
                  // either there's an equals sign near the beginning or not -
                  // handling is similar in either way; if there's no equals
                  // sign, the index of this field becomes the key
                  $sub_fields = explode('=', $field, 2);
                  if (count($sub_fields) > 1) {
                    $template_contents[trim($sub_fields[0])] = trim($sub_fields[1]);
                  } else {
                    $template_contents[] = trim($sub_fields[0]);
                  }
                }
              }
              // now remove this template from the text being edited
              $existing_page_content = str_replace($matches[0], '', $existing_page_content);
            }
          }
          // now the same check as above, but if the input came from a form -
          // if multiple instances of this template are allowed, and the template
          // name of the current instance is the same as the next one, re-use
          // the template section on the next loop through
          if ((! $source_is_page) && $allow_multiple) {
            $possible_next_template_name = $query_template_name . '_' . ($instance_num + 1);
            $template_values = $wgRequest->getArray($query_template_name);
            if (count($wgRequest->getArray($possible_next_template_name)) > 0) {
              $repeat_section = true;
            } elseif ($template_values['add'] != null && !$instance_added) {
              $instance_added = true;
              if (count($wgRequest->getArray($full_template_name)) > 0) {
                $repeat_section = true;
              }
            } elseif (count($wgRequest->getArray($full_template_name)) == 0 && !$instance_added) {
              $delete_cur_instance = true;
            }
          }
        } elseif ($tag_title == 'end template') {
          // remove this tag, reset some variables, and close off form HTML tag
          $section = substr_replace($section, '', $brackets_loc, $brackets_end_loc + 3 - $brackets_loc);
          $allow_multiple = false;
          $template_name = null;
          $full_template_name = null;
          if ($template_label != null) {
            $form_text .= "</fieldset>\n";
          }
          $template_label = null;
        } elseif ($tag_title == 'field') {
          $field_name = trim($tag_components[1]);
          // cycle through the other components
          $is_mandatory = false;
          $is_hidden = false;
          $input_type = null;
          $no_autocomplete = false;
          $autocomplete_category = null;
          $size = null;
          $num_rows = null;
          $num_cols = null;
          $default_value = "";
          $preload_page = null;
          for ($i = 2; $i < count($tag_components); $i++) {
            $component = trim($tag_components[$i]);
            if ($component == 'mandatory') {
              $is_mandatory = true;
            } elseif ($component == 'hidden') {
              $is_hidden = true;
            } else {
              $sub_components = explode('=', $component);
              if (count($sub_components) == 2) {
                if ($sub_components[0] == 'input type') {
                  $input_type = $sub_components[1];
                } elseif ($sub_components[0] == 'autocomplete on') {
                  $autocomplete_category = $sub_components[1];
                  // this field is overloaded - if it's empty, that indicates
                  // no automatic autocompleting should happen
                  if ($autocomplete_category == null) {
                    $no_autocomplete = true;
                  }
                } elseif ($sub_components[0] == 'size') {
                  $size = $sub_components[1];
                } elseif ($sub_components[0] == 'rows') {
                  $num_rows = $sub_components[1];
                } elseif ($sub_components[0] == 'cols') {
                  $num_cols = $sub_components[1];
                } elseif ($sub_components[0] == 'default') {
                  $default_value = $sub_components[1];
                } elseif ($sub_components[0] == 'preload') {
                  $preload_page = $sub_components[1];
                }
              }
            }
          }
          $error_str = null;
          // get the value from the request, if it's there
          $full_template_values = $wgRequest->getArray($full_template_name);
          $cur_value = $full_template_values[$field_name];
          if ($cur_value && ! is_array($cur_value)) {
            $cur_value = Sanitizer::safeEncodeAttribute($cur_value);
          }
          if (! $cur_value) {
            // set to default value specified in the form, if it's there
            $cur_value = $default_value;
          }

          // if the user is starting to edit a page, and that page contains
          // the current template being processed, get the current template
          // field's value in the existing page
          if ($source_is_page && ($existing_template_text != null)) {
            $cur_value = $template_contents[$field_name];
            if ($cur_value) {
              $cur_value = Sanitizer::safeEncodeAttribute($cur_value);
            }
          }
          // check that all mandatory values have been filled in
          if ($form_submitted && $is_mandatory && $cur_value == "") {
            $error_str = wfMsg('sf_blank_error');
            $passed_validation = false;
          }
          // the template display name might be different from what's in
          // the query string, if a previous instance was deleted
          if ($allow_multiple && $instance_was_deleted) {
            $template_display_name = $query_template_name . '_' . ($instance_num - 1);
          } else {
            $template_display_name = $full_template_name;
          }
          // handle non-template fields - 'page title' and 'free text'
          if ($template_name == '') {
            if ($field_name == 'page title') {
              // if we're adding a new page, make it an input field, and
              // possibly validate it; otherwise just put in the name
              if ($page_title == null) {
                $title = $wgRequest->getVal('page_title');
                if ($form_submitted && $title == "") {
                  $error_str = wfMsg('sf_blank_error');
                  $passed_validation = false;
                }
                if ($size == null) $size = 35;
                $new_text = SFFormPrinter::textEntryHTML($size, '', $field_name, $title, $error_str);
              } else {
                $new_text = $page_title;
              }
              $section = substr_replace($section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc);
            } elseif ($field_name == 'free text') {
              // add placeholders for the free text in both the form and
              // the page, using <free_text> tags - once all the free text
              // is known (at the end), it will get substituted in
              if ($num_rows == null) $num_rows = 5;
              if ($num_cols == null) $num_cols = 30;
              $new_text = SFFormPrinter::textAreaHTML($num_rows, $num_cols, null, 'free_text', '<free_text>', null);
              $free_text_was_included = true;
              $section = substr_replace($section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc);
	      // add a similar placeholder to the data text
              $data_text .= "<free_text>\n\n";
            }
          } else { // this field is part of a template
            if (is_array($cur_value)) {
              // if it has 1 or 2 elements, assume it's a checkbox; if it has
              // 3 elements, assume it's a date
              // - this handling will have to get more complex if other
              // possibilities get added
              if (count($cur_value) == 1) {
                $words_for_false = explode(',', wfMsgForContent('smw_false_words'));
                // for the various languages, the second word in the 'false'
                // series tends to be "no" - go with that one
                if (count($words_for_false) > 2) {
                  $no = $words_for_false[1];
                } else {
                  $no = $words_for_false[0];
                }
                $cur_value_in_template = $no;
              } elseif (count($cur_value) == 2) {
                $words_for_true = explode(',', wfMsgForContent('smw_true_words'));
                // the second word in the 'true' series tends to be "yes" -
                // go with that one
                if (count($words_for_true) > 2) {
                  $yes = $words_for_true[1];
                } else {
                  $yes = $words_for_true[0];
                }
                $cur_value_in_template = $yes;
              } elseif (count($cur_value) == 3) {
                $month = $cur_value['month'];
                $day = $cur_value['day'];
                $year = $cur_value['year'];
                if ($month != '' && $day != '' && $year != '') {
                  $cur_value_in_template = "$month $day, $year";
                } else {
                  $cur_value_in_template = "";
                }
              }
            } else { // value is not an array
              $cur_value_in_template = $cur_value;
            }
            $new_text = SFFormPrinter::formTemplateFieldHTML($template_display_name, $field_name, $instance_num,
              $cur_value, $is_mandatory, $is_hidden, $input_type, $size, $num_rows, $num_cols, $no_autocomplete,
              $autocomplete_category, $error_str, $all_fields, $strict_parsing);
            if ($new_text) {
              if (is_numeric($field_name)) {
                // if the value is null, don't include it at all -
                // TODO: this isn't quite right
                if ($cur_value_in_template != '')
                  $template_text .= "|$cur_value_in_template";
              } else {
                // if the value is null, don't include it at all
                if ($cur_value_in_template != '')
                  $template_text .= "\n|$field_name=$cur_value_in_template";
              }
              $section = substr_replace($section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc);
            } else {
              $start_position = $brackets_end_loc;
            }
          }
        } else { // tag is not one of the three allowed values
          // ignore tag
          $start_position = $brackets_end_loc;
        }
      } // end while

      if ($full_template_name != '' && ! $delete_cur_instance) {
	// add another newline before the final bracket, if this template
	// call is already more than one line
        if (strpos($template_text, "\n")) {
          $template_text .= "\n";
        }
        $template_text .= "}}\n";
        // if this is the last of its kind, add another newline
        if (! $repeat_section)
          $template_text .= "\n";
        $data_text .= $template_text;
      }
      if ($allow_multiple) {
        if (! $delete_cur_instance) {
          $input_name = $full_template_name . '[delete]';
          $remove_text = wfMsg('sf_editdata_remove');
          $gTabIndex++;
          $form_text .=<<<END
         <div style="background-color:#cccccc; padding:7px; margin:10px 0px 10px 0px;">
        $section
        <input tabindex="$gTabIndex" type="submit" name="$input_name" value="$remove_text" $gDisabledText>
        </div>

END;
        }
        if ($repeat_section) {
          // this will cause the section to be re-parsed on the next go
          $section_num--;
        } else {
          // this is the last instance of this template - stick an 'add'
          // button in the form
          $input_name = $template_name . '[add]';
          $add_another = wfMsg('sf_editdata_addanother');
          $gTabIndex++;
          $form_text .=<<<END
	<p style="margin-left:10px;">
	<input tabindex="$gTabIndex" type="submit" name="$input_name" value="$add_another" $gDisabledText></p>

END;
        }
      } else {
        $form_text .= $section;
      }

    } // end for

    // if it wasn't already included in the form definition, put the
    // 'free text' input at the bottom of the form
    if (! $free_text_was_included) {
      $form_text .= '	<fieldset><legend>' . wfMsg('sf_editdata_freetextlabel') . "</legend>\n";
      $form_text .= SFFormPrinter::textAreaHTML(5, 30, null, 'free_text', '<free_text>', null);
      $form_text .= "    </fieldset>\n";
    }
    // get free text, and add to page data, as well as retroactively
    // inserting it into the form
    if ($source_is_page) {
      // if the page is the source, free_text will just be whatever in the
      // page hasn't already been inserted into the form
      $free_text = trim($existing_page_content);
    // or get it from a form submission
    } elseif ($wgRequest->getCheck('free_text')) {
      $free_text = Sanitizer::safeEncodeAttribute($wgRequest->getVal('free_text'));
      if (! $free_text_was_included) {
        $data_text .= "<free_text>";
      }
    // of get it from the form definition
    } elseif ($preload_page != null) {
      $free_text = SFFormPrinter::getPreloadedText($preload_page);
    } else {
      $free_text = null;
    }
    // now that we have it, substitute free text into the form and page
    $form_text = str_replace('<free_text>', $free_text, $form_text);
    $data_text = str_replace('<free_text>', $free_text, $data_text);
    return array($form_text, $title, $data_text, $passed_validation);
  }

  function formTemplateFieldHTML($template_name, $field_name, $instance_num, $cur_value, $is_mandatory, $is_hidden, $input_type, $size, $num_rows, $num_cols, $no_autocomplete, $autocomplete_category, $error_str, $all_fields, $strict_parsing) {

    // see if this field matches one of the fields defined for this template -
    // if it is, use all available information about that field; if it's not,
    // either include it in the form or not, depending on whether template
    // has 'strict' setting in the form definition
    foreach ($all_fields as $cur_field) {
      if ($field_name == $cur_field->field_name) {
        $text = SFFormPrinter::formFieldHTML($template_name, $field_name, $cur_field, $instance_num, $cur_value, $is_mandatory, $is_hidden, $input_type, $size, $num_rows, $num_cols, $no_autocomplete, $autocomplete_category, $error_str);
        return $text;
      }
    }
    // if we're still here, field wasn't found
    if (! $strict_parsing) {
      $dummy_field = new SFTemplateField();
      $text = SFFormPrinter::formFieldHTML($template_name, $field_name, $dummy_field, $instance_num, $cur_value, $is_mandatory, $is_hidden, $input_type, $size, $num_rows, $num_cols, $no_autocomplete, $autocomplete_category, $error_str);
      return $text;
    }
    return null;
  }

  function formFieldHTML($template_name, $field_name, $template_field, $instance_num, $cur_value, $is_mandatory, $is_hidden, $input_type, $size, $num_rows, $num_cols, $no_autocomplete, $autocomplete_category, $error_str) {
    global $smwgContLang;
        if ($is_hidden) {
          $text = SFFormPrinter::hiddenFieldHTML($template_name, $field_name, $cur_value);
        } elseif ($autocomplete_category != null) {
          if ($size == null) $size = 35;
          $text = SFFormPrinter::textEntryWithAutocompleteHTML($size, $template_name, $field_name, $instance_num, $autocomplete_category, false, $cur_value, $error_str);
        } elseif ($input_type == 'text') {
          if ($size == null) $size = 35;
          $text = SFFormPrinter::textEntryHTML($size, $template_name, $field_name, $cur_value, $error_str);
        } elseif ($input_type == 'textarea') {
          if ($num_rows == null) $num_rows = 4;
          if ($num_cols == null) $num_cols = 40;
          $text = SFFormPrinter::textAreaHTML($num_rows, $num_cols, $template_name, $field_name, $cur_value, $error_str);
        } elseif ($input_type == 'date') {
          $text = SFFormPrinter::dateEntryHTML($template_name, $field_name, $cur_value, $error_str);
        } elseif ($input_type == 'checkbox') {
          $text = SFFormPrinter::checkboxHTML($template_name, $field_name, $cur_value, $error_str);
        } elseif ($template_field->attr_or_rel == "relation") {
          if ($size == null) $size = 35;
          if ($no_autocomplete) {
            $text = SFFormPrinter::textEntryHTML($size, $template_name, $field_name, $cur_value, $error_str);
          } else {
            $text = SFFormPrinter::textEntryWithAutocompleteHTML($size, $template_name, $field_name, $instance_num, $template_field->semantic_field, true, $cur_value, $error_str);
          }
        } else { // input type not defined in form, and not a relation
          $attr_type = $template_field->attribute_type;
          if ($attr_type == $smwgContLang->smwDatatypeLabels['smw_enum']) {
            // prepend the "None" option if it's not a mandatory field
            $include_none = ! $is_mandatory;
            $text = SFFormPrinter::dropdownHTML($template_name, $field_name, $template_field->possible_values, $include_none, $cur_value, $error_str);
          } elseif ($attr_type == $smwgContLang->smwDatatypeLabels['smw_datetime']) {
            $text = SFFormPrinter::dateEntryHTML($template_name, $field_name, $cur_value, $error_str);
          } elseif ($attr_type == $smwgContLang->smwDatatypeLabels['smw_float'] || $attr_type == $smwgContLang->smwDatatypeLabels['smw_int']) {
            if ($size == null) $size = 10;
            $text = SFFormPrinter::textEntryHTML($size, $template_name, $field_name, $cur_value, $error_str);
          } elseif ($attr_type == $smwgContLang->smwDatatypeLabels['smw_url']) {
            if ($size == null) $size = 100;
            $text = SFFormPrinter::textEntryHTML($size, $template_name, $field_name, $cur_value, $error_str);
          } elseif ($attr_type == $smwgContLang->smwDatatypeLabels['smw_bool']) {
            $text = SFFormPrinter::checkboxHTML($template_name, $field_name, $cur_value, $error_str);
          } else { // String or anything else
            if ($size == null) $size = 35;
            $text = SFFormPrinter::textEntryHTML($size, $template_name, $field_name, $cur_value, $error_str);
          }
        }
        return $text;
  }

  function createAutocompleteValuesString($field_name, $is_relation) {
    global $sfgMaxAutocompleteValues;
    $fname = 'SFFormPrinter::createAutocompleteValuesString';
    $db = & wfGetDB( DB_SLAVE );
    $sql_options = array();
    $sql_options['LIMIT'] = $sfgMaxAutocompleteValues;
    // the query depends on whether this field is a relation or a category
    if ($is_relation) {
      $conditions = "relation_title = '$field_name'";
      $res = $db->select( $db->tableName('smw_relations'),
                          'DISTINCT object_title',
                          $conditions, $fname, $sql_options);
    } else {
      $conditions = "cl_from = page_id AND cl_to = '$field_name'";
      $res = $db->select( $db->tableNames('categorylinks', 'page'),
                          'page_title',
                          $conditions, $fname, $sql_options);
    }
    if ($db->numRows( $res ) > 0) {
      $names_array = array();
      while ($row = $db->fetchRow($res)) {
        $cur_value = str_replace("'", "\'", $row[0]);
        $names_array[] = str_replace('_', ' ', $cur_value);
      }
      $array_str = "['" . implode("', '", $names_array) . "']";
    }
    $db->freeResult($res);
    return $array_str;
  }

  function textEntryHTML($size, $template_name, $field_name, $cur_value, $error_str) {
    global $gTabIndex, $gDisabledText;
    $gTabIndex++;

    if ($template_name == null || $template_name == '')
      $input_name = $field_name;
    else
      $input_name = $template_name . '[' . $field_name . ']';
    $text =<<<END
	<input tabindex="$gTabIndex" class="createboxInput" name="$input_name" type="text"
        value="$cur_value" size="$size" $gDisabledText/>

END;
    if ($error_str) {
      $text .= "      <font color=\"red\">$error_str</font>\n";
    }
    return $text;
  }

  function dropdownHTML($template_name, $field_name, $possible_values, $include_none, $cur_value, $error_str) {
    global $gTabIndex, $gDisabledText;
    $gTabIndex++;

    $text = '     <select tabindex="' . $gTabIndex . '" name="' . $template_name . '[' . $field_name . ']" ' . $gDisabledText . '>';
    if ($include_none)
      $text .= "  <option value=\"\">[None]</option>\n";
    foreach ($possible_values as $possible_value) {
      $text .= "  <option value=\"$possible_value\"";
      if ($possible_value == $cur_value) {$text .= " selected=\"selected\""; }
      $text .= ">$possible_value</option>\n";
    }
    $text .= "    </select>\n";
    if ($error_str)
      $text .= "      <font color=\"red\">$error_str</font>\n";
    return $text;
  }

  function textEntryWithAutocompleteHTML($size, $template_name, $field_name, $instance_num, $semantic_field_name, $is_relation, $cur_value, $error_str) {
    global $gTabIndex, $gDisabledText;
    $gTabIndex++;

    $input_id = $template_name . "_" . $field_name . "_input";
    $div_name = $template_name . "_" . $field_name . "_div";
    // add instance num to the HTML labels if it's the second or higher, to
    // guarantee uniqueness of name; autocompletion fails on IE otherwise
    if ($instance_num > 1) {
      $input_id .= "_" . $instance_num;
      $div_name .= "_" . $instance_num;
    }
    $input_name = $template_name . '[' . $field_name . ']';

    $options_str = SFFormPrinter::createAutocompleteValuesString(str_replace(' ', '_', $semantic_field_name), $is_relation);
    $text =<<<END
        <input tabindex="$gTabIndex" id="$input_id" autocomplete="off" class="createboxInput" name="$input_name" type="text"
        value="" size="$size" $gDisabledText/>

END;
    if ($error_str) {
      $text .= "   <font color=\"red\">$error_str</font>";
    }
    $text .=<<<END
        <div class="page_name_auto_complete" id="$div_name" style="display:none"></div>
<script type="text/javascript">
new Autocompleter.Local('$input_id', '$div_name', $options_str, {});

END;
    if ($cur_value) {
      $text .= "document.getElementById('$input_id').value = \"$cur_value\"\n";
    }
    $text .= "</script>\n";
    return $text;
  }

  function textAreaHTML($rows, $cols, $template_name, $field_name, $cur_value, $error_str) {
    global $gTabIndex, $gDisabledText;
    $gTabIndex++;

    if ($template_name == null) {
      $input_name = $field_name;
    } else {
      $input_name = $template_name . '[' . $field_name . ']';
    }
    $text =<<<END
	<textarea tabindex="$gTabIndex" name="$input_name" rows=$rows cols=$cols $gDisabledText/>$cur_value</textarea>

END;
    if ($error_str)
      $text .= "      <font color=\"red\">$error_str</font>\n";
    return $text;
  }

  function monthDropdownHTML($cur_month, $template_name, $index) {
    global $gTabIndex, $gDisabledText;
    $gTabIndex++;

    $text = '	<select tabindex="' . $gTabIndex . '" name="' . $template_name . '[' . $index . "][month]\" $gDisabledText>\n";
    $month_names = array(
      wfMsgForContent('sf_january'),
      wfMsgForContent('sf_february'),
      wfMsgForContent('sf_march'),
      wfMsgForContent('sf_april'),
      wfMsgForContent('sf_may'),
      wfMsgForContent('sf_june'),
      wfMsgForContent('sf_july'),
      wfMsgForContent('sf_august'),
      wfMsgForContent('sf_september'),
      wfMsgForContent('sf_october'),
      wfMsgForContent('sf_november'),
      wfMsgForContent('sf_december')
    );
    foreach ($month_names as $name) {
      $text .= "	<option value=\"$name\"";
      if ($name == $cur_month) {$text .= " selected=\"selected\""; }
      $text .= ">$name</option>\n";
    }
    $text .= "	</select>\n";
    return $text;
  }

  function dateEntryHTML($template_name, $index, $date, $error_str) {
    global $gTabIndex, $gDisabledText;
    $gTabIndex++;

    if ($date) {
      // can show up here either as an array or a string, depending on
      // whether it came from user input or a wiki page
      if (is_array($date)) {
        $year = $date['year'];
        $month = $date['month'];
        $day = $date['day'];
      } else {
        $actual_date = strtotime($date);
        $year = date("Y", $actual_date);
        $month = date("F", $actual_date);
        $day = date("j", $actual_date);
      }
    } else {
      $cur_date = getdate();
      $year = $cur_date[year];
      $month = $cur_date[month];
      $day = null; // no need for day
    }
    $text .= SFFormPrinter::monthDropdownHTML($month, $template_name, $index);
    $gTabIndex++;
    $text .= '  <input tabindex="' . $gTabIndex . '" name="' . $template_name . '[' . $index . '][day]" type="text" value="' . $day . '" size="2"/ ' . $gDisabledText . '>' . "\n";
    $gTabIndex++;
    $text .= '  <input tabindex="' . $gTabIndex . '" name="' . $template_name . '[' . $index . '][year]" type="text" value="' . $year . '" size="4"/ ' . $gDisabledText . '>' . "\n";
    if ($error_str) {
      $text .= "     <font color=\"red\">$error_str</font>\n";
    }
    return $text;
  }

  function radioButtonHTML($template_name, $field_name, $cur_value, $options_array) {
    global $gTabIndex, $gDisabledText;
    $gTabIndex++;

    if ($title)
      $text .= "     <strong>$title:</strong>\n";
    foreach ($options_array as $i => $option) {
      $text .= '	<input type="radio" tabindex="' . $gTabIndex . '" name="' . $template_name . '[' . $field_name . ']" value="$option"';
      if ($cur_value == $option || (! $cur_value && $i == 0))
        $text .= " checked";
      $text .= " $gDisabledText/> $option\n";
    }
    return $text;
  }

  function checkboxHTML($template_name, $field_name, $cur_value, $error_str) {
    global $gTabIndex, $gDisabledText;
    $gTabIndex++;

    // can show up here either as an array or a string, depending on
    // whether it came from user input or a wiki page
    if (is_array($cur_value)) {
      $checked_str = ($cur_value[value] == 'on') ? " checked" : "";
    } else {
      // default to false - no need to check if it matches a 'false' word
      $vlc = strtolower(trim($cur_value));
      if (in_array($vlc, explode(',', wfMsgForContent('smw_true_words')), TRUE)) {
        $checked_str = " checked";
      } else {
        $checked_str = "";
      }
    }
    $input_name = $template_name . '[' . $field_name . ']';
    $text =<<<END
	<input name="{$input_name}[is_checkbox]" type="hidden" value="true" />
	<input name="{$input_name}[value]" type="checkbox" tabindex="$gTabIndex " $checked_str $gDisabledText/>

END;
    if ($error_str) {
      $text .= "      <font color=\"red\">$error_str</font>\n";
    }
    return $text;
  }

  function hiddenFieldHTML($template_name, $field_name, $cur_value) {
    if ($template_name == null || $template_name == '')
      $input_name = $field_name;
    else
      $input_name = $template_name . '[' . $field_name . ']';
    $text =<<<END
	<input type="hidden" name="$input_name" value="$cur_value" />

END;
    return $text;
  }

  function redirectText($page_name, $data_text) {
    global $wgUser, $wgRequest;

    // first, add various hidden fields that allow for editing
    $title = Title::newFromText($page_name);
    $article = new Article($title);
    $starttime = wfTimestampNow();
    $edittime = $article->getTimestamp();
    if ( $wgUser->isLoggedIn() )
      $token = htmlspecialchars($wgUser->editToken());
    else
      $token = "";
    if ($wgRequest->getCheck('wpSave'))
      $page_action = "wpSave";
    elseif ($wgRequest->getVal('wpPreview'))
      $page_action = "wpPreview";
    else # if $wgRequest->getVal('wpDiff')
      $page_action = "wpDiff";

    global $wgScript;
    $text = <<<END
        <form id="editform" name="editform" method="post" action="$wgScript?title=$page_name&amp;action=submit">
        <input type="hidden" name="wpTextbox1" id="wpTextbox1" value="$data_text" />
    <input type="hidden" name="wpStarttime" value="$starttime" />
    <input type="hidden" name="wpEdittime" value="$edittime" />
    <input type="hidden" name="wpEditToken" value="$token" />
    <input type="hidden" name="wpSummary" value="{$wgRequest->getVal('wpSummary')}" />

END;
    if ($wgRequest->getCheck('wpMinoredit'))
      $text .= '    <input type="hidden" name="wpMinoredit">' . "\n";
    if ($wgRequest->getCheck('wpWatchthis'))
      $text .= '    <input type="hidden" name="wpWatchthis">' . "\n";

    $text .= <<<END
    <input type="hidden" name="$page_action" />
        </form>
        <script type="text/javascript">
        document.editform.submit();
        </script>

END;
    return $text;
  }

  // Much of this function is based on MediaWiki's EditPage::showEditForm()
  function formBottom($target_title = null) {
    global $wgUser, $wgRightsText, $wgParser;
    global $gTabIndex, $gDisabledText;
    $sk = $wgUser->getSkin();

    $copywarn = "<div id=\"editpage-copywarn\">\n" .
      wfMsg( $wgRightsText ? 'copyrightwarning' : 'copyrightwarning2',
             '[[' . wfMsgForContent( 'copyrightpage' ) . ']]',
	     null,
             $wgRightsText ) . "\n</div>";
    $title = new Title();
    $options = new ParserOptions();
    $copywarn_output = $wgParser->parse($copywarn, $title, $options);
    $copywarn_text = $copywarn_output->getText();
    $summary_text = wfMsg('summary');
    if ($target_title == null)
      $cancel = '';
    else
      $cancel = $sk->makeKnownLink( $target_title->getPrefixedText(),
                  wfMsgExt('cancel', array('parseinline')) );
    $edithelpurl = Skin::makeInternalOrExternalUrl( wfMsgForContent( 'edithelppage' ));
    $edithelp = '<a target="helpwindow" href="'.$edithelpurl.'">'.
      htmlspecialchars( wfMsg( 'edithelp' ) ).'</a> '.
      htmlspecialchars( wfMsg( 'newwindow' ) );

    $gTabIndex++;
    $text =<<<END
	$copywarn_text
	<span id='wpSummaryLabel'><label for='wpSummary'>{$summary_text}:</label></span>
	<div class='editOptions'>
	<input tabindex="$gTabIndex" type='text' value="" name='wpSummary' id='wpSummary' maxlength='200' size='60' $gDisabledText/><br />

END;
    $minor   = wfMsgExt('minoredit', array('parseinline'));
    if ( $wgUser->isAllowed('minoredit') ) {
      $gTabIndex++;
      $text .=
        "<input tabindex='$gTabIndex' type='checkbox' value='1' name='wpMinoredit'" .
        " accesskey='".wfMsg('accesskey-minoredit')."' id='wpMinoredit' $gDisabledText/>\n".
        "<label for='wpMinoredit' title='".wfMsg('tooltip-minoredit')."'>{$minor}</label>\n";
     }

    $watchthis = wfMsgExt('watchthis', array('parseinline'));
    if ( $wgUser->isLoggedIn() ) {
      $gTabIndex++;
      $text .= "<input tabindex='$gTabIndex' type='checkbox' name='wpWatchthis'".
        " accesskey=\"".htmlspecialchars(wfMsg('accesskey-watch'))."\" id='wpWatchthis' $gDisabledText/>\n".
        "<label for='wpWatchthis' title=\"" .
        htmlspecialchars(wfMsg('tooltip-watch'))."\">{$watchthis}</label>\n";
    }

    $gTabIndex++;
    $temp = array(
      'id'        => 'wpSave',
      'name'      => 'wpSave',
      'type'      => 'submit',
      'tabindex'  => $gTabIndex,
      'value'     => wfMsg('savearticle'),
      'accesskey' => wfMsg('accesskey-save'),
      'title'     => wfMsg('tooltip-save'),
      $gDisabledText => '',
    );
    $save_button = wfElement('input', $temp, '');

    $gTabIndex++;
    $temp = array(
      'id'        => 'wpPreview',
      'name'      => 'wpPreview',
      'type'      => 'submit',
      'tabindex'  => $gTabIndex,
      'value'     => wfMsg('showpreview'),
      'accesskey' => wfMsg('accesskey-preview'),
      'title'     => wfMsg('tooltip-preview'),
      $gDisabledText => '',
    );
    $preview_button = wfElement('input', $temp, '');

    $gTabIndex++;
    $temp = array(
      'id'        => 'wpDiff',
      'name'      => 'wpDiff',
      'type'      => 'submit',
      'tabindex'  => $gTabIndex,
      'value'     => wfMsg('showdiff'),
      'accesskey' => wfMsg('accesskey-diff'),
      'title'     => wfMsg('tooltip-diff'),
      $gDisabledText => '',
    );
    $diff_button = wfElement('input', $temp, '');

    $text .=<<<END
	<br />
	<div class='editButtons'>
		$save_button
		$preview_button
		$diff_button
		<span class='editHelp'>{$cancel} | {$edithelp}</span>
	</div><!-- editButtons -->
	</div><!-- editOptions -->
	</form>

END;
    return $text;
  }

  // based on MediaWiki's EditPage::getPreloadedText()
  function getPreloadedText($preload) {
    if ( $preload === '' )
       return '';
    else {
      $preloadTitle = Title::newFromText( $preload );
        if ( isset( $preloadTitle ) && $preloadTitle->userCanRead() ) {
          $rev=Revision::newFromTitle($preloadTitle);
        if ( is_object( $rev ) ) {
          $text = $rev->getText();
          // Remove <noinclude> sections and <includeonly> tags from text
          $text = StringUtils::delimiterReplace('<noinclude>', '</noinclude>', '', $text);
          $text = strtr($text, array('<includeonly>' => '', '</includeonly>' => ''));
          return $text;
        } else
          return '';
        }
     }
  } 

}

?>
