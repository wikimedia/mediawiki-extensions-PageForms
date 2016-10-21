<?php
/**
 * @author Siebrand Mazeland
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFLanguage
 */
class PF_LanguageNl extends PF_Language {
	/* private */ var $m_SpecialProperties = array(
		// always start upper-case
		PF_SP_HAS_DEFAULT_FORM   => 'Heeft standaard formulier',
		PF_SP_HAS_ALTERNATE_FORM => 'Heeft alternatief formulier',
		PF_SP_CREATES_PAGES_WITH_FORM => 'Maakt pagina\'s aan via formulier',
		PF_SP_PAGE_HAS_DEFAULT_FORM   => 'Pagina heeft standaard formulier',
		PF_SP_HAS_FIELD_LABEL_FORMAT  => 'Heeft veldlabelopmaak',
	);

}
