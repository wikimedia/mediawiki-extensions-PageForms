<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFTextAreaWithAutocompleteInput extends PFTextWithAutocompleteInput {

	public static function getName(): string {
		return 'textarea with autocomplete';
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		return call_user_func( self::getAlias() . "::getHTML", $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );
	}
}
