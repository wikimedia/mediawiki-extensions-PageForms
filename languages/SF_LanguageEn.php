<?php
/**
 * @author Yaron Koren
 */

class SF_LanguageEn {

/* private */ var $sfContentMessages = array(
	'sf_createproperty_isattribute' => 'This is an attribute of type $1.',
	'sf_createproperty_allowedvals' => 'The allowed values for this attribute are:',
	'sf_createproperty_isrelation' => 'This is a relation.',
	'sf_template_docu' => 'This is the \'$1\' template. It should be called in the following format:',
	'sf_template_docufooter' => 'Edit the page to see the template text.',
	'sf_form_docu' => 'This is the \'$1\' form; edit the page to see the source code. You can add data with this form [[$2|here]].',
	'sf_form_relation' => 'Has default form',
	// month names are already defined in MediaWiki, but unfortunately
	// there they're defined as user messages, and here they're
	// content messages
	'sf_january' => 'January',
	'sf_february' => 'February',
	'sf_march' => 'March',
	'sf_april' => 'April',
	'sf_may' => 'May',
	'sf_june' => 'June',
	'sf_july' => 'July',
	'sf_august' => 'August',
	'sf_september' => 'September',
	'sf_october' => 'October',
	'sf_november' => 'November',
	'sf_december' => 'December'

);

/* private */ var $sfUserMessages = array(
	'createproperty' => 'Create a semantic property',
	'templates' => 'Templates',
	'sf_templates_docu' => 'The following templates exist in the wiki.',
	'sf_templates_definescat' => 'defines category:',
	'createtemplate' => 'Create a template',
	'sf_createtemplate_namelabel' => 'Template name:',
	'sf_createtemplate_categorylabel' => 'Category defined by template (optional):',
	'sf_createtemplate_templatefields' => 'Template fields',
	'sf_createtemplate_fieldsdesc' => 'To have the fields of a template not require field names, simply enter the index of that field (e.g. 1, 2, 3, etc.) as the name, instead of an actual name.',
	'sf_createtemplate_fieldname' => 'Field name:',
	'sf_createtemplate_displaylabel' => 'Display label:',
	'sf_createtemplate_semanticproperty' => 'Semantic property:',
	'sf_createtemplate_outputformat' => 'Output format:',
	'sf_createtemplate_standardformat' => 'Standard',
	'sf_createtemplate_infoboxformat' => 'Right-hand-side infobox',
	'sf_createtemplate_addfield' => 'Add field',
	'sf_createtemplate_deletefield' => 'Delete',
	'forms' => 'Forms',
	'sf_forms_docu' => 'The following forms exist in the wiki.',
	'createform' => 'Create a form',
	'sf_createform_nameinput' => 'Form name (convention is to name the form after the main template it populates):',
	'sf_createform_template' => 'Template:',
	'sf_createform_templatelabelinput' => 'Template label (optional):',
	'sf_createform_allowmultiple' => 'Allow for multiple (or zero) instances of this template in the created page',
	'sf_createform_field' => 'Field:',
	'sf_createform_fieldattr' => 'This field defines the attribute $1, of type $2.',
	'sf_createform_fieldattrunknowntype' => 'This field defines the attribute $1, of unspecified type (assuming to be $2).',
	'sf_createform_fieldrel' => 'This field defines the relation $1.',
	'sf_createform_formlabel' => 'Form label:',
	'sf_createform_hidden' =>  'Hidden',
	'sf_createform_restricted' =>  'Restricted (only sysop users can modify it)',
	'sf_createform_mandatory' =>  'Mandatory',
	'sf_createform_removetemplate' => 'Remove template',
	'sf_createform_addtemplate' => 'Add template:',
	'sf_createform_beforetemplate' => 'Before template:',
	'sf_createform_atend' => 'At end',
	'sf_createform_add' => 'Add',
	'addpage' => 'Add page',
	'sf_addpage_noform' => 'Error: no form page was found at $1',
	'sf_addpage_badurl' => 'This is the page for adding a page. You must specify a form name in the URL; it should look like \'Special:AddPage?form=&lt;form name&gt;\' or  \'Special:AddPage/&lt;form name&gt;\'.',
	'sf_addpage_docu' => 'Enter the name of the page here, to be edited with the form \'$1\'. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.',
	'addoreditdata' => 'Add or edit',
	'adddata' => 'Add data',
	'sf_adddata_badurl' => 'This is the page for adding data. You must specify both a form name and a target page in the URL; it should look like \'Special:AddData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:AddData/&lt;form name&gt;/&lt;target page&gt;\'.',
	'sf_forms_adddata' => 'Add data with this form',
	'editdata' => 'Edit data',
	'form_edit' => 'Edit with form',
	'sf_editdata_badurl' => 'This is the page for editing data. You must specify both a form name and a target page in the URL; it should look like \'Special:EditData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:EditData/&lt;form name&gt;/&lt;target page&gt;\'.',
	'sf_editdata_remove' => 'Remove',
	'sf_editdata_addanother' => 'Add another',
	'sf_editdata_freetextlabel' => 'Free text',

	'sf_blank_error' => 'cannot be blank'
);

	/**
	 * Function that returns the namespace identifiers.
	 */
	function getNamespaceArray() {
		return array(
			SF_NS_FORM           => 'Form',
			SF_NS_FORM_TALK      => 'Form_talk'
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

}

?>
