<?php

/**
 * @covers \PFFormField
 *
 * @author Collins Wandji <collinschuwa@gmail.com>
 */

class PFFormFieldTemplateTest extends MediaWikiUnitTestCase {

	/**
	 * @covers \PFFormField::getInputType
	 */
	public function testGetType() {
		$field = new \PFFormField();
		$this->assertNull( $field->getInputType() );
	}

}
