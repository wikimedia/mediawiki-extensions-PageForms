<?php

if ( !class_exists( 'MediaWikiIntegrationTestCase' ) ) {
	class_alias( 'MediaWikiTestCase', 'MediaWikiIntegrationTestCase' );
}

/**
 * @covers \PFForm
 *
 * @author Wandji Collins
 */
class PFFormTest extends MediaWikiIntegrationTestCase {
	private $pfForm;

	/**
	 * Set up environment
	 */
	public function setUp(): void {
		$this->pfForm = new PFForm();
		parent::setUp();
	}

	/**
	 * @covers PFForm::getFormName
	 */
	public function testGetFormName() {
		$actual = $this->pfForm->getFormName();
		$this->assertTrue( (bool)equalTo( $actual ) );
		$this->assertEquals( $actual, $this->pfForm->getFormName() );
	}

	/**
	 * @covers PFForm::getItems
	 */
	public function testGetItems() {
		$actual = $this->pfForm->getItems();
		$this->assertTrue( (bool)equalTo( $actual ) );
		$this->assertEquals( $actual, $this->pfForm->getItems() );
	}
}
