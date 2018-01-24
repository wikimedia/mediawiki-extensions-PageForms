<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFTextAreaWithAutocompleteInput extends PFTextAreaInput {
	public static function getName() {
		return 'textarea with autocomplete';
	}

	public static function getDefaultPropTypes() {
		return array();
	}

	public static function getDefaultCargoTypes() {
		return array();
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params = array_merge( $params, PFTextWithAutocompleteInput::getAutocompletionParameters() );
		return $params;
	}

	protected function getTextAreaAttributes() {
		$textarea_attrs = parent::getTextAreaAttributes();

		list( $autocompleteSettings, $remoteDataType, $delimiter ) = PFTextWithAutocompleteInput::setAutocompleteValues( $this->mOtherArgs );

		if ( !is_null( $remoteDataType ) ) {
			$textarea_attrs['autocompletedatatype'] = $remoteDataType;
		}

		$textarea_attrs['autocompletesettings'] = $autocompleteSettings;

		$textarea_attrs['class'] .= ' autocompleteInput';

		return $textarea_attrs;
	}
}
