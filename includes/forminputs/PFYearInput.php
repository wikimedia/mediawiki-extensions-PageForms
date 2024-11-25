<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFYearInput extends PFTextInput {

	public static function getName(): string {
		return 'year';
	}

	public static function getDefaultPropTypes() {
		return [];
	}

	public static function getOtherPropTypesHandled() {
		return [ '_dat' ];
	}

	public static function getDefaultPropTypeLists() {
		return [];
	}

	public static function getOtherPropTypeListsHandled() {
		return [];
	}

	public static function getDefaultCargoTypes() {
		return [];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Date' ];
	}

	public static function getDefaultCargoTypeLists() {
		return [];
	}

	public static function getOtherCargoTypeListsHandled() {
		return [];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		$other_args['size'] = 4;
		return parent::getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );
	}

	public static function getParameters() {
		$params = [];
		$params[] = [
			'name' => 'mandatory',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_mandatory' )->text()
		];
		$params[] = [
			'name' => 'restricted',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_restricted' )->text()
		];
		$params[] = [
			'name' => 'class',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_class' )->text()
		];
		$params[] = [
			'name' => 'default',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_default' )->text()
		];
		$params[] = [
			'name' => 'size',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_size' )->text()
		];
		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText(): string {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}
}
