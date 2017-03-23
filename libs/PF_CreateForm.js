jQuery.fn.displayInputParams = function () {
	var inputParamsDiv = this.closest( '.formField' ).find( '.otherInputParams' );
	jQuery.ajax( {
		url: window.location.href +
			( ( window.location.href.indexOf('?') === -1 ) ? '?' : '&' ) +
			'showinputtypeoptions=' + encodeURIComponent( this.val() ) +
			'&formfield=' + encodeURIComponent( this.attr( 'formfieldid' ) ),
		context: document.body,
		success: function ( data ){
			inputParamsDiv.html( data );
		}
	});
};

jQuery(document).ready( function () {
	jQuery( '.inputTypeSelector' ).change( function () {
		jQuery( this ).displayInputParams();
	} );
	jQuery( '#addsection' ).click( function( event ) {
		if( jQuery( '#sectionname' ).val() === '' ) {
			event.preventDefault();
			jQuery( '#section_error' ).remove();
			var errorSpan = jQuery( '<span class="error" id="section_error"></span>' ).text( mediaWiki.msg( 'pf_blank_error' ) );
			jQuery( '<div/>' ).append( errorSpan ).appendTo( '#sectionerror' );
		}
	} );
} );

jQuery( "input,select" ).keypress( function ( event ) {
	// Don't submit the form if enter is pressed on a text input box or a select.
	return event.keyCode !== 13;
} );
