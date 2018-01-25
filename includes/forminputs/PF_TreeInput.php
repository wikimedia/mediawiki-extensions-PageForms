<?php
/**
 * @file
 * @ingroup PF
 *
 * @author Yaron Koren
 * @author Mathias Lidal
 */

/**
 * @ingroup PFFormInput
 */
class PFTreeInput extends PFFormInput {

	private static $multipleSelect = false;

	public static function getName() {
		return 'tree';
	}

	public static function getOtherPropTypesHandled() {
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			return array( '_str', '_wpg' );
		} else {
			return array( '_txt', '_wpg' );
		}
	}

	public static function getOtherPropTypeListsHandled() {
		if ( defined( 'SMWDataItem::TYPE_STRING' ) ) {
			// SMW < 1.9
			return array( '_str', '_wpg' );
		} else {
			return array( '_txt', '_wpg' );
		}
	}

	public static function getDefaultCargoTypes() {
		return array(
			'Hierarchy' => array()
		);
	}

	public static function getDefaultCargoTypeLists() {
		return array(
			'Hierarchy' => array()
		);
	}

	public static function getOtherCargoTypesHandled() {
		return array( 'String', 'Page' );
	}

	public static function getOtherCargoTypeListsHandled() {
		return array( 'String', 'Page' );
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
		// Handle the now-deprecated 'category' and 'categories'
		// input types.
		if ( array_key_exists( 'input type', $other_args ) && $other_args['input type'] == 'category' ) {
			$inputType = "radio";
			self::$multipleSelect = false;
		} elseif ( array_key_exists( 'input type', $other_args ) && $other_args['input type'] == 'categories' ) {
			$inputType = "checkbox";
			self::$multipleSelect = true;
		} else {
			$is_list = ( array_key_exists( 'is_list', $other_args ) && $other_args['is_list'] == true );
			if ( $is_list ) {
				$inputType = "checkbox";
				self::$multipleSelect = true;
			} else {
				$inputType = "radio";
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

			$tree = PFTree::newFromTopCategory( $top_category );
			$hideroot = array_key_exists( 'hideroot', $other_args );
		} elseif ( array_key_exists( 'structure', $other_args ) ) {
			$structure = $other_args['structure'];
			$tree = PFTree::newFromWikiText( $structure );
			$hideroot = true;
		} else {
			// Escape - we can't do anything.
			return null;
		}

		$inputText = self::treeToHTML( $tree, $input_name, $cur_values, $hideroot, $depth, $inputType );

		// Replace values one at a time, by an incrementing index -
		// inspired by http://bugs.php.net/bug.php?id=11457
		$dummy_str = "REPLACE THIS TEXT";
		$i = 0;
		while ( ( $a = strpos( $inputText, $dummy_str ) ) > 0 ) {
			$inputText = substr( $inputText, 0, $a ) . $i++ . substr( $inputText, $a + strlen( $dummy_str ) );
		}

		$class = 'pfTreeInput';
		if ( $is_mandatory ) {
			$class .= ' mandatory';
		}
		$text = Html::rawElement(
			'div',
			array(
				'class' => $class,
				'id' => $input_name . 'treeinput',
				'style' => 'height: ' . $height . 'px; width: ' . $width . 'px;'
			),
			$inputText
		);

		return $text;
	}

	// Perhaps treeToHTML() and nodeToHTML() should be moved to the
	// PFTree class? Currently PFTree doesn't know about HTML stuff, but
	// maybe it should.
	private static function treeToHTML( $fullTree, $input_name, $current_selection, $hideprefix, $depth, $inputType ) {
		$key_prefix = $input_name . "key";
		$text = '';
		if ( !$hideprefix ) {
			$text .= "<ul>\n";
		}
		$text .= self::nodeToHTML( $fullTree, $key_prefix, $input_name, $current_selection, $hideprefix, $depth, $inputType );
		if ( !$hideprefix ) {
			$text .= "</ul>\n";
		}
		if ( self::$multipleSelect ) {
			$text .= Html::hidden( $input_name . '[is_list]', 1 );
		}
		return $text;
	}

	private static function nodeToHTML( $node, $key_prefix, $input_name, $current_selection, $hidenode, $depth, $inputType, $index = 1 ) {
		global $wgPageFormsTabIndex;

		$text = '';

		// HTML IDs can't contain spaces.
		$key_id = str_replace( ' ', '-', "$key_prefix-$index" );

		if ( !$hidenode ) {
			$liAttribs = array( 'id' => $key_id );
			if ( in_array( $node->title, $current_selection ) ) {
				$liAttribs['class'] = 'selected';
			}
			if ( $depth > 0 ) {
				$liAttribs['data'] = "'expand': true";
			}
			// For some reason, the Dynatree JS library requires
			// unclosed <li> tags; "<li>...</li>" won't work.
			$text .= Html::openElement( 'li', $liAttribs );

			$dummy_str = "REPLACE THIS TEXT";

			$cur_input_name = $input_name;
			if ( self::$multipleSelect ) {
				$cur_input_name .= "[" . $dummy_str . "]";
			}
			$nodeAttribs = array(
				'tabindex' => $wgPageFormsTabIndex,
				'id' => "chb-$key_id",
				'class' => 'hidden'
			);
			if ( in_array( $node->title, $current_selection ) ) {
				$nodeAttribs['checked'] = true;
			}

			$text .= Html::input( $cur_input_name, $node->title, $inputType, $nodeAttribs );

			$text .= $node->title . "\n";
		}

		if ( array_key_exists( 'children', $node ) ) {
			$text .= "<ul>\n";
			$i = 1;
			foreach ( $node->children as $cat ) {
				$text .= self::nodeToHTML( $cat, $key_id, $input_name, $current_selection, false, $depth - 1, $inputType, $i++ );
			}
			$text .= "</ul>\n";
		}

		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = array(
			'name' => 'top category',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_topcategory' )->text()
		);
		$params[] = array(
			'name' => 'structure',
			'type' => 'text',
			'description' => wfMessage( 'pf_forminputs_structure' )->text()
		);
		$params[] = array(
			'name' => 'hideroot',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_hideroot' )->text()
		);
		$params[] = array(
			'name' => 'depth',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_depth' )->text()
		);
		$params[] = array(
			'name' => 'height',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_height' )->text()
		);
		$params[] = array(
			'name' => 'width',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_width' )->text()
		);
		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
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
