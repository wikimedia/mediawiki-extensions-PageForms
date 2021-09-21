<?php

/**
 * Represents a user-defined form.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */
class PFForm {
	private $mFormName;
	private $mPageNameFormula;
	private $mCreateTitle;
	private $mEditTitle;
	private $mAssociatedCategory;
	private $mItems;

	static function create( $formName, $items ) {
		$form = new PFForm();
		$form->mFormName = ucfirst( str_replace( '_', ' ', $formName ) );
		$form->mAssociatedCategory = null;
		$form->mItems = $items;
		return $form;
	}

	function getFormName() {
		return $this->mFormName;
	}

	function getItems() {
		return $this->mItems;
	}

	function setPageNameFormula( $pageNameFormula ) {
		$this->mPageNameFormula = $pageNameFormula;
	}

	function setCreateTitle( $createTitle ) {
		$this->mCreateTitle = $createTitle;
	}

	function setEditTitle( $editTitle ) {
		$this->mEditTitle = $editTitle;
	}

	function setAssociatedCategory( $associatedCategory ) {
		$this->mAssociatedCategory = $associatedCategory;
	}

	function createMarkup( $includeFreeText = true, $freeTextLabel = null ) {
		$title = Title::makeTitle( PF_NS_FORM, $this->mFormName );
		$fs = PFUtils::getSpecialPage( 'FormStart' );
		$form_start_url = PFUtils::titleURLString( $fs->getPageTitle() ) . "/" . $title->getPartialURL();
		$form_description = wfMessage( 'pf_form_docu', $this->mFormName, $form_start_url )->inContentLanguage()->text();
		$form_input = "{{#forminput:form=" . str_replace( ',', '\,', $this->mFormName );
		if ( $this->mAssociatedCategory !== null ) {
			$form_input .= "|autocomplete on category=" . $this->mAssociatedCategory;
		}
		$form_input .= "}}\n";
		$text = <<<END
<noinclude>
$form_description

$form_input
</noinclude><includeonly>

END;
		$info = '';
		if ( !empty( $this->mPageNameFormula ) ) {
			$info .= "|page name=" . $this->mPageNameFormula;
		}
		if ( !empty( $this->mCreateTitle ) ) {
			$info .= "|create title=" . $this->mCreateTitle;
		}
		if ( !empty( $this->mEditTitle ) ) {
			$info .= "|edit title=" . $this->mEditTitle;
		}
		if ( $info ) {
			$text .= "{{{info" . $info . "}}}\n";
		}
		$text .= <<<END
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>

END;
		foreach ( $this->mItems as $item ) {
			if ( $item['type'] == 'template' ) {
				$template = $item['item'];
				$text .= $template->createMarkup() . "\n";
			} elseif ( $item['type'] == 'section' ) {
				$section = $item['item'];
				$text .= $section->createMarkup() . "\n";
			}
		}

		if ( $includeFreeText ) {
			if ( $freeTextLabel === null ) {
				$freeTextLabel = wfMessage( 'pf_form_freetextlabel' )->inContentLanguage()->text();
			}
			$text .= <<<END
'''$freeTextLabel:'''

{{{standard input|free text|rows=10}}}

END;
		}
		$text .= "</includeonly>\n";

		return $text;
	}

}
