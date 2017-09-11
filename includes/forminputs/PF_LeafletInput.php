<?php
/**
 * File holding the PFLeafletInput class
 *
 * @author Peter Grassberger
 * @file
 * @ingroup PF
 */

/**
 * The PFLeafletInput class.
 *
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
		global $wgOut;

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

		$coordsInputAttrs = array(
			'type' => 'text',
			'tabindex' => $wgPageFormsTabIndex,
			'class' => 'pfCoordsInput',
			'name' => $input_name,
			'value' => PFOpenLayersInput::parseCoordinatesString( $cur_value ),
			'size' => 40
		);
		$coordsInput = Html::element( 'input', $coordsInputAttrs );
		//$wgPageFormsTabIndex++;
		$height = self::getHeight( $other_args );
		$width = self::getWidth( $other_args );
		$mapCanvas = Html::element( 'div', array( 'class' => 'pfMapCanvas', 'style' => "height: $height; width: $width;" ), 'Map goes here...' );

		$fullInputHTML = <<<END
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