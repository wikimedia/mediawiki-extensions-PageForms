<?php
/**
 *
 * @file
 * @ingroup SF
 */

/**
 * This class is distinct from SFTemplateField in that it represents a template
 * field defined in a form definition - it contains a SFTemplateField object
 * within it (the $template_field variable), along with the other properties
 * for that field that are set within the form
 * @ingroup SF
 */
class SFFormField {
	private $mNum;
	public $template_field;
	private $mInputType;
	private $mIsMandatory;
	private $mIsHidden;
	private $mIsRestricted;
	private $mPossibleValues;
	private $mIsList;
	// the following fields are not set by the form-creation page
	// (though they could be)
	private $mDefaultValue;
	private $mPreloadPage;
	private $mHoldsTemplate;
	private $mIsUploadable;
	private $mFieldArgs;
	private $mDescriptionArgs;
	// somewhat of a hack - these two fields are for a field in a specific
	// representation of a form, not the form definition; ideally these
	// should be contained in a third 'field' class, called something like
	// SFFormInstanceField, that holds these fields plus an instance of
	// SFFormField. Too much work?
	private $mInputName;
	private $mIsDisabled;

	static function create( $num, $template_field ) {
		$f = new SFFormField();
		$f->mNum = $num;
		$f->template_field = $template_field;
		$f->mInputType = null;
		$f->mIsMandatory = false;
		$f->mIsHidden = false;
		$f->mIsRestricted = false;
		$f->mIsUploadable = false;
		$f->mPossibleValues = null;
		$f->mFieldArgs = array();
		$f->mDescriptionArgs = array();
		return $f;
	}

	public function getTemplateField() {
		return $this->template_field;
	}

	public function setTemplateField( $templateField ) {
		$this->template_field = $templateField;
	}

	public function getInputType() {
		return $this->mInputType;
	}

	public function setInputType( $inputType ) {
		$this->mInputType = $inputType;
	}

	public function hasFieldArg( $key ) {
		return array_key_exists( $key, $this->mFieldArgs );
	}

	public function getFieldArgs() {
		return $this->mFieldArgs;
	}

	public function getFieldArg( $key ) {
		return $this->mFieldArgs[$key];
	}

	public function setFieldArg( $key, $value ) {
		$this->mFieldArgs[$key] = $value;
	}

	public function getDefaultValue() {
		return $this->mDefaultValue;
	}

	public function isMandatory() {
		return $this->mIsMandatory;
	}

	public function setIsMandatory( $isMandatory ) {
		$this->mIsMandatory = $isMandatory;
	}

	public function isHidden() {
		return $this->mIsHidden;
	}

	public function setIsHidden( $isHidden ) {
		$this->mIsHidden = $isHidden;
	}

	public function isRestricted() {
		return $this->mIsRestricted;
	}

	public function setIsRestricted( $isRestricted ) {
		$this->mIsRestricted = $isRestricted;
	}

	public function holdsTemplate() {
		return $this->mHoldsTemplate;
	}

	public function isList() {
		return $this->mIsList;
	}

	public function getPossibleValues() {
		if ( $this->mPossibleValues != null ) {
			return $this->mPossibleValues;
		} else {
			return $this->template_field->getPossibleValues();
		}
	}

	public function getInputName() {
		return $this->mInputName;
	}

	public function isDisabled() {
		return $this->mIsDisabled;
	}

	public function setDescriptionArg( $key, $value ) {
		$this->mDescriptionArgs[$key] = $value;
	}

