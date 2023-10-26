<?php

use OOUI\BlankTheme;

/**
 * @covers \PFRadioButtonInput
 * @group Database
 * @author Mark A. Hershberger <mah@nichework.com>
 */
class PFRadioButtonInputTest extends MediaWikiIntegrationTestCase {

	/**
	 * Set up the environment
	 */
	protected function setUp(): void {
		\OOUI\Theme::setSingleton( new BlankTheme() );

		parent::setUp();
	}

	private static function radioButtonFormat(
		$name, $value, $label = null, $checked = null, $class = null,
		$append = null, $disabled = null
	) {
		return "\t" . sprintf(
			'<label class="radioButtonItem%s">'
			. '<input id="input_\d+" tabindex="\d+"%s%s%s type="radio" value="%s" '
			. 'name="TestTemplate123\[%s\]">&nbsp;%s</label>',
			( $class !== null ? " $class" : '' ),
			( $append !== null ? " $append" : '' ),
			( $disabled !== null ? ' disabled=""' : '' ),
			( $checked !== null ? ' checked=""' : '' ),
			$value, $name,
			( $label !== null ? $label : $value )
		) . "\n";
	}

	// Tests for the radiobutton code.

	/**
	 * @dataProvider radioButtonDataProvider
	 */
	public function testRadioButtons( $setup, $expected ) {
		$args = $setup['args'];
		$args[1] = "TestTemplate123[{$args[1]}]";
		$result = call_user_func_array(
			[ 'PFRadioButtonInput', 'getHTML' ], $args
		);

		$this->assertMatchesRegularExpression(
			'#' . $expected['expected_html'] . '#',
			$result,
			'asserts that getHTML() returns the correct HTML text'
		);
	}

	/**
	 * Data provider method
	 */
	public static function radioButtonDataProvider() {
		$provider = [];
		$label = "froot_loops";

		// data set #0 radiobutton definition without other parameters
		//
		// FIXME: This seems like it shouldn't be possible
		$provider[] = [ [
			'args' => [ 999, $label, false, false, [] ],
		], [
			'expected_html' => '<span id="span_\d+" '
			. 'class="radioButtonSpan">' . "\n"

			. self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )

			. "</span>"
		] ];

