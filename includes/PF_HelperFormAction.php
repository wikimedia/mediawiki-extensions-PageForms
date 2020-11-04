<?php

use MediaWiki\MediaWikiServices;

/**
 * Handles the formcreate action - used for helper forms for creating
 * properties, forms, etc..
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

class PFHelperFormAction extends Action {

	/**
	 * Return the name of the action this object responds to
	 * @return string lowercase
	 */
	public function getName() {
		return 'formcreate';
	}

	/**
	 * The main action entry point.  Do all output for display and send it to the context
	 * output.  Do not use globals $wgOut, $wgRequest, etc, in implementations; use
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
	 * @param Title $obj
	 * @param array &$links
	 * @return bool
	 */
	static function displayTab( $obj, &$links ) {
		$title = $obj->getTitle();
		$user = $obj->getUser();

		// Make sure that this page is in one of the relevant
		// namespaces, and that it doesn't exist yet.
		$namespacesWithHelperForms = [ NS_TEMPLATE, PF_NS_FORM, NS_CATEGORY ];
		if ( defined( 'SMW_NS_PROPERTY' ) ) {
			$namespacesWithHelperForms[] = SMW_NS_PROPERTY;
		}
		if ( !isset( $title ) ||
			( !in_array( $title->getNamespace(), $namespacesWithHelperForms ) ) ) {
			return true;
		}
		if ( $title->exists() ) {
			return true;
		}

		// The tab should show up automatically for properties and
		// forms, but not necessarily for templates and categories,
		// since some of them might be outside of the SMW/PF system.
		if ( in_array( $title->getNamespace(), [ NS_TEMPLATE, NS_CATEGORY ] ) ) {
			global $wgPageFormsShowTabsForAllHelperForms;
			if ( !$wgPageFormsShowTabsForAllHelperForms ) {
				return true;
			}
		}

		$content_actions = &$links['views'];

		if ( method_exists( 'MediaWiki\Permissions\PermissionManager', 'userCan' ) ) {
			// MW 1.33+
			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			$userCanEdit = $permissionManager->userCan( 'edit', $user, $title );
		} else {
			$userCanEdit = $title->userCan( 'edit', $user ) && $user->isAllowed( 'edit' );
		}
		$form_create_tab_text = ( $userCanEdit ) ? 'pf_formcreate' : 'pf_viewform';

		$class_name = ( $obj->getRequest()->getVal( 'action' ) == 'formcreate' ) ? 'selected' : '';
		$form_create_tab = [
			'class' => $class_name,
			'text' => wfMessage( $form_create_tab_text )->text(),
			'href' => $title->getLocalURL( 'action=formcreate' )
		];

		// Find the location of the 'create' tab, and add 'create
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
		array_splice( $tab_values, $edit_tab_location, 0, [ $form_create_tab ] );
		$content_actions = [];
		foreach ( $tab_keys as $i => $key ) {
			$content_actions[$key] = $tab_values[$i];
		}

		if ( !$obj->getUser()->isAllowed( 'viewedittab' ) ) {
			// The tab can have either of these two actions.
			unset( $content_actions['edit'] );
			unset( $content_actions['viewsource'] );
		}

		return true; // always return true, in order not to stop MW's hook processing!
	}

	/**
	 * The function called if we're in index.php (as opposed to one of the
	 * special pages).
	 * @param string $action
	 * @param Article $article
	 * @return false
	 */
	static function displayForm( $action, $article ) {
		$title = $article->getTitle();
		if ( defined( 'SMW_NS_PROPERTY' ) && $title->getNamespace() == SMW_NS_PROPERTY ) {
			$createPropertyPage = new PFCreateProperty();
			$createPropertyPage->execute( $title->getText() );
		} elseif ( $title->getNamespace() == NS_TEMPLATE ) {
			$createTemplatePage = new PFCreateTemplate();
			$createTemplatePage->execute( $title->getText() );
		} elseif ( $title->getNamespace() == PF_NS_FORM ) {
			$createFormPage = new PFCreateForm();
			$createFormPage->execute( $title->getText() );
		} elseif ( $title->getNamespace() == NS_CATEGORY ) {
			$createCategoryPage = new PFCreateCategory();
			$createCategoryPage->execute( $title->getText() );
		}

		return false;
	}
}
