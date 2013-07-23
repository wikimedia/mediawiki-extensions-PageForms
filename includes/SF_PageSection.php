<?php
/**
 * Represents a page section in a user-defined form.
 * @author Himeshi
 * @file
 * @ingroup SF
 */
class SFPageSection {
	private $mSectionName;
	private $mSectionLevel;
	private $mIsMandatory;
	private $mIsHidden;
	private $mIsRestricted;

	static function create( $section_name ) {
		$ps = new SFPageSection();
		$ps->mSectionName = $section_name;
		$ps->mSectionLevel = 2;
		$ps->mIsMandatory = false;
		$ps->mIsHidden = false;
		$ps->mIsRestricted = false;

		return $ps;
	}

	public function getSectionName() {
		return $this->mSectionName;
	}

	public function getSectionLevel() {
		return $this->mSectionLevel;
	}

	public function setSectionLevel( $section_level ) {
		$this->mSectionLevel = $section_level;
	}

	public function setIsMandatory( $isMandatory ) {
		$this->mIsMandatory = $isMandatory;
	}

	public function setIsHidden( $isHidden ) {
		$this->mIsHidden = $isHidden;
	}

	public function setIsRestricted( $isRestricted ) {
		$this->mIsRestricted = $isRestricted;
	}

	function creationHTML( $section_count ) {
		$section_name = $this->mSectionName;
		$section_level = $this->mSectionLevel;

		$section_str = wfMessage( 'sf_createform_pagesection' )->text() . " '" . $section_name . "'";
		$text = <<<END
	<input type="hidden" name="section_$section_count" value="$section_name">
	<div class="sectionForm">
	<h2>$section_str</h2>

END;
		$paramValues = $this->getParamValues();
		$header_options =  '';
		$text .= Html::rawElement( 'span', null, wfMessage( 'sf_createform_sectionlevel' )->text() ) . "\n";
		for ( $i = 1; $i < 7; $i++ ) {
			if ( $section_level == $i ) {
				$header_options .= " " . Html::element( 'option', array( 'value' => $i, 'selected' ), $i ) . "\n";
			} else {
				$header_options .= " " . Html::element( 'option', array( 'value' => $i ), $i ) . "\n";
			}
		}
		$text .= Html::rawElement( 'select', array( 'name' => "sectionlevel_" . $section_count ), $header_options ) . "\n";
		$text .= Html::rawElement( 'div', array(),
		SFCreateForm::showSectionParameters( $section_count, $paramValues ) ) . "\n";

		$removeSectionButton = Html::input( 'delsection_' . $section_count, wfMessage( 'sf_createform_removesection' )->text(), 'submit' ) . "\n";
		$text .= "</br>" . Html::rawElement( 'p', null, $removeSectionButton ) . "\n";
		$text .= "	</div>\n";

		return $text;
	}

	function createMarkup() {
		$section_name = $this->mSectionName;
		$section_level = $this->mSectionLevel;
		// Set default section level to 2
		if ( $section_level == '' ){
			$section_level = 2;
		}
		//display the section headers in wikitext
		$header_string = "";
		$header_string .= str_repeat( "=", $section_level );
		$text = $header_string . $section_name . $header_string . "\n";

		$text .= "{{{section|" . $section_name . "|level=" . $section_level;

		if ( $this->mIsMandatory ) {
			$text .= "|mandatory";
		} elseif ( $this->mIsRestricted ) {
			$text .= "|restricted";
		} elseif ( $this->mIsHidden ) {
			$text .= "|hidden";
		}
		$text .= "}}}\n";

		return $text;
	}

	public static function getParameters() {
		$params = array();
		$params['mandatory'] = array(
			'name' => 'mandatory',
			'type' => 'boolean',
			'description' => wfMessage( 'sf_forminputs_mandatory' )->text()
		);
		$params['restricted'] = array(
			'name' => 'restricted',
			'type' => 'boolean',
			'description' => wfMessage( 'sf_forminputs_restricted' )->text()
		);
		$params['hidden'] = array(
			'name' => 'hidden',
			'type' => 'boolean',
			'description' => wfMessage( 'sf_createform_hiddensection' )->text()
		);
		return $params;
	}

	function getParamValues() {
		$paramValues = array();
		$paramValues['restricted'] = $this->mIsRestricted;
		$paramValues['hidden'] =  $this->mIsHidden;
		$paramValues['mandatory'] = $this->mIsMandatory;
		return $paramValues;
	}
}
