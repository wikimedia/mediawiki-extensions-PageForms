<?php
/**
 *
 * @file
 * @ingroup PF
 */

/**
 * Background job to create a new page, for use by the 'CreateClass' special
 * page.
 *
 * @author Yaron Koren
 * @ingroup PF
 */
class PFCreatePageJob extends Job {

	function __construct( $title, $params = '', $id = 0 ) {
		parent::__construct( 'createPage', $title, $params, $id );
	}

	/**
	 * Run a createPage job
	 * @return bool success
	 */
	function run() {
		if ( is_null( $this->title ) ) {
			$this->error = "createPage: Invalid title";
			return false;
		}

		$wikiPage = new WikiPage( $this->title );
		if ( !$wikiPage ) {
			$this->error = 'createPage: Wiki page not found "' . $this->title->getPrefixedDBkey() . '"';
			return false;
		}

		$page_text = $this->params['page_text'];
		// change global $wgUser variable to the one
		// specified by the job only for the extent of this
		// replacement
		global $wgUser;
		$actual_user = $wgUser;
		$wgUser = User::newFromId( $this->params['user_id'] );
		$edit_summary = '';
		if ( array_key_exists( 'edit_summary', $this->params ) ) {
			$edit_summary = $this->params['edit_summary'];
		}

		// It's strange that doEditContent() doesn't
		// automatically attach the 'bot' flag when the user
		// is a bot...
		if ( $wgUser->isAllowed( 'bot' ) ) {
			$flags = EDIT_FORCE_BOT;
		} else {
			$flags = 0;
		}

		if ( method_exists( 'WikiPage', 'doEditContent' ) ) {
			$new_content = new WikitextContent( $page_text );
			$wikiPage->doEditContent( $new_content, $edit_summary, $flags );
		} else {
			$article->doEditContent( $page_text, $edit_summary, $flags );
		}

		$wgUser = $actual_user;
		return true;
	}
}
