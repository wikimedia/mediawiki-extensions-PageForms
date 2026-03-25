<?php

/**
 * @group Database
 */
class PFPageNameFormulaTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'UseCdn', false );
		$this->overrideConfigValue( 'UseSquid', false );
	}

	/**
	 * @covers \PFHooks::renamePageBasedOnFormula
	 */
	public function testPageNameFormula() {
		// Create the page with a formula that should rename it.
		// We use a dummy template and field that the formula will extract.
		$oldTitleText = 'Old_Page_Name';
		$text = "{{TestTemplate|TestField=NewName}}\n{{#page_name_formula:Prefix <TestTemplate[TestField]> Suffix}}";

		$status = $this->editPage( $oldTitleText, $text );
		$this->assertTrue( $status->isGood(), 'Page edit should be successful.' );

		// The hook onPageSaveCompleteFormula should have renamed the page.
		// The formula generated name should be "Prefix NewName Suffix".
		$newTitle = \MediaWiki\Title\Title::newFromText( 'Prefix NewName Suffix' );

		$this->assertTrue( $newTitle->exists(), 'The new page should exist after renaming.' );

		// The old page should be a redirect to the new page.
		$oldTitle = \MediaWiki\Title\Title::newFromText( $oldTitleText );
		$oldPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $oldTitle );
		$this->assertTrue( $oldPage->isRedirect(), 'The old page should be a redirect.' );
		$this->assertEquals( $newTitle->getPrefixedText(), $oldPage->getRedirectTarget()->getPrefixedText(), 'The redirect should point to the new title.' );
	}

	/**
	 * @covers \PFHooks::renamePageBasedOnFormula
	 */
	public function testPageNameFormulaUniqueNumber() {
		$oldTitleText = 'Old_Page_Unique';
		// The formula contains a unique number tag
		$text = "{{TestTemplate|TestField=UniqueTest}}\n{{#page_name_formula:<TestTemplate[TestField]>_<unique number start=5>}}";

		$status = $this->editPage( $oldTitleText, $text );
		$this->assertTrue( $status->isGood(), 'Page edit should be successful.' );

		// The hook renamePageBasedOnFormula should have renamed the page.
		// Since it's the first one, it should use the start value 5.
		$newTitle = \MediaWiki\Title\Title::newFromText( 'UniqueTest 5' );

		$this->assertTrue( $newTitle->exists(), 'The new page UniqueTest_5 should exist after renaming.' );

		// Create another page with the exact same formula, it should be renamed to UniqueTest_6.
		$oldTitleText2 = 'Old_Page_Unique_2';
		$status2 = $this->editPage( $oldTitleText2, $text );
		$this->assertTrue( $status2->isGood(), 'Second page edit should be successful.' );

		$newTitle2 = \MediaWiki\Title\Title::newFromText( 'UniqueTest 6' );
		$this->assertTrue( $newTitle2->exists(), 'The second page UniqueTest_6 should exist after renaming.' );
	}
}
