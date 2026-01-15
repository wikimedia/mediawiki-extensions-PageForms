<?php

/**
 * @group Database
 * @covers PFFormRedLink
 */
class PFFormRedLinkTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers PFFormRedLink::run
	 */
	public function testFormRedLinkCreatesRedLinkForNonexistentPage(): void {
		// Test with a page that doesn't exist
		$nonexistentPageName = 'PFTestNonexistentPage_' . wfRandomString( 8 );
		$formName = 'TestForm';

		$result = $this->getServiceContainer()->getParser()->parse(
			"{{#formredlink:form=$formName|target=$nonexistentPageName}}",
			$this->getNonexistingTestPage()->getTitle(),
			ParserOptions::newFromAnon()
		);

		$html = $result->getRawText();

		// The link should have the "new" class, indicating a red/broken link
		$this->assertStringContainsString( 'class=', $html );
		$this->assertStringContainsString( 'class="new"', $html, 'Red link should have "new" class for nonexistent page' );

		// The link should point to the form edit page
		$this->assertStringContainsString( 'Special:FormEdit', $html );
		$this->assertStringContainsString( $formName, $html );
		$this->assertStringContainsString( $nonexistentPageName, $html );
	}
}
