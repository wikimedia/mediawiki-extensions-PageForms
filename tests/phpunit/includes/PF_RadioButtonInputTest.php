<?php

/**
 * @covers \PFRadioButtonInput
 *
 * @author Mark A. Hershberger <mah@nichework.com>
 */
class PFRadioButtonInputTest extends MediaWikiTestCase {

	private function radioButtonFormat(
		$name, $value, $label = null, $checked = null, $class = null,
		$append = null, $disabled = null
	) {
		return "\t" . sprintf(
			'<label class="radioButtonItem%s" for="input_\d+">'
			. '<input name="%s" type="radio" value="%s" %s'
			. 'id="input_\d+" tabindex="\d+"%s%s /> %s</label>',
			( $class !== null ? " $class" : '' ), $name, $value,
			( $checked !== null ? 'checked="' . $checked . '" ' : '' ),
			( $append !== null ? " $append" : '' ),
			( $disabled !== null ? ' disabled="1"' : '' ),
			( $label !== null ? $label : $value )
		) . "\n";
	}

	// Tests for the radiobutton code.

	/**
	 * @dataProvider radioButtonDataProvider
	 */
	public function testRadioButtons( $setup, $expected ) {
		$result = call_user_func_array(
			array( 'PFRadioButtonInput', 'getHTML' ), $setup['args']
		);

		$this->assertRegexp(
			'#' . $expected['expected_html'] . '#',
			$result,
			'asserts that getHTML() returns the correct HTML text'
		);
	}

