<?php
/**
 * A special page holding a form that allows the user to create a template
 * with semantic fields.
 *
 * @author Yaron Koren
 */

if ( !defined( 'MEDIAWIKI' ) ) die();

class SFCreateTemplate extends SpecialPage {

	/**
	 * Constructor
	 */
	public function SFCreateTemplate() {
		parent::__construct( 'CreateTemplate' );
		SFUtils::loadMessages();
	}

	public function execute( $query ) {
		$this->setHeaders();
		self::printCreateTemplateForm();
	}

	public static function getAllPropertyNames() {
		$all_properties = array();

		// Set limit on results - we don't want a massive dropdown
		// of properties, if there are a lot of properties in this wiki.
		// getProperties() functions stop requiring a limit
		$options = new SMWRequestOptions();
		$options->limit = 500;
		$used_properties = smwfGetStore()->getPropertiesSpecial( $options );
		
		foreach ( $used_properties as $property ) {
			$all_properties[] = $property[0]->getWikiValue();
		}
		
		$unused_properties = smwfGetStore()->getUnusedPropertiesSpecial( $options );
		
		foreach ( $unused_properties as $property ) {
			$all_properties[] = $property->getWikiValue();
		}

		// sort properties list alphabetically
		sort( $all_properties );
		return $all_properties;
	}

	public static function printPropertiesDropdown( $all_properties, $id, $selected_property ) {
		$selectBody = "<option value=\"\"></option>\n";
		foreach ( $all_properties as $prop_name ) {
			$optionAttrs = array( 'value' => $prop_name );
			if ( $selected_property == $prop_name ) { $optionAttrs['selected'] = 'selected'; }
			$selectBody .= Xml::element( 'option', $optionAttrs, $prop_name ) . "\n";
		}
		return Xml::tags( 'select', array( 'name' => "semantic_property_$id" ), $selectBody ) . "\n";
	}

	public static function printFieldEntryBox( $id, $f, $all_properties ) {
		SFUtils::loadMessages();
		
		$text = "\t" . '<div class="fieldBox">' . "\n";
		$text .= "\t<p>" . wfMsg( 'sf_createtemplate_fieldname' ) . ' ' .
			Xml::element( 'input',
				array( 'size' => '15', 'name' => 'name_' . $id, 'value' => $f->field_name ), null
			) . "\n";
		$text .= "\t" . wfMsg( 'sf_createtemplate_displaylabel' ) . ' ' .
			Xml::element( 'input',
				array( 'size' => '15', 'name' => 'label_' . $id, 'value' => $f->label ), null
			) . "\n";

		$dropdown_html = self::printPropertiesDropdown( $all_properties, $id, $f->semantic_property );
		$text .= "\t" . wfMsg( 'sf_createtemplate_semanticproperty' ) . ' ' . $dropdown_html . "</p>\n";
		$checked_str = ( $f->is_list ) ? " checked" : "";
		$text .= "\t<p>" . '<input type="checkbox" name="is_list_' . $id . '"' .  $checked_str . '> ' . wfMsg( 'sf_createtemplate_fieldislist' ) . "\n";
	
		if ( $id != "new" ) {
			$text .= '	&#160;&#160;<input name="del_' . $id . '" type="submit" value="' . wfMsg( 'sf_createtemplate_deletefield' ) . '">' . "\n";
		}
		
		$text .= <<<END
</p>
</div>

END;
		return $text;
	}

