<?php
/**
 * PFUploadSourceField - used within PFUploadWindow.
 * This class is heavily based on MediaWiki's UploadSourceField class.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * A form field that contains a radio box in the label
 */

/**
 * @ingroup PFSpecialPages
 */
class PFUploadSourceField extends HTMLTextField {

	/**
	 * @param array $cellAttributes
	 * @return string
	 */
	public function getLabelHtml( $cellAttributes = [] ) {
		$id = "wpSourceType{$this->mParams['upload-type']}";
		$label = Html::rawElement( 'label', [ 'for' => $id ], $this->mLabel );

		if ( !empty( $this->mParams['radio'] ) ) {
			$attribs = [
				'name' => 'wpSourceType',
				'type' => 'radio',
				'id' => $id,
				'value' => $this->mParams['upload-type'],
			];

			if ( !empty( $this->mParams['checked'] ) ) {
				$attribs['checked'] = 'checked';
			}
			$label .= Html::element( 'input', $attribs );
		}

		return Html::rawElement( 'td', [ 'class' => 'mw-label' ] + $cellAttributes, $label );
	}

	/**
	 * @return int
	 */
	public function getSize() {
		return $this->mParams['size'] ?? 60;
	}

}
