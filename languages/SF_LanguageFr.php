<?php
/**
 * @author Yaron Koren
 */

class SF_LanguageFr extends SF_Language {

/* private */ var $m_ContentMessages = array(
	'sf_template_docu' => 'Ceci est la modèle « $1 ». Elle doit être appelée par le format suivant:',
	'sf_template_docufooter' => 'Modifier la page pour voir la texte de la modèle.',
	// month names are already defined in MediaWiki, but unfortunately
	// there they're defined as user messages, and here they're
	// content messages
	'sf_january' => 'janvier',
	'sf_february' => 'février',
	'sf_march' => 'mars',
	'sf_april' => 'avril',
	'sf_may' => 'mai',
	'sf_june' => 'juin',
	'sf_july' => 'juillet',
	'sf_august' => 'août',
	'sf_september' => 'septembre',
	'sf_october' => 'octobre',
	'sf_november' => 'novembre',
	'sf_december' => 'décembre'
);

/* private */ var $m_UserMessages = array(
	'templates' => 'Modèles',
	'sf_templates_docu' => 'Les modèle suivantes existent dans le wiki.',
	'sf_templates_definescat' => 'défine la catégorie:',
	'createtemplate' => 'Créer une modèle',
	'sf_createtemplate_namelabel' => 'Nom de modèle:',
	'sf_createtemplate_categorylabel' => 'Catégorie définée par cette modèle (volontaire):',
	'sf_createtemplate_templatefields' => 'Champs de modèle',
	'sf_createtemplate_fieldsdesc' => 'Pour une modèle qui n\'utilise pas de noms pour les champs, simplement entrer l\'index de ce champ (e.g. 1, 2, 3, etc.) comme le nom, au lieu d\'un nom actuel.',
	'sf_createtemplate_fieldname' => 'Nom de champ:',
	'sf_createtemplate_displaylabel' => 'Étiquette pour l\'affichage:',
	'sf_createtemplate_semanticfield' => 'Propriètè sèmantique:',
	'sf_createtemplate_addfield' => 'Ajouter un champ',
	'sf_createtemplate_deletefield' => 'Efface',
	'forms' => 'Formulaires',
	'sf_forms_docu' => 'Les formulaires suivants existent dans le wiki.',
	'createform' => 'Créer un formulaire',
	'sf_createform_nameinput' => 'Nom de formulaire (la convention est de nommer le formulaire après la modèle principale qu\'il peuple):',
	'sf_createform_template' => 'Modèle:',
	'sf_createform_templatelabelinput' => 'Étiquette de modèle (volontaire):',
	'sf_createform_allowmultiple' => 'Laisser plusieurs (ou zero) instances de ce modèle dans la page',
	'sf_createform_field' => 'Champ:',
	'sf_createform_fieldattr' => 'Ce champ définit l\'attribut $1, de type $2.',
	'sf_createform_fieldattrunknowntype' => 'Ce champ définit l\'attribut $1, de type non spécifié.',
	'sf_createform_fieldrel' => 'Ce champ définit la relation $1.',
	'sf_createform_formlabel' => 'Étiquette dans le formulaire:',
	'sf_createform_hidden' =>  'Caché',
	'sf_createform_restricted' =>  'Restreint',
	'sf_createform_mandatory' =>  'Obligatoire',
	'sf_createform_removetemplate' => 'Enlever cette modèle',
	'sf_createform_addtemplate' => 'Ajouter une modèle:',
	'sf_createform_beforetemplate' => 'Avant modèle:',
	'sf_createform_atend' => 'À la fin',
	'sf_createform_add' => 'Ajouter',
	'addpage' => 'Ajouter une page',
	'addoreditdata' => 'Modifer ou ajouter données',
	'adddata' => 'Ajouter données',
	'sf_adddata_badurl' => 'Ceci est la page pour ajouter les données. Il faut indiquer un nom de formulaire et une page cible dans l\'URL; l\'URL doit ressembler à « Special:AddData?form=&lt;nom de formulaire&gt;&target=&lt;nom de page cible&gt; » ou « Special:AddData/&lt;nom de formulaire&gt;/&lt;nom de page cible&gt; ».',
	'sf_forms_adddata' => 'Ajouter données avec ce formulaire',
	'editdata' => 'Modifier les données',
	'form_edit' => 'Modifier avec formulaire',
	'sf_editdata_badurl' => 'Ceci est la page pour modifier les données. Il faut indiquer un nom de formulaire et une page cible dans l\'URL; l\'URL doit ressembler à « Special:EditData?form=&lt;nom de formulaire&gt;&target=&lt;nom de page cible&gt; » ou « Special:EditData/&lt;nom de formulaire&gt;/&lt;nom de page cible&gt; ».',
	'sf_editdata_remove' => 'Enlever',
	'sf_editdata_addanother' => 'Ajouter un autre',
	'sf_editdata_freetextlabel' => 'Texte libre',

	'sf_blank_error' => 'ne peut pas être blanc'
);

/* private */ var $m_SpecialProperties = array(
	//always start upper-case
	SF_SP_HAS_DEFAULT_FORM  => 'Utilise le formulaire'
);

/* private */ var $m_SpecialPropertyAliases = array(
	// support English aliases for special properties
	'Has default form'	=> SF_SP_HAS_DEFAULT_FORM,
	'Has alternate form'	=> SF_SP_HAS_ALTERNATE_FORM
);

var $m_Namespaces = array(
	SF_NS_FORM           => 'Formulaire',
	SF_NS_FORM_TALK      => 'Discussion_formulaire'
);

var $m_NamespaceAliases = array(
	// support English aliases for namespaces
	'Form'		=> SF_NS_FORM,
	'Form_talk'	=> SF_NS_FORM_TALK
);

}

?>
