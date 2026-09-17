<?php

namespace PageForms\Tests\Integration\Includes;

use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;

if ( !class_exists( 'PSExtensionHandler' ) ) {
	class_alias( \stdClass::class, 'PSExtensionHandler' );
}

/**
 * @covers \PFPageSchemas
 * @group PageForms
 */
class PFPageSchemasTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \PFPageSchemas::createSchemaXMLFromForm
	 */
	public function testCreateSchemaXMLFromForm_escapesSpecialCharacters() {
		$request = new FauxRequest( [
			'pf_form_name' => 'Form & "Test" <1>',
			'pf_page_name_formula' => 'Test & <Formula>',
			'pf_create_title' => 'Create & "Title"',
			'pf_edit_title' => 'Edit & "Title"',
			'pf_fi_free_text' => '1',
			'pf_fi_free_text_label' => 'Free & "Label"',
		], true );
		$this->setRequest( $request );

		$xml = \PFPageSchemas::createSchemaXMLFromForm();

		$this->assertStringContainsString( 'name="Form &amp; &quot;Test&quot; &lt;1&gt;"', $xml );
		$this->assertStringContainsString( '<PageNameFormula>Test &amp; &lt;Formula&gt;</PageNameFormula>', $xml );
		$this->assertStringContainsString( '<CreateTitle>Create &amp; "Title"</CreateTitle>', $xml );
		$this->assertStringContainsString( '<EditTitle>Edit &amp; "Title"</EditTitle>', $xml );
		$this->assertStringContainsString( 'freeTextLabel="Free &amp; &quot;Label&quot;"', $xml );
	}

	/**
	 * @covers \PFPageSchemas::createTemplateXMLFromForm
	 */
	public function testCreateTemplateXMLFromForm_escapesSpecialCharacters() {
		$request = new FauxRequest( [
			'pf_template_label_0' => 'Label & "Name" <Tag>',
			'pf_template_addanother_0' => 'Add & "Another" <Tag>',
		], true );
		$this->setRequest( $request );

		$xmlPerTemplate = \PFPageSchemas::createTemplateXMLFromForm();

		$this->assertArrayHasKey( '0', $xmlPerTemplate );
		$xml = $xmlPerTemplate['0'];
		$this->assertStringContainsString( '<Label>Label &amp; "Name" &lt;Tag&gt;</Label>', $xml );
		$this->assertStringContainsString( '<AddAnotherText>Add &amp; "Another" &lt;Tag&gt;</AddAnotherText>', $xml );
	}

	/**
	 * @covers \PFPageSchemas::createFieldXMLFromForm
	 */
	public function testCreateFieldXMLFromForm_escapesSpecialCharacters() {
		$request = new FauxRequest( [
			'pf_input_type_0' => 'text',
			'pf_key_values_0' => 'size=20 & "max", mandatory',
			'pf_input_befo_0' => 'Before & "Field"',
			'pf_input_desc_0' => 'Desc & "Field"',
			'pf_input_desctool_0' => '1',
			'pf_input_finish_0' => '1',
		], true );
		$this->setRequest( $request );

		$xmlPerField = \PFPageSchemas::createFieldXMLFromForm();

		$this->assertArrayHasKey( '0', $xmlPerField );
		$xml = $xmlPerField['0'];
		$this->assertStringContainsString( '<InputType>text</InputType>', $xml );
		$this->assertStringContainsString( '<Parameter name="size">20 &amp; "max"</Parameter>', $xml );
		$this->assertStringContainsString( '<Parameter name="mandatory" />', $xml );
		$this->assertStringContainsString( '<TextBeforeField>Before &amp; "Field"</TextBeforeField>', $xml );
		$this->assertStringContainsString( '<Description>Desc &amp; "Field"</Description>', $xml );
		$this->assertStringContainsString( '<DescriptionTooltipMode>1</DescriptionTooltipMode>', $xml );
	}

	/**
	 * @covers \PFPageSchemas::createPageSectionXMLFromForm
	 */
	public function testCreatePageSectionXMLFromForm_escapesSpecialCharacters() {
		$request = new FauxRequest( [
			'pf_pagesection_key_values_0' => 'rows=10, label=Section & "Name"',
		], true );
		$this->setRequest( $request );

		$xmlPerPageSection = \PFPageSchemas::createPageSectionXMLFromForm();

		$this->assertArrayHasKey( '0', $xmlPerPageSection );
		$xml = $xmlPerPageSection['0'];
		$this->assertStringContainsString( '<pageforms_PageSection>', $xml );
		$this->assertStringContainsString( '<Parameter name="rows">10</Parameter>', $xml );
		$this->assertStringContainsString( '<Parameter name="label">Section &amp; "Name"</Parameter>', $xml );
		$this->assertStringContainsString( '</pageforms_PageSection>', $xml );
	}

	/**
	 * @covers \PFPageSchemas::createFormInputXMLFromForm
	 */
	public function testCreateFormInputXMLFromForm_escapesSpecialCharacters() {
		$input = 'size=20 & "max", mandatory, title=A & B <C>';
		$xml = \PFPageSchemas::createFormInputXMLFromForm( $input );

		$this->assertStringContainsString( '<Parameter name="size">20 &amp; "max"</Parameter>', $xml );
		$this->assertStringContainsString( '<Parameter name="mandatory" />', $xml );
		$this->assertStringContainsString( '<Parameter name="title">A &amp; B &lt;C&gt;</Parameter>', $xml );
	}
}
