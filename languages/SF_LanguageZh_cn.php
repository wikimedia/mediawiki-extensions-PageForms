<?php
/**
 * @author： Yaron Koren  翻译:张致信 本档系以电子字典译自繁体版，请自行修订(Translation: Roc Michael Email:roc.no1@gmail.com. This file is translated from Tradition Chinese by using electronic dictionary. Please correct the file by yourself.)  
 */
 
class SF_LanguageZh_cn extends SF_Language {

/* private */ var $m_ContentMessages = array(
	'sf_property_isattribute' => '这是$1型态的属性。',	//(This is an attribute of type $1.)
	'sf_property_isproperty' => '这是$1型态的性质。', //'This is a property of type $1.'	
	'sf_property_allowedvals' => '此属性的可用型态为：',	//(The allowed values for this attribute are:)
	'sf_property_isrelation' => '这是一项关联。',	//(This is a relation.)
	'sf_template_docu' => '这是\'$1\'样板，它须以如下的格式引用：',	//(This is the \'$1\' template. It should be called in the following format:)
	'sf_template_docufooter' => '编辑此页以查看样板文字。',	//(Edit the page to see the template text.)
	'sf_form_docu' => '这是\'$1\'表单，编辑此页以查看原始码，您能以此表单新增资料[[$2|这里]]。',	//(This is the \'$1\' form; edit the page to see the source code. You can add data with this form [[$2|here]].)
	'sf_category_hasdefaultform' => '此项分类(category)使用$1表单。', //'This category uses the form $1.',
	'sf_category_desc' => '这是$1分类(category)',
	// month names are already defined in MediaWiki, but unfortunately
	// there they're defined as user messages, and here they're
	// content messages
	'sf_january' => '一月',		//	(January)
	'sf_february' => '二月',		//	(February)
	'sf_march' => '三月',		//	(March)
	'sf_april' => '四月',		//	(April)
	'sf_may' => '五月',		//	(May)
	'sf_june' => '六月',		//	(June)
	'sf_july' => '七月',		//	(July)
	'sf_august' => '八月',		//	(August)
	'sf_september' => '九月',		//	(September)
	'sf_october' => '十月',		//	(October)
	'sf_november' => '十一月',		//	(November)
	'sf_december' => '十二月',		//	(December)
	'sf_blank_namespace' => '主',   //'Main'	
);

/* private */ var $m_UserMessages = array(
	'createproperty' => '新增语意(semantic)性质',	//(Create a property)
	'sf_createproperty_allowedvalsinput' => '如果您希望此栏位只能输入特定的值,请将那些值输入此处，并以半型的逗号(,)分隔，万一您所指定的值中已包含了半型逗号，请在该逗号前加上一个反斜线(\,)：', //If you want this field to only be allowed to have certain values, enter the list of allowed values, separated by commas (if a value contains a comma, replace it with "\,"):'
	'sf_createproperty_propname' => '名称：',
	'sf_createproperty_proptype' => '型态:',
	'templates' => '样板',	//(Templates)
	'sf_templates_docu' => '本wiki系统已含有下列的样板。',	//(The following templates exist in the wiki.)
	'sf_templates_definescat' => '定义分类(Category)：',	//(defines category:)
	'createtemplate' => '新增样板',	//(Create a template)
	'sf_createtemplate_namelabel' => '样板名称：',	//(Template name:)
	'sf_createtemplate_categorylabel' => '以样板定义分类(选用性的)',	//(Category defined by template (optional):)
	'sf_createtemplate_templatefields' => '样板栏位',	//(Template fields)
	'sf_createtemplate_fieldsdesc' => '于某个样板之内新增无须名称的栏位，仅需赋予索引值(例如： 1,2,3 等等)给这些栏位 而无须指定名称。',	//(To have the fields of a template not require field names, simply enter the index of that field (e.g. 1, 2, 3, etc.) as the name, instead of an actual name.)
	'sf_createtemplate_fieldname' => '栏位名称：',	//(Field name:)
	'sf_createtemplate_displaylabel' => '栏位标签：',	//(Display label:)
	'sf_createtemplate_semanticproperty' => '语意(Semantic)性质',	//(Semantic property:)
  'sf_createtemplate_fieldislist' => '本栏位能够以某些值来建立列表，那些值须以半型逗号「,」分隔。',	//(This field can hold a list of values, separated by commas)
  'sf_createtemplate_aggregation' => '聚集(Aggregation)',
	'sf_createtemplate_aggregationdesc' => '列出所有使用本样版的页面，而那些带有特定性质指向那页面的文章，指定着如下性质：译注：To list, on any page using this template, all of the articles that have a certain property pointing to that page, specify the appropriate property below :',
	'sf_createtemplate_aggregationlabel' => '列表标题', //'Title for list:',
	'sf_createtemplate_outputformat' => '输出格式：',	//(Output format:)
	'sf_createtemplate_standardformat' => '标准型',	//(Standard)
	'sf_createtemplate_infoboxformat' => '右置型信息招牌，广告牌',	//(Right-hand-side infobox)
	'sf_createtemplate_addfield' => '新增栏位',	//(Add field)
	'sf_createtemplate_deletefield' => '删除 ', //(Delete)
	'sf_createtemplate_addtemplatebeforesave' => '在您存贮它之前，您必须为这个表单加入至少一项样板。', 	
	'forms' => '表单',	//(Forms)
	'sf_forms_docu' => '本wiki系统已建有下列的表单。',	//(The following forms exist in the wiki.)
	'createform' => '新增表单',	//(Create a form)
	'sf_createform_nameinput' => '表单名称(大致上系以其主要的引用样板的名称来为其命名)：',	//(Form name (convention is to name the form after the main template it populates):)
	'sf_createform_template' => '样板：',	//(Template:)
	'sf_createform_templatelabelinput' => '样板标签(选用性的)',	//(Template label (optional):)
	'sf_createform_allowmultiple' => '多重选项样板，此样板用于在新增页面上的多重(或无)选项。',	//(Allow for multiple (or zero) instances of this template in the created page)
	'sf_createform_field' => '栏位：',	//(Field:)
	'sf_createform_fieldattr' => '此栏位可定义型态$2上的 $1属性。',	//(This field defines the attribute $1, of type $2.)
	'sf_createform_fieldattrlist' => '此栏定义一些含有$2型态的$1属性的元件。', //'This field defines a list of elements that have the attribute $1, of type $2.',
  'sf_createform_fieldattrunknowntype' => '此栏位可定义属性$1，这些属性尚未指定的型态(假定为 $2)。',	//(This field defines the attribute $1, of unspecified type (assuming to be $2).)
	'sf_createform_fieldrel' => '此栏位可定义关联 $1。',	//(This field defines the relation $1.)
	'sf_createform_fieldrellist' => '此栏位定义一些与$1相关的元件', //This field defines a list of elements that have the relation $1.
	'sf_createform_fieldprop' => '此栏定义$2型态的$1性质。', //This field defines the property $1, of type $2.
	'sf_createform_fieldproplist' => '此栏定义一些采$2型态且带有$1质性的元件',  //This field defines a list of elements that have the property $1, of type $2.
	'sf_createform_fieldpropunknowntype' => '此栏定义 $1性质，却未指定型态。译注原文为：This field defines the property $1, of unspecified type.',  
	'sf_createform_inputtype' =>  '输入型态：', //'Input type:',
	'sf_createform_inputtypedefault' =>  '(内定值)',  //'(default)',
  'sf_createform_formlabel' => '表单标签。',	//(Form label:)
	'sf_createform_hidden' =>  '隐藏',	//(Hidden)
	'sf_createform_restricted' =>  '受限制的页面(只有管理员可编辑)',	//(Restricted (only sysop users can modify it))
	'sf_createform_mandatory' =>  '强制性的',	//(Mandatory)
	'sf_createform_removetemplate' => '删除样板',	//(Remove template)
	'sf_createform_addtemplate' => '新增样板：',	//(Add template:)
	'sf_createform_beforetemplate' => '在样板之前：',	//(Before template:)
	'sf_createform_atend' => '在末端',	//(At end)
	'sf_createform_add' => '新增',	//(Add)
	'sf_createform_choosefield' => '选取欲新增的栏位', //'Choose a field to add'
	'createcategory' => '新增分类(Category)',
	'sf_createcategory_name' => '名称：', //(Name:)
	'sf_createcategory_defaultform' => '预设的表单',
  'sf_createcategory_makesubcategory' => '令此项分类(Category)成为某一分类项下的子分类(选择性使用)',	//'Make this a subcategory of another category (optional):'
	'addpage' => '新增页面',	//(Add page)
	'sf_addpage_badform' => '错误！在$1上并没有找到表单页面。',	//(Error: no form page was found at $1)
	'sf_addpage_docu' => '输入页面名称以便以\'$1\'表单编辑。如果此页已存在的话，您便能以表单编辑该页，否则，您便能以表单新增此页面。',	//(Enter the name of the page here, to be edited with the form \'$1\'. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.)
	'sf_addpage_noform_docu' => '请于此处输入页面名称，再选取表单对其进行编辑，如果此页已存在的话，您便能以表单编辑该页，否则，您便能以表单新增此页面。',	//(Enter the name of the page here, and select the form to edit it with. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.)
	'addoreditdata' => '新增或编辑',	//(Add or edit)
	'adddata' => '新增资料',	//(Add data)
	'sf_adddata_title' => '增加 $1： $2',
  'sf_adddata_badurl' => '本页为新增资料之用，您必须在URL里同时指定表单及目标页面，它看起来应该像是\'Special:AddData?form=&lt;表单名称&gt;&target=&lt;目标页面&gt;\' 或是 \'Special:AddData/&lt;表单名称&gt;/&lt;目标页面&gt;\'。',	//(This is the page for adding data. You must specify both a form name and a target page in the URL; it should look like \'Special:AddData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:AddData/&lt;form name&gt;/&lt;target page&gt;\'.)
  'sf_adddata_altforms' => '您可选用下列其中一个的表单来新增此页',  //(You can instead add this page with one of the following forms:),
  'sf_adddata_altformsonly' => '请从下列的表单中选出其中一个，并以该表单编辑此页：', // (Please select from one of the following forms to add this page:)
	'sf_forms_adddata' => '以表单新增资料',	//(Add data with this form)
	'editdata' => '编辑资料',	//(Edit data)
	'form_edit' => '以表单进行编辑',	//(Edit with form)
	'edit_source' => '编辑来源',
	'sf_editdata_title' => '编辑 $1: $2',
  'sf_editdata_badurl' => '本页为编辑资料之用，您必须在URL里同时指定表单及目标页面，它看起来应该像是\'Special:EditData?form=&lt;表单名称;&target=&lt;目标页面&gt;\' 或是  \'Special:EditData/&lt;表单名称&gt;/&lt;目标页面&gt;\'.',	//(This is the page for editing data. You must specify both a form name and a target page in the URL; it should look like \'Special:EditData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:EditData/&lt;form name&gt;/&lt;target page&gt;\'.)
	'sf_editdata_formwarning' => '警告：<a href="$1">此页</a>已经存在，只是尚未引用本表单。',	 //'Warning: This page <a href="$1">already exists</a>, but it does not use this form.',
	'sf_editdata_remove' => '删除',	//(Remove)
	'sf_editdata_addanother' => '新增其他',	//(Add another)
	'sf_editdata_freetextlabel' => '随意文字区(Free text)',	//(Free text)
	
	'sf_blank_error' => '不得为空白',
  'sf_bad_url_error' => '须有正确的URL格式（即以 \'http\'起头）',
  'sf_bad_email_error' => '必须是有效的电子邮件(Email)格式', 
  'sf_bad_number_error' => '必须是有效的数字',
  'sf_bad_integer_error' => '必须是有效的整数',
  'sf_bad_date_error' => '必须是有效的日期' 	
);

/* private */ var $m_SpecialProperties = array(
	//always start upper-case
  SF_SP_HAS_DEFAULT_FORM  => '预设表单',	//(Has default form) 
	SF_SP_HAS_ALTERNATE_FORM  => '代用表单'  //(Has alternate form)
);

/* private */ var $m_SpecialPropertyAliases = array(
	'设有表单'	=> SF_SP_HAS_DEFAULT_FORM,	//(Has default form) //Adding the item "Has alternate form", this item will not be suitable for translating into “设有表单＂. It has changed to use “预设表单＂. 
	// support English aliases for special properties
	'Has default form'	=> SF_SP_HAS_DEFAULT_FORM,
	'Has alternate form'	=> SF_SP_HAS_ALTERNATE_FORM
);

var $m_Namespaces = array(
	SF_NS_FORM           => '表单',		//	(Form)
	SF_NS_FORM_TALK      => '表单讨论'	//	(Form_talk)  SF 0.7.6前原译为'表单_talk'
);

var $m_NamespaceAliases = array(
	// support English aliases for namespaces
	'Form'		=> SF_NS_FORM,
	'Form_talk'	=> SF_NS_FORM_TALK
);

}

?>


