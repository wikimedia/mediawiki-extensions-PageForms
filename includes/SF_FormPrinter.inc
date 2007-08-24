<?php
/**
 * Handles the creation and running of a user-created form.
 *
 * @author Yaron Koren
 * @author Nils Oppermann
 * @author Jeffrey Stuckman
 */

class SFFormPrinter {

  function formHTML($form_def, $form_submitted, $source_is_page, $existing_page_content = null, $page_title = null) {
    global $wgRequest, $wgUser, $wgParser;
    global $gTabIndex; // used to represent the current tab index in the form
    global $gDisabledText; // disables all form elements if user doesn't have edit permission

    // initialize some variables
    $title = null;
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
    $javascript_text = "";
    $js_validation_calls = array();

    // Remove <noinclude> sections and <includeonly> tags from form definition
    $form_def = StringUtils::delimiterReplace('<noinclude>', '</noinclude>', '', $form_def);
    $form_def = strtr($form_def, array('<includeonly>' => '', '</includeonly>' => ''));

    // turn form definition file into an array of sections, one for each
    // template definition (plus the first section)
    $form_def_sections = array();
    $start_position = 0;
    $section_start = 0;
    $free_text_was_included = false;
    $all_values_for_template = array();
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
    } // end while
    $form_def_sections[] = trim(substr($form_def, $section_start));

