<?php
/**
 *
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;

/**
 * This class is distinct from PFTemplateField in that it represents a template
 * field defined in a form definition - it contains an PFTemplateField object
 * within it (the $template_field variable), along with the other properties
 * for that field that are set within the form.
 * @ingroup PF
 */
class PFFormField {

	/**
	 * @var PFTemplateField
	 */
	public $template_field;
	private $mInputType;
	private $mIsMandatory;
	private $mIsHidden;
	private $mIsRestricted;
	private $mPossibleValues;
	private $mUseDisplayTitle;
	private $mIsList;
	/**
	 * The following fields are not set by the form-creation page
	 * (though they could be).
	 */
	private $mDefaultValue;
	private $mPreloadPage;
	private $mHoldsTemplate;
	private $mIsUploadable;
	private $mFieldArgs;
	private $mDescriptionArgs;
	private $mLabel;
	private $mLabelMsg;
	/**
	 * somewhat of a hack - these two fields are for a field in a specific
	 * representation of a form, not the form definition; ideally these
	 * should be contained in a third 'field' class, called something like
	 * PFFormInstanceField, which holds these fields plus an instance of
	 * PFFormField. Too much work?
	 */
	private $mInputName;
	private $mIsDisabled;

	/**
	 * @param PFTemplateField $template_field
	 *
	 * @return self
	 */
	static function create( PFTemplateField $template_field ) {
		$f = new PFFormField();
		$f->template_field = $template_field;
		$f->mInputType = null;
		$f->mIsMandatory = false;
		$f->mIsHidden = false;
		$f->mIsRestricted = false;
		$f->mIsUploadable = false;
		$f->mPossibleValues = null;
		$f->mUseDisplayTitle = false;
		$f->mFieldArgs = [];
		$f->mDescriptionArgs = [];
		return $f;
	}

	/**
	 * @return PFTemplateField
	 */
	public function getTemplateField() {
		return $this->template_field;
	}

	/**
	 * @param PFTemplateField $templateField
	 */
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

	public function getUseDisplayTitle() {
		return $this->mUseDisplayTitle;
	}

	public function getInputName() {
		return $this->mInputName;
	}

	public function getLabel() {
		return $this->mLabel;
	}

	public function getLabelMsg() {
		return $this->mLabelMsg;
	}

	public function isDisabled() {
		return $this->mIsDisabled;
	}

	public function setDescriptionArg( $key, $value ) {
		$this->mDescriptionArgs[$key] = $value;
	}

