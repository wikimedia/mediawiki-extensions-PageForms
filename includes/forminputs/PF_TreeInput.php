<?php
/**
 * @file
 * @ingroup PF
 *
 * @author Yaron Koren
 * @author Mathias Lidal
 * @author Amr El-Absy
 */

/**
 * @ingroup PFFormInput
 */
class PFTreeInput extends PFFormInput {

	private static $multipleSelect = false;

	public static function getName(): string {
		return 'tree';
	}

	public static function getOtherPropTypesHandled() {
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			return [ '_str', '_wpg' ];
		} else {
			return [ '_txt', '_wpg' ];
		}
	}

	public static function getOtherPropTypeListsHandled() {
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			return [ '_str', '_wpg' ];
		} else {
			return [ '_txt', '_wpg' ];
		}
	}

	public static function getDefaultCargoTypes() {
		return [
			'Hierarchy' => []
		];
	}

	public static function getDefaultCargoTypeLists() {
		return [
			'Hierarchy' => []
		];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'String', 'Page' ];
	}

	public static function getOtherCargoTypeListsHandled() {
		return [ 'String', 'Page' ];
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		// Handle the now-deprecated 'category' and 'categories'
		// input types.
		if ( array_key_exists( 'input type', $other_args ) && $other_args['input type'] == 'category' ) {
			self::$multipleSelect = false;
		} elseif ( array_key_exists( 'input type', $other_args ) && $other_args['input type'] == 'categories' ) {
			self::$multipleSelect = true;
		} else {
			$is_list = ( array_key_exists( 'is_list', $other_args ) && $other_args['is_list'] == true );
			if ( $is_list ) {
				self::$multipleSelect = true;
			} else {
				self::$multipleSelect = false;
			}
		}

		// get list delimiter - default is comma
		if ( array_key_exists( 'delimiter', $other_args ) ) {
			$delimiter = $other_args['delimiter'];
		} else {
			$delimiter = ',';
		}

		$cur_values = PFValuesUtils::getValuesArray( $cur_value, $delimiter );
		if ( array_key_exists( 'height', $other_args ) ) {
			$height = Sanitizer::checkCSS( $other_args['height'] );
		} else {
			$height = '100';
		}
		if ( array_key_exists( 'width', $other_args ) ) {
			$width = Sanitizer::checkCSS( $other_args['width'] );
		} else {
			$width = '500';
		}

		if ( array_key_exists( 'depth', $other_args ) ) {
			$depth = $other_args['depth'];
		} else {
			$depth = '10';
		}

		if ( array_key_exists( 'top category', $other_args ) ) {
			$top_category = $other_args['top category'];

			$title = self::makeTitle( $top_category );
			if ( $title->getNamespace() != NS_CATEGORY ) {
				return null;
			}
			$hideroot = array_key_exists( 'hideroot', $other_args );

			$pftree = new PFTree( $depth, $cur_values );
			$pftree->getFromTopCategory( $top_category, $hideroot );
		} elseif ( array_key_exists( 'structure', $other_args ) ) {
			$structure = $other_args['structure'];

			$pftree = new PFTree( $depth, $cur_values );
			$pftree->getTreeFromWikiText( $structure );

		} else {
			// Escape - we can't do anything.
			return null;
		}

		$cur_value = implode( $delimiter, $pftree->current_values );
		$params = [
			'multiple' => self::$multipleSelect,
			'delimiter' => $delimiter,
			'cur_value' => $cur_value
		];

		$treeInputAttrs = [
			'id' => $input_name . 'treeinput',
			'class' => 'pfTreeInput',
			'style' => 'height: ' . $height . 'px; width: ' . $width . 'px; overflow: auto; position: relative;',
			'data' => json_encode( $pftree->tree_array ),
			'params' => json_encode( $params )
		];

		$text = Html::element( 'div', $treeInputAttrs, null );
		$text .= "<input type='hidden' class='PFTree_data' name='" . $input_name . "'>";

		$wrapperClass = 'pfTreeInputWrapper';
		if ( $is_mandatory ) {
			$wrapperClass .= ' mandatory';
		}
		$text = Html::rawElement( 'div', [ 'class' => $wrapperClass ], $text );

		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = [
			'name' => 'top category',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_topcategory' )->text()
		];
		$params[] = [
			'name' => 'structure',
			'type' => 'text',
			'description' => wfMessage( 'pf_forminputs_structure' )->text()
		];
		$params[] = [
			'name' => 'hideroot',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_hideroot' )->text()
		];
		$params[] = [
			'name' => 'depth',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_depth' )->text()
		];
		$params[] = [
			'name' => 'height',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_height' )->text()
		];
		$params[] = [
			'name' => 'width',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_width' )->text()
		];
		return $params;
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

	/**
	 * Creates a Title object from a user-provided (and thus unsafe) string
	 * @param string $title
	 * @return null|Title
	 */
	static function makeTitle( $title ) {
		$title = trim( $title );

		if ( strval( $title ) === '' ) {
			return null;
		}

		# The title must be in the category namespace
		# Ignore a leading Category: if there is one
		$t = Title::newFromText( $title, NS_CATEGORY );
		if ( !$t || $t->getNamespace() != NS_CATEGORY || $t->getInterwiki() != '' ) {
			// If we were given something like "Wikipedia:Foo" or "Template:",
			// try it again but forced.
			$title = "Category:$title";
			$t = Title::newFromText( $title );
		}
		return $t;
	}

}
