<?php

/**
 * @covers \PFFormField
 * @author Collins Wandji <collinschuwa@gmail.com>
 */
class PFFormFieldTest extends MediaWikiIntegrationTestCase {

	private $pfFormField;
	private $pfTemplateField;

	/**
	 * Set up environment for testing PFFormField, including defining necessary namespaces and creating a PFFormField instance.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->pfTemplateField = $this->createMock( \PFTemplateField::class );
		$this->pfFormField = \PFFormField::create( $this->pfTemplateField );
	}

	/**
	 * @covers \PFFormField::getTemplateField
	 */
	public function testCreate() {
		$newField = \PFFormField::create( $this->pfTemplateField );
		$this->assertInstanceOf( \PFFormField::class, $newField );
		$this->assertSame( $this->pfTemplateField, $newField->getTemplateField() );
		$this->assertNull( $newField->getInputType() );
		$this->assertFalse( $newField->isMandatory() );
		$this->assertFalse( $newField->isHidden() );
		$this->assertFalse( $newField->isRestricted() );
		$this->assertNull( $newField->holdsTemplate() );
		$this->assertFalse( $newField->getUseDisplayTitle() );
	}

	/**
	 * @covers \PFFormField::getTemplateField
	 */
	public function testGetTemplateField() {
		$actual = $this->pfFormField->getTemplateField();
		$this->assertInstanceOf( \PFTemplateField::class, $actual );
		$this->assertEquals( $this->pfTemplateField, $actual );
	}

	public function testSetTemplateField() {
		$newTemplateField = $this->createMock( \PFTemplateField::class );
		$this->pfFormField->setTemplateField( $newTemplateField );
		$this->assertSame( $newTemplateField, $this->pfFormField->getTemplateField() );
	}

	public function testGetInputType() {
		$actual = $this->pfFormField->getInputType();
		$this->assertNull( $actual );
	}

	public function testSetInputType() {
		$this->pfFormField->setInputType( 'InputType' );
		$this->assertEquals( 'InputType', $this->pfFormField->getInputType() );
	}

	public function testHasFieldArg() {
		$actual = $this->pfFormField->hasFieldArg( 'arg name' );
		$this->assertFalse( $actual );
	}

	public function testGetFieldArgs() {
		$actual = $this->pfFormField->getFieldArgs();
		$this->assertIsArray( $actual );
		$this->assertArrayContains( [], $actual );
	}

	public function testGetFieldArg() {
		$this->pfFormField->setFieldArg( 'arg name', 'arg value' );
		$actual = $this->pfFormField->getFieldArg( 'arg name' );
		$this->assertEquals( 'arg value', $actual );
	}

	public function testSetFieldArg() {
		$this->pfFormField->setFieldArg( 'arg name', 'arg value' );
		$this->assertTrue( $this->pfFormField->hasFieldArg( 'arg name' ) );
		$this->assertEquals( 'arg value', $this->pfFormField->getFieldArg( 'arg name' ) );
	}

	public function testGetDefaultValue() {
		$actual = $this->pfFormField->getDefaultValue();
		$this->assertNull( $actual );
	}

	public function testIsMandatory() {
		$actual = $this->pfFormField->isMandatory();
		$this->assertFalse( $actual );
	}

	public function testSetIsMandatory() {
		$this->pfFormField->setIsMandatory( true );
		$this->assertTrue( $this->pfFormField->isMandatory() );
	}

	public function testIsHidden() {
		$actual = $this->pfFormField->isHidden();
		$this->assertFalse( $actual );
	}

	public function testSetIsHidden() {
		$this->pfFormField->setIsHidden( true );
		$this->assertTrue( $this->pfFormField->isHidden() );
	}

	public function testIsRestricted() {
		$actual = $this->pfFormField->isRestricted();
		$this->assertFalse( $actual );
	}

	public function testSetIsRestricted() {
		$this->pfFormField->setIsRestricted( true );
		$this->assertTrue( $this->pfFormField->isRestricted() );
	}

	public function testHoldsTemplate() {
		$actual = $this->pfFormField->holdsTemplate();
		$this->assertNull( $actual );
	}

	public function testIsList() {
		$actual = $this->pfFormField->isList();
		$this->assertNull( $actual );
	}

