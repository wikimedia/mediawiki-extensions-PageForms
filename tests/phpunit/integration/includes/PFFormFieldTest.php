<?php

use MediaWiki\Content\ContentHandler;
use MediaWiki\Title\Title;

/**
 * @group Database
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
			'wgPageFormsFieldProperties' => [],
			'wgPageFormsCargoFields' => [],
			'wgPageFormsUseDisplayTitle' => false,
			'wgCapitalLinks' => false,
			'wgTitle' => Title::newFromText( 'PFFormFieldTestPage' ),
			'wgPageFormsMaxAutocompleteValues' => 1000,
		] );
		\PFFormField::$mappedValuesCache = [];
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

	private function createPage( string $prefixedText, string $content = 'Page content' ): Title {
		$title = Title::newFromText( $prefixedText );
		$this->assertInstanceOf( Title::class, $title );

		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$wikiPage->doUserEditContent(
			ContentHandler::makeContent( $content, $title ),
			self::getTestUser()->getUser(),
			'Create page for PFFormField integration test',
			0,
			false
		);

		return $title;
	}

	public static function provideValueSourceParams() {
		return [
			'property' => [ 'values from property=Has status', false ],
			'wikidata' => [ 'values from wikidata=P31=Q5', false ],
			'query' => [ 'values from query=[[Category:Tracked]]', false ],
			'category' => [ 'values from category=tracked pages', true ],
			'concept' => [ 'values from concept=Tracked concept', true ],
			'namespace' => [ 'values from namespace=Help', true ],
		];
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

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagCreatesFallbackFieldWhenUnknownAndNotStrict() {
		$knownTemplateField = \PFTemplateField::create( 'Known field', null );

		$formField = $this->newFormFieldFromTags(
			'Flexible template',
			[ $knownTemplateField ],
			[ 'field', 'Unknown field' ]
		);

		$this->assertSame( 'Unknown field', $formField->getTemplateField()->getFieldName() );
		$this->assertSame( 'Flexible template[Unknown field]', $formField->getInputName() );
		$this->assertSame( ',', $formField->getFieldArg( 'delimiter' ) );
		$this->assertFalse( $formField->isDisabled() );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagHandlesSingleValueParams() {
		$templateField = \PFTemplateField::create( 'Body', null );

		$formField = $this->newFormFieldFromTags(
			'Article',
			[ $templateField ],
			[ 'field', 'Body', 'edittools', 'holds template', 'translatable' ]
		);

		$fieldArgs = $formField->getFieldArgs();
		$this->assertTrue( $fieldArgs['edittools'] );
		$this->assertTrue( $fieldArgs['holds template'] );
		$this->assertTrue( $fieldArgs['translatable'] );
		$this->assertTrue( $formField->isHidden() );
		$this->assertTrue( $formField->holdsTemplate() );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagHandlesPreloadFallback() {
		$this->createPage( 'PFFormFieldPreloadPage', 'Preloaded text<noinclude>hidden</noinclude><includeonly> shown</includeonly>' );
		$templateField = \PFTemplateField::create( 'Description', null );

		$formField = $this->newFormFieldFromTags(
			'Article',
			[ $templateField ],
			[ 'field', 'Description', 'preload=PFFormFieldPreloadPage' ]
		);

		$this->assertSame( 'PFFormFieldPreloadPage', $formField->getFieldArg( 'preload' ) );
		$this->assertSame(
			'Preloaded text shown',
			$formField->getCurrentValue( [], false, false, false )
		);
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagParsesShowOnSelect() {
		$templateField = \PFTemplateField::create( 'Status', null );

		$formField = $this->newFormFieldFromTags(
			'Issue',
			[ $templateField ],
			[
				'field',
				'Status',
				'show on select=Open=>status-open; Closed=&gt;status-closed; Other; ; Reopened=>status-open'
			]
		);

		$this->assertSame(
			[
				'status-open' => [ 'Open', 'Reopened' ],
				'status-closed' => [ 'Closed' ],
				'Other' => [],
			],
			$formField->getFieldArg( 'show on select' )
		);
	}

	/**
	 * @dataProvider provideValueSourceParams
	 * @covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagHandlesValueSourceParams( string $sourceParam, bool $usesDisplayTitle ) {
		$this->setMwGlobals( [ 'wgPageFormsUseDisplayTitle' => true ] );
		$templateField = \PFTemplateField::create( 'Status', null );
		[ $sourceKey, $sourceValue ] = array_map( 'trim', explode( '=', $sourceParam, 2 ) );
		if ( $usesDisplayTitle ) {
			$fieldArgs = [
				'input type' => 'combobox',
				$sourceKey => $sourceValue,
				'values' => 'Open,Closed',
				'delimiter' => ',',
			];
			\PFFormField::$mappedValuesCache[json_encode( $fieldArgs ) . 'displaytitle'] = [ 'Open', 'Closed' ];
		}

		$formField = $this->newFormFieldFromTags(
			'Issue',
			[ $templateField ],
			[ 'field', 'Status', 'input type=combobox', $sourceParam, 'values=Open,Closed' ]
		);

		$this->assertSame( $sourceValue, $formField->getFieldArg( $sourceKey ) );
		$this->assertSame( [ 'Open', 'Closed' ], $formField->getPossibleValues() );
		$this->assertSame( $usesDisplayTitle, $formField->getUseDisplayTitle() );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagCapitalizesCategoryValueSource() {
		$this->setMwGlobals( [
			'wgCapitalLinks' => true,
			'wgPageFormsUseDisplayTitle' => true,
		] );
		$this->createPage( 'PFFormFieldCategoryPage', '[[Category:Lowercase category]]' );
		$templateField = \PFTemplateField::create( 'Related page', null );

		$formField = $this->newFormFieldFromTags(
			'Article',
			[ $templateField ],
			[ 'field', 'Related page', 'values from category=lowercase category' ]
		);

		$this->assertSame( 'lowercase category', $formField->getFieldArg( 'values from category' ) );
		$this->assertContains( 'PFFormFieldCategoryPage', $formField->getPossibleValues() );
		$this->assertTrue( $formField->getUseDisplayTitle() );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagHandlesDependencyAndUniqueScopeParams() {
		global $wgPageFormsDependentFields;
		$templateField = \PFTemplateField::create( 'Status', null );

		$formField = $this->newFormFieldFromTags(
			'Issue',
			[ $templateField ],
			[
				'field',
				'Status',
				'values dependent on=ParentField',
				'unique for category=Tracked',
				'unique for namespace=Help',
				'unique for concept=KnownConcept'
			]
		);

		$fieldArgs = $formField->getFieldArgs();
		$this->assertSame( [ [ 'ParentField', 'Issue[Status]' ] ], $wgPageFormsDependentFields );
		$this->assertTrue( $fieldArgs['unique'] );
		$this->assertSame( 'Tracked', $fieldArgs['unique_for_category'] );
		$this->assertSame( 'Help', $fieldArgs['unique_for_namespace'] );
		$this->assertSame( 'KnownConcept', $fieldArgs['unique_for_concept'] );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagSubstitutesDefaultFilenameForRegularPage() {
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'Upload target' ) ] );
		$templateField = \PFTemplateField::create( 'File', null );

		$formField = $this->newFormFieldFromTags(
			'Image',
			[ $templateField ],
			[ 'field', 'File', 'default filename=File:<page name>.jpg' ]
		);

		$this->assertSame( 'File:Upload target.jpg', $formField->getFieldArg( 'default filename' ) );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagSubstitutesDefaultFilenameForSpecialFormEditTarget() {
		$this->setMwGlobals( [ 'wgTitle' => Title::newFromText( 'FormEdit/Image form/Target Page', NS_SPECIAL ) ] );
		$templateField = \PFTemplateField::create( 'File', null );

		$formField = $this->newFormFieldFromTags(
			'Image',
			[ $templateField ],
			[ 'field', 'File', 'default filename=File:<page name>.jpg' ]
		);

		$this->assertSame( 'File:Target Page.jpg', $formField->getFieldArg( 'default filename' ) );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagRegistersFormLevelSemanticPropertyWhenSMWIsAvailable() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'Semantic MediaWiki is not installed.' );
		}
		global $wgPageFormsFieldProperties;
		$templateField = \PFTemplateField::newFromParams( 'Status', [ 'property' => 'Original status' ] );

		$formField = $this->newFormFieldFromTags(
			'Issue',
			[ $templateField ],
			[ 'field', 'Status', 'property=Has status' ]
		);

		$this->assertSame( 'Has status', $formField->getTemplateField()->getSemanticProperty() );
		$this->assertSame( 'Has status', $wgPageFormsFieldProperties['Issue[Status]' ] );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagRegistersTemplateSemanticPropertyWhenSMWIsAvailable() {
		if ( !defined( 'SMW_VERSION' ) ) {
			$this->markTestSkipped( 'Semantic MediaWiki is not installed.' );
		}
		global $wgPageFormsFieldProperties;
		$templateField = \PFTemplateField::newFromParams( 'Status', [ 'property' => 'Has status' ] );

		$this->newFormFieldFromTags(
			'Issue',
			[ $templateField ],
			[ 'field', 'Status' ]
		);

		$this->assertSame( 'Has status', $wgPageFormsFieldProperties['Issue[Status]' ] );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagRegistersFormLevelCargoFieldWhenCargoIsAvailable() {
		if ( !defined( 'CARGO_VERSION' ) ) {
			$this->markTestSkipped( 'Cargo is not installed.' );
		}
		global $wgPageFormsCargoFields;
		$templateField = \PFTemplateField::create( 'Status', null );

		$formField = $this->newFormFieldFromTags(
			'Issue',
			[ $templateField ],
			[
				'field',
				'Status',
				'cargo table=Tasks',
				'cargo field=Status',
				'cargo where=Status="Open"',
				'values=Open,Closed'
			]
		);

		$this->assertSame( 'Status="Open"', $formField->getFieldArg( 'cargo where' ) );
		$this->assertSame( 'Tasks|Status', $formField->getTemplateField()->getFullCargoField() );
		$this->assertSame( 'Tasks|Status', $wgPageFormsCargoFields['Issue[Status]' ] );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagRegistersTemplateCargoFieldWhenCargoIsAvailable() {
		if ( !defined( 'CARGO_VERSION' ) ) {
			$this->markTestSkipped( 'Cargo is not installed.' );
		}
		global $wgPageFormsCargoFields;
		$templateField = \PFTemplateField::create( 'Status', null );
		$templateField->setCargoFieldData( 'Tasks', 'Status' );

		$this->newFormFieldFromTags(
			'Issue',
			[ $templateField ],
			[ 'field', 'Status', 'values=Open,Closed' ]
		);

		$this->assertSame( 'Tasks|Status', $wgPageFormsCargoFields['Issue[Status]' ] );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagPrefixesNamespaceValuesBeforeMappingAndUsesCache() {
		$this->setMwGlobals( [ 'wgPageFormsMaxAutocompleteValues' => 1 ] );
		$this->createPage( 'Help:PFFormFieldMappedHelpPage' );
		$templateField = \PFTemplateField::create( 'Related page', null );
		$fieldArgs = [
			'input type' => 'tokens',
			'values from namespace' => 'Help',
			'mapping using translate' => 'pf-form-field-test-',
			'delimiter' => ',',
		];
		$mappedValuesKey = json_encode( $fieldArgs ) . 'mapping using translate';
		\PFFormField::$mappedValuesCache[$mappedValuesKey] = [
			'Help:PFFormFieldMappedHelpPage' => 'Cached label',
		];

		$formField = $this->newFormFieldFromTags(
			'Article',
			[ $templateField ],
			[
				'field',
				'Related page',
				'input type=tokens',
				'values from namespace=Help',
				'mapping using translate=pf-form-field-test-'
			]
		);

		$this->assertSame(
			[ 'Help:PFFormFieldMappedHelpPage' => 'Cached label' ],
			$formField->getPossibleValues()
		);
		$this->assertTrue( $formField->getFieldArg( 'reverselookup' ) );
	}

	/*
	 *@covers \PFFormField::newFromFormFieldTag
	 */
	public function testNewFromFormFieldTagUsesFieldNameAsInputNameWhenTemplateNameIsEmpty() {
		$templateField = \PFTemplateField::create( 'Free text', null );

		$formField = $this->newFormFieldFromTags(
			'',
			[ $templateField ],
			[ 'field', 'Free text' ]
		);

		$this->assertSame( 'Free text', $formField->getInputName() );
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
