<?php

use MediaWiki\Title\Title;

/**
 * @group Database
 * @covers PFFormRedLink
 */
class PFFormRedLinkTest extends MediaWikiIntegrationTestCase {

	private function createPage( string $prefixedText, string $content = 'Page content' ): Title {
		$title = Title::newFromText( $prefixedText );
		$this->assertInstanceOf( Title::class, $title );

		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$wikiPage->doUserEditContent(
			\MediaWiki\Content\ContentHandler::makeContent( $content, $title ),
			self::getTestUser()->getUser(),
			'Create page for PFFormRedLink integration test',
			0,
			false
		);

		return $title;
	}

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

	/**
	 * Ensure that when the target page exists, the 'existing page link text'
	 * parameter which may contain wiki formatting is rendered as HTML
	 * (e.g. '''bold''' -> <b>bold</b>) and not shown as escaped tags.
	 */
	public function testFormRedLinkRendersFormattedExistingPageLinkTextWhenPageExists(): void {
		$existingTitle = $this->createPage( 'PFFormRedLinkExisting_' . wfRandomString( 8 ) );
		$formName = 'TestForm';
		$existingPageName = $existingTitle->getPrefixedText();

		$result = $this->getServiceContainer()->getParser()->parse(
			"{{#formredlink:form=$formName|target=$existingPageName|existing page link text='''bold''' ''italic''}}",
			$this->getNonexistingTestPage()->getTitle(),
			ParserOptions::newFromAnon()
		);

		$html = $result->getRawText();

		// Confirm we're in the "existing page" branch (i.e. not a red link).
		$this->assertStringNotContainsString( 'class="new"', $html );

		// The formatted markup should be rendered as HTML tags
		$this->assertStringContainsString( '<b>bold</b>', $html );
		$this->assertStringContainsString( '<i>italic</i>', $html );
		// And not the raw wiki markup
		$this->assertStringNotContainsString( "'''bold'''", $html );
	}
}