		// data set #1 form definition with only 'property_type' => '_boo'
		$provider[] = [ [
			'args' => [ 999, $label, false, false, [
				'property_type' => '_boo'
			] ] ], [
				'expected_html' => '<span id="span_\d+" '
				. 'class="radioButtonSpan">' . "\n"

				. self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )
				. self::radioButtonFormat( $label, 'Yes' )
				. self::radioButtonFormat( $label, 'No' )

				. "</span>"
			] ];

		// data set #2 form definition with only 'possible_values' =>
		// [ 'one', 'deux', 'drei' ]
		$provider[] = [ [
			'args' => [ 999, $label, false, false, [
				'possible_values' => [ 'one', 'deux', 'drei' ]
			] ] ], [
				'expected_html' => '<span id="span_\d+" '
				. 'class="radioButtonSpan">' . "\n"

				. self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )
				. self::radioButtonFormat( $label, 'one' )
				. self::radioButtonFormat( $label, 'deux' )
				. self::radioButtonFormat( $label, 'drei' )

				. "</span>"
				] ];

		// data set #3 - if this is a mandatory field,
		// make sure mandatoryFieldSpan class is added.
		$provider[] = [ [
			'args' => [
				999, $label, true, false, [
					'possible_values' => [ 'one', 'deux', 'drei' ]
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

					. self::radioButtonFormat( $label, 'one' )
					. self::radioButtonFormat( $label, 'deux' )
					. self::radioButtonFormat( $label, 'drei' )

					. "</span>"
				] ];

		// data set #4
		$provider[] = [ [
			'args' => [
				'drei', $label, true, false, [
					'possible_values' => [ 'one', 'deux', 'drei' ]
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

					. self::radioButtonFormat( $label, 'one' )
					. self::radioButtonFormat( $label, 'deux' )
					. self::radioButtonFormat(
						$label, 'drei', null, "checked"
					)

					. "</span>"
				] ];

		// data set #5 - if null is the current value provided on a
		// mandatory field, none of the options should be selected.
		$provider[] = [ [
			'args' => [
				null, $label, true, false, [
					'possible_values' => [ 'one', 'deux', 'drei' ]
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

					. self::radioButtonFormat( $label, 'one' )
					. self::radioButtonFormat( $label, 'deux' )
					. self::radioButtonFormat( $label, 'drei' )

					. "</span>"
				] ];

		// data set #6 - if null is the current value provided on a
		// non-mandatory field, make sure 'None/unknown' is selected.
		$provider[] = [ [
			'args' => [
				null, $label, false, false, [
					'possible_values' => [ 'one', 'deux', 'drei' ]
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan">' . "\n"

					. self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )
					. self::radioButtonFormat( $label, 'one' )
					. self::radioButtonFormat( $label, 'deux' )
					. self::radioButtonFormat( $label, 'drei' )

					. "</span>"
				] ];

		// data set #7 - if null is the current value provided on a
		// _boo, make sure 'None/unknown' is available.
		$provider[] = [ [
			'args' => [
				null, $label, false, false, [
					'property_type' => '_boo'
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan">' . "\n"

					. self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )
					. self::radioButtonFormat( $label, 'Yes' )
					. self::radioButtonFormat( $label, 'No' )

					. "</span>",
				] ];

		// data set #8 Add CSS class(es)
		$provider[] = [ [
			'args' => [
				999, $label, false, false, [
					"possible_values" => [ "one", "deux", "drei" ],
					'class' => 'testME'
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan testME">' . "\n"

					. self::radioButtonFormat( $label, '', 'None/unknown', 'checked', 'testME' )
					. self::radioButtonFormat( $label, 'one', null, null, 'testME' )
					. self::radioButtonFormat( $label, 'deux', null, null, 'testME' )
					. self::radioButtonFormat( $label, 'drei', null, null, 'testME' )

					. "</span>"
				] ];

		// data set #9 origName attribute
		$provider[] = [ [
			'args' => [
				999, $label, false, false, [
					'property_type' => '_boo',
					'origName' => 'testME'
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan">' . "\n"

					. self::radioButtonFormat(
						$label, '', 'None/unknown', 'checked', null, 'origname="testME"'
					)
					. self::radioButtonFormat(
						$label, 'Yes', null, null, null, 'origname="testME"'
					)
					. self::radioButtonFormat(
						$label, 'No', null, null, null, 'origname="testME"'
					)

					. "</span>"
				] ];

		// data set #10 is_disabled is true
		// FIXME: I can see an argument for using None here, but,
		// still seems wonky.
		$provider[] = [ [
			'args' => [
				999, $label, false, true, [
					'possible_values' => [ 'Yes', 'No' ],
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan">' . "\n"

					. self::radioButtonFormat(
						$label, '', 'None/unknown', 'checked', null, null, true
					)
					. self::radioButtonFormat(
						$label, 'Yes', null, null, null, null, true
					)
					. self::radioButtonFormat(
						$label, 'No', null, null, null, null, true
					)

					. "</span>"
				] ];

		// data set #11 is_disabled is true and is_mandatory is true
		// FIXME: shouldn't this fail instead of forcing true?
		$provider[] = [ [
			'args' => [
				999, $label, true, true, [
					'property_type' => '_boo',
					'origName' => 'testME'
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

					. self::radioButtonFormat(
						$label, 'Yes', null, null, null,
						'origname="testME"', true
					)
					. self::radioButtonFormat(
						$label, 'No', null, null, null, 'origname="testME"',
						true
					)

					. "</span>"
				] ];

		// data set #12 - Ensure value_labels are used when provided.
		$provider[] = [ [
			'args' => [
				null, $label, true, false, [
					'possible_values' => [ 'one', 'deux', 'drei' ],
					'value_labels' => [
						'deux' => 'two', 'drei' => 'three'
					] ] ] ], [
						'expected_html' => '<span id="span_\d+" '
						. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

						. self::radioButtonFormat( $label, 'one' )
						. self::radioButtonFormat( $label, 'deux', 'two' )
						. self::radioButtonFormat( $label, 'drei', 'three' )

						. "</span>"
					] ];

		// data set #13 - Ensure value_labels are escaped properly
		$provider[] = [ [
			'args' => [
				null, $label, true, false, [
					'possible_values' => [ 'one', 'deux', 'drei' ],
					'value_labels' => [
						'deux' => '&2&', 'drei' => '<3>'
					] ] ] ], [
						'expected_html' => '<span id="span_\d+" '
						. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

						. self::radioButtonFormat( $label, 'one' )
						. self::radioButtonFormat( $label, 'deux', '&amp;2&amp;' )
						. self::radioButtonFormat( $label, 'drei', '&lt;3&gt;' )

						. "</span>"
					] ];

		// data set #14 - Show on Select handling
		// FIXME: We're only dealing with the CSS class here
		$provider[] = [ [
			'args' => [
				null, $label, true, false, [
					'show on select' => [],
					'property_type' => '_boo'
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan '
					. 'pfShowIfChecked">' . "\n"

					. self::radioButtonFormat( $label, 'Yes' )
					. self::radioButtonFormat( $label, 'No' )

					. "</span>"
				] ];

		// data set #15 - mandatory boolean with default value
		$provider[] = [ [
			'args' => [
				'No', $label, true, false, [
					'property_type' => '_boo'
				] ] ], [
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">'
					. "\n"

					. self::radioButtonFormat( $label, 'Yes' )
					. self::radioButtonFormat( $label, 'No', null, 'checked' )

					. "</span>"
				] ];

		return $provider;
	}

	/**
	 * @dataProvider radioButtomFromWikitextDataProvider
	 */
	public function testRadioButtonsFromWikitext( $setup, $expected ) {
		if ( !isset( $expected['skip'] ) ) {
			global $wgPageFormsFormPrinter, $wgOut;

			$wgOut->getContext()->setTitle( $this->getTitle() );

			if ( isset( $setup['form_definition'] ) ) {
				// We have to specify a template name
				$form_definition = "{{{for template|TestTemplate123}}}\n{$setup['form_definition']}\n{{{end template}}}\n{{{standard input|save}}}";
				list( $form_text, $page_text, $form_page_title, $generated_page_name )
					= $wgPageFormsFormPrinter->formHTML(
						$form_definition, true, false, null, null,
						'TestStringForFormPageTitle', null,
						PFFormPrinter::CONTEXT_REGULAR, [], self::getTestUser()->getUser()
					);
			} else {
				$this->markTestSkipped( "No form to test!" );
				return;
			}

			if ( isset( $expected['expected_form_text'] ) ) {
				$this->assertMatchesRegularExpression(
					'#' . $expected['expected_form_text'] . '#',
					$form_text,
					'asserts that formHTML() returns the correct HTML text for the form'
				);
			}
			if ( isset( $expected['expected_page_text'] ) ) {
				$this->assertMatchesRegularExpression(
					'#' . $expected['expected_page_text'] . '#',
					$page_text,
					'assert that formHTML() returns the correct text for the page created'
				);
			}
			if (
				!isset( $expected['expected_form_text'] ) &&
				!isset( $expected['expected_page_text'] )
			) {
				$this->markTestSkipped( "No results to check!" );
			}
		} else {
			$this->markTestSkipped( "Skipping: $expected[skip]" );
		}
	}

	/**
	 * Data provider method
	 */
	public static function radioButtomFromWikitextDataProvider() {
		$provider = [];

		$label = "field_radiobutton";

		/**
		 * data set #0 radiobutton definition without other parameters
		 *
		 * FIXME: This seems like it shouldn't be possible
		 */
		$provider[] = [ [
			'form_definition' => "{{{field|$label|input type=radiobutton}}}"
		], [
			'expected_form_text' => self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )
		] ];

		/**
		 * data set #1 form definition with only 'property_type' => '_boo'
		 */
		$provider[] = [ [
		], [
			'expected_form_test' =>
			self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )
			. self::radioButtonFormat( $label, 'Yes' )
			. self::radioButtonFormat( $label, 'No' ),
			'skip' => 'How to do SMW?'
		] ];

		/**
		 * data set #2 form definition with only 'possible_values' =>
		 *             [ 'one', 'deux', 'drei' ]
		 */
		$provider[] = [ [
			'form_definition' => "{{{field|$label| input type=radiobutton| values=one, "
			. "deux, drei}}}"
		], [
			'expected_form_test' =>
			self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )
			. self::radioButtonFormat( $label, 'one' )
			. self::radioButtonFormat( $label, 'deux' )
			. self::radioButtonFormat( $label, 'drei' )
		] ];

		/**
		 * data set #3 - mandatory field
		 */
		$provider[] = [ [
			'form_definition' => "{{{field|$label| input type=radiobutton| values=one, "
			. "deux, drei|mandatory}}}"
		], [
			'expected_form_text' => self::radioButtonFormat(
				$label, 'one', null, 'checked'
			)
			. self::radioButtonFormat( $label, 'deux' )
			. self::radioButtonFormat( $label, 'drei' ),
			'skip' => 'Mandatory does not seem to work, see also #16 in this set'
		] ];

		/**
		 * data set #4 - mandatory field with default value
		 */
		$provider[] = [ [
			'form_definition' => "{{{field|$label| input type=radiobutton| values=one, "
			. "deux, drei|mandatory|default=drei}}}"
		], [
			'expected_form_text'  => '<span id="span_\d+" '
			. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

			. self::radioButtonFormat( $label, 'one' )
			. self::radioButtonFormat( $label, 'deux' )
			. self::radioButtonFormat(
				$label, 'drei', null, "checked"
			)

			. "</span>",
			'skip' => "This should pass, but doesn't see 'code, default value' above"
		] ];

		/*
		 * data set #5 - if null is the current value provided on a
		 *               mandatory field, make sure nothing is selected
		 */
		$provider[] = [ [
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|mandatory}}}"
		], [
			'expected_form_text' => self::radioButtonFormat( $label, 'one' )
			. self::radioButtonFormat( $label, 'deux' )
			. self::radioButtonFormat( $label, 'drei' ),
			'skip' => 'Works with parameters (see above), but wikitext parsing fails'
		] ];

		/**
		 * data set #6 - if null is the current value provided on a
		 *               non-mandatory field, make sure 'None/unknown' is
		 *               selected.
		 */
		$provider[] = [ [
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei}}}"
		], [
			'expected_form_text' => self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )
			. self::radioButtonFormat( $label, 'one' )
			. self::radioButtonFormat( $label, 'deux' )
			. self::radioButtonFormat( $label, 'drei' )
		] ];

		/**
		 * data set #7 - if null is the current value provided on a
		 *               mandatory boolean field, make sure 'None/unknown' is
		 *               not available.
		 */
		$provider[] = [ [
		], [
			'expected_form_text' => self::radioButtonFormat( $label, 'one' )
			. self::radioButtonFormat( $label, 'deux' )
			. self::radioButtonFormat( $label, 'drei' ),
			'skip' => 'no SMW'
		] ];

		/**
		 * data set #8 - Add CSS if specified
		 */
		$provider[] = [ [
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|class=testME}}}"
		], [
			'expected_form_text' =>
			self::radioButtonFormat( $label, '', 'None/unknown', 'checked', 'testME' )
			. self::radioButtonFormat( $label, 'one', null, null, 'testME' )
			. self::radioButtonFormat( $label, 'deux', null, null, 'testME' )
			. self::radioButtonFormat( $label, 'drei', null, null, 'testME' )
		] ];

		/**
		 * data set #9 - No tests for origName in wikitext yet
		 */
		$provider[] = [ [], [
			'skip' => 'No tests for origName in wikitext yet'
		] ];

		/**
		 * data set #10 - restricted
		 */
		$provider[] = [ [
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|restricted}}}"
		], [
			'expected_form_text' =>
			self::radioButtonFormat( $label, '', 'None/unknown', 'checked', null, null, true )
			. self::radioButtonFormat( $label, 'one', null, null, null, null, true )
			. self::radioButtonFormat( $label, 'deux', null, null, null, null, true )
			. self::radioButtonFormat( $label, 'drei', null, null, null, null, true )
		] ];

		/**
		 * restricted, but mandatory
		 *
		 * FIXME: This should flag permission denied for the whole form
		 */
		$provider["wikitext, restricted, mandatory"] = [ [
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|restricted|mandatory}}}"
		], [
			'expected_form_text' => '<span id="span_\d+" '
			. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

			. self::radioButtonFormat( $label, 'one', null, null, null, null, true )
			. self::radioButtonFormat( $label, 'deux', null, null, null, null, true )
			. self::radioButtonFormat( $label, 'drei', null, null, null, null, true )

			. "</span>"
		] ];

		/**
		 * Ensure value labels are used when provided
		 */
		$provider["wikitext, value labels"] = [ [
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei}}}"
		], [
			'skip' => "How to do this in wikitext?"
		] ];

		/**
		 * Ensure proper escaping for labels
		 */
		$provider["wikitext, value labels, escaping"] = [ [
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|label=deux=>&2&;drei=><3>}}}"
		], [
			'expected_form_text' => self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )
			. self::radioButtonFormat( $label, 'one' )
			. self::radioButtonFormat( $label, 'deux', '&amp;2&amp;' )
			. self::radioButtonFormat( $label, 'drei', '&lt;3&gt;' ),
			'skip' => 'This looks wrong, but I need to understand the code'
		] ];

		/**
		 * Show on select handling
		 *
		 * FIXME: This is only the CSS classes
		 */
		$provider["wikitext, show on select"] = [ [
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|show on select=one=>blah}}}"
		], [
			'expected_form_text' => '<span id="span_\d+" class="radioButtonSpan '
			. 'pfShowIfChecked">' . "\n"
			. self::radioButtonFormat( $label, '', 'None/unknown', 'checked' )
			. self::radioButtonFormat( $label, 'one' )
			. self::radioButtonFormat( $label, 'deux' )
			. self::radioButtonFormat( $label, 'drei' ),
		] ];

		/**
		 * https://www.mediawiki.org/wiki/Extension:Page_Forms/Input_types#radiobutton
		 *
		 * By default, the first radiobutton value is "None", which
		 * lets the user choose a blank value. To prevent "None" from
		 * showing up, you must make the field "mandatory", as well as
		 * making one of the allowed values the field's "default="
		 * value.
		 */
		$provider['wikitext, smw no none'] = [ [
			'form_definition' => "{{{field|$label|input type=radiobutton|"
		], [
			'skip' => "No SMW test yet!",
			'expected_form_text' => self::radioButtonFormat( $label, 'Yes' )
					. self::radioButtonFormat( $label, 'No', null, 'checked' )
		] ];

		return $provider;
	}

	/**
	 * Returns a mock Title for test
	 * @return Title
	 */
	private function getTitle() {
		$mockTitle = $this->getMockBuilder( 'Title' )
			->disableOriginalConstructor()
			->getMock();

		$mockTitle->expects( $this->any() )
			->method( 'getDBkey' )
			->will( $this->returnValue( 'Sometitle' ) );

		$mockTitle->expects( $this->any() )
			->method( 'getNamespace' )
			->will( $this->returnValue( PF_NS_FORM ) );

		return $mockTitle;
	}
}
