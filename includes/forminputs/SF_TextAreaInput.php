<?php

/**
 * File holding the SFTextAreaInput class
 *
 * @file
 * @ingroup SF
 */

/**
 * The SFTextAreaInput class.
 *
 * @ingroup SFFormInput
 */
class SFTextAreaInput extends SFFormInput {

	public static function getName() {
		return 'textarea';
	}

	public static function getDefaultPropTypes() {
		return array( '_txt' => array(), '_cod' => array() );
	}

	public static function getOtherPropTypesHandled() {
		return array( '_wpg', '_str' );
	}

	public static function getOtherPropTypeListsHandled() {
		return array( '_wpg', '_str' );
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {

		global $wgOut;
		global $sfgTabIndex, $sfgFieldNum;

		// Use a special ID for the free text field, for FCK's needs.
		$input_id = $input_name == 'free_text' ? 'free_text' : "input_$sfgFieldNum";

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

		$className .= ( $is_mandatory ) ? 'mandatoryField' : 'createboxInput';
		if ( array_key_exists( 'class', $other_args ) ) {
			$className .= " " . $other_args['class'];
		}

		if ( array_key_exists( 'rows', $other_args ) ) {
			$rows = $other_args['rows'];
		} else {
			$rows = 5;
		}

		if ( array_key_exists( 'autogrow', $other_args ) ) {
			$className .= ' autoGrow';
		}

		$textarea_attrs = array(
			'tabindex' => $sfgTabIndex,
			'id' => $input_id,
			'name' => $input_name,
			'rows' => $rows,
			'class' => $className,
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

		if ( $is_disabled ) {
			$textarea_attrs['disabled'] = 'disabled';
		}
		if ( array_key_exists( 'maxlength', $other_args ) ) {
			$maxlength = $other_args['maxlength'];
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
		if ( array_key_exists( 'placeholder', $other_args ) ) {
			$textarea_attrs['placeholder'] = $other_args['placeholder'];
		}

		$text = Html::element( 'textarea', $textarea_attrs, $cur_value );
		$spanClass = 'inputSpan';
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}
		$text = Html::rawElement( 'span', array( 'class' => $spanClass ), $text );

		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = array(
			'name' => 'preload',
			'type' => 'string',
			'description' => wfMsg( 'sf_forminputs_preload' )
		);
		$params[] = array(
			'name' => 'rows',
			'type' => 'int',
			'description' => wfMsg( 'sf_forminputs_rows' )
		);
		$params[] = array(
			'name' => 'cols',
			'type' => 'int',
			'description' => wfMsg( 'sf_forminputs_cols' )
		);
		$params[] = array(
			'name' => 'maxlength',
			'type' => 'int',
			'description' => wfMsg( 'sf_forminputs_maxlength' )
		);
		$params[] = array(
			'name' => 'placeholder',
			'type' => 'string',
			'description' => wfMsg( 'sf_forminputs_placeholder' )
		);
		$params[] = array(
			'name' => 'autogrow',
			'type' => 'boolean',
			'description' => wfMsg( 'sf_forminputs_autogrow' )
		);
		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 */
	public function getHtmlText() {

		return self::getHTML(
				$this->mCurrentValue, $this->mInputName, $this->mIsMandatory, $this->mIsDisabled, $this->mOtherArgs
		);
	}

}
