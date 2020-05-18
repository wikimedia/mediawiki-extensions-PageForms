<?php
/**
 * A special page holding a form that allows the user to create a template
 * that potentially stores its data with Cargo or Semantic MediaWiki.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFSpecialPages
 */
class PFCreateTemplate extends SpecialPage {

	public function __construct() {
		parent::__construct( 'CreateTemplate' );
	}

	public function execute( $query ) {
		$this->setHeaders();
		$this->printCreateTemplateForm( $query );
	}

	public static function getAllPropertyNames() {
		$all_properties = [];

		// Set limit on results - we don't want a massive dropdown
		// of properties, if there are a lot of properties in this wiki.
		// getProperties() functions stop requiring a limit
		$options = new SMWRequestOptions();
		$options->limit = 500;
		$used_properties = PFUtils::getSMWStore()->getPropertiesSpecial( $options );
		if ( $used_properties instanceof SMW\SQLStore\PropertiesCollector ) {
			// SMW 1.9+
			$used_properties = $used_properties->runCollector();
		}
		foreach ( $used_properties as $property ) {
			// Skip over properties that are errors. (This
			// shouldn't happen, but it sometimes does.)
			if ( !method_exists( $property[0], 'getKey' ) ) {
				continue;
			}
			$propName = $property[0]->getKey();
			if ( $propName[0] != '_' ) {
				$all_properties[] = str_replace( '_', ' ', $propName );
			}
		}

		$unused_properties = PFUtils::getSMWStore()->getUnusedPropertiesSpecial( $options );
		if ( $unused_properties instanceof SMW\SQLStore\UnusedPropertiesCollector ) {
			// SMW 1.9+
			$unused_properties = $unused_properties->runCollector();
		}
		foreach ( $unused_properties as $property ) {
			// Skip over properties that are errors. (This
			// shouldn't happen, but it sometimes does.)
			if ( !method_exists( $property, 'getKey' ) ) {
				continue;
			}
			$all_properties[] = str_replace( '_', ' ', $property->getKey() );
		}

		// Sort properties list alphabetically, and get unique values
		// (for SQLStore3, getPropertiesSpecial() seems to get unused
		// properties as well).
		sort( $all_properties );
		$all_properties = array_unique( $all_properties );
		return $all_properties;
	}

	public function printPropertiesComboBox( $all_properties, $id, $selected_property = null ) {
		$selectBody = "<option value=\"\"></option>\n";
		foreach ( $all_properties as $prop_name ) {
			$optionAttrs = [ 'value' => $prop_name ];
			if ( $selected_property == $prop_name ) {
				$optionAttrs['selected'] = 'selected';
			}
			$selectBody .= Html::element( 'option', $optionAttrs, $prop_name ) . "\n";
		}
		return Html::rawElement( 'select', [ 'id' => "semantic_property_$id", 'name' => "semantic_property_$id", 'class' => 'pfComboBox' ], $selectBody ) . "\n";
	}

	function printFieldTypeDropdown( $id ) {
		global $wgCargoFieldTypes;

		$selectBody = '';
		foreach ( $wgCargoFieldTypes as $type ) {
			$optionAttrs = [ 'value' => $type ];
			$selectBody .= Html::element( 'option', $optionAttrs, $type ) . "\n";
		}
		return Html::rawElement( 'select', [ 'id' => "field_type_$id", 'name' => "field_type_$id", ], $selectBody ) . "\n";
	}

