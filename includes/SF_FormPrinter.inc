<?php
/**
 * Handles the creation and running of a user-created form.
 *
 * @author Yaron Koren
 * @author Nils Oppermann
 * @author Jeffrey Stuckman
 * @author Matt Williamson
 */

class SFFormPrinter {

  var $mSemanticTypeHooks;
  var $mInputTypeHooks;
  var $standardInputsIncluded;
  var $mPageTitle;

  function SFFormPrinter() {
    global $smwgContLang;

    // initialize the set of hooks for the entry-field functions to call for
    // fields of both a specific semantic "type" and a defined "input type"
    // in the form definition
    $this->mSemanticTypeHooks = array();
    if ($smwgContLang != null) {
      // set type strings based on the SMW version
      $smw_version = SMW_VERSION;
      if ($smw_version{0} == '0') {
        $string_type = $smwgContLang->getDatatypeLabel('smw_string');
        $text_type = 'text'; // placeholder - SMW 1.0+ only
        $url_type = $smwgContLang->getDatatypeLabel('smw_url');
        $float_type = $smwgContLang->getDatatypeLabel('smw_float');
        $int_type = $smwgContLang->getDatatypeLabel('smw_int');
        $bool_type = $smwgContLang->getDatatypeLabel('smw_bool');
        $date_type = $smwgContLang->getDatatypeLabel('smw_datetime');
        $enum_type = $smwgContLang->getDatatypeLabel('smw_enum');
        $page_type = 'relation';
      } else {
        $datatypeLabels =  $smwgContLang->getDatatypeLabels();
        $string_type = $datatypeLabels['_str'];
        $text_type = $datatypeLabels['_txt'];
        $url_type = $datatypeLabels['_uri'];
        // backwards compatibility with SMW 1.0 alpha 1 - still used, for
        // now, by the Halo extension
        if (array_key_exists('_flt', $datatypeLabels)) {
          $float_type = $datatypeLabels['_flt'];
          $int_type = $datatypeLabels['_int'];
        } else {
          $float_type = $datatypeLabels['_num'];
          $int_type = $datatypeLabels['_num'];
        }
        // just a placeholder until SMW 1.0 supports Boolean types -
        // should be $datatypeLabels['_boo'];
        $bool_type = 'boolean';
        $date_type = $datatypeLabels['_dat'];
        $enum_type = 'enumeration'; // not a real type
        $page_type = $datatypeLabels['_wpg'];
      }
      $this->setSemanticTypeHook($string_type, false, array('SFFormPrinter', 'textEntryHTML'), array('size' => 35));
      $this->setSemanticTypeHook($string_type, true, array('SFFormPrinter', 'textEntryHTML'), array('size' => 35));
      $this->setSemanticTypeHook($text_type, false, array('SFFormPrinter', 'textAreaHTML'), array('rows' => 5, 'cols' => 30));
      $this->setSemanticTypeHook($url_type, false, array('SFFormPrinter', 'textEntryHTML'), array('size' => 100));
      $this->setSemanticTypeHook($float_type, false, array('SFFormPrinter', 'textEntryHTML'), array('size' => 10));
      $this->setSemanticTypeHook($int_type, false, array('SFFormPrinter', 'textEntryHTML'), array('size' => 10));
      $this->setSemanticTypeHook($bool_type, false, array('SFFormPrinter', 'checkboxHTML'), array());
      $this->setSemanticTypeHook($date_type, false, array('SFFormPrinter', 'dateEntryHTML'), array());
      $this->setSemanticTypeHook($enum_type, false, array('SFFormPrinter', 'dropdownHTML'), array());
      $this->setSemanticTypeHook($enum_type, true, array('SFFormPrinter', 'checkboxesHTML'), array());
      $this->setSemanticTypeHook($page_type, false, array('SFFormPrinter', 'textEntryWithAutocompleteHTML'), array('size' => 35));
    }
    $this->mInputTypeHooks = array();
    $this->setInputTypeHook('text', array('SFFormPrinter', 'textEntryHTML'), array('size' => 35));
    $this->setInputTypeHook('textarea', array('SFFormPrinter', 'textAreaHTML'), array('rows' => 5, 'cols' => 30));
    $this->setInputTypeHook('date', array('SFFormPrinter', 'dateEntryHTML'), array());
    $this->setInputTypeHook('datetime', array('SFFormPrinter', 'dateTimeEntryHTML'), array('include_timezone' => false));
    $this->setInputTypeHook('datetime with timezone', array('SFFormPrinter', 'dateTimeEntryHTML'), array('include_timezone' => true));
    $this->setInputTypeHook('checkbox', array('SFFormPrinter', 'checkboxHTML'), array());
    $this->setInputTypeHook('radiobutton', array('SFFormPrinter', 'radioButtonHTML'), array());
    $this->setInputTypeHook('checkboxes', array('SFFormPrinter', 'checkboxesHTML'), array());
    $this->setInputTypeHook('listbox', array('SFFormPrinter', 'listboxHTML'), array());

    // initialize other variables
    $this->standardInputsIncluded = false;
  }

  function setSemanticTypeHook($type, $is_list, $function_name, $default_args) {
    $this->mSemanticTypeHooks[$type][$is_list] = array($function_name, $default_args);
  }

  function setInputTypeHook($input_type, $function_name, $default_args) {
    $this->mInputTypeHooks[$input_type] = array($function_name, $default_args);
  }

