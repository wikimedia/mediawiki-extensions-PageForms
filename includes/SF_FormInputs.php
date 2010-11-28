<?php
/**
 * Helper functions to display the various inputs of a user-generated form
 *
 * @author Yaron Koren
 * @author Jeffrey Stuckman
 * @author Matt Williamson
 * @author Patrick Nagel
 * @author Sanyam Goyal
 */

class SFFormInput {
	public static function getStandardParameters() {
		$params = array();
		$params[] = array( 'name' => 'restricted', 'type' => 'boolean'  );
		$params[] = array( 'name' => 'mandatory', 'type' => 'boolean' );
		return array();
	}

	public static function getParameters() {
		$params = array();
		$params[] = array( 'name' => 'class', 'type' => 'string' );
		$params[] = array( 'name' => 'default', 'type' => 'string' );
		$params[] = array( 'name' => 'preload', 'type' => 'string' );
		return array();
	}
}

class SFEnumInput extends SFFormInput {
	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = array( 'name' => 'show on select', 'type' => 'string' );
		return $params;
	}
}

class SFMultiEnumInput extends SFEnumInput {
	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = array( 'name' => 'delimiter', 'type' => 'string' );
		return $params;
	}
}

class SFTextInput extends SFFormInput {
  static function uploadLinkHTML( $input_id, $delimiter = null, $default_filename = null ) {
    global $wgOut, $sfgScriptPath, $sfgFancyBoxIncluded;

    $upload_window_page = SpecialPage::getPage( 'UploadWindow' );
    $query_string = "sfInputID=$input_id";
    $fancybox_id = "fancybox_$input_id";
    if ( $delimiter != null )
      $query_string .= "&sfDelimiter=$delimiter";
    if ( $default_filename != null )
      $query_string .= "&wpDestFile=$default_filename";
    $upload_window_url = $upload_window_page->getTitle()->getFullURL( $query_string );
    $upload_label = wfMsg( 'upload' );
    // window needs to be bigger for MediaWiki version 1.16+
    if ( class_exists( 'HTMLForm' ) )
      $style = "width:650 height:500";
    else
      $style = '';

    if ( !$sfgFancyBoxIncluded &&  !class_exists( 'ResourceLoader' ) ) {
      $sfgFancyBoxIncluded = true;
      global $wgOut;
      $wgOut->addScriptFile( "$sfgScriptPath/libs/jquery.fancybox-1.3.1.js" );
    }

    $fancybox_js =<<<END
<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery("#$fancybox_id").fancybox({
		'width'		: '75%',
		'height'	: '75%',
		'autoScale'	: false,
		'transitionIn'	: 'none',
		'transitionOut'	: 'none',
		'type'		: 'iframe',
		'overlayColor'  : '#222',
		'overlayOpacity' : '0.8'
	});
});
</script>
END;
    $wgOut->addScript($fancybox_js);     

    $text = " <a id = \"$fancybox_id\"  href=\"$upload_window_url\" title=\"$upload_label\" rev=\"$style\">$upload_label</a>";    
    return $text;
  }

  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    // if it's an autocomplete, call the with-autocomplete function instead
    if ( array_key_exists( 'autocompletion source', $other_args ) ) {
        return SFTextWithAutocompleteInput::getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );
    }

    // if there are possible values specified, call the dropdown function
    if ( array_key_exists( 'possible_values', $other_args ) && $other_args['possible_values'] != null )
      return SFDropdownInput::getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );

    global $sfgTabIndex, $sfgFieldNum, $sfgJSValidationCalls;

    $className = ( $is_mandatory ) ? "createboxInput mandatoryField" : "createboxInput";
    if ( array_key_exists( 'class', $other_args ) )
      $className .= " " . $other_args['class'];
    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";
    // set size based on pre-set size, or field type - if field type is set,
    // possibly add validation too
    if ( array_key_exists( 'size', $other_args ) ) {
      $size = $other_args['size'];
    } elseif ( array_key_exists( 'field_type', $other_args ) ) {
      $validation_type_str = "";
      if ( $other_args['field_type'] == 'integer' ) {
        $size = 10;
        $validation_type_str = 'integer';
      } elseif ( $other_args['field_type'] == 'number' ) {
        $size = 10;
        $validation_type_str = 'number';
      } elseif ( $other_args['field_type'] == 'URL' ) {
        $size = 100;
        $validation_type_str = 'URL';
      } elseif ( $other_args['field_type'] == 'email' ) {
        $size = 45;
        $validation_type_str = 'email';
      } else {
        $size = 35;
      }
      if ( $validation_type_str != '' ) {
        if ( array_key_exists( 'part_of_multiple', $other_args ) ) {
          $sfgJSValidationCalls[] = "validate_type_of_multiple_fields($sfgFieldNum, '$validation_type_str')";
        } else {
          $sfgJSValidationCalls[] = "validate_field_type('$input_id', '$validation_type_str', '$info_id')";
        }
      }
    } else {
      $size = 35;
    }
    if ( ! is_null( $cur_value ) && ! is_array( $cur_value ) )
      $cur_value = htmlspecialchars( $cur_value );

    $text = <<<END
		<input id="$input_id" tabindex="$sfgTabIndex" class="$className" name="$input_name" type="text" value="$cur_value" size="$size"
END;
    if ( $is_disabled )
      $text .= " disabled";
    if ( array_key_exists( 'maxlength', $other_args ) )
      $text .= ' maxlength="' . $other_args['maxlength'] . '"';
    $text .= <<<END
/>
	<span id="$info_id" class="errorMessage"></span>

