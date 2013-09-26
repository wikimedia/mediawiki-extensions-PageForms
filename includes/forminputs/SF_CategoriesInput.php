<?php
/**
 * File holding the SFCategoriesInput class
 *
 * @file
 * @ingroup SF
 */

/**
 * The SFCategoriesInput class.
 *
 * @ingroup SFFormInput
 */
class SFCategoriesInput extends SFCategoryInput {
	public static function getName() {
		return 'categories';
	}

	public static function getOtherPropTypeListsHandled() {
		return array( '_wpg' );
	}


}