	static function newFromFormFieldTag( $tag_components, $template_in_form, $form_is_disabled ) {
		global $wgParser, $wgUser;

		$field_name = trim( $tag_components[1] );
		// See if this field matches one of the fields defined for this
		// template - if it is, use all available information about
		// that field; if it's not, either include it in the form or
		// not, depending on whether the template has a 'strict'
		// setting in the form definition.
		$the_field = null;
		$all_fields = $template_in_form->getAllFields();
		foreach ( $all_fields as $cur_field ) {
			if ( $field_name == $cur_field->getFieldName() ) {
				$the_field = $cur_field;
				break;
			}
		}
		if ( $the_field == null ) {
			if ( $template_in_form->strictParsing() ) {
				$dummy_ff = new SFFormField();
				$dummy_ff->template_field = new SFTemplateField();
				$dummy_ff->mIsList = false;
				return $dummy_ff;
			}
			$the_field = SFTemplateField::create( $field_name, null );
		}

		// Create an SFFormField object, containing this field as well
		// as settings from the form definition file.
		$f = new SFFormField();
		$f->template_field = $the_field;
		$f->mFieldArgs = array();

		$semantic_property = null;
		$cargo_table = $cargo_field = null;
		$show_on_select = array();
		$fullFieldName = $template_in_form->getTemplateName() . '[' . $field_name . ']';
		// Cycle through the other components.
		for ( $i = 2; $i < count( $tag_components ); $i++ ) {
			$component = trim( $tag_components[$i] );

			if ( $component == 'mandatory' ) {
				$f->mIsMandatory = true;
			} elseif ( $component == 'hidden' ) {
				$f->mIsHidden = true;
			} elseif ( $component == 'restricted' ) {
				$f->mIsRestricted = ( ! $wgUser || ! $wgUser->isAllowed( 'editrestrictedfields' ) );
			} elseif ( $component == 'list' ) {
				$f->mIsList = true;
			} elseif ( $component == 'unique' ) {
				$f->mFieldArgs['unique'] = true;
			} elseif ( $component == 'edittools' ) { // free text only
				$f->mFieldArgs['edittools'] = true;
			}

			$sub_components = array_map( 'trim', explode( '=', $component, 2 ) );

			if ( count( $sub_components ) == 1 ) {
				// add handling for single-value params, for custom input types
				$f->mFieldArgs[$sub_components[0]] = true;

				if ( $component == 'holds template' ) {
					$f->mIsHidden = true;
					$f->mHoldsTemplate = true;
				}
			} elseif ( count( $sub_components ) == 2 ) {
				// First, set each value as its own entry in $this->mFieldArgs.
				$f->mFieldArgs[$sub_components[0]] = $sub_components[1];

				// Then, do all special handling.
				if ( $sub_components[0] == 'input type' ) {
					$f->mInputType = $sub_components[1];
				} elseif ( $sub_components[0] == 'default' ) {
					// We call recursivePreprocess() here,
					// and not the more standard
					// recursiveTagParse(), so that
					// wikitext in the value, and bare URLs,
					// will not get turned into HTML.
					$f->mDefaultValue = $wgParser->recursivePreprocess( $sub_components[1] );
				} elseif ( $sub_components[0] == 'preload' ) {
					$f->mPreloadPage = $sub_components[1];
				} elseif ( $sub_components[0] == 'show on select' ) {
					// html_entity_decode() is needed to turn '&gt;' to '>'
					$vals = explode( ';', html_entity_decode( $sub_components[1] ) );
					foreach ( $vals as $val ) {
						$val = trim( $val );
						if ( empty( $val ) ) {
							continue;
						}
						$option_div_pair = explode( '=>', $val, 2 );
						if ( count( $option_div_pair ) > 1 ) {
							$option = $option_div_pair[0];
							$div_id = $option_div_pair[1];
							if ( array_key_exists( $div_id, $show_on_select ) ) {
								$show_on_select[$div_id][] = $option;
							} else {
								$show_on_select[$div_id] = array( $option );
							}
						} else {
							$show_on_select[$val] = array();
						}
					}
				} elseif ( $sub_components[0] == 'autocomplete on property' ) {
					$f->mFieldArgs['autocomplete field type'] = 'property';
					$f->mFieldArgs['autocompletion source'] = $sub_components[1];
				} elseif ( $sub_components[0] == 'autocomplete on category' ) {
					$f->mFieldArgs['autocomplete field type'] = 'category';
					$f->mFieldArgs['autocompletion source'] = $sub_components[1];
				} elseif ( $sub_components[0] == 'autocomplete on concept' ) {
					$f->mFieldArgs['autocomplete field type'] = 'concept';
					$f->mFieldArgs['autocompletion source'] = $sub_components[1];
				} elseif ( $sub_components[0] == 'autocomplete on namespace' ) {
					$f->mFieldArgs['autocomplete field type'] = 'namespace';
					$autocompletion_source = $sub_components[1];
					// Special handling for "main" (blank)
					// namespace.
					if ( $autocompletion_source == "" ) {
						$autocompletion_source = "main";
					}
					$f->mFieldArgs['autocompletion source'] = $autocompletion_source;
				} elseif ( $sub_components[0] == 'autocomplete from url' ) {
					$f->mFieldArgs['autocomplete field type'] = 'external_url';
					$f->mFieldArgs['autocompletion source'] = $sub_components[1];
					// 'external' autocompletion is always done remotely, i.e. via API
					$f->mFieldArgs['remote autocompletion'] = true;
				} elseif ( $sub_components[0] == 'values' ) {
					// Handle this one only after
					// 'delimiter' has also been set.
					$values = $wgParser->recursiveTagParse( $sub_components[1] );
				} elseif ( $sub_components[0] == 'values from property' ) {
					$propertyName = $sub_components[1];
					$f->mPossibleValues = SFUtils::getAllValuesForProperty( $propertyName );
				} elseif ( $sub_components[0] == 'values from query' ) {
					$pages = SFUtils::getAllPagesForQuery( $sub_components[1] );
					foreach ( $pages as $page ) {
						$page_name_for_values = $page->getDbKey();
						$f->mPossibleValues[] = $page_name_for_values;
					}
				} elseif ( $sub_components[0] == 'values from category' ) {
					$category_name = ucfirst( $sub_components[1] );
					$f->mPossibleValues = SFUtils::getAllPagesForCategory( $category_name, 10 );
				} elseif ( $sub_components[0] == 'values from concept' ) {
					$f->mPossibleValues = SFUtils::getAllPagesForConcept( $sub_components[1] );
				} elseif ( $sub_components[0] == 'values from namespace' ) {
					$f->mPossibleValues = SFUtils::getAllPagesForNamespace( $sub_components[1] );
				} elseif ( $sub_components[0] == 'values dependent on' ) {
					global $sfgDependentFields;
					$sfgDependentFields[] = array( $sub_components[1], $fullFieldName );
				} elseif ( $sub_components[0] == 'unique for category' ) {
					$f->mFieldArgs['unique'] = true;
					$f->mFieldArgs['unique_for_category'] = $sub_components[1];
				} elseif ( $sub_components[0] == 'unique for namespace' ) {
					$f->mFieldArgs['unique'] = true;
					$f->mFieldArgs['unique_for_namespace'] = $sub_components[1];
				} elseif ( $sub_components[0] == 'unique for concept' ) {
					$f->mFieldArgs['unique'] = true;
					$f->mFieldArgs['unique_for_concept'] = $sub_components[1];
				} elseif ( $sub_components[0] == 'property' ) {
					$semantic_property = $sub_components[1];
				} elseif ( $sub_components[0] == 'cargo table' ) {
					$cargo_table = $sub_components[1];
				} elseif ( $sub_components[0] == 'cargo field' ) {
					$cargo_field = $sub_components[1];
				} elseif ( $sub_components[0] == 'default filename' ) {
					$default_filename = str_replace( '&lt;page name&gt;', $page_name, $sub_components[1] );
					// Parse value, so default filename can
					// include parser functions.
					$default_filename = $wgParser->recursiveTagParse( $default_filename );
					$f->mFieldArgs['default filename'] = $default_filename;
				} elseif ( $sub_components[0] == 'restricted' ) {
					$f->mIsRestricted = !array_intersect(
						$wgUser->getEffectiveGroups(), array_map( 'trim', explode( ',', $sub_components[1] ) )
					);
				}
			}
		} // end for


		if ( !array_key_exists( 'delimiter', $f->mFieldArgs ) ) {
			$f->mFieldArgs['delimiter'] = ",";
		}
		$delimiter = $f->mFieldArgs['delimiter'];

		// If the 'values' parameter was set, separate it based on the
		// 'delimiter' parameter, if any.
		if ( ! empty( $values ) ) {
			// Remove whitespaces, and un-escape characters
			$valuesArray = array_map( 'trim', explode( $delimiter, $values ) );
			$f->mPossibleValues = array_map( 'htmlspecialchars_decode', $valuesArray );
		}

		// If we're using Cargo, there's no equivalent for "values from
		// property" - instead, we just always get the values if a 
		// field and table have been specified.
		if ( is_null( $f->mPossibleValues ) && defined( 'CARGO_VERSION' ) && $cargo_table != null && $cargo_field != null ) {
			// We only want the non-null values. Ideally this could
			// be done by calling getValuesForCargoField() with
			// an "IS NOT NULL" clause, but unfortunately that fails
			// for array/list fields.
			// Instead of getting involved with all that, we'll just
			// remove the null/blank values afterward.
			$cargoValues = SFUtils::getAllValuesForCargoField( $cargo_table, $cargo_field );
			$f->mPossibleValues = array_filter( $cargoValues, 'strlen' );
		}

		if ( !is_null( $f->mPossibleValues ) ) {
			if ( array_key_exists( 'mapping template', $f->mFieldArgs ) ) {
				$f->mPossibleValues = SFUtils::getLabelsFromTemplate( $f->mPossibleValues, $f->mFieldArgs['mapping template'] );
			} elseif ( array_key_exists( 'mapping property', $f->mFieldArgs ) ) {
				$f->mPossibleValues = SFUtils::getLabelsFromProperty( $f->mPossibleValues, $f->mFieldArgs['mapping property'] );
			} elseif ( array_key_exists( 'mapping cargo table', $f->mFieldArgs ) &&
				array_key_exists( 'mapping cargo field', $f->mFieldArgs ) ) {
				$f->mPossibleValues = SFUtils::getLabelsFromCargoField( $f->mPossibleValues, $f->mFieldArgs['mapping cargo table'], $f->mFieldArgs['mapping cargo field'] );
			}
		}
		// Backwards compatibility.
		if ( $f->mInputType == 'datetime with timezone' ) {
			$f->mInputType = 'datetime';
			$f->mFieldArgs['include timezone'] = true;
		} elseif ( $f->mInputType == 'text' || $f->mInputType == 'textarea' ) {
			// Backwards compatibility.
			$f->mFieldArgs['no autocomplete'] = true;
		}
		if ( $template_in_form->allowsMultiple() ) {
			$f->mFieldArgs['part_of_multiple'] = true;
		}
		if ( count( $show_on_select ) > 0 ) {
			$f->mFieldArgs['show on select'] = $show_on_select;
		}

		// Disable this field if either the whole form is disabled, or
		// it's a restricted field and user doesn't have sysop privileges.
		$f->mIsDisabled = ( $form_is_disabled || $f->mIsRestricted );

		// Do some data storage specific to the Semantic MediaWiki and
		// Cargo extensions.
		if ( defined( 'SMW_VERSION' ) ) {
			// If a property was set in the form definition,
			// overwrite whatever is set in the template field -
			// this is somewhat of a hack, since parameters set in
			// the form definition are meant to go into the
			// SFFormField object, not the SFTemplateField object
			// it contains;
			// it seemed like too much work, though, to create an
			// SFFormField::setSemanticProperty() function just for
			// this call.
			if ( !is_null( $semantic_property ) ) {
				$f->template_field->setSemanticProperty( $semantic_property );
			} else {
				$semantic_property = $f->template_field->getSemanticProperty();
			}
			if ( !is_null( $semantic_property ) ) {
				global $sfgFieldProperties;
				$sfgFieldProperties[$fullFieldName] = $semantic_property;
			}
		}
		if ( defined( 'CARGO_VERSION' ) ) {
			if ( $cargo_table != null && $cargo_field != null ) {
				$f->template_field->setCargoFieldData( $cargo_table, $cargo_field );
			}
			$fullCargoField = $f->template_field->getFullCargoField();
			if ( !is_null( $fullCargoField ) ) {
				global $sfgCargoFields;
				$sfgCargoFields[$fullFieldName] = $fullCargoField;
			}
		}

                if ( $template_in_form->getTemplateName() == null || $template_in_form->getTemplateName() === '' ) {
                        $f->mInputName = $field_name;
                } elseif ( $template_in_form->allowsMultiple() ) {
                        // 'num' will get replaced by an actual index, either in PHP
                        // or in Javascript, later on
                        $f->mInputName = $template_in_form->getTemplateName() . '[num][' . $field_name . ']';
                        $f->setFieldArg( 'origName', $template_in_form->getTemplateName() . '[' . $field_name . ']' );
                } else {
                        $f->mInputName = $template_in_form->getTemplateName() . '[' . $field_name . ']';
                }

		return $f;
	}

