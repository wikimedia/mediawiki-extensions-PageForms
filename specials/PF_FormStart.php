<?php
/**
 * Displays a form for entering the title of a page, which then redirects
 * to the form for creating/editing the page.
 *
 * @author Yaron Koren
 * @author Jeffrey Stuckman
 * @file
 * @ingroup PF
 */

use MediaWiki\Html\Html;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;

/**
 * @ingroup PFSpecialPages
 */
class PFFormStart extends SpecialPage {

	private WikiPageFactory $wikiPageFactory;

	function __construct(
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( 'FormStart' );
		$this->wikiPageFactory = $wikiPageFactory;
	}

	function execute( $query ) {
		$this->setHeaders();

		$out = $this->getOutput();
		$req = $this->getRequest();

		$form_name = $req->getVal( 'form' );
		$target_namespace = $req->getVal( 'namespace' );
		$super_page = $req->getVal( 'super_page' );
		$params = $req->getVal( 'params' );

		// If the query string did not contain a form name, try the URL.
		if ( $form_name == '' && $query !== null ) {
			$queryparts = explode( '/', $query, 2 );
			$form_name = isset( $queryparts[0] ) ? $queryparts[0] : '';
			// If a target was specified, it means we should
			// redirect to 'FormEdit' for this target page.
			if ( isset( $queryparts[1] ) ) {
				$target_name = $queryparts[1];
				$this->doRedirect( $form_name, $target_name, $params );
			}
		}

		// Get title of form.
		$form_title = Title::makeTitleSafe( PF_NS_FORM, $form_name );

		// Handle submission of this form.
		$form_submitted = $req->getCheck( 'page_name' );
		if ( $form_submitted ) {
			$page_name = trim( $req->getVal( 'page_name' ) );
			// This form can be used to create a sub-page for an
			// existing page
			if ( $super_page !== null && $super_page !== '' ) {
				$page_name = "$super_page/$page_name";
			}

			if ( $page_name !== '' ) {
				// Append the namespace prefix to the page name,
				// if this namespace was not already entered.
				if ( $target_namespace != '' && strpos( $page_name, $target_namespace . ':' ) === false ) {
					$page_name = $target_namespace . ':' . $page_name;
				}
				// If there was no page title, it's probably an
				// invalid page name, containing forbidden
				// characters - in that case, display an error
				// message.
				$page_title = Title::newFromText( $page_name );
				if ( !$page_title ) {
					$out->addHTML( $this->msg( 'pf_formstart_badtitle', $page_name )->escaped() );
					return;
				} else {
					$this->doRedirect( $form_name, $page_name, $params );
					return;
				}
			}
		}

		$out->addModules( 'ext.pageforms.forminput' );

		if ( ( !$form_title || !$form_title->exists() ) && ( $form_name !== '' ) ) {
			$linkToForm = PFUtils::linkText( PF_NS_FORM, $form_name );
			$badFormMsg = $this->msg( 'pf_formstart_badform', $linkToForm )->parse();
			$text = Html::rawElement( 'p', [ 'class' => 'error' ], $badFormMsg ) . "\n";
		} else {
			if ( $form_name === '' ) {
				$description = $this->msg( 'pf_formstart_noform_docu', $form_name )->escaped();
			} else {
				$description = $this->msg( 'pf_formstart_docu', $form_name )->escaped();
			}

			$text = <<<END
	<form action="" method="post">
	<p>$description</p>

END;
			$text .= Html::hidden( 'namespace', $target_namespace );
			$text .= Html::hidden( 'super_page', $super_page );
			$text .= Html::hidden( 'params', $params );

			$formInputAttrs = [
				'class' => 'pfFormInputWrapper',
				'data-button-label' => $this->msg( 'pf_formstart_createoredit' )->text()
			];

			// If no form was specified, display a dropdown letting
			// the user choose the form.
			if ( $form_name === '' ) {
				try {
					$allForms = PFUtils::getAllForms();
				} catch ( MWException $e ) {
					$out->addHTML( Html::element( 'div', [ 'class' => 'error' ], $e->getMessage() ) );
					return;
				}
				$formInputAttrs['data-possible-forms'] = implode( '|', $allForms );
				$formInputAttrs['data-form-label'] = wfMessage( 'pf-formstart-formlabel' )->escaped();
			} else {
				$formInputAttrs['data-autofocus'] = true;
			}

			$text .= "\t" . Html::element( 'div', $formInputAttrs, null ) . "\n";
			$text .= "\t</form>\n";
		}
		$out->addHTML( $text );
	}