	/**
	 * Data provider method
	 */
	public function radioButtonDataProvider() {
		$provider = array();
		$label = "froot_loops";

		// data set #0 radiobutton definition without other parameters
		//
		// FIXME: This seems like it shouldn't be possible
		$provider[] = array( array(
			'args' => array( 999, $label, false, false, array() ),
		), array(
			'expected_html' => '<span id="span_\d+" '
			. 'class="radioButtonSpan">' . "\n"

			. $this->radioButtonFormat( $label, '', 'None', 'checked' )

			. "</span>"
		) );

		// data set #1 form definition with only 'property_type' => '_boo'
		$provider[] = array( array(
			'args' => array( 999, $label, false, false, array(
				'property_type' => '_boo'
			) ) ), array(
				'expected_html' => '<span id="span_\d+" '
				. 'class="radioButtonSpan">' . "\n"

				. $this->radioButtonFormat( $label, '', 'None', 'checked' )
				. $this->radioButtonFormat( $label, 'Yes' )
				. $this->radioButtonFormat( $label, 'No' )

				. "</span>"
			) );

		// data set #2 form definition with only 'possible_values' =>
		// [ 'one', 'deux', 'drei' ]
		$provider[] = array( array(
			'args' => array( 999, $label, false, false, array(
				'possible_values' => array( 'one', 'deux', 'drei' )
			) ) ), array(
				'expected_html' => '<span id="span_\d+" '
				. 'class="radioButtonSpan">' . "\n"

				. $this->radioButtonFormat( $label, '', 'None', 'checked' )
				. $this->radioButtonFormat( $label, 'one' )
				. $this->radioButtonFormat( $label, 'deux' )
				. $this->radioButtonFormat( $label, 'drei' )

				. "</span>"
				) );

		// data set #3 - if this is a mandatory field:
		// # make sure mandatoryFieldSpan class is added
		// # ensure new first button is checked
		// See https://phabricator.wikimedia.org/T238397
		$provider[] = array( array(
			'args' => array(
				999, $label, true, false, array(
					'possible_values' => array( 'one', 'deux', 'drei' )
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

					. $this->radioButtonFormat( $label, 'one', null, 'checked' )
					. $this->radioButtonFormat( $label, 'deux' )
					. $this->radioButtonFormat( $label, 'drei' )

					. "</span>"
				) );

		/**
		 * https://www.mediawiki.org/wiki/Extension:Page_Forms/Input_types#radiobutton
		 *
		 * By default, the first radiobutton value is "None", which
		 * lets the user choose a blank value. To prevent "None" from
		 * showing up, you must make the field "mandatory", as well as
		 * making one of the allowed values the field's "default="
		 * value.
		 *
		 * See also #16 below.
		 */
		$provider['code, default value'] = array( array(
			'args' => array(
				'drei', $label, true, false, array(
					'possible_values' => array( 'one', 'deux', 'drei' )
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

					. $this->radioButtonFormat( $label, 'one' )
					. $this->radioButtonFormat( $label, 'deux' )
					. $this->radioButtonFormat(
						$label, 'drei', null, "checked"
					)

					. "</span>"
				) );

		// data set #5 - if null is the current value is provided on a
		// mandatory field, make the first option the
		// one selected.
		$provider[] = array( array(
			'args' => array(
				null, $label, true, false, array(
					'possible_values' => array( 'one', 'deux', 'drei' )
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

					. $this->radioButtonFormat( $label, '', 'None', "checked" )
					. $this->radioButtonFormat( $label, 'one' )
					. $this->radioButtonFormat( $label, 'deux' )
					. $this->radioButtonFormat( $label, 'drei' )

					. "</span>"
				) );

		// data set #6 - if null is the current value is provided on a
		// non-mandatory field, make sure 'None' is
		// selected.
		$provider[] = array( array(
			'args' => array(
				null, $label, false, false, array(
					'possible_values' => array( 'one', 'deux', 'drei' )
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan">' . "\n"

					. $this->radioButtonFormat( $label, '', 'None', 'checked' )
					. $this->radioButtonFormat( $label, 'one' )
					. $this->radioButtonFormat( $label, 'deux' )
					. $this->radioButtonFormat( $label, 'drei' )

					. "</span>"
				) );

		// data set #7 - if null is the current value is provided on a
		// _boo, make sure 'None' is available.
		$provider[] = array( array(
			'args' => array(
				null, $label, true, false, array(
					'property_type' => '_boo'
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

					. $this->radioButtonFormat( $label, '', 'None', 'checked' )
					. $this->radioButtonFormat( $label, 'Yes' )
					. $this->radioButtonFormat( $label, 'No' )

					. "</span>",
				) );

		// data set #8 Add CSS class(es)
		$provider[] = array( array(
			'args' => array(
				999, $label, false, false, array(
					"possible_values" => array( "one", "deux", "drei" ),
					'class' => 'testME'
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan testME">' . "\n"

					. $this->radioButtonFormat( $label, '', 'None', 'checked', 'testME' )
					. $this->radioButtonFormat( $label, 'one', null, null, 'testME' )
					. $this->radioButtonFormat( $label, 'deux', null, null, 'testME' )
					. $this->radioButtonFormat( $label, 'drei', null, null, 'testME' )

					. "</span>"
				) );

		// data set #9 origName attribute
		$provider[] = array( array(
			'args' => array(
				999, $label, false, false, array(
					'property_type' => '_boo',
					'origName' => 'testME'
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan">' . "\n"

					. $this->radioButtonFormat(
						$label, '', 'None', 'checked', null, 'origname="testME"'
					)
					. $this->radioButtonFormat(
						$label, 'Yes', null, null, null, 'origname="testME"'
					)
					. $this->radioButtonFormat(
						$label, 'No', null, null, null, 'origname="testME"'
					)

					. "</span>"
				) );

		// data set #10 is_disabled is true
		// FIXME: I can see an argument for using None here, but,
		// still seems wonky.
		$provider[] = array( array(
			'args' => array(
				999, $label, false, true, array(
					'possible_values' => array( 'Yes', 'No' ),
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan">' . "\n"

					. $this->radioButtonFormat(
						$label, '', 'None', 'checked', null, null, true
					)
					. $this->radioButtonFormat(
						$label, 'Yes', null, null, null, null, true
					)
					. $this->radioButtonFormat(
						$label, 'No', null, null, null, null, true
					)

					. "</span>"
				) );

		// data set #11 is_disabled is true and is_mandatory is true
		// FIXME: shouldn't this fail instead of forcing true?
		$provider[] = array( array(
			'args' => array(
				999, $label, true, true, array(
					'property_type' => '_boo',
					'origName' => 'testME'
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

					. $this->radioButtonFormat(
						$label, 'Yes', null, 'checked', null,
						'origname="testME"', true
					)
					. $this->radioButtonFormat(
						$label, 'No', null, null, null, 'origname="testME"',
						true
					)

					. "</span>"
				) );

		// data set #13 - Ensure value_labels are used when provided.
		$provider[] = array( array(
			'args' => array(
				null, $label, true, false, array(
					'possible_values' => array( 'one', 'deux', 'drei' ),
					'value_labels' => array(
						'deux' => 'two', 'drei' => 'three'
					) ) ) ), array(
						'expected_html' => '<span id="span_\d+" '
						. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

						. $this->radioButtonFormat( $label, '', 'None', 'checked' )
						. $this->radioButtonFormat( $label, 'one' )
						. $this->radioButtonFormat( $label, 'deux', 'two' )
						. $this->radioButtonFormat( $label, 'drei', 'three' )

						. "</span>"
					) );

		// data set #14 - Ensure value_labels are escaped properly
		$provider[] = array( array(
			'args' => array(
				null, $label, true, false, array(
					'possible_values' => array( 'one', 'deux', 'drei' ),
					'value_labels' => array(
						'deux' => '&2&', 'drei' => '<3>'
					) ) ) ), array(
						'expected_html' => '<span id="span_\d+" '
						. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

						. $this->radioButtonFormat( $label, '', 'None', 'checked' )
						. $this->radioButtonFormat( $label, 'one' )
						. $this->radioButtonFormat( $label, 'deux', '&amp;2&amp;' )
						. $this->radioButtonFormat( $label, 'drei', '&lt;3&gt;' )

						. "</span>"
					) );

		// data set #15 - Show on Select handling
		// FIXME: We're only dealing with the CSS class here
		$provider[] = array( array(
			'args' => array(
				null, $label, true, false, array(
					'show on select' => array(),
					'property_type' => '_boo'
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan '
					. 'pfShowIfChecked">' . "\n"

					. $this->radioButtonFormat( $label, '', 'None', 'checked' )
					. $this->radioButtonFormat( $label, 'Yes' )
					. $this->radioButtonFormat( $label, 'No' )

					. "</span>"
				) );

		/**
		 * https://www.mediawiki.org/wiki/Extension:Page_Forms/Input_types#radiobutton
		 *
		 * data set #16 - By default, the first radiobutton value is
		 *                "None", which lets the user choose a blank
		 *                value. To prevent "None" from showing up,
		 *                you must make the field "mandatory", as well
		 *                as making one of the allowed values the
		 *                field's "default=" value.
		 */
		$provider[] = array( array(
			'args' => array(
				'No', $label, true, false, array(
					'property_type' => '_boo'
				) ) ), array(
					'expected_html' => '<span id="span_\d+" '
					. 'class="radioButtonSpan mandatoryFieldSpan">'
					. "\n"

					. $this->radioButtonFormat( $label, 'Yes' )
					. $this->radioButtonFormat( $label, 'No', null, 'checked' )

					. "</span>"
				) );

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
				list( $form_text, $page_text, $form_page_title, $generated_page_name )
					= $wgPageFormsFormPrinter->formHTML(
						$setup['form_definition'], true, false, null, null,
						'TestStringForFormPageTitle', null
					);
			} else {
				$this->markTestSkipped( "No form to test!" );
				return;
			}

			if ( isset( $expected['expected_form_text'] ) ) {
				$this->assertRegexp(
					'#' . $expected['expected_form_text'] . '#',
					$form_text,
					'asserts that formHTML() returns the correct HTML text for the form'
				);
			}
			if ( isset( $expected['expected_page_text'] ) ) {
				$this->assertRegexp(
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
	public function radioButtomFromWikitextDataProvider() {
		$provider = array();

		$label = "field_radiobutton";

		/**
		 * data set #0 radiobutton definition without other parameters
		 *
		 * FIXME: This seems like it shouldn't be possible
		 */
		$provider[] = array( array(
			'form_definition' => "{{{field|$label|input type=radiobutton}}}"
		), array(
			'expected_form_text' => $this->radioButtonFormat( $label, '', 'None', 'checked' )
		) );

		/**
		 * data set #1 form definition with only 'property_type' => '_boo'
		 */
		$provider[] = array( array(
		), array(
			'expected_form_test' =>
			$this->radioButtonFormat( $label, '', 'None', 'checked' )
			. $this->radioButtonFormat( $label, 'Yes' )
			. $this->radioButtonFormat( $label, 'No' ),
			'skip' => 'How to do SMW?'
		) );

		/**
		 * data set #2 form definition with only 'possible_values' =>
		 *             [ 'one', 'deux', 'drei' ]
		 */
		$provider[] = array( array(
			'form_definition' => "{{{field|$label| input type=radiobutton| values=one, "
			. "deux, drei}}}"
		), array(
			'expected_form_test' =>
			$this->radioButtonFormat( $label, '', 'None', 'checked' )
			. $this->radioButtonFormat( $label, 'one' )
			. $this->radioButtonFormat( $label, 'deux' )
			. $this->radioButtonFormat( $label, 'drei' )
		) );

		/**
		 * data set #3 - if this is a mandatory field:
		 *             # ensure new first button is checked
		 *
		 * See https://phabricator.wikimedia.org/T238397
		 */
		$provider[] = array( array(
			'form_definition' => "{{{field|$label| input type=radiobutton| values=one, "
			. "deux, drei|mandatory}}}"
		), array(
			'expected_form_text' => $this->radioButtonFormat(
				$label, 'one', null, 'checked'
			)
			. $this->radioButtonFormat( $label, 'deux' )
			. $this->radioButtonFormat( $label, 'drei' ),
			'skip' => 'Mandatory does not seem to work, see also #16 in this set'
		) );

		/**
		 * https://www.mediawiki.org/wiki/Extension:Page_Forms/Input_types#radiobutton
		 *
		 * By default, the first radiobutton value is "None", which
		 * lets the user choose a blank value. To prevent "None" from
		 * showing up, you must make the field "mandatory", as well as
		 * making one of the allowed values the field's "default="
		 * value.
		 */
		$provider['wikitext, default value'] = array( array(
			'form_definition' => "{{{field|$label| input type=radiobutton| values=one, "
			. "deux, drei|mandatory|default=drei}}}"
		), array(
			'expected_form_text'  => '<span id="span_\d+" '
			. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

			. $this->radioButtonFormat( $label, 'one' )
			. $this->radioButtonFormat( $label, 'deux' )
			. $this->radioButtonFormat(
				$label, 'drei', null, "checked"
			)

			. "</span>",
			'skip' => "This should pass, but doesn't see 'code, default value' above"
		) );

		/*
		 * data set #5 - if null is the current value is provided on a
		 *               mandatory field, make the first option the
		 *               one selected.
		 */
		$provider[] = array( array(
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|mandatory}}}"
		), array(
			'expected_form_text' => $this->radioButtonFormat( $label, 'one', null, 'checked' )
			. $this->radioButtonFormat( $label, 'deux' )
			. $this->radioButtonFormat( $label, 'drei' ),
			'skip' => 'Works with parameters (see above), but wikitext parsing fails'
		) );

		/**
		 * data set #6 - if null is the current value is provided on a
		 *               non-mandatory field, make sure 'None' is
		 *               selected.
		 */
		$provider[] = array( array(
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|mandatory}}}"
		), array(
			'expected_form_text' => $this->radioButtonFormat( $label, '', 'None', 'checked' )
			. $this->radioButtonFormat( $label, 'one' )
			. $this->radioButtonFormat( $label, 'deux' )
			. $this->radioButtonFormat( $label, 'drei' )
		) );

		/**
		 * data set #7 - if null is the current value is provided on a
		 *               mandatory boolean field, make sure 'None' is
		 *               not available.
		 */
		$provider[] = array( array(
		), array(
			'expected_form_text' => $this->radioButtonFormat( $label, 'one' )
			. $this->radioButtonFormat( $label, 'deux' )
			. $this->radioButtonFormat( $label, 'drei' ),
			'skip' => 'no SMW'
		) );

		/**
		 * data set #8 - Add CSS if specified
		 */
		$provider[] = array( array(
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|class=testME}}}"
		), array(
			'expected_form_text' =>
			$this->radioButtonFormat( $label, '', 'None', 'checked', 'testME' )
			. $this->radioButtonFormat( $label, 'one', null, null, 'testME' )
			. $this->radioButtonFormat( $label, 'deux', null, null, 'testME' )
			. $this->radioButtonFormat( $label, 'drei', null, null, 'testME' )
		) );

		/**
		 * data set #9 - No tests for origName in wikitext yet
		 */
		$provider[] = array( array(), array(
			'skip' => 'No tests for origName in wikitext yet'
		) );

		/**
		 * data set #10 - restricted
		 */
		$provider[] = array( array(
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|restricted}}}"
		), array(
			'expected_form_text' =>
			$this->radioButtonFormat( $label, '', 'None', 'checked', null, null, true )
			. $this->radioButtonFormat( $label, 'one', null, null, null, null, true )
			. $this->radioButtonFormat( $label, 'deux', null, null, null, null, true )
			. $this->radioButtonFormat( $label, 'drei', null, null, null, null, true )
		) );

		/**
		 * restricted, but mandatory
		 *
		 * FIXME: This should flag permission denied for the whole form
		 */
		$provider["wikitext, restricted, mandatory"] = array( array(
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|restricted|mandatory}}}"
		), array(
			'expected_form_text' => '<span id="span_\d+" '
			. 'class="radioButtonSpan mandatoryFieldSpan">' . "\n"

			. $this->radioButtonFormat( $label, '', 'None', 'checked', null, null, true )
			. $this->radioButtonFormat( $label, 'one', null, null, null, null, true )
			. $this->radioButtonFormat( $label, 'deux', null, null, null, null, true )
			. $this->radioButtonFormat( $label, 'drei', null, null, null, null, true )

			. "</span>"
		) );

		/**
		 * Ensure value labels are used when provided
		 */
		$provider["wikitext, value labels"] = array( array(
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei}}}"
		), array(
			'skip' => "How to do this in wikitext?"
		) );

		/**
		 * Ensure proper escaping for labels
		 */
		$provider["wikitext, value labels, escaping"] = array( array(
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|label=deux=>&2&;drei=><3>}}}"
		), array(
			'expected_form_text' => $this->radioButtonFormat( $label, '', 'None', 'checked' )
			. $this->radioButtonFormat( $label, 'one' )
			. $this->radioButtonFormat( $label, 'deux', '&amp;2&amp;' )
			. $this->radioButtonFormat( $label, 'drei', '&lt;3&gt;' ),
			'skip' => 'This looks wrong, but I need to understand the code'
		) );

		/**
		 * Show on select handling
		 *
		 * FIXME: This is only the CSS classes
		 */
		$provider["wikitext, show on select"] = array( array(
			'form_definition' => "{{{field|$label|input type=radiobutton|values=one,"
			. "deux,drei|show on select=one=>blah}}}"
		), array(
			'expected_form_text' => '<span id="span_\d+" class="radioButtonSpan '
			. 'pfShowIfChecked">' . "\n"
			. $this->radioButtonFormat( $label, '', 'None', 'checked' )
			. $this->radioButtonFormat( $label, 'one' )
			. $this->radioButtonFormat( $label, 'deux' )
			. $this->radioButtonFormat( $label, 'drei' ),
		) );

		/**
		 * https://www.mediawiki.org/wiki/Extension:Page_Forms/Input_types#radiobutton
		 *
		 * By default, the first radiobutton value is "None", which
		 * lets the user choose a blank value. To prevent "None" from
		 * showing up, you must make the field "mandatory", as well as
		 * making one of the allowed values the field's "default="
		 * value.
		 */
		$provider['wikitext, smw no none'] = array( array(
			'form_definition' => "{{{field|$label|input type=radiobutton|"
		), array(
			'skip' => "No SMW test yet!",
			'expected_form_text' => $this->radioButtonFormat( $label, 'Yes' )
					. $this->radioButtonFormat( $label, 'No', null, 'checked' )
		) );

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
