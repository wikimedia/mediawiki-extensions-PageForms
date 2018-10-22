<?php
/**
 * @author Peter Grassberger
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFLeafletInput extends PFOpenLayersInput {
	public static function getName() {
		return 'leaflet';
	}

	public static function getDefaultCargoTypes() {
		return array();
	}

	public static function getOtherCargoTypesHandled() {
		return array( 'Coordinates' );
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
		global $wgPageFormsTabIndex;
		global $wgOut, $wgPageFormsMapsWithFeeders;

		$scripts = array(
			"https://unpkg.com/leaflet@1.1.0/dist/leaflet.js"
		);
		$styles = array(
			"https://unpkg.com/leaflet@1.1.0/dist/leaflet.css"
		);
		$scriptsHTML = '';
		$stylesHTML = '';
		foreach ( $scripts as $script ) {
			$scriptsHTML .= Html::linkedScript( $script );
		}
		foreach ( $styles as $style ) {
			$stylesHTML .= Html::linkedStyle( $style );
		}
		$wgOut->addHeadItem( $scriptsHTML, $scriptsHTML );
		$wgOut->addHeadItem( $stylesHTML, $stylesHTML );
		$wgOut->addModules( 'ext.pageforms.maps' );

		// The address input box is not necessary if we are using other form inputs for the address.
		if ( array_key_exists( $input_name, $wgPageFormsMapsWithFeeders ) ) {
			$addressLookupInput = '';
		} else {
			$addressLookupInputAttrs = array(
				'type' => 'text',
				'tabindex' => $wgPageFormsTabIndex++,
				'class' => 'pfAddressInput',
				'size' => 40,
				'placeholder' => wfMessage( 'pf-maps-enteraddress' )->parse()
			);
			$addressLookupInput = Html::element( 'input', $addressLookupInputAttrs, null );
		}
		$addressLookupButtonAttrs = array(
			'type' => 'button',
			'tabindex' => $wgPageFormsTabIndex++,
			'class' => 'pfLookUpAddress',
			'value' => wfMessage( 'pf-maps-lookupcoordinates' )->parse()
		);
		$addressLookupButton = Html::element( 'input', $addressLookupButtonAttrs, null );

		$coordsInputAttrs = array(
			'type' => 'text',
			'tabindex' => $wgPageFormsTabIndex++,
			'class' => 'pfCoordsInput',
			'name' => $input_name,
			'value' => PFOpenLayersInput::parseCoordinatesString( $cur_value ),
			'size' => 40
		);
		$coordsInput = Html::element( 'input', $coordsInputAttrs );

		$height = self::getHeight( $other_args );
		$width = self::getWidth( $other_args );
		$mapCanvas = Html::element( 'div', array( 'class' => 'pfMapCanvas', 'style' => "height: $height; width: $width;" ), 'Map goes here...' );

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
		$text = Html::rawElement( 'div', array( 'class' => 'pfLeafletInput' ), $fullInputHTML );

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
