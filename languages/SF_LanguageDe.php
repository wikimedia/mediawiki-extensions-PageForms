<?php
/**
 * @author Dominik Rodler
 */

class SF_LanguageDe {

/* private */ var $sfContentMessages = array(
	'sf_property_isattribute' => 'Dies ist ein Attribut des Typs $1.',
	'sf_property_isproperty' => 'Dies ist eine Eigenschaft des Typs $1.',
	'sf_property_allowedvals' => 'Die möglichen Werte f�r diese Eigenschaft bzw. dieses Attribut sind:',
	'sf_property_isrelation' => 'Dies ist eine Beziehung.',
	'sf_template_docu' => 'Dies ist die Vorlage f�r \'$1\'. Sie sollte im folgenden Format aufgerufen werden:',
	'sf_template_docufooter' => 'Bearbeiten sie diese Seite, um den Vorlagentext zu sehen.',
	'sf_form_docu' => 'Geben sie in dieses Textfeld den Namen der Seite ein, die mit Formular \'$1\' erstellt werden soll. Wenn bereits eine Seite mit diesem Namen existiert werden sie zu einem Formular weitergeleitet, mit dem sie diese Seite bearbeiten können.',
	// month names are already defined in MediaWiki, but unfortunately
	// there they're defined as user messages, and here they're
	// content messages
	'sf_january' => 'Januar',
	'sf_february' => 'Februar',
	'sf_march' => 'März',
	'sf_april' => 'April',
	'sf_may' => 'Mai',
	'sf_june' => 'Juni',
	'sf_july' => 'Juli',
	'sf_august' => 'August',
	'sf_september' => 'September',
	'sf_october' => 'Oktober',
	'sf_november' => 'November',
	'sf_december' => 'Dezember',
	'sf_blank_namespace' => 'Main'
);

/* private */ var $sfUserMessages = array(
	'createproperty' => 'Erstelle eine semantische Eigenschaft',
	'sf_createproperty_allowedvalsinput' => 'Wenn sie führ dieses Feld nur bestimmte Werte ermöglichen wollen geben sie diese bitte als kommagetrennte Liste ein (wenn ein Wert ein Komma enthält, ersetzen sie dieses mit "\,"):',
	'sf_createproperty_propname' => 'Name:',
	'sf_createproperty_proptype' => 'Typ:',
	'templates' => 'Vorlage ',
	'sf_templates_docu' => 'Die folgenden Vorlagen existieren in der Wiki.',
	'sf_templates_definescat' => 'definiert Kategorie:',
	'createtemplate' => 'Erstelle eine Vorlage',
	'sf_createtemplate_namelabel' => 'Vorlagenname:',
	'sf_createtemplate_categorylabel' => 'Durch Vorlage definierte Kategorie (optional):',
	'sf_createtemplate_templatefields' => 'Vorlagenfelder',
	'sf_createtemplate_fieldsdesc' => 'Wenn ein Feld einer Vorlage keinen Feldnamen benötigen soll geben sie einfach anstatt eines tatsächlichen Namens die Indexnummer des Feldes als Name ein (z.B. 1, 2, 3 usw.).',
	'sf_createtemplate_fieldname' => 'Feldname:',
	'sf_createtemplate_displaylabel' => 'Anzuzeigende Feldbezeichnung:',
	'sf_createtemplate_semanticproperty' => 'Semantische Eigenschaft:',
	'sf_createtemplate_fieldislist' => 'Dieses Feld kann eine Liste von  Werten enthalten, die durch Kommata getrennt werden.',
	'sf_createtemplate_outputformat' => 'Asugabeformat:',
	'sf_createtemplate_standardformat' => 'Standard',
	'sf_createtemplate_infoboxformat' => 'Rechts plazierte infobox',
	'sf_createtemplate_addfield' => 'Füge Feld hinzu',
	'sf_createtemplate_deletefield' => 'Löschen',
	'forms' => 'Formulare',
	'sf_forms_docu' => 'Die folgenden Formulare existieren in dieser Wiki:',
	'createform' => 'Erstelle ein Formular',
	'sf_createform_nameinput' => 'Formularname (laut Konvention wird ein Formular benannt nach der Hauptvorlage, die es bef�llt):',
	'sf_createform_template' => 'Vorlage:',
	'sf_createform_templatelabelinput' => 'Vorlagebezeichnung (optional):',
	'sf_createform_allowmultiple' => 'Erlaube mehrere (oder null) Instanzen dieser Vorlage in der erstellten Seite',
	'sf_createform_field' => 'Feld:',
	'sf_createform_fieldattr' => 'Dieses Feld definiert die Attribute $1 des Typs $2.',
	'sf_createform_fieldattrunknowntype' => 'Dieses Feld definiert die Attribute $1 eines unspezifizierten Typs.',
	'sf_createform_fieldrel' => 'Dieses Feld definiert den Bezug $1.',
	'sf_createform_formlabel' => 'Formularbezeichnung:',
	'sf_createform_hidden' =>  'Versteckt',
	'sf_createform_restricted' =>  'Gesperrt (nur Sysops können es verändern)',
	'sf_createform_mandatory' =>  'Pflichtfeld',
	'sf_createform_removetemplate' => 'Entferne Vorlage',
	'sf_createform_addtemplate' => 'Füge hinzu Vorlage',
	'sf_createform_beforetemplate' => 'vor Vorlage',
	'sf_createform_atend' => 'Am Ende',
	'sf_createform_add' => 'Füge hinzu',
	'addpage' => 'Füge Seite hinzu',
	'sf_addpage_badform' => 'Fehler: es wurde keine Seite gefunden bei $1',
	'sf_addpage_docu' => 'Geben sie in dieses Textfeld den Namen der Seite ein, die mit Formular \'$1\' bearbeitet werden soll. Wenn bereits eine Seite mit diesem Namen existiert werden sie zu einem Formular weitergeleitet, mit dem sie diese Seite bearbeiten können. Andernfalls werden sie zu einem Formular weitergeleitet, mit dem sie diese Seite erstellen können.',
	'sf_addpage_noform_docu' => 'Geben sie in dieses Textfeld den Namen der Seite ein und wählen sie das Formular, mit dem die Seite bearbeitet werden soll. Wenn bereits eine Seite mit diesem Namen existiert werden sie zu einem Formular weitergeleitet, mit dem sie diese Seite bearbeiten können. Andernfalls werden sie zu einem Formular weitergeleitet, mit dem sie diese Seite erstellen können.',
	'addoreditdata' => 'Hinzufügen oder Bearbeiten',
	'adddata' => 'Füge Daten hinzu',
	'sf_adddata_badurl' => 'Dies ist die Seite zum Hinzuf�gen von Daten. Sie m�ssen den Namen eines Formulars UND die zu bearbeitende Zielseite in der URL angeben. Es sollte aussehen wie \'Special:AddData?form=&lt;Formularname&gt;&target=&lt;Zielseite&gt;\' oder \'Special:AddData/&lt;Formularname&gt;/&lt;Zielseite&gt;\'.',
	'sf_forms_adddata' => 'Füge Daten mit diesem Formular hinzu',
	'editdata' => 'Bearbeite Daten',
	'form_edit' => 'Bearbeite mit Formular',
	'edit_source' => 'Bearbeite Quelltext',
	'sf_editdata_badurl' => 'Dies ist die Seite zum Bearbeiten von Daten. Sie m�ssen den Namen eines Formulars UND die zu bearbeitende Seite in der URL angeben.
	Es sollte aussehen wie \'Special:EditData?form=&lt;Formularname&gt;&target=&lt;Zielseite&gt;\' oder \'Special:EditData/&lt;Formularname&gt;/&lt;Zielseite&gt;\'.',
	'sf_editdata_formwarning' => 'Warnung: Diese Seite <a href="$1">existiert bereits</a>, aber sie nutzt nicht dieses Formular.',
	'sf_editdata_remove' => 'Entfernen',
	'sf_editdata_addanother' => 'Weitere hinzufügen',
	'sf_editdata_freetextlabel' => 'Freitext',

	'sf_blank_error' => 'Darf nicht leer sein!'
);

/* private */ var $sfSpecialProperties = array(
	//always start upper-case
	SF_SP_HAS_DEFAULT_FORM  => 'Hat Standardformular'
);

	/**
	 * Function that returns the namespace identifiers.
	 */
	function getNamespaceArray() {
		return array(
			SF_NS_FORM           => 'Formular',
			SF_NS_FORM_TALK      => 'Formulardiskussion'
		);
	}

	/**
	 * Function that returns all content messages (those that are stored
	 * in some article, and can thus not be translated to individual users).
	 */
	function getContentMsgArray() {
		return $this->sfContentMessages;
	}

	/**
	 * Function that returns all user messages (those that are given only to
	 * the current user, and can thus be given in the individual user language).
	 */

	function getUserMsgArray() {
		return $this->sfUserMessages;
	}

	/**
	 * Function that returns the labels for the special properties.
	 */
	function getSpecialPropertiesArray() {
		return $this->sfSpecialProperties;
	}

}

?>
