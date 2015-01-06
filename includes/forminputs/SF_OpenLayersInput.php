<?php
/**
 * File holding the SFOpenLayersInput class
 *
 * @file
 * @ingroup SF
 */

/**
 * The SFOpenLayersInput class.
 *
 * @ingroup SFFormInput
 */
class SFOpenLayersInput extends SFFormInput {
	public static function getName() {
		return 'openlayers';
	}

	public static function getDefaultPropTypes() {
		return array();
	}

	public static function getDefaultCargoTypes() {
		return array( 'Coordinates' );
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
		global $sfgTabIndex, $sfgFieldNum;
		global $wgOut;

		$scripts = array(
			"http://www.openlayers.org/api/OpenLayers.js"
		);
		$scriptsHTML = '';
		foreach ( $scripts as $script ) {
			$scriptsHTML .= Html::linkedScript( $script );
		}
		$wgOut->addHeadItem( $scriptsHTML, $scriptsHTML );
		$wgOut->addModules( 'ext.semanticforms.maps' );

		$coordsInput = Html::element( 'input', array( 'type' => 'text', 'class' => 'sfCoordsInput', 'name' => $input_name, 'size' => 40 ) );
		$mapUpdateButton = Html::element( 'input', array( 'type' => 'button', 'class' => 'sfUpdateMap', 'value' => wfMessage( 'sf-maps-setmarker' )->parse() ), null );
		// For OpenLayers, doing an address lookup, i.e. a geocode,
		// will require a separate geocoding address, which may
		// require a server-side reader to access that API.
		// For now, let's just not do this, since the Google Maps
		// input is much more widely used anyway.
		// @TODO - add this in.
		//$addressLookupInput = Html::element( 'input', array( 'type' => 'text', 'class' => 'sfAddressInput', 'size' => 40, 'placeholder' => wfMessage( 'sf-maps-enteraddress' )->parse() ), null );
		//$addressLookupButton = Html::element( 'input', array( 'type' => 'button', 'class' => 'sfLookUpAddress', 'value' => wfMessage( 'sf-maps-lookupcoordinates' )->parse() ), null );
		$mapCanvas = Html::element( 'div', array( 'class' => 'sfMapCanvas', 'id' => 'sfMapCanvas' . $sfgFieldNum, 'style' => 'height: 500px; width: 500px;' ), null );

		$fullInputHTML = <<<END
<div style="padding-bottom: 10px;">
$coordsInput
$mapUpdateButton
</div>

END;
/*
		$fullInputHTML = <<<END
<div style="padding-bottom: 10px;">
$addressLookupInput
$addressLookupButton
</div>

END;
*/
		$fullInputHTML .= "$mapCanvas\n";
		$text = Html::rawElement( 'div', array( 'class' => 'sfOpenLayersInput' ), $fullInputHTML );

		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		return $params;
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
