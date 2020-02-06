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

	function __construct( Title $title, array $params ) {
		parent::__construct( 'createPage', $title, $params );
		$this->removeDuplicates = true;
	}

	/**
	 * Run a createPage job
	 * @return bool success
	 */
	function run() {
		if ( $this->title === null ) {
			$this->error = "createPage: Invalid title";
			return false;
		}

		$wikiPage = new WikiPage( $this->title );
		if ( !$wikiPage ) {
			$this->error = 'createPage: Wiki page not found "' . $this->title->getPrefixedDBkey() . '"';
			return false;
		}

		$page_text = $this->params['page_text'];
		$user = User::newFromId( $this->params['user_id'] );
		$edit_summary = '';
		if ( array_key_exists( 'edit_summary', $this->params ) ) {
			$edit_summary = $this->params['edit_summary'];
		}

		// It's strange that doEditContent() doesn't
		// automatically attach the 'bot' flag when the user
		// is a bot...
		if ( $user->isAllowed( 'bot' ) ) {
			$flags = EDIT_FORCE_BOT;
		} else {
			$flags = 0;
		}

		$new_content = new WikitextContent( $page_text );
		$wikiPage->doEditContent( $new_content, $edit_summary, $flags, false, $user );

		return true;
	}
}
