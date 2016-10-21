<?php
/**
 * @author Ivan Lanin
 */

class PF_LanguageId extends PF_Language {

	/* private */ var $m_SpecialProperties = array(
		// always start upper-case
		PF_SP_HAS_DEFAULT_FORM    => 'Memiliki formulir bawaan',
		PF_SP_HAS_ALTERNATE_FORM  => 'Memiliki formulir alternatif',
		PF_SP_CREATES_PAGES_WITH_FORM => 'Membuat halaman dengan formulir',
		PF_SP_PAGE_HAS_DEFAULT_FORM   => 'Halaman memiliki formulir bawaan',
		PF_SP_HAS_FIELD_LABEL_FORMAT  => 'Memiliki format label bidang',
	);

}
