/**
 * Javascript code to be used with extension SemanticForms for popup forms.
 *
 * @author Stephan Gambke
 *
 */

// initialise 
jQuery(function(){

	// register eventhandlers on 'edit' links and buttons

	// register formlink with link
	jQuery('a.popupformlink').click(function(evt){
		return ext.popupform.handlePopupFormLink( this.getAttribute('href'), this );
	});

	// register formlink with button
	jQuery( 'form.popupformlink[method!="post"] input' ).each(function() {
		
		var input = jQuery(this);

		// Yay, IE 4 lines, FF 0 lines
		var target = String (this.getAttribute("onclick"));
		var start = target.indexOf("window.location.href='") + 22;
		var stop = target.indexOf("'", start);
		target = target.substring( start, stop );

		input.data( "target", target ) // extract link target from event handler
		.attr( "onclick", null ) // and remove event handler
		.click( function( evt ){
			return ext.popupform.handlePopupFormLink( jQuery( this ).data( "target" ), this);
		});
	})

	// register formlink with post button
	jQuery( 'form.popupformlink[method="post"]' ).submit(function(evt){
		return ext.popupform.handlePopupFormLink( this.getAttribute( 'action' ), this );
	});


	// register forminput
	jQuery( 'form.popupforminput' ).submit(function(evt){
		return ext.popupform.handlePopupFormInput( this.getAttribute( 'action' ), this );
	});

});

// create ext if it does not exist yet
if ( typeof( window[ 'ext' ] ) == "undefined" ) {
	window[ 'ext' ] = {};
}

