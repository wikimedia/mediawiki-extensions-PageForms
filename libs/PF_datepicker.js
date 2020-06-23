/**
 * JavaScript code to be used with input type datepicker.
 *
 * @author Sam Wilson
 */

( function( $, oo ) {
    'use strict';

    // Infuse all DateTimeInput widgets.
    mw.loader.using([
        'mediawiki.widgets',
        'mediawiki.widgets.DateInputWidget'
    ]).done(function () {
        $( '.ext-pageforms-datewidget' ).each( function () {
            var widget = oo.ui.infuse( $( this ) );
        } );
    });

} )( jQuery, OO );
