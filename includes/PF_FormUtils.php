<?php

use MediaWiki\MediaWikiServices;

/**
 * Utilities for the display and retrieval of forms.
 *
 * @author Yaron Koren
 * @author Jeffrey Stuckman
 * @author Harold Solbrig
 * @author Eugene Mednikov
 * @file
 * @ingroup PF
 */

class PFFormUtils {

	/**
	 * Add a hidden input for each field in the template call that's
	 * not handled by the form itself
	 * @param PFTemplateInForm|null $template_in_form
	 * @return string
	 */
	static function unhandledFieldsHTML( $template_in_form ) {
		// This shouldn't happen, but sometimes this value is null.
		// @TODO - fix the code that calls this function so the
		// value is never null.
		if ( is_null( $template_in_form ) ) {
			return '';
		}

		// HTML element names shouldn't contain spaces
		$templateName = str_replace( ' ', '_', $template_in_form->getTemplateName() );
		$text = "";
		foreach ( $template_in_form->getValuesFromPage() as $key => $value ) {
			if ( !is_null( $key ) && !is_numeric( $key ) ) {
				$key = urlencode( $key );
				$text .= Html::hidden( '_unhandled_' . $templateName . '_' . $key, $value );
			}
		}
		return $text;
	}

	static function summaryInputHTML( $is_disabled, $label = null, $attr = array(), $value = '' ) {
		global $wgPageFormsTabIndex;

		if ( $label == null ) {
			$label = wfMessage( 'summary' )->text();
		}
		$text = Html::rawElement( 'span', array( 'id' => 'wpSummaryLabel' ),
			Html::element( 'label', array( 'for' => 'wpSummary' ), $label ) );

		$wgPageFormsTabIndex++;
		$attr['tabindex'] = $wgPageFormsTabIndex;
		$attr['type'] = 'text';
		$attr['value'] = $value;
		$attr['name'] = 'wpSummary';
		$attr['id'] = 'wpSummary';
		$attr['maxlength'] = 200;
		$attr['size'] = 60;
		if ( $is_disabled ) {
			$attr['disabled'] = true;
		}
		$text .= ' ' . Html::element( 'input', $attr );

		return $text;
	}

	static function minorEditInputHTML( $form_submitted, $is_disabled, $is_checked, $label = null, $attrs = array() ) {
		global $wgPageFormsTabIndex, $wgUser, $wgParser;

		$wgPageFormsTabIndex++;
		if ( !$form_submitted ) {
			$is_checked = $wgUser->getOption( 'minordefault' );
		}

		if ( $label == null ) {
			$label = $wgParser->recursiveTagParse( wfMessage( 'minoredit' )->text() );
		}

		$tooltip = wfMessage( 'tooltip-minoredit' )->text();
		$attrs += array(
			'id' => 'wpMinoredit',
			'accesskey' => wfMessage( 'accesskey-minoredit' )->text(),
			'tabindex' => $wgPageFormsTabIndex,
		);
		if ( $is_disabled ) {
			$attrs['disabled'] = true;
		}
		$text = "\t" . Xml::check( 'wpMinoredit', $is_checked, $attrs ) . "\n";
		$text .= "\t" . Html::rawElement( 'label', array(
			'for' => 'wpMinoredit',
			'title' => $tooltip
		), $label ) . "\n";

		return $text;
	}

	static function watchInputHTML( $form_submitted, $is_disabled, $is_checked = false, $label = null, $attrs = array() ) {
		global $wgPageFormsTabIndex, $wgUser, $wgTitle, $wgParser;

		$wgPageFormsTabIndex++;
		// figure out if the checkbox should be checked -
		// this code borrowed from /includes/EditPage.php
		if ( !$form_submitted ) {
			if ( $wgUser->getOption( 'watchdefault' ) ) {
				# Watch all edits
				$is_checked = true;
			} elseif ( $wgUser->getOption( 'watchcreations' ) && !$wgTitle->exists() ) {
				# Watch creations
				$is_checked = true;
			} elseif ( $wgUser->isWatched( $wgTitle ) ) {
				# Already watched
				$is_checked = true;
			}
		}
		if ( $label == null ) {
			$label = $wgParser->recursiveTagParse( wfMessage( 'watchthis' )->text() );
		}
		$attrs += array(
			'id' => 'wpWatchthis',
			'accesskey' => wfMessage( 'accesskey-watch' )->text(),
			'tabindex' => $wgPageFormsTabIndex,
		);
		if ( $is_disabled ) {
			$attrs['disabled'] = true;
		}
		$text = "\t" . Xml::check( 'wpWatchthis', $is_checked, $attrs ) . "\n";
		$tooltip = wfMessage( 'tooltip-watch' )->text();
		$text .= "\t" . Html::rawElement( 'label', array(
			'for' => 'wpWatchthis',
			'title' => $tooltip
		), $label ) . "\n";

		return $text;
	}

