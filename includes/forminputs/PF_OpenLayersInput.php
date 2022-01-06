<?php
/**
 * @file
 * @ingroup PF
 */

/**
 * @ingroup PFFormInput
 */
class PFOpenLayersInput extends PFFormInput {

	public static function getName(): string {
		return 'openlayers';
	}

	public static function getDefaultPropTypes() {
		return [];
	}

	public static function getDefaultCargoTypes() {
		return [ 'Coordinates' => [] ];
	}

	public static function getHeight( $other_args ) {
		if ( array_key_exists( 'height', $other_args ) ) {
			$height = $other_args['height'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $height ) ) {
				$height .= "px";
			}
		} else {
			$height = "500px";
		}
		return $height;
	}

	public static function getWidth( $other_args ) {
		if ( array_key_exists( 'width', $other_args ) ) {
			$width = $other_args['width'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $width ) ) {
				$width .= "px";
			}
		} else {
			$width = "500px";
		}
		return $width;
	}

	/**
	 * @todo - change to non-static functions for all the map-based
	 * form input classes, so we don't need all these parameters.
	 *
	 * @param string $cur_value
	 * @param string $input_name
	 * @param bool $is_mandatory
	 * @param bool $is_disabled
	 * @param array $other_args
	 * @param int $height
	 * @param int $width
	 * @param bool $includeAddressLookup
	 * @return string
	 */
	public static function mapLookupHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args, $height, $width, $includeAddressLookup = true ) {
		global $wgPageFormsFieldNum, $wgPageFormsTabIndex;
		global $wgPageFormsMapsWithFeeders;

		if ( $includeAddressLookup ) {
			// The address input box is not necessary if we are using other form inputs for the address.
			if ( array_key_exists( $input_name, $wgPageFormsMapsWithFeeders ) ) {
				$addressLookupInput = '';
			} else {
				$addressLookupInputAttrs = [
					'type' => 'text',
					'tabIndex' => $wgPageFormsTabIndex++,
					'classes' => [ 'pfAddressInput' ],
					'size' => 40,
					'placeholder' => wfMessage( 'pf-maps-enteraddress' )->parse()
				];
				$addressLookupInput = new OOUI\TextInputWidget( $addressLookupInputAttrs );
			}
			$addressLookupButtonAttrs = [
				'tabIndex' => $wgPageFormsTabIndex++,
				'classes' => [ 'pfLookUpAddress' ],
				'label' => wfMessage( 'pf-maps-lookupcoordinates' )->parse(),
				'flags' => [ 'progressive' ],
				'icon' => 'mapPin'
			];
			if ( $is_disabled ) {
				$addressLookupButtonAttrs['disabled'] = true;
				$addressLookupButtonAttrs['classes'] = [];
			}
			$addressLookupButton = new OOUI\ButtonWidget( $addressLookupButtonAttrs );
		}

		if ( !$includeAddressLookup ) {
			$text = '';
		} elseif ( $addressLookupInput != '' ) {
			$text = new OOUI\ActionFieldLayout( $addressLookupInput, $addressLookupButton, [ 'align' => 'top' ] );
		} else {
			$text = new OOUI\FieldLayout( $addressLookupButton );
		}

		$coordsInputAttrs = [
			'type' => 'text',
			'tabindex' => $wgPageFormsTabIndex++,
			'class' => 'pfCoordsInput',
			'name' => $input_name,
			'value' => self::parseCoordinatesString( $cur_value ),
			'size' => 40
		];

		if ( array_key_exists( 'starting bounds', $other_args ) ) {
			$boundCoords = $other_args['starting bounds'];
			$boundCoords = explode( ";", $boundCoords );
			$boundCoords[0] = self::parseCoordinatesString( $boundCoords[0] );
			$boundCoords[1] = self::parseCoordinatesString( $boundCoords[1] );
			$coordsInputAttrs['data-bound-coords'] = "$boundCoords[0];$boundCoords[1]";
		}

		$coordsInput = Html::element( 'input', $coordsInputAttrs );

		$mapCanvas = Html::element( 'div', [
			'class' => 'pfMapCanvas',
			'id' => 'pfMapCanvas' . $wgPageFormsFieldNum,
			'style' => "height: $height; width: $width;"
		], null );

		$text .= Html::rawElement( 'div', [ 'style' => 'padding-top: 10px; padding-bottom: 10px;' ], $coordsInput );
		$text .= "$mapCanvas\n";

		return $text;
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgOut;

		if ( ExtensionRegistry::getInstance()->isLoaded( 'OpenLayers' ) ) {
			$wgOut->addModuleStyles( 'ext.openlayers.main' );
			$wgOut->addModuleScripts( 'ext.openlayers.main' );
		} else {
			$scripts = [
				"https://openlayers.org/api/OpenLayers.js"
			];
			$scriptsHTML = '';
			foreach ( $scripts as $script ) {
				$scriptsHTML .= Html::linkedScript( $script );
			}
			$wgOut->addHeadItem( $scriptsHTML, $scriptsHTML );
		}

		$wgOut->addModules( 'ext.pageforms.maps' );

		$height = self::getHeight( $other_args );
		$width = self::getWidth( $other_args );
		$fullInputHTML = self::mapLookupHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args, $height, $width );

		$text = Html::rawElement( 'div', [ 'class' => 'pfOpenLayersInput' ], $fullInputHTML );

		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = [
			'name' => 'height',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_height' )->text()
		];
		$params[] = [
			'name' => 'width',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_width' )->text()
		];
		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText(): string {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}

	/**
	 * Parses one half of a set of coordinates into a number.
	 *
	 * Copied from CargoStore::coordinatePartToNumber() in the Cargo
	 * extension.
	 * @param string $coordinateStr
	 * @return int
	 * @throws MWException
	 */
	public static function coordinatePartToNumber( $coordinateStr ) {
		$degreesSymbols = [ "\x{00B0}", "d" ];
		$minutesSymbols = [ "'", "\x{2032}", "\x{00B4}" ];
		$secondsSymbols = [ '"', "\x{2033}", "\x{00B4}\x{00B4}" ];

		$numDegrees = null;
		$numMinutes = null;
		$numSeconds = null;

		foreach ( $degreesSymbols as $degreesSymbol ) {
			$pattern = '/([\d\.]+)' . $degreesSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numDegrees = floatval( $matches[1] );
				break;
			}
		}
		if ( $numDegrees == null ) {
			throw new MWException( "Error: could not parse degrees in \"$coordinateStr\"." );
		}

		foreach ( $minutesSymbols as $minutesSymbol ) {
			$pattern = '/([\d\.]+)' . $minutesSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numMinutes = floatval( $matches[1] );
				break;
			}
		}
		if ( $numMinutes == null ) {
			// This might not be an error - the number of minutes
			// might just not have been set.
			$numMinutes = 0;
		}

		foreach ( $secondsSymbols as $secondsSymbol ) {
			$pattern = '/(\d+)' . $secondsSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numSeconds = floatval( $matches[1] );
				break;
			}
		}
		if ( $numSeconds == null ) {
			// This might not be an error - the number of seconds
			// might just not have been set.
			$numSeconds = 0;
		}

		return ( $numDegrees + ( $numMinutes / 60 ) + ( $numSeconds / 3600 ) );
	}

	/**
	 * Parses a coordinate string in (hopefully) any standard format.
	 *
	 * Copied from CargoStore::parseCoordinateString() in the Cargo
	 * extension.
	 * @param string $coordinatesString
	 * @return string|null
	 */
	public static function parseCoordinatesString( $coordinatesString ) {
		$coordinatesString = trim( $coordinatesString );
		if ( $coordinatesString === '' ) {
			return null;
		}

		// This is safe to do, right?
		$coordinatesString = str_replace( [ '[', ']' ], '', $coordinatesString );
		// See if they're separated by commas.
		if ( strpos( $coordinatesString, ',' ) > 0 ) {
			$latAndLonStrings = explode( ',', $coordinatesString );
		} else {
			// If there are no commas, the first half, for the
			// latitude, should end with either 'N' or 'S', so do a
			// little hack to split up the two halves.
			$coordinatesString = str_replace( [ 'N', 'S' ], [ 'N,', 'S,' ], $coordinatesString );
			$latAndLonStrings = explode( ',', $coordinatesString );
		}

		if ( count( $latAndLonStrings ) != 2 ) {
			throw new MWException( "Error parsing coordinates string: \"$coordinatesString\"." );
		}
		list( $latString, $lonString ) = $latAndLonStrings;

		// Handle strings one at a time.
		$latIsNegative = false;
		if ( strpos( $latString, 'S' ) > 0 ) {
			$latIsNegative = true;
		}
		$latString = str_replace( [ 'N', 'S' ], '', $latString );
		if ( is_numeric( $latString ) ) {
			$latNum = floatval( $latString );
		} else {
			$latNum = self::coordinatePartToNumber( $latString );
		}
		if ( $latIsNegative ) {
			$latNum *= -1;
		}

		$lonIsNegative = false;
		if ( strpos( $lonString, 'W' ) > 0 ) {
			$lonIsNegative = true;
		}
		$lonString = str_replace( [ 'E', 'W' ], '', $lonString );
		if ( is_numeric( $lonString ) ) {
			$lonNum = floatval( $lonString );
		} else {
			$lonNum = self::coordinatePartToNumber( $lonString );
		}
		if ( $lonIsNegative ) {
			$lonNum *= -1;
		}

		return "$latNum, $lonNum";
	}

}