END;
    if ( array_key_exists( 'is_uploadable', $other_args ) && $other_args['is_uploadable'] == true ) {
      if ( array_key_exists( 'is_list', $other_args ) && $other_args['is_list'] == true ) {
        if ( array_key_exists( 'delimiter', $other_args ) ) {
          $delimiter = $other_args['delimiter'];
        } else {
          $delimiter = ",";
        }
      } else {
        $delimiter = null;
      }
     if ( array_key_exists( 'default filename', $other_args ) ) {
        $default_filename = $other_args['default filename'];
      } else {
        $default_filename = "";
      }
      $text .= self::uploadLinkHTML( $input_id, $delimiter, $default_filename );
    }
    return array( $text, null );
  }

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = array( 'name' => 'size', 'type' => 'int' );
		$params[] = array( 'name' => 'maxlength', 'type' => 'int' );
		$params[] = array( 'name' => 'uploadable', 'type' => 'boolean' );
		$params[] = array( 'name' => 'default filename', 'type' => 'string' );
		return $params;
	}
}

class SFDropdownInput extends SFEnumInput {
  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    global $sfgTabIndex, $sfgFieldNum, $sfgShowOnSelectCalls;

    $className = ( $is_mandatory ) ? "mandatoryField" : "createboxInput";
    if ( array_key_exists( 'class', $other_args ) )
      $className .= " " . $other_args['class'];
    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";
    $disabled_text = ( $is_disabled ) ? "disabled" : "";
    if ( array_key_exists( 'show on select', $other_args ) ) {
      foreach ( $other_args['show on select'] as $div_id => $options ) {
        $options_str = implode( "', '", $options );
        $js_text = "showIfSelected('$input_id', ['$options_str'], '$div_id'); ";
        $sfgShowOnSelectCalls[] = "jQuery('#$input_id').change( function() { $js_text } );";
        $sfgShowOnSelectCalls[] = $js_text;
      }
    }
    $text = <<<END
	<select id="$input_id" tabindex="$sfgTabIndex" name="$input_name" class="$className" $disabled_text>

END;
    // add a blank value at the beginning, unless this is a mandatory field
    // and there's a current value in place (either through a default value
    // or because we're editing an existing page)
    if ( ! $is_mandatory || $cur_value == '' ) {
      $text .= "  <option value=\"\"></option>\n";
    }
    if ( ( $possible_values = $other_args['possible_values'] ) == null )
      $possible_values = array();
    foreach ( $possible_values as $possible_value ) {
      $text .= "  <option value=\"$possible_value\"";
      if ( $possible_value == $cur_value ) { $text .= " selected=\"selected\""; }
      $text .= ">";
      if ( array_key_exists( 'value_labels', $other_args ) && is_array( $other_args['value_labels'] ) && array_key_exists( $possible_value, $other_args['value_labels'] ) )
        $text .= htmlspecialchars( $other_args['value_labels'][$possible_value] );
      else
        $text .= $possible_value;
      $text .= "</option>\n";
    }
    $text .= <<<END
	</select>
	<span id="$info_id" class="errorMessage"></span>

END;
    return array( $text, null );
  }
}

class SFListBoxInput extends SFMultiEnumInput {
  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    global $sfgTabIndex, $sfgFieldNum, $sfgShowOnSelectCalls;

    $className = ( $is_mandatory ) ? "mandatoryField" : "createboxInput";
    if ( array_key_exists( 'class', $other_args ) )
      $className .= " " . $other_args['class'];
    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";
    $hidden_input_name = $input_name . "[is_list]";
    $input_name .= "[]"; // needed so that this input will send an array
    if ( array_key_exists( 'size', $other_args ) )
      $size_text = "size=" . $other_args['size'];
    else
      $size_text = "";
    $disabled_text = ( $is_disabled ) ? "disabled" : "";
    // get list delimiter - default is comma
    if ( array_key_exists( 'delimiter', $other_args ) ) {
      $delimiter = $other_args['delimiter'];
    } else {
       $delimiter = ",";
    }
    $cur_values = SFUtils::getValuesArray( $cur_value, $delimiter );

    $text = <<<END
	<select id="$input_id" tabindex="$sfgTabIndex" name="$input_name" class="$className" multiple $size_text $disabled_text>

END;
    if ( ( $possible_values = $other_args['possible_values'] ) == null )
      $possible_values = array();
    $enum_input_ids = array();
    foreach ( $possible_values as $possible_value ) {
      // create array $enum_input_ids to associate values with their input IDs,
      // for use in creating the 'show on select' Javascript later
      $enum_input_ids[$possible_value] = $input_id;
      $text .= "  <option value=\"$possible_value\"";
      if ( in_array( $possible_value, $cur_values ) ) { $text .= " selected"; }
      $text .= ">";
      if ( array_key_exists( 'value_labels', $other_args ) && is_array( $other_args['value_labels'] ) && array_key_exists( $possible_value, $other_args['value_labels'] ) )
        $text .= htmlspecialchars( $other_args['value_labels'][$possible_value] );
      else
        $text .= $possible_value;
      $text .= "</option>\n";
    }
    $text .= <<<END
	</select>
	<span id="$info_id" class="errorMessage"></span>
	<input type="hidden" name="$hidden_input_name" value="1" />

END;

    if ( array_key_exists( 'show on select', $other_args ) ) {
      foreach ( $other_args['show on select'] as $div_id => $options ) {
        $cur_input_ids = array();
        foreach ( $options as $option ) {
          if ( array_key_exists( $option, $enum_input_ids ) ) {
            $cur_input_ids[] = $enum_input_ids[$option];
          }
        }
        $options_str = "['" . implode( "', '", $options ) . "']";
        $js_text = "showIfSelectedInListBox('$input_id', $options_str, '$div_id'); ";
        $sfgShowOnSelectCalls[] = $js_text;
        foreach ( $possible_values as $key => $possible_value ) {
          $cur_input_id = $enum_input_ids[$possible_value];
          if ( in_array( $possible_value, $options ) ) {
            $sfgShowOnSelectCalls[] = "jQuery('#$cur_input_id').click( function() { $js_text } );";
          }
        }
      }
    }

    return array( $text, null );
  }
}

class SFCheckboxesInput extends SFMultiEnumInput {
  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    global $sfgTabIndex, $sfgFieldNum, $sfgShowOnSelectCalls;

