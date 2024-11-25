<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFEndDateInput extends PFDateInput {

	public static function getName(): string {
		return 'end date';
	}

	public static function getDefaultCargoTypes() {
		return [
			'End date' => [],
		];
	}

	public static function getDefaultPropTypes() {
		return [];
	}

	public static function getOtherPropTypesHandled() {
		return [ '_dat' ];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Date' ];
	}

	public function getInputClass() {
		return 'dateInput endDateInput';
	}
}
