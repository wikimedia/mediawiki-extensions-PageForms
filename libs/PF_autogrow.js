/**
 * PF_autogrow.js
 *
 * Allows for 'autogrow' textareas.
 *
 * @author Mklyle
 */

( function ( $, mw ) {
	var io = window.IntersectionObserver ? new IntersectionObserver( ( entries ) => {
		entries.forEach( ( e ) => {
			if ( e.target.offsetParent ) {
				io.unobserve( e.target );
				autoGrow( e.target );
			}
		} );
	} ) : null;

	function minHeight( ta ) {
		var rows = parseInt( $( ta ).data( 'pfAutogrowRows' ), 10 ) || 5;
		var lh = parseFloat( window.getComputedStyle( ta ).lineHeight ) || 20;
		return rows * lh;
	}

	function autoGrow( ta ) {
		if ( !ta.offsetParent ) { // hidden: measure once visible
			if ( io ) {
				io.observe( ta );
			}
			return;
		}
		// With Realtime Preview on, the pinned height lives on the
		// two-pane layout and .wikiEditor-ui-text is 100% inside it.
		var frame = $( ta ).closest( '.ext-WikiEditor-twopanes-TwoPaneLayout' )[ 0 ] ||
			$( ta ).closest( '.wikiEditor-ui-text' )[ 0 ];
		ta.style.height = 'auto';
		var h = Math.max( ta.scrollHeight + 4, minHeight( ta ) );
		if ( frame ) {
			// WikiEditor pins this wrapper's height; grow it and let
			// the textarea fill it so the drag bar keeps working.
			frame.style.height = h + 'px';
			ta.style.height = '100%';
		} else {
			ta.style.height = h + 'px';
		}
	}

	$.fn.autoGrow = function () {
		return this.each( function () {
			var $ta = $( this );
			if ( $ta.closest( '.multipleTemplateStarter' ).length ) {
				return;
			}
			if ( $ta.data( 'pfAutogrowRows' ) === undefined ) {
				$ta.data( 'pfAutogrowRows', this.rows || 5 ); // before WikiEditor strips it
			}
			$ta.off( '.pfAutogrow' ).on( 'input.pfAutogrow focus.pfAutogrow', function () {
				autoGrow( this );
			} );
			autoGrow( this );
		} );
	};

	// WikiEditor may attach (and pin the frame) after autoGrow() ran.
	mw.hook( 'wikiEditor.toolbarReady' ).add( ( $ta ) => {
		if ( $ta.hasClass( 'autoGrow' ) ) {
			setTimeout( () => {
				autoGrow( $ta[ 0 ] );
			}, 0 );
		}
	} );
}( jQuery, mediaWiki ) );
