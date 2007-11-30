<?php
/**
 * @author Yaron Koren
 */

class SF_LanguageEn extends SF_Language {

/* private */ var $m_ContentMessages = array(
	'sf_property_isattribute' => 'This is an attribute of type $1.',
	'sf_property_isproperty' => 'This is a property of type $1.',
	'sf_property_allowedvals' => 'The allowed values for this attribute or property are:',
	'sf_property_isrelation' => 'This is a relation.',
	'sf_template_docu' => 'This is the \'$1\' template. It should be called in the following format:',
	'sf_template_docufooter' => 'Edit the page to see the template text.',
	'sf_form_docu' => 'This is the \'$1\' form. To add a page with this form, enter the page name below; if a page with that name already exists, you will be sent to a form to edit that page.',
	'sf_category_hasdefaultform' => 'This category uses the form $1.',
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
	'sf_december' => 'December',
	'sf_blank_namespace' => 'Main'
);

/* private */ var $m_UserMessages = array(
	'createproperty' => 'Create a semantic property',
	'sf_createproperty_allowedvalsinput' => 'If you want this field to only be allowed to have certain values, enter the list of allowed values, separated by commas (if a value contains a comma, replace it with "\,"):',
	'sf_createproperty_propname' => 'Name:',
	'sf_createproperty_proptype' => 'Type:',
	'templates' => 'Templates',
	'sf_templates_docu' => 'The following templates exist in the wiki.',
	'sf_templates_definescat' => 'defines category:',
	'createtemplate' => 'Create a template',
	'sf_createtemplate_namelabel' => 'Template name:',
	'sf_createtemplate_categorylabel' => 'Category defined by template (optional):',
	'sf_createtemplate_templatefields' => 'Template fields',
	'sf_createtemplate_fieldsdesc' => 'To have the fields in this template no longer require field names, simply enter the index of each field (e.g. 1, 2, 3, etc.) as the name, instead of an actual name.',
	'sf_createtemplate_fieldname' => 'Field name:',
	'sf_createtemplate_displaylabel' => 'Display label:',
	'sf_createtemplate_semanticproperty' => 'Semantic property:',
	'sf_createtemplate_fieldislist' => 'This field can hold a list of values, separated by commas',
	'sf_createtemplate_aggregation' => 'Aggregation',
	'sf_createtemplate_aggregationdesc' => 'To list, on any page using this template, all of the articles that have a certain property pointing to that page, specify the appropriate property below:',
	'sf_createtemplate_aggregationlabel' => 'Title for list:',
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
	'sf_createform_fieldattrlist' => 'This field defines a list of elements that have the attribute $1, of type $2.',
	'sf_createform_fieldattrunknowntype' => 'This field defines the attribute $1, of unspecified type.',
	'sf_createform_fieldrel' => 'This field defines the relation $1.',
	'sf_createform_fieldrellist' => 'This field defines a list of elements that have the relation $1.',
	'sf_createform_fieldprop' => 'This field defines the property $1, of type $2.',
	'sf_createform_fieldproplist' => 'This field defines a list of elements that have the property $1, of type $2.',
	'sf_createform_fieldpropunknowntype' => 'This field defines the property $1, of unspecified type.',
	'sf_createform_inputtype' =>  'Input type:',
	'sf_createform_inputtypedefault' =>  '(default)',
	'sf_createform_formlabel' => 'Form label:',
	'sf_createform_hidden' =>  'Hidden',
	'sf_createform_restricted' =>  'Restricted (only sysop users can modify it)',
	'sf_createform_mandatory' =>  'Mandatory',
	'sf_createform_removetemplate' => 'Remove template',
	'sf_createform_addtemplate' => 'Add template:',
	'sf_createform_beforetemplate' => 'Before template:',
	'sf_createform_atend' => 'At end',
	'sf_createform_add' => 'Add',
	'sf_createform_choosefield' => 'Choose a field to add',
	'createcategory' => 'Create a category',
	'sf_createcategory_name' => 'Name:',
	'sf_createcategory_defaultform' => 'Default form:',
	'sf_createcategory_makesubcategory' => 'Make this a subcategory of another category (optional):',
	'addpage' => 'Add page',
	'sf_addpage_badform' => 'Error: no form page was found at $1',
	'sf_addpage_docu' => 'Enter the name of the page here, to be edited with the form \'$1\'. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.',
	'sf_addpage_noform_docu' => 'Enter the name of the page here, and select the form to edit it with. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.',
	'addoreditdata' => 'Add or edit',
	'adddata' => 'Add data',
	'sf_adddata_title' => 'Add $1: $2',
	'sf_adddata_badurl' => 'This is the page for adding data. You must specify both a form name and a target page in the URL; it should look like \'Special:AddData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:AddData/&lt;form name&gt;/&lt;target page&gt;\'.',
	'sf_adddata_altforms' => 'You can instead add this page with one of the following forms:',
	'sf_adddata_altformsonly' => 'Please select from one of the following forms to add this page:',
	'sf_forms_adddata' => 'Add data with this form',
	'editdata' => 'Edit data',
	'form_edit' => 'Edit with form',
	'edit_source' => 'Edit source',
	'sf_editdata_title' => 'Edit $1: $2',
	'sf_editdata_badurl' => 'This is the page for editing data. You must specify both a form name and a target page in the URL; it should look like \'Special:EditData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:EditData/&lt;form name&gt;/&lt;target page&gt;\'.',
	'sf_editdata_formwarning' => 'Warning: This page <a href="$1">already exists</a>, but it does not use this form.',
	'sf_editdata_remove' => 'Remove',
	'sf_editdata_addanother' => 'Add another',
	'sf_editdata_freetextlabel' => 'Free text',

	'sf_blank_error' => 'cannot be blank'
);

/* private */ var $m_SpecialProperties = array(
        //always start upper-case
        SF_SP_HAS_DEFAULT_FORM  => 'Has default form',
        SF_SP_HAS_ALTERNATE_FORM  => 'Has alternate form'
);

var $m_Namespaces = array(
	SF_NS_FORM           => 'Form',
	SF_NS_FORM_TALK      => 'Form_talk'
);

}

?>
