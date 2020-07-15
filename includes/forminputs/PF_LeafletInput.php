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
		return [];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Coordinates' ];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex;
		global $wgOut, $wgPageFormsMapsWithFeeders;

		$scripts = [
			"https://unpkg.com/leaflet@1.1.0/dist/leaflet.js"
		];
		$styles = [
			"https://unpkg.com/leaflet@1.1.0/dist/leaflet.css"
		];
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
			$addressLookupInputAttrs = [
				'type' => 'text',
				'tabindex' => $wgPageFormsTabIndex++,
				'class' => 'pfAddressInput',
				'size' => 40,
				'placeholder' => wfMessage( 'pf-maps-enteraddress' )->parse()
			];
			$addressLookupInput = Html::element( 'input', $addressLookupInputAttrs, null );
		}
		$addressLookupButtonAttrs = [
			'type' => 'button',
			'tabindex' => $wgPageFormsTabIndex++,
			'class' => 'pfLookUpAddress',
			'value' => wfMessage( 'pf-maps-lookupcoordinates' )->parse()
		];
		$addressLookupButton = Html::element( 'input', $addressLookupButtonAttrs, null );

		$coordsInputAttrs = [
			'type' => 'text',
			'tabindex' => $wgPageFormsTabIndex++,
			'class' => 'pfCoordsInput',
			'name' => $input_name,
			'value' => PFOpenLayersInput::parseCoordinatesString( $cur_value ),
			'size' => 40
		];
		$coordsInput = Html::element( 'input', $coordsInputAttrs );

		if ( array_key_exists( 'image', $other_args ) ) {
			global $wgUploadDirectory;
			$fileName = $other_args['image'];
			$fileTitle = Title::makeTitleSafe( NS_FILE, $fileName );
			$imagePage = new ImagePage( $fileTitle );
			$file = $imagePage->getDisplayedFile();
			$filePath = $wgUploadDirectory . '/' . $file->getUrlRel();
			list( $imageWidth, $imageHeight, $type, $attr ) = getimagesize( $filePath );
			if ( !array_key_exists( 'height', $other_args ) && !array_key_exists( 'width', $other_args ) ) {
				// Scale down image if it's huge.
				$maxDimension = max( $imageHeight, $imageWidth );
				$maxAllowedSize = 1000;
				if ( $maxDimension > $maxAllowedSize ) {
					$imageHeight *= $maxAllowedSize / $maxDimension;
					$imageWidth *= $maxAllowedSize / $maxDimension;
				}
				$height = $imageHeight . 'px';
				$width = $imageWidth . 'px';
			} else {
				$height = self::getHeight( $other_args );
				$width = self::getWidth( $other_args );
				// Reduce image height and width if necessary,
				// to fit it into the display.
				$heightRatio = (int)$height / $imageHeight;
				$widthRatio = (int)$width / $imageWidth;
				$smallerRatio = min( $heightRatio, $widthRatio );
				if ( $smallerRatio < 1 ) {
					$imageHeight *= $smallerRatio;
					$imageWidth *= $smallerRatio;
				}
			}
		} else {
			$fileName = null;
			$height = self::getHeight( $other_args );
			$width = self::getWidth( $other_args );
		}

		$mapCanvas = Html::element( 'div', [ 'class' => 'pfMapCanvas', 'style' => "height: $height; width: $width;" ], 'Map goes here...' );

		$fullInputHTML = '';
		if ( !array_key_exists( 'image', $other_args ) ) {
			$fullInputHTML .= <<<END
<div style="padding-bottom: 10px;">
$addressLookupInput
$addressLookupButton
</div>

END;
		}
		$fullInputHTML .= <<<END
<div style="padding-bottom: 10px;">
$coordsInput
</div>
$mapCanvas

END;

		$divAttrs = [ 'class' => 'pfLeafletInput' ];
		if ( $fileName !== null ) {
			$divAttrs['data-image-path'] = $file->getUrl();
			$divAttrs['data-height'] = $imageHeight;
			$divAttrs['data-width'] = $imageWidth;
		}

		$text = Html::rawElement( 'div', $divAttrs, $fullInputHTML );

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