    $checkbox_class = ( $is_mandatory ) ? "mandatoryField" : "createboxInput";
    $span_class = "checkboxSpan";
    if ( array_key_exists( 'class', $other_args ) )
      $span_class .= " " . $other_args['class'];
    $input_id = "input_$sfgFieldNum";
    $hidden_input_name = $input_name . "[is_list]";
    // get list delimiter - default is comma
    if ( array_key_exists( 'delimiter', $other_args ) ) {
      $delimiter = $other_args['delimiter'];
    } else {
      $delimiter = ",";
    }
    $cur_values = SFUtils::getValuesArray( $cur_value, $delimiter );

    if ( ( $possible_values = $other_args['possible_values'] ) == null )
      $possible_values = array();
    $text = "";
    $enum_input_ids = array();
    foreach ( $possible_values as $key => $possible_value ) {
      // create array $enum_input_ids to associate values with their input IDs,
      // for use in creating the 'show on select' Javascript later
      $enum_input_ids[$possible_value] = $input_id;
      $cur_input_name = $input_name . "[" . $key . "]";

      if ( array_key_exists( 'value_labels', $other_args ) && is_array( $other_args['value_labels'] ) && array_key_exists( $possible_value, $other_args['value_labels'] ) )
        $label = htmlspecialchars( $other_args['value_labels'][$possible_value] );
      else
        $label = $possible_value;

      $checkbox_attrs = array(
        'type' => 'checkbox',
        'id' => $input_id,
        'tabindex' => $sfgTabIndex,
        'name' => $cur_input_name,
        'value' => $possible_value,
        'class' => $checkbox_class,
      );
      if ( in_array( $possible_value, $cur_values ) ) {
        $checkbox_attrs['checked'] = 'checked';
      }
      if ( $is_disabled ) {
        $checkbox_attrs['disabled'] = 'disabled';
      }
      $checkbox_input = Xml::element( 'input', $checkbox_attrs );
      $text .= '	' . Xml::tags( 'span',
        array( 'class' => $span_class ),
        $checkbox_input . ' ' . $label
      ) . "\n";
      $sfgTabIndex++;
      $sfgFieldNum++;
      $input_id = "input_$sfgFieldNum";
    }
    // if it's mandatory, add a span around all the checkboxes, since
    // some browsers don't support formatting of checkboxes
    if ( $is_mandatory ) {
      $text = '	' . Xml::tags( 'span',
        array(
          'id' => $input_id,
          'class' => 'mandatoryFieldsSpan',
        ), $text ) . "\n";
    }
    $info_id = "info_$sfgFieldNum";
    $text .= <<<END
	<span id="$info_id" class="errorMessage"></span>
	<input type="hidden" name="$hidden_input_name" value="1" />

END;

    if ( array_key_exists( 'show on select', $other_args ) ) {
      foreach ( $other_args['show on select'] as $div_id => $options ) {
        $cur_input_ids = array();
        foreach ( $options as $option ) {
          if ( array_key_exists( $option, $enum_input_ids ) ) {
            $cur_input_ids[] = $enum_input_ids[$option];
          }
        }
        $options_str = "['" . implode( "', '", $cur_input_ids ) . "']";
        $js_text = "showIfChecked($options_str, '$div_id'); ";
        $sfgShowOnSelectCalls[] = $js_text;
        foreach ( $possible_values as $key => $possible_value ) {
          $cur_input_id = $enum_input_ids[$possible_value];
          if ( in_array( $possible_value, $options ) ) {
            $sfgShowOnSelectCalls[] = "jQuery('#$cur_input_id').click( function() { $js_text } );";
          }
        }
      }
    }

    return array( $text, null );
  }
}

class SFComboBoxInput extends SFFormInput {
  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    if ( array_key_exists( 'no autocomplete', $other_args ) &&
        $other_args['no autocomplete'] == true ) {
      unset( $other_args['autocompletion source'] );
      return SFTextInput::getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );
    }
    // if a set of values was specified, print a dropdown instead
    if ( array_key_exists( 'possible_values', $other_args ) && $other_args['possible_values'] != null )
      return SFDropdownInput::getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );

    global $sfgTabIndex, $sfgFieldNum, $wgOut, $sfgScriptPath, $wgJsMimeType;
    global $smwgScriptPath, $smwgJqUIAutoIncluded;
    global $sfgAutocompleteMappings, $sfgComboBoxInputs, $sfgAutogrowInputs;

    $autocomplete_field_type = "";
    $autocompletion_source = "";

    $className = ( $is_mandatory ) ? "autocompleteInput mandatoryField" : "autocompleteInput createboxInput";
    if ( array_key_exists( 'class', $other_args ) )
      $className .= " " . $other_args['class'];
    $disabled_text = ( $is_disabled ) ? "disabled" : "";
    if ( array_key_exists( 'autocomplete field type', $other_args ) ) {
      $autocomplete_field_type = $other_args['autocomplete field type'];
      $autocompletion_source = $other_args['autocompletion source'];
      if ( $autocomplete_field_type != 'external_url' ) {
        global $wgContLang;
        $autocompletion_source = $wgContLang->ucfirst( $autocompletion_source );
      }
    }
    if ( array_key_exists( 'size', $other_args ) )
      $size = $other_args['size'];
    else
      $size = "35";

    $input_id = "input_" . $sfgFieldNum;
    $info_id = "info_" . $sfgFieldNum;
    $div_name = "div_" . $sfgFieldNum;
       
    $options_str_key = str_replace( "'", "\'", $autocompletion_source );
    $sfgAutocompleteMappings[$sfgFieldNum] = $options_str_key;
    
    $values = SFUtils::getAutocompleteValues($autocompletion_source, $autocomplete_field_type );

    /*adding code for displaying dropdown of autocomplete values*/

    $text =<<<END
<div class="ui-widget">
	<select id="input_$sfgFieldNum" name="$input_name" tabindex="$sfgTabIndex">
		<option value="$cur_value"></option>

END;
    foreach ($values as $value) {
      $text .= "		<option value=\"$value\">$value</option>\n";
    }
    $text .= <<<END
	</select>
	<span id="$info_id" class="errorMessage"></span>
