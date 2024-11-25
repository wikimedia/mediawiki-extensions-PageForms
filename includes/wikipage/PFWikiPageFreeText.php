<?php
/**
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * Represents the "free text" in a wiki page, i.e. the text not in
 * pre-defined template calls and sections.
 */
class PFWikiPageFreeText {
	private $mText;

	function setText( $text ) {
		$this->mText = $text;
	}

	function getText() {
		return $this->mText;
	}
}
