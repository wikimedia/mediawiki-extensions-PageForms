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
		return [];
	}

	public static function getOtherPropTypeListsHandled() {
		return [ 'enumeration' ];
	}

	public static function getOtherCargoTypesHandled() {
		return [];
	}

	public static function getOtherCargoTypeListsHandled() {
		return [ 'Enumeration' ];
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = [
			'name' => 'delimiter',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_delimiter' )->text()
		];
		return $params;
	}
}
