<?php
/**
 * @author Yaron Koren (Translation:Ghassem Tofighi Email:[MyFamily]@gmail.com, HomePage:http://ght.ir)
 */

class SF_LanguageFa extends SF_Language {

/* private */ var $m_ContentMessages = array(
	'sf_property_isattribute' => 'این یک صفت از نوع $1 است.', //This is an attribute of type $1.
	'sf_property_isproperty' => 'این یک ویژگی از نوع $1 است.', //This is a property of type $1.
	'sf_property_allowedvals' => 'مقادیر مجاز برای این ویژگی یا صفت این‌ها هستند:',//The allowed values for this attribute or property are:
	'sf_property_isrelation' => 'این یک رابطه است.',//This is a relation.
	'sf_template_docu' => 'این الگوی \'$1\' است. باید با این قالب فراخوانی شود:',//This is the \'$1\' template. It should be called in the following format:
	'sf_template_docufooter' => 'برای مشاهده متن الگو صفحه را ویرایش کنید.',//Edit the page to see the template text.
	'sf_form_docu' => 'این فرم \'$1\' است. برای افزودن یک صفحه به‌وسیله این فرم،نام صفحه را وارد کنید، اگر صفحه‌ای با این نام وجود داشته باشد، به فرم ویرایش صفحه هدایت می‌شوید.',//This is the \'$1\' form. To add a page with this form, enter the page name below; if a page with that name already exists, you will be sent to a form to edit that page.
	'sf_category_hasdefaultform' => 'این رده از فرم $1 استفاده می‌کند.',//This category uses the form $1.
	'sf_category_desc' => 'این رده $1 است.',//This is the $1 category.
	// month names are already defined in MediaWiki, but unfortunately
	// there they're defined as user messages, and here they're
	// content messages
	'sf_january' => 'ژانویه',//January
	'sf_february' => 'فوریه',//February
	'sf_march' => 'مارس',//March
	'sf_april' => 'آوریل',//April
	'sf_may' => 'می',//May
	'sf_june' => 'ژوئن',//June
	'sf_july' => 'جولای',//July
	'sf_august' => 'آگوست',//August
	'sf_september' => 'سپتامبر',//September
	'sf_october' => 'اکتبر',//October
	'sf_november' => 'نوامبر',//November
	'sf_december' => 'دسامبر',//December
	'sf_blank_namespace' => 'اصلی'//Main
);

/* private */ var $m_UserMessages = array(
	'createproperty' => 'ویژگی بسازید',//Create a property
	'sf_createproperty_allowedvalsinput' => 'اگر می‌خواهید این ورودی تنها مقادیر مشخصی داشته باشد،سیاهه مقادیر را وارد کنید و آنها را با کاما جدا سازید (اگر در این مقادیر از کاما استفاده می‌کنید، آن‌را با "\،" جایگزین نمایید):',//If you want this field to only be allowed to have certain values, enter the list of allowed values, separated by commas (if a value contains a comma, replace it with "\,"):
	'sf_createproperty_propname' => 'نام:',//Name:
	'sf_createproperty_proptype' => 'نوع:',//Type:
	'templates' => 'الگوها',//Templates
	'sf_templates_docu' => 'الگوهای زیر در این ویکی وجود دارند.',//The following templates exist in the wiki.
	'sf_templates_definescat' => 'تعیین رده:',//defines category:
	'createtemplate' => 'الگو بسازید',//Create a template
	'sf_createtemplate_namelabel' => 'نام الگو:',//Template name:
	'sf_createtemplate_categorylabel' => 'تعیین رده به‌وسیله الگو (دلخواه):',//Category defined by template (optional):
	'sf_createtemplate_templatefields' => 'ورودی‌های الگو',//Template fields
	'sf_createtemplate_fieldsdesc' => 'برای داشتن ورودی‌های بدون نام در الگوها،کافیست شماره ورودی را به جای نام واقعی ورودی وارد کنید (مثلا ۳،۲،۱ و ...).',//To have the fields of a template not require field names, simply enter the index of that field (e.g. 1, 2, 3, etc.) as the name, instead of an actual name.
	'sf_createtemplate_fieldname' => 'نام ورودی:',//Field name:
	'sf_createtemplate_displaylabel' => 'نمایش برچسب:',//Display label:
	'sf_createtemplate_semanticproperty' => 'ویژگی معنایی:',//Semantic property:
	'sf_createtemplate_fieldislist' => 'این ورودی می‌تواند یک سیاهه از مقادیر را که با کاما از هم جدا شده‌اند، در خود ذخیره کند',//This field can hold a list of values, separated by commas
	'sf_createtemplate_aggregation' => 'تراکم',//Aggregation
	'sf_createtemplate_aggregationdesc' => 'برای داشتن سیاهه همه صفحاتی که این الگو را به‌کار می‌برند، و همه آن مقالاتی که با ویژگی معینی با آن صفحه در ارتباط هستند، وِیژگی مناسب را مشخص کنید:',//To list, on any page using this template, all of the articles that have a certain property pointing to that page, specify the appropriate property below:
	'sf_createtemplate_aggregationlabel' => 'عنوان سیاهه',//Title for list:
	'sf_createtemplate_outputformat' => 'قالب خروجی:',//Output format:
	'sf_createtemplate_standardformat' => 'استاندارد',//Standard
	'sf_createtemplate_infoboxformat' => 'جعبه اطلاعات سمت راست',//Right-hand-side infobox
	'sf_createtemplate_addfield' => 'افزودن ورودی',//Add field
	'sf_createtemplate_deletefield' => 'حذف',//Delete
	'sf_createtemplate_addtemplatebeforesave' => 'قبل از اینکه بتوانید فرم را ذخیره کنید، شما می‌بایست حداقل یک الگو به این فرم اضافه نمایید.',//You must add at least one template to this form before you can save it. 
	'forms' => 'فرم‌ها',//Forms
	'sf_forms_docu' => 'فرم‌های زیر در این ویکی وجود دارند.',//The following forms exist in the wiki.
	'createform' => 'فرم بسازید',//Create a form
	'sf_createform_nameinput' => 'نام فرم (بهتر است که بعد از پر شدن به‌وسیله یک الگو، فرم نام‌گذاری شود):',//Form name (convention is to name the form after the main template it populates):
	'sf_createform_template' => 'الگو:',//Template:
	'sf_createform_templatelabelinput' => 'برچسب الگو (دلخواه):',//Template label (optional):
	'sf_createform_allowmultiple' => 'می‌توان چند (یا صفر) نمونه از این الگو را در ساختن صفحات استفاده کرد',//Allow for multiple (or zero) instances of this template in the created page
	'sf_createform_field' => 'ورودی:',//Field:
	'sf_createform_fieldattr' => 'این ورودی صفت $1 از نوع $2 را تعیین می‌کند.',//This field defines the attribute $1, of type $2.
	'sf_createform_fieldattrlist' => 'این ورودی یک سیاهه از مواردی که صفت $1 از نوع $2 را دارند، تعیین می‌کند.',//This field defines a list of elements that have the attribute $1, of type $2.
	'sf_createform_fieldattrunknowntype' => 'این ورودی صفت $1 از یک نوع مشخص‌نشده(به‌فرض نوع $2) را تعیین می‌کند.',//This field defines the attribute $1, of unspecified type (assuming to be $2).
	'sf_createform_fieldrel' => 'این ورودی رابطه $1 را تعیین می‌کند.',//This field defines the relation $1.
	'sf_createform_fieldrellist' => 'این ورودی یک سیاهه از مواردی که ویژگی $1 را دارند، تعیین می‌کند.',//This field defines a list of elements that have the relation $1.
	'sf_createform_fieldprop' => 'این ورودی ویژگی $1 از نوع $2 را تعیین می‌کند.',//This field defines the property $1, of type $2.
	'sf_createform_fieldproplist' => 'این ورودی یک سیاهه از مواردی که ویژگی $1 از نوع $2 را دارند، تعیین می‌کند.',//This field defines a list of elements that have the property $1, of type $2.
	'sf_createform_fieldpropunknowntype' => 'این ورودی ویژگی $1 از نوع نامشخص را تعیین می‌کند.',//This field defines the property $1, of unspecified type.
	'sf_createform_inputtype' =>  'نوع ورودی:',//Input type:
	'sf_createform_inputtypedefault' =>  '(پیش‌فرض)',//(default)
	'sf_createform_formlabel' => 'برچسب فرم:',//Form label:
	'sf_createform_hidden' =>  'مخفی',//Hidden
	'sf_createform_restricted' =>  'محدود‌شده (فقط مدیران می‌توانند ویرایش کنند)',//Restricted (only sysop users can modify it)
	'sf_createform_mandatory' =>  'الزامی',//Mandatory
	'sf_createform_removetemplate' => 'حذف الگو',//Remove template
	'sf_createform_addtemplate' => 'افزودن الگو:',//Add template:
	'sf_createform_beforetemplate' => 'قبل از الگوی:',//Before template:
	'sf_createform_atend' => 'در آخر',//At end
	'sf_createform_add' => 'افزودن',//Add
	'createcategory' => 'رده بسازید',//Create a category
	'sf_createcategory_name' => 'نام:',//Name:
	'sf_createcategory_defaultform' => 'فرم پیش‌فرض:',//Default form:
	'sf_createcategory_makesubcategory' => 'قرار دادن این رده به عنوان زیررده یک رده دیگر(دلخواه):',//Make this a subcategory of another category (optional):
	'addpage' => 'افزودن صفحه',//Add page
	'sf_addpage_badform' => 'خطا: هیچ صفحه فرمی در $1 پیدا نشد',//Error: no form page was found at $1
	'sf_addpage_docu' => 'برای ویرایش با فرم \'$1\'، نام صفحه را اینجا وارد کنید. اگر صفحه در حال حاضر موجود باشد، شما به فرم ویرایش صفحه هدایت می‌شوید. در غیر این‌صورت به فرم افزودن صفحه منتقل خواهید شد.',//Enter the name of the page here, to be edited with the form \'$1\'. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.
	'sf_addpage_noform_docu' => 'نام صفحه را اینجا وارد کنید و فرمی را که می‌خواهید ویرایش با آن انجام شود انتخاب نمایید. اگر صفحه در حال حاضر موجود باشد، شما به صفحه ویرایش آن صفحه به‌وسیله فرم  هدایت می‌شوید. در غیر این‌صورت به فرم افزودن صفحه منتقل خواهید شد.',//Enter the name of the page here, and select the form to edit it with. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.
	'addoreditdata' => 'افزودن یا ویرایش',//Add or edit
	'adddata' => 'افزودن اطلاعات',//Add data
	'sf_adddata_title' => 'افزودن $1: $2',//Add $1: $2
	'sf_adddata_badurl' => 'این صفحه برای افزودن اطلاعات است. شما باید هم نام فرم هم صفحه مقصد را در URL وارد کنید. چیزی شبیه به این <br/><span dir="ltr"> \'ویژه:AddData?form=&lt;نام فرم&gt;&target=&lt;صفحه مقصد&gt;\' </span><br/>یا<br/><span dir="ltr"> \'ویژه:AddData/&lt;نام فرم&gt;/&lt;صفحه مقصد&gt;\' </span>.',//This is the page for adding data. You must specify both a form name and a target page in the URL; it should look like \'Special:AddData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:AddData/&lt;form name&gt;/&lt;target page&gt;\'.
	'sf_adddata_altforms' => 'می‌توانید این صفحه را به‌وسیله فرم‌های زیر نیز بسازید:',//You can instead add this page with one of the following forms:
	'sf_adddata_altformsonly' => 'لطفا برای افزودن صفحه از یکی از فرم‌های زیر استفاده کنید:',//Please select from one of the following forms to add this page:
	'sf_forms_adddata' => 'افزودن اطلاعات به‌وسیله این فرم',//Add data with this form
	'editdata' => 'ویرایش اطلاعات',//Edit data
	'form_edit' => 'ویرایش با فرم',//Edit with form
	'edit_source' => 'ویرایش مبدأ',//Edit source
	'sf_editdata_title' => 'ویرایش $1: $2',//Edit $1: $2
    'sf_editdata_badurl' => 'این صفحه برای ویرایش اطلاعات است. شما باید هم نام فرم هم صفحه مقصد را در URL وارد کنید. چیزی شبیه به این <br/><span dir="ltr"> \'ویژه:EditData?form=&lt;نام فرم&gt;&target=&lt;صفحه مقصد&gt;\' </span><br/>یا<br/><span dir="ltr"> \'ویژه:EditData/&lt;نام فرم&gt;/&lt;صفحه مقصد&gt;\' </span>.',//This is the page for editing data. You must specify both a form name and a target page in the URL; it should look like \'Special:EditData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:EditData/&lt;form name&gt;/&lt;target page&gt;\'.
	'sf_editdata_formwarning' => 'اخطار: این صفحه <a href="$1">هم‌اکنون وجود دارد</a>، ولی به‌وسیله این فرم ساخته نشده است.',//Warning: This page <a href="$1">already exists</a>, but it does not use this form.
	'sf_editdata_remove' => 'حذف',//Remove
	'sf_editdata_addanother' => 'افزدون دیگری',//Add another
	'sf_editdata_freetextlabel' => 'متن دلخواه',//Free text

	'sf_blank_error' => 'نمی‌تواند خالی باشد'//cannot be blank	
    'sf_bad_url_error' => 'باید قالب URL درستی داشته باشد و با \'http\' شروع شود',//must have the correct URL format, starting with \'http\'
	'sf_bad_email_error' => 'باید قالب صحیحی برای پست الکترونیک داشته باشد',//must have a valid email address format
    'sf_bad_number_error' => 'باید یک عدد معتبر باشد',//must be a valid number
    'sf_bad_integer_error' => 'باید یک عدد صحیح معتبر باشد',//must be a valid integer
    'sf_bad_date_error' => 'باید یک تاریخ معتبر باشد'//must be a valid date 
);

/* private */ var $m_SpecialProperties = array(
	//always start upper-case
	SF_SP_HAS_DEFAULT_FORM  => 'فرم پیش‌فرض دارد',//Has default form
	 SF_SP_HAS_ALTERNATE_FORM  => 'فرم مشابه دارد'//Has alternate form
);

/* private */ var $m_SpecialPropertyAliases = array(
	// support English aliases for special properties
	'Has default form'	=> SF_SP_HAS_DEFAULT_FORM,
	'Has alternate form'	=> SF_SP_HAS_ALTERNATE_FORM
);

var $m_Namespaces = array(
	SF_NS_FORM           => 'فرم',//Form
	SF_NS_FORM_TALK      => 'بحث_فرم'//Form_talk
);

var $m_NamespaceAliases = array(
	// support English aliases for namespaces
	'Form'		=> SF_NS_FORM,
	'Form_talk'	=> SF_NS_FORM_TALK
);

}

?>
