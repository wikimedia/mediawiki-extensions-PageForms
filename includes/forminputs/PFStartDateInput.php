<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFStartDateInput extends PFDateInput {

	public static function getName(): string {
		return 'start date';
	}

	public static function getDefaultPropTypes() {
		return [];
	}

	public static function getOtherPropTypesHandled() {
		return [ '_dat' ];
	}

	public static function getDefaultCargoTypes() {
		return [
			'Start date' => [],
		];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Date' ];
	}

	public function getInputClass() {
		return 'dateInput startDateInput';
	}
}