    // cycle through form definition file (and possibly an existing article
    // as well), finding template and field declarations and replacing them
    // with form elements, either blank or pre-populated, as appropriate
    $all_fields = array();
    $data_text = "";
    $template_name = "";
    $allow_multiple = false;
    $instance_num = 0;
    $all_instances_printed = false;
    $strict_parsing = false;
    $autocomplete_values = array();
    $autocomplete_values['mappings'] = array();
    // initialize list of choosers (dropdowns with available templates)
    $choosers = array();
    for ($section_num = 0; $section_num < count($form_def_sections); $section_num++) {
      $tif = new SFTemplateInForm();
      $start_position = 0;
      $template_text = "";
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
          $chooser_name = false;
          $chooser_caption = false;
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
              if ($sub_components[0] == 'chooser') {
                $allow_multiple = true;
                $chooser_name = $sub_components[1];
              }
              if ($sub_components[0] == 'chooser caption') {
                $chooser_caption = $sub_components[1];
              }
            }
          }
          // if this is the first instance, add the label in the form
          if (($old_template_name != $template_name) && isset($template_label)) {
            // add a placeholder to the form text so the fieldset can be
            // hidden if chooser support demands it
            if ($chooser_name !== false)
              $form_text .= "<fieldset [[placeholder]] haschooser=true>\n";
            else
               $form_text .= "<fieldset>\n";
            $form_text .= "<legend>$template_label</legend>\n";
          }
          $template_text .= "{{" . $tif->template_name;
          $all_fields = $tif->getAllFields();
          // remove template tag
          $section = substr_replace($section, '', $brackets_loc, $brackets_end_loc + 3 - $brackets_loc);
          $template_instance_query_values = $wgRequest->getArray($query_template_name);
          // if we are editing a page, and this template can be found more than
          // once in that page, and multiple values are allowed, repeat this
          // section
          $existing_template_text = null;
          if ($source_is_page) {
            $matches = array();
            if ($allow_multiple) {
              // find the number of instances of this template in the page -
              // if it's more than one, re-parse this section of the
              // definition form for the subsequent template instances in
              // this page; if there's none, don't include fields at all.
              // there has to be a more efficient way to handle multiple
              // instances of templates, one that doesn't involve re-parsing
              // the same tags, but I don't know what it is.
              if (preg_match_all('/\{\{' . $tif->template_name . '(.*?)\}\}/mis', $existing_page_content, $matches)) {
                $instance_num++;
              } else {
                $all_instances_printed = true;
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
              // cycle through template call, splitting it up by pipes ('|'),
              // except when that pipe is part of a piped link
              $field = "";
              $uncompleted_square_brackets = 0;
              for ($i = 0; $i < strlen($existing_template_text); $i++) {
                $c = $existing_template_text[$i];
                if (($i == strlen($existing_template_text) - 1) ||
                    ($c == '|' && $uncompleted_square_brackets == 0)) {
                  if ($field != null) {
                    // if this was the last character in the template, append
                    // this character
                    if ($i == strlen($existing_template_text) - 1) {
                      $field .= $c;
                    }
                    // either there's an equals sign near the beginning or not -
                    // handling is similar in either way; if there's no equals
                    // sign, the index of this field becomes the key
                    $sub_fields = explode('=', $field, 2);
                    if (count($sub_fields) > 1) {
                      $template_contents[trim($sub_fields[0])] = trim($sub_fields[1]);
                    } else {
                      $template_contents[] = trim($sub_fields[0]);
                    }
                    $field = '';
                  }
                } else {
                  $field .= $c;
                  if ($c == '[') {
                    $uncompleted_square_brackets++;
                  } elseif ($c == ']' && $uncompleted_square_brackets > 0) {
                    $uncompleted_square_brackets--;
                  }
                }
              }
              // now remove this template from the text being edited
              $existing_page_content = str_replace($matches[0], '', $existing_page_content);
            }
          }
          // if the input is from the form (meaning the user has hit one
          // of the bottom row of buttons), and we're dealing with a
          // multiple template, get the values for this instance of this
          // template, then delete them from the array, so we can get the
          // next group next time - the next() command for arrays doesn't
          // seem to work here
         if ((! $source_is_page) && $allow_multiple && $wgRequest) {
            $all_instances_printed = true;
            if ($old_template_name != $template_name) {
              $all_values_for_template = $wgRequest->getArray($query_template_name);
            }
            if ($all_values_for_template) {
              $cur_key = key($all_values_for_template);
              // skip the input coming in from the "starter" div
              if ($cur_key == 'num') {
                unset($all_values_for_template[$cur_key]);
                $cur_key = key($all_values_for_template);
              }
              if ($template_instance_query_values = current($all_values_for_template)) {
                $all_instances_printed = false;
                unset($all_values_for_template[$cur_key]);
              }
            }
          }
        } elseif ($tag_title == 'end template') {
          // remove this tag, reset some variables, and close off form HTML tag
          $section = substr_replace($section, '', $brackets_loc, $brackets_end_loc + 3 - $brackets_loc);
          $template_name = null;
          if (isset($template_label)) {
            $form_text .= "</fieldset>\n";
            unset ($template_label);
          }
          $allow_multiple = false;
          $all_instances_printed = false;
          $instance_num = 0;
          // if the hiding placeholder is still around, this fieldset should
          // be hidden because it is empty and choosers are being used. So,
          // hide it.
          $form_text = str_replace("[[placeholder]]", "style='display:none'", $form_text);
        } elseif ($tag_title == 'field') {
          $field_name = trim($tag_components[1]);
          // cycle through the other components
          $is_mandatory = false;
          $is_hidden = false;
          $is_restricted = false;
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
            } elseif ($component == 'restricted') {
              $is_restricted = true;
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
          // get the value from the request, if it's there
          if (isset ($template_instance_query_values[$field_name]))
            $cur_value = $template_instance_query_values[$field_name];
          else
            $cur_value = '';
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
          if ($source_is_page && (! empty($existing_template_text))) {
            if (isset($template_contents[$field_name]))
              $cur_value = $template_contents[$field_name];
            else
              $cur_value = '';
            if ($cur_value) {
              $cur_value = Sanitizer::safeEncodeAttribute($cur_value);
            }
          }

          // hack - if this is a 'restricted' field, and the user isn't
          // a sysop, set the global $gDisabledText temporarily to
          // 'disabled' - set it back at the end of this function
          $actual_disabled_text = $gDisabledText;
          // determine whether user is a sysop by whether or not they're
          // allowed to delete things - if there's a better way, please let
          // me know
          if ($is_restricted && (! $wgUser || ! $wgUser->isAllowed('delete'))) {
            // set background color to get around weird IE behavior (field
            // is disabled, but color stays white)
            $gDisabledText = "disabled";
            //$gDisabledText = "disabled style=\"background-color: #dddddd; border: 1px solid #ccccff;\"";
            //$gDisabledText = "disabled readonly";
          }

          // handle non-template fields - 'page title' and 'free text'
          if ($template_name == '') {
            if ($field_name == 'page title') {
              // the actual value should be non-null - stick it in
              $new_text = $page_title;
            } elseif ($field_name == 'free text') {
              // add placeholders for the free text in both the form and
              // the page, using <free_text> tags - once all the free text
              // is known (at the end), it will get substituted in
              if ($is_hidden) {
                $new_text = SFFormPrinter::hiddenFieldHTML('free_text', '<free_text>');
              } else {
                if ($num_rows == null) $num_rows = 5;
                if ($num_cols == null) $num_cols = 30;
                $new_text = SFFormPrinter::textAreaHTML($num_rows, $num_cols, 'free_text', '<free_text>', null);
              }
              $free_text_was_included = true;
              // add a similar placeholder to the data text
              $data_text .= "<free_text>\n\n";
            }
            $section = substr_replace($section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc);
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
            if ($query_template_name == null || $query_template_name == '')
              $input_name = $field_name;
            elseif ($allow_multiple)
              // 'num' will get replaced by an actual index, either in PHP
              // or in Javascript, later on
              $input_name = $query_template_name . '[num][' . $field_name . ']';
            else
              $input_name = $query_template_name . '[' . $field_name . ']';
            $new_text = SFFormPrinter::formTemplateFieldHTML($field_name, $input_name, $allow_multiple,
              $cur_value, $is_mandatory, $is_hidden, $is_restricted, $input_type, $size, $num_rows, $num_cols, $no_autocomplete,
              $autocomplete_category, $all_fields, $strict_parsing, $autocomplete_values);

            // if this was field was disabled due to being 'restricted',
            // restore $gDisabledText back to its actual value, and add
            // a hidden field holding the value of this field, because
            // disabled inputs for some reason don't submit their value
            if ($is_restricted && ! $wgUser->isAllowed('delete')) {
              $gDisabledText = $actual_disabled_text;
              if ($field_name == 'free text') {
                $new_text .= SFFormPrinter::hiddenFieldHTML('free_text', '<free_text>');
              } else {
                $new_text .= SFFormPrinter::hiddenFieldHTML($input_name, $cur_value);
              }
            }

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
              // also add to Javascript validation code
              if ($is_mandatory) {
		$input_id = "input_" . $gTabIndex;
		$info_id = "info_" . $gTabIndex;
                if ($input_type == 'date') {
                  $js_validation_calls[] = "validate_mandatory_field ('$input_id" . "_1', '$info_id')";
                  $js_validation_calls[] = "validate_mandatory_field ('$input_id" . "_2', '$info_id')";
                  $js_validation_calls[] = "validate_mandatory_field ('$input_id" . "_3', '$info_id')";
                } else {
                  $js_validation_calls[] = "validate_mandatory_field ('$input_id', '$info_id')";
                }
              }
            } else {
              $start_position = $brackets_end_loc;
            }
          }
        } else { // tag is not one of the three allowed values
          // ignore tag
          $start_position = $brackets_end_loc;
        }
      } // end while

      if (! $all_instances_printed && ($template_text != '')) {
	// add another newline before the final bracket, if this template
	// call is already more than one line
        if (strpos($template_text, "\n")) {
          $template_text .= "\n";
        }
        $template_text .= "}}\n\n";
        // TODO - should instances of the same template not be separated
        // by a blank line? if so, the following code could be used:
        //if (($data_text != "") &&
        //    (! $allow_multiple || $old_template_name != $template_name)) {
        //  $data_text .= "\n";
        //}
        $data_text .= $template_text;
      }
      if ($allow_multiple) {
        if (! $all_instances_printed) {
          $section = str_replace('[num]', "[{$instance_num}a]", $section);
          $remove_text = wfMsg('sf_editdata_remove');
          $form_text .=<<<END
	<div id="wrapper_$gTabIndex" class="multiple_template">
        $section
        <input type="button" onclick="removeInstance('wrapper_$gTabIndex');" value="$remove_text" tabindex="$gTabIndex" />
        </div>

END;
          // this will cause the section to be re-parsed on the next go
          $section_num--;
          // because there is an instance, the fieldset will never be hidden,
          // even if choosers are being used. So, do not hide the fieldset.
          $form_text = str_replace("[[placeholder]]", "", $form_text);
        } else {
          // this is the last instance of this template - stick an 'add'
          // button in the form
        $form_text .=<<<END
	<div id="starter_$query_template_name" class="multiple_template" style="display: none;">
        $section
        </div>
         <div id="main_$query_template_name"></div>

END;
          $add_another = wfMsg('sf_editdata_addanother');
          $form_text .=<<<END
	<p style="margin-left:10px;">
	<p><input type="button" onclick="addInstance('starter_$query_template_name', 'main_$query_template_name', '$gTabIndex');" value="$add_another" tabindex="$gTabIndex" /></p>

END;
           // if a chooser is being used for this template, add the template
           // to the chooser data array
          if ($chooser_name !== false)
            $choosers[$chooser_name][]= array($query_template_name, $gTabIndex, $chooser_caption);
        }
      } else {
        $form_text .= $section;
      }

    } // end for

    // if it wasn't already included in the form definition, put the
    // 'free text' input at the bottom of the form
    if (! $free_text_was_included) {
      $form_text .= '	<fieldset><legend>' . wfMsg('sf_editdata_freetextlabel') . "</legend>\n";
      $form_text .= SFFormPrinter::textAreaHTML(5, 30, 'free_text', '<free_text>', null);
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

    //Substitute the choosers in here too.
    $chooser_count = 0;
    $chooser_text = "";
    $using_choosers = "false";
    foreach ($choosers as $choosername => $chooser) {
      if (count($chooser)!=0) {
        $chooser_count++;
        if ($chooser_count==1) {
          // emit the initial javascript code
          $using_choosers = "true";
          $chooser_text .= <<<END
<script type="text/javascript">
function updatechooserbutton(f,n)
{
	document.getElementById(n).disabled = (f.options[f.selectedIndex].value=="invalid");
}

function addInstanceFromChooser(chooserid)
{
	var chooser = document.getElementById(chooserid);
	var optionstring = chooser.options[chooser.selectedIndex].value;
	var pos = optionstring.indexOf(",");
	var tabindex = optionstring.substr(0,pos);
	var chooservalue = optionstring.substr(pos+1);
	addInstance('starter_' + chooservalue, 'main_' + chooservalue, parseInt(tabindex));
}

//The fieldset containing the given element was just updated. If the fieldset is associated with a chooser,
//ensure that the fieldset is hidden if and only if there are no template instances inside.
function hideOrShowFieldset(element)
{
	//Find fieldset
	while (element.tagName.toLowerCase() != "fieldset")
		element = element.parentNode;
	//Bail out if fieldset is not part of chooser
	if (!element.getAttribute("haschooser"))
		return;
	//Now look for "input" or "select" tags that don't look like they're part of the starter template
	var inputs = element.getElementsByTagName("input");
	var x;
	var show = false;
	for (x=0;x<inputs.length;x++)
	{
		if (inputs[x].type=="text" && inputs[x].name.indexOf("[num]") == -1)
			show = true;
	}
	var selects = element.getElementsByTagName("select");
	for (x=0;x<selects.length;x++)
	{
		if (selects[x].name.indexOf("[num]") == -1)
			show = true;
	}
	//Now show or hide fieldset
	element.style.display = (show?"block":"none");
}
</script>
END;
        }
	
	

			
        $chooser_text .= "<p>$choosername:<select id='chooserselect$chooser_count' size='1' onchange='updatechooserbutton(this,\"chooserbutton$chooser_count\")'>";
        $chooser_text .= "<option value='invalid'>Choose a field to add</option>";
        foreach ($chooser as $chooser_item) {
          $chooser_value = str_replace('"','\\"',$chooser_item[0]);
          $tabindex = $chooser_item[1];
          $chooser_caption = $chooser_item[2];
          if ($chooser_caption === false)
            $chooser_caption = str_replace('_', ' ', $chooser_value);
            $chooser_text .= "<option value=\"$tabindex ,$chooser_value\">$chooser_caption</option>";
          }
        $chooser_text .= "</select>\n";
      }
      $chooser_text .= "<input type='button' onclick=\"addInstanceFromChooser('chooserselect$chooser_count');\" value='" . wfMsg('sf_editdata_addanother') . "' disabled='true' id='chooserbutton$chooser_count'></p>";
    }
	
    $form_text = str_replace('<choosers>', $chooser_text, $form_text);
	
	
    // add general Javascript code
    $blank_error_str = wfMsg('sf_blank_error');
    $javascript_text .=<<<END

function validate_mandatory_field(field_id, info_id) {
	field = document.getElementById(field_id);
	if (field.value.replace(/\s+/, '') == '') {
		infobox = document.getElementById(info_id);
		infobox.innerHTML = "$blank_error_str";
		//field.style.border = "1px solid red";
		return false;
	} else {
		return true;
	}
}

function validate_all() {
	var num_errors = 0;

END;
    foreach ($js_validation_calls as $function_call) {
      $javascript_text .= "	if (!$function_call) num_errors += 1;\n";
    }
    $remove_text = wfMsg('sf_editdata_remove');
    $javascript_text .=<<<END
	return (num_errors == 0);
}

var num_elements = 0;

function addInstance(starter_div_id, main_div_id, tab_index)
{
	var starter_div = document.getElementById(starter_div_id);
	var main_div = document.getElementById(main_div_id);
	num_elements++;
	
	//Create the new instance
	var new_div = starter_div.cloneNode(true);
	var div_id = 'div_gen_' + num_elements;
	new_div.className = 'multiple_template';
	new_div.id = div_id;
	new_div.style.display = 'block';
	
	// make internal ID unique for the relevant divs and spans, and replace
	// the [num] index in the element names with an actual unique index
	var children = new_div.getElementsByTagName('*');
	var x;
	for (x=0;x<children.length;x++)
	{
		if (children[x].name)
			children[x].name = children[x].name.replace(/\[num\]/g, '[' + num_elements + ']');
		if (children[x].id)
			children[x].id = children[x].id
				.replace(/input_/g, 'input_' + num_elements + '_')
				.replace(/info_/g, 'info_' + num_elements + '_')
				.replace(/div_/g, 'div_' + num_elements + '_');
	}

	//Create remove button
	var remove_button = document.createElement('input');
	remove_button.type = 'button';
	remove_button.value = '$remove_text';
	remove_button.tabIndex = tab_index;
	remove_button.onclick = removeInstanceEventHandler(div_id);
	new_div.appendChild(remove_button);
	
	//Add the new instance
	main_div.appendChild(new_div);
	attachAutocompleteToAllFields(new_div);
	
	if ($using_choosers)
	{
		hideOrShowFieldset(new_div);
	}
}

function removeInstanceEventHandler(this_div_id)
{
	return function()
	{
		removeInstance(this_div_id);
	};
}

function removeInstance(div_id) {
	var olddiv = document.getElementById(div_id);
	var parent = olddiv.parentNode;
	parent.removeChild(olddiv);
	if ($using_choosers)
		hideOrShowFieldset(parent);
}

var autocompletestrings = new Array();
var autocompletemappings = new Array();

//Activate autocomplete functionality for every field on the document
function attachAutocompleteToAllDocumentFields()
{
	var forms = document.getElementsByTagName("form");
	var x;
	for (x=0;x<forms.length;x++)
	{
		if (forms[x].name == "createbox")
		{
			attachAutocompleteToAllFields(forms[x]);
		}	
	}
}

//Activate autocomplete functionality for every field under the specified element
function attachAutocompleteToAllFields(base)
{
	var inputs = base.getElementsByTagName("input");
	var y;
	for (y=0;y<inputs.length;y++)
	{
		attachAutocompleteToField(inputs[y].id);
	}
}

//Activate autocomplete functionality for the specified field
function attachAutocompleteToField(input_id)
{
	//Check input id for the proper format, to ensure this is for SF
	if (input_id.substr(0,6) == 'input_')
	{
		//Extract the field ID number from the input field
		var field_num = parseInt(input_id.substring(input_id.lastIndexOf('_') + 1, input_id.length),10);
		//Add the autocomplete string, if a mapping exists.
		if (autocompletemappings[field_num])
		{
			var div_id = input_id.replace(/input_/g, 'div_');
			new Autocompleter.Local(input_id, div_id, autocompletestrings[autocompletemappings[field_num]], {});
		}
	}
}

Event.observe(window, 'load', attachAutocompleteToAllDocumentFields);

END;

    // send the autocomplete values to the browser, along with the mappings of which values should apply to which fields
    foreach ($autocomplete_values as $autocomplete_key => $autocomplete_string) {
      if ($autocomplete_key == "mappings") {
        foreach ($autocomplete_string as $field_number => $field_key) {
          $javascript_text .= "autocompletemappings[$field_number] = '" . str_replace("'", "\'", $field_key) . "';\n";
        }
      } else {
        $javascript_text .= "autocompletestrings['" . str_replace("'", "\'", $autocomplete_key) . "'] = $autocomplete_string;\n";
      }
    }
    return array($form_text, $javascript_text, $title, $data_text);
  }

  function formTemplateFieldHTML($field_name, $input_name, $part_of_multiple, $cur_value, $is_mandatory, $is_hidden, $is_restricted, $input_type, $size, $num_rows, $num_cols, $no_autocomplete, $autocomplete_category, $all_fields, $strict_parsing, &$out_autocomplete_values) {
   global $gTabIndex;

    // see if this field matches one of the fields defined for this template -
    // if it is, use all available information about that field; if it's not,
    // either include it in the form or not, depending on whether template
    // has 'strict' setting in the form definition
    $the_field = null;
    foreach ($all_fields as $cur_field) {
      if ($field_name == $cur_field->field_name) {
        $the_field = $cur_field;
        break;
      }
    }
    if ($the_field == null) {
      if ($strict_parsing)
        return null;
      $the_field = new SFTemplateField();
    }

    // if this is not part of a 'multiple' template, incrememt the
    // global tab index (used for correct tabbing, and for creating
    // unique div IDs.)
    if (! $part_of_multiple)
      $gTabIndex++;

    // populate field object with settings from the form definition file
    $the_field->is_mandatory = $is_mandatory;
    $the_field->is_hidden = $is_hidden;
    $the_field->is_restricted = $is_restricted;
    $the_field->input_type = $input_type;
    $the_field->size = $size;
    $the_field->num_rows = $num_rows;
    $the_field->num_cols = $num_cols;
    $the_field->no_autocomplete = $no_autocomplete;
    $the_field->autocomplete_category = $autocomplete_category;
    $the_field->input_name = $input_name;
    $the_field->part_of_multiple = $part_of_multiple;
    $text = SFFormPrinter::formFieldHTML($the_field, $cur_value, $out_autocomplete_values);
    return $text;
  }

  function formFieldHTML($template_field, $cur_value, &$out_autocomplete_values) {
    global $smwgContLang;
	//When a field is generated that requires autocomplete, the autocomplete string will be added to out_autocomplete_values. Then, at the end,
	//Javascript will be generated to activate autocomplete field at once.
	
    if ($template_field->is_hidden) {
      $text = SFFormPrinter::hiddenFieldHTML($template_field->input_name, $cur_value);
    } elseif ($template_field->autocomplete_category != null) {
      $size = $template_field->size;
      if ($size == null) $size = 35;
      $text = SFFormPrinter::textEntryWithAutocompleteHTML($size, $template_field->input_name, $template_field->part_of_multiple, $template_field->autocomplete_category, false, $cur_value, $out_autocomplete_values);
    } elseif ($template_field->input_type == 'text') {
      $size = $template_field->size;
      if ($size == null) $size = 35;
      $text = SFFormPrinter::textEntryHTML($size, $template_field->input_name, $cur_value);
    } elseif ($template_field->input_type == 'textarea') {
      $num_rows = $template_field->num_rows;
      if ($num_rows == null) $num_rows = 4;
      $num_cols = $template_field->num_cols;
      if ($num_cols == null) $num_cols = 40;
      $text = SFFormPrinter::textAreaHTML($num_rows, $num_cols, $template_field->input_name, $cur_value);
    } elseif ($template_field->input_type == 'date') {
      $text = SFFormPrinter::dateEntryHTML($template_field->input_name, $cur_value);
    } elseif ($template_field->input_type == 'checkbox') {
      $text = SFFormPrinter::checkboxHTML($template_field->input_name, $cur_value);
    } elseif ($template_field->input_type == 'radiobutton') {
      $include_none = ! $template_field->is_mandatory;
      $text = SFFormPrinter::radioButtonHTML($template_field->input_name, $cur_value, $include_none, $template_field->possible_values);
    } elseif ($template_field->attr_or_rel == "relation") {
      $size = $template_field->size;
      if ($size == null) $size = 35;
      if ($template_field->no_autocomplete) {
        $text = SFFormPrinter::textEntryHTML($size, $template_field->input_name, $cur_value);
      } else {
        $text = SFFormPrinter::textEntryWithAutocompleteHTML($size, $template_field->input_name, $template_field->part_of_multiple, $template_field->semantic_field, true, $cur_value, $out_autocomplete_values);
      }
    // escape out here if user doesn't have SMW installed
    } elseif ($smwgContLang == null) {
      if ($size == null) $size = 35;
      $text = SFFormPrinter::textEntryHTML($size, $template_field->input_name, $cur_value);
    } else { // input type not defined in form, and not a relation
      $attr_type = $template_field->attribute_type;
      $size = $template_field->size;
      if ($attr_type == $smwgContLang->smwDatatypeLabels['smw_enum']) {
        // prepend the "None" option if it's not a mandatory field
        $include_none = ! $template_field->is_mandatory;
        $text = SFFormPrinter::dropdownHTML($template_field->input_name, $template_field->possible_values, $include_none, $cur_value);
      } elseif ($attr_type == $smwgContLang->smwDatatypeLabels['smw_datetime']) {
        $text = SFFormPrinter::dateEntryHTML($template_field->input_name, $cur_value);
      } elseif ($attr_type == $smwgContLang->smwDatatypeLabels['smw_float'] || $attr_type == $smwgContLang->smwDatatypeLabels['smw_int']) {
        if ($size == null) $size = 10;
        $text = SFFormPrinter::textEntryHTML($size, $template_field->input_name, $cur_value);
      } elseif ($attr_type == $smwgContLang->smwDatatypeLabels['smw_url']) {
        if ($size == null) $size = 100;
        $text = SFFormPrinter::textEntryHTML($size, $template_field->input_name, $cur_value);
      } elseif ($attr_type == $smwgContLang->smwDatatypeLabels['smw_bool']) {
        $text = SFFormPrinter::checkboxHTML($template_field->input_name, $cur_value);
      } else { // String or anything else
        if ($size == null) $size = 35;
        $text = SFFormPrinter::textEntryHTML($size, $template_field->input_name, $cur_value);
      }
    }
    return $text;
  }

  function createAutocompleteValuesString($field_name, $is_relation) {
    global $sfgMaxAutocompleteValues;
    $fname = 'SFFormPrinter::createAutocompleteValuesString';
    $db = wfGetDB( DB_SLAVE );
    $sql_options = array();
    $sql_options['LIMIT'] = $sfgMaxAutocompleteValues;
    // the query depends on whether this field is a relation or a category
    if ($is_relation) {
      $conditions = "relation_title = '$field_name'";
      $sql_options['ORDER BY'] = 'object_title';
      $res = $db->select( $db->tableName('smw_relations'),
                          'DISTINCT object_title',
                          $conditions, $fname, $sql_options);
    } else {
      $conditions = "cl_from = page_id AND cl_to = '$field_name'";
      $sql_options['ORDER BY'] = 'page_title';
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
    } else {
      // return an empty array
      $array_str = "[]";
    }
    $db->freeResult($res);
    return $array_str;
  }

  function textEntryHTML($size, $input_name, $cur_value) {
    global $gTabIndex, $gDisabledText;
    $input_id = "input_$gTabIndex";
    $info_id = "info_$gTabIndex";

    $text =<<<END
		<input id="$input_id" tabindex="$gTabIndex" class="createboxInput" name="$input_name" type="text"
        value="$cur_value" size="$size" $gDisabledText/>
	<span id="$info_id" class="error_message"></span>

END;
    return $text;
  }

  function dropdownHTML($input_name, $possible_values, $include_none, $cur_value) {
    global $gTabIndex, $gDisabledText;

    $text = '     <select tabindex="' . $gTabIndex . '" name="' . $input_name . '" ' . $gDisabledText . '>';
    if ($include_none)
      $text .= "  <option value=\"\">" . wfMsg('sf_editdata_none') . "</option>\n";
    foreach ($possible_values as $possible_value) {
      $text .= "  <option value=\"$possible_value\"";
      if ($possible_value == $cur_value) {$text .= " selected=\"selected\""; }
      $text .= ">$possible_value</option>\n";
    }
    $text .=<<<END
	</select>
	<span id="$info_id" class="error_message"></span>

END;
    return $text;
  }

  function textEntryWithAutocompleteHTML($size, $input_name, $part_of_multiple, $semantic_field_name, $is_relation, $cur_value, &$out_autocomplete_values) {
    global $gTabIndex, $gDisabledText;
    $gTabIndex++;

    $input_id = "input_" . $gTabIndex;
    $info_id = "info_" . $gTabIndex;
    $div_name = "div_" . $gTabIndex;
    $text =<<<END
        <input tabindex="$gTabIndex" id="$input_id" name="$input_name" type="text"
	value="" size="$size" $gDisabledText/>
	<span id="$info_id" class="error_message"></span>
	<div class="page_name_auto_complete" id="$div_name" style="display:none"></div>
<script type="text/javascript">

END;
	$options_str_key = $semantic_field_name . ',' . $is_relation;
	if (!$out_autocomplete_values[$options_str_key])
		$out_autocomplete_values[$options_str_key] = SFFormPrinter::createAutocompleteValuesString(str_replace(' ', '_', $semantic_field_name), $is_relation);
	$out_autocomplete_values['mappings'][$gTabIndex] = $options_str_key;
    if ($cur_value) {
      $text .= "document.getElementById('$input_id').value = \"$cur_value\"\n";
    }
    $text .= "</script>\n";
    return $text;
  }

  function textAreaHTML($rows, $cols, $input_name, $cur_value) {
    global $gTabIndex, $gDisabledText;
    $input_id = "input_$gTabIndex";
    $info_id = "info_$gTabIndex";

    $text =<<<END
	<textarea tabindex="$gTabIndex" id="$input_id" name="$input_name" rows=$rows cols=$cols $gDisabledText>$cur_value</textarea>
	<span id="$info_id" class="error_message"></span>

END;
    return $text;
  }

  function monthDropdownHTML($cur_month, $input_name) {
    global $gTabIndex, $gDisabledText;

    $text = '	<select tabindex="' . $gTabIndex . '" id="input_' . $gTabIndex . '_1" name="' . $input_name . "[month]\" $gDisabledText>\n";
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

  function dateEntryHTML($input_name, $date) {
    global $gTabIndex, $gDisabledText;

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
      $year = $cur_date['year'];
      $month = $cur_date['month'];
      $day = null; // no need for day
    }
    $text = SFFormPrinter::monthDropdownHTML($month, $input_name);
    $text .= '  <input tabindex="' . $gTabIndex . '" id="input_' . $gTabIndex . '_2" name="' . $input_name . '[day]" type="text" value="' . $day . '" size="2"/ ' . $gDisabledText . '>' . "\n";
    $text .= '  <input tabindex="' . $gTabIndex . '" id="input_' . $gTabIndex . '_3" name="' . $input_name . '[year]" type="text" value="' . $year . '" size="4"/ ' . $gDisabledText . '>' . "\n";
    $info_id = "info_$gTabIndex";
    $text .= "	<span id=\"$info_id\" class=\"error_message\"></span>";
    return $text;
  }

  function radioButtonHTML($input_name, $cur_value, $include_none, $options_array) {
    global $gTabIndex, $gDisabledText;

    $check_set = false;
    if ($include_none) {
      $text .= '	<input type="radio" tabindex="' . $gTabIndex . '" name="' . $input_name . '" value=""';
      if (! $cur_value) {
        $text .= " checked";
        $check_set = true;
      }
      $text .= " $gDisabledText/> " . wfMsg('sf_editdata_none') . "\n";
    }
    foreach ($options_array as $i => $option) {
      $text .= '	<input type="radio" tabindex="' . $gTabIndex . '" name="' . $input_name . '" value="' . $option . '"';
      if ($cur_value == $option || (! $cur_value && ! $check_set)) {
        $text .= " checked";
        $check_set = true;
      }
      $text .= " $gDisabledText/> $option\n";
    }
    return $text;
  }

  function checkboxHTML($input_name, $cur_value) {
    global $gTabIndex, $gDisabledText;
    $info_id = "info_$gTabIndex";

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
    $text =<<<END
	<input name="{$input_name}[is_checkbox]" type="hidden" value="true" />
	<input id="input_$gTabIndex" name="{$input_name}[value]" type="checkbox" tabindex="$gTabIndex " $checked_str $gDisabledText/>
	<span id="$info_id" class="error_message"></span>

END;
    return $text;
  }

  function hiddenFieldHTML($input_name, $cur_value) {
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
      $token = EDIT_TOKEN_SUFFIX;
    if ($wgRequest->getCheck('wpSave'))
      $page_action = "wpSave";
    elseif ($wgRequest->getVal('wpPreview'))
      $page_action = "wpPreview";
    else # if $wgRequest->getVal('wpDiff')
      $page_action = "wpDiff";

    global $wgScript;
    $encoded_page_name = urlencode($page_name);
    $text = <<<END
        <form id="editform" name="editform" method="post" action="$wgScript?title=$encoded_page_name&amp;action=submit">
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
    global $wgVersion, $wgUser, $wgRightsText, $wgParser;
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
    
	//Fix so extension works with MediaWiki 1.7
	if (substr_compare($wgVersion, '1.7', 0, 3) == 0)
	{
		$edithelpurl = $sk->makeInternalOrExternalUrl( wfMsgForContent( 'edithelppage' ));
	}
	else
	{
		$edithelpurl = Skin::makeInternalOrExternalUrl( wfMsgForContent( 'edithelppage' ));
	}
    
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
    if ( $preload === '' ) {
      return '';
    } else {
      $preloadTitle = Title::newFromText( $preload );
      if ( isset( $preloadTitle ) && $preloadTitle->userCanRead() ) {
        $rev = Revision::newFromTitle($preloadTitle);
        if ( is_object( $rev ) ) {
          $text = $rev->getText();
          // Remove <noinclude> sections and <includeonly> tags from text
          $text = StringUtils::delimiterReplace('<noinclude>', '</noinclude>', '', $text);
          $text = strtr($text, array('<includeonly>' => '', '</includeonly>' => ''));
          return $text;
        }
      }
      return '';
    }
  } 

}

?>