	function getCurrentValue( $template_instance_query_values, $form_submitted, $source_is_page ) {
		// Get the value from the request, if
		// it's there, and if it's not an array.
		$cur_value = null;
		$field_name = $this->template_field->getFieldName();
		$delimiter = $this->mFieldArgs['delimiter'];
		$escaped_field_name = str_replace( "'", "\'", $field_name );
		if ( isset( $template_instance_query_values ) &&
			$template_instance_query_values != null &&
			is_array( $template_instance_query_values ) ) {

			// If the field name contains an apostrophe, the array
			// sometimes has the apostrophe escaped, and sometimes
			// not. For now, just check for both versions.
			// @TODO - figure this out.
			$field_query_val = null;
			if ( array_key_exists( $escaped_field_name, $template_instance_query_values ) ) {
				$field_query_val = $template_instance_query_values[$escaped_field_name];
			} elseif ( array_key_exists( $field_name, $template_instance_query_values ) ) {
				$field_query_val = $template_instance_query_values[$field_name];
			}

			if ( $form_submitted && $field_query_val != '' ) {
				$map_field = false;
				if ( array_key_exists( 'map_field', $template_instance_query_values ) &&
					array_key_exists( $field_name, $template_instance_query_values['map_field'] ) ) {
					$map_field = true;
				}
				if ( is_array( $field_query_val ) ) {
					$cur_values = array();
					if ( $map_field && !is_null( $this->mPossibleValues ) ) {
						$cur_values = array();
						foreach ( $field_query_val as $key => $val ) {
							$val = trim( $val );
							if ( $key === 'is_list' ) {
								$cur_values[$key] = $val;
							} else {
								$cur_values[] = SFUtils::labelToValue( $val, $this->mPossibleValues );
							}
						}
					} else {
						foreach ( $field_query_val as $key => $val ) {
							$cur_values[$key] = $val;
						}
					}
					return SFFormPrinter::getStringFromPassedInArray( $cur_values, $delimiter );
				} else {
					$field_query_val = trim( $field_query_val );
					if ( $map_field && !is_null( $this->mPossibleValues ) ) {
						// this should be replaced with an input type neutral way of
						// figuring out if this scalar input type is a list
						if ( $this->mInputType == "tokens" ) {
							$is_list = true;
						}
						if ( $is_list ) {
							$cur_values = array_map( 'trim', explode( $delimiter, $field_query_val ) );
							foreach ( $cur_values as $key => $value ) {
								$cur_values[$key] = SFUtils::labelToValue( $value, $this->mPossibleValues );
							}
							return implode( $delimiter, $cur_values );
						}
						return SFUtils::labelToValue( $field_query_val, $this->mPossibleValues );
					}
					return $field_query_val;
				}
			}
			if ( !$form_submitted && $field_query_val != '' ) {
				if ( is_array( $field_query_val ) ) {
					return SFFormPrinter::getStringFromPassedInArray( $field_query_val, $delimiter );
				}
				return $field_query_val;
			}
		}

		if ( !$source_is_page && empty( $cur_value ) && !$form_submitted ) {
			if ( !is_null( $this->mDefaultValue ) ) {
				// Set to the default value specified in the form, if it's there.
				return $this->mDefaultValue;
			} elseif ( $this->mPreloadPage ) {
				return SFFormUtils::getPreloadedText( $this->mPreloadPage );
			}
		}

		return $cur_value; // null
	}

