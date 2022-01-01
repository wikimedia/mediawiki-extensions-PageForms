<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFDateTimeInput extends PFDateInput {

	public static function getName(): string {
		return 'datetime';
	}

	public static function getDefaultPropTypes() {
		return [];
	}

	public static function getOtherPropTypesHandled() {
		return [ '_dat' ];
	}

	public static function getDefaultCargoTypes() {
		return [
			'Datetime' => []
		];
	}

	public function getInputClass() {
		return 'dateTimeInput';
	}

	public function getHTML( $datetime, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgPageForms24HourTime;

		$include_timezone = array_key_exists( 'include timezone', $other_args );

		if ( $datetime ) {
			// Can show up here either as an array or a string,
			// depending on whether it came from user input or a
			// wiki page.
			if ( is_array( $datetime ) ) {
				if ( isset( $datetime['hour'] ) ) {
					$hour = $datetime['hour'];
				}
				if ( isset( $datetime['minute'] ) ) {
					$minute = $datetime['minute'];
				}
				if ( isset( $datetime['second'] ) ) {
					$second = $datetime['second'];
				}
				if ( !$wgPageForms24HourTime ) {
					if ( isset( $datetime['ampm24h'] ) ) {
						$ampm24h = $datetime['ampm24h'];
					}
				}
				if ( isset( $datetime['timezone'] ) ) {
					$timezone = $datetime['timezone'];
				}
			} else {
				// Parse the date.
				// We get only the time data here - the main
				// date data is handled by the call to
				// parent::getMainHTML().

				// Handle 'default=now'.
				if ( $datetime == 'now' ) {
					global $wgLocaltimezone;
					if ( $wgLocaltimezone == null ) {
						$dateTimeObject = new DateTime( 'now' );
					} else {
						$dateTimeObject = new DateTime( 'now', new DateTimeZone( $wgLocaltimezone ) );
					}
				} else {
					$dateTimeObject = new DateTime( $datetime );
				}
				if ( $wgPageForms24HourTime ) {
					$hour = $dateTimeObject->format( 'G' );
				} else {
					$hour = $dateTimeObject->format( 'g' );
				}
				$minute = $dateTimeObject->format( 'i' );
				$second = $dateTimeObject->format( 's' );
				if ( !$wgPageForms24HourTime ) {
					$ampm24h = $dateTimeObject->format( 'A' );
				}
				$timezone = $dateTimeObject->format( 'T' );
			}
		} else {
			$hour = null;
			$minute = null;
			// Default at least the "seconds" field.
			$second = '00';
			$ampm24h = '';
			$timezone = '';
		}

		$text = parent::getMainHTML( $datetime, $input_name, $is_mandatory, $is_disabled, $other_args );
		$disabled_text = ( $is_disabled ) ? 'disabled' : '';
		$text .= '	&#160;<input tabindex="' . $wgPageFormsTabIndex . '" name="' . $input_name . '[hour]" type="text" class="hoursInput" value="' . $hour . '" size="2"/ ' . $disabled_text . '>';
		$wgPageFormsTabIndex++;
		$text .= '	:<input tabindex="' . $wgPageFormsTabIndex . '" name="' . $input_name . '[minute]" type="text" class="minutesInput" value="' . $minute . '" size="2"/ ' . $disabled_text . '>';
		$wgPageFormsTabIndex++;
		$text .= ':<input tabindex="' . $wgPageFormsTabIndex . '" name="' . $input_name . '[second]" type="text" class="secondsInput" value="' . $second . '" size="2"/ ' . $disabled_text . '>' . "\n";

		if ( !$wgPageForms24HourTime ) {
			$wgPageFormsTabIndex++;
			$text .= '	 <select tabindex="' . $wgPageFormsTabIndex . '" name="' . $input_name . "[ampm24h]\" class=\"ampmInput\" $disabled_text>\n";
			$ampm24h_options = [ '', 'AM', 'PM' ];
			foreach ( $ampm24h_options as $value ) {
				$text .= "				<option value=\"$value\"";
				if ( $value == $ampm24h ) {
					$text .= " selected=\"selected\"";
				}
				$text .= ">$value</option>\n";
			}
			$text .= "	</select>\n";
		}

		if ( $include_timezone ) {
			$wgPageFormsTabIndex++;
			$text .= '	<input tabindex="' . $wgPageFormsTabIndex . '" name="' . $input_name . '[timezone]" type="text" value="' . $timezone . '" size="3"/ ' . $disabled_text . '>' . "\n";
		}

		$spanClass = $this->getInputClass();
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}
		$text = Html::rawElement( 'span', [ 'class' => $spanClass ], $text );

		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = [
			'name' => 'include timezone',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_includetimezone' )->text()
		];
		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText(): string {
		return $this->getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}
}
