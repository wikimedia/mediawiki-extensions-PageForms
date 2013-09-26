<?php
/**
 * File holding the SFCategoryInput class
 *
 * @file
 * @ingroup SF
 */

/**
 * The SFCategoryInput class.
 *
 * @ingroup SFFormInput
 */
class SFCategoryInput extends SFFormInput {

	private static $multipleSelect = false;

	public static function getName() {
		return 'category';
	}

	public static function getOtherPropTypesHandled() {
		return array( '_wpg' );
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
		if ( array_key_exists( 'top category', $other_args ) ) {
			$top_category = $other_args['top category'];
		} else {
			// escape - we can't do anything
			return null;
		}

		if ( $other_args['input type'] == 'category' ) {
			$inputType = "radio";
			self::$multipleSelect = false;
		} else {
			$inputType = "checkbox";
			self::$multipleSelect = true;
		}

		// get list delimiter - default is comma
		if ( array_key_exists( 'delimiter', $other_args ) ) {
			$delimiter = $other_args['delimiter'];
		} else {
			$delimiter = ',';
		}

		$cur_values = SFUtils::getValuesArray( $cur_value, $delimiter );

		$cats = self::getCategoryHierarchy( $top_category );

		$hideroot = array_key_exists( 'hideroot', $other_args );
		if ( array_key_exists( 'depth', $other_args ) ) {
			$depth = $other_args['depth'];
		} else {
			$depth = '10';
		}
		if ( array_key_exists( 'height', $other_args ) ) {
			$height = $other_args['height'];
		} else {
			$height = '100';
		}
		if ( array_key_exists( 'width', $other_args ) ) {
			$width = $other_args['width'];
		} else {
			$width = '500';
		}

		$dummy_str = "REPLACE THIS TEXT";
		$text = '<div id="' . $input_name . 'categoryinput" style="height: ' . $height . 'px; width: ' . $width . 'px;">';
		$catText = self::encode_categories($cats, $input_name, $cur_values, $hideroot, $depth, $inputType);

		// replace values one at a time, by an incrementing index -
		// inspired by http://bugs.php.net/bug.php?id=11457
		$i = 0;
		while ( ( $a = strpos( $catText, $dummy_str ) ) > 0 ) {
			$catText = substr( $catText, 0, $a ) . $i++ . substr( $catText, $a + strlen( $dummy_str ) );
		}
		$text .= $catText;
		$text .= '</div>';

		return $text;
	}

	private static function encode_categories( $categories, $input_name, $current_selection, $hideprefix, $depth, $inputType ) {
		$key_prefix = $input_name . "key";
		$text = '';
		if ( !$hideprefix ) {
			$text .= "<ul>\n";
		}
		$text .= self::encode_node( $categories, $key_prefix, $input_name, $current_selection, $hideprefix, $depth, $inputType );
		if ( !$hideprefix ) {
			$text .= "</ul>\n";
		}
		if ( self::$multipleSelect ) {
			$text .= Html::hidden( $input_name . '[is_list]', 1 );
		}
		return $text;
	}