	/**
	 * Helper function to display a simple button
	 * @param string $name
	 * @param string $value
	 * @param string $type
	 * @param array $attrs
	 * @return string
	 */
	static function buttonHTML( $name, $value, $type, $attrs ) {
		return "\t\t" . Html::input( $name, $value, $type, $attrs ) . "\n";
	}

	static function saveButtonHTML( $is_disabled, $label = null, $attr = array() ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'savearticle' )->text();
		}
		$temp = $attr + array(
			'id'        => 'wpSave',
			'tabindex'  => $wgPageFormsTabIndex,
			'accesskey' => wfMessage( 'accesskey-save' )->text(),
			'title'     => wfMessage( 'tooltip-save' )->text(),
		);
		if ( $is_disabled ) {
			$temp['disabled'] = true;
		}
		return self::buttonHTML( 'wpSave', $label, 'submit', $temp );
	}

	static function saveAndContinueButtonHTML( $is_disabled, $label = null, $attr = array() ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;

		if ( $label == null ) {
			$label = wfMessage( 'pf_formedit_saveandcontinueediting' )->text();
		}

		$temp = $attr + array(
			'id'        => 'wpSaveAndContinue',
			'tabindex'  => $wgPageFormsTabIndex,
			'disabled'  => true,
			'accesskey' => wfMessage( 'pf_formedit_accesskey_saveandcontinueediting' )->text(),
			'title'     => wfMessage( 'pf_formedit_tooltip_saveandcontinueediting' )->text(),
		);

		if ( $is_disabled ) {
			$temp['class'] = 'pf-save_and_continue disabled';
		} else {
			$temp['class'] = 'pf-save_and_continue';
		}

		return self::buttonHTML( 'wpSaveAndContinue', $label, 'button', $temp );
	}

	static function showPreviewButtonHTML( $is_disabled, $label = null, $attr = array() ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'showpreview' )->text();
		}
		$temp = $attr + array(
			'id'        => 'wpPreview',
			'tabindex'  => $wgPageFormsTabIndex,
			'accesskey' => wfMessage( 'accesskey-preview' )->text(),
			'title'     => wfMessage( 'tooltip-preview' )->text(),
		);
		if ( $is_disabled ) {
			$temp['disabled'] = true;
		}
		return self::buttonHTML( 'wpPreview', $label, 'submit', $temp );
	}

	static function showChangesButtonHTML( $is_disabled, $label = null, $attr = array() ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'showdiff' )->text();
		}
		$temp = $attr + array(
			'id'        => 'wpDiff',
			'tabindex'  => $wgPageFormsTabIndex,
			'accesskey' => wfMessage( 'accesskey-diff' )->text(),
			'title'     => wfMessage( 'tooltip-diff' )->text(),
		);
		if ( $is_disabled ) {
			$temp['disabled'] = true;
		}
		return self::buttonHTML( 'wpDiff', $label, 'submit', $temp );
	}

	static function cancelLinkHTML( $is_disabled, $label = null, $attr = array() ) {
		global $wgTitle, $wgParser;

		if ( $label == null ) {
			$label = $wgParser->recursiveTagParse( wfMessage( 'cancel' )->text() );
		}
		if ( $wgTitle == null ) {
			$cancel = '';
		} elseif ( $wgTitle->isSpecial( 'FormEdit' ) ) {
			// If we're on the special 'FormEdit' page, just send the user
			// back to the previous page they were on.
			$stepsBack = 1;
			// For IE, we need to go back twice, past the redirect.
			if ( array_key_exists( 'HTTP_USER_AGENT', $_SERVER ) &&
				stristr( $_SERVER['HTTP_USER_AGENT'], "msie" ) ) {
				$stepsBack = 2;
			}
			$cancel = "<a href=\"javascript:history.go(-$stepsBack);\">$label</a>";
		} else {
			if ( function_exists( 'MediaWiki\MediaWikiServices::getLinkRenderer' ) ) {
				$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			} else {
				$linkRenderer = null;
			}
			$cancel = PFUtils::makeLink( $linkRenderer, $wgTitle, $label );
		}
		return "\t\t" . Html::rawElement( 'span', array( 'class' => 'editHelp' ), $cancel ) . "\n";
	}

	static function runQueryButtonHTML( $is_disabled = false, $label = null, $attr = array() ) {
		// is_disabled is currently ignored
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'runquery' )->text();
		}
		return self::buttonHTML( 'wpRunQuery', $label, 'submit',
			$attr + array(
			'id'        => 'wpRunQuery',
			'tabindex'  => $wgPageFormsTabIndex,
			'title'     => $label,
		) );
	}

	// Much of this function is based on MediaWiki's EditPage::showEditForm()
	static function formBottom( $form_submitted, $is_disabled ) {
		global $wgUser;

		$summary_text = self::summaryInputHTML( $is_disabled );
		$text = <<<END
	<br /><br />
	<div class='editOptions'>
$summary_text	<br />

END;
		if ( $wgUser->isAllowed( 'minoredit' ) ) {
			$text .= self::minorEditInputHTML( $form_submitted, $is_disabled, false );
		}

		if ( $wgUser->isLoggedIn() ) {
			$text .= self::watchInputHTML( $form_submitted, $is_disabled );
		}

		$text .= <<<END
	<br />
	<div class='editButtons'>

END;
		$text .= self::saveButtonHTML( $is_disabled );
		$text .= self::showPreviewButtonHTML( $is_disabled );
		$text .= self::showChangesButtonHTML( $is_disabled );
		$text .= self::cancelLinkHTML( $is_disabled );
		$text .= <<<END
	</div><!-- editButtons -->
	</div><!-- editOptions -->

END;
		return $text;
	}

	// based on MediaWiki's EditPage::getPreloadedText()
	static function getPreloadedText( $preload ) {
		if ( $preload === '' ) {
			return '';
		} else {
			$preloadTitle = Title::newFromText( $preload );
			if ( isset( $preloadTitle ) && $preloadTitle->userCan( 'read' ) ) {
				$rev = Revision::newFromTitle( $preloadTitle );
				if ( is_object( $rev ) ) {
					$content = $rev->getContent();
					$text = ContentHandler::getContentText( $content );
					// Remove <noinclude> sections and <includeonly> tags from text
					$text = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $text );
					$text = strtr( $text, array( '<includeonly>' => '', '</includeonly>' => '' ) );
					return $text;
				}
			}
			return '';
		}
	}

	/**
	 * Used by 'RunQuery' page
	 * @return string
	 */
	static function queryFormBottom() {
		return self::runQueryButtonHTML( false );
	}

	static function getMonthNames() {
		return array(
			wfMessage( 'january' )->inContentLanguage()->text(),
			wfMessage( 'february' )->inContentLanguage()->text(),
			wfMessage( 'march' )->inContentLanguage()->text(),
			wfMessage( 'april' )->inContentLanguage()->text(),
			// Needed to avoid using 3-letter abbreviation
			wfMessage( 'may_long' )->inContentLanguage()->text(),
			wfMessage( 'june' )->inContentLanguage()->text(),
			wfMessage( 'july' )->inContentLanguage()->text(),
			wfMessage( 'august' )->inContentLanguage()->text(),
			wfMessage( 'september' )->inContentLanguage()->text(),
			wfMessage( 'october' )->inContentLanguage()->text(),
			wfMessage( 'november' )->inContentLanguage()->text(),
			wfMessage( 'december' )->inContentLanguage()->text()
		);
	}

	public static function setGlobalVarsForSpreadsheet() {
		global $wgPageFormsContLangYes, $wgPageFormsContLangNo, $wgPageFormsContLangMonths;

		// JS variables that hold boolean and date values in the wiki's
		// (as opposed to the user's) language.
		$wgPageFormsContLangYes = wfMessage( 'htmlform-yes' )->inContentLanguage()->text();
		$wgPageFormsContLangNo = wfMessage( 'htmlform-no' )->inContentLanguage()->text();
		$monthMessages = array(
			"january", "february", "march", "april", "may_long", "june",
			"july", "august", "september", "october", "november", "december"
		);
		$wgPageFormsContLangMonths = array( '' );
		foreach ( $monthMessages as $monthMsg ) {
			$wgPageFormsContLangMonths[] = wfMessage( $monthMsg )->inContentLanguage()->text();
		}
	}

	/**
	 * Parse the form definition and return it
	 * @param Parser $parser
	 * @param string|null $form_def
	 * @param string|null $form_id
	 * @return string
	 */
	public static function getFormDefinition( Parser $parser, $form_def = null, $form_id = null ) {
		if ( $form_id !== null ) {
			$cachedDef = self::getFormDefinitionFromCache( $form_id, $parser );

			if ( $cachedDef !== null ) {
				return $cachedDef;
			}
		}

		if ( $form_id !== null ) {
			$form_title = Title::newFromID( $form_id );
			$form_def = PFUtils::getPageText( $form_title );
		} elseif ( $form_def == null ) {
			// No id, no text -> nothing to do

			return '';
		}

		// Remove <noinclude> sections and <includeonly> tags from form definition
		$form_def = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $form_def );
		$form_def = strtr( $form_def, array( '<includeonly>' => '', '</includeonly>' => '' ) );

		// We need to replace all PF tags in the form definition by strip items. But we can not just use
		// the Parser strip state because the Parser would during parsing replace all strip items and then
		// mangle them into HTML code. So we have to use our own. Which means we also can not just use
		// Parser::insertStripItem() (see below).
		$rnd = wfRandomString( 32 );

		// This regexp will find any PF triple braced tags (including correct handling of contained braces), i.e.
		// {{{field|foo|default={{Bar}}}}} is not a problem. When used with preg_match and friends, $matches[0] will
		// contain the whole PF tag, $matches[1] will contain the tag without the enclosing triple braces.
		$regexp = '#\{\{\{((?>[^\{\}]+)|(\{((?>[^\{\}]+)|(?-2))*\}))*\}\}\}#';
		// Needed to restore highlighting in vi - <?

		$items = array();

		// replace all PF tags by strip markers
		$form_def = preg_replace_callback(
			$regexp,

			// This is essentially a copy of Parser::insertStripItem().
			// The 'use' keyword will bump the minimum PHP version to 5.3
			function ( array $matches ) use ( &$items, $rnd ) {
				$markerIndex = count( $items );
				$items[] = $matches[0];
				return "$rnd-item-$markerIndex-$rnd";
			},

			$form_def
		);

		// Parse wiki-text.
		if ( isset( $parser->mInParse ) && $parser->mInParse === true ) {
			$form_def = $parser->recursiveTagParse( $form_def );
			$output = $parser->getOutput();
		} else {
			$title = is_object( $parser->getTitle() ) ? $parser->getTitle() : new Title();
			// We need to pass "false" in to the parse() $clearState param so that
			// embedding Special:RunQuery will work.
			$output = $parser->parse( $form_def, $title, $parser->getOptions(), true, false );
			$form_def = $output->getText();
		}
		$form_def = preg_replace_callback(
			"/{$rnd}-item-(\d+)-{$rnd}/",
			function ( array $matches ) use ( $items ) {
				$markerIndex = (int)$matches[1];
				return $items[$markerIndex];
			},
			$form_def
		);

		if ( $output->getCacheTime() == -1 ) {
			$form_article = Article::newFromID( $form_id );
			self::purgeCache( $form_article );
			wfDebug( "Caching disabled for form definition $form_id\n" );
		} elseif ( $form_id !== null ) {
			self::cacheFormDefinition( $form_id, $form_def, $parser );
		}

		return $form_def;
	}

	/**
	 * Get a form definition from cache
	 * @param string $form_id
	 * @param Parser $parser
	 * @return string|null
	 */
	protected static function getFormDefinitionFromCache( $form_id, Parser $parser ) {
		global $wgPageFormsCacheFormDefinitions;

		// use cache if allowed
		if ( !$wgPageFormsCacheFormDefinitions ) {
			return null;
		}

		$cache = self::getFormCache();

		// create a cache key consisting of owner name, article id and user options
		$cacheKeyForForm = self::getCacheKey( $form_id, $parser );

		$cached_def = $cache->get( $cacheKeyForForm );

		// Cache hit?
		if ( is_string( $cached_def ) ) {
			wfDebug( "Cache hit: Got form definition $cacheKeyForForm from cache\n" );

			return $cached_def;
		}

		wfDebug( "Cache miss: Form definition $cacheKeyForForm not found in cache\n" );

		return null;
	}

	/**
	 * Store a form definition in cache
	 * @param string $form_id
	 * @param string $form_def
	 * @param Parser $parser
	 */
	protected static function cacheFormDefinition( $form_id, $form_def, Parser $parser ) {
		global $wgPageFormsCacheFormDefinitions;

		// Store in cache if requested
		if ( !$wgPageFormsCacheFormDefinitions ) {
			return;
		}

		$cache = self::getFormCache();
		$cacheKeyForForm = self::getCacheKey( $form_id, $parser );
		$cacheKeyForList = self::getCacheKey( $form_id );

		// Update list of form definitions
		$listOfFormKeys = $cache->get( $cacheKeyForList );
		// The list of values is used by self::purge, keys are ignored.
		// This way we automatically override duplicates.
		$listOfFormKeys[$cacheKeyForForm] = $cacheKeyForForm;

		// We cache indefinitely ignoring $wgParserCacheExpireTime.
		// The reasoning is that there really is not point in expiring
		// rarely changed forms automatically (after one day per
		// default). Instead the cache is purged on storing/purging a
		// form definition.

		// Store form definition with current user options
		$cache->set( $cacheKeyForForm, $form_def );

		// Store updated list of form definitions
		$cache->set( $cacheKeyForList, $listOfFormKeys );
		wfDebug( "Cached form definition $cacheKeyForForm\n" );
	}

	/**
	 * Deletes the form definition associated with the given wiki page
	 * from the main cache.
	 *
	 * Hooks: ArticlePurge, PageContentSave
	 *
	 * @param WikiPage $wikipage
	 * @return bool
	 */
	public static function purgeCache( WikiPage $wikipage ) {
		if ( !$wikipage->getTitle()->inNamespace( PF_NS_FORM ) ) {
			return true;
		}

		$cache = self::getFormCache();
		$cacheKeyForList = self::getCacheKey( $wikipage->getId() );

		// get references to stored datasets
		$listOfFormKeys = $cache->get( $cacheKeyForList );

		if ( !is_array( $listOfFormKeys ) ) {
			return true;
		}

		// delete stored datasets
		foreach ( $listOfFormKeys as $key ) {
			$cache->delete( $key );
			wfDebug( "Deleted cached form definition $key.\n" );
		}

		// delete references to datasets
		$cache->delete( $cacheKeyForList );
		wfDebug( "Deleted cached form definition references $cacheKeyForList.\n" );

		return true;
	}

	/**
	 *  Get the cache object used by the form cache
	 * @return BagOStuff
	 */
	public static function getFormCache() {
		global $wgPageFormsFormCacheType, $wgParserCacheType;
		$ret = wfGetCache( ( $wgPageFormsFormCacheType !== null ) ? $wgPageFormsFormCacheType : $wgParserCacheType );
		return $ret;
	}

	/**
	 * Get a cache key.
	 *
	 * @param string $formId
	 * @param Parser|null $parser Provide parser to get unique cache key
	 * @return string
	 */
	public static function getCacheKey( $formId, $parser = null ) {
		if ( is_null( $parser ) ) {
			return wfMemcKey( 'ext.PageForms.formdefinition', $formId );
		} else {
			$optionsHash = $parser->getOptions()->optionsHash( ParserOptions::legacyOptions() );
			return wfMemcKey( 'ext.PageForms.formdefinition', $formId, $optionsHash );
		}
	}

	/**
	 * Get section header HTML
	 * @param string $header_name
	 * @param int $header_level
	 * @return string
	 */
	static function headerHTML( $header_name , $header_level = 2 ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		$text = "";

		if ( !is_numeric( $header_level ) ) {
			// The default header level is set to 2
			$header_level = 2;
		}

		$header_level = min( $header_level, 6 );
		$elementName = 'h'. $header_level;
		$text = Html::rawElement( $elementName, array(), $header_name );
		return $text;
	}

	/**
	 * Get the changed index if a new template or section was
	 * inserted before the end, or one was deleted in the form
	 * @param int $i
	 * @param int|null $new_item_loc
	 * @param int|null $deleted_item_loc
	 * @return int
	 */
	static function getChangedIndex( $i, $new_item_loc, $deleted_item_loc ) {
		$old_i = $i;
		if ( $new_item_loc != null ) {
			if ( $i > $new_item_loc ) {
				$old_i = $i - 1;
			} elseif ( $i == $new_item_loc ) {
				// it's the new template; it shouldn't
				// get any query-string data
				$old_i = - 1;
			}
		} elseif ( $deleted_item_loc != null ) {
			if ( $i >= $deleted_item_loc ) {
				$old_i = $i + 1;
			}
		}
		return $old_i;
	}
}
