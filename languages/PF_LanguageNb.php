<?php
/**
 * @author Jon Harald Søby
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFLanguage
 */
class PF_LanguageNb extends PF_Language {

	/* private */ var $m_SpecialProperties = array(
		// always start upper-case
		PF_SP_HAS_DEFAULT_FORM    => 'Har standardskjema',
		PF_SP_HAS_ALTERNATE_FORM  => 'Har alternativt skjema'
	);

}