	static function printCreateTemplateForm() {
		global $wgOut, $wgRequest, $wgUser, $sfgScriptPath;

		SFUtils::loadMessages();

		$all_properties = self::getAllPropertyNames();

		$template_name = $wgRequest->getVal( 'template_name' );
		$template_name_error_str = "";
		$category = $wgRequest->getVal( 'category' );
		$cur_id = 1;
		$fields = array();
		// Cycle through the query values, setting the appropriate
		// local variables.
		foreach ( $wgRequest->getValues() as $var => $val ) {
			$var_elements = explode( "_", $var );
			// we only care about query variables of the form "a_b"
			if ( count( $var_elements ) != 2 )
				continue;
			list ( $field_field, $old_id ) = $var_elements;
			if ( $field_field == "name" ) {
				if ( $old_id != "new" || ( $old_id == "new" && $val != "" ) ) {
					if ( $wgRequest->getVal( 'del_' . $old_id ) != '' ) {
						// Do nothing - this field won't get added to the new list
					} else {
						$field = SFTemplateField::create( $val, $wgRequest->getVal( 'label_' . $old_id ) );
						$field->semantic_property = $wgRequest->getVal( 'semantic_property_' . $old_id );
						$field->is_list = $wgRequest->getCheck( 'is_list_' . $old_id );
						$fields[] = $field;
					}
				}
			}
		}
		$aggregating_property = $wgRequest->getVal( 'semantic_property_aggregation' );
		$aggregation_label = $wgRequest->getVal( 'aggregation_label' );
		$template_format = $wgRequest->getVal( 'template_format' );

		$text = "";
		$save_button_text = wfMsg( 'savearticle' );
		$preview_button_text = wfMsg( 'preview' );
		$save_page = $wgRequest->getCheck( 'wpSave' );
		$preview_page = $wgRequest->getCheck( 'wpPreview' );
		if ( $save_page || $preview_page ) {
			# validate template name
			if ( $template_name == '' ) {
				$template_name_error_str = wfMsg( 'sf_blank_error' );
			} else {
				// redirect to wiki interface
				$wgOut->setArticleBodyOnly( true );
				$title = Title::makeTitleSafe( NS_TEMPLATE, $template_name );
				$full_text = SFTemplateField::createTemplateText( $template_name, $fields, $category, $aggregating_property, $aggregation_label, $template_format );
				$text = SFUtils::printRedirectForm( $title, $full_text, "", $save_page, $preview_page, false, false, false, null, null );
				$wgOut->addHTML( $text );
				return;
			}
		}

		$text .= '	<form action="" method="post">' . "\n";

		// set 'title' field, in case there's no URL niceness
		$ct = Title::makeTitleSafe( NS_SPECIAL, 'CreateTemplate' );
		$text .= "\t" . Xml::hidden( 'title', SFUtils::titleURLString( $ct ) ) . "\n";
		$text .= "\t<p>" . wfMsg( 'sf_createtemplate_namelabel' ) . ' <input size="25" name="template_name" value="' . $template_name . '"> <font color="red">' . $template_name_error_str . '</font></p>' . "\n";
		$text .= "\t<p>" . wfMsg( 'sf_createtemplate_categorylabel' ) . ' <input size="25" name="category" value="' . $category . '"></p>' . "\n";
		$text .= "\t<fieldset>\n";
		$text .= "\t" . Xml::element( 'legend', null, wfMsg( 'sf_createtemplate_templatefields' ) ) . "\n";
		$text .= "\t" . Xml::element( 'p', null, wfMsg( 'sf_createtemplate_fieldsdesc' ) ) . "\n";

		foreach ( $fields as $i => $field ) {
			$text .= self::printFieldEntryBox( $i + 1, $field, $all_properties );
		}
		$new_field = new SFTemplateField();
		$text .= self::printFieldEntryBox( "new", $new_field, $all_properties );

		$text .= "\t<p>" . Xml::element( 'input',
			array( 'type' => 'submit', 'value' => wfMsg( 'sf_createtemplate_addfield' ) ), null ) . "</p>\n";
		$text .= "\t</fieldset>\n";
		$text .= "\t<fieldset>\n";
		$text .= "\t" . Xml::element( 'legend', null, wfMsg( 'sf_createtemplate_aggregation' ) ) . "\n";
		$text .= "\t" . Xml::element( 'p', null, wfMsg( 'sf_createtemplate_aggregationdesc' ) ) . "\n";
		$text .= "\t<p>" . wfMsg( 'sf_createtemplate_semanticproperty' ) . ' ' .
			self::printPropertiesDropdown( $all_properties, "aggregation", $aggregating_property ) . "</p>\n";
		$text .= "\t<p>" . wfMsg( 'sf_createtemplate_aggregationlabel' ) . ' ' .
		Xml::element( 'input',
			array( 'size' => '25', 'name' => 'aggregation_label', 'value' => $aggregation_label ), null ) .
			"</p>\n";
		$text .= "\t</fieldset>\n";
		$text .= "\t<p>" . wfMsg( 'sf_createtemplate_outputformat' ) . "\n";
		$text .= "\t" . Xml::element( 'input', array(
			'type' => 'radio',
			'name' => 'template_format',
			'checked' => 'checked',
			'value' => 'standard'
		), null ) . ' ' . wfMsg( 'sf_createtemplate_standardformat' ) . "\n";
		$text .= "\t" . Xml::element( 'input',
			array( 'type' => 'radio', 'name' => 'template_format', 'value' => 'infobox'), null ) .
			' ' . wfMsg( 'sf_createtemplate_infoboxformat' ) . "</p>\n";
		$text .= <<<END
	<div class="editButtons">
	<input type="submit" id="wpSave" name="wpSave" value="$save_button_text" />
	<input type="submit" id="wpPreview" name="wpPreview" value="$preview_button_text" />
	</div>
	</form>

END;
		$sk = $wgUser->getSkin();
		$create_property_link = SFUtils::linkForSpecialPage( $sk, 'CreateProperty' );
		$text .= "\t<br /><hr /><br />\n";
		$text .= "\t" . Xml::tags( 'p', null, $create_property_link . '.' ) . "\n";

		$wgOut->addExtensionStyle( $sfgScriptPath . "/skins/SemanticForms.css" );
		$wgOut->addHTML( $text );
	}

}