</div>
END;
    $sfgComboBoxInputs[] = $sfgFieldNum;
    // there's no direct correspondence between the 'size=' attribute for
    // text inputs and the number of pixels, but multiplying by 6 seems to
    // be about right for the major browsers
    $pixel_width = $size * 6;
    $combobox_css =<<<END
<style type="text/css">
input#input_$sfgFieldNum { width: {$pixel_width}px; }
</style>
END;
    $wgOut->addScript($combobox_css);
    return array( $text, null );
  }
}

class SFTextWithAutocompleteInput extends SFTextInput {
  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    // if 'no autocomplete' was specified, print a regular text entry instead
    if ( array_key_exists( 'no autocomplete', $other_args ) &&
        $other_args['no autocomplete'] == true ) {
      unset( $other_args['autocompletion source'] );
      return SFTextInput::getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );
    }
    // if a set of values was specified, print a dropdown instead
    if ( array_key_exists( 'possible_values', $other_args ) && $other_args['possible_values'] != null )
      return SFDropdownInput::getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );

    global $sfgTabIndex, $sfgFieldNum, $sfgScriptPath, $wgJsMimeType, $smwgScriptPath, $smwgJqUIAutoIncluded;
    global $sfgAutogrowInputs, $sfgAutocompleteMappings, $sfgAutocompleteDataTypes, $sfgAutocompleteValues;

    $className = ( $is_mandatory ) ? "autocompleteInput mandatoryField" : "autocompleteInput createboxInput";
    if ( array_key_exists( 'class', $other_args ) )
      $className .= " " . $other_args['class'];
    if ( array_key_exists( 'autocomplete field type', $other_args ) ) {
      $autocomplete_field_type = $other_args['autocomplete field type'];
      $autocompletion_source = $other_args['autocompletion source'];
      if ( $autocomplete_field_type != 'external_url' ) {
        global $wgContLang;
        $autocompletion_source = $wgContLang->ucfirst( $autocompletion_source );
      }
    }
    $input_id = "input_" . $sfgFieldNum;
    $info_id = "info_" . $sfgFieldNum;
    $div_name = "div_" . $sfgFieldNum;
    if ( array_key_exists( 'input_type', $other_args ) && $other_args['input_type'] == "textarea" ) {
       
      $rows = $other_args['rows'];
      $cols = $other_args['cols'];
      $text = "";
      if ( array_key_exists( 'autogrow', $other_args ) ) {
        $sfgAutogrowInputs[] = $input_id;
        $className .= ' autoGrow';
        if ( ! method_exists( 'OutputPage', 'addModules' ) ) {
          global $wgOut;
          $wgOut->addScriptFile( "$sfgScriptPath/libs/SF_autogrow.js" );
        }
      }

      $textarea_attrs = array(
        'tabindex' => $sfgTabIndex,
        'id' => $input_id,
        'name' => $input_name,
        'rows' => $rows,
        'cols' => $cols,
        'class' => $className,
      );
      if ( $is_disabled ) {
        $textarea_attrs['disabled'] = 'disabled';
      }
      if ( array_key_exists( 'maxlength', $other_args ) ) {
        $maxlength = $other_args['maxlength'];
        // For every actual character pressed (i.e., excluding things like the
        // Shift key), reduce the string to its allowed length if it's exceeded
        // that.
        // This JS code is complicated so that it'll work correctly in IE - IE
        // moves the cursor to the end whenever this.value is reset, so we'll
        // make sure to do that only when we need to.
        $maxLengthJSCheck = "if (window.event && window.event.keyCode < 48 && window.event.keyCode != 13) return; if (this.value.length > $maxlength) { this.value = this.value.substring(0, $maxlength); }";
        $textarea_attrs['onKeyDown'] = $maxLengthJSCheck;
        $textarea_attrs['onKeyUp'] = $maxLengthJSCheck;
      }
      $textarea_input = Xml::element('textarea', $textarea_attrs, '', false);
      $text .= $textarea_input;
    } else {
      if ( array_key_exists( 'size', $other_args ) )
        $size = $other_args['size'];
      else
        $size = "35";

      $text = <<<END
        <input tabindex="$sfgTabIndex" id="$input_id" name="$input_name" type="text" value="" size="$size" class="$className"

END;
    if ( $is_disabled )
      $text .= " disabled";
    if ( array_key_exists( 'maxlength', $other_args ) )
      $text .= ' maxlength="' . $other_args['maxlength'] . '"';
    $text .= "/>\n";
    }
    // is_list and delimiter variables - needed later
    $is_list = ( array_key_exists( 'is_list', $other_args ) && $other_args['is_list'] == true );
    if ( $is_list ) {
      if ( array_key_exists( 'delimiter', $other_args ) ) {
        $delimiter = $other_args['delimiter'];
      } else {
        $delimiter = ",";
      }
    } else {
      $delimiter = null;
    }
    if ( array_key_exists( 'is_uploadable', $other_args ) && $other_args['is_uploadable'] == true ) {
     if ( array_key_exists( 'default filename', $other_args ) ) {
        $default_filename = $other_args['default filename'];
      } else {
        $default_filename = "";
      }
      $text .= self::uploadLinkHTML( $input_id, $delimiter, $default_filename );
    }
    $text .= <<<END
	<span id="$info_id" class="errorMessage"></span>
	<div class="page_name_auto_complete" id="$div_name"></div>
<script type="text/javascript">/* <![CDATA[ */

