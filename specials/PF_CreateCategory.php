<?php
/**
 * A special page holding a form that allows the user to create a category
 * page, with PF forms associated with it
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFSpecialPages
 */
class PFCreateCategory extends SpecialPage {

	function __construct() {
		parent::__construct( 'CreateCategory' );
	}

	static function createCategoryText( $default_form, $category_name, $parent_category ) {
		if ( $default_form === '' ) {
			$text = wfMessage( 'pf_category_desc', $category_name )->inContentLanguage()->text();
		} else {
			$text = "{{#default_form:$default_form}}";
		}
		if ( $parent_category !== '' ) {
			$namespace_labels = PFUtils::getContLang()->getNamespaces();
			$category_namespace = $namespace_labels[NS_CATEGORY];
			$text .= "\n\n[[$category_namespace:$parent_category]]";
		}
		return $text;
	}

	function execute( $query ) {
		$this->setHeaders();

		$out = $this->getOutput();
		$req = $this->getRequest();

		// Cycle through the query values, setting the appropriate
		// local variables.
		if ( $query !== null ) {
			$presetCategoryName = str_replace( '_', ' ', $query );
			$out->setPageTitle( $this->msg( 'pf-createcategory-with-name', $presetCategoryName )->text() );
			$category_name = $presetCategoryName;
		} else {
			$presetCategoryName = null;
			$category_name = $req->getVal( 'category_name' );
		}
		$default_form = $req->getVal( 'default_form' );
		$parent_category = $req->getVal( 'parent_category' );

		$category_name_error_str = null;
		$save_page = $req->getCheck( 'wpSave' );
		$preview_page = $req->getCheck( 'wpPreview' );
		if ( $save_page || $preview_page ) {
			// Guard against cross-site request forgeries (CSRF).
			$validToken = $this->getUser()->matchEditToken( $req->getVal( 'csrf' ), 'CreateCategory' );
			if ( !$validToken ) {
				$text = "This appears to be a cross-site request forgery; canceling save.";
				$out->addHTML( $text );
				return;
			}
			// Validate category name
			if ( $category_name === '' ) {
				$category_name_error_str = $this->msg( 'pf_blank_error' )->escaped();
			} else {
				// Redirect to wiki interface
				$out->setArticleBodyOnly( true );
				$title = Title::makeTitleSafe( NS_CATEGORY, $category_name );
				$full_text = self::createCategoryText( $default_form, $category_name, $parent_category );
				$text = PFUtils::printRedirectForm( $title, $full_text, "", $save_page, $preview_page, false, false, false, null, null );
				$out->addHTML( $text );
				return;
			}
		}

		// Set 'title' as hidden field, in case there's no URL niceness.
		$text = "\t" . '<form action="" method="post">' . "\n";
		$firstRow = '';
		if ( $presetCategoryName === null ) {
			$text .= "\t" . Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() ) . "\n";
			$firstRow .= $this->msg( 'pf_createcategory_name' )->escaped() . ' ' .
				Html::input( 'category_name', null, 'text',
					[ 'size' => 25 ] ) . "\n";
			if ( $category_name_error_str !== null ) {
				$firstRow .= Html::element( 'span',
					[ 'style' => 'color: red;' ],
					$category_name_error_str ) . "\n";
			}
		}
		try {
			$all_forms = PFUtils::getAllForms();
			$firstRow .= "\t" . $this->msg( 'pf_createcategory_defaultform' )->escaped() . "\n";
			$formSelector = "\t" . Html::element( 'option', null, null ) . "\n";
			foreach ( $all_forms as $form ) {
				$formSelector .= "\t" . Html::element( 'option', null, $form ) . "\n";
			}

			$firstRow .= Html::rawElement( 'select',
				[ 'id' => 'form_dropdown', 'name' => 'default_form' ],
				$formSelector );
		} catch ( MWException $e ) {
			// If we're here, it's probably because no forms have
			// been defined on this wiki. If that's the case, just
			// leave out the form selector.
		}
		$text .= Html::rawElement( 'p', null, $firstRow ) . "\n";
		$secondRow = $this->msg( 'pf_createcategory_makesubcategory' )->escaped() . ' ';
		$selectBody = "\t" . Html::element( 'option', null, null ) . "\n";
		$categories = PFValuesUtils::getAllCategories();
		foreach ( $categories as $category ) {
			$category = str_replace( '_', ' ', $category );
			$selectBody .= "\t" . Html::element( 'option', null, $category ) . "\n";
		}
		$secondRow .= Html::rawElement( 'select', [ 'id' => 'category_dropdown', 'name' => 'parent_category' ], $selectBody );
		$text .= Html::rawElement( 'p', null, $secondRow ) . "\n";

		$text .= "\t" . Html::hidden( 'csrf', $this->getUser()->getEditToken( 'CreateCategory' ) ) . "\n";

		$editButtonsText = "\t" . Html::input( 'wpSave', $this->msg( 'savearticle' )->text(), 'submit', [ 'id' => 'wpSave' ] ) . "\n";
		$editButtonsText .= "\t" . Html::input( 'wpPreview', $this->msg( 'preview' )->text(), 'submit', [ 'id' => 'wpPreview' ] ) . "\n";
		$text .= "\t" . Html::rawElement( 'div', [ 'class' => 'editButtons' ], $editButtonsText ) . "\n";
		$text .= "\t</form>\n";

		$out->addHTML( $text );
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
