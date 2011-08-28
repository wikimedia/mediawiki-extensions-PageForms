<?php
/**
 * @author Niklas Laxström
 * @file
 * @ingroup SF
 */

/**
 * @ingroup SFLanguage
 */
class SF_LanguageFi extends SF_Language {

	/* private */ var $m_SpecialProperties = array(
		// always start upper-case
		SF_SP_HAS_DEFAULT_FORM    => 'Oletuslomake',
		SF_SP_HAS_ALTERNATE_FORM  => 'Vaihtoehtoinen lomake',
		SF_SP_CREATES_PAGES_WITH_FORM => 'Sivunluontilomake',
		SF_SP_PAGE_HAS_DEFAULT_FORM   => 'Sivun oletuslomake',
	);

	var $m_Namespaces = array(
		SF_NS_FORM           => 'Lomake',
		SF_NS_FORM_TALK      => 'Keskustelu_lomakkeesta'
	);

}