END;
    
    $options_str_key = $autocompletion_source;
    if ( $is_list ) {
      $options_str_key .= ",list";
      if ( $delimiter != "," ) {
        $options_str_key .= "," . $delimiter;
      }
    }
    $sfgAutocompleteMappings[$sfgFieldNum] = $options_str_key;
    if ( array_key_exists( 'remote autocompletion', $other_args ) &&
        $other_args['remote autocompletion'] == true ) {
      $sfgAutocompleteDataTypes[$options_str_key] = $autocomplete_field_type;
    } elseif ( $autocompletion_source != '' ) {
      $autocomplete_values = SFUtils::getAutocompleteValues( $autocompletion_source, $autocomplete_field_type );
      $sfgAutocompleteValues[$options_str_key] = $autocomplete_values;
    }
    if ( $cur_value ) {
      // replace various values to not break the Javascript
      $cur_value = str_replace( '"', '\"', $cur_value );
      $cur_value = str_replace( "\n", '\n', $cur_value );
      $cur_value = str_replace( "\r", '\r', $cur_value );
      $text .= "document.getElementById('$input_id').value = \"$cur_value\"\n";
    }
    $text .= '/* ]]> */</script>';
    return array( $text, null );
  }

	public static function getParameters() {
		$params = array();
		$params[] = array( 'name' => 'maxlength', 'type' => 'int' );
		$params[] = array( 'name' => 'list', 'type' => 'boolean' );
		$params[] = array( 'name' => 'remote autocompletion', 'type' => 'boolean' );
		return array();
	}
}

class SFTextAreaInput extends SFFormInput {
  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    // set size values
      
    if ( ! array_key_exists( 'rows', $other_args ) )
      $other_args['rows'] = 5;
    if ( ! array_key_exists( 'cols', $other_args ) )
      $other_args['cols'] = 80;

    // if it's an autocomplete, call the with-autocomplete function instead
    if ( array_key_exists( 'autocompletion source', $other_args ) ) {
        $other_args['input_type'] = "textarea";
        return SFTextWithAutocompleteInput::getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );
    }

    global $sfgTabIndex, $sfgFieldNum, $smwgScriptPath, $sfgScriptPath, $sfgAutogrowInputs;

    $className = ( $is_mandatory ) ? "mandatoryField" : "createboxInput";
    if ( array_key_exists( 'class', $other_args ) )
      $className .= " " . $other_args['class'];
    $info_id = "info_$sfgFieldNum";
    // use a special ID for the free text field, for FCK's needs
    $input_id = $input_name == "free_text" ? "free_text" : "input_$sfgFieldNum";

    $rows = $other_args['rows'];
    $cols = $other_args['cols'];

    $text = "";
    if ( array_key_exists( 'autogrow', $other_args ) ) {
      $sfgAutogrowInputs[] = $input_id;
      $className .= ' autoGrow';
      if ( ! method_exists( 'OutputPage', 'addModules' ) ) {
        global $wgOut;
        $wgOut->addScriptFile( "$sfgScriptPath/libs/SF_autogrow.js" );
      }
    }

    $textarea_attrs = array(
      'tabindex' => $sfgTabIndex,
      'id' => $input_id,
      'name' => $input_name,
      'rows' => $rows,
      'cols' => $cols,
      'class' => $className,
    );
    if ( $is_disabled ) {
      $textarea_attrs['disabled'] = 'disabled';
    }
    if ( array_key_exists( 'maxlength', $other_args ) ) {
      $maxlength = $other_args['maxlength'];
      // For every actual character pressed (i.e., excluding things like the
      // Shift key), reduce the string to its allowed length if it's exceeded
      // that.
      // This JS code is complicated so that it'll work correctly in IE - IE
      // moves the cursor to the end whenever this.value is reset, so we'll
      // make sure to do that only when we need to.
      $maxLengthJSCheck = "if (window.event && window.event.keyCode < 48 && window.event.keyCode != 13) return; if (this.value.length > $maxlength) { this.value = this.value.substring(0, $maxlength); }";
      $textarea_attrs['onKeyDown'] = $maxLengthJSCheck;
      $textarea_attrs['onKeyUp'] = $maxLengthJSCheck;
    }
    $textarea_input = Xml::element( 'textarea', $textarea_attrs, $cur_value, false );
    $text .= <<<END
	$textarea_input
	<span id="$info_id" class="errorMessage"></span>

END;
    return array( $text, null );
  }

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = array( 'name' => 'rows', 'type' => 'int' );
		$params[] = array( 'name' => 'cols', 'type' => 'int' );
		$params[] = array( 'name' => 'autogrow', 'type' => 'boolean' );
		return $params;
	}
}

class SFDateInput extends SFFormInput {
  static function monthDropdownHTML( $cur_month, $input_name, $is_disabled ) {
    global $sfgTabIndex, $sfgFieldNum, $wgAmericanDates;

    $disabled_text = ( $is_disabled ) ? "disabled" : "";
    $text = '	<select tabindex="' . $sfgTabIndex . '" id="input_' . $sfgFieldNum . '_month" name="' . $input_name . "[month]\" $disabled_text>\n";
    $month_names = SFFormUtils::getMonthNames();
    foreach ( $month_names as $i => $name ) {
      // pad out month to always be two digits
      $month_value = ( $wgAmericanDates == true ) ? $name : str_pad( $i + 1, 2, "0", STR_PAD_LEFT );
      $text .= "	<option value=\"$month_value\"";
      if ( $name == $cur_month || ( $i + 1 ) == $cur_month ) { $text .= " selected=\"selected\""; }
      $text .= ">$name</option>\n";
    }
    $text .= "	</select>\n";
    return $text;
  }

