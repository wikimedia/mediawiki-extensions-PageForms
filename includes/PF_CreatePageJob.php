<?php
/**
 *
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * Background job to create a new page, for use by the 'CreateClass' special
 * page.
 *
 * @author Yaron Koren
 * @ingroup PF
 */
class PFCreatePageJob extends Job {

	function __construct( Title $title, array $params ) {
		parent::__construct( 'pageFormsCreatePage', $title, $params );
		$this->removeDuplicates = true;
	}

	/**
	 * Run a pageFormsCreatePage job
	 * @return bool success
	 */
	function run() {
		if ( $this->title === null ) {
			$this->error = "pageFormsCreatePage: Invalid title";
			return false;
		}

		try {
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->title );
		// @phan-suppress-next-line PhanUnusedVariableCaughtException
		} catch ( MWException $e ) {
			$this->error = 'pageFormsCreatePage: Wiki page not found "' . $this->title->getPrefixedDBkey() . '"';
			return false;
		}

		$pageText = $this->params['page_text'];
		if ( array_key_exists( 'edit_summary', $this->params ) ) {
			$editSummary = $this->params['edit_summary'];
		} else {
			$editSummary = '';
		}
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $this->params['user_id'] );

		self::createOrModifyPage( $wikiPage, $pageText, $editSummary, $user );

		return true;
	}

	public static function createOrModifyPage( $wikiPage, $pageText, $editSummary, $user ) {
		$newContent = new WikitextContent( $pageText );

		// It's strange that doEditContent() doesn't automatically
		// attach the 'bot' flag when the user is a bot...
		// @TODO - is all this code still necessary?
		$flags = 0;
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( $permissionManager->userHasRight( $user, 'bot' ) ) {
			$flags = EDIT_FORCE_BOT;
		}

		$updater = $wikiPage->newPageUpdater( $user );
		$updater->setContent( MediaWiki\Revision\SlotRecord::MAIN, $newContent );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( $editSummary ), $flags );
	}

}
