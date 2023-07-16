<?php

/**
 * @group Database
 */
class PFFormLinkerTest extends MediaWikiIntegrationTestCase {
	/**
	 * @covers PFFormLinker::getDefaultForm
	 */
	public function testGetDefaultFormShouldReturnNullForNullTitle(): void {
		$form = PFFormLinker::getDefaultForm( null );

		$this->assertNull( $form );
	}

	/**
	 * @covers PFFormLinker::getDefaultForm
	 */
	public function testGetDefaultFormShouldReturnNullForNonexistentPage(): void {
		$title = $this->getNonexistingTestPage()->getTitle();
		$form = PFFormLinker::getDefaultForm( $title );

		$this->assertNull( $form );
	}

	/**
	 * @covers PFFormLinker::getDefaultForm
	 */
	public function testGetDefaultFormShouldReturnNullForExistingPageWithoutDefaultForm(): void {
		$title = $this->getExistingTestPage()->getTitle();
		$form = PFFormLinker::getDefaultForm( $title );

		$this->assertNull( $form );
	}

	/**
	 * @covers PFFormLinker::getDefaultForm
	 */
	public function testGetDefaultFormShouldReturnDefaultFormForParserFunction(): void {
		$page = $this->getNonexistingTestPage();
		$this->editPage( $page, '{{#default_form:TestForm}}' );

		$form = PFFormLinker::getDefaultForm( $page->getTitle() );

		$this->assertSame( 'TestForm', $form );
	}
}
