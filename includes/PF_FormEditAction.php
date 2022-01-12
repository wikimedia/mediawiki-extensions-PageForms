<?php

use MediaWiki\MediaWikiServices;

/**
 * Handles the formedit action.
 *
 * @author Yaron Koren
 * @author Stephan Gambke
 * @file
 * @ingroup PF
 */

class PFFormEditAction extends Action {

	/**
	 * Return the name of the action this object responds to
	 * @return string lowercase
	 */
	public function getName() {
		return 'formedit';
	}

	/**
	 * The main action entry point.  Do all output for display and send it to the context
	 * output. Do not use globals $wgOut, $wgRequest, etc, in implementations; use
	 * $this->getOutput(), etc.
	 * @throws ErrorPageError
	 * @return false
	 */
	public function show() {
		return self::displayForm( $this, $this->page );
	}

	/**
	 * Execute the action in a silent fashion: do not display anything or release any errors.
	 * @return bool whether execution was successful
	 */
	public function execute() {
		return true;
	}

	/**
	 * Adds an "action" (i.e., a tab) to edit the current article with
	 * a form
	 * @param IContextSource $obj
	 * @param array &$links
	 * @return true
	 */
	static function displayTab( $obj, &$links ) {
		$title = $obj->getTitle();
		$user = $obj->getUser();

		// Make sure that this is not a special page, and
		// that the user is allowed to edit it
		// - this function is almost never called on special pages,
		// but before SMW is fully initialized, it's called on
		// Special:SMWAdmin for some reason, which is why the
		// special-page check is there.
		if ( !isset( $title ) ||
			( $title->getNamespace() == NS_SPECIAL ) ) {
			return true;
		}

		$form_names = PFFormLinker::getDefaultFormsForPage( $title );
		if ( count( $form_names ) == 0 ) {
			return true;
		}

		global $wgPageFormsRenameEditTabs, $wgPageFormsRenameMainEditTab;

		$content_actions = &$links['views'];

		if ( method_exists( 'MediaWiki\Permissions\PermissionManager', 'userCan' ) ) {
			// MW 1.33+
			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			$user_can_edit = $permissionManager->userCan( 'edit', $user, $title );
		} else {
			$user_can_edit = $title->userCan( 'edit', $user );
		}

		// Create the form edit tab, and apply whatever changes are
		// specified by the edit-tab global variables.
		if ( $wgPageFormsRenameEditTabs ) {
			$form_edit_tab_msg = $user_can_edit ? 'edit' : 'pf_viewform';
			if ( array_key_exists( 'edit', $content_actions ) ) {
				$msg = $user_can_edit ? 'pf_editsource' : 'viewsource';
				$content_actions['edit']['text'] = wfMessage( $msg )->text();
			}
		} else {
			if ( $user_can_edit ) {
				$form_edit_tab_msg = $title->exists() ? 'formedit' : 'pf_formcreate';
			} else {
				$form_edit_tab_msg = 'pf_viewform';
			}
			// Check for renaming of main edit tab only if
			// $wgPageFormsRenameEditTabs is off.
			if ( $wgPageFormsRenameMainEditTab ) {
				if ( array_key_exists( 'edit', $content_actions ) ) {
					$msg = $user_can_edit ? 'pf_editsource' : 'viewsource';
					$content_actions['edit']['text'] = wfMessage( $msg )->text();
				}
			}
		}

		$class_name = ( $obj->getRequest()->getVal( 'action' ) == 'formedit' ) ? 'selected' : '';
		$form_edit_tab = [
			'class' => $class_name,
			'text' => wfMessage( $form_edit_tab_msg )->text(),
			'href' => $title->getLocalURL( 'action=formedit' )
		];

		// Find the location of the 'edit' tab, and add 'edit
		// with form' right before it.
		// This is a "key-safe" splice - it preserves both the keys
		// and the values of the array, by editing them separately
		// and then rebuilding the array. Based on the example at
		// http://us2.php.net/manual/en/function.array-splice.php#31234
		$tab_keys = array_keys( $content_actions );
		$tab_values = array_values( $content_actions );
		$edit_tab_location = array_search( 'edit', $tab_keys );

		// If there's no 'edit' tab, look for the 'view source' tab
		// instead.
		if ( $edit_tab_location == null ) {
			$edit_tab_location = array_search( 'viewsource', $tab_keys );
		}

		// This should rarely happen, but if there was no edit *or*
		// view source tab, set the location index to -1, so the
		// tab shows up near the end.
		if ( $edit_tab_location == null ) {
			$edit_tab_location = -1;
		}
		array_splice( $tab_keys, $edit_tab_location, 0, 'formedit' );
		array_splice( $tab_values, $edit_tab_location, 0, [ $form_edit_tab ] );
		$content_actions = [];
		foreach ( $tab_keys as $i => $key ) {
			$content_actions[$key] = $tab_values[$i];
		}

		if ( !$obj->getUser()->isAllowed( 'viewedittab' ) ) {
			// The tab can have either of these two actions.
			unset( $content_actions['edit'] );
			unset( $content_actions['viewsource'] );
		}

		// always return true, in order not to stop MW's hook processing!
		return true;
	}

