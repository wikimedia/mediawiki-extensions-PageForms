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

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'RunQuery' );
	}

	function execute( $query ) {
		if ( !$this->including() ) {
			$this->setHeaders();
		}
		$form_name = $this->including() ? $query : $this->getRequest()->getVal( 'form', $query );
		$form_name = str_replace( '_', ' ', $form_name );

		$this->printPage( $form_name, $this->including() );
	}

	function printPage( $form_name, $embedded = false ) {
		global $wgPageFormsFormPrinter, $wgParser, $wgPageFormsRunQueryFormAtTop;

		$out = $this->getOutput();
		$req = $this->getRequest();
		$user = $this->getUser();

		// Get contents of form-definition page.
		$form_title = Title::makeTitleSafe( PF_NS_FORM, $form_name );

		if ( !$form_title || !$form_title->exists() ) {
			if ( $form_name === '' ) {
				$text = Html::element( 'p', array( 'class' => 'error' ), wfMessage( 'pf_runquery_badurl' )->text() ) . "\n";
			} else {
				$text = Html::rawElement( 'p', array( 'class' => 'error' ),
					wfMessage( 'pf_formstart_badform', PFUtils::linkText( PF_NS_FORM, $form_name ) )->parse() ) . "\n";
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
			$wgPageFormsFormPrinter->formHTML( $form_definition, $form_submitted, false, $form_title->getArticleID(), $content, null, null, true, $embedded );
		$text = "";

		// Get the text of the results.
		$resultsText = '';

		if ( $form_submitted ) {
			// @TODO - fix RunQuery's parsing so that this check
			// isn't needed.
			if ( $wgParser->getOutput() == null ) {
				$headItems = array();
			} else {
				$headItems = $wgParser->getOutput()->getHeadItems();
			}
			foreach ( $headItems as $key => $item ) {
				$out->addHeadItem( $key, "\t\t" . $item . "\n" );
			}

			$wgParser->mOptions = ParserOptions::newFromUser( $user );
			$resultsText = $wgParser->parse( $data_text, $this->getPageTitle(), $wgParser->mOptions, true, false )->getText();
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
				$additionalQueryHeader = "\n" . Html::element( 'h2', null, wfMessage( 'pf_runquery_additionalquery' )->text() ) . "\n";
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

			// Preserve all query string values in the results page.
			$queryStringValues = array();
			foreach ( $req->getValues() as $key => $value ) {
				if ( $key != 'title' ) {
					$queryStringValues[$key] = $value;
				}
			}
			$action = htmlspecialchars( $realTitle->getLocalURL( $queryStringValues ) );

			$fullFormText .= <<<END
	<form id="pfForm" name="createbox" action="$action" method="post" class="createbox">

END;
			$fullFormText .= Html::hidden( 'pfRunQueryFormName', $form_name );
			$fullFormText .= $form_text;
		}

		// Either don't display a query form at all, or display the
		// query form at the top, and the results at the bottom, or the
		// other way around, depending on the settings.
		if ( $req->getVal( 'additionalquery' ) == 'false' ) {
			$text .= $resultsText;
		} elseif ( $wgPageFormsRunQueryFormAtTop ) {
			$text .= Html::openElement( 'div', array( 'class' => 'pf-runquery-formcontent' ) );
			$text .= $fullFormText;
			$text .= $dividerText;
			$text .= Html::closeElement( 'div' );
			$text .= $resultsText;
		} else {
			$text .= $resultsText;
			$text .= Html::openElement( 'div', array( 'class' => 'pf-runquery-formcontent' ) );
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
		PFUtils::addFormRLModules( $embedded ? $wgParser : null );
		if ( !$embedded ) {
			$po = $wgParser->getOutput();
			if ( $po ) {
				// addParserOutputMetadata was introduced in 1.24 when addParserOutputNoText was deprecated
				if ( method_exists( $out, 'addParserOutputMetadata' ) ) {
					$out->addParserOutputMetadata( $po );
				} else {
					$out->addParserOutputNoText( $po );
				}
			}
		}

		// Finally, set the page title - previously, this had to be
		// called after addParserOutputNoText() for it to take effect;
		// now the order doesn't matter.
		if ( !$embedded ) {
			if ( $form_page_title != null ) {
				$out->setPageTitle( $form_page_title );
			} else {
				$s = wfMessage( 'pf_runquery_title', $form_title->getText() )->text();
				$out->setPageTitle( $s );
			}
		}
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