  static function getText( $date, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    global $sfgTabIndex, $sfgFieldNum, $sfgJSValidationCalls, $wgAmericanDates;

    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";
    // add to validation calls
    if ( array_key_exists( 'part_of_multiple', $other_args ) ) {
      $sfgJSValidationCalls[] = "validate_type_of_multiple_fields($sfgFieldNum, 'date')";
    } else {
      $sfgJSValidationCalls[] = "validate_field_type('$input_id', 'date', '$info_id')";
    }

    if ( $date ) {
      // can show up here either as an array or a string, depending on
      // whether it came from user input or a wiki page
      if ( is_array( $date ) ) {
        $year = $date['year'];
        $month = $date['month'];
        $day = $date['day'];
      } else {
        // handle 'default=now'
        if ( $date == 'now' ) $date = date( 'Y/m/d' );
        $actual_date = new SMWTimeValue( '_dat' );
        $actual_date->setUserValue( $date );
        $year = $actual_date->getYear();
        // TODO - the code to convert from negative to BC notation should
        // be in SMW itself
        if ( $year < 0 ) { $year = ( $year * - 1 + 1 ) . " BC"; }
        $month = $actual_date->getMonth();
        $day = $actual_date->getDay();
      }
    } else {
      $cur_date = getdate();
      $year = $cur_date['year'];
      $month = $cur_date['month'];
      $day = null; // no need for day
    }
    $text = "";
    $disabled_text = ( $is_disabled ) ? "disabled" : "";
    if ( $wgAmericanDates ) {
      $text .= self::monthDropdownHTML( $month, $input_name, $is_disabled );
      $text .= '  <input tabindex="' . $sfgTabIndex . '" id="' . $input_id . '_day" name="' . $input_name . '[day]" type="text" value="' . $day . '" size="2" ' . $disabled_text . '/>' . "\n";
    } else {
      $text .= '  <input tabindex="' . $sfgTabIndex . '" id="' . $input_id . '_day" name="' . $input_name . '[day]" type="text" value="' . $day . '" size="2" ' . $disabled_text . '/>' . "\n";
      $text .= self::monthDropdownHTML( $month, $input_name, $is_disabled );
    }
    $text .= '  <input tabindex="' . $sfgTabIndex . '" id="' . $input_id . '_year" name="' . $input_name . '[year]" type="text" value="' . $year . '" size="4" ' . $disabled_text . '/>' . "\n";
    $text .= "	<span id=\"$info_id\" class=\"errorMessage\"></span>";
    return array( $text, null );
  }
}

class SFDateTimeInput extends SFDateInput {
  static function getText( $datetime, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    global $sfgTabIndex, $sfg24HourTime;

    $include_timezone = $other_args['include_timezone'];
 
    if ( $datetime ) {
      // can show up here either as an array or a string, depending on
      // whether it came from user input or a wiki page
      if ( is_array( $datetime ) ) {
        if ( isset( $datetime['hour'] ) ) $hour = $datetime['hour'];
        if ( isset( $datetime['minute'] ) ) $minute = $datetime['minute'];
        if ( isset( $datetime['second'] ) ) $second = $datetime['second'];
        if ( ! $sfg24HourTime ) {
          if ( isset( $datetime['ampm24h'] ) ) $ampm24h = $datetime['ampm24h'];
        }
        if ( isset( $datetime['timezone'] ) ) $timezone = $datetime['timezone'];
      } else {
        // TODO - this should change to use SMW's own date-handling class,
        // just like dateEntryHTML() does
        $actual_date = strtotime( $datetime );
        if ( $sfg24HourTime ) {
          $hour = date( "G", $actual_date );
        } else {
          $hour = date( "g", $actual_date );
        }
        $minute = date( "i", $actual_date );
        $second = date( "s", $actual_date );
        if ( ! $sfg24HourTime ) {
          $ampm24h = date( "A", $actual_date );
        }
        $timezone = date( "T", $actual_date );
      }
    } else {
      $cur_date = getdate();
      $hour = null;
      $minute = null;
      $second = "00"; // default at least this value
      $ampm24h = "";
      $timezone = "";
    }

    list( $text, $javascript_text ) = parent::getText( $datetime, $input_name, $is_mandatory, $is_disabled, $other_args );
    $disabled_text = ( $is_disabled ) ? "disabled" : "";
    $text .= '  &#160;<input tabindex="' . $sfgTabIndex . '" name="' . $input_name . '[hour]" type="text" value="' . $hour . '" size="2"/ ' . $disabled_text . '>';
    $sfgTabIndex++;
    $text .= '  :<input tabindex="' . $sfgTabIndex . '" name="' . $input_name . '[minute]" type="text" value="' . $minute . '" size="2"/ ' . $disabled_text . '>';
    $sfgTabIndex++;
    $text .= ':<input tabindex="' . $sfgTabIndex . '" name="' . $input_name . '[second]" type="text" value="' . $second . '" size="2"/ ' . $disabled_text . '>' . "\n";

    if ( ! $sfg24HourTime ) {
      $sfgTabIndex++;
      $text .= '   <select tabindex="' . $sfgTabIndex . '" name="' . $input_name . "[ampm24h]\" $disabled_text>\n";
      $ampm24h_options = array( '', 'AM', 'PM' );
      foreach ( $ampm24h_options as $value ) {
        $text .= "        <option value=\"$value\"";
        if ( $value == $ampm24h ) { $text .= " selected=\"selected\""; }
        $text .= ">$value</option>\n";
      }
      $text .= "  </select>\n";
    }

    if ( $include_timezone ) {
      $sfgTabIndex++;
      $text .= '  <input tabindex="' . $sfgTabIndex . '" name="' . $input_name . '[timezone]" type="text" value="' . $timezone . '" size="2"/ ' . $disabled_text . '>' . "\n";
    }

    return array( $text, null );
  }
}

class SFRadioButtonInput extends SFEnumInput {
  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    global $sfgTabIndex, $sfgFieldNum, $sfgShowOnSelectCalls;

    $span_class = "checkboxSpan";
    if ( array_key_exists( 'class', $other_args ) )
      $span_class .= " " . $other_args['class'];

    $check_set = false;
    $text = "";
    // if it's mandatory, add a span around all the radiobuttons, since
    // some browsers don't support formatting of radiobuttons
    if ( $is_mandatory )
      $text .= '	<span class="mandatoryFieldsSpan">' . "\n";

    if ( ( $possible_values = $other_args['possible_values'] ) == null )
      $possible_values = array();

    // Add a "None" value, unless this is a mandatory field and there's a
    // current value in place (either through a default value or because
    // we're editing an existing page).
    // We place it at the end of the array, instead of the beginning (even
    // though it gets displayed at the beginning) so that the "None" value's
    // ID will match that of the "error message" span, which is created at
    // the end of the process - the validation Javascript requires that the
    // two be matching
    if ( ! $is_mandatory || $cur_value == '' ) {
      $possible_values[] = '';
    }

