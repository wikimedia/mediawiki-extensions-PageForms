<?php

/**
 * @covers \PFTokensInput
 * @group Database
 */
class PFTokensInputTest extends MediaWikiIntegrationTestCase {

	private static function callGetHTML(
		string $curValue = '',
		string $inputName = 'TestTemplate[Field]',
		array $otherArgs = []
	): string {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;
		$wgPageFormsTabIndex = $wgPageFormsTabIndex ?? 1;
		$wgPageFormsFieldNum = $wgPageFormsFieldNum ?? 1;

		return PFTokensInput::getHTML( $curValue, $inputName, false, false, $otherArgs );
	}

	/**
	 * @covers \PFTokensInput::getHTML
	 */
	public function testMappingTemplateAttributeIsRendered(): void {
		$html = self::callGetHTML( '', 'TestTemplate[Field]', [
			'mapping template' => 'MyMappingTemplate',
		] );

		$this->assertStringContainsString( 'mappingtemplate="MyMappingTemplate"', $html );
	}

	/**
	 * @covers \PFTokensInput::getHTML
	 */
	public function testMappingPropertyAttributeIsRendered(): void {
		$html = self::callGetHTML( '', 'TestTemplate[Field]', [
			'mapping property' => 'Has label',
		] );

		$this->assertStringContainsString( 'mappingproperty="Has label"', $html );
	}

	/**
	 * @covers \PFTokensInput::getHTML
	 */
	public function testMappingCargoAttributesAreRendered(): void {
		$html = self::callGetHTML( '', 'TestTemplate[Field]', [
			'mapping cargo table' => 'MyTable',
			'mapping cargo field' => 'LabelField',
		] );

		$this->assertStringContainsString( 'mappingcargotable="MyTable"', $html );
		$this->assertStringContainsString( 'mappingcargofield="LabelField"', $html );
	}

	/**
	 * @covers \PFTokensInput::getHTML
	 */
	public function testNoMappingAttributesWhenNotSpecified(): void {
		$html = self::callGetHTML();

		$this->assertStringNotContainsString( 'mappingtemplate', $html );
		$this->assertStringNotContainsString( 'mappingproperty', $html );
		$this->assertStringNotContainsString( 'mappingcargotable', $html );
		$this->assertStringNotContainsString( 'mappingcargofield', $html );
	}

	/**
	 * When $wgPageFormsUseDisplayTitle is true and no explicit mapping is
	 * set, getMappingType() returns 'displaytitle', triggering the mapped
	 * values path. The current values should still appear as selected
	 * <option> elements with the internal value. (T427758)
	 *
	 * @covers \PFTokensInput::getHTML
	 */
	public function testDisplayTitleMappingRendersCurrentValuesAsOptions(): void {
		$this->setMwGlobals( [ 'wgPageFormsUseDisplayTitle' => true ] );

		$html = self::callGetHTML( 'SomeValue', 'TestTemplate[Field]', [
			'possible_values' => [ 'SomeValue' ],
		] );

		$this->assertStringContainsString( '<option', $html );
		$this->assertStringContainsString( 'SomeValue', $html );
	}

	/**
	 * When display title is disabled and no mapping is configured, the
	 * unmapped code path should produce options where id and text are the
	 * same value. (T427758)
	 *
	 * @covers \PFTokensInput::getHTML
	 */
	public function testNoMappingRendersCurrentValuesAsIdenticalOptions(): void {
		$this->setMwGlobals( [ 'wgPageFormsUseDisplayTitle' => false ] );

		$html = self::callGetHTML( 'Alpha,Beta', 'TestTemplate[Field]', [] );

		$this->assertStringContainsString( 'Alpha', $html );
		$this->assertStringContainsString( 'Beta', $html );
	}

}