	public function additionalHTMLForInput( $cur_value, $field_name, $template_name ) {
		$text = '';

		// Add a field just after the hidden field, within the HTML, to
		// locate where the multiple-templates HTML, stored in
		// $multipleTemplateString, should be inserted.
		if ( $this->mHoldsTemplate ) {
			$text .= SFFormPrinter::makePlaceholderInFormHTML( SFFormPrinter::placeholderFormat( $template_name, $field_name ) );
		}

		// If this field is disabled, add a hidden field holding
		// the value of this field, because disabled inputs for some
		// reason don't submit their value.
		if ( $this->mIsDisabled ) {
			if ( $field_name == 'free text' || $field_name == '<freetext>' ) {
				$text .= Html::hidden( 'sf_free_text', '!free_text!' );
			} else {
				if ( is_array( $cur_value ) ) {
					$delimiter = $this->mFieldArgs['delimiter'];
					$text .= Html::hidden( $this->mInputName, implode( $delimiter, $cur_value ) );
				} else {
					$text .= Html::hidden( $this->mInputName, $cur_value );
				}
			}
		}

		if ( $this->hasFieldArg( 'mapping template' ) ||
			$this->hasFieldArg( 'mapping property' ) ||
			( $this->hasFieldArg( 'mapping cargo table' ) &&
			$this->hasFieldArg( 'mapping cargo field' ) ) ) {
			if ( $this->hasFieldArg( 'part_of_multiple' ) ) {
				$text .= Html::hidden( $template_name . '[num][map_field][' . $field_name . ']', 'true' );
			} else {
				$text .= Html::hidden( $template_name . '[map_field][' . $field_name . ']', 'true' );
			}
		}

		if ( $this->hasFieldArg( 'unique' ) ) {
			$semantic_property = $this->template_field->getSemanticProperty();
			if ( $semantic_property != null ) {
				$text .= Html::hidden( 'input_' . $sfgFieldNum . '_unique_property', $semantic_property );
			}
			$fullCargoField = $this->template_field->getFullCargoField();
			if ( $fullCargoField != null ) {
				// It's inefficient to get these values via
				// text parsing, but oh well.
				list( $cargo_table, $cargo_field ) = explode( '|', $fullCargoField, 2 );
				$text .= Html::hidden( 'input_' . $sfgFieldNum . '_unique_cargo_table', $cargo_table );
				$text .= Html::hidden( 'input_' . $sfgFieldNum . '_unique_cargo_field', $cargo_field );
			}
			if ( $this->hasFieldArg( 'unique_for_category' ) ) {
				$text .= Html::hidden( 'input_' . $sfgFieldNum . '_unique_for_category', $this->getFieldArg( 'unique_for_category' ) );
			}
			if ( $this->hasFieldArg( 'unique_for_namespace' ) ) {
				$text .= Html::hidden( 'input_' . $sfgFieldNum . '_unique_for_namespace', $this->getFieldArg( 'unique_for_namespace' ) );
			}
			if ( $this->hasFieldArg( 'unique_for_concept' ) ) {
				$text .= Html::hidden( 'input_' . $sfgFieldNum . '_unique_for_concept', $this->getFieldArg( 'unique_for_concept' ) );
			}
		}
		return $text;
	}