  function formHTML($form_def, $form_submitted, $source_is_page, $existing_page_content = null, $page_title = null) {
    global $wgRequest, $wgUser, $wgParser;
    global $sfgTabIndex; // used to represent the current tab index in the form
    global $sfgFieldNum; // used for setting various HTML IDs

    // initialize some variables
    $sfgTabIndex = 1;
    $sfgFieldNum = 1;
    $source_page_matches_this_form = false;
    // disable all form elements if user doesn't have edit permission -
    // two different checks are needed, because editing permissions can be
    // set in different ways
    $this->mPageTitle = Title::newFromText($page_title);
    if ($wgUser->isAllowed('edit') && $this->mPageTitle->userCanEdit()) {
      $form_is_disabled = false;
      $form_text = "";
      // show "Your IP address will be recorded" warning if user is
      // anonymous - wikitext for bolding has to be replaced with HTML, until
      // FormPrinter can parse wikitext
      if ($wgUser->isAnon()) {
        $anon_edit_warning = preg_replace("/'''(.*)'''/", "<strong>$1</strong>", wfMsg('anoneditwarning'));
        $form_text .= "<p>$anon_edit_warning</p>\n";
      }
    } else {
      $form_is_disabled = true;
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
    $fields_javascript_text = "";

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
    // initialize list of choosers (dropdowns with available templates)
    $choosers = array();
    for ($section_num = 0; $section_num < count($form_def_sections); $section_num++) {
      $tif = new SFTemplateInForm();
      $start_position = 0;
      $template_text = "";
      // the append is there to ensure that the original array doesn't get
      // modified; is it necessary?
      $section = " " . $form_def_sections[$section_num];

$while_instance = 0;
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
          // also replace periods with underlines, since that's what
          // POST does to strings anyway
          $query_template_name = str_replace('.', '_', $query_template_name);
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
          // special template string for preg_match calls - includes a
          // regular expression, to allow matching of either spaces or
          // underlines in name
          $search_template_str = str_replace('/', '\/', $tif->template_name);
          $search_template_str = preg_replace('/[_| ]/', '[_| ]', $search_template_str);
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
              if (preg_match_all('/\{\{' . $search_template_str . '\s*(?:(?:([\||:].*?)\}\})|()\}\})/mis', $existing_page_content, $matches)) {
                $instance_num++;
              } else {
                $all_instances_printed = true;
              }
            }
            // get the first instance of this template on the page being edited,
            // even if there are more
            if (preg_match('/\{\{' . $search_template_str . '\s*(?:(?:([\||:].*?)\}\})|()\}\})/mis', $existing_page_content, $matches)) {
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
              // if this is not a multiple-instance template, and we've found
              // a match in the source page, there's a good chance that this
              // page was created with this form - note that, so we don't
              // send the user a warning
              // (multiple-instance templates have a greater chance of
              // getting repeated from one form to the next)
              if (! $allow_multiple) {
                $source_page_matches_this_form = true;
              }
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
          $field_args = array();
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
                } elseif ($sub_components[0] == 'default') {
                  $default_value = $sub_components[1];
                } elseif ($sub_components[0] == 'preload') {
                  $preload_page = $sub_components[1];
                } else {
                  $field_args[$sub_components[0]] = $sub_components[1];
                }
                // special handling for this field, because it's overloaded -
                // if it's empty, no automatic autocompleting should happen
                if ($sub_components[0] == 'autocomplete on' && $sub_components[1] == null) {
                    $field_args['no_autocomplete'] = true;
                  }
              }
            }
          }
          $field_args['part_of_multiple'] = $allow_multiple;
          // get the value from the request, if it's there
          if (isset ($template_instance_query_values[$field_name]))
            $cur_value = $template_instance_query_values[$field_name];
          else
            $cur_value = '';
          if ($cur_value && ! is_array($cur_value)) {
            $cur_value = Sanitizer::safeEncodeAttribute($cur_value);
          }
          if ($cur_value == null) {
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
                $new_text = $this->hiddenFieldHTML('free_text', '<free_text>');
              } else {
		if (! array_key_exists('rows', $field_args))
                  $field_args['rows'] = 5;
		if (! array_key_exists('cols', $field_args))
                  $field_args['cols'] = 30;
                $sfgTabIndex++;
                $sfgFieldNum++;
                list($new_text, $new_javascript_text) = $this->textAreaHTML('<free_text>', 'free_text', false, $form_is_disabled, $field_args);
              }
              $free_text_was_included = true;
              // add a similar placeholder to the data text
              $data_text .= "<free_text>\n";
            }
            $section = substr_replace($section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc);
          } else { // this field is part of a template
            if (is_array($cur_value)) {
              // first, check if it's a list
              if (array_key_exists('is_list', $cur_value) &&
                  $cur_value['is_list'] == 1) {
                $cur_value_in_template = "";
                if (array_key_exists('delimiter', $field_args)) {
                  $delimiter = $field_args['delimiter'];
                } else {
                  $delimiter = ",";
                }
                foreach ($cur_value as $key => $val) {
                  if ($key !== "is_list") {
		    if ($cur_value_in_template != "") {
                      $cur_value_in_template .= $delimiter . " ";
                    }
                    $cur_value_in_template .= $val;
                  }
                }
              } else {
                // otherwise:
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
                  } elseif (count($words_for_false) == 0) {
                    $no = "0"; // some safe value if no words are found
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
                  } elseif (count($words_for_true) == 0) {
                    $yes = "1"; // some safe value if no words are found
                  } else {
                    $yes = $words_for_true[0];
                  }
                  $cur_value_in_template = $yes;
                // if it's 3 or greater, assume it's a date or datetime
                } elseif (count($cur_value) >= 3) {
                  $month = $cur_value['month'];
                  $day = $cur_value['day'];
                  $year = $cur_value['year'];
                  if (isset($cur_value['hour'])) $hour = $cur_value['hour'];
                  if (isset($cur_value['minute'])) $minute = $cur_value['minute'];
                  if (isset($cur_value['second'])) $second = $cur_value['second'];
                  if (isset($cur_value['ampm24h'])) $ampm24h = $cur_value['ampm24h'];
                  if (isset($cur_value['timezone'])) $timezone = $cur_value['timezone'];
                  if ($month != '' && $day != '' && $year != '') {
                    // special handling for American dates - otherwise, just
                    // the standard year/month/day (where month is a number)
                    global $wgAmericanDates;
                    if ($wgAmericanDates) {
                      $cur_value_in_template = "$month $day, $year";
                    } else {
                      $cur_value_in_template = "$year/$month/$day";
                    }
                    // include whatever time information we have
                    if(isset($hour)) $cur_value_in_template .= " " . str_pad(intval(substr($hour,0,2)),2,'0',STR_PAD_LEFT) . ":" . str_pad(intval(substr($minute,0,2)),2,'0',STR_PAD_LEFT);
                    if(isset($second)) $cur_value_in_template .= ":" . str_pad(intval(substr($second,0,2)),2,'0',STR_PAD_LEFT);
                    if(isset($ampm24h)) $cur_value_in_template .= " $ampm24h";
                    if(isset($timezone)) $cur_value_in_template .= " $timezone";
                  } else {
                    $cur_value_in_template = "";
                  }
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
            // disable this field if either the whole form is disabled, or
            // it's a restricted field and user doesn't have sysop privileges
            $is_disabled = ($form_is_disabled ||
              ($is_restricted && (! $wgUser || ! $wgUser->isAllowed('delete'))));
            list($new_text, $new_javascript_text) = $this->formTemplateFieldHTML($field_name, $input_name,
              $cur_value, $is_mandatory, $is_hidden, $is_disabled, $input_type, $field_args,
              $all_fields, $strict_parsing);
            $fields_javascript_text .= $new_javascript_text;

            // if this was field was disabled due to being 'restricted',
            // add a hidden field holding the value of this field, because
            // disabled inputs for some reason don't submit their value
            if ($is_restricted && ! $wgUser->isAllowed('delete')) {
              if ($field_name == 'free text') {
                $new_text .= $this->hiddenFieldHTML('free_text', '<free_text>');
              } else {
                $new_text .= $this->hiddenFieldHTML($input_name, $cur_value);
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
		$input_id = "input_" . $sfgFieldNum;
		$info_id = "info_" . $sfgFieldNum;
                if ($input_type == 'date' || $input_type == 'datetime' || $input_type == 'datetime with timezone') {
                  $js_validation_calls[] = "validate_mandatory_field ('$input_id" . "_1', '$info_id')";
                  $js_validation_calls[] = "validate_mandatory_field ('$input_id" . "_2', '$info_id')";
                  $js_validation_calls[] = "validate_mandatory_field ('$input_id" . "_3', '$info_id')";
		  if ($input_type == 'datetime' || $input_type == 'datetime with timezone') {
                    // TODO - validate the time fields
                    if ($input_type == 'datetime with timezone') {
                       // TODO - validate the timezone
                    }
                  }
                } else {
                  // temporary fix - simply turn off validation for fields
                  // that are part of a multiple-instance template
                  if ($allow_multiple) {
                    $js_validation_calls[] = "validate_multiple_mandatory_fields($sfgFieldNum)";
                  } else {
                    $js_validation_calls[] = "validate_mandatory_field('$input_id', '$info_id')";
                  }
                }
              }
            } else {
              $start_position = $brackets_end_loc;
            }
          }
        } elseif ($tag_title == 'standard input') {
          // set a flag so that the standard 'form bottom' won't get displayed
          $this->standardInputsIncluded = true;
          // handle all the possible values
          $input_name = $tag_components[1];
          $input_label = null;
          // cycle through the other components
          for ($i = 2; $i < count($tag_components); $i++) {
            $component = $tag_components[$i];
            $sub_components = explode('=', $component);
            if (count($sub_components) == 2) {
              if ($sub_components[0] == 'label') {
                $input_label = $sub_components[1];
              }
            }
          }
          if ($input_name == 'summary') {
            $new_text = $this->summaryInputHTML($form_is_disabled, $input_label);
          } elseif ($input_name == 'minor edit') {
            $new_text = $this->minorEditInputHTML($form_is_disabled, $input_label);
          } elseif ($input_name == 'watch') {
            $new_text = $this->watchInputHTML($form_is_disabled, $input_label);
          } elseif ($input_name == 'save') {
            $new_text = $this->saveButtonHTML($form_is_disabled, $input_label);
          } elseif ($input_name == 'preview') {
            $new_text = $this->showPreviewButtonHTML($form_is_disabled, $input_label);
          } elseif ($input_name == 'changes') {
            $new_text = $this->showChangesButtonHTML($form_is_disabled, $input_label);
          } elseif ($input_name == 'cancel') {
            $new_text = $this->cancelLinkHTML($form_is_disabled, $input_label);
          }
          $section = substr_replace($section, $new_text, $brackets_loc, $brackets_end_loc + 3 - $brackets_loc);
        } else { // tag is not one of the three allowed values
          // ignore tag
          $start_position = $brackets_end_loc;
        } // end if
      } // end while

      if (! $all_instances_printed && ($template_text != '')) {
	// add another newline before the final bracket, if this template
	// call is already more than one line
        if (strpos($template_text, "\n")) {
          $template_text .= "\n";
        }
        $template_text .= "}}\n";
        $data_text .= $template_text;
      }
      if ($allow_multiple) {
        if (! $all_instances_printed) {
          $section = str_replace('[num]', "[{$instance_num}a]", $section);
          $remove_text = wfMsg('sf_editdata_remove');
          $form_text .=<<<END
	<div id="wrapper_$sfgFieldNum" class="multiple_template">
        $section
        <input type="button" onclick="removeInstance('wrapper_$sfgFieldNum');" value="$remove_text" tabindex="$sfgTabIndex" class="remove" />
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
	<p><input type="button" onclick="addInstance('starter_$query_template_name', 'main_$query_template_name', '$sfgFieldNum');" value="$add_another" tabindex="$sfgTabIndex" class="addAnother" /></p>

END;
           // if a chooser is being used for this template, add the template
           // to the chooser data array
          if ($chooser_name !== false)
            $choosers[$chooser_name][]= array($query_template_name, $sfgFieldNum, $chooser_caption);
        }
      } else {
        $form_text .= $section;
      }

    } // end for

    // if it wasn't included in the form definition, add the
    // 'free text' input as a hidden field at the bottom
    if (! $free_text_was_included) {
      $form_text .= $this->hiddenFieldHTML('free_text', '<free_text>');
    }
    // get free text, and add to page data, as well as retroactively
    // inserting it into the form
    if ($source_is_page) {
      // if the page is the source, free_text will just be whatever in the
      // page hasn't already been inserted into the form
      $free_text = trim($existing_page_content);
    // or get it from a form submission
    } elseif ($wgRequest->getCheck('free_text')) {
      $free_text = $wgRequest->getVal('free_text');
      if (! $free_text_was_included) {
        $data_text .= "<free_text>";
      }
    // or get it from the form definition
    } elseif ($preload_page != null) {
      $free_text = $this->getPreloadedText($preload_page);
    } else {
      $free_text = null;
    }
    $free_text = Sanitizer::safeEncodeAttribute($free_text);
    // now that we have it, substitute free text into the form and page
    $form_text = str_replace('<free_text>', $free_text, $form_text);
    $data_text = str_replace('<free_text>', $free_text, $data_text);

    // add a warning in, if the source was a page and that page appears
    // to have been edited with a different form
    if ($source_is_page && ($existing_page_content != '') && ! $source_page_matches_this_form) {
      $form_text = '	<div class="warning_message">' . wfMsg('sf_editdata_formwarning', $this->mPageTitle->getFullURL()) . "</div>\n" . $form_text;
    }

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

        $chooser_text .= "<p>$choosername:<select id='chooserselect$chooser_count' size='1' onchange='updatechooserbutton(this,\"chooserbutton$chooser_count\")'>\n";
        $chooser_text .= "<option value='invalid'>" . wfMsg('sf_createform_choosefield') . "</option>\n";
        foreach ($chooser as $chooser_item) {
          $chooser_value = str_replace('"','\\"',$chooser_item[0]);
          $tabindex = $chooser_item[1];
          $chooser_caption = $chooser_item[2];
          if ($chooser_caption === false)
            $chooser_caption = str_replace('_', ' ', $chooser_value);
            $chooser_text .= "<option value=\"$tabindex ,$chooser_value\">$chooser_caption</option>\n";
          }
        $chooser_text .= "</select>\n";
      }
      $chooser_text .= "<input type='button' onclick=\"addInstanceFromChooser('chooserselect$chooser_count');\" value='" . wfMsg('sf_editdata_addanother') . "' disabled='true' id='chooserbutton$chooser_count'></p>";
    }
	
    $form_text = str_replace('<choosers>', $chooser_text, $form_text);

    // add form bottom, if no custom "standard inputs" have been defined
    if (! $this->standardInputsIncluded)
      $form_text .= $this->formBottom($form_is_disabled);
    $form_text .= "\n	</form>\n";

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