	static function displayFormChooser( $output, $title ) {
		$output->addModules( 'ext.pageforms.main' );

		$targetName = $title->getPrefixedText();
		$output->setPageTitle( wfMessage( "creating", $targetName )->text() );

		try {
			$formNames = PFUtils::getAllForms();
		} catch ( MWException $e ) {
			$output->addHTML( Html::element( 'div', [ 'class' => 'error' ], $e->getMessage() ) );
			return;
		}

		$output->addHTML( Html::element( 'p', null, wfMessage( 'pf-formedit-selectform' )->text() ) );
		$pagesPerForm = self::getNumPagesPerForm();
		$totalPages = 0;
		foreach ( $pagesPerForm as $formName => $numPages ) {
			$totalPages += $numPages;
		}
		// We define "popular forms" as those that are used to
		// edit more than 1% of the wiki's form-editable pages.
		$popularForms = [];
		foreach ( $pagesPerForm as $formName => $numPages ) {
			if ( $numPages > $totalPages / 100 ) {
				$popularForms[] = $formName;
			}
		}
		$otherForms = [];
		foreach ( $formNames as $i => $formName ) {
			if ( !in_array( $formName, $popularForms ) ) {
				$otherForms[] = $formName;
			}
		}

		$fe = PFUtils::getSpecialPage( 'FormEdit' );

		if ( count( $popularForms ) > 0 ) {
			if ( count( $otherForms ) > 0 ) {
				$output->addHTML( Html::element(
					'p',
					[],
					wfMessage( 'pf-formedit-mainforms' )->text()
				) );
			}
			$text = self::printLinksToFormArray( $popularForms, $targetName, $fe );
			$output->addHTML( Html::rawElement( 'div', [ 'class' => 'infoMessage mainForms' ], $text ) );
		}

		if ( count( $otherForms ) > 0 ) {
			if ( count( $popularForms ) > 0 ) {
				$output->addHTML( Html::element(
					'p',
					[],
					wfMessage( 'pf-formedit-otherforms' )->text()
				) );
			}
			$text = self::printLinksToFormArray( $otherForms, $targetName, $fe );
			$output->addHTML( Html::rawElement( 'div', [ 'class' => 'infoMessage otherForms' ], $text ) );
		}

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$linkParams = [ 'action' => 'edit', 'redlink' => true ];
		$noFormLink = $linkRenderer->makeKnownLink( $title, wfMessage( 'pf-formedit-donotuseform' )->escaped(), [], $linkParams );
		$output->addHTML( Html::rawElement( 'p', null, $noFormLink ) );
	}

	/**
	 * Find the number of pages on the wiki that use each form, by getting
	 * all the categories that have a #default_form call pointing to a
	 * particular form, and adding up the number of pages in each such
	 * category.
	 * This approach doesn't count #default_form calls for namespaces or
	 * individual pages, but that doesn't seem like a big deal, because,
	 * when creating a page in a namespace that has a form, this interface
	 * probably won't get called anyway; and #default_form calls for
	 * individual pages are (hopefully) pretty rare.
	 * @return int[]
	 */
	static function getNumPagesPerForm() {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			[ 'category', 'page', 'page_props' ],
			[ 'pp_value', 'SUM(cat_pages) AS total_pages' ],
			[
				// Keep backward compatibility with
				// the page property name for
				// Semantic Forms.
				'pp_propname' => [ 'PFDefaultForm', 'SFDefaultForm' ]
			],
			__METHOD__,
			[
				'GROUP BY' => 'pp_value',
				'ORDER BY' => 'total_pages DESC',
				'LIMIT' => 100
			],
			[
				'page' => [ 'JOIN', 'cat_title = page_title' ],
				'page_props' => [ 'JOIN', 'page_id = pp_page' ]
			]
		);

		$pagesPerForm = [];
		while ( $row = $res->fetchRow() ) {
			$formName = $row['pp_value'];
			$pagesPerForm[$formName] = $row['total_pages'];
		}
		return $pagesPerForm;
	}

	static function printLinksToFormArray( $formNames, $targetName, $fe ) {
		$text = '';
		foreach ( $formNames as $i => $formName ) {
			if ( $i > 0 ) {
				$text .= " &middot; ";
			}

			// Special handling for forms whose name contains a slash.
			if ( strpos( $formName, '/' ) !== false ) {
				$url = $fe->getPageTitle()->getLocalURL( [ 'form' => $formName, 'target' => $targetName ] );
			} else {
				$url = $fe->getPageTitle( "$formName/$targetName" )->getLocalURL();
			}
			$text .= Html::element( 'a', [ 'href' => $url ], $formName );
		}
		return $text;
	}

	/**
	 * The function called if we're in index.php (as opposed to one of the
	 * special pages)
	 * @param Action $action
	 * @param Article $article
	 * @return true
	 */
	static function displayForm( $action, $article ) {
		$output = $action->getOutput();
		$title = $article->getTitle();
		$form_names = PFFormLinker::getDefaultFormsForPage( $title );
		if ( count( $form_names ) == 0 ) {
			// If no form is set, display an interface to let the
			// user choose out of all the forms defined on this wiki
			// (or none at all).
			self::displayFormChooser( $output, $title );
			return true;
		}

		if ( count( $form_names ) > 1 ) {
			$warning_text = "\t" . '<div class="warningbox">' . wfMessage( 'pf_formedit_morethanoneform' )->text() . "</div>\n";
			$output->addWikiTextAsInterface( $warning_text );
		}

		$form_name = $form_names[0];
		$page_name = PFUtils::titleString( $title );

		$pfFormEdit = new PFFormEdit();
		$pfFormEdit->printForm( $form_name, $page_name );

		return false;
	}
}
