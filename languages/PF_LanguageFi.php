<?php
/**
 * @author Niklas Laxström
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFLanguage
 */
class PF_LanguageFi extends PF_Language {

	/* private */ var $m_SpecialProperties = array(
		// always start upper-case
		PF_SP_HAS_DEFAULT_FORM    => 'Oletuslomake',
		PF_SP_HAS_ALTERNATE_FORM  => 'Vaihtoehtoinen lomake',
		PF_SP_CREATES_PAGES_WITH_FORM => 'Sivunluontilomake',
		PF_SP_PAGE_HAS_DEFAULT_FORM   => 'Sivun oletuslomake',
	);

}
