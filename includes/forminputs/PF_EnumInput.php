<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * The base class for every form input that holds a pre-set enumeration
 * of values.
 *
 * @ingroup PFFormInput
 */
abstract class PFEnumInput extends PFFormInput {

	public static function getOtherPropTypesHandled() {
		return array( 'enumeration', '_boo' );
	}

	public static function getOtherCargoTypesHandled() {
		return array( 'Enumeration', 'Boolean' );
	}

	public static function getValuesParameters() {
		$params = array();
		$params[] = array(
			'name' => 'values',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_values' )->text()
		);
		if ( defined( 'SMW_VERSION' ) ) {
			$params[] = array(
				'name' => 'values from property',
				'type' => 'string',
				'description' => wfMessage( 'pf_forminputs_valuesfromproperty' )->text()
			);
		}
		$params[] = array(
			'name' => 'values from category',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_valuesfromcategory' )->text()
		);
		$params[] = array(
			'name' => 'values from namespace',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_valuesfromnamespace' )->text()
		);
		if ( defined( 'SMW_VERSION' ) ) {
			$params[] = array(
				'name' => 'values from concept',
				'type' => 'string',
				'description' => wfMessage( 'pf_forminputs_valuesfromconcept' )->text()
			);
		}
		return $params;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params = array_merge( $params, self::getValuesParameters() );
		$params[] = array(
			'name' => 'show on select',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_showonselect' )->text()
		);
		return $params;
	}
}
