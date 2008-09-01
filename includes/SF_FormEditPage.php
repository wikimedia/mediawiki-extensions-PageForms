<?php
/**
 * Form Edit Page inheriting from EditPage
 * 
 * @author Daniel Friesen
 */

class FormEditPage extends EditPage {
	
	protected $form;
	
	function __construct( $article, $form_name = '' ) {
		global $wgRequest;
		parent::__construct( $article );
		wfLoadExtensionMessages('SemanticForms');
		$this->action = 'formedit';
		$form_name = $wgRequest->getText('form', $form_name);
		$this->form = Title::newFromText($form_name, SF_NS_FORM);
	}
	
	function setHeaders() {
		parent::setHeaders();
		global $wgOut, $wgTitle;
		if( !$this->isConflict ) {
			$wgOut->setPageTitle( wfMsg( 'sf_editdata_title',
				$this->form->getText(), $wgTitle->getPrefixedText() ) );
		}
	}
	
	protected function showTextbox1() {
		if( $this->isConflict ) {
			// Fallback to normal mode when showing an editconflict
			parent::showTextbox1();
			return;
		}
		
		
	}
	
	
}