	static function newFromFormFieldTag(
		$tag_components,
		$template,
		$template_in_form,
		$form_is_disabled,
		User $user
	) {
		global $wgPageFormsEmbeddedTemplates;

		$parser = PFUtils::getParser();

		$f = new PFFormField();
		$f->mFieldArgs = [];

		$field_name = trim( $tag_components[1] );
		$template_name = $template_in_form->getTemplateName();

		// See if this field matches one of the fields defined for this
		// template - if it does, use all available information about
		// that field; if it doesn't, either include it in the form or
		// not, depending on whether the template has a 'strict'
		// setting in the form definition.
		$template_field = $template->getFieldNamed( $field_name );

		if ( $template_field != null ) {
			$f->template_field = $template_field;
		} else {
			if ( $template_in_form->strictParsing() ) {
				$f->template_field = new PFTemplateField();
				$f->mIsList = false;
				return $f;
			}
			$f->template_field = PFTemplateField::create( $field_name, null );
		}

		$embeddedTemplate = $f->template_field->getHoldsTemplate();
		if ( $embeddedTemplate != '' ) {
			$f->mIsHidden = true;
			$f->mHoldsTemplate = true;
			// Store this information so that the embedded/"held"
			// template - which is hopefully after this one in the
			// form definition - can be handled correctly. In forms,
			// both the embedding field and the embedded template are
			// specified as such, but in templates (i.e., with
			// #template_params), it's only the embedding field.
			$wgPageFormsEmbeddedTemplates[$embeddedTemplate] = [ $template_name, $field_name ];
		}

		$semantic_property = null;
		$cargo_table = $cargo_field = null;
		$show_on_select = [];
		$fullFieldName = $template_name . '[' . $field_name . ']';
		$values = $valuesSourceType = $valuesSource = null;

		// Cycle through the other components.
		for ( $i = 2; $i < count( $tag_components ); $i++ ) {
			$component = trim( $tag_components[$i] );

			if ( $component == 'mandatory' ) {
				$f->mIsMandatory = true;
			} elseif ( $component == 'hidden' ) {
				$f->mIsHidden = true;
			} elseif ( $component == 'restricted' ) {
				$f->mIsRestricted = ( !$user || !$user->isAllowed( 'editrestrictedfields' ) );
			} elseif ( $component == 'list' ) {
				$f->mIsList = true;
			} elseif ( $component == 'unique' ) {
				$f->mFieldArgs['unique'] = true;
			} elseif ( $component == 'edittools' ) {
				// free text only
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
					$f->mDefaultValue = $parser->recursivePreprocess( $sub_components[1] );
				} elseif ( $sub_components[0] == 'preload' ) {
					$f->mPreloadPage = $sub_components[1];
				} elseif ( $sub_components[0] == 'label' ) {
					$f->mLabel = $sub_components[1];
				} elseif ( $sub_components[0] == 'label msg' ) {
					$f->mLabelMsg = $sub_components[1];
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
							$option = trim( $parser->recursiveTagParse( $option_div_pair[0] ) );
							$div_id = $option_div_pair[1];
							if ( array_key_exists( $div_id, $show_on_select ) ) {
								$show_on_select[$div_id][] = $option;
							} else {
								$show_on_select[$div_id] = [ $option ];
							}
						} else {
							$show_on_select[$val] = [];
						}
					}
				} elseif ( $sub_components[0] == 'values' ) {
					// Handle this one only after
					// 'delimiter' has also been set.
					$values = $parser->recursiveTagParse( $sub_components[1] );
				} elseif ( $sub_components[0] == 'values from property' ) {
					$propertyName = $sub_components[1];
					$f->mPossibleValues = PFValuesUtils::getAllValuesForProperty( $propertyName );
				} elseif ( $sub_components[0] == 'values from query' ) {
					$valuesSourceType = 'query';
					$valuesSource = $sub_components[1];
				} elseif ( $sub_components[0] == 'values from category' ) {
					$valuesSource = $parser->recursiveTagParse( $sub_components[1] );
					global $wgCapitalLinks;
					if ( $wgCapitalLinks ) {
						$valuesSource = ucfirst( $valuesSource );
					}
					$valuesSourceType = 'category';
				} elseif ( $sub_components[0] == 'values from concept' ) {
					$valuesSourceType = 'concept';
					$valuesSource = $parser->recursiveTagParse( $sub_components[1] );
				} elseif ( $sub_components[0] == 'values from namespace' ) {
					$valuesSourceType = 'namespace';
					$valuesSource = $parser->recursiveTagParse( $sub_components[1] );
				} elseif ( $sub_components[0] == 'values dependent on' ) {
					global $wgPageFormsDependentFields;
					$wgPageFormsDependentFields[] = [ $sub_components[1], $fullFieldName ];
				} elseif ( $sub_components[0] == 'unique for category' ) {
					$f->mFieldArgs['unique'] = true;
					$f->mFieldArgs['unique_for_category'] = $parser->recursiveTagParse( $sub_components[1] );
				} elseif ( $sub_components[0] == 'unique for namespace' ) {
					$f->mFieldArgs['unique'] = true;
					$f->mFieldArgs['unique_for_namespace'] = $parser->recursiveTagParse( $sub_components[1] );
				} elseif ( $sub_components[0] == 'unique for concept' ) {
					$f->mFieldArgs['unique'] = true;
					$f->mFieldArgs['unique_for_concept'] = $parser->recursiveTagParse( $sub_components[1] );
				} elseif ( $sub_components[0] == 'property' ) {
					$semantic_property = $sub_components[1];
				} elseif ( $sub_components[0] == 'cargo table' ) {
					$cargo_table = $sub_components[1];
				} elseif ( $sub_components[0] == 'cargo field' ) {
					$cargo_field = $sub_components[1];
				} elseif ( $sub_components[0] == 'default filename' ) {
					global $wgTitle;
					$page_name = $wgTitle->getText();
					if ( $wgTitle->isSpecialPage() ) {
						// If it's of the form
						// Special:FormEdit/form/target,
						// get just the target.
						$pageNameComponents = explode( '/', $page_name, 3 );
						if ( count( $pageNameComponents ) == 3 ) {
							$page_name = $pageNameComponents[2];
						}
					}
					$default_filename = str_replace( '<page name>', $page_name, $sub_components[1] );
					// Parse value, so default filename can
					// include parser functions.
					$default_filename = $parser->recursiveTagParse( $default_filename );
					$f->mFieldArgs['default filename'] = $default_filename;
				} elseif ( $sub_components[0] == 'restricted' ) {
					if ( method_exists( MediaWikiServices::class, 'getUserGroupManager' ) ) {
						// MediaWiki >= 1.35
						$effectiveGroups = MediaWikiServices::getInstance()->getUserGroupManager()->getUserEffectiveGroups( $user );
					} else {
						$effectiveGroups = $user->getEffectiveGroups();
					}
					$f->mIsRestricted = !array_intersect(
						$effectiveGroups, array_map( 'trim', explode( ',', $sub_components[1] ) )
					);
				}
			}
		}
		// end for

		if ( $valuesSourceType !== null ) {
			$f->mPossibleValues = PFValuesUtils::getAutocompleteValues( $valuesSource, $valuesSourceType );
			if ( in_array( $valuesSourceType, [ 'category', 'namespace', 'concept' ] ) ) {
				global $wgPageFormsUseDisplayTitle;
				$f->mUseDisplayTitle = $wgPageFormsUseDisplayTitle;
			}
		}

		if ( !array_key_exists( 'delimiter', $f->mFieldArgs ) ) {
			$delimiterFromTemplate = $f->getTemplateField()->getDelimiter();
			if ( $delimiterFromTemplate == '' ) {
				$f->mFieldArgs['delimiter'] = ',';
			} else {
				$f->mFieldArgs['delimiter'] = $delimiterFromTemplate;
				$f->mIsList = true;
			}
		}
		$delimiter = $f->mFieldArgs['delimiter'];

		// If the 'values' parameter was set, separate it based on the
		// 'delimiter' parameter, if any.
		if ( $values != null ) {
			// Remove whitespaces, and un-escape characters
			$valuesArray = array_map( 'trim', explode( $delimiter, $values ) );
			$f->mPossibleValues = array_map( 'htmlspecialchars_decode', $valuesArray );
		}

		// If we're using Cargo, there's no equivalent for "values from
		// property" - instead, we just always get the values if a
		// field and table have been specified.
		if ( $f->mPossibleValues === null && defined( 'CARGO_VERSION' ) && $cargo_table != null && $cargo_field != null ) {
			// We only want the non-null values. Ideally this could
			// be done by calling getValuesForCargoField() with
			// an "IS NOT NULL" clause, but unfortunately that fails
			// for array/list fields.
			// Instead of getting involved with all that, we'll just
			// remove the null/blank values afterward.
			$cargoValues = PFValuesUtils::getAllValuesForCargoField( $cargo_table, $cargo_field );
			$f->mPossibleValues = array_filter( $cargoValues, 'strlen' );
		}

		if ( $f->mPossibleValues == null ) {
			$f->mPossibleValues = $f->template_field->getPossibleValues();
		}

		$mappingType = null;
		if ( array_key_exists( 'mapping template', $f->mFieldArgs ) ) {
			$mappingType = 'template';
		} elseif ( array_key_exists( 'mapping property', $f->mFieldArgs ) ) {
			$mappingType = 'property';
		} elseif ( array_key_exists( 'mapping cargo table', $f->mFieldArgs ) &&
			array_key_exists( 'mapping cargo field', $f->mFieldArgs ) ) {
			$mappingType = 'cargo field';
		} elseif ( $f->mUseDisplayTitle ) {
			$f->mPossibleValues = PFValuesUtils::disambiguateLabels( $f->mPossibleValues );
		}

		if ( $mappingType !== null && !empty( $f->mPossibleValues ) ) {
			// If we're going to be mapping values, we need to have
			// the exact page name - and if these values come from
			// "values from namespace", the namespace prefix was
			// not included, so we need to add it now.
			if ( $valuesSourceType == 'namespace' && $valuesSource != '' && $valuesSource != 'Main' ) {
				foreach ( $f->mPossibleValues as $index => &$value ) {
					$value = $valuesSource . ':' . $value;
				}
				// Has to be set to false to not mess up the
				// handling.
				$f->mUseDisplayTitle = false;
			}

			$f->setMappedValues( $mappingType );
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
			// PFFormField object, not the PFTemplateField object
			// it contains;
			// it seemed like too much work, though, to create an
			// PFFormField::setSemanticProperty() function just for
			// this call.
			if ( $semantic_property !== null ) {
				$f->template_field->setSemanticProperty( $semantic_property );
			} else {
				$semantic_property = $f->template_field->getSemanticProperty();
			}
			if ( $semantic_property !== null ) {
				global $wgPageFormsFieldProperties;
				$wgPageFormsFieldProperties[$fullFieldName] = $semantic_property;
			}
		}
		if ( defined( 'CARGO_VERSION' ) ) {
			if ( $cargo_table != null && $cargo_field != null ) {
				$f->template_field->setCargoFieldData( $cargo_table, $cargo_field );
			}
			$fullCargoField = $f->template_field->getFullCargoField();
			if ( $fullCargoField !== null ) {
				global $wgPageFormsCargoFields;
				$wgPageFormsCargoFields[$fullFieldName] = $fullCargoField;
			}
		}

		if ( $template_name === null || $template_name === '' ) {
			$f->mInputName = $field_name;
		} elseif ( $template_in_form->allowsMultiple() ) {
			// 'num' will get replaced by an actual index, either in PHP
			// or in Javascript, later on
			$f->mInputName = $template_name . '[num][' . $field_name . ']';
			$f->setFieldArg( 'origName', $fullFieldName );
		} else {
			$f->mInputName = $fullFieldName;
		}

		return $f;
	}

	function isTranslateEnabled() {
		return class_exists( 'SpecialTranslate' );
	}

	function cleanupTranslateTags( &$value ) {
		$i = 0;
		// If there are two tags ("<!--T:X-->") with no content between them, remove the first one.
		while ( preg_match( '/(<!--T:[0-9]+-->\s*)(<!--T:[0-9]+-->)/', $value, $matches ) ) {
			$value = str_replace( $matches[1], '', $value );
			if ( $i++ > 200 ) {
				// Is this necessary?
				break;
			}
		}

		$i = 0;
		// If there is a tag ("<!--T:X-->") at the end, with nothing after, remove it.
		while ( preg_match( '#(<!--T:[0-9]+-->\s*)(</translate>)#', $value, $matches ) ) {
			$value = str_replace( $matches[1], '', $value );
			if ( $i++ > 200 ) {
				// Is this necessary?
				break;
			}
		}

		$i = 0;
		// If there is a tag ("<!--T:X-->") not separated from a template call ("{{ ..."),
		// add a new line between them.
		while ( preg_match( '/(<!--T:[0-9]+-->)({{[^}]+}}\s*)/', $value, $matches ) ) {
			$value = str_replace( $matches[1], $matches[1] . "\n", $value );
			if ( $i++ > 200 ) {
				// Is this necessary?
				break;
			}
		}
	}

	function getCurrentValue( $template_instance_query_values, $form_submitted, $source_is_page, $all_instances_printed, &$val_modifier = null ) {
		// Get the value from the request, if
		// it's there, and if it's not an array.
		$field_name = $this->template_field->getFieldName();
		$delimiter = $this->mFieldArgs['delimiter'];
		$escaped_field_name = str_replace( "'", "\'", $field_name );

		if ( $this->isTranslateEnabled() && $this->hasFieldArg( 'translatable' ) && $this->getFieldArg( 'translatable' ) ) {
			// If this is a translatable field, and both it and its corresponding translate ID tag are passed in, we add it.
			$fieldName = $this->getTemplateField()->getFieldName();
			$fieldNameTag = $fieldName . '_translate_number_tag';
			if ( isset( $template_instance_query_values[$fieldName] ) && isset( $template_instance_query_values[$fieldNameTag] ) ) {
				$tag = $template_instance_query_values[$fieldNameTag];
				if ( !preg_match( '/( |\n)$/', $tag ) ) {
					$tag .= "\n";
				}
				if ( trim( $template_instance_query_values[$fieldName] ) ) {
					// Don't add the tag if field content has been removed.
					$template_instance_query_values[$fieldName] = $tag . $template_instance_query_values[$fieldName];
				}
			}
			// If user has deleted some content, and there is some translate tag ("<!--T:X-->") with no content, remove the tag.
			if ( isset( $template_instance_query_values[$fieldName] ) ) {
				$this->cleanupTranslateTags( $template_instance_query_values[$fieldName] );
			}
		}

		if ( isset( $template_instance_query_values ) &&
			$template_instance_query_values != null &&
			is_array( $template_instance_query_values )
		) {
			// If the field name contains an apostrophe, the array
			// sometimes has the apostrophe escaped, and sometimes
			// not. For now, just check for both versions.
			// @TODO - figure this out.
			$field_query_val = null;
			if ( array_key_exists( $escaped_field_name, $template_instance_query_values ) ) {
				$field_query_val = $template_instance_query_values[$escaped_field_name];
			} elseif ( array_key_exists( $field_name, $template_instance_query_values ) ) {
				$field_query_val = $template_instance_query_values[$field_name];
			} else {
				// The next checks are to allow for support for appending/prepending with autoedit.
				if ( array_key_exists( "$field_name+", $template_instance_query_values ) ) {
					$field_query_val = $template_instance_query_values["$field_name+"];
					$val_modifier = '+';
				} elseif ( array_key_exists( "$field_name-", $template_instance_query_values ) ) {
					$field_query_val = $template_instance_query_values["$field_name-"];
					$val_modifier = '-';
				}
			}

			if ( $form_submitted && $field_query_val != '' ) {
				$map_field = false;
				if ( array_key_exists( 'map_field', $template_instance_query_values ) &&
					array_key_exists( $field_name, $template_instance_query_values['map_field'] ) ) {
					$map_field = true;
				}
				if ( is_array( $field_query_val ) ) {
					$cur_values = [];
					if ( $map_field && $this->mPossibleValues !== null ) {
						foreach ( $field_query_val as $key => $val ) {
							$val = trim( $val );
							if ( $key === 'is_list' ) {
								$cur_values[$key] = $val;
							} else {
								$cur_values[] = $this->labelToValue( $val );
							}
						}
					} else {
						foreach ( $field_query_val as $key => $val ) {
							$cur_values[$key] = $val;
						}
					}
					return PFFormPrinter::getStringFromPassedInArray( $cur_values, $delimiter );
				} else {
					$field_query_val = trim( $field_query_val );
					if ( $map_field && $this->mPossibleValues !== null ) {
						// this should be replaced with an input type neutral way of
						// figuring out if this scalar input type is a list
						if ( $this->mInputType == "tokens" ) {
							$this->mIsList = true;
						}
						if ( $this->mIsList ) {
							$cur_values = array_map( 'trim', explode( $delimiter, $field_query_val ) );
							foreach ( $cur_values as $key => $val ) {
								$cur_values[$key] = $this->labelToValue( $val );
							}
							return implode( $delimiter, $cur_values );
						}
						return $this->labelToValue( $field_query_val );
					}
					return $field_query_val;
				}
			}
			if ( !$form_submitted && $field_query_val != '' ) {
				if ( is_array( $field_query_val ) ) {
					$str = PFFormPrinter::getStringFromPassedInArray( $field_query_val, $delimiter );
				} else {
					$str = $field_query_val;
				}
				return str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $str );

			}
		}

		// Default values in new instances of multiple-instance
		// templates should always be set, even for existing pages.
		$part_of_multiple = array_key_exists( 'part_of_multiple', $this->mFieldArgs );
		$printing_starter_instance = $part_of_multiple && $all_instances_printed;
		if ( ( !$source_is_page || $printing_starter_instance ) && !$form_submitted ) {
			if ( $this->mDefaultValue !== null ) {
				// Set to the default value specified in the form, if it's there.
				return $this->mDefaultValue;
			} elseif ( $this->mPreloadPage ) {
				return PFFormUtils::getPreloadedText( $this->mPreloadPage );
			}
		}

		// We're still here...
		return null;
	}

	function setMappedValues( $mappingType ) {
		if ( $mappingType == 'template' ) {
			$this->setValuesWithMappingTemplate();
		} elseif ( $mappingType == 'property' ) {
			$this->setValuesWithMappingProperty();
		} elseif ( $mappingType == 'cargo field' ) {
			$this->setValuesWithMappingCargoField();
		}

		$this->mPossibleValues = PFValuesUtils::disambiguateLabels( $this->mPossibleValues );
	}

	/**
	 * Helper function to get an array of labels from an array of values
	 * given a mapping template.
	 */
	function setValuesWithMappingTemplate() {
		$labels = [];
		$templateName = $this->mFieldArgs['mapping template'];
		$title = Title::makeTitleSafe( NS_TEMPLATE, $templateName );
		$templateExists = $title->exists();
		foreach ( $this->mPossibleValues as $index => $value ) {
			if ( $this->mUseDisplayTitle ) {
				$value = $index;
			}
			if ( $templateExists ) {
				$label = trim( PFUtils::getParser()->recursiveTagParse( '{{' . $templateName .
					'|' . $value . '}}' ) );
				if ( $label == '' ) {
					$labels[$value] = $value;
				} else {
					$labels[$value] = $label;
				}
			} else {
				$labels[$value] = $value;
			}
		}
		$this->mPossibleValues = $labels;
	}

	/**
	 * Helper function to get an array of labels from an array of values
	 * given a mapping property.
	 */
	function setValuesWithMappingProperty() {
		$store = PFUtils::getSMWStore();
		if ( $store == null ) {
			return;
		}

		$propertyName = $this->mFieldArgs['mapping property'];
		$labels = [];
		foreach ( $this->mPossibleValues as $index => $value ) {
			if ( $this->mUseDisplayTitle ) {
				$value = $index;
			}
			$labels[$value] = $value;
			$subject = Title::newFromText( $value );
			if ( $subject != null ) {
				$vals = PFValuesUtils::getSMWPropertyValues( $store, $subject, $propertyName );
				if ( count( $vals ) > 0 ) {
					$labels[$value] = trim( $vals[0] );
				}
			}
		}
		$this->mPossibleValues = $labels;
	}

	/**
	 * Helper function to get an array of labels from an array of values
	 * given a mapping Cargo table/field.
	 */
	function setValuesWithMappingCargoField() {
		$labels = [];
		foreach ( $this->mPossibleValues as $index => $value ) {
			if ( $this->mUseDisplayTitle ) {
				$value = $index;
			}
			$labels[$value] = $value;
			if ( $this->hasFieldArg( 'mapping cargo value field' ) ) {
				$valueField = $this->mFieldArgs['mapping cargo value field'];
			} else {
				$valueField = '_pageName';
			}
			$vals = PFValuesUtils::getValuesForCargoField(
				$this->mFieldArgs['mapping cargo table'],
				$this->mFieldArgs['mapping cargo field'],
				$valueField . '="' . $value . '"'
			);
			if ( count( $vals ) > 0 ) {
				$labels[$value] = html_entity_decode( trim( $vals[0] ) );
			}
		}
		$this->mPossibleValues = $labels;
	}

	/**
	 * Map a label back to a value.
	 * @param string $label
	 * @return string
	 */
	function labelToValue( $label ) {
		$value = array_search( $label, $this->mPossibleValues );
		if ( $value === false ) {
			return $label;
		} else {
			return $value;
		}
	}

	/**
	 * Map a template field value into labels.
	 * @param string $valueString
	 * @param string $delimiter
	 * @return string|string[]
	 */
	public function valueStringToLabels( $valueString, $delimiter ) {
		if ( strlen( trim( $valueString ) ) === 0 ||
			$this->mPossibleValues === null ) {
			return $valueString;
		}
		if ( $delimiter !== null ) {
			$values = array_map( 'trim', explode( $delimiter, $valueString ) );
		} else {
			$values = [ $valueString ];
		}
		$labels = [];
		foreach ( $values as $value ) {
			if ( $value != '' ) {
				if ( array_key_exists( $value, $this->mPossibleValues ) ) {
					$labels[] = $this->mPossibleValues[$value];
				} else {
					$labels[] = $value;
				}
			}
		}
		if ( count( $labels ) > 1 ) {
			return $labels;
		} else {
			return $labels[0];
		}
	}

	public function additionalHTMLForInput( $cur_value, $field_name, $template_name ) {
		$text = '';

		// Add a field just after the hidden field, within the HTML, to
		// locate where the multiple-templates HTML, stored in
		// $multipleTemplateString, should be inserted.
		if ( $this->mHoldsTemplate ) {
			$text .= PFFormPrinter::makePlaceholderInFormHTML( PFFormPrinter::placeholderFormat( $template_name, $field_name ) );
		}

		// If this field is disabled, add a hidden field holding
		// the value of this field, because disabled inputs for some
		// reason don't submit their value.
		if ( $this->mIsDisabled ) {
			if ( $field_name == 'free text' || $field_name == '#freetext#' ) {
				$text .= Html::hidden( 'pf_free_text', '!free_text!' );
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
			$this->hasFieldArg( 'mapping cargo field' ) ) ||
			$this->mUseDisplayTitle ) {
			if ( $this->hasFieldArg( 'part_of_multiple' ) ) {
				$text .= Html::hidden( $template_name . '[num][map_field][' . $field_name . ']', 'true' );
			} else {
				$text .= Html::hidden( $template_name . '[map_field][' . $field_name . ']', 'true' );
			}
		}

		if ( $this->hasFieldArg( 'unique' ) ) {
			global $wgPageFormsFieldNum;

			$semantic_property = $this->template_field->getSemanticProperty();
			if ( $semantic_property != null ) {
				$text .= Html::hidden( 'input_' . $wgPageFormsFieldNum . '_unique_property', $semantic_property );
			}
			$fullCargoField = $this->template_field->getFullCargoField();
			if ( $fullCargoField != null ) {
				// It's inefficient to get these values via
				// text parsing, but oh well.
				list( $cargo_table, $cargo_field ) = explode( '|', $fullCargoField, 2 );
				$text .= Html::hidden( 'input_' . $wgPageFormsFieldNum . '_unique_cargo_table', $cargo_table );
				$text .= Html::hidden( 'input_' . $wgPageFormsFieldNum . '_unique_cargo_field', $cargo_field );
			}
			if ( $this->hasFieldArg( 'unique_for_category' ) ) {
				$text .= Html::hidden( 'input_' . $wgPageFormsFieldNum . '_unique_for_category', $this->getFieldArg( 'unique_for_category' ) );
			}
			if ( $this->hasFieldArg( 'unique_for_namespace' ) ) {
				$text .= Html::hidden( 'input_' . $wgPageFormsFieldNum . '_unique_for_namespace', $this->getFieldArg( 'unique_for_namespace' ) );
			}
			if ( $this->hasFieldArg( 'unique_for_concept' ) ) {
				$text .= Html::hidden( 'input_' . $wgPageFormsFieldNum . '_unique_for_concept', $this->getFieldArg( 'unique_for_concept' ) );
			}
		}
		return $text;
	}

	/**
	 * For now, HTML of an individual field depends on whether or not it's
	 * part of multiple-instance template; this may change if handling of
	 * such templates in form definitions gets more sophisticated.
	 *
	 * @param bool $part_of_multiple
	 * @param bool $is_last_field_in_template
	 * @return string
	 */
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
						$descPlaceholder = '<br><p class="pfFieldDescription" style="font-size:0.7em; color:gray;">' . $fieldDesc . '</p>';
					}
				} else {
					$descPlaceholder = '<br><p class="pfFieldDescription" style="font-size:0.7em; color:gray;">' . $fieldDesc . '</p>';
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

		if ( !$part_of_multiple ) {
			$text .= "| ";
		}
		$text .= "{{{field|" . $this->template_field->getFieldName();
		if ( $this->mIsHidden ) {
			$text .= "|hidden";
		} elseif ( $this->getInputType() !== null ) {
			$text .= "|input type=" . $this->getInputType();
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
		} elseif ( !$is_last_field_in_template ) {
			$text .= "|-\n";
		}
		return $text;
	}

	function getArgumentsForInputCallSMW( array &$other_args ) {
		if ( $this->template_field->getSemanticProperty() !== '' &&
			!array_key_exists( 'semantic_property', $other_args ) ) {
			$other_args['semantic_property'] = $this->template_field->getSemanticProperty();
			$other_args['property_type'] = $this->template_field->getPropertyType();
		}
		// If autocompletion hasn't already been hardcoded in the form,
		// and it's a property of type page, or a property of another
		// type with 'autocomplete' specified, set the necessary
		// parameters.
		if ( !array_key_exists( 'autocompletion source', $other_args ) ) {
			if ( $this->template_field->getPropertyType() == '_wpg' ) {
				$other_args['autocompletion source'] = $this->template_field->getSemanticProperty();
				$other_args['autocomplete field type'] = 'property';
			} elseif ( array_key_exists( 'autocomplete', $other_args ) || array_key_exists( 'remote autocompletion', $other_args ) ) {
				$other_args['autocompletion source'] = $this->template_field->getSemanticProperty();
				$other_args['autocomplete field type'] = 'property';
			}
		}
	}

	function getArgumentsForInputCallCargo( array &$other_args ) {
		$fullCargoField = $this->template_field->getFullCargoField();
		if ( $fullCargoField !== null &&
			!array_key_exists( 'full_cargo_field', $other_args ) ) {
			$other_args['full_cargo_field'] = $fullCargoField;
		}

		if ( $this->template_field->getFieldType() == 'Hierarchy' ) {
			$other_args['structure'] = $this->template_field->getHierarchyStructure();
		}

		if ( !array_key_exists( 'autocompletion source', $other_args ) ) {
			if (
				$this->template_field->getFieldType() == 'Page' ||
				array_key_exists( 'autocomplete', $other_args ) ||
				array_key_exists( 'remote autocompletion', $other_args )
			) {
				$other_args['autocompletion source'] = $this->template_field->getFullCargoField();
				$other_args['autocomplete field type'] = 'cargo field';
			}
		}
	}

	/**
	 * Since Page Forms uses a hook system for the functions that
	 * create HTML inputs, most arguments are contained in the "$other_args"
	 * array - create this array, using the attributes of this form
	 * field and the template field it corresponds to, if any.
	 * @param array|null $default_args
	 * @return array
	 */
	function getArgumentsForInputCall( array $default_args = null ) {
		$parser = PFUtils::getParser();

		// start with the arguments array already defined
		$other_args = $this->mFieldArgs;
		// a value defined for the form field should always supersede
		// the coresponding value for the template field
		if ( $this->mPossibleValues != null ) {
			$other_args['possible_values'] = $this->mPossibleValues;
		} else {
			$other_args['possible_values'] = $this->template_field->getPossibleValues();
			if ( $this->hasFieldArg( 'mapping using translate' ) ) {
				$other_args['value_labels'] = [];
				foreach ( $other_args['possible_values'] as $key ) {
					$other_args['value_labels'][$key] = $parser->recursiveTagParse( '{{int:' . $this->getFieldArg( 'mapping using translate' ) . $key . '}}' );
				}
			} else {
				$other_args['value_labels'] = $this->template_field->getValueLabels();
			}
		}
		$other_args['is_list'] = ( $this->mIsList || $this->template_field->isList() );
		if ( $this->template_field->isMandatory() ) {
			$other_args['mandatory'] = true;
		}
		if ( $this->template_field->isUnique() ) {
			$other_args['unique'] = true;
		}

		// Now add some extension-specific arguments to the input call.
		if ( defined( 'CARGO_VERSION' ) ) {
			$this->getArgumentsForInputCallCargo( $other_args );
		}
		if ( defined( 'SMW_VERSION' ) ) {
			$this->getArgumentsForInputCallSMW( $other_args );
		}

		// Now merge in the default values set by PFFormPrinter, if
		// there were any - put the default values first, so that if
		// there's a conflict they'll be overridden.
		if ( $default_args != null ) {
			$other_args = array_merge( $default_args, $other_args );
		}

		foreach ( $other_args as $argname => $argvalue ) {
			if ( is_string( $argvalue ) ) {
				$other_args[$argname] =
					$parser->recursiveTagParse( $argvalue );
			}
		}

		return $other_args;
	}
}