	public function printFieldEntryBox( $id, $all_properties, $display = true ) {
		$fieldString = $display ? '' : 'id="starterField" style="display: none"';
		$text = "\t<div class=\"fieldBox\" $fieldString>\n";
		$text .= "\t<table style=\"width: 100%;\"><tr><td>\n";
		$text .= "\t<p><label>" . $this->msg( 'pf_createtemplate_fieldname' )->escaped() . ' ' .
			Html::input( 'name_' . $id, null, 'text',
				[ 'size' => '15' ]
			) . "</label>&nbsp;&nbsp;&nbsp;\n";
		$text .= "\t<label>" . $this->msg( 'pf_createtemplate_displaylabel' )->escaped() . ' ' .
			Html::input( 'label_' . $id, null, 'text',
				[ 'size' => '15' ]
			) . "</label>&nbsp;&nbsp;&nbsp;\n";

		if ( defined( 'SMW_VERSION' ) ) {
			$dropdown_html = $this->printPropertiesComboBox( $all_properties, $id );
			$text .= "\t<label>" . $this->msg( 'pf_createtemplate_semanticproperty' )->escaped() . ' ' . $dropdown_html . "</label></p>\n";
		} elseif ( defined( 'CARGO_VERSION' ) ) {
			$dropdown_html = $this->printFieldTypeDropdown( $id );
			$text .= "\t<label class=\"cargo_field_type\">" . $this->msg( 'pf_createproperty_proptype' )->escaped() . ' ' . $dropdown_html . "</label></p>\n";
		}

		$text .= "\t<p>" . '<label><input type="checkbox" name="is_list_' . $id . '" class="isList" /> ' . $this->msg( 'pf_createtemplate_fieldislist' )->escaped() . "</label>&nbsp;&nbsp;&nbsp;\n";
		$text .= "\t" . '<label class="delimiter" style="display: none;">' . $this->msg( 'pf_createtemplate_delimiter' )->escaped() . ' ' .
			Html::input( 'delimiter_' . $id, ',', 'text',
				[ 'size' => '2' ]
			) . "</label>\n";
		$text .= "\t</p>\n";
		if ( !defined( 'SMW_VERSION' ) && defined( 'CARGO_VERSION' ) ) {
			$text .= "\t<p>\n";
			$text .= "<label class=\"is_hierarchy\"><input type=\"checkbox\" name=\"is_hierarchy_" . $id . "\"/> " . $this->msg( 'pf_createtemplate_fieldishierarchy' )->escaped() . "</label>&nbsp;&nbsp;&nbsp;\n";
			$text .= "\t</p>\n";

			$text .= "\t<p>\n";
			$text .= "\t<label class=\"allowed_values_input\">" . $this->msg( 'pf_createproperty_allowedvalsinput' )->escaped();
			$text .= Html::input( 'allowed_values_' . $id, null, 'text',
				[ 'size' => '80' ] ) . "</label>\n";

			$text .= "\t<label class=\"hierarchy_structure_input\" style=\"display: none;\">" . $this->msg( 'pf_createproperty_allowedvalsforhierarchy' )->escaped();
			$text .= '<textarea class="hierarchy_structure" rows="10" cols="20" name="hierarchy_structure_' . $id . '"></textarea></label>';
			$text .= "\t</p>\n";
		}
		$text .= "\t</td><td>\n";
		$text .= "\t" . '<input type="button" value="' . $this->msg( 'pf_createtemplate_deletefield' )->escaped() . '" class="deleteField" />' . "\n";

		$text .= <<<END
</td></tr></table>
</div>

END;
		return $text;
	}

	static function printTemplateStyleButton( $formatStr, $formatMsg, $htmlFieldName, $curSelection ) {
		$attrs = [ 'id' => $formatStr ];
		if ( $formatStr === $curSelection ) {
			$attrs['checked'] = true;
		}
		return "\t" . Html::input( $htmlFieldName, $formatStr, 'radio', $attrs ) .
			' ' . Html::element( 'label', [ 'for' => $formatStr ], wfMessage( $formatMsg )->escaped() ) . "\n";
	}

