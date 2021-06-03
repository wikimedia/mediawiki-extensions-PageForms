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

	public static function getName(): string {
		return 'leaflet';
	}

	public static function getDefaultCargoTypes() {
		return [];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Coordinates' ];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgOut;

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

		$fullInputHTML = self::mapLookupHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args, $height, $width, $fileName == null );

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