// validate a mandatory field that exists across multiple instances of
// a template - we have to find each one, matching on the pattern of its
// ID, and validate it
function validate_multiple_mandatory_fields(field_num) {
	var num_errors = 0;
	elems = document.getElementsByTagName("*");
	var field_pattern = new RegExp('input_(.*)_' + field_num);
	for (var i = 0; i < elems.length; i++) {
		id = elems[i].id;
		if (matches = field_pattern.exec(id)) {
			instance_num = matches[1];
			var input_name = "input_" + instance_num + "_" + field_num;
			var info_name = "info_" + instance_num + "_" + field_num;
			if (! validate_mandatory_field(input_name, info_name)) {
				num_errors += 1;
			}
		}
	}
	return (num_errors == 0);
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
	remove_button.value = "$remove_text";
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
    $javascript_text .= $fields_javascript_text;
    return array($form_text, $javascript_text, $data_text);
  }

  function formTemplateFieldHTML($field_name, $input_name, $cur_value, $is_mandatory, $is_hidden, $is_disabled, $input_type, $field_args, $all_fields, $strict_parsing) {
   global $sfgTabIndex, $sfgFieldNum;

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
    // global tab index (used for correct tabbing)
    if (! $field_args['part_of_multiple'])
      $sfgTabIndex++;
    // increment the global field number regardless
    $sfgFieldNum++;

    // create an SFFormTemplateField object, containing this field as well
    // as settings from the form definition file
    $form_field = new SFFormTemplateField();
    $form_field->template_field = $the_field;
    $form_field->is_mandatory = $is_mandatory;
    $form_field->is_hidden = $is_hidden;
    $form_field->input_type = $input_type;
    $form_field->field_args = $field_args;
    $form_field->input_name = $input_name;
    $form_field->is_disabled = $is_disabled;
    list($text, $javascript_text) = $this->formFieldHTML($form_field, $cur_value);
    return array($text, $javascript_text);
  }

  function formFieldHTML($form_field, $cur_value) {
    global $smwgContLang;

    // also get the actual field, with all the semantic information (type is
    // SFTemplateField, instead of SFFormTemplateField)
    $template_field = $form_field->template_field;

    if ($form_field->is_hidden) {
      $text = $this->hiddenFieldHTML($form_field->input_name, $cur_value);
    } elseif ($form_field->input_type != '' && $this->mInputTypeHooks[$form_field->input_type] != null) {
      $funcArgs = array();
      $funcArgs[] = $cur_value;
      $funcArgs[] = $form_field->input_name;
      $funcArgs[] = $form_field->is_mandatory;
      $funcArgs[] = $form_field->is_disabled;
      // last argument to function should be a hash, merging the default
      // values for this input type with all other properties set in
      // the form definition, plus some semantic-related arguments
      $hook_values = $this->mInputTypeHooks[$form_field->input_type];
      $default_args = $hook_values[1];
      $other_args = $form_field->field_args;
      $other_args['possible_values'] = $template_field->possible_values;
      if ($template_field->is_relation) {
        $other_args['is_relation'] = true;
        $other_args['semantic_field_name'] = $template_field->semantic_field;
      }
        // merge default values and values from form - put the default values
        // first, so that if there's a conflict they'll be overridden
      $funcArgs[] = array_merge($default_args, $other_args);
      list($text, $javascript_text) = call_user_func_array($hook_values[0], $funcArgs);
    // escape out here if user doesn't have SMW installed
    } elseif ($smwgContLang == null) {
      $size = $template_field->field_args['size'];
      if ($size == null) $size = 35;
      $field_args = array('size' => $size);
      list($text, $javascript_text) = $this->textEntryHTML($cur_value, $form_field->input_name, $form_field->is_mandatory, $form_field->is_disabled, $field_args);
    } else { // input type not defined in form, and not a relation
      $field_type = $template_field->field_type;
      $is_list = $template_field->is_list;
      if ($field_type != '' &&
          array_key_exists($field_type, $this->mSemanticTypeHooks) &&
          isset($this->mSemanticTypeHooks[$field_type][$is_list])) {
        $hook_values = $this->mSemanticTypeHooks[$field_type][$is_list];
        $funcArgs = array();
        $funcArgs[] = $cur_value;
        $funcArgs[] = $form_field->input_name;
        $funcArgs[] = $form_field->is_mandatory;
        $funcArgs[] = $form_field->is_disabled;
        // merge default values and values from form - put the default values
        // first, so that if there's a conflict they'll be overridden
        $other_args = array_merge($hook_values[1], $form_field->field_args);
        $other_args['possible_values'] = $template_field->possible_values;
        if ($template_field->is_relation) {
          $other_args['is_relation'] = true;
          $other_args['semantic_field_name'] = $template_field->semantic_field;
        }
        $funcArgs[] = $other_args;
        list($text, $javascript_text) = call_user_func_array($hook_values[0], $funcArgs);
      } else { // anything else
        $other_args = $form_field->field_args;
        if (!array_key_exists('size', $other_args))
          $other_args['size'] = 35;
        list($text, $javascript_text) = $this->textEntryHTML($cur_value, $form_field->input_name, $form_field->is_mandatory, $form_field->is_disabled, $other_args);
      }
    }
    return array($text, $javascript_text);
  }

  function getAllPagesForRelation_0_7($relation_name) {
    global $sfgMaxAutocompleteValues;

    $fname = "SFFormPrinter::getAllPagesForRelation_0_7";
    $pages = array();
    $db = wfGetDB( DB_SLAVE );
    $sql_options = array();
    $sql_options['LIMIT'] = $sfgMaxAutocompleteValues;
    $conditions = "relation_title = '$relation_name'";
    $sql_options['ORDER BY'] = 'object_title';
    $res = $db->select( $db->tableName('smw_relations'),
                        'DISTINCT object_title',
                        $conditions, $fname, $sql_options);
    while ($row = $db->fetchRow($res)) {
      $cur_value = str_replace("'", "\'", $row[0]);
      $pages[] = str_replace('_', ' ', $cur_value);
    }
    $db->freeResult($res);
    return $pages;
  }

  /*
   * Get all the pages that belong to a category and all its subcategories,
   * down a certain number of levels - heavily based on SMW's
   * SMWInlineQuery::includeSubcategories()
   */
  function getAllPagesForCategory($top_category, $num_levels) {
    if (0 == $num_levels) return $top_category;
    global $sfgMaxAutocompleteValues;

    $db = wfGetDB( DB_SLAVE );
    $fname = "SFFormPrinter::getAllPagesForCategory";
    $categories = array($top_category);
    $checkcategories = array($top_category);
    $pages = array();
    for ($level = $num_levels; $level > 0; $level--) {
      $newcategories = array();
      foreach ($checkcategories as $category) {
        $res = $db->select( // make the query
          array('categorylinks', 'page'),
          array('page_title', 'page_namespace'),
          array('cl_from = page_id',
          'cl_to = '. $db->addQuotes($category)),
          $fname);
          if ($res) {
            while ($res && $row = $db->fetchRow($res)) {
            if (array_key_exists('page_title', $row)) {
              $page_namespace = $row['page_namespace'];
              if ($page_namespace == NS_CATEGORY) { 
                $new_category = $row[ 'page_title' ];
                if (!in_array($new_category, $categories)) {
                  $newcategories[] = $new_category;
                }
              } else {
                $cur_value = str_replace("'", "\'", $row['page_title']);
                $pages[] = str_replace("_", " ", $cur_value);
                // return if we've reached the maximum number of allowed values
                if (count($pages) > $sfgMaxAutocompleteValues)
                  return $pages;
              }
            }
          }
          $db->freeResult( $res );
        }
      }
      if (count($newcategories) == 0) {
        return $pages;
      } else {
        $categories = array_merge($categories, $newcategories);
      }
      $checkcategories = array_diff($newcategories, array());
    }
    return $pages;
  }

  function createAutocompleteValuesString($field_name, $autocomplete_field_type) {
    $names_array = array();
    // the query depends on whether this field is a relation, category or
    // namespace
    if ($autocomplete_field_type == 'relation') {
      $smw_version = SMW_VERSION;
      if ($smw_version{0} == '0') {
        $names_array = $this->getAllPagesForRelation_0_7($field_name);
      } else {
        // Unfortunately, there's no 1.0 version of this function yet,
        // because it would require a function like
        // SMWStore::getAllPropertyObjects(), which doesn't exist yet.
        // For now, just use the 0.7 version - this forces the data store
        // to be a database, but hopefully that's not a problem for any
        // users yet
        $names_array = $this->getAllPagesForRelation_0_7($field_name);
      }
    } elseif ($autocomplete_field_type == 'category') {
      $names_array = $this->getAllPagesForCategory($field_name, 10);
    } else { // i.e., $autocomplete_field_type == 'namespace'
      // cycle through all the namespace names for this language, and if
      // one matches the namespace specified in the form, add the names
      // of all the pages in that namespace to $names_array
      global $wgContLang;
      $namespaces = $wgContLang->getNamespaces();
      $db = wfGetDB( DB_SLAVE );
      $fname = "SFFormPrinter::createAutocompleteValuesString";
      foreach ($namespaces as $ns_code => $ns_name) {
        if ($ns_name == $field_name) {
          $conditions = "page_namespace = $ns_code";
          $sql_options['ORDER BY'] = 'page_title';
          $res = $db->select( $db->tableNames('page'),
                              'page_title',
                              $conditions, $fname, $sql_options);
          while ($row = $db->fetchRow($res)) {
            $cur_value = str_replace("'", "\'", $row[0]);
            $names_array[] = str_replace('_', ' ', $cur_value);
          }
          $db->freeResult($res);
        }
      }
    }
    $array_str = "['" . implode("', '", $names_array) . "']";
    return $array_str;
  }

  function textEntryHTML($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args) {
    // if it's an autocomplete, call the with-autocomplete function instead
    if ((array_key_exists('autocomplete on', $other_args) && $other_args['autocomplete on'] != "") ||
        array_key_exists('autocomplete on namespace', $other_args)) {
      return SFFormPrinter::textEntryWithAutocompleteHTML($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args);
    }

    global $sfgTabIndex, $sfgFieldNum;
    $className = ($is_mandatory) ? "mandatoryField" : "createboxInput";
    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";
    $disabled_text = ($is_disabled) ? "disabled" : "";
    $size = $other_args['size'];

    $text =<<<END
		<input id="$input_id" tabindex="$sfgTabIndex" class="$className" name="$input_name" type="text"
        value="$cur_value" size="$size" $disabled_text/>
	<span id="$info_id" class="error_message"></span>

END;
    return array($text, null);
  }

  function dropdownHTML($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args) {
    global $sfgTabIndex, $sfgFieldNum;

    $className = ($is_mandatory) ? "mandatoryField" : "createboxInput";
    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";
    $disabled_text = ($is_disabled) ? "disabled" : "";
    $text =<<<END
	<select id="$input_id" tabindex="$sfgTabIndex" name="$input_name" class="$className" $disabled_text>

END;
    // add a blank value at the beginning if it's a non-mandatory field
    if (! $is_mandatory)
      $text .= "  <option value=\"\"></option>\n";
    if (($possible_values = $other_args['possible_values']) == null)
      $possible_values = array(); 
    foreach ($possible_values as $possible_value) {
      $text .= "  <option value=\"$possible_value\"";
      if ($possible_value == $cur_value) {$text .= " selected=\"selected\""; }
      $text .= ">$possible_value</option>\n";
    }
    $text .=<<<END
	</select>
	<span id="$info_id" class="error_message"></span>

END;
    return array($text, null);
  }

  /*
   * getValuesArray() - helper function to get an array of values out of
   * what may be either an array or a delimited string
   */
  function getValuesArray($value, $delimiter) {
    if (is_array($value)) {
      return $value;
    } else {
      // remove extra spaces
      return array_map('trim', explode($delimiter, $value));
    }
  }

  function listboxHTML($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args) {
    global $sfgTabIndex, $sfgFieldNum;

    $className = ($is_mandatory) ? "mandatoryField" : "createboxInput";
    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";
    $hidden_input_name = $input_name . "[is_list]";
    $input_name .= "[]"; // needed so that this input will send an array
    $disabled_text = ($is_disabled) ? "disabled" : "";
    // get list delimiter - default is comma
    if (array_key_exists('delimiter', $other_args)) {
      $delimiter = $other_args['delimiter'];
    } else {
       $delimiter = ",";
    }
    $cur_values = $this->getValuesArray($cur_value, $delimiter);

    $text =<<<END
	<select id="$input_id" tabindex="$sfgTabIndex" name="$input_name" class="$className" multiple $disabled_text>

END;
    if (($possible_values = $other_args['possible_values']) == null)
      $possible_values = array(); 
    foreach ($possible_values as $possible_value) {
      $text .= "  <option value=\"$possible_value\"";
      if (in_array($possible_value, $cur_values)) {$text .= " selected"; }
      $text .= ">$possible_value</option>\n";
    }
    $text .=<<<END
	</select>
	<span id="$info_id" class="error_message"></span>
	<input type="hidden" name="$hidden_input_name" value="1" />

END;
    return array($text, null);
  }

  function checkboxesHTML($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args) {
    global $sfgTabIndex, $sfgFieldNum;

    $className = ($is_mandatory) ? "mandatoryField" : "createboxInput";
    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";
    $hidden_input_name = $input_name . "[is_list]";
    $disabled_text = ($is_disabled) ? "disabled" : "";
    // get list delimiter - default is comma
    if (array_key_exists('delimiter', $other_args)) {
      $delimiter = $other_args['delimiter'];
    } else {
      $delimiter = ",";
    }
    $cur_values = $this->getValuesArray($cur_value, $delimiter);

    if (($possible_values = $other_args['possible_values']) == null)
      $possible_values = array(); 
    $text = "";
    foreach ($possible_values as $key => $possible_value) {
      $cur_input_name = $input_name . "[" . $key . "]";
      $checked_text = (in_array($possible_value, $cur_values)) ? 'checked="checked"' : "";
      $text .=<<<END
	<span class="checkboxSpan">
	<input type="checkbox" id="$input_id" tabindex="$sfgTabIndex" name="$cur_input_name" value="$possible_value" class="$className" $checked_text $disabled_text/> $possible_value
	</span>

END;
      $sfgTabIndex++;
    }
    $text .=<<<END
	<span id="$info_id" class="error_message"></span>
	<input type="hidden" name="$hidden_input_name" value="1" />

END;
    return array($text, null);
  }

  function textEntryWithAutocompleteHTML($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args) {
    // if 'no autocomplete' was specified, call the regular text entry instead
    if (array_key_exists('no_autocomplete', $other_args) &&
        $other_args['no_autocomplete'] == true)
      return SFFormPrinter::textEntryHTML($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args);

    global $sfgTabIndex, $sfgFieldNum;

    $className = ($is_mandatory) ? "mandatoryField" : "autocompleteInput";
    $disabled_text = ($is_disabled) ? "disabled" : "";
    $size = $other_args['size'];
    if (array_key_exists('autocomplete on', $other_args)) {
      $autocomplete_field_type = "category";
      $semantic_field_name = $other_args['autocomplete on'];
    } elseif (array_key_exists('autocomplete on namespace', $other_args)) {
      $autocomplete_field_type = "namespace";
      $semantic_field_name = $other_args['autocomplete on namespace'];
    } else {
      $autocomplete_field_type = "relation";
      $semantic_field_name = $other_args['semantic_field_name'];
    }
    $input_id = "input_" . $sfgFieldNum;
    $info_id = "info_" . $sfgFieldNum;
    $div_name = "div_" . $sfgFieldNum;
    $text =<<<END
        <input tabindex="$sfgTabIndex" id="$input_id" name="$input_name" type="text"
	value="" size="$size" class="$className" $disabled_text/>
	<span id="$info_id" class="error_message"></span>
	<div class="page_name_auto_complete" id="$div_name" style="display:none"></div>
<script type="text/javascript">

END;
    $autocomplete_values = array();
    $autocomplete_values['mappings'] = array();
    $options_str_key = $semantic_field_name . ',' . $autocomplete_field_type;
    $javascript_text = "";
    if (! array_key_exists($options_str_key, $autocomplete_values))
      $autocomplete_values[$options_str_key] = $this->createAutocompleteValuesString(str_replace(' ', '_', $semantic_field_name), $autocomplete_field_type);
    $autocomplete_values['mappings'][$sfgFieldNum] = $options_str_key;
    foreach ($autocomplete_values as $autocomplete_key => $autocomplete_string) {
      if ($autocomplete_key == "mappings") {
        foreach ($autocomplete_string as $field_number => $field_key) {
          $javascript_text .= "autocompletemappings[$field_number] = '" . str_replace("'", "\'", $field_key) . "';\n";
        }
      } else {
        $javascript_text .= "autocompletestrings['" . str_replace("'", "\'", $autocomplete_key) . "'] = $autocomplete_string;\n";
      }
    }
    if ($cur_value) {
      $text .= "document.getElementById('$input_id').value = \"$cur_value\"\n";
    }
    $text .= "</script>\n";
    return array($text, $javascript_text);
  }

  function textAreaHTML($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args) {
    global $sfgTabIndex, $sfgFieldNum;

    $className = ($is_mandatory) ? "mandatoryField" : "createboxInput";
    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";
    $disabled_text = ($is_disabled) ? "disabled" : "";

    $rows = $other_args['rows'];
    $cols = $other_args['cols'];

    $text =<<<END
	<textarea tabindex="$sfgTabIndex" id="$input_id" name="$input_name" rows="$rows" cols="$cols" class="$className" $disabled_text>$cur_value</textarea>
	<span id="$info_id" class="error_message"></span>

END;
    return array($text, null);
  }

  function monthDropdownHTML($cur_month, $input_name, $is_disabled) {
    global $sfgTabIndex, $sfgFieldNum, $wgAmericanDates;

    $disabled_text = ($is_disabled) ? "disabled" : "";
    $text = '	<select tabindex="' . $sfgTabIndex . '" id="input_' . $sfgFieldNum . '_1" name="' . $input_name . "[month]\" $disabled_text>\n";
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
    foreach ($month_names as $i => $name) {
      $month_value = $wgAmericanDates ? $name : ($i + 1);
      $text .= "	<option value=\"$month_value\"";
      if ($name == $cur_month || ($i + 1) == $cur_month) {$text .= " selected=\"selected\""; }
      $text .= ">$name</option>\n";
    }
    $text .= "	</select>\n";
    return $text;
  }

  function dateEntryHTML($date, $input_name, $is_mandatory, $is_disabled, $other_args) {
    global $sfgTabIndex, $sfgFieldNum;

    if ($date) {
      // can show up here either as an array or a string, depending on
      // whether it came from user input or a wiki page
      if (is_array($date)) {
        $year = $date['year'];
        $month = $date['month'];
        $day = $date['day'];
      } else {
        if ($date == 'now') { // special value
          $actual_date = time();
        } else {
          $actual_date = strtotime($date);
        }
        $year = date("Y", $actual_date);
        $month = date("n", $actual_date);
        $day = date("j", $actual_date);
      }
    } else {
      $cur_date = getdate();
      $year = $cur_date['year'];
      $month = $cur_date['month'];
      $day = null; // no need for day
    }
    $disabled_text = ($is_disabled) ? "disabled" : "";
    $text = $this->monthDropdownHTML($month, $input_name, $is_disabled);
    $text .= '  <input tabindex="' . $sfgTabIndex . '" id="input_' . $sfgFieldNum . '_2" name="' . $input_name . '[day]" type="text" value="' . $day . '" size="2" ' . $disabled_text . '/>' . "\n";
    $text .= '  <input tabindex="' . $sfgTabIndex . '" id="input_' . $sfgFieldNum . '_3" name="' . $input_name . '[year]" type="text" value="' . $year . '" size="4" ' . $disabled_text . '/>' . "\n";
    $info_id = "info_$sfgFieldNum";
    $text .= "	<span id=\"$info_id\" class=\"error_message\"></span>";
    return array($text, null);
  }

  function dateTimeEntryHTML($datetime, $input_name, $is_mandatory, $is_disabled, $other_args) {
    global $sfgTabIndex;

    $include_timezone = $other_args['include_timezone'];
 
    if ($datetime) {
      // can show up here either as an array or a string, depending on
      // whether it came from user input or a wiki page
      if (is_array($datetime)) {
        if (isset($datetime['hour'])) $hour = $cur_value['hour'];
        if (isset($datetime['minute'])) $minute = $cur_value['minute'];
        if (isset($datetime['second'])) $second = $cur_value['second'];
        if (isset($datetime['ampm24h'])) $ampm24h = $cur_value['ampm24h'];
        if (isset($datetime['timezone'])) $timezone = $cur_value['timezone'];
      } else {
        if ($datetime == 'now') { // special value
          $actual_date = time();
        } else {
          $actual_date = strtotime($datetime);
        }
        $hour = date("g", $actual_date);
        $minute = date("i", $actual_date);
        $second = date("s", $actual_date);
        $ampm24h = date("A", $actual_date);
        $timezone = date("T", $actual_date);
      }
    } else {
      $cur_date = getdate();
      $hour = null;
      $minute = null;
      $second = "00"; // default at least this value
      $timezone = "";
    }

    list($text, $javascript_text) = $this->dateEntryHTML($datetime, $input_name, $is_mandatory, $is_disabled, $other_args);
    $disabled_text = ($is_disabled) ? "disabled" : "";
    $text .= '  &nbsp;<input tabindex="' . $sfgTabIndex . '" name="' . $input_name . '[hour]" type="text" value="' . $hour . '" size="2"/ ' . $disabled_text . '>';
    $sfgTabIndex++;
    $text .= '  :<input tabindex="' . $sfgTabIndex . '" name="' . $input_name . '[minute]" type="text" value="' . $minute . '" size="2"/ ' . $disabled_text . '>';
    $sfgTabIndex++;
    $text .= ':<input tabindex="' . $sfgTabIndex . '" name="' . $input_name . '[second]" type="text" value="' . $second . '" size="2"/ ' . $disabled_text . '>' . "\n";

    $sfgTabIndex++;
    $text .= '   <select tabindex="' . $sfgTabIndex . '" name="' . $input_name . "[ampm24h]\" $disabled_text>\n";
    $ampm24h_options = array('', 'AM', 'PM');
    foreach ($ampm24h_options as $value) {
      $text .= "        <option value=\"$value\"";
      if ($value == $ampm24h) {$text .= " selected=\"selected\""; }
      $text .= ">$value</option>\n";
    }
    $text .= "  </select>\n";

    if ($include_timezone) {
      $sfgTabIndex++;
      $text .= '  <input tabindex="' . $sfgTabIndex . '" name="' . $input_name . '[timezone]" type="text" value="' . $timezone . '" size="2"/ ' . $disabled_text . '>' . "\n";
    }

    return array($text, $javascript_text);
  }

  function radioButtonHTML($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args) {
    global $sfgTabIndex;

    $disabled_text = ($is_disabled) ? "disabled" : "";
    $check_set = false;
    if (! $is_mandatory) {
      $text .= '	<input type="radio" tabindex="' . $sfgTabIndex . '" name="' . $input_name . '" value=""';
      if (! $cur_value) {
        $text .= ' checked="checked"';
        $check_set = true;
      }
      $text .= " $disabled_text/> " . wfMsg('sf_editdata_none') . "\n";
    }
    if (($possible_values = $other_args['possible_values']) == null)
      $possible_values = array(); 
    foreach ($possible_values as $i => $possible_value) {
      $text .= '	<input type="radio" tabindex="' . $sfgTabIndex . '" name="' . $input_name . '" value="' . $possible_value . '"';
      if ($cur_value == $possible_value || (! $cur_value && ! $check_set)) {
        $text .= ' checked="checked"';
        $check_set = true;
      }
      $text .= " $disabled_text/> $possible_value\n";
    }
    return array($text, null);
  }

  function checkboxHTML($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args) {
    global $sfgTabIndex, $sfgFieldNum;

    $className = ($is_mandatory) ? "mandatoryField" : "createboxInput";
    $info_id = "info_$sfgFieldNum";
    $disabled_text = ($is_disabled) ? "disabled" : "";

    // can show up here either as an array or a string, depending on
    // whether it came from user input or a wiki page
    if (is_array($cur_value)) {
      $checked_str = ($cur_value[value] == 'on') ? ' checked="checked"' : "";
    } else {
      // default to false - no need to check if it matches a 'false' word
      $vlc = strtolower(trim($cur_value));
      if (in_array($vlc, explode(',', wfMsgForContent('smw_true_words')), TRUE)) {
        $checked_str = ' checked="checked"';
      } else {
        $checked_str = "";
      }
    }
    $text =<<<END
	<input name="{$input_name}[is_checkbox]" type="hidden" value="true" />
	<input id="input_$sfgFieldNum" name="{$input_name}[value]" type="checkbox" class="$className" tabindex="$sfgTabIndex" $checked_str $disabled_text/>
	<span id="$info_id" class="error_message"></span>

END;
    return array($text, null);
  }

  function hiddenFieldHTML($input_name, $cur_value) {
    $text =<<<END
	<input type="hidden" name="$input_name" value="$cur_value" />

END;
    return $text;
  }

  function summaryInputHTML($is_disabled, $label = null) {
    global $sfgTabIndex;

    $sfgTabIndex++;
    if ($label == null)
      $label = wfMsg('summary');
    $disabled_text = ($is_disabled) ? "disabled" : "";
    $text =<<<END
	<span id='wpSummaryLabel'><label for='wpSummary'>$label:</label></span>
	<input tabindex="$sfgTabIndex" type='text' value="" name='wpSummary' id='wpSummary' maxlength='200' size='60' $disabled_text/>

END;
    return $text;
  }

  function minorEditInputHTML($is_disabled, $label = null) {
    global $sfgTabIndex;

    $sfgTabIndex++;
    $disabled_text = ($is_disabled) ? "disabled" : "";
    if ($label == null)
      $label = wfMsgExt('minoredit', array('parseinline'));
    $accesskey = wfMsg('accesskey-minoredit');
    $tooltip = wfMsg('tooltip-minoredit');
    $text =<<<END
	<input tabindex="$sfgTabIndex" type="checkbox" value="1" name="wpMinoredit" accesskey="$accesskey" id="wpMinoredit" $disabled_text/>
	<label for="wpMinoredit" title="$tooltip">$label</label>

END;
    return $text;
  }

  function watchInputHTML($is_disabled, $label = null) {
    global $sfgTabIndex;

    $sfgTabIndex++;
    $disabled_text = ($is_disabled) ? "disabled" : "";
    if ($label == null)
      $label = wfMsgExt('watchthis', array('parseinline'));
    $accesskey = htmlspecialchars(wfMsg('accesskey-watch'));
    $tooltip = htmlspecialchars(wfMsg('tooltip-watch'));
    $text =<<<END
	<input tabindex="$sfgTabIndex" type="checkbox" name="wpWatchthis" accesskey="$accesskey" id='wpWatchthis' $disabled_text/>
        <label for="wpWatchthis" title="$tooltip">$label</label>

END;
    return $text;
  }

  function saveButtonHTML($is_disabled, $label = null) {
    global $sfgTabIndex;

    $sfgTabIndex++;
    $disabled_text = ($is_disabled) ? "disabled" : "";
    if ($label == null)
      $label = wfMsg('savearticle');
    $temp = array(
      'id'        => 'wpSave',
      'name'      => 'wpSave',
      'type'      => 'submit',
      'tabindex'  => $sfgTabIndex,
      'value'     => $label,
      'accesskey' => wfMsg('accesskey-save'),
      'title'     => wfMsg('tooltip-save'),
      $disabled_text => '',
    );
    $text = "		" . wfElement('input', $temp, '') . "\n";
    return $text;
  }

  function showPreviewButtonHTML($is_disabled, $label = null) {
    global $sfgTabIndex;

    $sfgTabIndex++;
    $disabled_text = ($is_disabled) ? "disabled" : "";
    if ($label == null)
      $label = wfMsg('showpreview');
    $temp = array(
      'id'        => 'wpPreview',
      'name'      => 'wpPreview',
      'type'      => 'submit',
      'tabindex'  => $sfgTabIndex,
      'value'     => $label,
      'accesskey' => wfMsg('accesskey-preview'),
      'title'     => wfMsg('tooltip-preview'),
      $disabled_text => '',
    );
    $text = "		" . wfElement('input', $temp, '') . "\n";
    return $text;
  }

  function showChangesButtonHTML($is_disabled, $label = null) {
    global $sfgTabIndex;

    $sfgTabIndex++;
    $disabled_text = ($is_disabled) ? "disabled" : "";
    if ($label == null)
      $label = wfMsg('showdiff');
    $temp = array(
      'id'        => 'wpDiff',
      'name'      => 'wpDiff',
      'type'      => 'submit',
      'tabindex'  => $sfgTabIndex,
      'value'     => $label,
      'accesskey' => wfMsg('accesskey-diff'),
      'title'     => wfMsg('tooltip-diff'),
      $disabled_text => '',
    );
    $text = "		" . wfElement('input', $temp, '') . "\n";
    return $text;
  }

  function cancelLinkHTML($is_disabled, $label = null) {
    global $wgUser;

    $sk = $wgUser->getSkin();
    if ($label == null)
      $label = wfMsgExt('cancel', array('parseinline'));
    if ($this->mPageTitle == null)
      $cancel = '';
    else
      $cancel = $sk->makeKnownLink( $this->mPageTitle->getPrefixedText(), $label);
    $text = "		<span class='editHelp'>$cancel</span>\n";
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
  function formBottom($is_disabled) {
    global $wgUser;
    
    $summary_text = $this->summaryInputHTML($is_disabled);
    $text =<<<END
	<br /><br />
	<div class='editOptions'>
$summary_text	<br />

END;
    if ( $wgUser->isAllowed('minoredit') ) {
      $text .= $this->minorEditInputHTML($is_disabled);
    }

    if ( $wgUser->isLoggedIn() ) {
      $text .= $this->watchInputHTML($is_disabled);
    }

    $text .=<<<END
	<br />
	<div class='editButtons'>

END;
    $text .= $this->saveButtonHTML($is_disabled);
    $text .= $this->showPreviewButtonHTML($is_disabled);
    $text .= $this->showChangesButtonHTML($is_disabled);
    $text .= $this->cancelLinkHTML($is_disabled);
    $text .=<<<END
	</div><!-- editButtons -->
	</div><!-- editOptions -->

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