	function inputTypeDropdownHTML( $field_form_text, $default_input_type, $possible_input_types, $cur_input_type ) {
		if ( !is_null( $default_input_type ) ) {
			array_unshift( $possible_input_types, $default_input_type );
		}
		// create the dropdown HTML for a list of possible input types
		$dropdownHTML = "";
		foreach ( $possible_input_types as $i => $input_type ) {
			if ( $i == 0 ) {
				$dropdownHTML .= "	<option value=\".$input_type\">$input_type " .
					wfMessage( 'sf_createform_inputtypedefault' )->escaped() . "</option>\n";
			} else {
				$selected_str = ( $cur_input_type == $input_type ) ? "selected" : "";
				$dropdownHTML .= "	<option value=\"$input_type\" $selected_str>$input_type</option>\n";
			}
		}
		$hidden_text = wfMessage( 'sf_createform_hidden' )->escaped();
		$selected_str = ( $cur_input_type == 'hidden' ) ? "selected" : "";
		// @todo FIXME: Contains hard coded parentheses.
		$dropdownHTML .= "	<option value=\"hidden\" $selected_str>($hidden_text)</option>\n";
		$text = "\t" . Html::rawElement( 'select',
			array(
				'class' => 'inputTypeSelector',
				'name' => 'input_type_' . $field_form_text,
				'formfieldid' => $field_form_text
			), $dropdownHTML ) . "\n";
		return $text;
	}

