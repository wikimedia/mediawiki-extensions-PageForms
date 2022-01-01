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
		$out = $this->getOutput();
		$out->enableOOUI();
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

	public static function printPropertiesComboBox( $all_properties, $id, $selected_property = null ) {
		$optionAttrs = [];
		$value = '';
		foreach ( $all_properties as $prop_name ) {
			array_push( $optionAttrs, [ 'data' => $prop_name, 'label' => $prop_name ] );
			if ( $selected_property == $prop_name ) {
				$value = $prop_name;
			}
		}
		return new OOUI\DropdownInputWidget( [
			'options' => $optionAttrs,
			'id' => "semantic_property_$id",
			'name' => "semantic_property_$id",
			'classes' => [ 'pfComboBox' ]
		] );
	}

	function printFieldTypeDropdown( $id ) {
		global $wgCargoFieldTypes;

		$selectBody = '';
		$optionAttrs = [];
		foreach ( $wgCargoFieldTypes as $type ) {
			array_push( $optionAttrs, [ 'data' => $type, 'label' => $type ] );
		}
		return new OOUI\DropdownInputWidget( [
			'options' => $optionAttrs,
			'id' => "field_type_$id",
			'name' => "field_type_$id",
			'classes' => [ 'pfFieldTypeDropdown' ],
			'value' => ''
		] );
	}

	public function printFieldEntryBox( $id, $all_properties, $display = true ) {
		$items = [
			new OOUI\LabelWidget( [
				'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createtemplate_fieldname' )->escaped() )
			] ),
			new OOUI\TextInputWidget( [
				'name' => 'name_' . $id,
				'classes' => [ 'pfFieldName' ]
			] ),
			new OOUI\LabelWidget( [
				'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createtemplate_displaylabel' )->escaped() )
			] ),
			new OOUI\TextInputWidget( [
				'name' => 'label_' . $id,
				'classes' => [ 'pfDisplayLabel' ]
			] )
		];
		$fieldString = $display ? '' : 'id="starterField" style="display: none"';
		$text = "\t<div class=\"fieldBox\" $fieldString>\n";
		$text .= "\t<table style=\"width: 100%;\"><tr><td class=\"instanceRearranger\"></td>";
		$text .= "<td style=\"padding-left:10px\">\n";

		if ( defined( 'SMW_VERSION' ) ) {
			$dropdown_html = self::printPropertiesComboBox( $all_properties, $id );
			array_push(
				$items,
				new OOUI\LabelWidget( [
					'label' => new OOUi\HtmlSnippet( $this->msg( 'pf_createproperty_propname' )->escaped() )
				] ),
				new OOUI\TextInputWidget( [
					'name' => 'property_name_' . $id,
					'classes' => [ 'pfPropertyName' ]
				] ),
				new OOUI\LabelWidget( [
					'label' => new OOUi\HtmlSnippet( $this->msg( 'pf_createtemplate_semanticproperty' )->escaped() )
				] ),
				$dropdown_html
			);
		} elseif ( defined( 'CARGO_VERSION' ) ) {
			$dropdown_html = $this->printFieldTypeDropdown( $id );
			array_push(
				$items,
				new OOUI\LabelWidget( [
					'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createproperty_proptype' )->escaped() ),
					'classes' => [ 'cargo_field_type' ]
				] ),
				$dropdown_html
			);
		}
		$fieldBoxFirstRow = new OOUI\HorizontalLayout( [
			'items' => $items
		] );
		$text .= $fieldBoxFirstRow;
		$fieldBoxSecondRow = new OOUI\HorizontalLayout( [
			'items' => [
				new OOUI\CheckboxInputWidget( [
					'name' => "is_list_$id",
					'classes' => [ 'isList' ]
				] ),
				new OOUI\LabelWidget( [
					'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createtemplate_fieldislist' )->escaped() )
				] ),
				new OOUI\LabelWidget( [
					'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createtemplate_delimiter' )->escaped() ),
					'classes' => [ 'delimiter' ]
				] ),
				new OOUI\TextInputWidget( [
					'name' => "delimiter_$id",
					'value' => ',',
					'classes' => [ 'delimiter' ]
				] )
			]
		] );
		$text .= $fieldBoxSecondRow;
		if ( !defined( 'SMW_VERSION' ) && defined( 'CARGO_VERSION' ) ) {
			$fieldBoxThirdRow = new OOUI\HorizontalLayout( [
				'items' => [
					new OOUI\CheckboxInputWidget( [
						'name' => "is_hierarchy_$id",
						'classes' => [ 'is_hierarchy' ]
					] ),
					new OOUI\LabelWidget( [
						'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createtemplate_fieldishierarchy' )->escaped() ),
					] )
				]
			] );
			$text .= $fieldBoxThirdRow;
			$text .= new OOUI\LabelWidget( [
				'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createproperty_allowedvalsinput' )->escaped() ),
				'classes' => [ 'allowed_values_input' ]
			] );
			$text .= new OOUI\TextInputWidget( [
				'name' => "allowed_values_$id",
				'classes' => [ 'allowed_values_input' ]
			] );
			$text .= new OOUI\LabelWidget( [
				'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createproperty_allowedvalsforhierarchy' )->escaped() ),
				'classes' => [ 'hierarchy_structure_input' ]
			] );
			$text .= new OOUI\MultilineTextInputWidget( [
				'classes' => [ 'hierarchy_structure' ],
				'name' => "hierarchy_structure_$id",
				'rows' => 10,
			] );
		}
		$text .= "\t</td>\n";
		$addAboveButton = Html::element( 'a', [ 'class' => "addAboveButton", 'title' => $this->msg( 'pf_createtemplate_addanotherabove' )->text() ] );
		$removeButton = Html::element( 'a', [ 'class' => "removeButton", 'title' => $this->msg( 'pf_createtemplate_deletefield' )->text() ] );

		$text .= <<<END
			<td class="instanceAddAbove">$addAboveButton</td>
			<td class="instanceRemove">$removeButton</td>
		</tr>
	</table>
