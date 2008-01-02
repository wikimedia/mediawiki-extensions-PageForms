<?php
/**
 * A special page holding a form that allows the user to create a semantic
 * property (an attribute or relation).
 *
 * @author Yaron Koren
 */

include_once $sfgIP . "/includes/SF_TemplateField.inc";

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once( "$IP/includes/SpecialPage.php" );

SpecialPage::addPage( new SpecialPage('CreateProperty','',true,'doSpecialCreateProperty',false) );

function createPropertyText($property_type, $allowed_values_str) {
  global $smwgContLang;

  $smw_version = SMW_VERSION;
  if ($smw_version{0} == '0') {
    $namespace_labels = $smwgContLang->getNamespaceArray();
  } else {
    $namespace_labels = $smwgContLang->getNamespaces();
  }
  if ($property_type == $namespace_labels[SMW_NS_RELATION]) {
    $text = wfMsgForContent('sf_property_isrelation');
  } else {
    global $smwgContLang;
    $specprops = $smwgContLang->getSpecialPropertiesArray();
    if ($smw_version{0} == '0') {
      $type_tag = "[[" . $specprops[SMW_SP_HAS_TYPE] . "::" .
        $namespace_labels[SMW_NS_TYPE] . ":$property_type|$property_type]]";
      $text = wfMsgForContent('sf_property_isattribute', $type_tag);
    } else {
      $type_tag = "[[" . $specprops[SMW_SP_HAS_TYPE] .
        "::$property_type|$property_type]]";
      $text = wfMsgForContent('sf_property_isproperty', $type_tag);
    }
    if ($allowed_values_str != '') {
      $text .= "\n\n" . wfMsgForContent('sf_property_allowedvals');
      // replace the comma substitution character that has no chance of
      // being included in the values list - namely, the ASCII beep
      global $sfgListSeparator;
      $allowed_values_str = str_replace("\\$sfgListSeparator", "\a", $allowed_values_str);
      $allowed_values_array = explode($sfgListSeparator, $allowed_values_str);
      foreach ($allowed_values_array as $i => $value) {
        // replace beep with comma, trim
        $value = str_replace("\a", $sfgListSeparator, trim($value));
        $text .= "\n* [[" . $specprops[SMW_SP_POSSIBLE_VALUE] . ":=$value]]";
      }
    }
  }
  return $text;
}

function doSpecialCreateProperty() {
  global $wgOut, $wgRequest, $sfgScriptPath;
  global $smwgContLang;

  # cycle through the query values, setting the appropriate local variables
  $property_name = $wgRequest->getVal('property_name');
  $property_type = $wgRequest->getVal('property_type');
  $allowed_values = $wgRequest->getVal('values');

  $save_button_text = wfMsg('savearticle');
  $preview_button_text = wfMsg('preview');

  $property_name_error_str = '';
  $save_page = $wgRequest->getCheck('wpSave');
  $preview_page = $wgRequest->getCheck('wpPreview');
  if ($save_page || $preview_page) {
    # validate property name
    if ($property_name == '') {
      $property_name_error_str = wfMsg('sf_blank_error');
    } else {
      # redirect to wiki interface
      $smw_version = SMW_VERSION;
      if ($smw_version{0} == '0') {
        $namespace_labels = $smwgContLang->getNamespaceArray();
        $namespace = ($property_type == $namespace_labels[SMW_NS_RELATION]) ? SMW_NS_RELATION : SMW_NS_ATTRIBUTE;
      } else {
        $namespace = SMW_NS_PROPERTY;
      }
      $title = Title::newFromText($property_name, $namespace);
      $full_text = createPropertyText($property_type, $allowed_values);
      // HTML-encode
      $full_text = str_replace('"', '&quot;', $full_text);
      $text = sffPrintRedirectForm($title, $full_text, "", $save_page, $preview_page, false, false, false);
      $wgOut->addHTML($text);
      return;
    }
  }

  $smw_version = SMW_VERSION;
  if ($smw_version{0} == '0') {
    $all_properties = getSemanticProperties_0_7();
    $namespace_labels = $smwgContLang->getNamespaceArray();
    $datatype_labels = array(
      $namespace_labels[SMW_NS_RELATION],
      $smwgContLang->getDatatypeLabel('smw_string'),
      $smwgContLang->getDatatypeLabel('smw_int'),
      $smwgContLang->getDatatypeLabel('smw_float'),
      $smwgContLang->getDatatypeLabel('smw_datetime'),
      $smwgContLang->getDatatypeLabel('smw_bool'),
      $smwgContLang->getDatatypeLabel('smw_enum'),
      $smwgContLang->getDatatypeLabel('smw_url'),
      $smwgContLang->getDatatypeLabel('smw_uri'),
      $smwgContLang->getDatatypeLabel('smw_email'),
      $smwgContLang->getDatatypeLabel('smw_temperature'),
      $smwgContLang->getDatatypeLabel('smw_geocoordinate')
    );
    $enum_str = $smwgContLang->getDatatypeLabel('smw_enum');
  } else {
    $all_properties = getSemanticProperties_1_0();
    $datatype_labels = $smwgContLang->getDatatypeLabels();
  }

  $javascript_text =<<<END
function toggleAllowedValues() {
	var values_div = document.getElementById("allowed_values");

END;
  // add toggling of 'possible values' entry only if this is SMW 0.7 or
  // lower; otherwise, it should always appear
  if ($smw_version{0} == '0') {
    $javascript_text .=<<<END
        var prop_dropdown = document.getElementById("property_dropdown");
        if (prop_dropdown.value == "$enum_str") {
		values_div.style.display = "block";
	} else {
		values_div.style.display = "none";
        }

END;
  }
  $javascript_text .= "}\n";

  // set 'title' as hidden field, in case there's no URL niceness
  global $wgContLang;
  $mw_namespace_labels = $wgContLang->getNamespaces();
  $special_namespace = $mw_namespace_labels[NS_SPECIAL];
  $name_label = wfMsg('sf_createproperty_propname');
  $type_label = wfMsg('sf_createproperty_proptype');
  $text =<<<END
	<form action="" method="get">
	<input type="hidden" name="title" value="$special_namespace:CreateProperty">
	<p>$name_label <input size="25" name="property_name" value="">
	<span style="color: red;">$property_name_error_str</span>
	$type_label
	<select id="property_dropdown" name="property_type" onChange="toggleAllowedValues();">
END;
  foreach ($datatype_labels as $label) {
    $text .= "	<option>$label</option>\n";
  }

  $values_input_display = ($smw_version{0} == '0') ? "display: none;" : "";
  $values_input = wfMsg('sf_createproperty_allowedvalsinput');
  $text .=<<<END
	</select>
	<div id="allowed_values" style="$values_input_display margin-bottom: 15px;">
	<p>$values_input</p>
	<p><input size="35" name="values" value=""></p>
	</div>
	<div class="editButtons">
	<input id="wpSave" type="submit" name="wpSave" value="$save_button_text">
	<input id="wpPreview" type="submit" name="wpPreview" value="$preview_button_text">
	</div>

END;

  $text .= "	</form>\n";

  $wgOut->addLink( array(
    'rel' => 'stylesheet',
    'type' => 'text/css',
    'media' => "screen, projection",
    'href' => $sfgScriptPath . "/skins/SF_main.css"
  ));
  $wgOut->addScript('<script type="text/javascript">' . $javascript_text . '</script>');
  $wgOut->addHTML($text);
}
