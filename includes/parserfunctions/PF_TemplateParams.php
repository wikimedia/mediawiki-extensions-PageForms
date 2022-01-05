<?php

/**
 * Defines the #template_display parser function.
 *
 * @author Yaron Koren
 */

class PFTemplateParams {

	public static function run( Parser $parser ) {
		global $wgRenderHashAppend, $wgLang;

		$title = $parser->getTitle();
		if ( $title->getNamespace() !== NS_TEMPLATE ) {
			return '<div class="error">Error: #template_params can only be called within a template.</div>';
		}

		// In theory, this will set a separate cache for each user
		// language - so that a user viewing the output of
		// #template_params in one language won't affect the display
		// for a user viewing it in another language.
		// In practice, setting this variable to *any* value seems to
		// just disable the cache entirely. That's probably alright,
		// though - template pages don't get viewed that frequently,
		// so disabling the cache for them probably will not have a
		// big effect on performance.
		$wgRenderHashAppend = ';lang=' . $wgLang->getCode();

		$params = func_get_args();
		// We don't need the parser.
		array_shift( $params );

		$fieldData = [];
		foreach ( $params as $param ) {
			list( $fieldName, $fieldParams ) = self::parseWikitextString( $param );
			if ( $fieldName !== '' ) {
				$fieldData[$fieldName] = $fieldParams;
			}
		}

		$parserOutput = $parser->getOutput();
		if ( method_exists( $parserOutput, 'setPageProperty' ) ) {
			// MW 1.38+
			$parserOutput->setPageProperty( 'PageFormsTemplateParams', serialize( $fieldData ) );
		} else {
			$parserOutput->setProperty( 'PageFormsTemplateParams', serialize( $fieldData ) );
		}

		$text = wfMessage( "pf_template_docu", $title->getText() )->escaped();
		$text .= "<pre>\n{{" . $title->getText() . "\n";
		foreach ( $fieldData as $fieldName => $fieldParams ) {
			$text .= "|$fieldName=\n";
		}
		$text .= "}}</pre>\n";
		$text .= '<p>' . wfMessage( "pf_template_docufooter" )->escaped() . '</p>';

		return [ $text, 'noparse' => true, 'isHTML' => true ];
	}

	public static function parseWikitextString( $fieldString ) {
		$fieldParams = [];
		$matches = [];
		$foundMatch = preg_match( '/([^(]*)\s*\((.*)\)/s', $fieldString, $matches );
		$allowedValuesParam = "";
		if ( $foundMatch ) {
			$fieldName = trim( $matches[1] );
			$extraParamsString = $matches[2];
			$extraParams = explode( ';', $extraParamsString );
			foreach ( $extraParams as $extraParam ) {
				$extraParamParts = explode( '=', $extraParam, 2 );
				if ( count( $extraParamParts ) == 1 ) {
					$paramKey = strtolower( trim( $extraParamParts[0] ) );
					$fieldParams[$paramKey] = true;
				} else {
					$paramKey = strtolower( trim( $extraParamParts[0] ) );
					$paramValue = trim( $extraParamParts[1] );
					$fieldParams[$paramKey] = $paramValue;
				}
			}
		} else {
			$fieldName = trim( $fieldString );
		}

		return [ $fieldName, $fieldParams ];
	}

}
