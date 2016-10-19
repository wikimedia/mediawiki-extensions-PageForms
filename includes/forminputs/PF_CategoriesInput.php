<?php
/**
 * File holding the PFCategoriesInput class
 *
 * This input type is deprecated - in PF 2.6.2, it was replaced with, and
 * became a wrapper for, the "tree" input type.
 *
 * @file
 * @ingroup PF
 */

/**
 * The PFCategoriesInput class.
 *
 * @ingroup PFFormInput
 */
class PFCategoriesInput extends PFTreeInput {
	public static function getName() {
		return 'categories';
	}

	public static function getOtherPropTypeListsHandled() {
		return array( '_wpg' );
	}
}
