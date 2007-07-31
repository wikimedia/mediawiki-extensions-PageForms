<?php
/**
 * @author lleoliveirabr
 */

class SF_LanguagePt_br {

/* private */ var $sfContentMessages = array(
    'sf_template_docu' => 'Esta é a \'$1\' predefinição. Necessita ser chamada no seguinte formato:',
    'sf_template_docufooter' => 'Edite a página para ver o texto da predefinição.',
    'sf_template_pagetype' => 'Esta é uma página do tipo $1.',
    'sf_form_relation' => 'Tem formulário padrão',
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
    'sf_december' => 'Dezembro'

);

/* private */ var $sfUserMessages = array(
    'templates' => 'Predefinições',
    'sf_templates_docu' => 'As seguintes redifinições existem na wiki.',
    'sf_templates_definescat' => 'define categoria:',
    'createtemplate' => 'Criar predefinição',
    'sf_createtemplate_namelabel' => 'Nome da predefinição:',
    'sf_createtemplate_categorylabel' => 'Categoria definida por predefinição (opcional):',
    'sf_createtemplate_templatefields' => 'Campos da predefinição',
    'sf_createtemplate_fieldsdesc' => 'Para ter os
campos da predefinição não requer os nomes dos campos, simplesmente
entre com o índice daquele campo (e.g. 1, 2, 3, etc.) como nome, no
lugar do nome atual.',
    'sf_createtemplate_fieldname' => 'Nome do campo:',
    'sf_createtemplate_displaylabel' => 'Mostrar rótulo:',
    'sf_createtemplate_semanticfield' => 'Semantic nome do campo:',
    'sf_createtemplate_attribute' => 'atributo',
    'sf_createtemplate_relation' => 'relação',
    'sf_createtemplate_addfield' => 'Adicionar campo',
    'sf_createtemplate_deletefield' => 'Deletar',
    'forms' => 'Formulários',
    'sf_forms_docu' => 'Os seguintes formulários existem na wiki.',
    'createform' => 'Criar formulário',
    'sf_createform_nameinput' => 'Nome do formulário:',
    'sf_createform_template' => 'Predefinição:',
    'sf_createform_templatelabelinput' => 'Rótulo da predefinição (opcional):',
    'sf_createform_allowmultiple' => 'Permitir múltiplos (ou zero) exemplos dessa predefinição na página criada',
    'sf_createform_field' => 'Campo:',
    'sf_createform_fieldattr' => 'Este campo define o atributo $1, do tipo $2.',
    'sf_createform_fieldattrunknowntype' => 'Este campo define o atributo $1, de um tipo não especificado (assumindo ser $2).',
    'sf_createform_fieldrel' => 'Este campo define a relação $1.',
    'sf_createform_formlabel' => 'Rótulo do formulário',
    'sf_createform_hidden' =>  'Escondido',
    'sf_createform_mandatory' =>  'Obrigatório',
    'sf_createform_removetemplate' => 'Remover predefinição',
    'sf_createform_addtemplate' => 'Adicionar predefinição:',
    'sf_createform_beforetemplate' => 'Antes da predefinição:',
    'sf_createform_atend' => 'No final',
    'sf_createform_add' => 'Adicionar',
    'adddata' => 'Adicionar dados',
    'sf_adddata_badurl' => 'Esta é a página para adicionar dados. Você deve especificar o nome de formulário para adicionar dados; exemplo \'Special:AddData?form=&lt;nome do formulário&gt;\' ou \'Special:AddData/&lt;nome do formulário&gt;\'.',
    'sf_forms_adddata' => 'Adicionar dados usando esse formulário',
    'editdata' => 'Editar dados',
    'form_edit' => 'Editar com formulário',
    'sf_editdata_badurl' => 'Esta é a página para editar dados. Você deve especificar o nome do formulário e a página a ser editada; exemplo \'Special:EditData?form=&lt;nome do formulário&gt;&amp;target=&lt;nome do página&gt;\' ou \'Special:EditData/&lt;nome do formulário&gt;/&lt;nome do página&gt;\'.',
    'sf_editdata_remove' => 'Remover',
    'sf_editdata_addanother' => 'Adicionar outro',
    'sf_editdata_freetextlabel' => 'Texto',

    'sf_blank_error' => 'Não pode ficar em branco'
);

    /**
     * Function that returns the namespace identifiers.
     */
    function getNamespaceArray() {
        return array(
          SF_NS_FORM          => 'Formulário',
          SF_NS_FORM_TALK     => 'Discussão do formulário'
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
