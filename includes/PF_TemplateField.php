<?php
/**
 * Defines a class, PFTemplateField, that represents a field in a template,
 * including any possible semantic aspects it may have. Used in both creating
 * templates and displaying user-created forms.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

class PFTemplateField {
	private $mFieldName;
	private $mValueLabels;
	private $mLabel;

	// SMW-specific
	private $mSemanticProperty;
	private $mPropertyType;

	// Cargo-specific
	private $mCargoTable;
	private $mCargoField;
	private $mFieldType;
	private $mHierarchyStructure;

	private $mPossibleValues;
	private $mIsList;
	private $mDelimiter;
	private $mDisplay;
	private $mNamespace;
	private $mIsMandatory = false;
	private $mIsUnique = false;
	private $mRegex = null;

	static function create( $name, $label, $semanticProperty = null, $isList = null, $delimiter = null, $display = null ) {
		$f = new PFTemplateField();
		$f->mFieldName = trim( str_replace( '\\', '', $name ) );
		$f->mLabel = trim( str_replace( '\\', '', $label ) );
		$f->setSemanticProperty( $semanticProperty );
		$f->mIsList = $isList;
		$f->mDelimiter = $delimiter;
		$f->mDisplay = $display;
		// Delimiter should default to ','.
		if ( $isList && !$delimiter ) {
			$f->mDelimiter = ',';
		}
		return $f;
	}

	function setTypeAndPossibleValues() {
		if ( !defined( 'SMW_NS_PROPERTY' ) ) {
			return;
		}

		// The presence of "-" at the beginning of a property name
		// (which happens if PF tries to parse an inverse query)
		// leads to an error in SMW - just exit if that's the case.
		if ( strpos( $this->mSemanticProperty, '-' ) === 0 ) {
			return;
		}

		$proptitle = Title::makeTitleSafe( SMW_NS_PROPERTY, $this->mSemanticProperty );
		if ( $proptitle === null ) {
			return;
		}

		$store = PFUtils::getSMWStore();
		// this returns an array of objects
		$allowed_values = PFValuesUtils::getSMWPropertyValues( $store, $proptitle, "Allows value" );
		$label_formats = PFValuesUtils::getSMWPropertyValues( $store, $proptitle, "Has field label format" );
		$propValue = SMWDIProperty::newFromUserLabel( $this->mSemanticProperty );
		$this->mPropertyType = $propValue->findPropertyTypeID();

		foreach ( $allowed_values as $allowed_value ) {
			// HTML-unencode each value
			$this->mPossibleValues[] = html_entity_decode( $allowed_value );
			if ( count( $label_formats ) > 0 ) {
				$label_format = $label_formats[0];
				$prop_instance = SMWDataValueFactory::findTypeID( $this->mPropertyType );
				$label_value = SMWDataValueFactory::newTypeIDValue( $prop_instance, $wiki_value );
				$label_value->setOutputFormat( $label_format );
				$this->mValueLabels[$wiki_value] = html_entity_decode( $label_value->getWikiValue() );
			}
		}

		// HACK - if there were any possible values, set the property
		// type to be 'enumeration', regardless of what the actual type is
		if ( count( $this->mPossibleValues ) > 0 ) {
			$this->mPropertyType = 'enumeration';
		}
	}

	/**
	 * Called if a matching property is found for a template field when
	 * a template is parsed during the creation of a form.
	 * @param string $semantic_property
	 */
	function setSemanticProperty( $semantic_property ) {
		$this->mSemanticProperty = str_replace( '\\', '', $semantic_property );
		$this->mPossibleValues = array();
		// set field type and possible values, if any
		$this->setTypeAndPossibleValues();
	}

	/**
	 * Equivalent to setSemanticProperty(), but called when using Cargo
	 * instead of SMW.
	 * @param string $tableName
	 * @param string $fieldName
	 * @param string|null $fieldDescription
	 */
	function setCargoFieldData( $tableName, $fieldName, $fieldDescription = null ) {
		$this->mCargoTable = $tableName;
		$this->mCargoField = $fieldName;

		if ( is_null( $fieldDescription ) ) {
			try {
				$tableSchemas = CargoUtils::getTableSchemas( array( $tableName ) );
			} catch ( MWException $e ) {
				return;
			}
			if ( count( $tableSchemas ) == 0 ) {
				return;
			}
			$tableSchema = $tableSchemas[$tableName];
			$fieldDescriptions = $tableSchema->mFieldDescriptions;
			if ( array_key_exists( $fieldName, $fieldDescriptions ) ) {
				$fieldDescription = $fieldDescriptions[$fieldName];
			} else {
				return;
			}
		}

		// We have some "pseudo-types", used for setting the correct
		// form input.
		if ( $fieldDescription->mAllowedValues != null ) {
			if ( $fieldDescription->mIsHierarchy == true ) {
				$this->mFieldType = 'Hierarchy';
				$this->mHierarchyStructure = $fieldDescription->mHierarchyStructure;
			} else {
				$this->mFieldType = 'Enumeration';
			}
		} elseif ( $fieldDescription->mType == 'Text' && $fieldDescription->mSize != '' && $fieldDescription->mSize <= 100 ) {
			$this->mFieldType = 'String';
		} else {
			$this->mFieldType = $fieldDescription->mType;
		}
		$this->mIsList = $fieldDescription->mIsList;
		if ( method_exists( $fieldDescription, 'getDelimiter' ) ) {
			// Cargo 0.11+
			$this->mDelimiter = $fieldDescription->getDelimiter();
		} else {
			$this->mDelimiter = $fieldDescription->mDelimiter;
		}
		$this->mPossibleValues = $fieldDescription->mAllowedValues;
		if ( property_exists( $fieldDescription, 'mIsMandatory' ) ) {
			// Cargo 1.7+
			$this->mIsMandatory = $fieldDescription->mIsMandatory;
			$this->mIsUnique = $fieldDescription->mIsUnique;
			$this->mRegex = $fieldDescription->mRegex;
		}
	}

	function getFieldName() {
		return $this->mFieldName;
	}

	function getValueLabels() {
		return $this->mValueLabels;
	}

	function getLabel() {
		return $this->mLabel;
	}

	function getSemanticProperty() {
		return $this->mSemanticProperty;
	}

	function getPropertyType() {
		return $this->mPropertyType;
	}

	function getFullCargoField() {
		if ( $this->mCargoTable == '' || $this->mCargoField == '' ) {
			return null;
		}
		return $this->mCargoTable . '|' . $this->mCargoField;
	}

	function getFieldType() {
		return $this->mFieldType;
	}

	function getPossibleValues() {
		return $this->mPossibleValues;
	}

	function getHierarchyStructure() {
		return $this->mHierarchyStructure;
	}

	function isList() {
		return $this->mIsList;
	}

	function getDelimiter() {
		return $this->mDelimiter;
	}

	function getDisplay() {
		return $this->mDisplay;
	}

	function getNamespace() {
		return $this->mNamespace;
	}

	function isMandatory() {
		return $this->mIsMandatory;
	}

	function isUnique() {
		return $this->mIsUnique;
	}

	function getRegex() {
		return $this->mRegex;
	}

	function setTemplateField( $templateField ) {
		$this->mTemplateField = $templateField;
	}

	function setLabel( $label ) {
		$this->mLabel = $label;
	}

	function setNamespace( $namespace ) {
		$this->mNamespace = $namespace;
	}

	function setFieldType( $fieldType ) {
		$this->mFieldType = $fieldType;
	}

	function setPossibleValues( $possibleValues ) {
		$this->mPossibleValues = $possibleValues;
	}

	function setHierarchyStructure( $hierarchyStructure ) {
		$this->mHierarchyStructure = $hierarchyStructure;
	}
}
