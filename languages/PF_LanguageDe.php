<?php
/**
 * @author Dominik Rodler
 * @author Karsten Hoffmeyer (kghbln)
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFLanguage
 */
class PF_LanguageDe extends PF_Language {

	/* private */ var $m_SpecialProperties = array(
		// always start upper-case
		PF_SP_HAS_DEFAULT_FORM => 'Hat Standardformular',
		PF_SP_PAGE_HAS_DEFAULT_FORM => 'Seite Hat Standardformular',
		PF_SP_HAS_ALTERNATE_FORM => 'Hat Alternativformular',
		PF_SP_CREATES_PAGES_WITH_FORM => 'Erstellt Seiten mit Formular',
		PF_SP_HAS_FIELD_LABEL_FORMAT  => 'Hat Feldbezeichnungsformat',
	);

}
