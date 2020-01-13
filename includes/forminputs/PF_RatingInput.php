<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFRatingInput extends PFFormInput {
	public static function getName() {
		return 'rating';
	}

	public static function getOtherPropTypesHandled() {
		return [ '_num' ];
	}

	public static function getDefaultCargoTypes() {
		return [
			'Rating' => []
		];
	}

	public static function getParameters() {
		$params = parent::getParameters();

		$params['star width'] = [
			'name' => 'star width',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_starwidth' )->text()
		];
		$params['num stars'] = [
			'name' => 'num stars',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_numstars' )->text()
		];
		$params['allow half stars'] = [
			'name' => 'allow half stars',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_allowhalfstars' )->text()
		];

		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText() {
		global $wgPageFormsFieldNum;

		$className = 'pfRating';
		if ( $this->mIsMandatory ) {
			$className .= ' mandatoryField';
		}
		if ( array_key_exists( 'class', $this->mOtherArgs ) ) {
			$className .= ' ' . $this->mOtherArgs['class'];
		}
		$input_id = "input_$wgPageFormsFieldNum";
		$ratingAttrs = [
			'class' => $className,
			// Not useful, since the rating can't be modified
			// via the keyboard.
			// 'tabindex' => $wgPageFormsTabIndex,
			'data-curvalue' => $this->mCurrentValue

		];
		if ( $this->mIsDisabled ) {
			$ratingAttrs['disabled'] = 'disabled';
		}
		if ( array_key_exists( 'origName', $this->mOtherArgs ) ) {
			$ratingAttrs['origname'] = $this->mOtherArgs['origName'];
		}
		if ( array_key_exists( 'star width', $this->mOtherArgs ) ) {
			$ratingAttrs['data-starwidth'] = $this->mOtherArgs['star width'];
		} else {
			$ratingAttrs['data-starwidth'] = '24px';
		}
		if ( array_key_exists( 'num stars', $this->mOtherArgs ) ) {
			$ratingAttrs['data-numstars'] = $this->mOtherArgs['num stars'];
		} else {
			$ratingAttrs['data-numstars'] = 5;
		}
		if ( array_key_exists( 'allow half stars', $this->mOtherArgs ) ) {
			$ratingAttrs['data-allows-half'] = true;
		}
		$hiddenInputAttrs = [
			'id' => $input_id
		];

		$hiddenInput = Html::hidden(
			$this->mInputName,
			$this->mCurrentValue,
			$hiddenInputAttrs
		);

		$text = Html::element( 'div', $ratingAttrs ) . ' ' . $hiddenInput;
		// For some reason this wrapper has to be a div, not a span,
		// or else the HTML gets messed up.
		$text = Html::rawElement( 'div', [ 'class' => 'pfRatingWrapper' ], $text );

		return $text;
	}
}
