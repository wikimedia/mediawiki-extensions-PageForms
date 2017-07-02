<?php

/**
 * Namespace internationalization for the Page Forms extension.
 *
 * @since 2.4.1
 *
 * @file PF_Namespaces.php
 * @ingroup PageForms
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Yaron Koren
 */

$namespaceNames = array();

if ( !defined( 'PF_NS_FORM' ) ) {
	define( 'PF_NS_FORM', 106 );
	define( 'PF_NS_FORM_TALK', 107 );
}

/**
 * @author Meno25
 */
$namespaceNames['ar'] = array(
	PF_NS_FORM           => 'استمارة',
	PF_NS_FORM_TALK      => 'نقاش_الاستمارة'
);

/**
 * @author Meno25
 */
$namespaceNames['arz'] = array(
	PF_NS_FORM           => 'استمارة',
	PF_NS_FORM_TALK      => 'نقاش_الاستمارة'
);

$namespaceNames['ca'] = array(
	PF_NS_FORM           => 'Formulari',
	PF_NS_FORM_TALK      => 'Discussió_formulari'
);

/**
 * @author Dominik Rodler
 */
$namespaceNames['de'] = array(
	PF_NS_FORM           => 'Formular',
	PF_NS_FORM_TALK      => 'Formular_Diskussion'
);

/**
 * @author Protnet
 */
$namespaceNames['el'] = array(
	PF_NS_FORM           => 'Φόρμα',
	PF_NS_FORM_TALK      => 'Συζήτηση_φόρμας'
);

$namespaceNames['en'] = array(
	PF_NS_FORM       => 'Form',
	PF_NS_FORM_TALK  => 'Form_talk',
);

$namespaceNames['es'] = array(
	PF_NS_FORM           => 'Formulario',
	PF_NS_FORM_TALK      => 'Formulario_discusión'
);

/**
 * @author Ghassem Tofighi
 */
$namespaceNames['fa'] = array(
	PF_NS_FORM           => 'فرم',
	PF_NS_FORM_TALK      => 'بحث_فرم'
);

/**
 * @author Niklas Laxström
 */
$namespaceNames['fi'] = array(
	PF_NS_FORM           => 'Lomake',
	PF_NS_FORM_TALK      => 'Keskustelu_lomakkeesta'
);

$namespaceNames['fr'] = array(
	PF_NS_FORM           => 'Formulaire',
	PF_NS_FORM_TALK      => 'Discussion_formulaire'
);

/**
 * Hebrew (עברית)
 * @author FreedomFightrerSparrow
 */
$namespaceNames['he'] = array(
	PF_NS_FORM           => 'טופס',
	PF_NS_FORM_TALK      => 'שיחת_טופס'
);

/**
 * @author Ivan Lanin
 */
$namespaceNames['id'] = array(
	PF_NS_FORM           => 'Formulir',
	PF_NS_FORM_TALK      => 'Pembicaraan_Formulir'
);

/**
 * @author Michele.Fella
 * We can't use "Modulo" here because it's used already for
 * the Scribunto "Module" namespace.
 * "Maschera" is an alternate term, short for "Maschera di
 * inserimento".
 */
$namespaceNames['it'] = array(
	PF_NS_FORM           => 'Maschera',
	PF_NS_FORM_TALK      => 'Discussione_maschera'
);

/**
 * @author Jon Harald Søby
 */
$namespaceNames['nb'] = array(
	PF_NS_FORM           => 'Skjema',
	PF_NS_FORM_TALK      => 'Skjemadiskusjon'
);

/**
 * @author Siebrand Mazeland
 */
$namespaceNames['nl'] = array(
	PF_NS_FORM      => 'Formulier',
	PF_NS_FORM_TALK => 'Overleg_formulier'
);

$namespaceNames['ru'] = array(
	PF_NS_FORM           => 'Форма',
	PF_NS_FORM_TALK      => 'Обсуждение_формы'
);

/**
 * @author Roc Michael
 */
$namespaceNamess['zh-cn'] = array(
	PF_NS_FORM           => '表单',
	PF_NS_FORM_TALK      => '表单讨论'
);

/**
 * @author Roc Michael
 */
$namespaceNames['zh-tw'] = array(
	PF_NS_FORM           => '表單',
	PF_NS_FORM_TALK      => '表單討論'
);
