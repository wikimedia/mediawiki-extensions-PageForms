<?php
/**
 * Helper functions for the Page Forms extension.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;

class PFUtils {

	/**
	 * Get a content language object.
	 *
	 * @return Language
	 */
	public static function getContLang() {
		return MediaWikiServices::getInstance()->getContentLanguage();
	}

	public static function getSMWContLang() {
		if ( function_exists( 'smwfContLang' ) ) {
			// SMW 3.2+
			return smwfContLang();
		} else {
			global $smwgContLang;
			return $smwgContLang;
		}
	}

	/**
	 * Get a parser object.
	 *
	 * @return Parser
	 */
	public static function getParser() {
		return MediaWikiServices::getInstance()->getParser();
	}

	/**
	 * Creates a link to a special page, using that page's top-level description as the link text.
	 * @param LinkRenderer $linkRenderer
	 * @param string $specialPageName
	 * @return string
	 */
	public static function linkForSpecialPage( $linkRenderer, $specialPageName ) {
		$specialPage = self::getSpecialPage( $specialPageName );
		return $linkRenderer->makeKnownLink( $specialPage->getPageTitle(),
			htmlspecialchars( $specialPage->getDescription() ) );
	}

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param LinkTarget|Title $title
	 * @param string|null $msg Must already be HTML escaped
	 * @param array $attrs link attributes
	 * @param array $params query parameters
	 *
	 * @return string HTML link
	 *
	 * Copied from CargoUtils::makeLink().
	 */
	public static function makeLink( $linkRenderer, $title, $msg = null, $attrs = [], $params = [] ) {
		global $wgTitle;

		if ( $title === null ) {
			return null;
		} elseif ( $wgTitle !== null && $title->equals( $wgTitle ) ) {
			// Display bolded text instead of a link.
			return Linker::makeSelfLinkObj( $title, $msg );
		} else {
			$html = ( $msg == null ) ? null : new HtmlArmor( $msg );
			return $linkRenderer->makeLink( $title, $html, $attrs, $params );
		}
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
		if ( self::isCapitalized( $title->getNamespace() ) ) {
			return $namespace . self::getContLang()->ucfirst( $title->getPartialURL() );
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
		if ( self::isCapitalized( $title->getNamespace() ) ) {
			return $namespace . self::getContLang()->ucfirst( $title->getText() );
		} else {
			return $namespace . $title->getText();
		}
	}

	/**
	 * Gets the text contents of a page with the passed-in Title object.
	 * @param Title $title
	 * @param int $audience
	 * @return string|null
	 */
	public static function getPageText( $title, $audience = RevisionRecord::FOR_PUBLIC ) {
		$wikiPage = new WikiPage( $title );
		$content = $wikiPage->getContent( $audience );
		if ( $content instanceof TextContent ) {
			// Since MW 1.33
			if ( method_exists( $content, 'getText' ) ) {
				return $content->getText();
			} else {
				return $content->getNativeData();
			}
		} else {
			return null;
		}
	}

	public static function getSpecialPage( $pageName ) {
		return MediaWikiServices::getInstance()
			->getSpecialPageFactory()
			->getPage( $pageName );
	}

	/**
	 * Helper function to get the SMW data store, if SMW is installed.
	 * @return Store|null
	 */
	public static function getSMWStore() {
		if ( class_exists( '\SMW\StoreFactory' ) ) {
			return \SMW\StoreFactory::getStore();
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
		if ( $title === null ) {
			// TODO maybe report an error here?
			return $name;
		}
		if ( $text === null ) {
			return '[[:' . $title->getPrefixedText() . '|' . $name . ']]';
		} else {
			return '[[:' . $title->getPrefixedText() . '|' . $text . ']]';
		}
	}

	/**
	 * Returns a hidden mini-form to be printed at the bottom of various helper
	 * forms, like Special:CreateForm, so that the main form can either save or
	 * preview the resulting page.
	 *
	 * @param string $title
	 * @param string $page_contents
	 * @param string $edit_summary
	 * @param bool $is_save
	 * @param User $user
	 * @return string
	 */
	public static function printRedirectForm(
		$title,
		$page_contents,
		$edit_summary,
		$is_save,
		$user
	) {
		global $wgPageFormsScriptPath;

		if ( $is_save ) {
			$action = "wpSave";
		} else {
			$action = "wpPreview";
		}

		$text = <<<END
	<p style="position: absolute; left: 45%; top: 45%;"><img src="$wgPageFormsScriptPath/skins/loading.gif" /></p>

END;
		$form_body = Html::hidden( 'wpTextbox1', $page_contents );
		$form_body .= Html::hidden( 'wpUnicodeCheck', 'ℳ𝒲♥𝓊𝓃𝒾𝒸ℴ𝒹ℯ' );
		$form_body .= Html::hidden( 'wpSummary', $edit_summary );
		// @TODO - add this in at some point.
		//$form_body .= Html::hidden( 'editRevId', $edit_rev_id );

		if ( method_exists( $user, 'isRegistered' ) ) {
			// MW 1.34+
			$userIsRegistered = $user->isRegistered();
		} else {
			$userIsRegistered = $user->isLoggedIn();
		}
		if ( $userIsRegistered ) {
			$edit_token = $user->getEditToken();
		} else {
			$edit_token = \MediaWiki\Session\Token::SUFFIX;
		}
		$form_body .= Html::hidden( 'wpEditToken', $edit_token );
		$form_body .= Html::hidden( $action, null );

		$form_body .= Html::hidden( 'wpUltimateParam', true );

		$text .= Html::rawElement(
			'form',
			[
				'id' => 'editform',
				'name' => 'editform',
				'method' => 'post',
				'action' => $title instanceof Title ? $title->getLocalURL( 'action=submit' ) : $title
			],
			$form_body
		);

		$text .= <<<END
	<script type="text/javascript">
	window.onload = function() {
		document.editform.submit();
	}
	</script>

END;
		// @TODO - remove this hook? It seems useless.
		Hooks::run( 'PageForms::PrintRedirectForm', [ $is_save, !$is_save, false, &$text ] );
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
		global $wgOut, $wgPageFormsSimpleUpload;

		// Handling depends on whether or not this form is embedded
		// in another page.
		if ( !$parser ) {
			$wgOut->addMeta( 'robots', 'noindex,nofollow' );
			$output = $wgOut;
		} else {
			$output = $parser->getOutput();
		}

		$mainModules = [
			'ext.pageforms.main',
			'ext.pageforms.submit',
			'ext.smw.tooltips',
			// @TODO - the inclusion of modules for specific
			// form inputs is wasteful, and should be removed -
			// it should only be done as needed for each input.
			// Unfortunately the use of multiple-instance
			// templates makes that tricky (every form input needs
			// to re-apply the JS on a new instance) - it can be
			// done via JS hooks, but it hasn't been done yet.
			'ext.pageforms.jstree',
			'ext.pageforms.imagepreview',
			'ext.pageforms.autogrow',
			'ext.pageforms.checkboxes',
			'ext.pageforms.select2',
			'ext.pageforms.rating',
			'ext.pageforms.fancybox',
			'ext.pageforms.fullcalendar',
			'jquery.makeCollapsible'
		];

		if ( $wgPageFormsSimpleUpload ) {
			$mainModules[] = 'ext.pageforms.simpleupload';
		}

		$output->addModules( $mainModules );

		$otherModules = [];
		Hooks::run( 'PageForms::AddRLModules', [ &$otherModules ] );
		foreach ( $otherModules as $rlModule ) {
			$output->addModules( $rlModule );
		}
	}

	/**
	 * Returns an array of all form names on this wiki.
	 * @return string[]
	 */
	public static function getAllForms() {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'page',
			'page_title',
			[ 'page_namespace' => PF_NS_FORM,
				'page_is_redirect' => false ],
			__METHOD__,
			[ 'ORDER BY' => 'page_title' ] );
		$form_names = [];
		while ( $row = $res->fetchRow() ) {
			$form_names[] = str_replace( '_', ' ', $row[0] );
		}
		$res->free();
		if ( count( $form_names ) == 0 ) {
			// This case requires special handling in the UI.
			throw new MWException( wfMessage( 'pf-noforms-error' )->parse() );
		}
		return $form_names;
	}

	public static function getFormDropdownLabel() {
		$namespaceStrings = self::getContLang()->getNamespaces();
		$formNSString = $namespaceStrings[PF_NS_FORM];
		return $formNSString . wfMessage( 'colon-separator' )->escaped();
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
			return [];
		}

		$delimiter = '|';
		$returnValues = [];
		$numOpenCurlyBrackets = 0;
		$curReturnValue = '';

		for ( $i = 0; $i < strlen( $string ); $i++ ) {
			$curChar = $string[$i];
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
		// regex adapted from:
		// https://www.regular-expressions.info/recurse.html
		$pattern = '/{{(?>[^{}]|(?R))*?}}/';
		// needed to fix highlighting - <?
		$str = preg_replace_callback( $pattern, static function ( $match ) {
			$hasPipe = strpos( $match[0], '|' );
			return $hasPipe ? str_replace( "|", "\1", $match[0] ) : $match[0];
		}, $str );
		return array_map( [ 'PFUtils', 'convertBackToPipes' ], self::smartSplitFormTag( $str ) );
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
	 * arrayMergeRecursiveDistinct() does not change the datatypes of the values in the arrays.
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
	public static function arrayMergeRecursiveDistinct( array &$array1, array &$array2 ) {
		$merged = $array1;

		foreach ( $array2 as $key => &$value ) {
			if ( is_array( $value ) && isset( $merged[$key] ) && is_array( $merged[$key] ) ) {
				$merged[$key] = self::arrayMergeRecursiveDistinct( $merged[$key], $value );
			} else {
				$merged[$key] = $value;
			}
		}

		return $merged;
	}

	/**
	 * Return whether to "ignore" (treat as a non-form) a form with this
	 * name, based on whether it matches any of the specified text patterns.
	 *
	 * @param string $formName
	 * @return bool
	 */
	public static function ignoreFormName( $formName ) {
		global $wgPageFormsIgnoreTitlePattern;

		if ( !is_array( $wgPageFormsIgnoreTitlePattern ) ) {
			$wgPageFormsIgnoreTitlePattern = [ $wgPageFormsIgnoreTitlePattern ];
		}

		foreach ( $wgPageFormsIgnoreTitlePattern as $pattern ) {
			if ( preg_match( '/' . $pattern . '/', $formName ) ) {
				return true;
			}
		}

		return false;
	}

	public static function isCapitalized( $index ) {
		if ( class_exists( NamespaceInfo::class ) ) {
			// MW 1.34+
			return MediaWikiServices::getInstance()
				->getNamespaceInfo()
				->isCapitalized( $index );
		} else {
			return MWNamespace::isCapitalized( $index );
		}
	}

	public static function getCanonicalName( $index ) {
		if ( class_exists( NamespaceInfo::class ) ) {
			// MW 1.34+
			return MediaWikiServices::getInstance()
				->getNamespaceInfo()
				->getCanonicalName( $index );
		} else {
			return MWNamespace::getCanonicalIndex( $index );
		}
	}
}