window.ext.popupform = new function() {

	var wrapper;
	var background;
	var container;
	var iframe;
	var waitIndicator;
	var instance = 0;

	var doc;
	var docWidth;
	var docHeight;

	var brokenBrowser, brokenChrome;

	function handlePopupFormInput( ptarget, elem ) {

		showForm();

		iframe.one( 'load', function(){
			// attach event handler to iframe
			iframe.bind( 'load', handleLoadFrame );
			return false;
		})

		elem.target = 'popupform-iframe' + instance;
		return true;
	}

	function handlePopupFormLink( ptarget, elem ) {

		showForm();

		// attach event handler to iframe
		iframe.bind( 'load', handleLoadFrame );

		if ( elem.tagName == 'FORM' ) {

			elem.target = 'popupform-iframe' + instance;
			return true;
			
		} else {

			var delim = ptarget.indexOf( '?' );
			var form = document.createElement("form");

			form.target = 'popupform-iframe' + instance;

			// Do we have parameters?
			if ( delim > 0 ) {
				form.action = ptarget.substr( 0, delim );
				var params = String( ptarget.substr( delim + 1 ) ).split("&");
				for ( var i = 0; i < params.length; ++i ) {

					var input = document.createElement("input");
					var param = String( params[i] ).split('=');
					input.type = 'hidden';
					input.name = decodeURIComponent( param[0] );
					input.value = decodeURIComponent( param[1] );
					form.appendChild( input );
					
				}
			} else {
				form.action = ptarget;
			}

			document.getElementsByTagName('body')[0].appendChild(form);
			form.submit();
			document.getElementsByTagName('body')[0].removeChild(form);

			return false;
		}
	}

	function showForm() {

		instance++;

		brokenChrome =
			( navigator.userAgent.indexOf("Chrome") >= 0 &&
			navigator.platform.indexOf("Linux x86_64") >= 0 );

		brokenBrowser= jQuery.browser.msie ||brokenChrome;

		var maxZIndex = 0;

		jQuery("*").each(function() {
			var curr = parseInt( jQuery( this ).css( "z-index" ) );
			maxZIndex = curr > maxZIndex ? curr : maxZIndex;
		});


		wrapper = jQuery( "<div class='popupform-wrapper' >" );
		background = jQuery( "<div class='popupform-background' >" );

		var waitIndicatorWrapper = jQuery(  "<div class='popupform-loading'>" );

		waitIndicator = jQuery(  "<div class='popupform-loadingbg'></div><div class='popupform-loadingfg'></div>" );

		var anchor = jQuery( "<div class='popupform-anchor' >" );

		container = jQuery( "<div class='popupform-container' >" );
		iframe = jQuery( "<iframe class='popupform-innerdocument' name='popupform-iframe" + instance + "' id='popupform-iframe" + instance + "' >");

		var closeBtn = jQuery( "<div class='popupform-close'></div> " );

		// initially hide background and waitIndicator
		if (brokenChrome) background.css("background", "transparent");
		else background.css("opacity", 0.0);

		waitIndicator.hide();
		container.hide()

		// insert background and wait indicator into wrapper and all into document
		waitIndicatorWrapper
		.append( waitIndicator );

		container
		.append( closeBtn )
		.append( iframe );

		anchor
		.append(container);

		wrapper
		.css( "z-index", maxZIndex + 1 )
		.append( background )
		.append( waitIndicatorWrapper )
		.append( anchor )
		.appendTo( "body" );

		// fade background in
		if ( !brokenChrome ) background.fadeTo( 400, 0.3 );
		fadeIn( waitIndicator );

		// attach event handler to close button
		closeBtn.click( handleCloseFrame );

	// TODO: wrapper must be set to max z-index;

	}

	function handleLoadFrame( event ){
		
		var iframe = jQuery( event.target );
		var iframecontents = iframe.contents();
		
		if ( brokenChrome ) container[0].style.visibility = "hidden";
		else container[0].style.opacity = 0;

		container.show();

		// GuMaxDD has #content but keeps headlines in #gumax-content-body
		var content = iframecontents.find("#gumax-content-body");

		// normal skins use #content (e.g. Vector, Monobook)
		if ( content.length == 0 ) content = iframecontents.find("#content");

		// some skins use #mw_content (e.g. Modern)
		if ( content.length == 0 ) content = iframecontents.find("#mw_content");

		// this is not a normal MW page (or it uses an unknown skin)
		if ( content.length == 0 ) content = iframecontents.find("body");

		// the huge left margin looks ugly in Vector, reduce it
		// (How does this look for other skins?)
		var siblings = content
		.css( {
			margin: 0,
			padding: "1em",
			width: "auto",
			height: "auto",
			minWidth: "0px",
			minHeight:"0px"
		} )
		.parents().css( {
			margin: 0,
			padding: 0,
			width: "auto",
			height: "auto",
			minWidth: "0px",
			minHeight:"0px"
		})
		.andSelf().siblings();

		if ( jQuery.browser.msie && jQuery.browser.version < "6" ) {
			siblings.hide();
		} else {
			siblings
			.each( function(){
				var elem = jQuery(this);
//				if ( ( elem.outerWidth(true) > 0 && elem.outerHeight(true) > 0 ) &&
				if ( getStyle(this, "display") != "none"
					&& ( getStyle( this, "width") != "0px" || getStyle( this, "height") != "0px" )
					&& ! (
						( this.offsetLeft + elem.outerWidth(true) < 0 ) ||		// left of document
						( this.offsetTop + elem.outerHeight(true) < 0 )  || // above document
						( this.offsetLeft > 100000 ) ||		// right of document
						( this.offsetTop > 100000 )  // below document
						)
					) {

					jQuery(this).hide();
				//					css({
				//						height : "0px",
				//						width : "0px",
				//						minWidth : "0px",
				//						minHeight : "0px",
				//						margin : "0px",
				//						padding : "0px"
				//						border : "none",
				//						overflow: "hidden"
				//					//position: "static"
				//					});
				}
				if ( ( this.offsetLeft + elem.outerWidth() < 0 ) ||
					( this.offsetTop + elem.outerHeight() < 0 )
					) {
					this.style.left = (-elem.outerWidth(true)) + "px";
					this.style.top = (-elem.outerHeight(true)) + "px";
				}
			});
		//.children().css("position", "static");
		}

		// find content document
		doc = iframe[0].contentWindow || iframe[0].contentDocument;

		if (doc.document) {
			doc = doc.document;
		}

		// first try if the content enforces its dimensions (e.g. GuMaxDD)
		docWidth = content.outerWidth(true);
		docHeight = content.outerHeight(true);

		// then try if it grows to its dimensions given enough space (e.g. Vector)
		var origPos = content[0].style.position;
		content[0].style.position = "fixed";

		if ( content.outerWidth(true) > docWidth || content.outerHeight(true) > docHeight ) {
			docWidth = content.outerWidth(true);
			docHeight = content.outerHeight(true);
		}

		content[0].style.position = origPos;

		// default for broken browsers
		if ( docWidth == 0 || docHeight == 0 ) {

			docWidth = jQuery(window).width();
			docHeight = jQuery(window).height();
			
		}


		// adjust frame size to dimensions just calculated
		adjustFrameSize();

		// and attach event handler to adjust frame size every time the window
		// size changes
		jQuery( window ).resize( adjustFrameSize );

		var form = content.find("#sfForm");
		var innerwdw = window.frames['popupform-iframe' + instance];

		if (form.length > 0) {

			var submitok = false;
			var innersubmitprocessed = false;

			// catch form submit event
			form
			.bind( "submit", function( event ){

				var interval = setInterval(function(){

					if ( innersubmitprocessed ) {
						clearInterval( interval );
						innersubmitprocessed = false;
						if ( submitok ) handleSubmitData( event );
					}

				}, 10)
				event.stopPropagation();
				return false;
				
			});

			// catch inner form submit event
			innerwdw.jQuery(form[0])
			.bind( "submit", function( event ) {
				submitok = event.result;
				innersubmitprocessed = true;
				return false;
			})
		}

		innerwdw.jQuery( innerwdw[0] ).unload(function (event) {
			return false;
		});

		// find all links. Have to use inner jQuery so event.result below
		// reflects the result of inner event handlers. We (hopefully) come last
		// in the chain of event handlers as we only attach when the frame is
		// already completely loaded, i.e. every inner event handler is already
		// attached.
		var allLinks = innerwdw.jQuery("a[href]");

		// catch 'Cancel'-Link (and other 'back'-links) and close frame instead of going back
		var backlinks = allLinks.filter('a[href="javascript:history.go(-1);"]');
		backlinks.click(handleCloseFrame);

		// promote any other links to open in main window, prevent nested browsing
		allLinks
		.not('a[href*="javascript:"]') // scripted links
		.not('a[target]')              // targeted links
		.not('a[href^="#"]')           // local links
		.click(function(event){
			if ( event.result != false ) {  // if not already caught by somebody else
				closeFrameAndFollowLink( event.target.getAttribute('href') )
			}
			return false;
		});

		// finally show the frame
		fadeOut ( waitIndicator, function(){
			fadeTo( container, 400, 1 );
		});

		return false;
		
	}

	function handleSubmitData( event ){

		fadeOut( container, function() {
			fadeIn( waitIndicator );
		});

		var form = jQuery( event.target );
		var formdata = form.serialize() + "&wpSave=" + escape(form.find("#wpSave").attr("value"));

		// Send form data off. SF will send back a fake edit page
		//
		// Normally we should check this.action first and only if it is empty
		// revert to this.ownerDocument.URL. Tough luck, IE does not return an
		// empty action but fills in some bogus
		jQuery.post( event.target.ownerDocument.URL , formdata, handleInnerSubmit);

		return false;


		function handleInnerSubmit ( returnedData, textStatus, XMLHttpRequest ) {


			// find form in fake edit page
			var innerform = jQuery("<div>" + returnedData + "</div>").find("form");

			// check if we got an error page
			if ( innerform.length == 0 ) {

				form.unbind( event );

				var iframe = container.find("iframe");
				var doc = iframe[0].contentWindow || iframe[0].contentDocument;
				if (doc.document) {
					doc = doc.document;
				}

				doc.open();
				doc.write(returnedData);
				doc.close();

				return false;
			}

			// Send the form data off, we do not care for the returned data
			var innerformdata = innerform.serialize();
			jQuery.post( innerform.attr("action"), innerformdata );

			// build new url for outer page (we have to ask for a purge)

			var url = location.href;

			// does a querystring exist?
			var start = url.indexOf("action=");

			if ( start >= 0 ) {

				var stop = url.indexOf("&", start);

				if ( stop >= 0 ) url = url.substr( 0, start - 1 ) + url.substr(stop + 1);
				else url = url.substr( 0, start - 1 );

			}

			var form = jQuery('<form action="' + url + '" method="POST"><input type="hidden" name="action" value="purge"></form>')
			.appendTo('body');

			form
			.submit();

			fadeOut( container, function(){
				fadeIn( waitIndicator );
			});

			return false;

		}
	}

	function adjustFrameSize() {

		var availW = jQuery(window).width();
		var availH = jQuery(window).height();

		var w, h;

		// Standard max height/width is 80% of viewport, but we will allow
		// up to 85% to avoid scrollbars with nearly nothing to scroll
		if ( docWidth > availW * .85 || docHeight > availH * .85 ) {

			iframe[0].style.overflow = "auto";

			// For now, just ignore docWidth and docHeight - at
			// least on Vector, they're getting set to values
			// that are far too small.
			// TODO: fix this
			//if ( docWidth > availW * .85 ) {
				w = ( availW * .8 );
			//} else {
			//	w = docWidth + 20;
			//}

			//if ( docHeight > availH * .85 ) {
				h = ( availH * .8 );
			//} else {
			//	h = docHeight + 20;
			//}

		} else {
			iframe[0].style.overflow = "hidden";
			w = docWidth;
			h = docHeight;
		}

		with ( container[0].style ) {
			width = ( w ) + "px";
			height = ( h ) + "px";
			top = (( - h ) / 2) + "px";
			left = (( - w ) / 2) + "px";
			}

	}

	function closeFrameAndFollowLink( link ){

		fadeOut( container, function(){
			fadeIn ( waitIndicator );
			window.location.href = link;
		});

	}

	function handleCloseFrame( event ){

		jQuery(window).unbind( "resize", adjustFrameSize )

		fadeOut( container, function(){
			background.fadeOut( function(){
				wrapper.remove();
			});
		});
		return false;
	}

	// Saw it on http://robertnyman.com/2006/04/24/get-the-rendered-style-of-an-element
	// and liked it
	function getStyle(oElm, strCssRule){
		var strValue = "";
		if(document.defaultView && document.defaultView.getComputedStyle){
			strValue = document.defaultView.getComputedStyle(oElm, "").getPropertyValue(strCssRule);
		}
		else if(oElm.currentStyle){
			strCssRule = strCssRule.replace(/\-(\w)/g, function (strMatch, p1){
				return p1.toUpperCase();
			});
			strValue = oElm.currentStyle[strCssRule];
		}
		return strValue;
	}

	function fadeIn(elem, callback ) {
		// no fading for broken browsers
		if ( brokenBrowser ){

			elem.show();
			if ( callback ) callback();

		} else {

			// what an ugly hack
			if ( elem === waitIndicator ) elem.fadeIn( 200, callback );
			else elem.fadeIn( callback );

		}
	}

	function fadeOut(elem, callback ) {
		// no fading for broken browsers
		if ( brokenBrowser ){

			elem.hide();
			if ( callback ) callback();

		} else {

			// what an ugly hack
			if ( elem === waitIndicator ) elem.fadeOut( 200, callback );
			else elem.fadeOut( callback );

		}
	}

	function fadeTo(elem, time, target, callback) {
		// no fading for broken browsers
		if ( brokenBrowser ){

			if (target > 0) elem[0].style.visibility = "visible";
			else  elem[0].style.visibility = "hidden";

			if ( callback ) callback();

		} else {

			elem.fadeTo(time, target, callback);

		}

	}

	// export public funcitons
	this.handlePopupFormInput = handlePopupFormInput;
	this.handlePopupFormLink = handlePopupFormLink;

}
