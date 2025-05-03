/**
 * JavaScript for the Page Forms MediaWiki extension.
 *
 * @param $
 * @param mw
 * @license GNU GPL v3+
 * @author Jeroen De Dauw <jeroendedauw at gmail dot com>
 */

( function ( $, mw ) {
	const _this = this;

	this.getPreviewImage = function( args, callback ) {
		$.getJSON(
			mw.config.get( 'wgScriptPath' ) + '/api.php',
			{
				'action': 'query',
				'format': 'json',
				'prop': 'imageinfo',
				'iiprop': 'url',
				'titles': 'File:' + args.title,
				'iiurlwidth': args.width
			},
			( data ) => {
				if ( data.query && data.query.pages ) {
					const pages = data.query.pages;

					for ( const p in pages ) { // object, not an array
						const info = pages[p].imageinfo;
						if ( info && info.length > 0 ) {
							callback( info[0].thumburl );
							return;
						}
					}
				}
				callback( false );
			}
		);
	};

	$( () => {
		const showPreview = function( inputId ) {
			const $input = $( '#' + inputId );
			const $previewDiv = $( '#' + inputId + '_imagepreview' );
			_this.getPreviewImage(
				{
					'title': $input.val(),
					'width': 200
				},
				( url ) => {
					if ( url === false ) {
						$previewDiv.html( '' );
					} else {
						$previewDiv.html( $( '<img />' ).attr( { 'src': url } ) );
					}
				}
			);
		};

		$( '.pfImagePreview' ).each( ( index, domElement ) => {
			const $uploadLink = $( domElement );
			const inputId = $uploadLink.attr( 'data-input-id' );
			const $input = $( '#' + inputId );
			$input.change( () => {
				showPreview( inputId );
			} );
		} );

		mw.hook( 'pf.comboboxChange2' ).add( ( inputId ) => {
			showPreview( inputId );
		} );
	} );
}( jQuery, mediaWiki ) );
