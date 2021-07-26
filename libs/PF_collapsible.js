/**
 * PF_collapsible.js
 *
 * Allows for collapsible fieldsets.
 *
 * This code was originally based heavily on the 'coolfieldset' jQuery plugin:
 * http://w3shaman.com/article/jquery-plugin-collapsible-fieldset
 *
 * Now it's less so, because that code used a .toggle() function that was
 * removed in jQuery 1.9.
 */

function pfHideFieldsetContent($obj){
	$obj.find('div').slideUp( 'medium' );
	$obj.removeClass("pfExpandedFieldset");
	$obj.addClass("pfCollapsedFieldset");
}

function pfShowFieldsetContent($obj){
	$obj.find('div').slideDown( 'medium' );
	$obj.removeClass("pfCollapsedFieldset");
	$obj.addClass("pfExpandedFieldset");
}

jQuery.fn.pfMakeCollapsible = function(){
	this.each(function(){
		var $fieldset = jQuery(this);

		$fieldset.children('legend').click( function() {
			if ($fieldset.hasClass('pfCollapsedFieldset')) {
				pfShowFieldsetContent($fieldset);
			} else {
				pfHideFieldsetContent($fieldset);
			}
		});
		pfHideFieldsetContent($fieldset);
	});
};

jQuery(document).ready(function() {
	jQuery('.pfCollapsibleFieldset').pfMakeCollapsible();
});
