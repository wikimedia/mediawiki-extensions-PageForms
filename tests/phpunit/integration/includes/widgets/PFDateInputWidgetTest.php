<?php

use OOUI\BlankTheme;

/**
 * @covers \PFDateInputWidget
 * @group PageForms
 * @group PF
 * @group PF_Form
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
	 * @dataProvider widgetRenderDataProvider
	 */
	public function testWidgetRenderingIntegration( array $setup, array $expected ): void {
		$widget = new \PFDateInputWidget( $setup['config'] );
		$widgetHtml = $widget->toString();
		$inputElement = $widget->getInputElement( $setup['config'] );
		$inputHtml = $inputElement->toString();

		$this->assertInstanceOf( \PFDateInputWidget::class, $widget );
		$this->assertInstanceOf( \OOUI\Tag::class, $inputElement );

		$this->assertMatchesRegularExpression( $expected['widget_name_regex'], $widgetHtml );
		$this->assertMatchesRegularExpression( $expected['widget_value_regex'], $widgetHtml );
		$this->assertMatchesRegularExpression( $expected['input_type_regex'], $inputHtml );
	}

	/**
	 * @covers \PFDateInputWidget::getConfig
	 */
	public function testGetConfig(): void {
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

	/**
	 * @covers \PFDateInputWidget::getInputElement
	 */
	public function testGetInputElementFallsBackToTextForMultipleInputFormats(): void {
		$config = [
			'name' => 'test-date',
			'value' => '2023-10-27',
			'precision' => 'day',
			'inputFormat' => 'Y-m-d;d/m/Y',
		];
		$widget = new \PFDateInputWidget( $config );
		$inputHtml = $widget->getInputElement( $config )->toString();

		$this->assertMatchesRegularExpression( "/type=['\"]text['\"]/", $inputHtml );
	}

	/**
	 * @covers \PFDateInputWidget::getJavaScriptClassName
	 */
	public function testGetJavaScriptClassName(): void {
		$widget = new class( [ 'name' => 'test-date', 'value' => '2023-10-27', 'precision' => 'day' ] )
			extends \PFDateInputWidget {
			public function getPublicJavaScriptClassName(): string {
				return $this->getJavaScriptClassName();
			}
		};

		$this->assertSame( 'mw.widgets.PFDateInputWidget', $widget->getPublicJavaScriptClassName() );
	}

	public static function widgetRenderDataProvider(): array {
		return [
			'single format keeps native input type' => [
				[
					'config' => [
						'name' => 'test-date',
						'value' => '2023-10-27',
						'precision' => 'day',
						'inputFormat' => 'Y-m-d',
					],
				],
				[
					'widget_name_regex' => "/name=['\"]test-date['\"]/i",
					'widget_value_regex' => "/value=['\"]2023-10-27['\"]/i",
					'input_type_regex' => "/type=['\"]date['\"]/i",
				],
			],
			'multiple formats use text input type' => [
				[
					'config' => [
						'name' => 'test-date-multi',
						'value' => '2023-10-27',
						'precision' => 'day',
						'inputFormat' => 'Y-m-d;d/m/Y',
					],
				],
				[
					'widget_name_regex' => "/name=['\"]test-date-multi['\"]/i",
					'widget_value_regex' => "/value=['\"]2023-10-27['\"]/i",
					'input_type_regex' => "/type=['\"]text['\"]/i",
				],
			],
		];
	}
}