	/**
	 * Helper function - returns a URL that includes Special:FormEdit.
	 * @param string $formName
	 * @param string $targetName
	 * @return string
	 */
	static function getFormEditURL( $formName, $targetName ) {
		$fe = PFUtils::getSpecialPage( 'FormEdit' );
		// Special handling for forms whose name contains a slash.
		if ( strpos( $formName, '/' ) !== false ) {
			return $fe->getPageTitle()->getLocalURL( [ 'form' => $formName, 'target' => $targetName ] );
		}
		return $fe->getPageTitle( "$formName/$targetName" )->getLocalURL();
	}

	function doRedirect( $form_name, $page_name, $params ) {
		$out = $this->getOutput();

		$page_title = Title::newFromText( $page_name );
		if ( $page_title->exists() ) {
			// It exists - see if page is a redirect; if
			// it is, edit the target page instead.
			$content = $this->wikiPageFactory->newFromTitle( $page_title )->getContent();
			if ( $content && $content->getRedirectTarget() ) {
				$page_title = $content->getRedirectTarget();
				$page_name = PFUtils::titleURLString( $page_title );
			}
			// HACK - if this is the default form for
			// this page, send to the regular 'formedit'
			// tab page; otherwise, send to the 'Special:FormEdit'
			// page, with the form name hardcoded.
			// Is this logic necessary? Or should we just
			// out-guess the user and always send to the
			// standard form-edit page, with the 'correct' form?
			$default_forms = PFFormLinker::getDefaultFormsForPage( $page_title );
			if ( count( $default_forms ) > 0 ) {
				$default_form_name = $default_forms[0];
			} else {
				$default_form_name = null;
			}
			if ( $form_name == $default_form_name ) {
				$redirect_url = $page_title->getLocalURL( 'action=formedit' );
			} else {
				$redirect_url = self::getFormEditURL( $form_name, $page_name );
			}
		} else {
			$redirect_url = self::getFormEditURL( $form_name, $page_name );
			// Of all the request values, send on to 'FormEdit' only
			// 'preload', 'returnto', and specific form fields - we can
			// identify the latter because they show up as arrays.
			foreach ( $_REQUEST as $key => $val ) {
				if ( is_array( $val ) ) {
					$redirect_url .= ( strpos( $redirect_url, '?' ) > -1 ) ? '&' : '?';
					// Re-add the key (i.e. the template
					// name), so we can make a nice query
					// string snippet out of the whole
					// thing.
					$wrapperArray = [ $key => $val ];
					$redirect_url .= urldecode( http_build_query( $wrapperArray ) );
				} elseif ( $key == 'preload' || $key == 'returnto' ) {
					$redirect_url .= ( strpos( $redirect_url, '?' ) > -1 ) ? '&' : '?';
					$redirect_url .= "$key=$val";
				}
			}
		}

		if ( $params !== null && $params !== '' ) {
			$redirect_url .= ( strpos( $redirect_url, '?' ) > -1 ) ? '&' : '?';
			$redirect_url .= $params;
		}

		$out->setArticleBodyOnly( true );

		// Show "loading" animated image while people wait for the
		// redirect.
		global $wgPageFormsScriptPath;
		$loadingImage = Html::element( 'img', [ 'src' => "$wgPageFormsScriptPath/skins/loading.gif" ] );
		$text = "\t" . Html::rawElement( 'p', [ 'style' => "position: absolute; left: 45%; top: 45%;" ], $loadingImage );
		$text .= "\t" . Html::element( 'meta', [ 'http-equiv' => 'refresh', 'content' => "0; url=$redirect_url" ] );
		$out->addHTML( $text );
	}

	protected function getGroupName() {
		return 'pf_group';
	}
}
