<?php
/**
 * Helper functions for the Page Forms extension.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

class PFUtils {

	/**
	 * Helper function for backward compatibility.
	 * @param LinkRenderer $linkRenderer
	 * @param string $title
	 * @param string|null $msg
	 * @param array $attrs
	 * @param array $params
	 * @return string
	 */
	public static function makeLink( $linkRenderer, $title, $msg = null, $attrs = array(), $params = array() ) {
		if ( !is_null( $linkRenderer ) ) {
			// MW 1.28+
			// Is there a makeLinkKnown() method? We'll just do it
			// manually.
			return $linkRenderer->makeLink( $title, $msg, $attrs, $params, array( 'known' ) );
		} else {
			return Linker::linkKnown( $title, $msg, $attrs, $params );
		}
	}

	/**
	 * Creates a link to a special page, using that page's top-level description as the link text.
	 * @param LinkRenderer $linkRenderer
	 * @param string $specialPageName
	 * @return string
	 */
	public static function linkForSpecialPage( $linkRenderer, $specialPageName ) {
		$specialPage = SpecialPageFactory::getPage( $specialPageName );
		return self::makeLink( $linkRenderer, $specialPage->getPageTitle(),
			htmlspecialchars( $specialPage->getDescription() ) );
	}

	/**
	 * Creates the name of the page that appears in the URL;
	 * this method is necessary because Title::getPartialURL(), for
	 * some reason, doesn't include the namespace
	 * @param Title $title
	 * @return string
	 */
	public static function titleURLString( $title ) {
		$namespace = $title->getNsText();
		if ( $namespace !== '' ) {
			$namespace .= ':';
		}
		if ( MWNamespace::isCapitalized( $title->getNamespace() ) ) {
			global $wgContLang;
			return $namespace . $wgContLang->ucfirst( $title->getPartialURL() );
		} else {
			return $namespace . $title->getPartialURL();
		}
	}

	/**
	 * A very similar function to titleURLString(), to get the
	 * non-URL-encoded title string
	 * @param Title $title
	 * @return string
	 */
	public static function titleString( $title ) {
		$namespace = $title->getNsText();
		if ( $namespace !== '' ) {
			$namespace .= ':';
		}
		if ( MWNamespace::isCapitalized( $title->getNamespace() ) ) {
			global $wgContLang;
			return $namespace . $wgContLang->ucfirst( $title->getText() );
		} else {
			return $namespace . $title->getText();
		}
	}

	/**
	 * Gets the text contents of a page with the passed-in Title object.
	 * @param Title $title
	 * @return string|null
	 */
	public static function getPageText( $title ) {
		$wikiPage = new WikiPage( $title );
		$content = $wikiPage->getContent();
		if ( $content !== null ) {
			return $content->getNativeData();
		} else {
			return null;
		}
	}

	/**
	 * Helper function to get the SMW data store for different versions
	 * of SMW.
	 * @return Store|null
	 */
	public static function getSMWStore() {
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			// SMW 1.9+
			return \SMW\StoreFactory::getStore();
		} elseif ( function_exists( 'smwfGetStore' ) ) {
			return smwfGetStore();
		} else {
			return null;
		}
	}

	/**
	 * Creates wiki-text for a link to a wiki page
	 * @param int $namespace
	 * @param string $name
	 * @param string|null $text
	 * @return string
	 */
	public static function linkText( $namespace, $name, $text = null ) {
		$title = Title::makeTitleSafe( $namespace, $name );
		if ( is_null( $title ) ) {
			return $name; // TODO maybe report an error here?
		}
		if ( is_null( $text ) ) {
			return '[[:' . $title->getPrefixedText() . '|' . $name . ']]';
		} else {
			return '[[:' . $title->getPrefixedText() . '|' . $text . ']]';
		}
	}

	/**
	 * Prints the mini-form contained at the bottom of various pages, that
	 * allows pages to spoof a normal edit page, that can preview, save,
	 * etc.
	 * @param string $title
	 * @param string $page_contents
	 * @param string $edit_summary
	 * @param bool $is_save
	 * @param bool $is_preview
	 * @param bool $is_diff
	 * @param bool $is_minor_edit
	 * @param bool $watch_this
	 * @param string $start_time
	 * @param string $edit_time
	 * @return string
	 */
	public static function printRedirectForm(
		$title,
		$page_contents,
		$edit_summary,
		$is_save,
		$is_preview,
		$is_diff,
		$is_minor_edit,
		$watch_this,
		$start_time,
		$edit_time
	) {
		global $wgUser, $wgPageFormsScriptPath;

		if ( $is_save ) {
			$action = "wpSave";
		} elseif ( $is_preview ) {
			$action = "wpPreview";
		} else { // $is_diff
			$action = "wpDiff";
		}

		$text = <<<END
	<p style="position: absolute; left: 45%; top: 45%;"><img src="$wgPageFormsScriptPath/skins/loading.gif" /></p>

END;
		$form_body = Html::hidden( 'wpTextbox1', $page_contents );
		$form_body .= Html::hidden( 'wpUnicodeCheck', 'ℳ𝒲♥𝓊𝓃𝒾𝒸ℴ𝒹ℯ' );
		$form_body .= Html::hidden( 'wpSummary', $edit_summary );
		$form_body .= Html::hidden( 'wpStarttime', $start_time );
		$form_body .= Html::hidden( 'wpEdittime', $edit_time );

		if ( $wgUser->isLoggedIn() ) {
			$edit_token = $wgUser->getEditToken();
		} elseif ( class_exists( '\MediaWiki\Session\Token' ) ) {
			// MW 1.27+
			$edit_token = \MediaWiki\Session\Token::SUFFIX;
		} else {
			$edit_token = EDIT_TOKEN_SUFFIX;
		}
		$form_body .= Html::hidden( 'wpEditToken', $edit_token );
		$form_body .= Html::hidden( $action, null );

		if ( $is_minor_edit ) {
			$form_body .= Html::hidden( 'wpMinoredit', null );
		}
		if ( $watch_this ) {
			$form_body .= Html::hidden( 'wpWatchthis', null );
		}
		$text .= Html::rawElement(
			'form',
			array(
				'id' => 'editform',
				'name' => 'editform',
				'method' => 'post',
				'action' => $title instanceof Title ? $title->getLocalURL( 'action=submit' ) : $title
			),
			$form_body
		);

		$text .= <<<END
	<script type="text/javascript">
	window.onload = function() {
		document.editform.submit();
	}
	</script>

END;
		Hooks::run( 'PageForms::PrintRedirectForm', array( $is_save, $is_preview, $is_diff, &$text ) );
		return $text;
	}

	/**
	 * Includes the necessary ResourceLoader modules for the form
	 * to display and work correctly.
	 *
	 * Accepts an optional Parser instance, or uses $wgOut if omitted.
	 * @param Parser|null $parser
	 */
	public static function addFormRLModules( $parser = null ) {
		global $wgOut, $wgPageFormsSimpleUpload, $wgVersion,
			$wgUsejQueryThree;

		// Handling depends on whether or not this form is embedded
		// in another page.
		if ( !$parser ) {
			$wgOut->addMeta( 'robots', 'noindex,nofollow' );
			$output = $wgOut;
		} else {
			$output = $parser->getOutput();
		}

		$mainModules = array(
			'ext.pageforms.main',
			'ext.pageforms.submit',
			'ext.smw.tooltips',
			'ext.smw.sorttable',
			// @TODO - the inclusion of modules for specific
			// form inputs is wasteful, and should be removed -
			// it should only be done as needed for each input.
			// Unfortunately the use of multiple-instance
			// templates makes that tricky (every form input needs
			// to re-apply the JS on a new instance) - it can be
			// done via JS hooks, but it hasn't been done yet.
			'ext.pageforms.dynatree',
			'ext.pageforms.imagepreview',
			'ext.pageforms.autogrow',
			'ext.pageforms.checkboxes',
			'ext.pageforms.select2',
			'ext.pageforms.rating'
		);

		if ( version_compare( $wgVersion, '1.30', '<' ) || $wgUsejQueryThree === false ) {
			$mainModules[] = 'ext.pageforms.fancybox.jquery1';
		} else {
			$mainModules[] = 'ext.pageforms.fancybox.jquery3';
		}

		if ( $wgPageFormsSimpleUpload ) {
			$mainModules[] = 'ext.pageforms.simpleupload';
		}

		$output->addModules( $mainModules );

		$otherModules = array();
		Hooks::run( 'PageForms::AddRLModules', array( &$otherModules ) );
		foreach ( $otherModules as $rlModule ) {
			$output->addModules( $rlModule );
		}
	}

	/**
	 * Returns an array of all form names on this wiki.
	 * @return string[]
	 */
	public static function getAllForms() {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'page',
			'page_title',
			array( 'page_namespace' => PF_NS_FORM,
				'page_is_redirect' => false ),
			__METHOD__,
			array( 'ORDER BY' => 'page_title' ) );
		$form_names = array();
		while ( $row = $dbr->fetchRow( $res ) ) {
			$form_names[] = str_replace( '_', ' ', $row[0] );
		}
		$dbr->freeResult( $res );
		return $form_names;
	}

	/**
	 * Creates a dropdown of possible form names.
	 * @param array|null $form_names
	 * @return string
	 */
	public static function formDropdownHTML( $form_names = null ) {
		global $wgContLang;
		$namespace_labels = $wgContLang->getNamespaces();
		$form_label = $namespace_labels[PF_NS_FORM];
		if ( $form_names === null ) {
			$form_names = self::getAllForms();
		}
		$select_body = "\n";
		foreach ( $form_names as $form_name ) {
			$select_body .= "\t" . Html::element( 'option', null, $form_name ) . "\n";
		}
		return "\t" . Html::rawElement( 'label', array( 'for' => 'formSelector' ), $form_label . wfMessage( 'colon-separator' )->escaped() ) . "\n" . Html::rawElement( 'select', array( 'id' => 'formSelector', 'name' => 'form' ), $select_body ) . "\n";
	}

	/**
	 * A helper function, used by getFormTagComponents().
	 * @param string $s
	 * @return string
	 */
	public static function convertBackToPipes( $s ) {
		return str_replace( "\1", '|', $s );
	}

	/**
	 * Splits the contents of a tag in a form definition based on pipes,
	 * but does not split on pipes that are contained within additional
	 * curly brackets, in case the tag contains any calls to parser
	 * functions or templates.
	 * @param string $string
	 * @return string[]
	 */
	static function smartSplitFormTag( $string ) {
		if ( $string == '' ) {
			return array();
		}

		$delimiter = '|';
		$returnValues = array();
		$numOpenCurlyBrackets = 0;
		$curReturnValue = '';

		for ( $i = 0; $i < strlen( $string ); $i++ ) {
			$curChar = $string{$i};
			if ( $curChar == '{' ) {
				$numOpenCurlyBrackets++;
			} elseif ( $curChar == '}' ) {
				$numOpenCurlyBrackets--;
			}

			if ( $curChar == $delimiter && $numOpenCurlyBrackets == 0 ) {
				$returnValues[] = trim( $curReturnValue );
				$curReturnValue = '';
			} else {
				$curReturnValue .= $curChar;
			}
		}
		$returnValues[] = trim( $curReturnValue );

		return $returnValues;
	}

	/**
	 * This function is basically equivalent to calling
	 * explode( '|', $str ), except that it doesn't split on pipes
	 * that are within parser function calls - i.e., pipes within
	 * double curly brackets.
	 * @param string $str
	 * @return string[]
	 */
	public static function getFormTagComponents( $str ) {
		// Turn each pipe within double curly brackets into another,
		// unused character (here, "\1"), then do the explode, then
		// convert them back.
		$pattern = '/({{.*)\|(.*}})/';
		while ( preg_match( $pattern, $str, $matches ) ) {
			$str = preg_replace( $pattern, "$1" . "\1" . "$2", $str );
		}
		return array_map( array( 'PFUtils', 'convertBackToPipes' ), self::smartSplitFormTag( $str ) );
	}

	/**
	 * Gets the word in the wiki's language for either the value 'yes' or
	 * 'no'.
	 * @param bool $isYes
	 * @return string
	 */
	public static function getWordForYesOrNo( $isYes ) {
		// @TODO - should Page Forms define these messages itself?
		$message = $isYes ? 'htmlform-yes' : 'htmlform-no';
		return wfMessage( $message )->inContentLanguage()->text();
	}

	/**
	 * array_merge_recursive merges arrays, but it converts values with duplicate
	 * keys to arrays rather than overwriting the value in the first array with the duplicate
	 * value in the second array, as array_merge does.
	 *
	 * array_merge_recursive_distinct does not change the datatypes of the values in the arrays.
	 * Matching keys' values in the second array overwrite those in the first array.
	 *
	 * Parameters are passed by reference, though only for performance reasons. They're not
	 * altered by this function.
	 *
	 * See http://www.php.net/manual/en/function.array-merge-recursive.php#92195
	 *
	 * @param array &$array1
	 * @param array &$array2
	 * @return array
	 * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
	 * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
	 */
	public static function array_merge_recursive_distinct( array &$array1, array &$array2 ) {
		$merged = $array1;

		foreach ( $array2 as $key => &$value ) {
			if ( is_array( $value ) && isset( $merged[$key] ) && is_array( $merged[$key] ) ) {
				$merged[$key] = self::array_merge_recursive_distinct( $merged[$key], $value );
			} else {
				$merged[$key] = $value;
			}
		}

		return $merged;
	}

}
