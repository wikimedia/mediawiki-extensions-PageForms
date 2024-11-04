<?php

class PFDateInputWidget extends MediaWiki\Widget\DateInputWidget {

	protected function getJavaScriptClassName() {
		return 'mw.widgets.PFDateInputWidget';
	}

	public function getInputElement( $config ) {
		if ( count( explode( ';', $config['inputFormat'] ?? '' ) ) > 1 ) {
			return parent::getInputElement( $config )
				->setAttributes( [
					'type' => 'text'
				] );
		}
		// Inserts date/month type attribute
		return parent::getInputElement( $config );
	}
}
