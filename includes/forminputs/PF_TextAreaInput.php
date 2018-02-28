<?php

/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFTextAreaInput extends PFFormInput {

	protected $mEditor = null;

	public static function getDefaultCargoTypes() {
		return array( 'Text' => array() );
	}

	public static function getDefaultCargoTypeLists() {
		return array(
			'Text' => array( 'field_type' => 'text', 'is_list' => 'true' )
		);
	}

	/**
	 * @param string $input_number The number of the input in the form. For a simple HTML input
	 *  element this should end up in the id attribute in the format 'input_<number>'.
	 * @param string $cur_value The current value of the input field. For a simple HTML input
	 *  element this should end up in the value attribute.
	 * @param string $input_name The name of the input. For a simple HTML input element this should
	 *  end up in the name attribute.
	 * @param bool $disabled Is this input disabled?
	 * @param array $other_args An associative array of other parameters that were present in the
	 *  input definition.
	 */
	public function __construct( $input_number, $cur_value, $input_name, $disabled, $other_args ) {
		global $wgOut;

		parent::__construct( $input_number, $cur_value, $input_name, $disabled, $other_args );

		// WikiEditor
		if (
			array_key_exists( 'editor', $this->mOtherArgs ) &&
			$this->mOtherArgs['editor'] == 'wikieditor' &&
			in_array( 'ext.wikiEditor', $wgOut->getResourceLoader()->getModuleNames() ) &&
			class_exists( 'WikiEditorHooks' )
		) {
			$this->mEditor = 'wikieditor';
			$this->addJsInitFunctionData( 'window.ext.wikieditor.init' );
		}

		// TinyMCE
		if (
			array_key_exists( 'editor', $this->mOtherArgs ) &&
			$this->mOtherArgs['editor'] == 'tinymce'
		) {
			$this->mEditor = 'tinymce';
			global $wgTinyMCEEnabled;
			$wgTinyMCEEnabled = true;
			$newClasses = 'mceMinimizeOnBlur';
			if ( $input_name != 'pf_free_text' && !array_key_exists( 'isSection', $this->mOtherArgs ) ) {
				$newClasses .= ' mcePartOfTemplate';
			}
			if ( array_key_exists( 'class', $this->mOtherArgs ) ) {
				$this->mOtherArgs['class'] .= ' ' . $newClasses;
			} else {
				$this->mOtherArgs['class'] = $newClasses;
			}
		}
	}

	public static function getName() {
		return 'textarea';
	}

	public static function getDefaultPropTypes() {
		$defaultPropTypes = array( '_cod' => array() );
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			$defaultPropTypes['_txt'] = array();
		}
		return $defaultPropTypes;
	}

	public static function getOtherPropTypesHandled() {
		$otherPropTypesHandled = array( '_wpg' );
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			$otherPropTypesHandled[] = '_str';
		} else {
			$otherPropTypesHandled[] = '_txt';
		}
		return $otherPropTypesHandled;
	}

	public static function getOtherPropTypeListsHandled() {
		$otherPropTypeListsHandled = array( '_wpg' );
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			$otherPropTypeListsHandled[] = '_str';
		} else {
			$otherPropTypeListsHandled[] = '_txt';
		}
		return $otherPropTypeListsHandled;
	}

	public static function getParameters() {
		$params = parent::getParameters();

		$params['preload'] = array(
			'name' => 'preload',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_preload' )->text()
		);
		$params['rows'] = array(
			'name' => 'rows',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_rows' )->text()
		);
		$params['cols'] = array(
			'name' => 'cols',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_cols' )->text()
		);
		$params['maxlength'] = array(
			'name' => 'maxlength',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_maxlength' )->text()
		);
		$params['placeholder'] = array(
			'name' => 'placeholder',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_placeholder' )->text()
		);
		$params['autogrow'] = array(
			'name' => 'autogrow',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_autogrow' )->text()
		);
		return $params;
	}

	/**
	 * Returns the names of the resource modules this input type uses.
	 *
	 * Returns the names of the modules as an array or - if there is only one
	 * module - as a string.
	 *
	 * @return null|string|array
	 */
	public function getResourceModuleNames() {
		if ( $this->mEditor == 'wikieditor' ) {
			return 'ext.pageforms.wikieditor';
		} elseif ( $this->mEditor == 'tinymce' ) {
			return 'ext.tinymce';
		} else {
			return null;
		}
	}

	protected function getTextAreaAttributes() {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;

		// Use a special ID for the free text field -
		// this was originally done for FCKeditor, but maybe it's
		// useful for other stuff too.
		$input_id = $this->mInputName == 'pf_free_text' ? 'pf_free_text' : "input_$wgPageFormsFieldNum";

		if ( $this->mEditor == 'wikieditor' ) {
			// Load modules for all enabled WikiEditor features.
			// The header for this function was changed in July
			// 2014, and the function itself was changed
			// significantly in March 2015 - this call should
			// hopefully work for all versions.
			global $wgTitle, $wgOut;
			$article = new Article( $wgTitle );
			$editPage = new EditPage( $article );
			WikiEditorHooks::editPageShowEditFormInitial( $editPage, $wgOut );
			$className = 'wikieditor ';
		} elseif ( $this->mEditor == 'tinymce' ) {
			$className = 'tinymce ';
		} else {
			$className = '';
		}

		$className .= ( $this->mIsMandatory ) ? 'mandatoryField' : 'createboxInput';
		if ( array_key_exists( 'unique', $this->mOtherArgs ) ) {
			$className .= ' uniqueField';
		}

		if ( array_key_exists( 'class', $this->mOtherArgs ) ) {
			$className .= ' ' . $this->mOtherArgs['class'];
		}

		if ( array_key_exists( 'autogrow', $this->mOtherArgs ) ) {
			$className .= ' autoGrow';
		}

		if ( array_key_exists( 'rows', $this->mOtherArgs ) ) {
			$rows = $this->mOtherArgs['rows'];
		} else {
			$rows = 5;
		}

		$textarea_attrs = array(
			'tabindex' => $wgPageFormsTabIndex,
			'name' => $this->mInputName,
			'id' => $input_id,
			'class' => $className,
			'rows' => $rows,
		);

		if ( array_key_exists( 'cols', $this->mOtherArgs ) ) {
			$textarea_attrs['cols'] = $this->mOtherArgs['cols'];
			// Needed to prevent CSS from overriding the manually-
			// set width.
			$textarea_attrs['style'] = 'width: auto';
		} elseif ( array_key_exists( 'autogrow', $this->mOtherArgs ) ) {
			// If 'autogrow' has been set, automatically set
			// the number of columns - otherwise, the Javascript
			// won't be able to know how many characters there
			// are per line, and thus won't work.
			$textarea_attrs['cols'] = 90;
			$textarea_attrs['style'] = 'width: auto';
		} else {
			$textarea_attrs['cols'] = 90;
			$textarea_attrs['style'] = 'width: 100%';
		}

		if ( $this->mIsDisabled ) {
			$textarea_attrs['disabled'] = 'disabled';
		}

		if ( array_key_exists( 'maxlength', $this->mOtherArgs ) ) {
			$maxlength = $this->mOtherArgs['maxlength'];
			// For every actual character pressed (i.e., excluding
			// things like the Shift key), reduce the string to its
			// allowed length if it's exceeded that.
			// This JS code is complicated so that it'll work
			// correctly in IE - IE moves the cursor to the end
			// whenever this.value is reset, so we'll make sure to
			// do that only when we need to.
			$maxLengthJSCheck = "if (window.event && window.event.keyCode < 48 && window.event.keyCode != 13) return; if (this.value.length > $maxlength) { this.value = this.value.substring(0, $maxlength); }";
			$textarea_attrs['onKeyDown'] = $maxLengthJSCheck;
			$textarea_attrs['onKeyUp'] = $maxLengthJSCheck;
		}

		if ( array_key_exists( 'placeholder', $this->mOtherArgs ) ) {
			$textarea_attrs['placeholder'] = $this->mOtherArgs['placeholder'];
		}
		if ( array_key_exists( 'feeds to map', $this->mOtherArgs ) ) {
			global $wgPageFormsMapsWithFeeders;
			$targetMapName = $this->mOtherArgs['feeds to map'];
			$wgPageFormsMapsWithFeeders[$targetMapName] = true;
			$textarea_attrs['data-feeds-to-map'] = $targetMapName;
		}

		return $textarea_attrs;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText() {
		$textarea_attrs = $this->getTextAreaAttributes();

		$text = Html::element( 'textarea', $textarea_attrs, $this->mCurrentValue );
		$spanClass = 'inputSpan';
		if ( $this->mInputName == 'pf_free_text' ) {
			$spanClass .= ' freeText';
		}
		if ( array_key_exists( 'isSection', $this->mOtherArgs ) ) {
			$spanClass .= ' pageSection';
		}
		if ( $this->mIsMandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}
		if ( array_key_exists( 'unique', $this->mOtherArgs ) ) {
			$spanClass .= ' uniqueFieldSpan';
		}
		$text = Html::rawElement( 'span', array( 'class' => $spanClass ), $text );

		return $text;
	}

}
