<?php
/**
 * Gets the form(s) used to edit a page, both for existing pages and for
 * not-yet-created, red-linked pages.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

class PFFormLinker {

	static function getDefaultForm( $title ) {
		// The title passed in can be null in at least one
		// situation: if the "namespace page" is being checked, and
		// the project namespace alias contains any non-ASCII
		// characters. There may be other cases too.
		// If that happens, just exit.
		if ( is_null( $title ) ) {
			return null;
		}

		$pageID = $title->getArticleID();
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'page_props',
			array(
				'pp_value'
			),
			array(
				'pp_page' => $pageID,
				// Keep backward compatibility with
				// the page property name for
				// Semantic Forms.
				'pp_propname' => array( 'PFDefaultForm', 'SFDefaultForm' )
			)
		);

		if ( $row = $dbr->fetchRow( $res ) ) {
			return $row['pp_value'];
		}
	}

	public static function createPageWithForm( $title, $formName ) {
		global $wgPageFormsFormPrinter;

		$formTitle = Title::makeTitleSafe( PF_NS_FORM, $formName );
		$formDefinition = PFUtils::getPageText( $formTitle );
		$preloadContent = null;

		// Allow outside code to set/change the preloaded text.
		Hooks::run( 'PageForms::EditFormPreloadText', array( &$preloadContent, $title, $formTitle ) );

		list( $formText, $pageText, $formPageTitle, $generatedPageName ) =
			$wgPageFormsFormPrinter->formHTML( $formDefinition, false, false, null, $preloadContent, 'Some very long page name that will hopefully never get created ABCDEF123', null );
		$params = array();

		// Get user "responsible" for all auto-generated
		// pages from red links.
		$userID = 1;
		global $wgPageFormsAutoCreateUser;
		if ( !is_null( $wgPageFormsAutoCreateUser ) ) {
			$user = User::newFromName( $wgPageFormsAutoCreateUser );
			if ( !is_null( $user ) ) {
				$userID = $user->getId();
			}
		}
		$params['user_id'] = $userID;
		$params['page_text'] = $pageText;
		$job = new PFCreatePageJob( $title, $params );

		$jobs = array( $job );
		JobQueueGroup::singleton()->push( $jobs );
	}

	/**
	 * Sets the URL for form-based creation of a nonexistent (broken-linked,
	 * AKA red-linked) page
	 * @param Linker $linker
	 * @param Title $target
	 * @param array $options
	 * @param string $text
	 * @param array &$attribs
	 * @param bool &$ret
	 * @return true
	 */
	static function setBrokenLink( $linker, $target, $options, $text, &$attribs, &$ret ) {
		// If it's not a broken (red) link, exit.
		if ( !in_array( 'broken', $options, true ) ) {
			return true;
		}
		// If the link is to a special page, exit.
		if ( $target->getNamespace() == NS_SPECIAL ) {
			return true;
		}

		global $wgPageFormsLinkAllRedLinksToForms;
		// Don't do this is it it's a category page - it probably
		// won't have an associated form.
		if ( $wgPageFormsLinkAllRedLinksToForms && $target->getNamespace() != NS_CATEGORY ) {
			$attribs['href'] = $target->getLinkURL( array( 'action' => 'formedit', 'redlink' => '1' ) );
			return true;
		}

		return true;
	}

	/**
	 * Get the form(s) used to edit this page - either:
	 * - the default form(s) for the page itself, if there are any; or
	 * - the default form(s) for a category that this article belongs to,
	 * if there are any; or
	 * - the default form(s) for the article's namespace, if there are any.
	 * @param Title $title
	 * @return array
	 */
	static function getDefaultFormsForPage( $title ) {
		// See if the page itself has a default form (or forms), and
		// return it/them if so.
		// (Disregard category pages for this check.)
		if ( $title->getNamespace() != NS_CATEGORY ) {
			$default_form = self::getDefaultForm( $title );
			if ( $default_form === '' ) {
				// A call to "{{#default_form:}}" (i.e., no form
				// specified) should cancel any inherited forms.
				return array();
			} elseif ( $default_form !== null ) {
				return array( $default_form );
			}
		}

		// If this is not a category page, look for a default form
		// for its parent category or categories.
		$namespace = $title->getNamespace();
		if ( NS_CATEGORY !== $namespace ) {
			$default_forms = array();
			$categories = PFValuesUtils::getCategoriesForPage( $title );
			foreach ( $categories as $category ) {
				if ( class_exists( 'PSSchema' ) ) {
					// Check the Page Schema, if one exists.
					$psSchema = new PSSchema( $category );
					if ( $psSchema->isPSDefined() ) {
						$formName = PFPageSchemas::getFormName( $psSchema );
						if ( !is_null( $formName ) ) {
							$default_forms[] = $formName;
						}
					}
				}
				$categoryPage = Title::makeTitleSafe( NS_CATEGORY, $category );
				$defaultFormForCategory = self::getDefaultForm( $categoryPage );
				if ( $defaultFormForCategory != '' ) {
					$default_forms[] = $defaultFormForCategory;
				}
			}
			if ( count( $default_forms ) > 0 ) {
				// It is possible for two categories to have the same default form, so purge any
				// duplicates from the array to avoid a "more than one default form" warning.
				return array_unique( $default_forms );
			}
		}

		// All that's left is checking for the namespace. If this is
		// a subpage, exit out - default forms for namespaces don't
		// apply to subpages.
		if ( $title->isSubpage() ) {
			return array();
		}

		// If we're still here, just return the default form for the
		// namespace, which may well be null.
		if ( NS_MAIN === $namespace ) {
			// If it's in the main (blank) namespace, check for the
			// file named with the word for "Main" in this language.
			$namespace_label = wfMessage( 'pf_blank_namespace' )->inContentLanguage()->text();
		} else {
			global $wgContLang;
			$namespace_labels = $wgContLang->getNamespaces();
			$namespace_label = $namespace_labels[$namespace];
		}

		$namespacePage = Title::makeTitleSafe( NS_PROJECT, $namespace_label );
		$default_form = self::getDefaultForm( $namespacePage );
		if ( $default_form != '' ) {
			return array( $default_form );
		}

		return array();
	}
}
