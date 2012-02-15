<?php
/**
 * @author Yaron Koren
 * @file
 * @ingroup SF
 */

/**
 * Base class for all language classes - a truncated version of Semantic
 * MediaWiki's 'SMW_Language' class
 * @ingroup SFLanguage
 */
abstract class SF_Language {

	// arrays for the names of special properties and namespaces -
	// all messages are stored in SF_Messages.php
	protected $m_SpecialProperties;

	// By default, every language has English-language aliases for
	// special properties and namespaces
	protected $m_SpecialPropertyAliases = array(
		'Has default form'	=> SF_SP_HAS_DEFAULT_FORM,
		'Has alternate form'	=> SF_SP_HAS_ALTERNATE_FORM,
		'Creates pages with form'	=> SF_SP_CREATES_PAGES_WITH_FORM,
	);

	/**
	 * Function that returns the labels for the special properties.
	 */
	function getPropertyLabels() {
		return $this->m_SpecialProperties;
	}

	/**
	 * Aliases for special properties, if any.
	 */
	function getPropertyAliases() {
		return $this->m_SpecialPropertyAliases;
	}

}
