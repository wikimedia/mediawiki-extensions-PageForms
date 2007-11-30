<?php
/**
 * @author Yaron Koren
 */

class SF_LanguageHe extends SF_Language {


/* private */ var $m_ContentMessages = array(
        'sf_template_docu' => 'זאת התבנית $1. צריך לקרוא לה בפורמט הזה:',
        'sf_template_docufooter' => 'ערוך את הדף כדי לראות את טקסט התבנית.',
	// month names are already defined in MediaWiki, but unfortunately
	// there they're defined as user messages, and here they're
	// content messages
	'sf_january' => 'ינואר',
	'sf_february' => 'פברואר',
	'sf_march' => 'מרץ',
	'sf_april' => 'אפריל',
	'sf_may' => 'מאי',
	'sf_june' => 'יוני',
	'sf_july' => 'יולי',
	'sf_august' => 'אוגוסט',
	'sf_september' => 'ספטמבר',
	'sf_october' => 'אוקטובר',
	'sf_november' => 'נובמבר',
	'sf_december' => 'דצמבר'

);

/* private */ var $m_UserMessages = array(
        'templates' => 'תבניות',
        'sf_templates_docu' => 'התבניות הבאות קיימות בוויקי הזה.',
	'sf_templates_definescat' => 'מגדיר את הקטגוריה:',
        'createtemplate' => 'צור תבנית',
        'sf_createtemplate_namelabel' => 'שם התבנית:',
        'sf_createtemplate_categorylabel' => 'קטגוריה מוגדרת על ידי תבנית ():',
        'sf_createtemplate_templatefields' => 'שדות התבנית',
        'sf_createtemplate_fieldsdesc' => 'כדי ששדות התבנית לא יצטרכו שמות, פשוט הכנס את האינדקס של השדה (1, 2, 3...) במקום שם אמיתי.',
        'sf_createtemplate_fieldname' => 'שם השדה:',
        'sf_createtemplate_displaylabel' => 'תוית תצוגה:',
        'sf_createtemplate_semanticproperty' => 'זה השדה הסמנטי:',
        'sf_createtemplate_addfield' => 'הוסף שדה',
        'sf_createtemplate_deletefield' => 'מחק',
        'forms' => 'טפסים',
        'sf_forms_docu' => 'הטפסים הבאים קיימים בוויקי הזה.',
        'createform' => 'צור טופס',
        'sf_createform_nameinput' => 'שם הטופס (מקובל לתת לטופס שם בהתאם לשם התבנית העיקרית שהוא מגדיר)',
        'sf_createform_template' => 'תבנית:',
        'sf_createform_templatelabelinput' => 'תוית לתבנית (אופציונלי):',
        'sf_createform_allowmultiple' => ' הרשה כפילויות (או אפס) מהתבנית הזאת בדף המיוצר',
        'sf_createform_field' => 'שדה:',
        'sf_createform_fieldattr' => 'השדה הזה מגדיר את התכונה $1, מטיפוס $2.',
        'sf_createform_fieldattrunknowntype' => 'השדה הזה מגדיר את התכונה $1, לא מוגדר.',
        'sf_createform_fieldrel' => 'השדה הזה מגדיר את היחס $1.',
	'sf_createform_formlabel' => 'תוית בטופס:',
        'sf_createform_hidden' =>  'מוסתר',
        'sf_createform_mandatory' =>  'הכרחי',
	'sf_createform_removetemplate' => 'הוריד תבנית',
        'sf_createform_addtemplate' => 'הוסף תבנית:',
	'sf_createform_beforetemplate' => 'לפני התבנית:',
        'sf_createform_atend' => 'בסוף',
        'sf_createform_add' => 'הוסף',
        'adddata' => 'הוסף מידע',
        'sf_adddata_badurl' => 'זה הדף עבור הוספת מידע. צריך לפרט שם טופס ב-URL; ה-URL צריך להראות כמו \'Special:AddData?form=&lt;form name&gt;\', \'Special:AddData/&lt;form name&gt;\'.',
        'sf_forms_adddata' => 'הוסף מידע עם הטופס הזה',
	'editdata' => 'עורך מידע',
	'form_edit' => 'עריכה עם טופס',
        'sf_editdata_badurl' => 'זה הדף עבור עריכת מידע. צריך לפרט גם שם טופס וגם דף מטרה ב-URL; ה-URL צריך להראות כמו \'Special:EditData?form=&lt;form name&gt;&amp;target=&lt;target page&gt;\', \'Special:EditData/&lt;form name&gt;/&lt;target page&gt;\'.',
	'sf_editdata_remove' => 'הוריד',
	'sf_editdata_addanother' => 'הוסיף עוד',
        'sf_editdata_freetextlabel' => 'טקסט חופשי',

        'sf_blank_error' => 'לא יכול להיות ריק'
);

/* private */ var $m_SpecialProperties = array(
	//always start upper-case
	SF_SP_HAS_DEFAULT_FORM  => 'משתמש בטופס'
);

/* private */ var $m_SpecialPropertyAliases = array(
	// support English aliases for special properties
	'Has default form'	=> SF_SP_HAS_DEFAULT_FORM,
	'Has alternate form'	=> SF_SP_HAS_ALTERNATE_FORM
);

var $m_Namespaces = array(
	SF_NS_FORM           => 'Form',
	SF_NS_FORM_TALK      => 'Form_talk'
);

var $m_NamespaceAliases = array(
	// support English aliases for namespaces
	'Form'		=> SF_NS_FORM,
	'Form_talk'	=> SF_NS_FORM_TALK
);

}

?>
