<?php
/**
 * File holding the PFCategoryInput class
 *
 * This input type is deprecated - in PF 2.6.2, it was replaced with, and
 * became a wrapper for, the "tree" input type.
 *
 * @file
 * @ingroup PF
 */

/**
 * The PFCategoryInput class.
 *
 * @ingroup PFFormInput
 */
class PFCategoryInput extends PFTreeInput {
	public static function getName() {
		return 'category';
	}

	public static function getOtherPropTypesHandled() {
		return array( '_wpg' );
	}
}
