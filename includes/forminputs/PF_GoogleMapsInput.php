<?php
/**
 * File holding the PFGoogleMapsInput class
 *
 * @file
 * @ingroup PF
 */

/**
 * The PFGoogleMapsInput class.
 *
 * @ingroup PFFormInput
 */
class PFGoogleMapsInput extends PFOpenLayersInput {
	public static function getName() {
		return 'googlemaps';
	}

	public static function getDefaultCargoTypes() {
		return array();
	}

	public static function getOtherCargoTypesHandled() {
		return array( 'Coordinates' );
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
		global $pfgGoogleMapsKey, $pfgTabIndex, $pfgFieldNum;
		global $wgOut;

		$scripts = array(
			"https://maps.googleapis.com/maps/api/js?v=3.exp&key=$pfgGoogleMapsKey"
		);
		$scriptsHTML = '';
		foreach ( $scripts as $script ) {
			$scriptsHTML .= Html::linkedScript( $script );
		}
		$wgOut->addHeadItem( $scriptsHTML, $scriptsHTML );
		$wgOut->addModules( 'ext.pageforms.maps' );

		$parsedCurValue = PFOpenLayersInput::parseCoordinatesString( $cur_value );

		$coordsInput = Html::element( 'input', array( 'type' => 'text', 'class' => 'pfCoordsInput', 'name' => $input_name, 'value' => $parsedCurValue, 'size' => 40 ) );
		$mapUpdateButton = Html::element( 'input', array( 'type' => 'button', 'class' => 'pfUpdateMap', 'value' => wfMessage( 'pf-maps-setmarker' )->parse() ), null );
		$addressLookupInput = Html::element( 'input', array( 'type' => 'text', 'class' => 'pfAddressInput', 'size' => 40, 'placeholder' => wfMessage( 'pf-maps-enteraddress' )->parse() ), null );
		$addressLookupButton = Html::element( 'input', array( 'type' => 'button', 'class' => 'pfLookUpAddress', 'value' => wfMessage( 'pf-maps-lookupcoordinates' )->parse() ), null );
		$height = self::getHeight( $other_args );
		$width = self::getWidth( $other_args );
		$mapCanvas = Html::element( 'div', array( 'class' => 'pfMapCanvas', 'style' => "height: $height; width: $width;" ), 'Map goes here...' );

		$fullInputHTML = <<<END
<div style="padding-bottom: 10px;">
$coordsInput
$mapUpdateButton
</div>
<div style="padding-bottom: 10px;">
$addressLookupInput
$addressLookupButton
</div>
$mapCanvas

END;
		$text = Html::rawElement( 'div', array( 'class' => 'pfGoogleMapsInput' ), $fullInputHTML );

		return $text;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
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
