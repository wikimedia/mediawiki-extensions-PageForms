jQuery.fn.displayInputParams = function () {
	const inputParamsDiv = this.closest( '.formField' ).find( '.otherInputParams' );
	jQuery.ajax( {
		url: mw.util.wikiScript() + '?title=' + mw.config.get('wgPageName') +
			'&showinputtypeoptions=' + encodeURIComponent( $(this).find('select').val() ) +
			'&formfield=' + encodeURIComponent( this.attr( 'id' ) ),
		context: document.body,
		success: function ( data ){
			inputParamsDiv.html( data );
		}
	});
};

jQuery( () => {
	jQuery( '.inputTypeSelector' ).change( function () {
		jQuery( this ).displayInputParams();
	} );

	jQuery( 'span#pfAddTemplateButton' ).click( () => {
		jQuery( 'button[name="add_field"]' ).attr( 'value', 'true' );
	} );

	jQuery( 'span#pfAddSectionButton' ).click( ( event ) => {
		if( jQuery( 'input[name="sectionname"]' ).val() === '' ) {
			event.preventDefault();
			jQuery( '#section_error' ).remove();
			const errorMessage = new OO.ui.MessageWidget( {
				type: 'error',
				inline: true,
				label: mediaWiki.msg( 'pf_blank_error' )
			} )
			const errorSpan = '<span class="error" id="section_error"></span>';
			jQuery( 'div#sectionerror' ).append(errorSpan);
			jQuery( 'span#section_error' ).append( errorMessage.$element );
		} else {
			jQuery( 'button[name="add_section"]' ).attr( 'value', 'true' );
		}
	} );

	jQuery( 'span#pfRemoveTemplateButton' ).click( () => {
		jQuery( 'span#pfRemoveTemplateButton' ).find( 'button' ).attr( 'value', 'true' );
	} );

	jQuery( 'span#pfRemoveSectionButton' ).click( () => {
		jQuery( 'span#pfRemoveSectionButton' ).find( 'button' ).attr( 'value', 'true' );
	} );

	// this is done as the OOUI's Checkbox behave differently than the normal checkbox
	jQuery( 'div.templateForm' ).find( 'input[type="checkbox"]' ).click( function() {
		if ( jQuery( this ).attr( 'value' ) == 'on' ){
			jQuery( this ).attr( 'value', '' );
		} else {
			jQuery( this ).attr( 'value', 'on' );
		}
	} );
} );

jQuery( "input,select" ).keypress( ( event ) =>
	// Don't submit the form if enter is pressed on a text input box or a select.
	 event.keyCode !== 13
 );