    // Set $cur_value to be one of the allowed options, if it isn't already -
    // that makes it easier to automatically have one of the radiobuttons
    // be checked at the beginning.
    if ( ! in_array( $cur_value, $possible_values ) ) {
      if ( in_array( '', $possible_values ) )
        $cur_value = '';
      else
        $cur_value = $possible_values[0];
    }

    $enum_input_ids = array();
    $radiobuttons_text = '';
    $none_radiobutton_text = '';
    foreach ( $possible_values as $i => $possible_value ) {
      $sfgTabIndex++;
      $sfgFieldNum++;
      $input_id = "input_$sfgFieldNum";

      // create array $enum_input_ids to associate values with their input IDs,
      // for use in creating the 'show on select' Javascript later
      $enum_input_ids[$possible_value] = $input_id;

      $radiobutton_attrs = array(
        'type' => 'radio',
        'id' => $input_id,
        'tabindex' => $sfgTabIndex,
        'name' => $input_name,
        'value' => $possible_value,
      );
      if ( $cur_value == $possible_value ) {
        $radiobutton_attrs['checked'] = 'checked';
      }
      if ( $is_disabled ) {
        $radiobutton_attrs['disabled'] = 'disabled';
      }
      if ( $possible_value == '' ) // blank/"None" value
        $label = wfMsg( 'sf_formedit_none' );
      elseif ( array_key_exists( 'value_labels', $other_args ) && is_array( $other_args['value_labels'] ) && array_key_exists( $possible_value, $other_args['value_labels'] ) )
        $label = htmlspecialchars( $other_args['value_labels'][$possible_value] );
      else
        $label = $possible_value;

      $radiobutton_text = '	' .
        Xml::element ( 'input', $radiobutton_attrs ) . " $label\n";

      // There's special handling for the "None" radiobutton, if it exists,
      // because it's created last but displayed first - see above.
      if ( $possible_value == '' ) {
        $none_radiobutton_text = $radiobutton_text;
      } else {
        $radiobuttons_text .= $radiobutton_text;
      }
    }
    $text = $none_radiobutton_text . $radiobuttons_text;

    // close span
    if ( $is_mandatory )
      $text .= "	</span>";
    $info_id = "info_$sfgFieldNum";
    $text .= <<<END
	<span id="$info_id" class="errorMessage"></span>

END;

    // Finally, we do the 'show on select' handling.
    if ( array_key_exists( 'show on select', $other_args ) ) {
      foreach ( $other_args['show on select'] as $div_id => $options ) {
        $cur_input_ids = array();
        foreach ( $options as $option ) {
          if ( array_key_exists( $option, $enum_input_ids ) ) {
            $cur_input_ids[] = $enum_input_ids[$option];
          }
        }
	// If there were no matches to existing radiobutton options, escape
	if ( count( $cur_input_ids ) == 0 ) {
		continue;
	}
        $options_str = "['" . implode( "', '", $cur_input_ids ) . "']";
        $js_text = "showIfChecked($options_str, '$div_id');";
        $sfgShowOnSelectCalls[] = $js_text;
        foreach ( $possible_values as $key => $possible_value ) {
          // We need to add each click handler to each radiobutton,
          // because there doesn't seem to be any way for a radiobutton
          // to know that it was unchecked - rather, the newly-checked
          // radiobutton has to handle the change.
          foreach ( $enum_input_ids as $cur_input_id ) {
            if ( in_array( $possible_value, $options ) ) {
              $sfgShowOnSelectCalls[] = "jQuery('#$cur_input_id').click( function() { $js_text } );";
            }
          }
        }
      }
    }

    return array( $text, null );
  }
}

class SFCheckboxInput extends SFFormInput {
  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    global $sfgTabIndex, $sfgFieldNum, $sfgShowOnSelectCalls;

    $className = ( $is_mandatory ) ? "mandatoryField" : "createboxInput";
    if ( array_key_exists( 'class', $other_args ) )
      $className .= " " . $other_args['class'];
    $info_id = "info_$sfgFieldNum";
    $input_id = "input_$sfgFieldNum";
    $disabled_text = ( $is_disabled ) ? "disabled" : "";
    if ( array_key_exists( 'show on select', $other_args ) ) {
      $div_id = key( $other_args['show on select'] );
      $js_text = "showIfChecked(['$input_id'], '$div_id');";
      $sfgShowOnSelectCalls[] = $js_text;
      $sfgShowOnSelectCalls[] = "jQuery('#$input_id').click( function() { $js_text } );";
    }

    // can show up here either as an array or a string, depending on
    // whether it came from user input or a wiki page
    if ( is_array( $cur_value ) ) {
      $checked_str = ( array_key_exists( 'value', $cur_value ) && $cur_value['value'] == 'on' ) ? ' checked="checked"' : "";
    } else {
      // default to false - no need to check if it matches a 'false' word
      $vlc = strtolower( trim( $cur_value ) );
      // manually load SMW's message values, if they weren't loaded before
      wfLoadExtensionMessages( 'SemanticMediaWiki' );
      if ( in_array( $vlc, explode( ',', wfMsgForContent( 'smw_true_words' ) ), TRUE ) ) {
        $checked_str = ' checked="checked"';
      } else {
        $checked_str = "";
      }
    }
    $text = <<<END
	<input name="{$input_name}[is_checkbox]" type="hidden" value="true" />
	<input id="$input_id" name="{$input_name}[value]" type="checkbox" class="$className" tabindex="$sfgTabIndex" $checked_str $disabled_text/>
	<span id="$info_id" class="errorMessage"></span>

END;
    return array( $text, null );
  }
}

class SFCategoryInput extends SFFormInput {
  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    // escape if CategoryTree extension isn't included
    if ( ! function_exists( 'efCategoryTreeParserHook' ) )
      return array( null, null );

    global $sfgTabIndex, $sfgFieldNum;