	static function printTemplateStyleInput( $htmlFieldName, $curSelection = null ) {
		if ( !$curSelection ) {
			$curSelection = 'standard';
		}
		$text = "\t<p>" . wfMessage( 'pf_createtemplate_outputformat' )->escaped() . "\n";
		$text .= self::printTemplateStyleButton( 'standard', 'pf_createtemplate_standardformat', $htmlFieldName, $curSelection );
		$text .= self::printTemplateStyleButton( 'infobox', 'pf_createtemplate_infoboxformat', $htmlFieldName, $curSelection );
		$text .= self::printTemplateStyleButton( 'plain', 'pf_createtemplate_plainformat', $htmlFieldName, $curSelection );
		$text .= self::printTemplateStyleButton( 'sections', 'pf_createtemplate_sectionsformat', $htmlFieldName, $curSelection );
		$text .= "</p>\n";
		return $text;
	}

	function printCreateTemplateForm( $query ) {
		$out = $this->getOutput();
		$req = $this->getRequest();

		if ( $query !== null ) {
			$presetTemplateName = str_replace( '_', ' ', $query );
			$out->setPageTitle( $this->msg( 'pf-createtemplate-with-name', $presetTemplateName )->text() );
			$template_name = $presetTemplateName;
		} else {
			$presetTemplateName = null;
			$template_name = $req->getVal( 'template_name' );
		}

		$out->addModules( [ 'ext.pageforms.main', 'ext.pageforms.PF_CreateTemplate' ] );

		$text = '';
		$save_page = $req->getCheck( 'wpSave' );
		$preview_page = $req->getCheck( 'wpPreview' );
		if ( $save_page || $preview_page ) {
			$validToken = $this->getUser()->matchEditToken( $req->getVal( 'csrf' ), 'CreateTemplate' );
			if ( !$validToken ) {
				$text = "This appears to be a cross-site request forgery; canceling save.";
				$out->addHTML( $text );
				return;
			}

			$fields = [];
			// Cycle through the query values, setting the
			// appropriate local variables.
			foreach ( $req->getValues() as $var => $val ) {
				$var_elements = explode( "_", $var );
				// We only care about query variables of the form "a_b".
				if ( count( $var_elements ) != 2 ) {
					continue;
				}
				list( $field_field, $id ) = $var_elements;
				if ( $field_field == 'name' && $id != 'starter' ) {
					$field = PFTemplateField::create(
						$val,
						$req->getVal( 'label_' . $id ),
						$req->getVal( 'semantic_property_' . $id ),
						$req->getCheck( 'is_list_' . $id ),
						$req->getVal( 'delimiter_' . $id )
					);
					$field->setFieldType( $req->getVal( 'field_type_' . $id ) );

					if ( defined( 'CARGO_VERSION' ) ) {
						if ( $req->getCheck( 'is_hierarchy_' . $id ) ) {
							$hierarchyStructureStr = $req->getVal( 'hierarchy_structure_' . $id );
							$field->setHierarchyStructure( $hierarchyStructureStr );
						} else {
							$allowedValuesStr = $req->getVal( 'allowed_values_' . $id );
							$possibleValues = CargoUtils::smartSplit( ',', $allowedValuesStr );
							$field->setPossibleValues( $possibleValues );
						}
					}

					$fields[] = $field;
				}
			}

			// Assemble the template text, and submit it as a wiki
			// page.
			$out->setArticleBodyOnly( true );
			$title = Title::makeTitleSafe( NS_TEMPLATE, $template_name );
			$category = $req->getVal( 'category' );
			$cargo_table = $req->getVal( 'cargo_table' );
			$aggregating_property = $req->getVal( 'semantic_property_aggregation' );
			$aggregation_label = $req->getVal( 'aggregation_label' );
			$template_format = $req->getVal( 'template_format' );
			$pfTemplate = new PFTemplate( $template_name, $fields );
			$pfTemplate->setCategoryName( $category );
			if ( $req->getBool( 'use_cargo' ) ) {
				$pfTemplate->setCargoTable( $cargo_table );
			}
			$pfTemplate->setAggregatingInfo( $aggregating_property, $aggregation_label );
			$pfTemplate->setFormat( $template_format );
			$full_text = $pfTemplate->createText();

			$text = PFUtils::printRedirectForm( $title, $full_text, "", $save_page, $preview_page, false, false, false, null, null );
			$out->addHTML( $text );
			return;
		}

		$text .= '	<form id="createTemplateForm" action="" method="post">' . "\n";
		if ( $presetTemplateName === null ) {
			// Set 'title' field, in case there's no URL niceness.
			$text .= Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() ) . "\n";
			$text .= "\t<p id=\"template_name_p\">" .
				$this->msg( 'pf_createtemplate_namelabel' )->escaped() .
				' <input size="25" id="template_name" name="template_name" /></p>' . "\n";
		}
		$text .= "\t<p>" . $this->msg( 'pf_createtemplate_categorylabel' )->escaped() . ' <input size="25" name="category" /></p>' . "\n";
		if ( !defined( 'SMW_VERSION' ) && defined( 'CARGO_VERSION' ) ) {
			$text .= "\t<p><label>" . Html::check( 'use_cargo', true, [ 'id' => 'use_cargo' ] ) .
				' ' . $this->msg( 'pf_createtemplate_usecargo' )->escaped() . "</label></p>\n";
			$text .= "\t<p id=\"cargo_table_input\"><label>" .
				$this->msg( 'pf_createtemplate_cargotablelabel' )->escaped() .
				' <input id="cargo_table" size="25" name="cargo_table" /></label></p>' . "\n";
		}