	function creationHTML( $template_num ) {
		$field_form_text = $template_num . "_" . $this->mNum;
		$template_field = $this->template_field;
		$text = Html::element( 'h3', null, wfMessage( 'sf_createform_field' )->text() . " " . $template_field->getFieldName() ) . "\n";
		// TODO - remove this probably-unnecessary check?
		if ( !defined( 'SMW_VERSION' ) || $template_field->getSemanticProperty() == "" ) {
			// Print nothing if there's no semantic property.
		} elseif ( $template_field->getPropertyType() == "" ) {
			$prop_link_text = SFUtils::linkText( SMW_NS_PROPERTY, $template_field->getSemanticProperty() );
			$text .= wfMessage( 'sf_createform_fieldpropunknowntype', $prop_link_text )->parseAsBlock() . "\n";
		} else {
			if ( $template_field->isList() ) {
				$propDisplayMsg = 'sf_createform_fieldproplist';
			} else {
				$propDisplayMsg = 'sf_createform_fieldprop';
			}
			$prop_link_text = SFUtils::linkText( SMW_NS_PROPERTY, $template_field->getSemanticProperty() );

			// Get the display label for this property type.
			global $smwgContLang;
			$propertyTypeStr = '';
			if ( $smwgContLang != null ) {
				$datatypeLabels = $smwgContLang->getDatatypeLabels();
				$datatypeLabels['enumeration'] = 'enumeration';

				$propTypeID = $template_field->getPropertyType();

				// Special handling for SMW 1.9
				if ( $propTypeID == '_str' && !array_key_exists( '_str', $datatypeLabels ) ) {
					$propTypeID = '_txt';
				}
				$propertyTypeStr = $datatypeLabels[$propTypeID];
			}
			$text .= Html::rawElement( 'p', null, wfMessage( $propDisplayMsg, $prop_link_text, $propertyTypeStr )->parse() ) . "\n";
		}
		// If it's not a semantic field - don't add any text.
		$form_label_text = wfMessage( 'sf_createform_formlabel' )->escaped();
		$form_label_input = Html::input(
			'label_' . $field_form_text,
			$template_field->getLabel(),
			'text',
			array( 'size' => 20 )
		);
		$input_type_text = wfMessage( 'sf_createform_inputtype' )->escaped();
		$text .= <<<END
	<div class="formField">
	<p>$form_label_text $form_label_input
	&#160; $input_type_text

END;
		global $sfgFormPrinter;
		if ( !is_null( $template_field->getPropertyType() ) ) {
			$default_input_type = $sfgFormPrinter->getDefaultInputTypeSMW( $template_field->isList(), $template_field->getPropertyType() );
			$possible_input_types = $sfgFormPrinter->getPossibleInputTypesSMW( $template_field->isList(), $template_field->getPropertyType() );
		} elseif ( !is_null( $template_field->getFieldType() ) ) {
			$default_input_type = $sfgFormPrinter->getDefaultInputTypeCargo( $template_field->isList(), $template_field->getFieldType() );
			$possible_input_types = $sfgFormPrinter->getPossibleInputTypesCargo( $template_field->isList(), $template_field->getFieldType() );
		} else {
			// Most likely, template uses neither SMW nor Cargo.
			$default_input_type = null;
			$possible_input_types = array();
		}

		if ( $default_input_type == null && count( $possible_input_types ) == 0 ) {
			$default_input_type = null;
			$possible_input_types = $sfgFormPrinter->getAllInputTypes();
		}
		$text .= $this->inputTypeDropdownHTML( $field_form_text, $default_input_type, $possible_input_types, $template_field->getInputType() );

		if ( !is_null( $template_field->getInputType() ) ) {
			$cur_input_type = $template_field->getInputType();
		} elseif ( !is_null( $default_input_type ) ) {
			$cur_input_type = $default_input_type;
		} else {
			$cur_input_type = $possible_input_types[0];
		}

		global $wgRequest;
		$paramValues = array();
		foreach ( $wgRequest->getValues() as $key => $value ) {
			if ( ( $pos = strpos( $key, '_' . $field_form_text ) ) != false ) {
				$paramName = substr( $key, 0, $pos );
				// Spaces got replaced by underlines in the
				// query.
				$paramName = str_replace( '_', ' ', $paramName );
				$paramValues[$paramName] = $value;
			}
		}

		$other_param_text = wfMessage( 'sf_createform_otherparameters' )->escaped();
		$text .= "<fieldset class=\"sfCollapsibleFieldset\"><legend>$other_param_text</legend>\n";
		$text .= Html::rawElement( 'div', array( 'class' => 'otherInputParams' ),
			SFCreateForm::showInputTypeOptions( $cur_input_type, $field_form_text, $paramValues ) ) . "\n";
		$text .= "</fieldset>\n";
		$text .= <<<END
	</p>
	</div>
	<hr>

END;
		return $text;
	}

