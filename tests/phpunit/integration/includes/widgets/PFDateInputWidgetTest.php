<?php

use OOUI\BlankTheme;

/**
 * @group PageForms, PF, PF_Form
 * @group \PFDateInputWidget
 * @author Collins Wandji <collinschuwa@gmail.com>
 */
class PFDateInputWidgetTest extends MediaWikiIntegrationTestCase {
	/**
	 * Set up the environment
	 */
	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );

		// Make sure the form is not in "disabled" state. Unfortunately setting up the global state
		// environment in a proper way to have PFFormPrinter work on a mock title object is very
		// difficult. Therefore we just override the permission check by using a hook.
		$hookContainer = $this->getServiceContainer()->getHookContainer();
		$hookContainer->register( 'PageForms::UserCanEditPage', static function ( $pageTitle, &$userCanEditPage ) {
			$userCanEditPage = true;
			return true;
		} );

		parent::setUp();
	}

	/**
	 * @covers \PFDateInputWidget::__construct
	 */
	public function testConstructor() {
		$widget = new \PFDateInputWidget( [
			'name' => 'test-date',
			'value' => '2023-10-27',
		] );

		$output = $widget->toString();

		$this->assertInstanceOf( \PFDateInputWidget::class, $widget );
		$this->assertMatchesRegularExpression( "/name=['\"]test-date['\"]/", $output );
		$this->assertMatchesRegularExpression( "/value=['\"]2023-10-27['\"]/", $output );
	}

	/**
	 * @covers \PFDateInputWidget::getConfig
	 */
	public function testGetConfig() {
		$config = [
			'name' => 'test-date',
			'value' => '2023-10-27',
			'format' => 'Y-m-d',
		];
		$widget = new \PFDateInputWidget( $config );
		$retrievedConfig = $widget->getConfig( $config );

		$this->assertArrayHasKey( 'name', $retrievedConfig );
		$this->assertEquals( 'test-date', $retrievedConfig['name'] );
		$this->assertEquals( '2023-10-27', $retrievedConfig['value'] );
	}
}
