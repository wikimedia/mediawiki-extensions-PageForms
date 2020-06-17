<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFTextWithAutocompleteInput extends PFTextInput {
	private static $alias;

	public function __construct( $input_number, $cur_value, $input_name, $disabled, array $other_args ) {
		parent::__construct( $input_number, $cur_value, $input_name, $disabled, $other_args );
		$isList = ( array_key_exists( 'is_list', $other_args ) && $other_args['is_list'] == true );
		if ( $isList ) {
			self::$alias = 'PFTokensInput';
		} else {
			self::$alias = 'PFComboBoxInput';
		}
	}

	public static function getName() {
		return 'text with autocomplete';
	}

	public static function getDefaultPropTypes() {
		return call_user_func( self::getAlias() . "::getDefaultPropTypes" );
	}

	public static function getOtherPropTypesHandled() {
		return call_user_func( self::getAlias() . "::getOtherPropTypesHandled" );
	}

	public static function getDefaultPropTypeLists() {
		return call_user_func( self::getAlias() . "::getDefaultPropTypeLists" );
	}

	public static function getOtherPropTypeListsHandled() {
		return call_user_func( self::getAlias() . "::getOtherPropTypeListsHandled" );
	}

	public static function getDefaultCargoTypes() {
		return call_user_func( self::getAlias() . "::getDefaultCargoTypes" );
	}

	public static function getOtherCargoTypesHandled() {
		return call_user_func( self::getAlias() . "::getOtherCargoTypesHandled" );
	}

	public static function getDefaultCargoTypeLists() {
		return call_user_func( self::getAlias() . "::getDefaultCargoTypeLists" );
	}

	public static function getOtherCargoTypeListsHandled() {
		return call_user_func( self::getAlias() . "::getOtherCargoTypeListsHandled" );
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		return call_user_func( self::getAlias() . "::getHTML", $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );
	}

	public static function getAutocompletionParameters() {
		$params = PFEnumInput::getValuesParameters();
		$params[] = [
			'name' => 'values from url',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_valuesfromurl' )->text()
		];
		$params[] = [
			'name' => 'list',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_list' )->text()
		];
		$params[] = [
			'name' => 'delimiter',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_delimiter' )->text()
		];
		return $params;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params = array_merge( $params, self::getAutocompletionParameters() );
		return $params;
	}

	protected static function getAlias() {
		if ( isset( self::$alias ) ) {
			return self::$alias;
		} else {
			return 'PFComboBoxInput';
		}
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText() {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}
}