	// for now, HTML of an individual field depends on whether or not it's
	// part of multiple-instance template; this may change if handling of
	// such templates in form definitions gets more sophisticated
	function createMarkup( $part_of_multiple, $is_last_field_in_template ) {
		$text = "";
		$descPlaceholder = "";
		$textBeforeField = "";

		if ( array_key_exists( "Description", $this->mDescriptionArgs ) ) {
			$fieldDesc = $this->mDescriptionArgs['Description'];
			if ( $fieldDesc != '' ) {
				if ( isset( $this->mDescriptionArgs['DescriptionTooltipMode'] ) ) {
					// The wikitext we use for tooltips
					// depends on which other extensions
					// are installed.
					if ( defined( 'SMW_VERSION' ) ) {
						// Semantic MediaWiki
						$descPlaceholder = " {{#info:$fieldDesc}}";
					} elseif ( class_exists( 'SimpleTooltipParserFunction' ) ) {
						// SimpleTooltip
						$descPlaceholder = " {{#tip-info:$fieldDesc}}";
					} else {
						// Don't make it a tooltip.
						$descPlaceholder = '<br><p class="sfFieldDescription" style="font-size:0.7em; color:gray;">' . $fieldDesc . '</p>';
					}
				} else {
					$descPlaceholder = '<br><p class="sfFieldDescription" style="font-size:0.7em; color:gray;">' . $fieldDesc . '</p>';
				}
			}
		}

		if ( array_key_exists( "TextBeforeField", $this->mDescriptionArgs ) ) {
			$textBeforeField = $this->mDescriptionArgs['TextBeforeField'];
		}

		$fieldLabel = $this->template_field->getLabel();
		if ( $textBeforeField != '' ) {
			$fieldLabel = $textBeforeField . ' ' . $fieldLabel;
		}

		if ( $part_of_multiple ) {
			$text .= "'''$fieldLabel:''' $descPlaceholder";
		} else {
			$text .= "! $fieldLabel: $descPlaceholder\n";
		}

		if ( ! $part_of_multiple ) { $text .= "| "; }
		$text .= "{{{field|" . $this->template_field->getFieldName();
		// TODO - why is there an input type field in both the form
		// field and the template field? One of them should probably
		// be removed.
		if ( $this->mIsHidden ) {
			$text .= "|hidden";
		} elseif ( !is_null( $this->getInputType() ) ) {
			$text .= "|input type=" . $this->getInputType();
		} elseif ( $this->template_field->getInputType() != '' ) {
			$text .= "|input type=" . $this->template_field->getInputType();
		}
		foreach ( $this->mFieldArgs as $arg => $value ) {
			if ( $value === true ) {
				$text .= "|$arg";
			} elseif ( $arg === 'uploadable' ) {
				// Are there similar value-less arguments
				// that need to be handled here?
				$text .= "|$arg";
			} else {
				$text .= "|$arg=$value";
			}
		}
		if ( $this->mIsMandatory ) {
			$text .= "|mandatory";
		} elseif ( $this->mIsRestricted ) {
			$text .= "|restricted";
		}
		$text .= "}}}\n";
		if ( $part_of_multiple ) {
			$text .= "\n";
		} elseif ( ! $is_last_field_in_template ) {
			$text .= "|-\n";
		}
		return $text;
	}

