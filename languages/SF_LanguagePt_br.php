<?php
/**
 * @author Yaron Koren
 */

class SF_LanguageEn extends SF_Language {

/* private */ var $m_ContentMessages = array(
	'sf_property_isattribute' => 'Este é um atributo to tipo $1.',
	'sf_property_isproperty' => 'Esta é uma propriedade do tipo $1.',
	'sf_property_allowedvals' => 'Os valores permitidos para este atributo ou propriedade são:',
	'sf_property_isrelation' => 'Esta é uma relação.',
	'sf_template_docu' => 'Esta é a \'$1\' predefinição. Ela deve ser chamada no seguinte formato:',
	'sf_template_docufooter' => 'Edite a página para ver o texto da predefinição.',
	'sf_form_docu' => 'Este é o \'$1\' formulário. Para adicionar uma página com esse formulário, adicione o nome da página abaixo; se já existir uma página com o mesmo nome, você será enviado para um formulário para editar a página.',
	'sf_category_hasdefaultform' => 'Esta categoria usa o formulário $1.',
	 'sf_category_desc' => 'Esta é a $1 categoria.',
	// month names are already defined in MediaWiki, but unfortunately
	// there they're defined as user messages, and here they're
	// content messages
	'sf_january' => 'Janeiro', 
	'sf_february' => 'Fevereiro',
	'sf_march' => 'Março',
	'sf_april' => 'Abril',
	'sf_may' => 'Maio',
	'sf_june' => 'Junho',
	'sf_july' => 'Julho',
	'sf_august' => 'Agosto',
	'sf_september' => 'Setembro',
	'sf_october' => 'Outubro',
	'sf_november' => 'Novembro',
	'sf_december' => 'Dezembro',
	'sf_blank_namespace' => 'Principal'
);

/* private */ var $m_UserMessages = array(
	'createproperty' => 'Cria uma propriedade',
	'sf_createproperty_allowedvalsinput' => 'Se você quer que somente determinados valores sejam permitidos nesse campo, entre com a lista dos valores permitidos, separados por vírgulas (se um valor contém vírgula, substitua por "\,"):',
	'sf_createproperty_propname' => 'Nome:',
	'sf_createproperty_proptype' => 'Tipo:',
	'templates' => 'Predefinições',
	'sf_templates_docu' => 'As seguintes predefinições existem na wiki.',
	'sf_templates_definescat' => 'define categoria:',
	'createtemplate' => 'Cria uma predefinição',
	'sf_createtemplate_namelabel' => 'Nome da predefinição:',
	'sf_createtemplate_categorylabel' => 'Categoria definida por predefinição (opcional):',
	'sf_createtemplate_templatefields' => 'Campos da predefinição',
	'sf_createtemplate_fieldsdesc' => 'Para ter os campos nesta predefinição não é necessário o nome dos campos, simplesmente entre com o índice de cada campo (e.g. 1, 2, 3, etc.) como nome, ao invés de um nome atual.',
	'sf_createtemplate_fieldname' => 'Nome do Campo:',
	'sf_createtemplate_displaylabel' => 'Exibir rótulo:',
	'sf_createtemplate_semanticproperty' => 'Propriedade semântica:',
	'sf_createtemplate_fieldislist' => 'Este campo pode manter uma lista de valores, separados por vírgulas',
	'sf_createtemplate_aggregation' => 'Agregação',
	'sf_createtemplate_aggregationdesc' => 'Para listar, em qualquer página usando essa predefinição, todos os artigos que tem uma determinada propriedade apontando para aquela página, especifique a propriedade apropriada abaixo:',
	'sf_createtemplate_aggregationlabel' => 'Título para a lista:',
	'sf_createtemplate_outputformat' => 'Formato de saída:',
	'sf_createtemplate_standardformat' => 'Padrão',
	'sf_createtemplate_infoboxformat' => 'Right-hand-side infobox',
	'sf_createtemplate_addfield' => 'Adicionar campo',
	'sf_createtemplate_deletefield' => 'Deletar',
	  'sf_createtemplate_addtemplatebeforesave' => 'Você deve adicionar ao menos uma predefinição para este formulário antes de salvar.',
	'forms' => 'Formulários',
	'sf_forms_docu' => 'Os seguintes formulários existem na wiki.',
	'createform' => 'Criar um formulário',
	'sf_createform_nameinput' => 'Nome do formulário (convention is to name the form after the main template it populates):',
	'sf_createform_template' => 'Predefinição:',
	'sf_createform_templatelabelinput' => 'Título da predefinição (opcional):',
	'sf_createform_allowmultiple' => 'Permitir várias instâncias (ou zero) dessa predefinição na página criada',
	'sf_createform_field' => 'Campo:',
	'sf_createform_fieldattr' => 'Este campo define o atributo $1, do tipo $2.',
	'sf_createform_fieldattrlist' => 'Este campo define uma lista de elementos que tem o atributo $1, do tipo $2.',
	'sf_createform_fieldattrunknowntype' => 'Estte campo define o atributo $1, de um tipo não especificado.',
	'sf_createform_fieldrel' => 'Este campo define uma relação $1.',
	'sf_createform_fieldrellist' => 'Este campo define uma lista de elementos que tem a relação $1.',
	'sf_createform_fieldprop' => 'Este campo define a propriedade $1, do tipo $2.',
	'sf_createform_fieldproplist' => 'Este campo define uma lista de elementos que tem a propriedade $1, do tipo $2.',
	'sf_createform_fieldpropunknowntype' => 'Este campo define a propriedade $1, de um tipo não especificado.',
	'sf_createform_inputtype' =>  'Input type:',
	'sf_createform_inputtypedefault' =>  '(padrão)',
	'sf_createform_formlabel' => 'Título do formulário:',
	'sf_createform_hidden' =>  'Escondido',
	'sf_createform_restricted' =>  'Restrito (somente usuários sysop podem modificar isto)',
	'sf_createform_mandatory' =>  '	Obrigatório',
	'sf_createform_removetemplate' => 'Remover predefinição',
	'sf_createform_addtemplate' => 'Adicionar predefinição:',
	'sf_createform_beforetemplate' => 'Predefinição anterior:',
	'sf_createform_atend' => 'No final',
	'sf_createform_add' => 'Adicionar',
	'sf_createform_choosefield' => 'Escolha um campo para adicionar',
	'createcategory' => 'Cria uma categoria',
	'sf_createcategory_name' => 'Nome:',
	'sf_createcategory_defaultform' => 'Formulário padrão:',
	'sf_createcategory_makesubcategory' => 'Faz desta uma subcategoria de outra categoria (opcional):',
	'addpage' => 'Adicionar página',
	'sf_addpage_badform' => 'Erro: nenhum formulário de página foi encontrado em $1',
	'sf_addpage_docu' => 'Entre com o nome da página aqui, para ser editado com o formulário \'$1\'. Se esta página já existir, você será direcionado para o formulário para editar a página. Senão, você será direcionado para o formulário para adicionar a página.',
	'sf_addpage_noform_docu' => 'Entre com o nome da página aqui, e selecione o formulário na qual a página será editada. Se esta página já existir, você será direcionado para o formulário para editar a página.  Senão, você será direcionado para o formulário para adicionar a página.',
	'addoreditdata' => 'Adicionar ou editar',
	'adddata' => 'Adicionar dados',
	'sf_adddata_title' => 'Adicionar $1: $2',
	'sf_adddata_badurl' => 'Esta é a página para adicionar dados. Você deve especificar ambos um nome de formulário e uma página alvo na URL; deve ser semelhante a \'Special:AddData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:AddData/&lt;form name&gt;/&lt;target page&gt;\'.',
	'sf_adddata_altforms' => 'Você também pode adicionar está página com um dos seguintes formulários:',
	'sf_adddata_altformsonly' => 'Por favor selecione através de um dos seguintes formulários para adicionar esta página:',
	'sf_forms_adddata' => 'Adicionar dados com o seguinte formulário',
	'editdata' => 'Editar dados',
	'form_edit' => 'Editar com formulário',
	'edit_source' => 'Editar fonte',
	'sf_editdata_title' => 'Editar $1: $2',
	'sf_editdata_badurl' => 'Está é a página para editar dados. Você deve especificar ambos um nome de formulário e uma página alvo na URL; deve ser semelhante a \'Special:EditData?form=&lt;form name&gt;&target=&lt;target page&gt;\' or  \'Special:EditData/&lt;form name&gt;/&lt;target page&gt;\'.',
	'sf_editdata_formwarning' => 'Perigo: Esta página <a href="$1">already exists</a>, mas não use esse formulário.',
	'sf_editdata_remove' => 'Remover',
	'sf_editdata_addanother' => 'Adicionar outro',
	'sf_editdata_freetextlabel' => 'Texto livre',

	'sf_blank_error' => 'Não pode ficar em branco'
	'sf_bad_url_error' => 'deve ter o formato correto da URL, começando com \'http\'', 
    'sf_bad_email_error' => 'deve ter um formato válido de email', 
    'sf_bad_number_error' => 'deve ser um número válido',
    'sf_bad_integer_error' => 'deve ser um válido integer', 
    'sf_bad_date_error' => 'deve ser uma data válida'
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
