<?php
/**
 * @author Yaron Koren
 * @file
 * @ingroup PF
 */

/**
 * Represents a section (header and contents) in a wiki page.
 */
class PFWikiPageSection {
	private $mHeader, $mHeaderLevel, $mText, $mHideIfEmpty;

	function __construct( $sectionName, $headerLevel, $sectionText, $sectionOptions ) {
		$this->mHeader      = $sectionName;
		$this->mHeaderLevel = $headerLevel;
		$this->mText        = $sectionText;
		$this->mHideIfEmpty = $sectionOptions['hideIfEmpty'];
	}

	/**
	 * @return bool
	 */
	function isHideIfEmpty() {
		return $this->mHideIfEmpty;
	}

	function getHeader() {
		return $this->mHeader;
	}

	function getHeaderLevel() {
		return $this->mHeaderLevel;
	}

	function getText() {
		return $this->mText;
	}
}
