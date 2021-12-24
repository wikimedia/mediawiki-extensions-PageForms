<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFStartDateTimeInput extends PFDateTimeInput {

	public static function getName(): string {
		return 'start datetime';
	}

	public function getInputClass() {
		return 'dateTimeInput startDateTimeInput';
	}

	public static function getDefaultCargoTypes() {
		return [
			'Start datetime' => [],
		];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Datetime' ];
	}
}
