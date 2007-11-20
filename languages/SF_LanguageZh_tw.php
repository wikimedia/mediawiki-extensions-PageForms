<?php
/**
 * @author Yaron Koren  翻譯:張致信(Translation: Roc Michael Email:roc.no1@gmail.com)
 */

class SF_LanguageZh_tw {

/* private */ var $sfContentMessages = array(
	'sf_property_isattribute' => '這是$1型態的屬性。',	//(This is an attribute of type $1.)
	'sf_property_isproperty' => '這是$1型態的性質。', //'This is a property of type $1.'	
	'sf_property_allowedvals' => '此屬性的可用型態為：',	//(The allowed values for this attribute are:)
	'sf_property_isrelation' => '這是一項關聯。',	//(This is a relation.)
	'sf_template_docu' => '這是\'$1\'樣板，它須以如下的格式引用：',	//(This is the \'$1\' template. It should be called in the following format:)
	'sf_template_docufooter' => '編輯此頁以查看樣板文字。',	//(Edit the page to see the template text.)
	'sf_form_docu' => '這是\'$1\'表單，編輯此頁以查看原始碼，您能以此表單新增資料[[$2|這裡]]。',	//(This is the \'$1\' form; edit the page to see the source code. You can add data with this form [[$2|here]].)
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

/* private */ var $sfUserMessages = array(
	'createproperty' => '新增語意(semantic)性質',	//(Create a semantic property)
	'sf_createproperty_allowedvalsinput' => '如果您希望此欄位只能輸入特定的值,請將那些值輸入此處，並以半型的逗號(,)分隔，萬一您所指定的值中已包含了半型逗號，請在該逗號前加上一個反斜線(\,)：', //If you want this field to only be allowed to have certain values, enter the list of allowed values, separated by commas (if a value contains a comma, replace it with "\,"):'
	'sf_createproperty_propname' => '名稱：',
	'sf_createproperty_proptype' => '型態:',
	'templates' => '樣板',	//(Templates)
	'sf_templates_docu' => '本wiki系統已含有下列的樣板。',	//(The following templates exist in the wiki.)
	'sf_templates_definescat' => '定義分類(category)：',	//(defines category:)
	'createtemplate' => '新增樣板',	//(Create a template)
	'sf_createtemplate_namelabel' => '樣板名稱：',	//(Template name:)
	'sf_createtemplate_categorylabel' => '以樣板定義分類(選用性的)',	//(Category defined by template (optional):)
	'sf_createtemplate_templatefields' => '樣板欄位',	//(Template fields)
	'sf_createtemplate_fieldsdesc' => '於某個樣板之內新增無須名稱的欄位，僅需賦予索引值(例如： 1,2,3 等等)給這些欄位 而無須指定名稱。',	//(To have the fields of a template not require field names, simply enter the index of that field (e.g. 1, 2, 3, etc.) as the name, instead of an actual name.)
	'sf_createtemplate_fieldname' => '欄位名稱：',	//(Field name:)
	'sf_createtemplate_displaylabel' => '欄位標籤：',	//(Display label:)
	'sf_createtemplate_semanticproperty' => '語意(Semantic)性質',	//(Semantic property:)
  'sf_createtemplate_fieldislist' => '本欄位能夠以某些值來建立清單，那些值須以半型逗號「,」分隔。',	//(This field can hold a list of values, separated by commas)
  'sf_createtemplate_aggregation' => '聚集(Aggregation)',
	'sf_createtemplate_aggregationdesc' => '列出所有使用本樣版的頁面，而那些帶有特定性質指向那頁面的文章，指定著如下性質：譯註：To list, on any page using this template, all of the articles that have a certain property pointing to that page, specify the appropriate property below :',
	'sf_createtemplate_aggregationlabel' => '清單標題', //'Title for list:',
	'sf_createtemplate_outputformat' => '輸出格式：',	//(Output format:)
	'sf_createtemplate_standardformat' => '標準型',	//(Standard)
	'sf_createtemplate_infoboxformat' => '右置型訊息看板',	//(Right-hand-side infobox)
	'sf_createtemplate_addfield' => '新增欄位',	//(Add field)
	'sf_createtemplate_deletefield' => '刪除 ', //(Delete)
	'forms' => '表單',	//(Forms)
	'sf_forms_docu' => '本wiki系統已建有下列的表單。',	//(The following forms exist in the wiki.)
	'createform' => '新增表單',	//(Create a form)
	'sf_createform_nameinput' => '表單名稱(大致上係以其主要的引用樣板的名稱來為其命名)：',	//(Form name (convention is to name the form after the main template it populates):)
	'sf_createform_template' => '樣板：',	//(Template:)
	'sf_createform_templatelabelinput' => '樣板標籤(選用性的)',	//(Template label (optional):)
	'sf_createform_allowmultiple' => '多重選項樣板，此樣板用於在新增頁面上的多重(或無)選項。',	//(Allow for multiple (or zero) instances of this template in the created page)
	'sf_createform_field' => '欄位：',	//(Field:)
	'sf_createform_fieldattr' => '此欄位可定義型態$2上的 $1屬性。',	//(This field defines the attribute $1, of type $2.)
	'sf_createform_fieldattrlist' => '此欄定義一些含有$2型態的$1屬性的元件。', //'This field defines a list of elements that have the attribute $1, of type $2.',
  'sf_createform_fieldattrunknowntype' => '此欄位可定義屬性$1，這些屬性尚未指定的型態(假定為 $2)。',	//(This field defines the attribute $1, of unspecified type (assuming to be $2).)
	'sf_createform_fieldrel' => '此欄位可定義關聯 $1。',	//(This field defines the relation $1.)
	'sf_createform_fieldrellist' => '此欄位定義一些與$1相關的元件', //This field defines a list of elements that have the relation $1.
	'sf_createform_fieldprop' => '此欄定義$2型態的$1性質。', //This field defines the property $1, of type $2.
	'sf_createform_fieldproplist' => '此欄定義一些採$2型態且帶有$1質性的元件',  //This field defines a list of elements that have the property $1, of type $2.
	'sf_createform_fieldpropunknowntype' => '此欄定義 $1性質，卻未指定型態。譯注原文為：This field defines the property $1, of unspecified type.',  
	'sf_createform_inputtype' =>  '輸入型態：', //'Input type:',
	'sf_createform_inputtypedefault' =>  '(內定值)',  //'(default)',
  'sf_createform_formlabel' => '表單標籤。',	//(Form label:)
	'sf_createform_hidden' =>  '隱藏',	//(Hidden)
	'sf_createform_restricted' =>  '受限制的頁面(只有管理員可編輯)',	//(Restricted (only sysop users can modify it))
	'sf_createform_mandatory' =>  '強制性的',	//(Mandatory)
	'sf_createform_removetemplate' => '刪除樣板',	//(Remove template)
	'sf_createform_addtemplate' => '新增樣板：',	//(Add template:)
	'sf_createform_beforetemplate' => '在樣板之前：',	//(Before template:)
	'sf_createform_atend' => '在末端',	//(At end)
	'sf_createform_add' => '新增',	//(Add)
	'addpage' => '新增頁面',	//(Add page)
	'sf_addpage_badform' => '錯誤！在$1上並沒有找到表單頁面。',	//(Error: no form page was found at $1)
	'sf_addpage_docu' => '輸入頁面名稱以便以\'$1\'表單編輯。如果此頁已存在的話，您便能以表單編輯該頁，否則，您便能以表單新增此頁面。',	//(Enter the name of the page here, to be edited with the form \'$1\'. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.)
	'sf_addpage_noform_docu' => '請於此處輸入頁面名稱，再選取表單對其進行編輯，如果此頁已存在的話，您便能以表單編輯該頁，否則，您便能以表單新增此頁面。',	//(Enter the name of the page here, and select the form to edit it with. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.)
	'addoreditdata' => '新增或編輯',	//(Add or edit)
	'adddata' => '新增資料',	//(Add data)
	'sf_adddata_title' => '增加 $1： $2',
  'sf_adddata_badurl' => '本頁為新增資料之用，您必須在URL裡同時指定表單及目標頁面，它看起來應該像是\'Special:AddData?form=&lt;表單名稱&gt;&target=&lt;目標頁面&gt;\' 或是 \'Special:AddData/&lt;表單名稱&gt;/&lt;目標頁面&gt;\'。',	//(This is the page for adding data. You must specify both a form name and a target page in the URL; it should look like \'Special:AddData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:AddData/&lt;form name&gt;/&lt;target page&gt;\'.)
	'sf_forms_adddata' => '以表單新增資料',	//(Add data with this form)
	'editdata' => '編輯資料',	//(Edit data)
	'form_edit' => '以表單進行編輯',	//(Edit with form)
	'edit_source' => '編輯來源',
	'sf_editdata_title' => '編輯 $1: $2',
  'sf_editdata_badurl' => '本頁為編輯資料之用，您必須在URL裡同時指定表單及目標頁面，它看起來應該像是\'Special:EditData?form=&lt;表單名稱;&target=&lt;目標頁面&gt;\' 或是  \'Special:EditData/&lt;表單名稱&gt;/&lt;目標頁面&gt;\'.',	//(This is the page for editing data. You must specify both a form name and a target page in the URL; it should look like \'Special:EditData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:EditData/&lt;form name&gt;/&lt;target page&gt;\'.)
	'sf_editdata_formwarning' => '警告：<a href="$1">此頁</a>已經存在，只是尚未引用本表單。',	 //'Warning: This page <a href="$1">already exists</a>, but it does not use this form.',
	'sf_editdata_remove' => '刪除',	//(Remove)
	'sf_editdata_addanother' => '新增其他',	//(Add another)
	'sf_editdata_freetextlabel' => '隨意文字區(Free text)',	//(Free text)
	
	'sf_blank_error' => '不得為空白'	//(cannot be blank)
);

/* private */ var $sfSpecialProperties = array(
	//always start upper-case
	SF_SP_HAS_DEFAULT_FORM  => '設有表單',	//(Has default form)
);

	/**
	 * Function that returns the namespace identifiers.
	 */
	function getNamespaceArray() {
		return array(
			SF_NS_FORM           => '表單',			//	(Form)
			SF_NS_FORM_TALK      => '表單_talk'		//	(Form_talk)

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