    $className = ( $is_mandatory ) ? "mandatoryField" : "createboxInput";
    if ( array_key_exists( 'class', $other_args ) )
      $className .= " " . $other_args['class'];
    if ( array_key_exists( 'top category', $other_args ) ) {
      $top_category = $other_args['top category'];
    } else {
      // escape - we can't do anything
      return array( null, null );
    }
    if ( array_key_exists( 'height', $other_args ) ) {
      $height = $other_args['height'];
    } else {
      $height = "100";
    }
    if ( array_key_exists( 'width', $other_args ) ) {
      $width = $other_args['width'];
    } else {
      $width = "500";
    }
    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";

    $text = '<div style="overflow: auto; padding: 5px; border: 1px #aaaaaa solid; max-height: ' . $height . 'px; width: ' . $width . 'px;">';

    // start with an initial "None" value, unless this is a mandatory field
    // and there's a current value in place (either through a default value
    // or because we're editing an existing page)
    if ( ! $is_mandatory || $cur_value == '' ) {
      $input_id = "input_$sfgFieldNum";
      $info_id = "info_$sfgFieldNum";
      $text .= '	<input type="radio" id="' . $input_id . '" tabindex="' . $sfgTabIndex . '" name="' . $input_name . '" value=""';
      if ( ! $cur_value ) {
        $text .= ' checked="checked"';
      }
      $disabled_text = ( $is_disabled ) ? "disabled" : "";
      $text .= " $disabled_text/> <em>" . wfMsg( 'sf_formedit_none' ) . "</em>\n";
    }

    global $wgCategoryTreeMaxDepth;
    $wgCategoryTreeMaxDepth = 10;
    $tree = efCategoryTreeParserHook( $top_category, array( 'mode' => 'categories', 'depth' => 10 ) );

    // capitalize the first letter, if first letters always get capitalized
    global $wgCapitalLinks;
    if ( $wgCapitalLinks ) {
      global $wgContLang;
      $cur_value = $wgContLang->ucfirst( $cur_value );
    }

    $tree = preg_replace( '/(<a class="CategoryTreeLabel.*>)(.*)(<\/a>)/', '<input id="' . $input_id . '" tabindex="' . $sfgTabIndex . '" name="' . $input_name . '" value="$2" type="radio"> $1$2$3', $tree );
    $tree = str_replace( "value=\"$cur_value\"", "value=\"$cur_value\" checked=\"checked\"", $tree );
    // if it's disabled, set all to disabled
    if ( $is_disabled ) {
      $tree = str_replace( 'type="radio"', 'type="radio" disabled', $tree );
    }
    $text .= $tree . '</div>';

    $text .= <<<END
	<span id="$info_id" class="errorMessage"></span>

END;

    return array( $text, null );
  }
}

class SFCategoriesInput {
  static function getText( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
    // escape if CategoryTree extension isn't included
    if ( ! function_exists( 'efCategoryTreeParserHook' ) )
      return array( null, null );

    global $sfgTabIndex, $sfgFieldNum, $wgCapitalLinks;

    $className = ( $is_mandatory ) ? "mandatoryField" : "createboxInput";
    if ( array_key_exists( 'class', $other_args ) )
      $className .= " " . $other_args['class'];
    $input_id = "input_$sfgFieldNum";
    $info_id = "info_$sfgFieldNum";
    $hidden_input_name = $input_name . "[is_list]";
    // get list delimiter - default is comma
    if ( array_key_exists( 'delimiter', $other_args ) ) {
      $delimiter = $other_args['delimiter'];
    } else {
      $delimiter = ",";
    }
    $cur_values = SFUtils::getValuesArray( $cur_value, $delimiter );
    if ( array_key_exists( 'top category', $other_args ) ) {
      $top_category = $other_args['top category'];
    } else {
      // escape - we can't do anything
      return array( null, null );
    }
    if ( array_key_exists( 'height', $other_args ) ) {
      $height = $other_args['height'];
    } else {
      $height = "100";
    }
    if ( array_key_exists( 'width', $other_args ) ) {
      $width = $other_args['width'];
    } else {
      $width = "500";
    }

    global $wgCategoryTreeMaxDepth;
    $wgCategoryTreeMaxDepth = 10;
    $tree = efCategoryTreeParserHook( $top_category, array( 'mode' => 'categories', 'depth' => 10 ) );
    // some string that will hopefully never show up in a category, template
    // or field name
    $dummy_str = 'REPLACE THIS STRING!';
    $tree = preg_replace( '/(<a class="CategoryTreeLabel.*>)(.*)(<\/a>)/', '<input id="' . $input_id . '" tabindex="' . $sfgTabIndex . '" name="' . $input_name . '[' . $dummy_str . ']" value="$2" type="checkbox"> $1$2$3', $tree );
    // replace values one at a time, by an incrementing index -
    // inspired by http://bugs.php.net/bug.php?id=11457
    $i = 0;
    while ( ( $a = strpos( $tree, $dummy_str ) ) > 0 ) {
      $tree = substr( $tree, 0, $a ) . $i++ . substr( $tree, $a + strlen( $dummy_str ) );
    }
    // set all checkboxes matching $cur_values to checked
    foreach ( $cur_values as $value ) {
      // capitalize the first letter, if first letters always get capitalized
      if ( $wgCapitalLinks ) {
        global $wgContLang;
        $value = $wgContLang->ucfirst( $value );
      }

      $tree = str_replace( "value=\"$value\"", "value=\"$value\" checked=\"checked\"", $tree );
    }
    // if it's disabled, set all to disabled
    if ( $is_disabled ) {
      $tree = str_replace( 'type="checkbox"', 'type="checkbox" disabled', $tree );
    }
    $text = '<div style="overflow: auto; padding: 5px; border: 1px #aaaaaa solid; max-height: ' . $height . 'px; width: ' . $width . 'px;">' . $tree . '</div>';

    $text .= <<<END
	<input type="hidden" name="$hidden_input_name" value="1" />

END;

    return array( $text, null );
  }
}
