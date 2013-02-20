<?php
/**
 * Displays a pre-defined form for either creating a new page or editing an
 * existing one.
 *
 * @author Yaron Koren
 * @file
 * @ingroup SF
 */

/**
 * @ingroup SFSpecialPages
 */
class SFFormEdit extends SpecialPage {

	public $mTarget;
	public $mForm;
	public $mError;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'FormEdit' );
	}

	function execute( $query, $redirectOnError = true ) {

		wfProfileIn( __METHOD__ );

		$this->setHeaders();

		$this->mForm = $this->getRequest()->getText( 'form' );
		$this->mTarget = $this->getRequest()->getText( 'target' );

		// if query string did not contain these variables, try the URL
		if ( !$this->mForm && !$this->mTarget ) {
			$queryparts = explode( '/', $query, 2 );
			$this->mForm = isset( $queryparts[ 0 ] ) ? $queryparts[ 0 ] : '';
			$this->mTarget = isset( $queryparts[ 1 ] ) ? $queryparts[ 1 ] : '';
		}

		$alt_forms = $this->getRequest()->getArray( 'alt_form' );

		self::printForm( $this->mForm, $this->mTarget, $alt_forms, $redirectOnError );

		wfProfileOut( __METHOD__ );
	}

	static function printAltFormsList( $alt_forms, $target_name ) {
		$text = "";
		$fe = SFUtils::getSpecialPage( 'FormEdit' );
		$fe_url = $fe->getTitle()->getFullURL();
		$i = 0;
		foreach ( $alt_forms as $alt_form ) {
			if ( $i++ > 0 ) {
				$text .= ', ';
			}
			$text .= "<a href=\"$fe_url/$alt_form/$target_name\">" . str_replace( '_', ' ', $alt_form ) . '</a>';
		}
		return $text;
	}

	static function printForm( &$form_name, &$targetName, $alt_forms = array( ) ) {

		global $wgOut, $wgRequest;

		if ( method_exists( 'ApiMain', 'getContext' ) ) {
			$module = new SFAutoeditAPI( new ApiMain(), 'sfautoedit' );
		} else { // TODO: remove else branch when raising supported version to MW 1.19
			$module = new SFAutoeditAPI( new ApiMain( $wgRequest ), 'sfautoedit' );
		}
		$module->setOption( 'form', $form_name );
		$module->setOption( 'target', $targetName );

		if ( $wgRequest->getCheck( 'wpSave' ) || $wgRequest->getCheck( 'wpPreview' ) || $wgRequest->getCheck( 'wpDiff' ) ) {
			// if the page was submitted, formdata should be complete => do not preload
			$module->setOption( 'preload', false );
		} else if ( !empty($targetName) && Title::newFromText( $targetName )->exists ( ) ) {
			// if target page exists do not overwrite it with preload data, just preload the page's data
			$module->setOption( 'preload', true );
		} else if ( $wgRequest->getCheck( 'preload' ) ) {
			// if page does not exist and preload parameter is set, pass that on
			$module->setOption( 'preload', $wgRequest->getText( 'preload' ) );
		} else {
			// nothing set, so do not preload
			$module->setOption( 'preload', false );			
		}

		$module->execute();

		// if action was successful and action was a Save, return
		if ( $module->getStatus() === 200 && $module->getAction() === SFAutoeditAPI::ACTION_SAVE ) {
			return;
		}

		// override the default title for this page if a title was specified in the form
		$result = $module->getOptions();
		$targetTitle = Title::newFromText( $result[ 'target' ] );

		// set page title depending on whether the target page exists

		if ( $result[ 'form' ] !== '' ) {
			if ( empty( $targetName ) ) {
				$pageTitle = wfMessage( 'sf_formedit_createtitlenotarget', $result[ 'form' ] )->text();
			} elseif ( $targetTitle->exists() ) {
				$pageTitle = wfMessage( 'sf_formedit_edittitle', $result[ 'form' ], $targetName )->text();
			} else {
				$pageTitle = wfMessage( 'sf_formedit_createtitle', $result[ 'form' ], $targetName )->text();
			}
			$wgOut->setPageTitle( $pageTitle );
		}

		$text = '';
		if ( count( $alt_forms ) > 0 ) {
			$text .= '<div class="infoMessage">' . wfMessage( 'sf_formedit_altforms' )->escaped() . ' ';
			$text .= self::printAltFormsList( $alt_forms, $targetName );
			$text .= "</div>\n";
		}

		$text .= '<form name="createbox" id="sfForm" method="post" class="createbox">';
		$pre_form_html = '';
		wfRunHooks( 'sfHTMLBeforeForm', array( &$targetTitle, &$pre_form_html ) );
		$text .= $pre_form_html;
		if ( isset( $result[ 'formHTML' ] ) ) {
			$text .= $result[ 'formHTML' ];
		}

		SFUtils::addJavascriptAndCSS();

		if ( isset( $result[ 'formJS' ] ) ) {
			$wgOut->addScript( '		<script type="text/javascript">' . "\n$result[formJS]\n" . '</script>' . "\n" );
		}

		$wgOut->addHTML( $text );

		return null;
	}

}