		$text .= "\t<fieldset>\n";
		$text .= "\t" . Html::element( 'legend', null, $this->msg( 'pf_createtemplate_templatefields' )->text() ) . "\n";
		$text .= "\t" . Html::element( 'p', null, $this->msg( 'pf_createtemplate_fieldsdesc' )->text() ) . "\n";

		if ( defined( 'SMW_VERSION' ) ) {
			$all_properties = self::getAllPropertyNames();
		} else {
			$all_properties = [];
		}
		$text .= '<div id="fieldsList">' . "\n";
		$text .= $this->printFieldEntryBox( "1", $all_properties );
		$text .= $this->printFieldEntryBox( "starter", $all_properties, false );
		$text .= "</div>\n";

		$add_field_button = Html::input(
			null,
			$this->msg( 'pf_createtemplate_addfield' )->text(),
			'button',
			[ 'class' => "createTemplateAddField" ]
		);
		$text .= Html::rawElement( 'p', null, $add_field_button ) . "\n";
		$text .= "\t</fieldset>\n";

		if ( defined( 'SMW_VERSION' ) ) {
			$text .= "\t<fieldset>\n";
			$text .= "\t" . Html::element( 'legend', null, $this->msg( 'pf_createtemplate_aggregation' )->text() ) . "\n";
			$text .= "\t" . Html::element( 'p', null, $this->msg( 'pf_createtemplate_aggregationdesc' )->text() ) . "\n";
			$text .= "\t<p>" . $this->msg( 'pf_createtemplate_semanticproperty' )->escaped() . ' ' .
				$this->printPropertiesComboBox( $all_properties, "aggregation" ) . "</p>\n";
			$text .= "\t<p>" . $this->msg( 'pf_createtemplate_aggregationlabel' )->escaped() . ' ' .
				Html::input( 'aggregation_label', null, 'text',
					[ 'size' => '25' ] ) .
				"</p>\n";
			$text .= "\t</fieldset>\n";
		}

		$text .= self::printTemplateStyleInput( 'template_format' );

		$text .= "\t" . Html::hidden( 'csrf', $this->getUser()->getEditToken( 'CreateTemplate' ) ) . "\n";

		$save_button = Html::input( 'wpSave', $this->msg( 'savearticle' )->escaped(), 'submit', [ 'id' => 'wpSave' ] );
		$preview_button = Html::input( 'wpPreview', $this->msg( 'preview' )->escaped(), 'submit', [ 'id' => 'wpPreview' ] );
		$text .= Html::rawElement( 'div', [ 'class' => 'editButtons' ], $save_button . "\n" . $preview_button );

		$text .= '</form>';

		$out->addHTML( $text );
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
