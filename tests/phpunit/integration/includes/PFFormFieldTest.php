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
		$this->setMwGlobals( [
			'wgPageFormsDependentFields' => [],
			'wgPageFormsEmbeddedTemplates' => [],
			'wgPageFormsUseDisplayTitle' => false,
		] );
	}

	/**
	 * Create a real PFFormField from the same collaborators used by form parsing.
	 *
	 * @param string $templateName
	 * @param PFTemplateField[] $templateFields
	 * @param string[] $fieldTagComponents
	 * @param string[] $templateTagOptions
	 * @param bool $formIsDisabled
	 * @param User|null $user
	 * @return PFFormField
	 */
	private function newFormFieldFromTags(
		$templateName,
		array $templateFields,
		array $fieldTagComponents,
		array $templateTagOptions = [],
		$formIsDisabled = false,
		?\User $user = null
	) {
		$template = new \PFTemplate( $templateName, $templateFields );
		$templateInForm = \PFTemplateInForm::create(
			$templateName,
			null,
			in_array( 'multiple', $templateTagOptions ),
			null,
			[]
		);
		if ( in_array( 'strict', $templateTagOptions ) ) {
			$strictParsing = new \ReflectionProperty( \PFTemplateInForm::class, 'mStrictParsing' );
			$strictParsing->setValue( $templateInForm, true );
		}

		\PFUtils::getParser()->startExternalParse(
			null,
			\ParserOptions::newFromAnon(),
			\Parser::OT_WIKI
		);

		return \PFFormField::newFromFormFieldTag(
			$fieldTagComponents,
			$template,
			$templateInForm,
			$formIsDisabled,
			$user ?: $this->newUserStub()
		);
	}

	private function newUserStub( $canEditRestrictedFields = true ): \User {
		$user = $this->createMock( \User::class );
		$user->method( 'isAllowed' )
			->willReturnCallback( static function ( string $right ) use ( $canEditRestrictedFields ) {
				return $right === 'editrestrictedfields' ? $canEditRestrictedFields : true;
			} );
		return $user;
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
		$this->assertNotNull( $actual );
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

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTag() {
		$templateField = \PFTemplateField::create( 'Status', 'Status label' );

		$formField = $this->newFormFieldFromTags(
			'Issue',
			[ $templateField ],
			[
				'field',
				'Status',
				'mandatory',
				'hidden',
				'list',
				'unique',
				'input type=checkboxes',
				'default=Open',
				'label=Current status',
				'label msg=pf-status-message',
				'delimiter=;',
				'values=Open;Closed;Blocked',
			]
		);

		$this->assertInstanceOf( \PFFormField::class, $formField );
		$this->assertSame( $templateField, $formField->getTemplateField() );
		$this->assertSame( 'checkboxes', $formField->getInputType() );
		$this->assertSame( 'Open', $formField->getDefaultValue() );
		$this->assertSame( 'Current status', $formField->getLabel() );
		$this->assertSame( 'pf-status-message', $formField->getLabelMsg() );
		$this->assertTrue( $formField->isMandatory() );
		$this->assertTrue( $formField->isHidden() );
		$this->assertTrue( $formField->isList() );
		$this->assertNull( $formField->isRestricted() );
		$this->assertFalse( $formField->isDisabled() );
		$this->assertSame( 'Issue[Status]', $formField->getInputName() );
		$this->assertSame( [ 'Open', 'Closed', 'Blocked' ], $formField->getPossibleValues() );

		$fieldArgs = $formField->getFieldArgs();
		$this->assertTrue( $fieldArgs['unique'] );
		$this->assertSame( ';', $fieldArgs['delimiter'] );
		$this->assertSame( 'checkboxes', $fieldArgs['input type'] );
		$this->assertFalse( $formField->getUseDisplayTitle() );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagUsesTemplateFieldDefaults() {
		global $wgPageFormsEmbeddedTemplates;

		$templateField = \PFTemplateField::newFromParams( 'Related page', [
			'category' => 'Tracked pages',
			'namespace' => 'Help',
			'delimiter' => '|',
			'holds template' => 'Embedded template',
		] );
		$templateField->setPossibleValues( [ 'Alpha', 'Beta' ] );

		$formField = $this->newFormFieldFromTags(
			'Container',
			[ $templateField ],
			[ 'field', 'Related page' ]
		);

		$this->assertSame( $templateField, $formField->getTemplateField() );
		$this->assertTrue( $formField->isHidden() );
		$this->assertTrue( $formField->holdsTemplate() );
		$this->assertTrue( $formField->isList() );
		$this->assertSame( 'Container[Related page]', $formField->getInputName() );
		$this->assertSame( [ 'Alpha', 'Beta' ], $formField->getPossibleValues() );

		$fieldArgs = $formField->getFieldArgs();
		$this->assertSame( 'Tracked pages', $fieldArgs['values from category'] );
		$this->assertSame( 'Help', $fieldArgs['values from namespace'] );
		$this->assertSame( '|', $fieldArgs['delimiter'] );
		$this->assertSame( [ 'Container', 'Related page' ], $wgPageFormsEmbeddedTemplates['Embedded template'] );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagMarksMultipleTemplateFields() {
		$templateField = \PFTemplateField::create( 'Step', null );

		$formField = $this->newFormFieldFromTags(
			'Workflow',
			[ $templateField ],
			[ 'field', 'Step' ],
			[ 'multiple' ]
		);

		$this->assertSame( 'Workflow[num][Step]', $formField->getInputName() );
		$this->assertTrue( $formField->getFieldArg( 'part_of_multiple' ) );
		$this->assertSame( 'Workflow[Step]', $formField->getFieldArg( 'origName' ) );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagSkipsUnknownFieldsDuringStrictParsing() {
		$knownTemplateField = \PFTemplateField::create( 'Known field', null );

		$formField = $this->newFormFieldFromTags(
			'Strict template',
			[ $knownTemplateField ],
			[ 'field', 'Unknown field', 'mandatory' ],
			[ 'strict' ]
		);

		$this->assertInstanceOf( \PFFormField::class, $formField );
		$this->assertNotSame( $knownTemplateField, $formField->getTemplateField() );
		$this->assertNull( $formField->getTemplateField()->getFieldName() );
		$this->assertFalse( $formField->isList() );
		$this->assertNull( $formField->isMandatory() );
		$this->assertNull( $formField->getInputName() );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagHandlesRestrictionAndDisabledState() {
		$templateField = \PFTemplateField::create( 'Private note', null );
		$user = $this->newUserStub( false );

		$restrictedField = $this->newFormFieldFromTags(
			'Access controlled',
			[ $templateField ],
			[ 'field', 'Private note', 'restricted' ],
			[],
			false,
			$user
		);

		$this->assertTrue( $restrictedField->isRestricted() );
		$this->assertTrue( $restrictedField->isDisabled() );

		$disabledField = $this->newFormFieldFromTags(
			'Access controlled',
			[ $templateField ],
			[ 'field', 'Private note' ],
			[],
			true,
			$user
		);

		$this->assertNull( $disabledField->isRestricted() );
		$this->assertTrue( $disabledField->isDisabled() );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagAllowsRestrictedFieldsForPermittedGroups() {
		$templateField = \PFTemplateField::create( 'Private note', null );
		$sysop = $this->newUserStub();
		$userGroupManager = $this->createMock( \MediaWiki\User\UserGroupManager::class );
		$userGroupManager->method( 'getUserEffectiveGroups' )
			->with( $sysop )
			->willReturn( [ 'user', 'sysop' ] );
		$this->setService( 'UserGroupManager', $userGroupManager );

		$formField = $this->newFormFieldFromTags(
			'Access controlled',
			[ $templateField ],
			[ 'field', 'Private note', 'restricted=sysop' ],
			[],
			false,
			$sysop
		);

		$this->assertFalse( $formField->isRestricted() );
		$this->assertFalse( $formField->isDisabled() );
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
