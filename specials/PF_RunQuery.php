<?php
/**
 * Displays a pre-defined form that a user can run a query with.
 *
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFSpecialPages
 */
class PFRunQuery extends IncludableSpecialPage {

	function __construct() {
		parent::__construct( 'RunQuery' );
	}

	function execute( $query ) {
		if ( !$this->including() ) {
			$this->setHeaders();
		}
		$this->getOutput()->enableOOUI();

		$form_name = $this->including() ? $query : $this->getRequest()->getVal( 'form', $query );
		$form_name = str_replace( '_', ' ', $form_name );

		$this->printPage( $form_name, $this->including() );
	}

	function printPage( $form_name, $embedded = false ) {
		global $wgPageFormsFormPrinter, $wgPageFormsRunQueryFormAtTop;

		$out = $this->getOutput();
		$req = $this->getRequest();
		$user = $this->getUser();

		if ( PFUtils::ignoreFormName( $form_name ) ) {
			$text = Html::rawElement( 'p', [ 'class' => 'error' ],
				$this->msg( 'pf_autoedit_invalidform', PFUtils::linkText( PF_NS_FORM, $form_name ) )->parse() ) . "\n";
			$out->addHTML( $text );
			return;
		}

		// Get contents of form-definition page.
		$form_title = Title::makeTitleSafe( PF_NS_FORM, $form_name );

		if ( !$form_title || !$form_title->exists() ) {
			if ( $form_name === '' ) {
				$text = Html::element( 'p', [ 'class' => 'error' ], $this->msg( 'pf_runquery_badurl' )->text() ) . "\n";
			} else {
				$text = Html::rawElement( 'p', [ 'class' => 'error' ],
					$this->msg( 'pf_formstart_badform', PFUtils::linkText( PF_NS_FORM, $form_name ) )->parse() ) . "\n";
			}
			$out->addHTML( $text );
			return;
		}

		// Initialize variables.
		$form_definition = PFUtils::getPageText( $form_title );
		if ( $embedded ) {
			$req = $this->getUser()->getRequest();
			// @HACK - set $wgRequest so that FormPrinter::formHTML()
			// can have the right data. Much better would be to
			// pass this in as a parameter to formHTML().
			global $wgRequest;
			$wgRequest = $req;
		} else {
			$req = $this->getRequest();
		}

		// We check that the form name is the same, in case
		// Special:RunQuery is embedded on the page and there's more
		// than one of them.
		// Query/ies on the page can also be run automatically if
		// "_run" is added to the query string (this was added in
		// PF 4.3.1; in PF 4.3, there was no such option, and before
		// 4.3, "wpRunQuery=true" was used).
		$form_submitted = $req->getCheck( '_run' ) || $req->getVal( 'pfRunQueryFormName' ) == $form_name;
		$content = $req->getVal( 'wpTextbox1' );
		$raw = $req->getBool( 'raw', false );

		if ( $raw ) {
			$out->setArticleBodyOnly( true );
		}

		list( $form_text, $data_text, $form_page_title ) =
			$wgPageFormsFormPrinter->formHTML(
				$form_definition, $form_submitted, false, $form_title->getArticleID(),
				$content, null, null, true, $embedded, false, [], $user
			);
		$text = "";

		// Get the text of the results.
		$resultsText = '';

		if ( $form_submitted ) {
			// @TODO - fix RunQuery's parsing so that this check
			// isn't needed.
			if ( PFUtils::getParser()->getOutput() == null ) {
				$headItems = [];
			} else {
				$headItems = PFUtils::getParser()->getOutput()->getHeadItems();
			}
			foreach ( $headItems as $key => $item ) {
				$out->addHeadItem( $key, "\t\t" . $item . "\n" );
			}

			PFUtils::getParser()->mOptions = ParserOptions::newFromUser( $user );
			$resultsText = PFUtils::getParser()->parse( $data_text, $this->getPageTitle(), PFUtils::getParser()->mOptions, true, false )->getText();
		}

		// Get the full text of the form.
		$fullFormText = '';
		$additionalQueryHeader = '';
		$dividerText = '';
		if ( !$raw ) {
			// Create the "additional query" header, and the
			// divider text - one of these (depending on whether
			// the query form is at the top or bottom) is displayed
			// if the form has already been submitted.
			if ( $form_submitted ) {
				$additionalQueryHeader = "\n" . Html::element( 'h2', null, $this->msg( 'pf_runquery_additionalquery' )->text() ) . "\n";
				$dividerText = "\n<hr style=\"margin: 15px 0;\" />\n";
			}

			if ( $embedded ) {
				$embeddingPageName = $req->getVal( 'title' );
				if ( $embeddingPageName == '' ) {
					// Seems to happen on page save.
					$realTitle = $this->getPageTitle();
				} else {
					$realTitle = Title::newFromText( $embeddingPageName );
				}
			} else {
				$realTitle = $this->getPageTitle( $form_name );
			}

			// Preserve all query string values in the results
			// page - including "title", in case this wiki has the
			// default URL structure.
			$queryStringValues = $req->getValues();
			if ( !array_key_exists( 'pfRunQueryFormName', $queryStringValues ) ) {
				$queryStringValues['pfRunQueryFormName'] = $form_name;
			}
			$action = htmlspecialchars( $realTitle->getLocalURL() );

			$fullFormText .= <<<END
	<form id="pfForm" name="createbox" action="$action" method="get" class="createbox">

END;
			foreach ( $queryStringValues as $key => $value ) {
				if ( is_array( $value ) ) {
					$value = wfArrayToCgi( $value );
				}
				$fullFormText .= Html::hidden( $key, $value ) . "\n";
			}
			$fullFormText .= $form_text;
		}

		// Either don't display a query form at all, or display the
		// query form at the top, and the results at the bottom, or the
		// other way around, depending on the settings.
		if ( $req->getVal( 'additionalquery' ) == 'false' ) {
			$text .= $resultsText;
		} elseif ( $wgPageFormsRunQueryFormAtTop ) {
			$text .= Html::openElement( 'div', [ 'class' => 'pf-runquery-formcontent' ] );
			$text .= $fullFormText;
			$text .= $dividerText;
			$text .= Html::closeElement( 'div' );
			$text .= $resultsText;
		} else {
			$text .= $resultsText;
			$text .= Html::openElement( 'div', [ 'class' => 'pf-runquery-formcontent' ] );
			$text .= $additionalQueryHeader;
			$text .= $fullFormText;
			$text .= Html::closeElement( 'div' );
		}

		if ( $embedded ) {
			$text = "<div class='runQueryEmbedded'>$text</div>";
		}

		// Armor against doBlockLevels()
		$text = preg_replace( '/^ +/m', '', $text );

		// Now write everything to the screen.
		$out->addHTML( $text );
		PFUtils::addFormRLModules( $embedded ? PFUtils::getParser() : null );
		if ( !$embedded ) {
			$po = PFUtils::getParser()->getOutput();
			if ( $po ) {
				$out->addParserOutputMetadata( $po );
			}
		}

		// Finally, set the page title - previously, this had to be
		// called after addParserOutputNoText() for it to take effect;
		// now the order doesn't matter.
		if ( !$embedded ) {
			if ( $form_page_title != null ) {
				$out->setPageTitle( $form_page_title );
			} else {
				$s = $this->msg( 'pf_runquery_title', $form_title->getText() )->text();
				$out->setPageTitle( $s );
			}
		}
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
