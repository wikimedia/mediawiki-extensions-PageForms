<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFGoogleMapsInput extends PFOpenLayersInput {
	public static function getName() {
		return 'googlemaps';
	}

	public static function getDefaultCargoTypes() {
		return [];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Coordinates' ];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsGoogleMapsKey, $wgPageFormsTabIndex;
		global $wgOut, $wgPageFormsMapsWithFeeders;

		$scripts = [
			"https://maps.googleapis.com/maps/api/js?v=3.exp&key=$wgPageFormsGoogleMapsKey"
		];
		$scriptsHTML = '';
		foreach ( $scripts as $script ) {
			$scriptsHTML .= Html::linkedScript( $script );
		}
		$wgOut->addHeadItem( $scriptsHTML, $scriptsHTML );
		$wgOut->addModules( 'ext.pageforms.maps' );
		$coordsInputAttrs = [
			'type' => 'text',
			'tabindex' => $wgPageFormsTabIndex,
			'class' => 'pfCoordsInput',
			'name' => $input_name,
			'value' => PFOpenLayersInput::parseCoordinatesString( $cur_value ),
			'size' => 40
		];
		if ( array_key_exists( 'starting bounds', $other_args ) ) {
			$boundCoords = $other_args['starting bounds'];
			$boundCoords = explode( ";", $boundCoords );
			$boundCoords[0] = PFOpenLayersInput::parseCoordinatesString( $boundCoords[0] );
			$boundCoords[1] = PFOpenLayersInput::parseCoordinatesString( $boundCoords[1] );
			$coordsInputAttrs['data-bound-coords'] = "$boundCoords[0];$boundCoords[1]";
		}
		$coordsInput = Html::element( 'input', $coordsInputAttrs );
		$wgPageFormsTabIndex++;
		// The address input box is not necessary if we are using other form inputs for the address.
		if ( array_key_exists( $input_name, $wgPageFormsMapsWithFeeders ) ) {
			$addressLookupInput = '';
		} else {
			$addressLookupInput = Html::element( 'input', [ 'type' => 'text', 'tabindex' => $wgPageFormsTabIndex, 'class' => 'pfAddressInput', 'size' => 40, 'placeholder' => wfMessage( 'pf-maps-enteraddress' )->parse() ], null );
		}
		$addressLookupButton = Html::element( 'input', [ 'type' => 'button', 'class' => 'pfLookUpAddress', 'value' => wfMessage( 'pf-maps-lookupcoordinates' )->parse() ], null );
		$height = self::getHeight( $other_args );
		$width = self::getWidth( $other_args );
		$mapCanvas = Html::element( 'div', [ 'class' => 'pfMapCanvas', 'style' => "height: $height; width: $width;" ], 'Map goes here...' );

		$fullInputHTML = <<<END
<div style="padding-bottom: 10px;">
$addressLookupInput
$addressLookupButton
</div>
<div style="padding-bottom: 10px;">
$coordsInput
</div>
$mapCanvas

END;
		$text = Html::rawElement( 'div', [ 'class' => 'pfGoogleMapsInput' ], $fullInputHTML );

		return $text;
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
