<?php
/**
 * File holding the SFTextAreaWithAutocompleteInput class
 *
 * @file
 * @ingroup SF
 */

/**
 * The SFTextAreaWithAutocompleteInput class.
 *
 * @ingroup SFFormInput
 */
class SFTextAreaWithAutocompleteInput extends SFTextAreaInput {
	public static function getName() {
		return 'textarea with autocomplete';
	}

	public static function getDefaultPropTypes() {
		return array();
	}

	public static function getOtherPropTypesHandled() {
		return array( '_wpg', '_str' );
	}

	public static function getOtherPropTypeListsHandled() {
		return array( '_wpg', '_str' );
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {

		// TODO: Lots of duplication of code in the parent class. Needs refactoring!

		global $wgOut;

		// If 'no autocomplete' was specified, print a regular
		// textarea instead.
		if ( array_key_exists( 'no autocomplete', $other_args ) &&
				$other_args['no autocomplete'] == true ) {
			unset( $other_args['autocompletion source'] );
			return SFTextAreaInput::getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args );
		}

		global $sfgTabIndex, $sfgFieldNum;

		list( $autocompleteSettings, $remoteDataType, $delimiter ) = SFTextWithAutocompleteInput::setAutocompleteValues( $other_args );

		$input_id = 'input_' . $sfgFieldNum;

		if ( array_key_exists( 'editor', $other_args ) &&
			$other_args['editor'] == 'wikieditor' &&

			method_exists( $wgOut, 'getResourceLoader' ) &&
			in_array( 'jquery.wikiEditor', $wgOut->getResourceLoader()->getModuleNames() ) &&

			class_exists( 'WikiEditorHooks' ) ) {

			// load modules for all enabled features
			WikiEditorHooks::editPageShowEditFormInitial( $this );

			$wgOut->addModules( 'ext.semanticforms.wikieditor' );

			$jstext = <<<JAVASCRIPT
			jQuery( jQuery('#$input_id').SemanticForms_registerInputInit( ext.wikieditor.init, null ) );
JAVASCRIPT;

			// write JS code directly to the page's code
			$wgOut->addScript( Html::inlineScript( $jstext ) );

			$className = "wikieditor ";
		} else {
			$className = "";
		}

		$className .= ( $is_mandatory ) ? 'autocompleteInput mandatoryField' : 'autocompleteInput createboxInput';
		if ( array_key_exists( 'class', $other_args ) ) {
			$className .= ' ' . $other_args['class'];
		}

		if ( array_key_exists( 'rows', $other_args ) ) {
			$rows = $other_args['rows'];
		} else {
			$rows = 5;
		}
		$text = '';
		if ( array_key_exists( 'autogrow', $other_args ) ) {
			$className .= ' autoGrow';
		}

		$textarea_attrs = array(
			'tabindex' => $sfgTabIndex,
			'id' => $input_id,
			'name' => $input_name,
			'rows' => $rows,
			'class' => $className,
			'autocompletesettings' => $autocompleteSettings,
		);

		if ( array_key_exists( 'cols', $other_args ) ) {
			$textarea_attrs['cols'] = $other_args['cols'];
			// Needed to prevent CSS from overriding the manually-
			// set width.
			$textarea_attrs['style'] = 'width: auto';
		} elseif ( array_key_exists( 'autogrow', $other_args ) ) {
			// If 'autogrow' has been set, automatically set
			// the number of columns - otherwise, the Javascript
			// won't be able to know how many characters there
			// are per line, and thus won't work.
			$textarea_attrs['cols'] = 90;
			$textarea_attrs['style'] = 'width: auto';
		} else {
			$textarea_attrs['style'] = 'width: 100%';
		}

		if ( array_key_exists( 'origName', $other_args ) ) {
			$inputAttrs['origName'] = $other_args['origName'];
		}
		if ( !is_null( $remoteDataType ) ) {
			$textarea_attrs['autocompletedatatype'] = $remoteDataType;
		}
		if ( $is_disabled ) {
			$textarea_attrs['disabled'] = 'disabled';
		}
		if ( array_key_exists( 'maxlength', $other_args ) ) {
			$maxlength = $other_args['maxlength'];
			// For every actual character pressed (i.e., excluding
			// things like the Shift key), reduce the string to
			// its allowed length if it's exceeded that.
			// This JS code is complicated so that it'll work
			// correctly in IE - IE moves the cursor to the end
			// whenever this.value is reset, so we'll make sure
			// to do that only when we need to.
			$maxLengthJSCheck = "if (window.event && window.event.keyCode < 48 && window.event.keyCode != 13) return; if (this.value.length > $maxlength) { this.value = this.value.substring(0, $maxlength); }";
			$textarea_attrs['onKeyDown'] = $maxLengthJSCheck;
			$textarea_attrs['onKeyUp'] = $maxLengthJSCheck;
		}
		if ( array_key_exists( 'placeholder', $other_args ) ) {
			$textarea_attrs = $other_args['placeholder'];
		}

		$textarea_input = Html::element( 'textarea', $textarea_attrs, $cur_value );
		$text .= $textarea_input;

		if ( array_key_exists( 'uploadable', $other_args ) && $other_args['uploadable'] == true ) {
			if ( array_key_exists( 'default filename', $other_args ) ) {
				$default_filename = $other_args['default filename'];
			} else {
				$default_filename = '';
			}
			$text .= self::uploadableHTML( $input_id, $delimiter, $default_filename, $cur_value, $other_args );
		}

		$spanClass = 'inputSpan';
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}
		$text = "\n" . Html::rawElement( 'span', array( 'class' => $spanClass ), $text );

		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params = array_merge( $params, SFTextWithAutocompleteInput::getAutocompletionParameters() );
		return $params;
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
