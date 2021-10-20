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
		$out->enableOOUI();

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
			// Redirect to wiki interface
			$out->setArticleBodyOnly( true );
			$title = Title::makeTitleSafe( NS_CATEGORY, $category_name );
			$full_text = self::createCategoryText( $default_form, $category_name, $parent_category );
			$text = PFUtils::printRedirectForm( $title, $full_text, "", $save_page, $this->getUser() );
			$out->addHTML( $text );
			return;
		}

		// Set 'title' as hidden field, in case there's no URL niceness.
		$text = "\t" . '<form action="" method="post">' . "\n";
		$firstRow = '';
		if ( $presetCategoryName === null ) {
			$text .= "\t" . Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() ) . "\n";

			$categoryNameTextInput = new OOUI\TextInputWidget( [
				'required' => true,
				'name' => 'category_name'
			] );
			$firstRow .= new OOUI\FieldLayout(
				$categoryNameTextInput,
				[
					'label' => $this->msg( 'pf_createcategory_name' )->escaped(),
					'align' => 'top'
				]
			);
		}
		$secondRow = '';
		try {
			$all_forms = PFUtils::getAllForms();
			$options = [];
			array_push( $options, [ 'data' => null, 'label' => null ] );
			foreach ( $all_forms as $form ) {
				array_push( $options, [ 'data' => $form, 'label' => $form ] );
			}
			$formSelector = new OOUI\DropdownInputWidget( [
				'options' => $options,
				'id' => 'form_dropdown',
				'name' => 'default_form',
			] );
			$secondRow .= new OOUI\FieldLayout(
				$formSelector,
				[
					'label' => $this->msg( 'pf_createcategory_defaultform' )->escaped(),
					'align' => 'top'
				]
			);

		} catch ( MWException $e ) {
			// If we're here, it's probably because no forms have
			// been defined on this wiki. If that's the case, just
			// leave out the form selector.
		}
		$text .= $firstRow . $secondRow . "\n";
		$options = [];
		array_push( $options, [ 'data' => null, 'label' => null ] );
		$categories = PFValuesUtils::getAllCategories();
		foreach ( $categories as $category ) {
			$category = str_replace( '_', ' ', $category );
			array_push( $options, [ 'data' => $category, 'label' => $category ] );
		}
		$selectBody = new OOUI\DropdownInputWidget( [
			'options' => $options,
			'id' => 'category_dropdown',
			'name' => 'parent_category',
		] );
		$thirdRow = new OOUI\FieldLayout(
				$selectBody,
				[
					'label' => $this->msg( 'pf_createcategory_makesubcategory' )->escaped(),
					'align' => 'top'
				]
		);
		$text .= $thirdRow . "\n";

		$text .= "\t" . Html::hidden( 'csrf', $this->getUser()->getEditToken( 'CreateCategory' ) ) . "\n";
		$savePageButton = new OOUI\ButtonInputWidget( [
			'label' => $this->msg( 'savearticle' )->text(),
			'type' => 'submit',
			'name' => 'wpSave',
			'id' => 'wpSave',
			'flags' => [ 'primary', 'progressive' ],
			'useInputTag' => true
		] );

		$previewPageButton = new OOUI\ButtonInputWidget( [
			'label' => $this->msg( 'preview' )->text(),
			'type' => 'submit',
			'name' => 'wpPreview',
			'id' => 'wpPreview',
			'flags' => [ 'progressive' ],
			'useInputTag' => true
		] );

		$editButtonsText = $savePageButton . "\n";
		$editButtonsText .= $previewPageButton . "\n";
		$text .= "<br>" . Html::rawElement( 'div', [ 'class' => 'editButtons' ], $editButtonsText ) . "\n";
		$text .= "\t</form>\n";

		$out->addHTML( $text );
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
