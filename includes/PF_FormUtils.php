<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;
use OOUI\ButtonInputWidget;

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
		if ( $template_in_form === null ) {
			return '';
		}

		// HTML element names shouldn't contain spaces
		$templateName = str_replace( ' ', '_', $template_in_form->getTemplateName() );
		$text = "";
		foreach ( $template_in_form->getValuesFromPage() as $key => $value ) {
			if ( $key !== null && !is_numeric( $key ) ) {
				$key = urlencode( $key );
				$text .= Html::hidden( '_unhandled_' . $templateName . '_' . $key, $value );
			}
		}
		return $text;
	}

	static function summaryInputHTML( $is_disabled, $label = null, $attr = [], $value = '' ) {
		global $wgPageFormsTabIndex;

		if ( $label == null ) {
			$label = wfMessage( 'summary' )->text();
		}

		$wgPageFormsTabIndex++;
		$attr += [
			'tabIndex' => $wgPageFormsTabIndex,
			'value' => $value,
			'name' => 'wpSummary',
			'id' => 'wpSummary',
			'maxlength' => 255,
			'title' => wfMessage( 'tooltip-summary' )->text(),
			'accessKey' => wfMessage( 'accesskey-summary' )->text()
		];
		if ( $is_disabled ) {
			$attr['disabled'] = true;
		}
		$text = new OOUI\FieldLayout(
			new OOUI\TextInputWidget( $attr ),
			[
				'align' => 'top',
				'label' => $label
			]
		);

		return $text;
	}

	static function minorEditInputHTML( $form_submitted, $is_disabled, $is_checked, $label = null, $attrs = [] ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( !$form_submitted ) {
			$user = RequestContext::getMain()->getUser();
			$is_checked = $user->getOption( 'minordefault' );
		}

		if ( $label == null ) {
			$label = wfMessage( 'minoredit' )->parse();
		}

		$attrs += [
			'id' => 'wpMinoredit',
			'accessKey' => wfMessage( 'accesskey-minoredit' )->text(),
			'tabIndex' => $wgPageFormsTabIndex,
		];
		if ( $is_checked ) {
			$attrs['selected'] = true;
		}
		if ( $is_disabled ) {
			$attrs['disabled'] = true;
		}

		// We can't use OOUI\FieldLayout here, because it will make the display too wide.
		$labelWidget = new OOUI\LabelWidget( [
			'label' => new OOUI\HtmlSnippet( $label )
		] );
		$text = Html::rawElement(
			'label',
			[ 'title' => wfMessage( 'tooltip-minoredit' )->parse() ],
			new OOUI\CheckboxInputWidget( $attrs ) . $labelWidget
		);
		$text = Html::rawElement( 'div', [ 'style' => 'display: inline-block; padding: 12px 16px 12px 0;' ], $text );

		return $text;
	}

	static function watchInputHTML( $form_submitted, $is_disabled, $is_checked = false, $label = null, $attrs = [] ) {
		global $wgPageFormsTabIndex, $wgTitle;

		$wgPageFormsTabIndex++;
		// figure out if the checkbox should be checked -
		// this code borrowed from /includes/EditPage.php
		if ( !$form_submitted ) {
			$user = RequestContext::getMain()->getUser();
			if ( method_exists( \MediaWiki\Watchlist\WatchlistManager::class, 'isWatched' ) ) {
				// MediaWiki 1.37+
				// UserOptionsLookup::getOption was introduced in MW 1.35
				$services = MediaWikiServices::getInstance();
				$userOptionsLookup = $services->getUserOptionsLookup();
				$watchlistManager = $services->getWatchlistManager();
				if ( $userOptionsLookup->getOption( $user, 'watchdefault' ) ) {
					# Watch all edits
					$is_checked = true;
				} elseif ( $userOptionsLookup->getOption( $user, 'watchcreations' ) &&
					!$wgTitle->exists() ) {
					# Watch creations
					$is_checked = true;
				} elseif ( $watchlistManager->isWatched( $user, $wgTitle ) ) {
					# Already watched
					$is_checked = true;
				}
			} else {
				if ( $user->getOption( 'watchdefault' ) ) {
					# Watch all edits
					$is_checked = true;
				} elseif ( $user->getOption( 'watchcreations' ) && !$wgTitle->exists() ) {
					# Watch creations
					$is_checked = true;
				} elseif ( $user->isWatched( $wgTitle ) ) {
					# Already watched
					$is_checked = true;
				}
			}
		}
		if ( $label == null ) {
			$label = wfMessage( 'watchthis' )->parse();
		}
		$attrs += [
			'id' => 'wpWatchthis',
			'accessKey' => wfMessage( 'accesskey-watch' )->text(),
			'tabIndex' => $wgPageFormsTabIndex,
		];
		if ( $is_checked ) {
			$attrs['selected'] = true;
		}
		if ( $is_disabled ) {
			$attrs['disabled'] = true;
		}

		// We can't use OOUI\FieldLayout here, because it will make the display too wide.
		$labelWidget = new OOUI\LabelWidget( [
			'label' => new OOUI\HtmlSnippet( $label )
		] );
		$text = Html::rawElement(
			'label',
			[ 'title' => wfMessage( 'tooltip-watch' )->parse() ],
			new OOUI\CheckboxInputWidget( $attrs ) . $labelWidget
		);
		$text = Html::rawElement( 'div', [ 'style' => 'display: inline-block; padding: 12px 16px 12px 0;' ], $text );

		return $text;
	}

	/**
	 * Helper function to display a simple button
	 * @param string $name
	 * @param string $value
	 * @param string $type
	 * @param array $attrs
	 * @return ButtonInputWidget
	 */
	static function buttonHTML( $name, $value, $type, $attrs ) {
		$attrs += [
			'type' => $type,
			'name' => $name,
			'label' => $value
		];
		$button = new ButtonInputWidget( $attrs );
		// Special handling for 'class'.
		if ( isset( $attrs['class'] ) ) {
			// Make sure it's an array.
			if ( is_string( $attrs['class'] ) ) {
				$attrs['class'] = [ $attrs['class'] ];
			}
			$button->addClasses( $attrs['class'] );
		}
		return $button;
	}

	static function saveButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'savearticle' )->text();
		}
		$temp = $attr + [
			'id'        => 'wpSave',
			'tabIndex'  => $wgPageFormsTabIndex,
			'accessKey' => wfMessage( 'accesskey-save' )->text(),
			'title'     => wfMessage( 'tooltip-save' )->text(),
			'flags'     => [ 'primary', 'progressive' ]
		];
		if ( $is_disabled ) {
			$temp['disabled'] = true;
		}
		return self::buttonHTML( 'wpSave', $label, 'submit', $temp );
	}

	static function saveAndContinueButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;

		if ( $label == null ) {
			$label = wfMessage( 'pf_formedit_saveandcontinueediting' )->text();
		}

		$temp = $attr + [
			'id'        => 'wpSaveAndContinue',
			'tabIndex'  => $wgPageFormsTabIndex,
			'disabled'  => true,
			'accessKey' => wfMessage( 'pf_formedit_accesskey_saveandcontinueediting' )->text(),
			'title'     => wfMessage( 'pf_formedit_tooltip_saveandcontinueediting' )->text(),
		];

		if ( $is_disabled ) {
			$temp['class'] = 'pf-save_and_continue disabled';
		} else {
			$temp['class'] = 'pf-save_and_continue';
		}

		return self::buttonHTML( 'wpSaveAndContinue', $label, 'button', $temp );
	}

	static function showPreviewButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'showpreview' )->text();
		}
		$temp = $attr + [
			'id'        => 'wpPreview',
			'tabIndex'  => $wgPageFormsTabIndex,
			'accessKey' => wfMessage( 'accesskey-preview' )->text(),
			'title'     => wfMessage( 'tooltip-preview' )->text(),
		];
		if ( $is_disabled ) {
			$temp['disabled'] = true;
		}
		return self::buttonHTML( 'wpPreview', $label, 'submit', $temp );
	}

	static function showChangesButtonHTML( $is_disabled, $label = null, $attr = [] ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'showdiff' )->text();
		}
		$temp = $attr + [
			'id'        => 'wpDiff',
			'tabIndex'  => $wgPageFormsTabIndex,
			'accessKey' => wfMessage( 'accesskey-diff' )->text(),
			'title'     => wfMessage( 'tooltip-diff' )->text(),
		];
		if ( $is_disabled ) {
			$temp['disabled'] = true;
		}
		return self::buttonHTML( 'wpDiff', $label, 'submit', $temp );
	}

	static function cancelLinkHTML( $is_disabled, $label = null, $attr = [] ) {
		global $wgTitle;

		if ( $label == null ) {
			$label = wfMessage( 'cancel' )->parse();
		}
		if ( $wgTitle == null || $wgTitle->isSpecial( 'FormEdit' ) ) {
			$attr['classes'] = [ 'pfSendBack' ];
		} else {
			$attr['href'] = $wgTitle->getFullURL();
		}
		$attr['framed'] = false;
		$attr['label'] = $label;
		$attr['flags'] = [ 'destructive' ];
		return "\t\t" . new OOUI\ButtonWidget( $attr ) . "\n";
	}

	static function runQueryButtonHTML( $is_disabled = false, $label = null, $attr = [] ) {
		// is_disabled is currently ignored
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		if ( $label == null ) {
			$label = wfMessage( 'runquery' )->text();
		}
		$buttonHTML = self::buttonHTML( 'wpRunQuery', $label, 'submit',
			$attr + [
			'id' => 'wpRunQuery',
			'tabIndex' => $wgPageFormsTabIndex,
			'title' => $label,
			'flags' => [ 'primary', 'progressive' ],
			'icon' => 'search'
		] );
		return new OOUI\FieldLayout( $buttonHTML );
	}

	/**
	 * Much of this function is based on MediaWiki's EditPage::showEditForm().
	 * @param bool $form_submitted
	 * @param bool $is_disabled
	 * @return string
	 */
	static function formBottom( $form_submitted, $is_disabled ) {
		$text = <<<END
	<br />
	<div class='editOptions'>

END;
		$text .= self::summaryInputHTML( $is_disabled );
		$user = RequestContext::getMain()->getUser();
		if ( $user->isAllowed( 'minoredit' ) ) {
			$text .= self::minorEditInputHTML( $form_submitted, $is_disabled, false );
		}

		if ( method_exists( $user, 'isRegistered' ) ) {
			// MW 1.34+
			$userIsRegistered = $user->isRegistered();
		} else {
			$userIsRegistered = $user->isLoggedIn();
		}
		if ( $userIsRegistered ) {
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

	/**
	 * Loosely based on MediaWiki's EditPage::getPreloadedContent().
	 *
	 * @param string $preload
	 * @return string
	 */
	static function getPreloadedText( $preload ) {
		if ( $preload === '' ) {
			return '';
		}

		$preloadTitle = Title::newFromText( $preload );
		if ( !isset( $preloadTitle ) ) {
			return '';
		}

		if ( method_exists( 'MediaWiki\Permissions\PermissionManager', 'userCan' ) ) {
			// MW 1.33+
			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			$user = RequestContext::getMain()->getUser();
			if ( !$permissionManager->userCan( 'read', $user, $preloadTitle ) ) {
				return '';
			}
		} else {
			if ( !$preloadTitle->userCan( 'read' ) ) {
				return '';
			}
		}

		$text = PFUtils::getPageText( $preloadTitle );
		// Remove <noinclude> sections and <includeonly> tags from text
		$text = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $text );
		$text = strtr( $text, [ '<includeonly>' => '', '</includeonly>' => '' ] );
		return $text;
	}

	/**
	 * Used by 'RunQuery' page
	 * @return string
	 */
	static function queryFormBottom() {
		return self::runQueryButtonHTML( false );
	}

	static function getMonthNames() {
		return [
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
		];
	}

	public static function setGlobalVarsForSpreadsheet() {
		global $wgPageFormsContLangYes, $wgPageFormsContLangNo, $wgPageFormsContLangMonths;

		// JS variables that hold boolean and date values in the wiki's
		// (as opposed to the user's) language.
		$wgPageFormsContLangYes = wfMessage( 'htmlform-yes' )->inContentLanguage()->text();
		$wgPageFormsContLangNo = wfMessage( 'htmlform-no' )->inContentLanguage()->text();
		$monthMessages = [
			"january", "february", "march", "april", "may_long", "june",
			"july", "august", "september", "october", "november", "december"
		];
		$wgPageFormsContLangMonths = [ '' ];
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
		$form_def = strtr( $form_def, [ '<includeonly>' => '', '</includeonly>' => '' ] );

		// We need to replace all PF tags in the form definition by strip items. But we can not just use
		// the Parser strip state because the Parser would during parsing replace all strip items and then
		// mangle them into HTML code. So we have to use our own. Which means we also can not just use
		// Parser::insertStripItem() (see below).
		// Also include a quotation mark, to help avoid security leaks.
		$rnd = wfRandomString( 16 ) . '"' . wfRandomString( 15 );

		// This regexp will find any PF triple braced tags (including correct handling of contained braces), i.e.
		// {{{field|foo|default={{Bar}}}}} is not a problem. When used with preg_match and friends, $matches[0] will
		// contain the whole PF tag, $matches[1] will contain the tag without the enclosing triple braces.
		$regexp = '#\{\{\{((?>[^\{\}]+)|(\{((?>[^\{\}]+)|(?-2))*\}))*\}\}\}#';
		// Needed to restore highlighting in vi - <?

		$items = [];

		// replace all PF tags by strip markers
		$form_def = preg_replace_callback(
			$regexp,

			// This is essentially a copy of Parser::insertStripItem().
			static function ( array $matches ) use ( &$items, $rnd ) {
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
			$title = is_object( $parser->getTitle() ) ? $parser->getTitle() : $form_title;
			// We need to pass "false" in to the parse() $clearState param so that
			// embedding Special:RunQuery will work.
			$output = $parser->parse( $form_def, $title, $parser->getOptions(), true, false );
			$form_def = $output->getText();
		}
		$form_def = preg_replace_callback(
			"/{$rnd}-item-(\d+)-{$rnd}/",
			static function ( array $matches ) use ( $items ) {
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
	 * Deletes the form definition associated with the given wiki page
	 * from the main cache, for MW 1.35+.
	 *
	 * Hook: MultiContentSave
	 *
	 * @param RenderedRevision $renderedRevision
	 * @return bool
	 */
	public static function purgeCache2( RenderedRevision $renderedRevision ) {
		$articleID = $renderedRevision->getRevision()->getPageId();
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromID( $articleID );
		} else {
			// MW 1.35
			$wikiPage = WikiPage::newFromID( $articleID );
		}
		if ( $wikiPage == null ) {
			// @TODO - should this ever happen?
			return true;
		}
		return self::purgeCache( $wikiPage );
	}

	/**
	 * Get the cache object used by the form cache
	 * @return BagOStuff
	 */
	public static function getFormCache() {
		global $wgPageFormsFormCacheType, $wgParserCacheType;
		$ret = ObjectCache::getInstance( ( $wgPageFormsFormCacheType !== null ) ? $wgPageFormsFormCacheType : $wgParserCacheType );
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
		$cache = self::getFormCache();

		return ( $parser === null )
			? $cache->makeKey( 'ext.PageForms.formdefinition', $formId )
			: $cache->makeKey(
				'ext.PageForms.formdefinition',
				$formId,
				$parser->getOptions()->optionsHash( ParserOptions::allCacheVaryingOptions() )
			);
	}

	/**
	 * Get section header HTML
	 * @param string $header_name
	 * @param int $header_level
	 * @return string
	 */
	static function headerHTML( $header_name, $header_level = 2 ) {
		global $wgPageFormsTabIndex;

		$wgPageFormsTabIndex++;
		$text = "";

		if ( !is_numeric( $header_level ) ) {
			// The default header level is set to 2
			$header_level = 2;
		}

		$header_level = min( $header_level, 6 );
		$elementName = 'h' . $header_level;
		$text = Html::rawElement( $elementName, [], $header_name );
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
				$old_i = -1;
			}
		} elseif ( $deleted_item_loc != null ) {
			if ( $i >= $deleted_item_loc ) {
				$old_i = $i + 1;
			}
		}
		return $old_i;
	}
}
