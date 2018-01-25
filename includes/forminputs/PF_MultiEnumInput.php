<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * The base class for every form input that holds a list of elements, each
 * one from a pre-set enumeration of values.
 *
 * @ingroup PFFormInput
 */
abstract class PFMultiEnumInput extends PFEnumInput {

	public static function getOtherPropTypesHandled() {
		return array();
	}

	public static function getOtherPropTypeListsHandled() {
		return array( 'enumeration' );
	}

	public static function getOtherCargoTypesHandled() {
		return array();
	}

	public static function getOtherCargoTypeListsHandled() {
		return array( 'Enumeration' );
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = array(
			'name' => 'delimiter',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_delimiter' )->text()
		);
		return $params;
	}
}