</div>

END;
		return $text;
	}

	static function printTemplateStyleButton( $formatStr, $formatMsg, $htmlFieldName, $curSelection ) {
		$attrs = [ 'id' => $formatStr ];
		if ( $formatStr === $curSelection ) {
			$attrs['selected'] = true;
		}
		$attrs[ 'name' ] = $htmlFieldName;
		$attrs[ 'value' ] = $formatStr;
		$radioButton = new OOUI\RadioInputWidget(
			$attrs
		);
		return $radioButton . Html::element( 'label', [ 'for' => $formatStr ], wfMessage( $formatMsg )->escaped() ) . "&nbsp;&nbsp;&nbsp;\n";
	}

	static function printTemplateStyleInput( $htmlFieldName, $curSelection = null ) {
		if ( !$curSelection ) {
			$curSelection = 'standard';
		}
		$text = "<br>\t<p>" . wfMessage( 'pf_createtemplate_outputformat' )->escaped() . "\n";
		$text .= self::printTemplateStyleButton( 'standard', 'pf_createtemplate_standardformat', $htmlFieldName, $curSelection );
		$text .= self::printTemplateStyleButton( 'infobox', 'pf_createtemplate_infoboxformat', $htmlFieldName, $curSelection );
		$text .= self::printTemplateStyleButton( 'plain', 'pf_createtemplate_plainformat', $htmlFieldName, $curSelection );
		$text .= self::printTemplateStyleButton( 'sections', 'pf_createtemplate_sectionsformat', $htmlFieldName, $curSelection );
		$text .= "</p>\n<br>";
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

			$use_cargo = $req->getBool( 'use_cargo' );
			$cargo_table = $req->getVal( 'cargo_table' );

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

					if ( $use_cargo ) {
						$cargo_field = str_replace( ' ', '_', $val );
						$field->setCargoFieldData( $cargo_table, $cargo_field );
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
			$aggregating_property = $req->getVal( 'semantic_property_aggregation' );
			$aggregation_label = $req->getVal( 'aggregation_label' );
			$template_format = $req->getVal( 'template_format' );
			$pfTemplate = new PFTemplate( $template_name, $fields );
			$pfTemplate->setCategoryName( $category );
			if ( $use_cargo ) {
				$pfTemplate->setCargoTable( $cargo_table );
			}
			$pfTemplate->setAggregatingInfo( $aggregating_property, $aggregation_label );
			$pfTemplate->setFormat( $template_format );
			$full_text = $pfTemplate->createText();

			$text = PFUtils::printRedirectForm( $title, $full_text, "", $save_page, $this->getUser() );
			$out->addHTML( $text );
			return;
		}

		// to avoid the FOUC (flash of unstyled content) hide the form until the css loads
		$text .= '	<form id="createTemplateForm" action="" method="post" style="display:none">' . "\n";
		if ( $presetTemplateName === null ) {
			// Set 'title' field, in case there's no URL niceness.
			$text .= Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() ) . "\n";
			$templateNameRow = new OOUI\HorizontalLayout( [
				'items' => [
					new OOUI\LabelWidget( [
						'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createtemplate_namelabel' )->escaped() )
					] ),
					new OOUI\TextInputWidget( [
						'id' => 'template_name',
						'name' => 'template_name',
					] ),
					new OOUI\MessageWidget( [
						'type' => 'error',
						'inline' => true,
						'label' => $this->msg( 'pf_blank_error' )->escaped(),
						'classes' => [ 'pfTemplateNameBlankError' ]
					] )
				]
			] );
			$text .= $templateNameRow;
		}
		$categoryNameRow = new OOUI\HorizontalLayout( [
			'items' => [
				new OOUI\LabelWidget( [
					'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createtemplate_categorylabel' )->escaped() )
				] ),
				new OOUI\TextInputWidget( [
					'id' => 'category',
					'name' => 'category',
				] )
			]
		] );
		$text .= $categoryNameRow;
		if ( !defined( 'SMW_VERSION' ) && defined( 'CARGO_VERSION' ) ) {
			$text .= "\t<p><label id='cargo_toggle'>" . Html::hidden( 'use_cargo', true ) .
				' ' . $this->msg( 'pf_createtemplate_usecargo' )->escaped() . "</label></p>\n";

			$cargoTableNameRow = new OOUI\HorizontalLayout( [
				'items' => [
					new OOUI\LabelWidget( [
						'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createtemplate_cargotablelabel' )->escaped() )
					] ),
					new OOUI\TextInputWidget( [
						'id' => 'cargo_table',
						'name' => 'cargo_table',
					] ),
					new OOUI\MessageWidget( [
						'type' => 'error',
						'inline' => true,
						'label' => $this->msg( 'pf_blank_error' )->escaped(),
						'classes' => [ 'pfCargoTableNameBlankError' ]
					] )
				],
				'id' => 'cargo_table_input'
			] );
			$text .= $cargoTableNameRow;
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

		$add_field_button = new OOUI\ButtonWidget( [
			'label' => $this->msg( 'pf_createtemplate_addfield' )->text(),
			'classes' => [ 'createTemplateAddField' ],
			'icon' => 'add'
		] );
		$text .= new OOUI\FieldLayout( $add_field_button ) . "\n";
		$text .= "\t</fieldset>\n";

		if ( defined( 'SMW_VERSION' ) ) {
			$text .= "\t<fieldset>\n";
			$text .= "\t" . Html::element( 'legend', null, $this->msg( 'pf_createtemplate_aggregation' )->text() ) . "\n";
			$text .= "\t" . Html::element( 'p', null, $this->msg( 'pf_createtemplate_aggregationdesc' )->text() ) . "\n";
			$dropdownHtml = $this->printPropertiesComboBox( $all_properties, "aggregation" );
			$text .= new OOUI\HorizontalLayout( [
				'items' => [
					new OOUI\LabelWidget( [
						'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createtemplate_semanticproperty' )->escaped() )
					] ),
					$dropdownHtml
				]
			] );
			$text .= new OOUI\HorizontalLayout( [
				'items' => [
					new OOUI\LabelWidget( [
						'label' => new OOUI\HtmlSnippet( $this->msg( 'pf_createtemplate_aggregationlabel' )->escaped() )
					] ),
					new OOUI\TextInputWidget( [
						'name' => 'aggregation_label',
						'classes' => [ 'pfAggregationLabel' ]
					] )
				]
			] );
			$text .= "\t</fieldset>\n";
		}

		$text .= self::printTemplateStyleInput( 'template_format' );

		$text .= "\t" . Html::hidden( 'csrf', $this->getUser()->getEditToken( 'CreateTemplate' ) ) . "\n";

		$save_button = new OOUI\ButtonInputWidget( [
			'type' => 'submit',
			'name' => 'wpSave',
			'id' => 'wpSave',
			'label' => $this->msg( 'savearticle' )->escaped(),
			'flags' => [ 'primary', 'progressive' ]
		] );
		$preview_button = new OOUI\ButtonInputWidget( [
			'type' => 'submit',
			'name' => 'wpPreview',
			'id' => 'wpPreview',
			'label' => $this->msg( 'preview' )->escaped(),
			'flags' => [ 'progressive' ]
		] );
		$text .= Html::rawElement( 'div', [ 'class' => 'editButtons' ], $save_button . "\n" . $preview_button );

		$text .= '</form>';

		$out->addHTML( $text );
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