	private static function encode_node( $categories, $key_prefix, $input_name, $current_selection, $hidenode, $depth, $inputType, $index = 1 ) {
		global $sfgTabIndex, $sfgFieldNum;

		$input_id = "input_$sfgFieldNum";
		$key_id = "$key_prefix$index";
		$dataItems = array();
		$li_data = "";
		$input_data = "";
		if ( in_array($categories['title'], $current_selection) ) {
			$li_data .= 'class="selected" ';
			$input_data .= 'checked="checked"';
		}

		if ( $depth > 0 ) {
			$dataItems[] = "'expand': true";
		}

		if ( $dataItems ) {
			$li_data .= "data=\"" . implode(",", $dataItems) . "\" ";
		}

		$text = '';
		if ( !$hidenode ) {
			$dummy_str = "REPLACE THIS TEXT";
			$text .= "<li id=\"$key_id\" $li_data>";
			if ( self::$multipleSelect) {
				$inputName = $input_name . "[" . $dummy_str . "]";
			} else {
				$inputName = $input_name;
			}
			$text .= "<input type=\"$inputType\" tabindex=\"$sfgTabIndex\" name=\"" . $inputName .
				"\" value=\"" . $categories['title'] . "\" id=\"chb-$key_id\" $input_data class=\"hidden\" />";
			$text .= $categories['title'] . "\n";
		}
		if ( array_key_exists('children', $categories) ) {
			$text .= "<ul>\n";
			$i = 1;
			foreach ( $categories['children'] as $cat ) {
				$text .= self::encode_node( $cat, $key_id, $input_name, $current_selection, false, $depth - 1, $inputType, $i++ );
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
			'description' => wfMessage( 'sf_forminputs_topcategory' )->text()
		);
		$params[] = array(
			'name' => 'hideroot',
			'type' => 'boolean',
			'description' => wfMessage( 'sf_forminputs_hideroot' )->text()
		);
		$params[] = array(
			'name' => 'depth',
			'type' => 'int',
			'description' => wfMessage( 'sf_forminputs_depth' )->text()
		);
		$params[] = array(
			'name' => 'height',
			'type' => 'int',
			'description' => wfMessage( 'sf_forminputs_height' )->text()
		);
		$params[] = array(
			'name' => 'width',
			'type' => 'int',
			'description' => wfMessage( 'sf_forminputs_width' )->text()
		);
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

	/**
	 * Creates a Title object from a user provided (and thus unsafe) string
	 * @param $title string
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
		if ( !$t || $t->getNamespace() != NS_CATEGORY || $t->getInterWiki() != '' ) {
			// If we were given something like "Wikipedia:Foo" or "Template:",
			// try it again but forced.
			$title = "Category:$title";
			$t = Title::newFromText( $title );
		}
		return $t;
	}

	/**
	 * @param $top_category String
	 * @return mixed
	 */
	private static function getCategoryHierarchy( $top_category ) {
		$title = self::makeTitle( $top_category );
		if ( $title->getNamespace() != NS_CATEGORY ) {
			return null;
		}

		$nodes = array();

		$defaultDepth = 20;
		$res = self::getNode( $title, $defaultDepth, $nodes );
		return $res;
	}

	/**
	 * @param Title $title
	 * @param int $depth
	 * @param array $nodes
	 * @return array
	 */
	private static function getNode ( $title, $depth, &$nodes ) {
		$res = array( 'title' => $title->getText() );
		if ( !in_array( $title, $nodes ) ) {
			$nodes[] = $title;
			if ( $depth != 0 ) {
				$children = self::getChildren( $title, $depth - 1, $nodes );
				if ( !empty($children) ) {
					$res['children'] = $children;
				}
			}
		}
		return $res;
	}


	/**
	 * @param Title $title
	 * @param int $depth
	 * @return array
	 */
	private static function getChildren( $title, $depth, &$nodes ) {
		$dbr = wfGetDb( DB_SLAVE );

		$tables = array( 'page', 'categorylinks' );
		$fields = array( 'page_id', 'page_namespace', 'page_title',
			'page_is_redirect', 'page_len', 'page_latest', 'cl_to',
			'cl_from' );
		$where = array();
		$joins = array();
		$options = array( 'ORDER BY' => 'cl_type, cl_sortkey' );

		$joins['categorylinks'] = array( 'JOIN', 'cl_from = page_id' );
		$where['cl_to'] = $title->getDBkey();
		$options['USE INDEX']['categorylinks'] = 'cl_sortkey';

		$tables = array_merge( $tables, array( 'category' ) );
		$fields = array_merge( $fields, array( 'cat_id', 'cat_title', 'cat_subcats', 'cat_pages', 'cat_files' ) );
		$joins['category'] = array( 'LEFT JOIN', array( 'cat_title = page_title', 'page_namespace' => NS_CATEGORY ) );

		$res = $dbr->select( $tables, $fields, $where, __METHOD__, $options, $joins );
		$children = array();

		foreach ( $res as $row ) {
			$t = Title::newFromRow( $row );
			if ( $t->getNamespace() == NS_CATEGORY ) {
				$children[] = self::getNode( $t, $depth, $nodes );
			}
		}
		return $children;
	}


}
