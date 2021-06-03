<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFGoogleMapsInput extends PFOpenLayersInput {

	public static function getName(): string {
		return 'googlemaps';
	}

	public static function getDefaultCargoTypes() {
		return [];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Coordinates' ];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsGoogleMapsKey, $wgOut;

		$scripts = [
			"https://maps.googleapis.com/maps/api/js?v=3.exp&key=$wgPageFormsGoogleMapsKey"
		];
		$scriptsHTML = '';
		foreach ( $scripts as $script ) {
			$scriptsHTML .= Html::linkedScript( $script );
		}
		$wgOut->addHeadItem( $scriptsHTML, $scriptsHTML );
		$wgOut->addModules( 'ext.pageforms.maps' );

		$height = self::getHeight( $other_args );
		$width = self::getWidth( $other_args );
		$fullInputHTML = self::mapLookupHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args, $height, $width );
		$text = Html::rawElement( 'div', [ 'class' => 'pfGoogleMapsInput' ], $fullInputHTML );

		return $text;
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
