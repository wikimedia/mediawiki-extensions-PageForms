<?php

class PFPageNameFormula {
	public static function run( Parser $parser ) {
		$params = func_get_args();
		// The first standard argument is the parser, so shift it off
		array_shift( $params );
		$formula = implode( '|', $params );

		$parser->getOutput()->setExtensionData( 'PFPageNameFormula', $formula );

		$title = $parser->getTitle();

		// Return nothing
		return '';
	}
}