	function getArgumentsForInputCallSMW( &$other_args ) {
		if ( $this->template_field->getSemanticProperty() !== '' &&
			! array_key_exists( 'semantic_property', $other_args ) ) {
			$other_args['semantic_property'] = $this->template_field->getSemanticProperty();
			$other_args['property_type'] = $this->template_field->getPropertyType();
		}
		// If autocompletion hasn't already been hardcoded in the form,
		// and it's a property of type page, or a property of another
		// type with 'autocomplete' specified, set the necessary
		// parameters.
		if ( ! array_key_exists( 'autocompletion source', $other_args ) ) {
			if ( $this->template_field->getPropertyType() == '_wpg' ) {
				$other_args['autocompletion source'] = $this->template_field->getSemanticProperty();
				$other_args['autocomplete field type'] = 'property';
			} elseif ( array_key_exists( 'autocomplete', $other_args ) || array_key_exists( 'remote autocompletion', $other_args ) ) {
				$other_args['autocompletion source'] = $this->template_field->getSemanticProperty();
				$other_args['autocomplete field type'] = 'property';
			}
		}
	}

	function getArgumentsForInputCallCargo( &$other_args ) {
		$fullCargoField = $this->template_field->getFullCargoField();
		if ( $fullCargoField !== null &&
			! array_key_exists( 'full_cargo_field', $other_args ) ) {
			$other_args['full_cargo_field'] = $fullCargoField;
		}

		if ( ! array_key_exists( 'autocompletion source', $other_args ) ) {
			if ( $this->template_field->getFieldType() == 'Page' || array_key_exists( 'autocomplete', $other_args ) || array_key_exists( 'remote autocompletion', $other_args ) ) {
				$other_args['autocompletion source'] = $this->template_field->getFullCargoField();
				$other_args['autocomplete field type'] = 'cargo field';
			}
		}
	}

	/**
	 * Since Semantic Forms uses a hook system for the functions that
	 * create HTML inputs, most arguments are contained in the "$other_args"
	 * array - create this array, using the attributes of this form
	 * field and the template field it corresponds to, if any.
	 */
	function getArgumentsForInputCall( $default_args = null ) {
		// start with the arguments array already defined
		$other_args = $this->mFieldArgs;
		// a value defined for the form field should always supersede
		// the coresponding value for the template field
		if ( $this->mPossibleValues != null ) {
			$other_args['possible_values'] = $this->mPossibleValues;
		} else {
			$other_args['possible_values'] = $this->template_field->getPossibleValues();
			$other_args['value_labels'] = $this->template_field->getValueLabels();
		}
		$other_args['is_list'] = ( $this->mIsList || $this->template_field->isList() );

		// Now add some extension-specific arguments to the input call.
		if ( defined( 'CARGO_VERSION' ) ) {
			$this->getArgumentsForInputCallCargo( $other_args );
		}
		if ( defined( 'SMW_VERSION' ) ) {
			$this->getArgumentsForInputCallSMW( $other_args );
		}

		// Now merge in the default values set by SFFormPrinter, if
		// there were any - put the default values first, so that if
		// there's a conflict they'll be overridden.
		if ( $default_args != null ) {
			$other_args = array_merge( $default_args, $other_args );
		}

		global $wgParser;
		foreach ( $other_args as $argname => $argvalue ) {
			if ( is_string( $argvalue ) ) {
				$other_args[$argname] =
					$wgParser->recursiveTagParse( $argvalue );
			}
		}

		return $other_args;
	}
}
