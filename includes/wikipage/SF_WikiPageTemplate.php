<?php
/**
 * @author Yaron Koren
 * @file
 * @ingroup SF
 */

/**
 * Represents a single template call within a wiki page.
 */
class SFWikiPageTemplate {
	private $mName;
	private $mParams = array();
	private $mAddUnhandledParams;

	function __construct( $name, $addUnhandledParams ) {
		$this->mName = $name;
		$this->mAddUnhandledParams = $addUnhandledParams;
	}

	function addParam( $paramName, $value ) {
		$this->mParams[] = new SFWikiPageTemplateParam( $paramName, $value );
	}

	function addUnhandledParams() {
		global $wgRequest;

		if ( !$this->mAddUnhandledParams ) {
			return;
		}

		$templateName = str_replace( ' ', '_', $this->mName );
		$prefix = '_unhandled_' . $templateName . '_';
		$prefixSize = strlen( $prefix );
		foreach ( $wgRequest->getValues() as $key => $value ) {
			if ( strpos( $key, $prefix ) === 0 ) {
				$paramName = urldecode( substr( $key, $prefixSize ) );
				$this->addParam( $paramName, $value );
			}
		}

	}

	function getName() {
		return $this->mName;
	}

	function getParams() {
		return $this->mParams;
	}
}
