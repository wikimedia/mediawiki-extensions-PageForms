<?php
/**
 * PFUploadWindow - used for uploading files from within a form.
 * This class is nearly identical to MediaWiki's SpecialUpload class, with
 * a few changes to remove skin CSS and HTML, and to populate the relevant
 * field in the form with the name of the uploaded form.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * A form field that contains a radio box in the label.
 */

/**
 * @ingroup PFSpecialPages
 */
class PFUploadSourceField extends HTMLTextField {

	function getLabelHtml( $cellAttributes = array() ) {
		$id = "wpSourceType{$this->mParams['upload-type']}";
		$label = Html::rawElement( 'label', array( 'for' => $id ), $this->mLabel );

		if ( !empty( $this->mParams['radio'] ) ) {
			$attribs = array(
				'name' => 'wpSourceType',
				'type' => 'radio',
				'id' => $id,
				'value' => $this->mParams['upload-type'],
			);

			if ( !empty( $this->mParams['checked'] ) ) {
				$attribs['checked'] = 'checked';
			}
			$label .= Html::element( 'input', $attribs );
		}

		return Html::rawElement( 'td', array( 'class' => 'mw-label' ), $label );
	}

	function getSize() {
		return isset( $this->mParams['size'] )
			? $this->mParams['size']
			: 60;
	}

	/**
	 * This page can be shown if uploading is enabled.
	 * Handle permission checking elsewhere in order to be able to show
	 * custom error messages.
	 *
	 * @param User $user
	 * @return bool
	 */
	public function userCanExecute( User $user ) {
		return UploadBase::isEnabled() && parent::userCanExecute( $user );
	}

}
