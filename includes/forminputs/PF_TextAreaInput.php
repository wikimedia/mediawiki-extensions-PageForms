<?php

/**
 * @file
 * @ingroup PF
 */

use MediaWiki\Html\Html;

/**
 * @ingroup PFFormInput
 */
class PFTextAreaInput extends PFFormInput {

	protected $mEditor = null;

	public static function getName(): string {
		return 'textarea';
	}

	public static function getDefaultCargoTypes() {
		return [
			'Text' => [],
			'Searchtext' => []
		];
	}

	public static function getDefaultCargoTypeLists() {
		return [
			'Text' => [ 'field_type' => 'text', 'is_list' => 'true' ],
			'Searchtext' => [ 'field_type' => 'text', 'is_list' => 'true' ]
		];
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
	public function __construct( $input_number, $cur_value, $input_name, $disabled, array $other_args ) {
		global $wgOut;

		parent::__construct( $input_number, $cur_value, $input_name, $disabled, $other_args );

		$newClasses = null;

		// WikiEditor
		if (
			array_key_exists( 'editor', $this->mOtherArgs ) &&
			$this->mOtherArgs['editor'] == 'wikieditor' &&
			in_array( 'ext.wikiEditor', $wgOut->getResourceLoader()->getModuleNames() )
		) {
			$this->mEditor = 'wikieditor';
			$this->addJsInitFunctionData( 'window.ext.wikieditor.init' );
		}

		// VisualEditor (plus VEForAll)
		if (
			array_key_exists( 'editor', $this->mOtherArgs ) &&
			$this->mOtherArgs['editor'] == 'visualeditor' &&
			ExtensionRegistry::getInstance()->isLoaded( 'VisualEditor' )
		) {
			$this->mEditor = 'visualeditor';
			if ( $input_name != 'pf_free_text' && !array_key_exists( 'isSection', $this->mOtherArgs ) ) {
				$newClasses = 'vePartOfTemplate';
			}
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
		}

		if ( $newClasses == null ) {
			// Do nothing.
		} elseif ( array_key_exists( 'class', $this->mOtherArgs ) ) {
			$this->mOtherArgs['class'] .= ' ' . $newClasses;
		} else {
			$this->mOtherArgs['class'] = $newClasses;
		}
	}

	public static function getDefaultPropTypes() {
		return [ '_cod' => [] ];
	}

	public static function getOtherPropTypesHandled() {
		return [ '_txt', '_wpg' ];
	}

	public static function getOtherPropTypeListsHandled() {
		return [ '_txt', '_wpg' ];
	}

	public static function getParameters() {
		$params = parent::getParameters();

		$params['preload'] = [
			'name' => 'preload',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_preload' )->text()
		];
		$params['rows'] = [
			'name' => 'rows',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_rows' )->text()
		];
		$params['cols'] = [
			'name' => 'cols',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_cols' )->text()
		];
		$params['maxlength'] = [
			'name' => 'maxlength',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_maxlength' )->text()
		];
		$params['placeholder'] = [
			'name' => 'placeholder',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_placeholder' )->text()
		];
		$params['autogrow'] = [
			'name' => 'autogrow',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_autogrow' )->text()
		];
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
		} elseif ( $this->mEditor == 'visualeditor' ) {
			return 'ext.veforall.main';
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
			global $wgOut;
			$wgOut->addModuleStyles( 'ext.wikiEditor.styles' );
			$wgOut->addModules( 'ext.wikiEditor' );
			$className = 'wikieditor ';
		} elseif ( $this->mEditor == 'visualeditor' ) {
			$className = 'visualeditor ';
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

		$textarea_attrs = [
			'tabindex' => $wgPageFormsTabIndex,
			'name' => $this->mInputName,
			'id' => $input_id,
			'class' => $className,
			'rows' => $rows,
		];

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
			$textarea_attrs['maxlength'] = $this->mOtherArgs['maxlength'];
		}

		if ( array_key_exists( 'placeholder', $this->mOtherArgs ) ) {
			$textarea_attrs['placeholder'] = $this->mOtherArgs['placeholder'];
		}
		if ( array_key_exists( 'autocapitalize', $this->mOtherArgs ) ) {
			$textarea_attrs['autocapitalize'] = $this->mOtherArgs['autocapitalize'];
		}
		if ( array_key_exists( 'feeds to map', $this->mOtherArgs ) ) {
			global $wgPageFormsMapsWithFeeders;
			$targetMapName = $this->mOtherArgs['feeds to map'];
			if ( array_key_exists( 'part_of_multiple', $this->mOtherArgs ) ) {
				$targetMapName = str_replace( '[', '[num][', $targetMapName );
			}
			$wgPageFormsMapsWithFeeders[$targetMapName] = true;
			$textarea_attrs['data-feeds-to-map'] = $targetMapName;
		}

		return $textarea_attrs;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText(): string {
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
		if ( $this->mEditor == 'visualeditor' && !$this->mIsDisabled ) {
			$spanClass .= ' ve-area-wrapper';
		}
		$spanAttrs = [ 'class' => $spanClass ];
		if ( $this->mEditor == 'visualeditor' ) {
			// VisualEditor, by default, autogrows with no limit -
			// which is fine in a regular edit page, but not good
			// in a form. So we add a "max height" value, which in
			// turn gets processed by VEForAll into true CSS.
			$maxHeightNumOnly = true;
			if ( array_key_exists( 'max height', $this->mOtherArgs ) ) {
				$maxHeightStr = $this->mOtherArgs['max height'];
				if ( substr( $maxHeightStr, -2 ) == 'em' || substr( $maxHeightStr, -2 ) == 'vh' ) {
					$maxHeightNumOnly = false;
					$maxHeight = $maxHeightStr;
				} else {
					$maxHeight = (int)$maxHeightStr;
				}
			} else {
				$config = RequestContext::getMain()->getConfig();
				$maxHeight = $config->get( 'PageFormsVisualEditorMaxHeight' );
			}
			if ( $maxHeightNumOnly ) {
				$spanAttrs['data-max-height'] = $maxHeight . 'px';
			} else {
				$spanAttrs['data-max-height'] = $maxHeight;
			}
		}

		$text = Html::rawElement( 'span', $spanAttrs, $text );

		return $text;
	}

}
