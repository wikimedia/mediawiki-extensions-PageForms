jQuery.fn.toggleFormDataDisplay = function() {
	if ( jQuery( this ).is( ":checked" ) ) {
		jQuery('#pf-page-name-formula').css('display', 'none');
		jQuery('#pf-edit-title').css('display', 'block');
	} else {
		jQuery('#pf-page-name-formula').css('display', 'block');
		jQuery('#pf-edit-title').css('display', 'none');
	}
	return this;
};

jQuery( document ).ready( function () {
	jQuery('#pf-two-step-process')
		.toggleFormDataDisplay()
		.click( function() {
			jQuery(this).toggleFormDataDisplay();
		} );
} );