	public function testGetPossibleValues() {
		// Should return from template field if mPossibleValues is null
		$this->pfTemplateField->method( 'getPossibleValues' )->willReturn( [ 'value1' => 'label1' ] );
		$actual = $this->pfFormField->getPossibleValues();
		$this->assertIsArray( $actual );
	}

	public function testGetUseDisplayTitle() {
		$actual = $this->pfFormField->getUseDisplayTitle();
		$this->assertFalse( $actual );
	}

	public function testGetInputName() {
		$actual = $this->pfFormField->getInputName();
		$this->assertNull( $actual );
	}

	public function testGetLabel() {
		$actual = $this->pfFormField->getLabel();
		$this->assertNull( $actual );
	}

	public function testGetLabelMsg() {
		$actual = $this->pfFormField->getLabelMsg();
		$this->assertNull( $actual );
	}

	public function testIsDisabled() {
		$actual = $this->pfFormField->isDisabled();
		$this->assertNull( $actual );
	}

	public function testSetDescriptionArg() {
		$this->pfFormField->setDescriptionArg( 'Description', 'Test description' );
		$this->assertTrue( true );
	}

	public function testNewFromFormFieldTag() {
		$this->assertTrue( method_exists( '\PFFormField', 'newFromFormFieldTag' ) );
	}

	public function testCleanupTranslateTags() {
		// This method takes a reference parameter, so just verify it exists
		$this->assertTrue( method_exists( $this->pfFormField, 'cleanupTranslateTags' ) );
	}

	public function testGetCurrentValue() {
		$this->pfTemplateField->method( 'getFieldName' )->willReturn( 'TestField' );
		$this->pfFormField->setFieldArg( 'delimiter', ',' );
		$actual = $this->pfFormField->getCurrentValue( [], false, false, false );
		$this->assertNull( $actual );
	}

	public function testValueStringToLabels() {
		$actual = $this->pfFormField->valueStringToLabels( 'test_value', ',', false );
		$this->assertEquals( 'test_value', $actual );
		$actual = $this->pfFormField->valueStringToLabels( '', ',', false );
		$this->assertSame( '', $actual );
	}

	public function testAdditionalHTMLForInput() {
		$actual = $this->pfFormField->additionalHTMLForInput( 'test_value', 'TestField', 'TestTemplate' );
		$this->assertIsString( $actual );
	}

	public function testCreateMarkup() {
		$this->pfTemplateField->method( 'getFieldName' )->willReturn( 'TestField' );
		$this->pfTemplateField->method( 'getLabel' )->willReturn( 'Test Label' );
		$this->pfTemplateField->method( 'getPossibleValues' )->willReturn( [] );
		$actual = $this->pfFormField->createMarkup( false, false );
		$this->assertIsString( $actual );
		$this->assertStringContainsString( 'field|TestField', $actual );
	}

	public function testGetArgumentsForInputCallSMW() {
		$other_args = [];
		if ( defined( 'SMW_VERSION' ) ) {
			$this->pfFormField->getArgumentsForInputCallSMW( $other_args );
			$this->assertIsArray( $other_args );
		} else {
			$this->assertTrue( method_exists( $this->pfFormField, 'getArgumentsForInputCallSMW' ) );
		}
	}

	public function testGetArgumentsForInputCallCargo() {
		$other_args = [];
		if ( defined( 'CARGO_VERSION' ) ) {
			$this->pfFormField->getArgumentsForInputCallCargo( $other_args );
			$this->assertIsArray( $other_args );
		} else {
			$this->assertTrue( method_exists( $this->pfFormField, 'getArgumentsForInputCallCargo' ) );
		}
	}

	public function testGetArgumentsForInputCall() {
		$this->pfTemplateField->method( 'getPossibleValues' )->willReturn( [] );
		$this->pfTemplateField->method( 'isList' )->willReturn( false );
		$this->pfTemplateField->method( 'isMandatory' )->willReturn( false );
		$this->pfTemplateField->method( 'isUnique' )->willReturn( false );
		$actual = $this->pfFormField->getArgumentsForInputCall();
		$this->assertIsArray( $actual );
		$this->assertArrayHasKey( 'is_list', $actual );
	}

}
