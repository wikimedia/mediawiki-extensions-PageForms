<?php
/**
 * @author Roc Michael
 */
 
class SF_LanguageEn {
 
/* private */ var $sfContentMessages = array(
        'sf_createproperty_isattribute' => '&#36889;&#26159;&#22411;&#24907;$1&#30340;&#23660;&#24615;&#12290; This is an attribute of type $1.',
        'sf_createproperty_allowedvals' => '&#36889;&#38917;&#23660;&#24615;&#30340;&#21487;&#29992;&#22411;&#24907;&#28858;&#65306; The allowed values for this attribute are:',
        'sf_createproperty_isrelation' => '&#36889;&#26159;&#19968;&#38917;&#38364;&#36899;&#12290;This is a relation.',
        'sf_template_docu' => '&#36889;&#26159;\'$1\'&#27171;&#26495;&#65292;&#23427;&#38920;&#20197;&#19979;&#21015;&#26684;&#24335;&#24341;&#29992;&#12290;This is the \'$1\' template. It should be called in the following format:',
        'sf_template_docufooter' => '&#32232;&#36655;&#27492;&#38913;&#20197;&#26597;&#30475;&#27171;&#26495;&#12290;Edit the page to see the template text.',
        'sf_form_docu' => '&#36889;&#26159;\'$1\'&#34920;&#21934;&#65292;&#32232;&#36655;&#27492;&#38913;&#20197;&#26597;&#30475;&#21407;&#22987;&#30908;&#65292;&#24744;&#33021;&#20197;&#27492;&#34920;&#21934;&#26032;&#22686;&#36039;&#26009;[[$2|&#36889;&#35041;]]&#12290; This is the \'$1\' form; edit the page to see the source code. You can add data with this form [[$2|here]].',
        'sf_form_relation' => '&#20351;&#29992;&#34920;&#21934; Has default form',
        // month names are already defined in MediaWiki, but unfortunately
        // there they're defined as user messages, and here they're
        // content messages
        'sf_january' => '&#19968;&#26376;',              //    'sf_january' => 'January',
        'sf_february' => '&#20108;&#26376;',             //    'sf_february' => 'February',
        'sf_march' => '&#19977;&#26376;',                //    'sf_march' => 'March',
        'sf_april' => '&#22235;&#26376;',                //    'sf_april' => 'April',
        'sf_may' => '&#20116;&#26376;',          //    'sf_may' => 'May',
        'sf_june' => '&#20845;&#26376;',         //    'sf_june' => 'June',
        'sf_july' => '&#19971;&#26376;',         //    'sf_july' => 'July',
        'sf_august' => '&#20843;&#26376;',               //    'sf_august' => 'August',
        'sf_september' => '&#20061;&#26376;',            //    'sf_september' => 'September',
        'sf_october' => '&#21313;&#26376;',              //    'sf_october' => 'October',
        'sf_november' => '&#21313;&#26376;',             //    'sf_november' => 'November',
        'sf_december' => '&#21313;&#20108;&#26376;'             //    'sf_december' => 'December'
 
);
 
/* private */ var $sfUserMessages = array(
        'createproperty' => '&#24314;&#31435;semantic&#30340;&#24615;&#36074; Create a semantic property',
        'templates' => '&#27171;&#26495; Templates',
        'sf_templates_docu' => '&#26412;wiki&#31995;&#32113;&#21547;&#26377;&#19979;&#21015;&#30340;&#27171;&#26495;&#12290;The following templates exist in the wiki.',
        'sf_templates_definescat' => '&#23450;&#32681;&#20998;&#39006; defines category:',
        'createtemplate' => '&#24314;&#31435;&#27171;&#26495; Create a template',
        'sf_createtemplate_namelabel' => '&#27171;&#26495;&#21517;&#31281; Template name:',
        'sf_createtemplate_categorylabel' => '&#20197;&#27171;&#26495;&#23450;&#32681;&#20998;&#39006;(&#36984;&#29992;&#24615;) Category defined by template (optional):',
        'sf_createtemplate_templatefields' => '&#27171;&#26495;&#27396;&#20301; Template fields',
        'sf_createtemplate_fieldsdesc' => '&#24314;&#31435;&#28961;&#38920;&#21517;&#31281;&#30340;&#27396;&#20301;&#26044;&#26576;&#20491;&#27171;&#26495;&#20043;&#20839;&#65292;&#20677;&#38656;&#36070;&#20104;&#32034;&#24341;&#20540;(&#20363;&#22914;&#65306; 1,2,3 &#31561;&#31561;)&#32102;&#36889;&#20123;&#27396;&#20301; &#32780;&#28961;&#38920;&#25351;&#23450;&#21517;&#31281;&#12290;To have the fields of a template not require field names, simply enter the index of that field (e.g. 1, 2, 3, etc.) as the name, instead of an actual name.',
        'sf_createtemplate_fieldname' => '&#27396;&#20301;&#21517;&#31281; Field name:',
        'sf_createtemplate_displaylabel' => '&#27396;&#20301;&#27161;&#31844; Display label:',
        'sf_createtemplate_semanticproperty' => 'Semantic&#24615;&#36074; Semantic property:',
        'sf_createtemplate_fieldislist' => '&#26412;&#27396;&#20301;&#22816;&#24314;&#31435;&#19968;&#28165;&#21934;&#65292;&#20839;&#37096;&#30340;&#36039;&#26009;&#23559;&#20197;&#21322;&#22411;&#36887;&#34399;&#12300;,&#12301;&#20998;&#38548;&#12290;This field can hold a list of values, separated by commas',
        'sf_createtemplate_outputformat' => '&#36664;&#20986;&#26684;&#24335; Output format:',
        'sf_createtemplate_standardformat' => '&#27161;&#28310; Standard',
        'sf_createtemplate_infoboxformat' => '&#21491;&#32622;&#22411;&#35338;&#24687;&#30475;&#26495; &#30340;Right-hand-side infobox',
        'sf_createtemplate_addfield' => '&#26032;&#22686;&#27396;&#20301; Add field',
        'sf_createtemplate_deletefield' => '&#21034;&#38500; Delete',
        'forms' => '&#34920;&#21934; Forms',
        'sf_forms_docu' => '&#26412;wiki&#31995;&#32113;&#21547;&#26377;&#19979;&#21015;&#30340;&#34920;&#21934;&#12290;The following forms exist in the wiki.',
        'createform' => '&#26032;&#22686;&#34920;&#21934; Create a form',
        'sf_createform_nameinput' => '&#34920;&#21934;&#21517;&#31281;(convention is to name the form after the main template it populates)&#12290;Form name (convention is to name the form after the main template it populates):',
        'sf_createform_template' => '&#27171;&#26495;&#65306; Template:',
        'sf_createform_templatelabelinput' => '&#27171;&#26495;&#27161;&#31844;(&#36984;&#29992;) Template label (optional):',
        'sf_createform_allowmultiple' => '&#20801;&#35377;&#22312;&#29992;&#20197;&#24314;&#31435;&#26032;&#38913;&#38754;&#19978;&#24314;&#31435;&#22810;&#37325;&#36984;&#38917;(&#25110;&#28961;)&#12290; Allow for multiple (or zero) instances of this template in the created page',
        'sf_createform_field' => '&#27396;&#20301;&#65306; Field:',
        'sf_createform_fieldattr' => '&#27492;&#27396;&#20301;&#21487;&#23450;&#32681;&#22411;&#24907;$2&#19978;&#30340; $1&#23660;&#24615;&#12290;This field defines the attribute $1, of type $2.',
        'sf_createform_fieldattrunknowntype' => '&#36889;&#27396;&#20301;&#21487;&#23450;&#32681;&#23660;&#24615;$1&#65292;&#36889;&#20123;&#23660;&#24615;&#23578;&#26410;&#25351;&#23450;&#30340;&#22411;&#24907;(assuming to be $2)&#12290; This field defines the attribute $1, of unspecified type (assuming to be $2).',
        'sf_createform_fieldrel' => '&#27492;&#27396;&#20301;&#21487;&#23450;&#32681;&#38364;&#36899; $1&#12290; This field defines the relation $1.',
        'sf_createform_formlabel' => '&#34920;&#21934;&#27161;&#31844;&#12290;Form label:',
        'sf_createform_hidden' =>  '&#31337;&#34255; Hidden',
        'sf_createform_restricted' =>  '&#21463;&#38480;&#21046;&#30340;&#38913;&#38754;(&#21482;&#26377;&#31649;&#29702;&#21487;&#32232;&#36655;) Restricted (only sysop users can modify it)',
        'sf_createform_mandatory' =>  '&#24375;&#21046;&#24615;&#30340; Mandatory',
        'sf_createform_removetemplate' => '&#21034;&#38500;&#27171;&#26495; Remove template',
        'sf_createform_addtemplate' => '&#26032;&#22686;&#27171;&#26495; Add template:',
        'sf_createform_beforetemplate' => 'Before template:',
        'sf_createform_atend' => 'At end',
        'sf_createform_add' => '&#26032;&#22686; Add',
        'addpage' => '&#26032;&#22686;&#38913;&#38754; Add page',
        'sf_addpage_badform' => '&#37679;&#35492;&#65281;&#22312;$1&#19978;&#20006;&#27794;&#26377;&#25214;&#21040;&#34920;&#21934;&#38913;&#38754;&#12290; Error: no form page was found at $1',
        'sf_addpage_docu' => '&#36664;&#20837;&#38913;&#38754;&#21517;&#31281;&#20197;&#20415;&#20197;&#34920;&#21934;&#32232;&#36655;\'$1\'&#12290;&#22914;&#26524;&#27492;&#38913;&#24050;&#23384;&#22312;&#30340;&#35441;&#65292;&#24744;&#20415;&#33021;&#20197;&#34920;&#21934;&#32232;&#36655;&#35442;&#38913;&#65292;&#19981;&#28982;&#65292;&#24744;&#20415;&#33021;&#20197;&#34920;&#21934;&#26032;&#22686;&#27492;&#38913;&#38754;&#12290; Enter the name of the page here, to be edited with the form \'$1\'. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.',
        'sf_addpage_noform_docu' => '&#35531;&#26044;&#27492;&#34389;&#36664;&#20837;&#38913;&#38754;&#21517;&#31281;&#65292;&#20877;&#36984;&#21462;&#34920;&#21934;&#23565;&#20854;&#36914;&#34892;&#32232;&#36655;&#65292;&#22914;&#26524;&#27492;&#38913;&#24050;&#23384;&#22312;&#30340;&#35441;&#65292;&#24744;&#20415;&#33021;&#20197;&#34920;&#21934;&#32232;&#36655;&#35442;&#38913;&#65292;&#19981;&#28982;&#65292;&#24744;&#20415;&#33021;&#20197;&#34920;&#21934;&#26032;&#22686;&#27492;&#38913;&#38754;&#12290; Enter the name of the page here, and select the form to edit it with. If this page already exists, you will be sent to the form for editing that page. Otherwise, you will be sent to the form for adding the page.',
        'addoreditdata' => '&#26032;&#22686;&#25110;&#32232;&#36655; Add or edit',
        'adddata' => '&#26032;&#22686;&#36039;&#26009; Add data',
        'sf_adddata_badurl' => '&#26412;&#38913;&#28858;&#26032;&#22686;&#36039;&#26009;&#20043;&#29992;&#65292;&#24744;&#24517;&#38920;&#22312;URL&#35041;&#21516;&#26178;&#25351;&#23450;&#34920;&#21934;&#21450;&#30446;&#27161;&#38913;&#38754;&#65292;&#23427;&#25033;&#35442;&#30475;&#36215;&#20687;&#26159;\'Special:AddData?form=&lt;form name&gt;&target=&lt;target page&gt;\' &#25110;&#26159; \'Special:AddData/&lt;form name&gt;/&lt;target page&gt;\'&#12290; This is the page for adding data. You must specify both a form name and a target page in the URL; it should look like \'Special:AddData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:AddData/&lt;form name&gt;/&lt;target page&gt;\'.',
        'sf_forms_adddata' => '&#20197;&#34920;&#21934;&#26032;&#22686;&#36039;&#26009; Add data with this form',
        'editdata' => '&#32232;&#36655;&#36039;&#26009; Edit data',
        'form_edit' => '&#20197;&#34920;&#21934;&#36914;&#34892;&#32232;&#36655; Edit with form',
        'sf_editdata_badurl' => '&#26412;&#38913;&#28858;&#32232;&#36655;&#36039;&#26009;&#20043;&#29992;&#65292;&#24744;&#24517;&#38920;&#22312;URL&#35041;&#21516;&#26178;&#25351;&#23450;&#34920;&#21934;&#21450;&#30446;&#27161;&#38913;&#38754;&#65292;&#23427;&#25033;&#35442;&#30475;&#36215;&#20687;&#26159;\'Special:EditData?form=&lt;form name&gt;&target=&lt;target page&gt;\' &#25110;&#26159;  \'Special:EditData/&lt;form name&gt;/&lt;target page&gt;\'. This is the page for editing data. You must specify both a form name and a target page in the URL; it should look like \'Special:EditData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:EditData/&lt;form name&gt;/&lt;target page&gt;\'.',
        'sf_editdata_remove' => '&#21034;&#38500; Remove',
        'sf_editdata_addanother' => '&#26032;&#22686;&#20854;&#20182; Add another',
        'sf_editdata_freetextlabel' => '&#33258;&#30001;&#32232;&#36655;&#21312; Free text',
 
        'sf_blank_error' => '&#19981;&#24471;&#28858;&#31354;&#30333; cannot be blank'
);
 
        /**
         * Function that returns the namespace identifiers.
         */
        function getNamespaceArray() {
                return array(
                        SF_NS_FORM           => '&#34920;&#21934;',                       //    SF_NS_FORM           => 'Form',
                        SF_NS_FORM_TALK      => '&#34920;&#21934;_talk'           //    SF_NS_FORM_TALK      => 'Form_talk'
 
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